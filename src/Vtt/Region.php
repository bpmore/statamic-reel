<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * A `REGION` block: a named box cues can be placed in.
 *
 * The one setting the validator cares about is `lines`, because a region with
 * room for more than one line is where overlapping cues are legitimate: that
 * is how roll-up captions are written. The spec's default is three.
 */
final class Region
{
    public const DEFAULT_LINES = 3;

    /**
     * @param  array<string, string>  $settings  by name, in file order
     */
    public function __construct(
        public readonly array $settings = [],
        public readonly int $line = 0,
    ) {}

    public function id(): ?string
    {
        $id = $this->settings['id'] ?? null;

        return $id === null || $id === '' ? null : $id;
    }

    public function lines(): int
    {
        $lines = $this->settings['lines'] ?? null;

        return $lines !== null && ctype_digit($lines) ? (int) $lines : self::DEFAULT_LINES;
    }

    /** Whether two cues can show at once in this region. */
    public function allowsStacking(): bool
    {
        return $this->lines() >= 2;
    }

    /**
     * @return array{settings: array<string, string>}
     */
    public function toArray(): array
    {
        return ['settings' => $this->settings];
    }
}
