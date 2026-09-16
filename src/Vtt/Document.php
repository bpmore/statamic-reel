<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * A caption file: the header, then blocks in the order they were written.
 *
 * Blocks stay in one ordered list rather than being sorted into cues, notes,
 * regions and styles, because a note between two cues belongs between those
 * two cues, and the person who wrote it there will notice if it moves.
 */
final class Document
{
    /**
     * @param  string  $header  the text after `WEBVTT` on the first line
     * @param  array<int, string>  $preamble  the lines between the header and the first blank line
     * @param  array<int, Cue|Note|Region|Style>  $blocks
     */
    public function __construct(
        public readonly string $header = '',
        public readonly array $preamble = [],
        public readonly array $blocks = [],
    ) {}

    /** @return array<int, Cue> in file order, indexed from zero */
    public function cues(): array
    {
        return array_values(array_filter($this->blocks, fn ($b) => $b instanceof Cue));
    }

    /** @return array<string, Region> by id; a region with no id is unreachable and left out */
    public function regions(): array
    {
        $regions = [];

        foreach ($this->blocks as $block) {
            if ($block instanceof Region && $block->id() !== null) {
                $regions[$block->id()] = $block;
            }
        }

        return $regions;
    }

    /** The end of the last-ending cue, in milliseconds. Zero for a file with no cues. */
    public function durationMs(): int
    {
        $end = 0;

        foreach ($this->cues() as $cue) {
            $end = max($end, $cue->end->milliseconds);
        }

        return $end;
    }

    /**
     * The same document with its cues replaced, in place, and every other
     * block where it was. The editor saves cues; it never touches a note.
     *
     * @param  array<int, Cue>  $cues
     */
    public function withCues(array $cues): self
    {
        $cues = array_values($cues);
        $blocks = [];
        $i = 0;

        foreach ($this->blocks as $block) {
            if ($block instanceof Cue) {
                if (isset($cues[$i])) {
                    $blocks[] = $cues[$i];
                }
                $i++;
            } else {
                $blocks[] = $block;
            }
        }

        // More cues than there were: the new ones go on the end.
        for (; $i < count($cues); $i++) {
            $blocks[] = $cues[$i];
        }

        return new self($this->header, $this->preamble, $blocks);
    }

    /**
     * @return array{header: string, preamble: array<int, string>, blocks: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        $blocks = [];

        foreach ($this->blocks as $block) {
            $blocks[] = match (true) {
                $block instanceof Cue => ['type' => 'cue'] + $block->toArray(),
                $block instanceof Note => ['type' => 'note', 'text' => $block->text, 'inline' => $block->inline],
                $block instanceof Region => ['type' => 'region', 'settings' => $block->settings],
                $block instanceof Style => ['type' => 'style', 'css' => $block->css],
            };
        }

        return ['header' => $this->header, 'preamble' => $this->preamble, 'blocks' => $blocks];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $blocks = [];

        foreach ((array) ($data['blocks'] ?? []) as $block) {
            $block = (array) $block;

            $blocks[] = match ($block['type'] ?? 'cue') {
                'note' => new Note((string) ($block['text'] ?? ''), (bool) ($block['inline'] ?? false)),
                'region' => new Region(array_map('strval', (array) ($block['settings'] ?? []))),
                'style' => new Style((string) ($block['css'] ?? '')),
                default => Cue::fromArray($block),
            };
        }

        return new self(
            (string) ($data['header'] ?? ''),
            array_values(array_map('strval', (array) ($data['preamble'] ?? []))),
            $blocks,
        );
    }
}
