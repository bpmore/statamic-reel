<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * A cue whose timing could not be read, kept as it was written.
 *
 * The parser used to skip these, which meant the editor never showed them
 * and a save wrote the file without them: an SRT comma on one timestamp
 * cost the cue its text. So the block stays in the document, in place, and
 * is written back byte for byte until a person fixes the timing. It is not
 * a `Cue`, because nothing that measures cues can measure one with no time,
 * but it is counted as one, so cue numbers match the file and the editor.
 *
 * `$timing` is the timing line as written, or empty for a block that had no
 * timing line at all.
 */
final class UnreadCue
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $timing,
        public readonly string $text = '',
        /** The line the timing is on (or the block starts on, with no timing), 1-based, or 0 when not read from a file. */
        public readonly int $line = 0,
    ) {}

    public function code(): string
    {
        return $this->timing === '' ? 'cue-missing-timing' : 'malformed-timestamp';
    }

    /** What is wrong, in the parser's own words. */
    public function problem(): string
    {
        return $this->timing === ''
            ? Parser::MISSING_TIMING
            : (Parser::timingProblem($this->timing) ?? 'The timing line could not be read.');
    }

    /**
     * The timing line split the way the editor shows a cue: start, end, and
     * the rest as settings text. Loose on purpose: this is what a person
     * typed, and the editor has to show all of it to be fixed.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function parts(): array
    {
        if (! str_contains($this->timing, '-->')) {
            return [trim($this->timing), '', ''];
        }

        [$start, $rest] = explode('-->', $this->timing, 2);
        $rest = preg_split('/[ \t]+/', trim($rest), 2) ?: [''];

        return [trim($start), $rest[0], $rest[1] ?? ''];
    }

    /**
     * @return array{id: ?string, start: string, end: string, settings: string, text: string, line: int}
     */
    public function toArray(): array
    {
        [$start, $end, $settings] = $this->parts();

        return ['id' => $this->id, 'start' => $start, 'end' => $end, 'settings' => $settings, 'text' => $this->text, 'line' => $this->line];
    }

    /**
     * A cue from the editor whose start or end is not a timestamp yet.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $settings = $data['settings'] ?? '';
        $settings = is_string($settings) ? trim($settings) : (new CueSettings(array_map('strval', (array) $settings)))->toString();

        $start = trim((string) ($data['start'] ?? ''));
        $end = trim((string) ($data['end'] ?? ''));
        $timing = $start === '' && $end === '' ? '' : trim($start.' --> '.$end.($settings !== '' ? ' '.$settings : ''));

        $id = $data['id'] ?? null;

        return new self(
            $id === null || $id === '' ? null : (string) $id,
            $timing,
            (string) ($data['text'] ?? ''),
            (int) ($data['line'] ?? 0),
        );
    }
}
