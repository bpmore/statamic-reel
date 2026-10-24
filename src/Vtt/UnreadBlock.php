<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * A STYLE or REGION block after the first cue, kept as it was written.
 *
 * Players ignore such a block, so it is not a `Style` or a `Region`: a cue
 * placed in a region defined here is placed in nothing. But it is someone's
 * text, and the parser used to skip it, so the next save from the editor
 * deleted it. It stays in the document, in place, and is written back byte
 * for byte. The editor does not show it; the error says where it is.
 */
final class UnreadBlock
{
    public const CODE = 'block-out-of-place';

    public function __construct(
        /** `STYLE` or `REGION`, the word the block starts with */
        public readonly string $kind,
        /** every line of the block, the first included */
        public readonly string $text,
        /** The line the block starts on, 1-based, or 0 when not read from a file. */
        public readonly int $line = 0,
    ) {}

    public function problem(): string
    {
        return "A {$this->kind} block after the first cue is ignored by players. Move it above the cues.";
    }

    /**
     * @return array{kind: string, text: string, line: int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'text' => $this->text, 'line' => $this->line];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['kind'] ?? ''),
            (string) ($data['text'] ?? ''),
            (int) ($data['line'] ?? 0),
        );
    }
}
