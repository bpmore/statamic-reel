<?php

declare(strict_types=1);

namespace Bpmore\Reel\Compliance;

use Bpmore\Reel\Vtt\MachineCaptionScore;

/**
 * One media item against WCAG 1.2.1 to 1.2.5, from what is known about it.
 *
 * Pure: kind, caption summary, transcript, audio-description flag in;
 * statuses and reasons out. The rules, and the reasons they are what they
 * are:
 *
 * - A video is assumed to have sound. 1.2.1 covers video-only media and the
 *   tool cannot tell a silent video from a sound one, so for a video it is
 *   not applicable and the README says so. For audio it needs a transcript.
 * - 1.2.2 needs a caption track that parses without hard errors. A track
 *   the detector calls likely machine output is `manual`, not `fail`: the
 *   score is a likelihood, and a person confirms it.
 * - 1.2.3 is met by a transcript, or by an audio description. An unset
 *   audio-description flag with no transcript is `manual`.
 * - 1.2.4 is live captions, always a person's answer.
 * - 1.2.5 is the audio-description flag alone.
 *
 * The overall verdict is `fail` if anything fails; `warn` if the captions
 * carry quality findings or the detector says "possible" or "likely";
 * `manual` if the audio description is unreviewed; otherwise `pass`. 1.2.4
 * never moves the verdict, because it is always manual and never about
 * prerecorded media.
 */
final class Assessment
{
    public const VIDEO = 'video';

    public const AUDIO = 'audio';

    public const FAIL = 'fail';

    public const WARN = 'warn';

    public const MANUAL = 'manual';

    public const PASS = 'pass';

    /** Verdicts in order of severity, worst first. */
    public const VERDICTS = [self::FAIL, self::WARN, self::MANUAL, self::PASS];

    public readonly string $verdict;

    /**
     * @param  array<string, array{status: string, reason: string}>  $criteria  by criterion number
     * @param  array<int, string>  $problems  what blocks a publish, in plain words
     */
    private function __construct(
        public readonly string $kind,
        public readonly array $criteria,
        public readonly array $problems,
        public readonly ?CaptionSummary $captions,
        public readonly bool $hasTranscript,
        public readonly ?string $audioDescription,
    ) {
        $this->verdict = $this->computeVerdict();
    }

    public static function of(string $kind, ?CaptionSummary $captions, bool $hasTranscript, ?string $audioDescription): self
    {
        $ad = AudioDescription::normalize($audioDescription);
        $criteria = [];
        $problems = [];

        if ($kind === self::AUDIO) {
            $criteria[Criterion::AUDIO_ONLY_AND_VIDEO_ONLY] = $hasTranscript
                ? [Status::PASS, 'A transcript is provided.']
                : [Status::FAIL, 'Audio-only media needs a transcript, and this asset has none.'];

            if (! $hasTranscript) {
                $problems[] = 'This audio has no transcript.';
            }

            $criteria[Criterion::CAPTIONS_PRERECORDED] = [Status::NOT_APPLICABLE, 'Captions apply to video. Audio-only media needs a transcript (1.2.1).'];
            $criteria[Criterion::AUDIO_DESCRIPTION_OR_MEDIA_ALTERNATIVE] = [Status::NOT_APPLICABLE, 'Applies to video.'];
            $criteria[Criterion::CAPTIONS_LIVE] = [Status::MANUAL, 'Live captions cannot be checked from a file. If this audio is ever streamed live, that is a separate obligation.'];
            $criteria[Criterion::AUDIO_DESCRIPTION_PRERECORDED] = [Status::NOT_APPLICABLE, 'Applies to video.'];
        } else {
            $criteria[Criterion::AUDIO_ONLY_AND_VIDEO_ONLY] = [Status::NOT_APPLICABLE, 'Reel assumes a video has sound. A video with no audio track is video-only media and needs a transcript or audio track under 1.2.1; that is a person\'s call.'];

            if ($captions === null) {
                $criteria[Criterion::CAPTIONS_PRERECORDED] = [Status::FAIL, 'No caption track. Add a WebVTT file beside the video or choose one in the asset\'s Reel fields.'];
                $problems[] = 'This video has no caption track.';
            } elseif ($captions->errors > 0) {
                $criteria[Criterion::CAPTIONS_PRERECORDED] = [Status::FAIL, sprintf('The caption track has %d %s a player will not show correctly.', $captions->errors, $captions->errors === 1 ? 'error' : 'errors')];
                $problems[] = sprintf('The caption track for this video has %d %s.', $captions->errors, $captions->errors === 1 ? 'error' : 'errors');
            } elseif ($captions->cues === 0) {
                $criteria[Criterion::CAPTIONS_PRERECORDED] = [Status::FAIL, 'The caption track has no cues.'];
                $problems[] = 'The caption track for this video is empty.';
            } elseif ($captions->machineLabel === MachineCaptionScore::LIKELY) {
                $criteria[Criterion::CAPTIONS_PRERECORDED] = [Status::MANUAL, sprintf('The caption track reads like uncorrected machine output (%d%% confidence). Uncorrected automatic captions do not meet 1.2.2; a person confirms whether these were corrected.', round($captions->machineConfidence * 100))];
            } else {
                $criteria[Criterion::CAPTIONS_PRERECORDED] = [Status::PASS, 'A caption track is present and well formed.'.($captions->warnings > 0 ? sprintf(' It has %d quality %s.', $captions->warnings, $captions->warnings === 1 ? 'finding' : 'findings') : '')];
            }

            $criteria[Criterion::AUDIO_DESCRIPTION_OR_MEDIA_ALTERNATIVE] = match (true) {
                $hasTranscript => [Status::PASS, 'A transcript is provided as the media alternative.'],
                $ad === AudioDescription::PROVIDED => [Status::PASS, 'An audio description is provided.'],
                $ad === AudioDescription::NOT_APPLICABLE => [Status::NOT_APPLICABLE, 'Marked as needing no audio description.'],
                $ad === AudioDescription::NEEDED => [Status::FAIL, 'No transcript, and the audio description is marked as needed but not provided.'],
                default => [Status::MANUAL, 'No transcript, and nobody has said whether an audio description is needed.'],
            };

            $criteria[Criterion::CAPTIONS_LIVE] = [Status::MANUAL, 'Live captions cannot be checked from a file. If this video is ever streamed live, that is a separate obligation.'];

            $criteria[Criterion::AUDIO_DESCRIPTION_PRERECORDED] = match ($ad) {
                AudioDescription::PROVIDED => [Status::PASS, 'An audio description is provided.'],
                AudioDescription::NOT_APPLICABLE => [Status::NOT_APPLICABLE, 'Marked as needing no audio description.'],
                AudioDescription::NEEDED => [Status::FAIL, 'Marked as needing an audio description that is not yet provided.'],
                default => [Status::MANUAL, 'Not reviewed: nobody has said whether this video needs an audio description.'],
            };
        }

        return new self(
            $kind,
            array_map(fn (array $c) => ['status' => $c[0], 'reason' => $c[1]], $criteria),
            $problems,
            $captions,
            $hasTranscript,
            $ad,
        );
    }

    public function status(string $criterion): string
    {
        return $this->criteria[$criterion]['status'];
    }

    /** Whether a publish gate in block mode should refuse an entry using this media. */
    public function blocks(): bool
    {
        return $this->problems !== [];
    }

    private function computeVerdict(): string
    {
        foreach ($this->criteria as $c) {
            if ($c['status'] === Status::FAIL) {
                return self::FAIL;
            }
        }

        if ($this->captions !== null && ($this->captions->warnings > 0 || $this->captions->machineLabel !== MachineCaptionScore::UNLIKELY)) {
            return self::WARN;
        }

        if ($this->kind === self::VIDEO && $this->audioDescription === null) {
            return self::MANUAL;
        }

        return self::PASS;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $criteria = [];

        foreach ($this->criteria as $number => $c) {
            $criteria[] = [
                'criterion' => $number,
                'name' => Criterion::NAMES[$number],
                'level' => Criterion::LEVELS[$number],
                'status' => $c['status'],
                'reason' => $c['reason'],
            ];
        }

        return [
            'kind' => $this->kind,
            'verdict' => $this->verdict,
            'problems' => $this->problems,
            'criteria' => $criteria,
            'captions' => $this->captions?->toArray(),
            'transcript' => $this->hasTranscript,
            'audio_description' => $this->audioDescription,
        ];
    }
}
