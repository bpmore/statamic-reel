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
