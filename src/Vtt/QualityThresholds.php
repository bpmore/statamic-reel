<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

use InvalidArgumentException;

/**
 * The numbers a caption is held to, from a named standard with overrides.
 *
 * Every default below has a source, quoted or derived, in `PRESETS`. A
 * number with no source is a number an editor can rightly refuse to be
 * held to. Where a standard gives no figure for something, the preset says
 * whose figure it borrowed.
 */
final class QualityThresholds
{
    /**
     * Sources, checked September 2026:
     *
     * Netflix, "Timed Text Style Guide: General Requirements" and "English
     * Timed Text Style Guide" (partnerhelp.netflixstudios.com): 42 characters
     * per line; 2 lines; up to 20 characters per second for adult programs
     * (17 for children's); minimum duration five-sixths of a second (833 ms);
     * maximum 7 seconds. "Subtitle Timing Guidelines": gaps are either 2
     * frames (83 ms at 24 fps) or half a second or more; gaps between are
     * closed.
     *
     * BBC, "Subtitle Guidelines" (bbc.github.io/subtitle-guidelines): 37
     * characters per line for teletext, two lines, 160 to 180 words per
     * minute. The BBC gives words, not characters: at about 5.6 characters
     * per English word plus a space, 180 wpm is about 17 characters per
     * second, and that derived figure is used here. The BBC gives no
     * absolute minimum or maximum duration; Netflix's are borrowed.
     *
     * DCMP, "Captioning Key" (dcmp.org/learn/captioningkey): 32 characters
     * per line; a minimum duration of 40 frames (1 second 10 frames, 1333
     * ms at 30 fps); a presentation rate not to exceed 160 words per minute
     * for upper-level content, which by the same derivation is about 15
     * characters per second. Two lines is the general practice rather than a
     * figure the Key states outright, and no maximum duration is given, so
     * Netflix's 7 seconds is borrowed.
     *
     * The frame-gap floor and flicker ceiling are Netflix's for every preset,
     * because it is the only one of the three that states them.
     */
    public const PRESETS = [
        'netflix' => [
            'characters_per_line' => 42,
            'lines_per_cue' => 2,
            'characters_per_second' => 20.0,
            'minimum_duration_ms' => 833,
            'maximum_duration_ms' => 7000,
            'frame_gap_ms' => 83,
            'flicker_gap_ms' => 500,
        ],
        'bbc' => [
            'characters_per_line' => 37,
            'lines_per_cue' => 2,
            'characters_per_second' => 17.0,
            'minimum_duration_ms' => 833,
            'maximum_duration_ms' => 7000,
            'frame_gap_ms' => 83,
            'flicker_gap_ms' => 500,
        ],
        'dcmp' => [
            'characters_per_line' => 32,
            'lines_per_cue' => 2,
            'characters_per_second' => 15.0,
            'minimum_duration_ms' => 1333,
            'maximum_duration_ms' => 7000,
            'frame_gap_ms' => 83,
            'flicker_gap_ms' => 500,
        ],
    ];

    /** How each preset is named in a finding. */
    public const NAMES = ['netflix' => 'Netflix', 'bbc' => 'BBC', 'dcmp' => 'DCMP'];

    public function __construct(
        public readonly string $standard,
        public readonly int $charactersPerLine,
        public readonly int $linesPerCue,
        public readonly float $charactersPerSecond,
        public readonly int $minimumDurationMs,
        public readonly int $maximumDurationMs,
        /** A gap this long or shorter is a deliberate frame gap, not flicker. */
        public readonly int $frameGapMs,
        /** A gap shorter than this, and longer than the frame gap, flickers. */
        public readonly int $flickerGapMs,
    ) {}

    public static function preset(string $name): self
    {
        if (! isset(self::PRESETS[$name])) {
            throw new InvalidArgumentException('Unknown caption standard "'.$name.'". Known: '.implode(', ', array_keys(self::PRESETS)).'.');
        }

        return self::fromConfig(['standard' => $name]);
    }

    /**
     * A preset as the base, any key overridden on top. A null or missing
     * override means the preset's figure.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $standard = (string) ($config['standard'] ?? 'netflix');

        if (! isset(self::PRESETS[$standard])) {
            throw new InvalidArgumentException('Unknown caption standard "'.$standard.'". Known: '.implode(', ', array_keys(self::PRESETS)).'.');
        }

        $values = self::PRESETS[$standard];

        foreach ($values as $key => $default) {
            if (isset($config[$key])) {
                $values[$key] = $config[$key];
            }
        }

        return new self(
            $standard,
            (int) $values['characters_per_line'],
            (int) $values['lines_per_cue'],
            (float) $values['characters_per_second'],
            (int) $values['minimum_duration_ms'],
            (int) $values['maximum_duration_ms'],
            (int) $values['frame_gap_ms'],
            (int) $values['flicker_gap_ms'],
        );
    }

    public function name(): string
    {
        return self::NAMES[$this->standard] ?? $this->standard;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function toArray(): array
    {
        return [
            'standard' => $this->standard,
            'characters_per_line' => $this->charactersPerLine,
            'lines_per_cue' => $this->linesPerCue,
            'characters_per_second' => $this->charactersPerSecond,
            'minimum_duration_ms' => $this->minimumDurationMs,
            'maximum_duration_ms' => $this->maximumDurationMs,
            'frame_gap_ms' => $this->frameGapMs,
            'flicker_gap_ms' => $this->flickerGapMs,
        ];
    }
}
