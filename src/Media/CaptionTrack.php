<?php

declare(strict_types=1);

namespace Bpmore\Reel\Media;

use Statamic\Contracts\Assets\Asset;

/** A caption file found for a media asset, and how it was found. */
final class CaptionTrack
{
    public const CHOSEN = 'chosen';

    public const SIDECAR = 'sidecar';

    public function __construct(
        public readonly Asset $asset,
        /** `chosen` on the asset's Reel field, or `sidecar` by filename. */
        public readonly string $how,
        public readonly string $language,
    ) {}

    /**
     * Whether a file is WebVTT, by extension. The only kind Reel reads as
     * a track, and the only kind it will ever write: the asset's field is
     * set by a request, so without this it could name a script or an
     * image in the container and the editor's save would overwrite it.
     */
    public static function isVtt(Asset $asset): bool
    {
        return strtolower((string) $asset->extension()) === 'vtt';
    }

    /**
     * Whether this person may write the file: it is WebVTT, and Statamic's
     * own `edit <container> assets` permission allows it. The addon's
     * permission says they may edit captions; this says they may edit this
     * file, which a chosen track in another container needs separately.
     */
    public function editableBy(mixed $user): bool
    {
        return $user !== null && self::isVtt($this->asset) && (bool) $user->can('edit', $this->asset);
    }

    public function path(): string
    {
        return $this->asset->path();
    }

    public function contents(): string
    {
        return (string) $this->asset->contents();
    }

    /**
     * The language's own name for itself ("Deutsch"), which is what a
     * `<track label>` should say, or the code when ext-intl is not there.
     */
    public function label(): string
    {
        if (! class_exists(\Locale::class)) {
            return $this->language;
        }

        $name = \Locale::getDisplayLanguage($this->language, $this->language);

        return $name === '' || $name === $this->language ? $this->language : ucfirst($name);
    }
}
