<?php

declare(strict_types=1);

namespace Bpmore\Reel\Compliance;

/**
 * Whether a video has an audio description. Nothing can detect this from
 * the file, so it is a flag a person sets, and until they do it is unset,
 * which is reported as "not reviewed" and never as either answer.
 */
final class AudioDescription
{
    public const NEEDED = 'needed';

    public const PROVIDED = 'provided';

    public const NOT_APPLICABLE = 'not_applicable';

    public const OPTIONS = [
        self::NEEDED => 'Needed, not yet provided',
        self::PROVIDED => 'Provided',
        self::NOT_APPLICABLE => 'Not applicable (nothing visual the audio leaves out)',
    ];

    /** Anything that is not one of the three is unset, a typo included. */
    public static function normalize(mixed $value): ?string
    {
        return is_string($value) && isset(self::OPTIONS[$value]) ? $value : null;
    }
}
