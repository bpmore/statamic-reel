<?php

declare(strict_types=1);

namespace Bpmore\Reel\Compliance;

/** The five WCAG 2.1 success criteria Reel reports against. */
final class Criterion
{
    public const AUDIO_ONLY_AND_VIDEO_ONLY = '1.2.1';

    public const CAPTIONS_PRERECORDED = '1.2.2';

    public const AUDIO_DESCRIPTION_OR_MEDIA_ALTERNATIVE = '1.2.3';

    public const CAPTIONS_LIVE = '1.2.4';

    public const AUDIO_DESCRIPTION_PRERECORDED = '1.2.5';

    public const NAMES = [
        self::AUDIO_ONLY_AND_VIDEO_ONLY => 'Audio-only and Video-only (Prerecorded)',
        self::CAPTIONS_PRERECORDED => 'Captions (Prerecorded)',
        self::AUDIO_DESCRIPTION_OR_MEDIA_ALTERNATIVE => 'Audio Description or Media Alternative (Prerecorded)',
        self::CAPTIONS_LIVE => 'Captions (Live)',
        self::AUDIO_DESCRIPTION_PRERECORDED => 'Audio Description (Prerecorded)',
    ];

    public const LEVELS = [
        self::AUDIO_ONLY_AND_VIDEO_ONLY => 'A',
        self::CAPTIONS_PRERECORDED => 'A',
        self::AUDIO_DESCRIPTION_OR_MEDIA_ALTERNATIVE => 'A',
        self::CAPTIONS_LIVE => 'AA',
        self::AUDIO_DESCRIPTION_PRERECORDED => 'AA',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(self::NAMES);
    }
}
