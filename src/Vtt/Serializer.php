<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Writes a Document back out as WebVTT.
 *
 * Text a person wrote is written as they wrote it: cue payloads, notes and
 * style sheets are kept verbatim, timestamps keep or omit their hours as
 * they had them, and settings keep their order. Parse a file and write it
 * and the diff should be empty; the parser test holds it to that.
 */
final class Serializer
{
    public function serialize(Document $document): string
    {
        $out = 'WEBVTT'.($document->header !== '' ? ' '.$document->header : '')."\n";

        foreach ($document->preamble as $line) {
            $out .= $line."\n";
        }

        foreach ($document->blocks as $block) {
            $out .= "\n".$this->block($block)."\n";
        }

        return $out;
    }

    private function block(Cue|UnreadCue|Note|Region|Style $block): string
    {
        // Written exactly as it was read, until a person fixes the timing.
        if ($block instanceof UnreadCue) {
            return implode("\n", array_filter([$block->id, $block->timing, $block->text], fn ($part) => $part !== null && $part !== ''));
        }

        if ($block instanceof Cue) {
            $lines = [];

            if ($block->id !== null) {
                $lines[] = $block->id;
            }

            $timing = $block->start->format().' --> '.$block->end->format();
            $lines[] = $block->settings->isEmpty() ? $timing : $timing.' '.$block->settings->toString();

            if ($block->text !== '') {
                $lines[] = $block->text;
            }

            return implode("\n", $lines);
        }

        if ($block instanceof Note) {
            if ($block->inline) {
                return 'NOTE '.$block->text;
            }

            return $block->text === '' ? 'NOTE' : "NOTE\n".$block->text;
        }

        if ($block instanceof Region) {
            $lines = ['REGION'];

            foreach ($block->settings as $name => $value) {
                $lines[] = $name.':'.$value;
            }

            return implode("\n", $lines);
        }

        return $block->css === '' ? 'STYLE' : "STYLE\n".$block->css;
    }
}
