<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * The settings after a cue's timings: `line:0 position:50% align:start`.
 *
 * Kept in the order written, unknown names included, so a file is written
 * back as it was. The spec says a player ignores a setting it does not
 * understand; an editor that dropped it would be throwing away something the
 * author wrote for a player that does.
 */
final class CueSettings
{
    /**
     * @param  array<string, string>  $values  by name, in file order
     */
    public function __construct(public readonly array $values = []) {}

    /**
     * Anything that is not `name:value` is dropped silently, as the spec's
     * parser drops it. A name given twice keeps the last, as the spec does.
     */
    public static function parse(string $text): self
    {
        $values = [];

        foreach (preg_split('/[ \t]+/', trim($text)) ?: [] as $token) {
            if ($token === '' || ! str_contains($token, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $token, 2);

            if ($name === '' || $value === '') {
                continue;
            }

            $values[$name] = $value;
        }

        return new self($values);
    }

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function region(): ?string
    {
        return $this->get('region');
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function toString(): string
    {
        $parts = [];

        foreach ($this->values as $name => $value) {
            $parts[] = $name.':'.$value;
        }

        return implode(' ', $parts);
    }
}
