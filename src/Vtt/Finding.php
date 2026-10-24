<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * One thing wrong, or worth a look, in a caption file.
 *
 * `error` is a hard failure: a player will misbehave or the file breaks the
 * format. `warning` is a quality finding against a captioning standard, and
 * the standard is named in the data so an editor can disagree with the
 * standard rather than with the tool.
 */
final class Finding
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @param  int|null  $cue  the cue's number in the file from zero, unread cues counted (`Document::cueNumbers()`), when the finding is about one
     * @param  int|null  $line  the line in the file, when known
     * @param  array<string, mixed>  $data  the numbers behind the message
     */
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $message,
        public readonly ?int $cue = null,
        public readonly ?int $line = null,
        public readonly array $data = [],
    ) {}

    public static function error(string $code, string $message, ?int $cue = null, ?int $line = null, array $data = []): self
    {
        return new self($code, self::ERROR, $message, $cue, $line, $data);
    }

    public static function warning(string $code, string $message, ?int $cue = null, ?int $line = null, array $data = []): self
    {
        return new self($code, self::WARNING, $message, $cue, $line, $data);
    }

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    /**
     * @return array{code: string, severity: string, message: string, cue: ?int, line: ?int, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'cue' => $this->cue,
            'line' => $this->line,
            'data' => $this->data,
        ];
    }
}
