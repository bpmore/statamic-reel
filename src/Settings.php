<?php

declare(strict_types=1);

namespace Bpmore\Reel;

use Bpmore\Reel\Vtt\Inspector;
use Bpmore\Reel\Vtt\MachineCaptionDetector;
use Bpmore\Reel\Vtt\QualityThresholds;

/**
 * The config file, read once into values the rest of the addon asks for.
 *
 * One class so the tests can state a setting rather than edit global
 * state, and so an unrecognised value is refused here, where the message
 * can name the key, rather than acted on somewhere quieter.
 */
final class Settings
{
    public const PACKAGE = 'bpmore/statamic-reel';

    /**
     * @param  array<string, mixed>  $config  the contents of `config/statamic-reel.php`
     */
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('statamic-reel', []));
    }

    public function thresholds(): QualityThresholds
    {
        return QualityThresholds::fromConfig(array_filter(
            (array) ($this->config['quality'] ?? []),
            fn ($v) => $v !== null,
        ));
    }

    public function detector(): MachineCaptionDetector
    {
        $minutes = (int) ($this->config['machine']['annotation_minutes'] ?? 10);

        return new MachineCaptionDetector(annotationDurationMs: max(0, $minutes) * 60_000);
    }

    public function inspector(): Inspector
    {
        return new Inspector($this->thresholds(), $this->detector());
    }

    /** @return array<int, string> */
    public function sidecarPatterns(): array
    {
        $patterns = array_values(array_filter(array_map('strval', (array) ($this->config['captions']['sidecars'] ?? []))));

        return $patterns === [] ? ['{name}.vtt', '{name}.*.vtt'] : $patterns;
    }

    public function language(): string
    {
        return (string) ($this->config['captions']['language'] ?? 'en');
    }

    public function gate(): Gate\GateSettings
    {
        return Gate\GateSettings::fromConfig((array) ($this->config['gate'] ?? []));
    }

    public function auditThreshold(): string
    {
        return (string) ($this->config['audit']['threshold'] ?? 'fail');
    }

    public function addsFieldsToAssets(): bool
    {
        return (bool) ($this->config['add_fields_to_assets'] ?? true);
    }
}
