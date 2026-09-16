<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/** What the parser read, and what it could not. Both, always. */
final class ParseResult
{
    /**
     * @param  array<int, ParseError>  $errors
     */
    public function __construct(
        public readonly Document $document,
        public readonly array $errors = [],
    ) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }
}
