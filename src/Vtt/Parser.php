<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Reads a WebVTT file into a Document. Never throws.
 *
 * Follows the file-format section of the WebVTT spec block by block: a block
 * is a run of non-blank lines, and the first line says what it is. What
 * cannot be read is recorded with its line number and skipped, and reading
 * continues, because an editor has to open the file to fix it.
 */
final class Parser
{
    public function parse(string $vtt): ParseResult
    {
        // A file from an unknown tool can be anything; invalid UTF-8 would
        // make the output unencodable as JSON and a message unreadable.
        $vtt = mb_scrub($vtt, 'UTF-8');

        if (str_starts_with($vtt, "\xEF\xBB\xBF")) {
            $vtt = substr($vtt, 3);
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $vtt));
        $errors = [];
        $header = '';
        $preamble = [];

        // The signature: `WEBVTT`, alone or followed by a space or tab and
        // anything. `WEBVTTfoo` is not it, and neither is a blank file.
        if (preg_match('/^WEBVTT(?:[ \t](.*))?$/', $lines[0], $m)) {
            $header = $m[1] ?? '';
            $i = 1;
        } else {
            $errors[] = new ParseError(1, 'missing-header', 'The file does not start with WEBVTT. The first line must be "WEBVTT", on its own or followed by a space and a title.');
            // Read on regardless: the cues are still worth showing.
            $i = 0;
        }

        // Anything between the signature and the first blank line is header
        // text a player ignores. Kept so it is written back.
        if ($i === 1) {
            while ($i < count($lines) && $lines[$i] !== '') {
                // A timing line here means the blank line after the
                // signature is missing. The spec forbids an arrow in the
                // header; reading the cue is more use than losing it.
                if (str_contains($lines[$i], '-->')) {
                    $errors[] = new ParseError($i + 1, 'missing-blank-line', 'The WEBVTT line must be followed by a blank line before the first cue.');

                    break;
                }

                $preamble[] = $lines[$i];
                $i++;
            }
        }

        $blocks = [];
        $seenCue = false;

        while ($i < count($lines)) {
            if ($lines[$i] === '') {
                $i++;

                continue;
            }

            $startLine = $i + 1;
            $block = [];

            while ($i < count($lines) && $lines[$i] !== '') {
                $block[] = $lines[$i];
                $i++;
            }

            $first = $block[0];

            if (preg_match('/^NOTE(?:[ \t]|$)/', $first)) {
                $inline = strlen($first) > 4;
                $text = $inline ? substr($first, 5) : '';
                $rest = array_slice($block, 1);

                if ($rest !== []) {
                    $text = ($inline ? $text."\n" : '').implode("\n", $rest);
                }

                $blocks[] = new Note($text, $inline, $startLine);

                continue;
            }

            if (preg_match('/^STYLE[ \t]*$/', $first)) {
                if ($seenCue) {
                    $errors[] = new ParseError($startLine, 'block-out-of-place', 'A STYLE block after the first cue is ignored by players. Move it above the cues.');

                    continue;
                }

                $blocks[] = new Style(implode("\n", array_slice($block, 1)), $startLine);

                continue;
            }

            if (preg_match('/^REGION[ \t]*$/', $first)) {
                if ($seenCue) {
                    $errors[] = new ParseError($startLine, 'block-out-of-place', 'A REGION block after the first cue is ignored by players. Move it above the cues.');

                    continue;
                }

                $region = new Region(CueSettings::parse(implode(' ', array_slice($block, 1)))->values, $startLine);

                if ($region->id() === null) {
                    $errors[] = new ParseError($startLine, 'region-missing-id', 'This REGION has no id, so no cue can use it.');
                }

                $blocks[] = $region;

                continue;
            }

            // A cue, or several: the spec starts a new cue at any payload line
            // containing an arrow, so one block can hold more than one.
            $seenCue = true;
            $offset = 0;

            while ($offset < count($block)) {
                $id = null;
                $timingAt = $offset;

                if (! str_contains($block[$offset], '-->')) {
                    if (! isset($block[$offset + 1]) || ! str_contains($block[$offset + 1], '-->')) {
                        $errors[] = new ParseError($startLine + $offset, 'cue-missing-timing', 'This block has no timing line ("00:00:01.000 --> 00:00:02.000"), so it cannot be a cue.');

                        break;
                    }

                    $id = $block[$offset];
                    $timingAt = $offset + 1;
                }

                $payload = [];
                $next = $timingAt + 1;

                while ($next < count($block) && ! str_contains($block[$next], '-->')) {
                    $payload[] = $block[$next];
                    $next++;
                }

                $timing = $this->timing($block[$timingAt], $startLine + $timingAt, $errors);

                if ($timing !== null) {
                    [$start, $end, $settings] = $timing;
                    $blocks[] = new Cue($start, $end, implode("\n", $payload), $id, $settings, $startLine + $timingAt);
                }

                $offset = $next;
            }
        }

        return new ParseResult(new Document($header, $preamble, $blocks), $errors);
    }

    /**
     * @param  array<int, ParseError>  $errors
     * @return array{0: Timestamp, 1: Timestamp, 2: CueSettings}|null
     */
    private function timing(string $line, int $number, array &$errors): ?array
    {
        if (! preg_match('/^(\S+)[ \t]*-->[ \t]*(\S+)(?:[ \t]+(.*))?$/', $line, $m)) {
            $errors[] = new ParseError($number, 'malformed-timestamp', 'The timing line could not be read: "'.$this->excerpt($line).'". Expected "00:00:01.000 --> 00:00:02.000".');

            return null;
        }

        $start = Timestamp::parse($m[1]);
        $end = Timestamp::parse($m[2]);

        if ($start === null || $end === null) {
            $bad = $start === null ? $m[1] : $m[2];
            $errors[] = new ParseError($number, 'malformed-timestamp', 'The timestamp "'.$this->excerpt($bad).'" could not be read. Expected "00:00:01.000" (hours optional, three decimals, a full stop and not a comma).');

            return null;
        }

        return [$start, $end, CueSettings::parse($m[3] ?? '')];
    }

    private function excerpt(string $text): string
    {
        $text = trim(preg_replace('/[\x00-\x1F\x7F]/', '?', $text) ?? '');

        return mb_strlen($text) > 40 ? mb_substr($text, 0, 37).'...' : $text;
    }
}
