<?php

declare(strict_types=1);

namespace Bpmore\Reel\Listeners;

use Bpmore\Reel\Compliance\AudioDescription;
use Bpmore\Reel\Media\MediaAsset;
use Bpmore\Reel\Settings;
use Statamic\Events\AssetContainerBlueprintFound;

/**
 * The Reel fields, added to the edit screen of every video and audio asset.
 *
 * Added to the blueprint as it is resolved rather than written into the
 * container's blueprint file, so nothing of the site's is changed, and only
 * when the blueprint is being resolved for a media asset: an image gets
 * nothing. A site that would rather place the fields itself turns this
 * off in the config file and uses the same handles.
 */
final class AddFieldsToMediaAssets
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(AssetContainerBlueprintFound $event): void
    {
        if (! $this->settings->addsFieldsToAssets() || $event->asset === null || ! MediaAsset::isMedia($event->asset)) {
            return;
        }

        $isVideo = $event->asset->isVideo();

        $fields = [];

        if ($isVideo) {
            $fields[MediaAsset::CAPTIONS] = [
                'type' => 'assets',
                'display' => 'Caption track',
                'instructions' => 'A WebVTT file. Leave empty to use a file named like the video in the same folder: talk.vtt or talk.en.vtt for talk.mp4.',
                'container' => $event->container?->handle(),
                'max_files' => 1,
                'mode' => 'list',
                'validate' => ['nullable'],
            ];
        }

        $fields[MediaAsset::TRANSCRIPT] = [
            'type' => 'bard',
            'display' => 'Transcript',
            'instructions' => $isVideo
                ? 'The full text of what is said and heard, for people who cannot use the video. Headings for sections and a paragraph per speaker turn read well.'
                : 'The full text of what is said and heard. Audio-only media needs one under WCAG 1.2.1.',
            'buttons' => ['h2', 'h3', 'bold', 'italic', 'unorderedlist', 'orderedlist', 'removeformat'],
            'save_html' => false,
            'validate' => ['nullable'],
        ];

        $fields[MediaAsset::TRANSCRIPT_ENTRY] = [
            'type' => 'entries',
            'display' => 'Transcript entry',
            'instructions' => 'Or an entry that is the transcript, if it lives on its own page.',
            'max_items' => 1,
            'mode' => 'select',
            'validate' => ['nullable'],
        ];

        if ($isVideo) {
            $fields[MediaAsset::AUDIO_DESCRIPTION] = [
                'type' => 'select',
                'display' => 'Audio description',
                'instructions' => 'Nothing can detect this from the file. Until somebody answers, it is reported as not reviewed.',
                'options' => AudioDescription::OPTIONS,
                'clearable' => true,
                'validate' => ['nullable', 'in:'.implode(',', array_keys(AudioDescription::OPTIONS))],
            ];
        }

        $event->blueprint->ensureFieldsInTab($fields, 'captions');
    }
}
