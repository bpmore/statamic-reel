<?php

declare(strict_types=1);

namespace Bpmore\Reel\Media;

use Bpmore\Reel\Settings;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\AssetContainer;

/**
 * Where the media is: every video and audio asset on the site, and the
 * ones a given entry uses.
 */
final class MediaFinder
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Every media asset, by container then path.
     *
     * @param  array<int, string>  $containers  handles to look in; empty means all
     * @return array<int, MediaAsset>
     */
    public function all(array $containers = []): array
    {
        $media = [];

        foreach (AssetContainer::all() as $container) {
            if ($containers !== [] && ! in_array($container->handle(), $containers, true)) {
                continue;
            }

            foreach ($container->assets('/', true) as $asset) {
                $item = MediaAsset::from($asset, $this->settings);

                if ($item !== null) {
                    $media[] = $item;
                }
            }
        }

        usort($media, fn (MediaAsset $a, MediaAsset $b) => $a->id() <=> $b->id());

        return $media;
    }

    public function find(string $id): ?MediaAsset
    {
        $asset = AssetFacade::find($id);

        return $asset === null ? null : MediaAsset::from($asset, $this->settings);
    }

    /**
     * The media an entry uses, with the top-level field each was found in.
     *
     * The entry's data is walked rather than its blueprint, because a video
     * can sit in an assets field, inside a Bard set, inside a Replicator
     * inside a Grid, and the data has it wherever it is. A string is taken
     * as an asset if it is a `container::path` id, or if it ends in a media
     * extension and some container has a file at that path. A text field
     * that happens to contain "talk.mp4" and nothing else is the one way to
     * fool this, and it is fooled towards checking rather than away from it.
     *
     * @return array<int, array{media: MediaAsset, field: string}>
     */
    public function inEntry(Entry $entry): array
    {
        $found = [];
        $seen = [];

        foreach ($entry->data()->all() as $handle => $value) {
            $this->walk($value, (string) $handle, $found, $seen);
        }

        return $found;
    }

    /**
     * @param  array<int, array{media: MediaAsset, field: string}>  $found
     * @param  array<string, true>  $seen
     */
    private function walk(mixed $value, string $field, array &$found, array &$seen): void
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                $this->walk($v, $field, $found, $seen);
            }

            return;
        }

        if (! is_string($value) || $value === '') {
            return;
        }

        foreach ($this->assetsNamedBy($value) as $asset) {
            $media = MediaAsset::from($asset, $this->settings);

            if ($media === null || isset($seen[$media->id()])) {
                continue;
            }

            $seen[$media->id()] = true;
            $found[] = ['media' => $media, 'field' => $field];
        }
    }

    /**
     * @return array<int, Asset>
     */
    private function assetsNamedBy(string $value): array
    {
        if (preg_match('/^asset::([^:]+)::(.+)$/', $value, $m)) {
            $asset = AssetFacade::find($m[1].'::'.$m[2]);

            return $asset === null ? [] : [$asset];
        }

        if (str_contains($value, '::') && ! str_contains($value, "\n")) {
            $asset = AssetFacade::find($value);

            return $asset === null ? [] : [$asset];
        }

        $extensions = array_merge(\Statamic\Assets\Asset::VIDEO_EXTENSIONS, \Statamic\Assets\Asset::AUDIO_EXTENSIONS);

        if (str_contains($value, "\n") || ! preg_match('/\.('.implode('|', $extensions).')$/i', $value)) {
            return [];
        }

        $assets = [];

        foreach (AssetContainer::all() as $container) {
            $asset = $container->asset(ltrim($value, '/'));

            if ($asset !== null) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }
}
