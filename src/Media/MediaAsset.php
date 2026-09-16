<?php

declare(strict_types=1);

namespace Bpmore\Reel\Media;

use Bpmore\Reel\Compliance\Assessment;
use Bpmore\Reel\Compliance\AudioDescription;
use Bpmore\Reel\Compliance\CaptionSummary;
use Bpmore\Reel\Settings;
use Bpmore\Reel\Vtt\Inspection;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

/**
 * A video or audio asset, with what Reel knows about it.
 *
 * The four Reel fields on the asset, by handle:
 *
 *   reel_captions            a chosen caption track, overriding the sidecar
 *   reel_transcript          the transcript, as Bard content
 *   reel_transcript_entry    or an entry that is the transcript
 *   reel_audio_description   needed, provided, or not_applicable
 *
 * Everything here reads. The one write in the addon, saving an edited
 * caption file, is in the editor's controller and nowhere else.
 */
final class MediaAsset
{
    public const CAPTIONS = 'reel_captions';

    public const TRANSCRIPT = 'reel_transcript';

    public const TRANSCRIPT_ENTRY = 'reel_transcript_entry';

    public const AUDIO_DESCRIPTION = 'reel_audio_description';

    private ?Inspection $inspection = null;

    private bool $inspected = false;

    private function __construct(
        public readonly Asset $asset,
        private readonly Settings $settings,
    ) {}

    public static function from(Asset $asset, Settings $settings): ?self
    {
        return $asset->isVideo() || $asset->isAudio() ? new self($asset, $settings) : null;
    }

    public static function isMedia(Asset $asset): bool
    {
        return $asset->isVideo() || $asset->isAudio();
    }

    public function kind(): string
    {
        return $this->asset->isVideo() ? Assessment::VIDEO : Assessment::AUDIO;
    }

    public function id(): string
    {
        return (string) $this->asset->id();
    }

    /**
     * The caption track: the one chosen on the asset if it exists, else the
     * best sidecar. A chosen track that no longer exists is not silently
     * replaced by a sidecar; it is reported as missing, because somebody
     * chose it and would want to know it is gone.
     */
    public function captionTrack(): ?CaptionTrack
    {
        $chosen = $this->asset->get(self::CAPTIONS);
        $chosen = is_array($chosen) ? ($chosen[0] ?? null) : $chosen;

        if (is_string($chosen) && $chosen !== '') {
            $track = $this->resolve($chosen);

            return $track === null ? null : new CaptionTrack($track, CaptionTrack::CHOSEN, Sidecar::language($track->path(), $this->asset->path()) ?? $this->settings->language());
        }

        $candidates = $this->sidecars();

        return $candidates === [] ? null : $candidates[0];
    }

    /** Whether a track was chosen by hand and cannot be found. */
    public function chosenTrackIsMissing(): bool
    {
        $chosen = $this->asset->get(self::CAPTIONS);
        $chosen = is_array($chosen) ? ($chosen[0] ?? null) : $chosen;

        return is_string($chosen) && $chosen !== '' && $this->resolve($chosen) === null;
    }

    /**
     * Every sidecar in the folder, best first.
     *
     * @return array<int, CaptionTrack>
     */
    public function sidecars(): array
    {
        $container = $this->asset->container();
        $folder = $this->asset->folder();
        $files = $container->files($folder === '' ? '/' : $folder);

        $tracks = [];

        foreach ((new Sidecar($this->settings->sidecarPatterns()))->matches($this->asset->path(), $files) as $path) {
            $found = $container->asset($path);

            if ($found !== null) {
                $tracks[] = new CaptionTrack($found, CaptionTrack::SIDECAR, Sidecar::language($path, $this->asset->path()) ?? $this->settings->language());
            }
        }

        return $tracks;
    }

    public function inspection(): ?Inspection
    {
        if (! $this->inspected) {
            $this->inspected = true;
            $track = $this->captionTrack();
            $this->inspection = $track === null ? null : $this->settings->inspector()->inspect($track->contents());
        }

        return $this->inspection;
    }

    /** The transcript as HTML, from the Bard field, or an empty string. */
    public function transcriptHtml(): string
    {
        $value = $this->asset->get(self::TRANSCRIPT);

        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        $augmented = $this->asset->augmentedValue(self::TRANSCRIPT)->value();

        return trim((string) $augmented);
    }

    public function transcriptEntry(): ?Entry
    {
        $id = $this->asset->get(self::TRANSCRIPT_ENTRY);
        $id = is_array($id) ? ($id[0] ?? null) : $id;

        return is_string($id) && $id !== '' ? EntryFacade::find($id) : null;
    }

    public function hasTranscript(): bool
    {
        return $this->transcriptHtml() !== '' || $this->transcriptEntry() !== null;
    }

    public function audioDescription(): ?string
    {
        return AudioDescription::normalize($this->asset->get(self::AUDIO_DESCRIPTION));
    }

    public function assess(): Assessment
    {
        $track = $this->captionTrack();
        $inspection = $this->inspection();

        return Assessment::of(
            $this->kind(),
            $track === null || $inspection === null ? null : CaptionSummary::fromInspection($track->path(), $inspection),
            $this->hasTranscript(),
            $this->audioDescription(),
        );
    }

    /**
     * A caption track named on the asset: a path in the same container, or
     * a full `container::path` id.
     */
    private function resolve(string $reference): ?Asset
    {
        if (str_contains($reference, '::')) {
            return \Statamic\Facades\Asset::find($reference);
        }

        return $this->asset->container()->asset($reference);
    }
}
