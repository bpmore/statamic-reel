<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Something the parser could not read, with the line it was on.
 *
 * A structured value rather than an exception, because a caption file with
 * one bad line has hundreds of good ones, and the editor has to show all of
 * them with the bad one marked.
 */
final class ParseError
{
    public function __construct(
        public readonly int $line,
        public readonly string $code,
        public readonly string $message,
    ) {}

    /**
     * @return array{line: int, code: string, message: string}
     */
    public function toArray(): array
    {
        return ['line' => $this->line, 'code' => $this->code, 'message' => $this->message];
    }
}
