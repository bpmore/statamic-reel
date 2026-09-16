<?php

declare(strict_types=1);

namespace Bpmore\Reel\Gate;

/**
 * The gate's mode, overall and by collection, as a value.
 *
 * An unrecognised mode fails closed to `block`. A typo in a config file
 * must never be the reason a gate stopped gating, because nothing in the
 * interface would show that it had.
 */
final class GateSettings
{
    public const BLOCK = 'block';

    public const WARN = 'warn';

    public const OFF = 'off';

    public const MODES = [self::BLOCK, self::WARN, self::OFF];

    /**
     * @param  array<string, string>  $collections  mode by collection handle
     */
    public function __construct(
        public readonly string $mode = self::BLOCK,
        public readonly array $collections = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $collections = [];

        foreach ((array) ($config['collections'] ?? []) as $handle => $mode) {
            $collections[(string) $handle] = self::mode($mode);
        }

        return new self(self::mode($config['mode'] ?? null), $collections);
    }

    public function modeFor(string $collection): string
    {
        return $this->collections[$collection] ?? $this->mode;
    }

    private static function mode(mixed $value): string
    {
        return is_string($value) && in_array($value, self::MODES, true) ? $value : self::BLOCK;
    }
}
