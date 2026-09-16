<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * A `NOTE` block. `$inline` records whether the text began on the `NOTE`
 * line itself, so it is written back the same way.
 */
final class Note
{
    public function __construct(
        public readonly string $text,
        public readonly bool $inline = false,
        public readonly int $line = 0,
    ) {}
}
