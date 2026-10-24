<?php

declare(strict_types=1);

use Bpmore\Reel\Compliance\Assessment;
use Bpmore\Reel\Compliance\AudioDescription;
use Bpmore\Reel\Compliance\CaptionSummary;
use Bpmore\Reel\Compliance\Status;

function summary(int $errors = 0, int $warnings = 0, string $machine = 'unlikely', float $confidence = 0.0, int $cues = 10): CaptionSummary
{
    return new CaptionSummary('media/talk.vtt', $errors, $warnings, $confidence, $machine, $cues);
}

it('fails a video with no caption track on 1.2.2 and blocks', function () {
    $a = Assessment::of(Assessment::VIDEO, null, false, null);

    expect($a->status('1.2.2'))->toBe(Status::FAIL);
    expect($a->status('1.2.1'))->toBe(Status::NOT_APPLICABLE);
    expect($a->status('1.2.4'))->toBe(Status::MANUAL);
    expect($a->verdict)->toBe(Assessment::FAIL);
    expect($a->blocks())->toBeTrue();
    expect($a->problems)->toBe(['This video has no caption track.']);
});

it('fails a video whose caption track has hard errors', function () {
    $a = Assessment::of(Assessment::VIDEO, summary(errors: 2), true, AudioDescription::PROVIDED);

    expect($a->status('1.2.2'))->toBe(Status::FAIL);
    expect($a->blocks())->toBeTrue();
});

it('passes a video with clean captions, a transcript and an audio description', function () {
    $a = Assessment::of(Assessment::VIDEO, summary(), true, AudioDescription::PROVIDED);

    expect($a->status('1.2.2'))->toBe(Status::PASS);
    expect($a->status('1.2.3'))->toBe(Status::PASS);
    expect($a->status('1.2.5'))->toBe(Status::PASS);
    expect($a->verdict)->toBe(Assessment::PASS);
    expect($a->blocks())->toBeFalse();
});

it('makes likely machine captions manual rather than a failure, and never blocks on them', function () {
    $a = Assessment::of(Assessment::VIDEO, summary(machine: 'likely', confidence: 0.72), true, AudioDescription::NOT_APPLICABLE);

    expect($a->status('1.2.2'))->toBe(Status::MANUAL);
    expect($a->criteria['1.2.2']['reason'])->toContain('72%');
    expect($a->verdict)->toBe(Assessment::WARN);
    expect($a->blocks())->toBeFalse();
});

it('reports an unset audio description as not reviewed: manual, never pass, never fail', function () {
    $a = Assessment::of(Assessment::VIDEO, summary(), false, null);

    expect($a->status('1.2.3'))->toBe(Status::MANUAL);
    expect($a->status('1.2.5'))->toBe(Status::MANUAL);
    expect($a->verdict)->toBe(Assessment::MANUAL);
    expect($a->blocks())->toBeFalse();

    // A value that is not one of the three is unset too, not "needed".
    expect(Assessment::of(Assessment::VIDEO, summary(), false, 'yes')->status('1.2.5'))->toBe(Status::MANUAL);
});

it('fails 1.2.5 when the audio description is marked needed, and 1.2.3 too without a transcript', function () {
    $a = Assessment::of(Assessment::VIDEO, summary(), false, AudioDescription::NEEDED);

    expect($a->status('1.2.3'))->toBe(Status::FAIL);
    expect($a->status('1.2.5'))->toBe(Status::FAIL);
    expect($a->verdict)->toBe(Assessment::FAIL);

    // With a transcript 1.2.3 is met, but 1.2.5 still fails: they are different obligations.
    $b = Assessment::of(Assessment::VIDEO, summary(), true, AudioDescription::NEEDED);
    expect($b->status('1.2.3'))->toBe(Status::PASS);
    expect($b->status('1.2.5'))->toBe(Status::FAIL);
});

it('warns, without blocking, when the captions carry quality findings', function () {
    $a = Assessment::of(Assessment::VIDEO, summary(warnings: 4), true, AudioDescription::NOT_APPLICABLE);

    expect($a->verdict)->toBe(Assessment::WARN);
    expect($a->blocks())->toBeFalse();
});

it('judges audio on the transcript alone', function () {
    $without = Assessment::of(Assessment::AUDIO, null, false, null);
    expect($without->status('1.2.1'))->toBe(Status::FAIL);
    expect($without->status('1.2.2'))->toBe(Status::NOT_APPLICABLE);
    expect($without->status('1.2.5'))->toBe(Status::NOT_APPLICABLE);
    expect($without->verdict)->toBe(Assessment::FAIL);
    expect($without->blocks())->toBeTrue();

    $with = Assessment::of(Assessment::AUDIO, null, true, null);
    expect($with->status('1.2.1'))->toBe(Status::PASS);
    expect($with->verdict)->toBe(Assessment::PASS);
});

it('lists all five criteria with names and levels in its array form', function () {
    $array = Assessment::of(Assessment::VIDEO, summary(), true, AudioDescription::PROVIDED)->toArray();

    expect(array_column($array['criteria'], 'criterion'))->toBe(['1.2.1', '1.2.2', '1.2.3', '1.2.4', '1.2.5']);
    expect($array['criteria'][3]['level'])->toBe('AA');
    expect($array['criteria'][1]['name'])->toBe('Captions (Prerecorded)');
});

it('counts a cue whose timing cannot be read in the caption summary', function () {
    $inspection = (new \Bpmore\Reel\Vtt\Inspector(\Bpmore\Reel\Vtt\QualityThresholds::preset('netflix')))
        ->inspect("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nOne\n\n00:00:03,000 --> 00:00:04.000\nTwo\n");

    expect(CaptionSummary::fromInspection('media/talk.vtt', $inspection)->cues)->toBe(2);
});
