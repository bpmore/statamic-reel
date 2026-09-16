<?php

declare(strict_types=1);

namespace Bpmore\Reel\Tags;

use Bpmore\Reel\Media\MediaAsset;
use Bpmore\Reel\Media\MediaFinder;
use Bpmore\Reel\Settings;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Value;
use Statamic\Tags\Tags;

/**
 * `{{ reel:tracks }}` and `{{ reel:transcript }}`.
 *
 * Reel does not ship a player. The tracks tag emits `<track>` elements for
 * the site's own `<video>` or `<audio>`, and the transcript tag emits the
 * transcript as a labelled section. Both work with no JavaScript, because
 * static caching is the norm on the sites this is for.
 */
class Reel extends Tags
{
    /**
     * `{{ reel:tracks :asset="video" }}`
     *
     * One `<track>` per caption track, the first marked `default` unless
     * `default="false"`. `kind` defaults to `captions`, which is right for
     * a track that carries sound as well as speech; pass `subtitles` when
     * it carries speech alone.
     */
    public function tracks(): string
    {
        $media = $this->media();

        if ($media === null) {
            return '';
        }

        $kind = (string) $this->params->get('kind', 'captions');
        $default = $this->params->bool('default', true);
        $tracks = $media->captionTrack() === null ? [] : [$media->captionTrack()];

        // A chosen track stands alone; sidecars come as a set, one language each.
        if ($tracks !== [] && $tracks[0]->how === \Bpmore\Reel\Media\CaptionTrack::SIDECAR) {
            $tracks = $media->sidecars();
        }

        $html = [];

        foreach ($tracks as $i => $track) {
            $html[] = sprintf(
                '<track kind="%s" src="%s" srclang="%s" label="%s"%s>',
                e($kind),
                e((string) $track->asset->url()),
                e($track->language),
                e($track->label()),
                $default && $i === 0 ? ' default' : '',
            );
        }

        return implode("\n", $html);
    }

    /**
     * `{{ reel:transcript :asset="video" }}`
     *
     * A `<section>` labelled by its own heading, so it is a landmark a
     * screen reader lists and jumps to, with the transcript inside. A
     * linked entry becomes a link to its page. `heading` sets the heading
     * level (2 by default), `title` the heading text, `class` the section's
     * class. Nothing at all when there is no transcript: the tag never
     * fakes one.
     */
    public function transcript(): string
    {
        $media = $this->media();

        if ($media === null) {
            return '';
        }

        $level = max(1, min(6, (int) $this->params->get('heading', 2)));
        $title = (string) $this->params->get('title', 'Transcript');
        $class = (string) $this->params->get('class', 'reel-transcript');
        $id = 'reel-transcript-'.substr(md5($media->id()), 0, 8);

        $body = $media->transcriptHtml();

        if ($body === '' && ($entry = $media->transcriptEntry()) !== null) {
            $url = (string) $entry->url();

            if ($url === '') {
                return '';
            }

            $body = '<p><a href="'.e($url).'">'.e('Read the transcript: '.(string) $entry->get('title', $entry->slug())).'</a></p>';
        }

        if ($body === '') {
            return '';
        }

        return sprintf(
            '<section class="%s" aria-labelledby="%s"><h%d id="%s">%s</h%d>%s</section>',
            e($class),
            $id,
            $level,
            $id,
            e($title),
            $level,
            $body,
        );
    }

    private function media(): ?MediaAsset
    {
        $param = $this->params->get('asset') ?? $this->params->get('src') ?? $this->context->get('asset');

        if ($param instanceof Value) {
            $param = $param->value();
        }

        if ($param instanceof \Illuminate\Support\Collection) {
            $param = $param->first();
        }

        if (is_array($param)) {
            $param = $param[0] ?? ($param['id'] ?? null);
        }

        if ($param instanceof Value) {
            $param = $param->value();
        }

        $asset = match (true) {
            $param instanceof Asset => $param,
            is_string($param) && $param !== '' => $this->assetNamed($param),
            default => null,
        };

        return $asset === null ? null : MediaAsset::from($asset, app(Settings::class));
    }

    private function assetNamed(string $reference): ?Asset
    {
        if (str_contains($reference, '::')) {
            return AssetFacade::find(preg_replace('/^asset::/', '', $reference) ?? $reference);
        }

        foreach (AssetContainer::all() as $container) {
            $asset = $container->asset(ltrim($reference, '/'));

            if ($asset !== null) {
                return $asset;
            }
        }

        return null;
    }
}
