<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/** A `STYLE` block, kept as the CSS text it was. Nothing here reads it. */
final class Style
{
    public function __construct(
        public readonly string $css,
        public readonly int $line = 0,
    ) {}
}
