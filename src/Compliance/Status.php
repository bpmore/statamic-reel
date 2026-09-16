<?php

declare(strict_types=1);

namespace Bpmore\Reel\Compliance;

/**
 * What can be said about one success criterion for one asset.
 *
 * `manual` is the honest answer whenever the tool cannot know, and it is
 * never quietly promoted to `pass`. A criterion nobody has looked at is
 * work to do, not a green tick.
 */
final class Status
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const MANUAL = 'manual';

    public const NOT_APPLICABLE = 'not_applicable';

    public const ALL = [self::PASS, self::FAIL, self::MANUAL, self::NOT_APPLICABLE];
}
