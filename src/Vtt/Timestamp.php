<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * A WebVTT timestamp: `HH:MM:SS.mmm` or `MM:SS.mmm`, as a millisecond count.
 *
 * Whether the hours field was written is kept, so a file written without it is
 * written back without it. Two timestamps compare by milliseconds only; the
 * hours flag is how it is spelled, not what it means.
 */
final class Timestamp
{
    private function __construct(
        public readonly int $milliseconds,
        public readonly bool $withHours,
    ) {}

    /**
     * Null for anything that is not a timestamp, never an exception. The
     * parser reports the line; this only has to say no.
     *
     * The spec allows more than two digits of hours and exactly two of
     * minutes and seconds, three of milliseconds, with minutes and seconds
     * below sixty. An SRT comma is the malformation seen most often and is
     * refused like any other.
     */
    public static function parse(string $text): ?self
    {
        if (! preg_match('/^(?:(\d{2,}):)?(\d{2}):(\d{2})\.(\d{3})$/', $text, $m)) {
            return null;
        }

        $minutes = (int) $m[2];
        $seconds = (int) $m[3];

        if ($minutes > 59 || $seconds > 59) {
            return null;
        }

        $hours = $m[1] === '' ? 0 : (int) $m[1];

        return new self(
            ((($hours * 60) + $minutes) * 60 + $seconds) * 1000 + (int) $m[4],
            $m[1] !== '',
        );
    }

    public static function fromMilliseconds(int $milliseconds, bool $withHours = true): self
    {
        return new self(max(0, $milliseconds), $withHours);
    }

    /**
     * Written back the way it came in. A value of an hour or more always
     * carries the hours field, because it cannot be written without one.
     */
    public function format(): string
    {
        $hours = intdiv($this->milliseconds, 3_600_000);
        $minutes = intdiv($this->milliseconds % 3_600_000, 60_000);
        $seconds = intdiv($this->milliseconds % 60_000, 1000);
        $ms = $this->milliseconds % 1000;

        if ($this->withHours || $hours > 0) {
            return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $ms);
        }

        return sprintf('%02d:%02d.%03d', $minutes, $seconds, $ms);
    }

    public function equals(self $other): bool
    {
        return $this->milliseconds === $other->milliseconds;
    }
}
