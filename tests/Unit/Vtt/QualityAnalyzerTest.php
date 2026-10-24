<?php

declare(strict_types=1);

use Bpmore\Reel\Vtt\Cue;
use Bpmore\Reel\Vtt\Parser;
use Bpmore\Reel\Vtt\QualityAnalyzer;
use Bpmore\Reel\Vtt\QualityThresholds;
use Bpmore\Reel\Vtt\Timestamp;

function cue(string $start, string $end, string $text): Cue
{
    return new Cue(Timestamp::parse($start), Timestamp::parse($end), $text);
}

function analyze(string $vtt, ?QualityThresholds $thresholds = null): array
{
    return (new QualityAnalyzer($thresholds ?? QualityThresholds::preset('netflix')))
        ->analyze((new Parser)->parse($vtt)->document);
}

it('computes the reading rate the way a captioner would, by hand', function () {
    // "Hello world" is 11 characters, spaces included, shown for 2 seconds:
    // 11 / 2 = 5.5 characters per second.
    expect(QualityAnalyzer::readingRate(cue('00:00:00.000', '00:00:02.000', 'Hello world')))->toBe(5.5);

    // Two lines. The break counts as the one space it stands in for, so
    // "Take a deep" (11) + 1 + "breath." (7) = 19 characters over 0.8 s.
    // 19 / 0.8 = 23.75.
    expect(QualityAnalyzer::readingRate(cue('00:00:01.200', '00:00:02.000', "Take a deep\nbreath.")))->toBe(23.75);

    // Markup is not read aloud and is not counted: the same 19 characters.
    expect(QualityAnalyzer::readingRate(cue('00:00:01.200', '00:00:02.000', "<v Dr. Smith>Take a <i>deep</i>\nbreath.</v>")))->toBe(23.75);

    // A zero-length cue cannot be read at any rate; the rate is infinite,
    // and reported as such rather than dividing by zero.
    expect(QualityAnalyzer::readingRate(cue('00:00:01.000', '00:00:01.000', 'Blink')))->toBe(INF);
});

it('flags a cue read faster than the threshold and not one read slower', function () {
    // 41 characters in 2 seconds is 20.5 cps: over Netflix's 20.
    $fast = "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nThe quick brown fox jumps over a lazy dog\n";
    $findings = analyze($fast);
    expect(array_map(fn ($f) => $f->code, $findings))->toBe(['reading-rate']);
    expect($findings[0]->data['cps'])->toBe(20.5);
    expect($findings[0]->data['limit'])->toBe(20.0);

    // Same text over 3 seconds is 13.67 cps: under.
    $slow = "WEBVTT\n\n00:00:00.000 --> 00:00:03.000\nThe quick brown fox jumps over a lazy dog\n";
    expect(analyze($slow))->toBe([]);
});

it('flags a line longer than the standard allows, counting plain text only', function () {
    $line = str_repeat('a', 43);
    $vtt = "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\n<b>{$line}</b>\n";

    $findings = analyze($vtt);

    expect(array_map(fn ($f) => $f->code, $findings))->toBe(['line-length']);
    expect($findings[0]->data['characters'])->toBe(43);
    expect($findings[0]->data['limit'])->toBe(42);

    // The DCMP preset allows 32, so the same 43 is over there too, and 33 is
    // over there but not under Netflix.
    $thirtyThree = "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\n".str_repeat('b', 33)."\n";
    expect(analyze($thirtyThree))->toBe([]);
    expect(array_map(fn ($f) => $f->code, analyze($thirtyThree, QualityThresholds::preset('dcmp'))))->toBe(['line-length']);
});

it('flags more than two lines in a cue', function () {
    $vtt = "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\nOne\nTwo\nThree\n";

    $findings = analyze($vtt);

    expect(array_map(fn ($f) => $f->code, $findings))->toBe(['too-many-lines']);
    expect($findings[0]->data['lines'])->toBe(3);
});

it('flags a cue shorter than the minimum and one longer than the maximum', function () {
    $short = "WEBVTT\n\n00:00:00.000 --> 00:00:00.500\nHi\n";
    expect(array_map(fn ($f) => $f->code, analyze($short)))->toBe(['duration-short']);

    $long = "WEBVTT\n\n00:00:00.000 --> 00:00:07.001\nHi\n";
    expect(array_map(fn ($f) => $f->code, analyze($long)))->toBe(['duration-long']);

    // Exactly the limits pass.
    $edges = "WEBVTT\n\n00:00:00.000 --> 00:00:00.833\nHi\n\n00:00:02.000 --> 00:00:09.000\nHi\n";
    expect(analyze($edges))->toBe([]);
});

it('flags a gap short enough to flicker and neither a contiguous cue nor a frame gap nor a real pause', function () {
    $flicker = "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nOne\n\n00:00:02.200 --> 00:00:04.000\nTwo\n";
    $findings = analyze($flicker);
    expect(array_map(fn ($f) => $f->code, $findings))->toBe(['gap-flicker']);
    expect($findings[0]->cue)->toBe(1);
    expect($findings[0]->data['gap_ms'])->toBe(200);

    $fine = "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nOne\n\n00:00:02.000 --> 00:00:04.000\nTwo\n\n00:00:04.083 --> 00:00:06.000\nThree\n\n00:00:06.500 --> 00:00:08.000\nFour\n";
    expect(analyze($fine))->toBe([]);
});

it('reports the professional fixture with no findings under its own standard', function () {
    // A file a professional wrote is the calibration for the defaults. If the
    // analyzer flags it, the thresholds are wrong, not the file.
    expect(analyze(captions('lecture-professional.vtt'), QualityThresholds::preset('bbc')))->toBe([]);
});

it('builds thresholds from config with a preset as the base and overrides on top', function () {
    $t = QualityThresholds::fromConfig(['standard' => 'netflix', 'characters_per_line' => 30]);

    expect($t->charactersPerLine)->toBe(30);
    expect($t->charactersPerSecond)->toBe(20.0);
    expect($t->minimumDurationMs)->toBe(833);

    // An unknown preset is an error at configuration time, not a silent default.
    expect(fn () => QualityThresholds::preset('nope'))->toThrow(InvalidArgumentException::class);
});

it('numbers a cue after an unread one as the file and the editor do', function () {
    // Cue 2's timing has an SRT comma, so cue 3 is the second readable cue.
    // Its finding still has to say "Cue 3" and point at the third cue.
    $long = str_repeat('a', 50);
    $findings = analyze("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nOk\n\n00:00:04,000 --> 00:00:06.000\nBad\n\n00:00:07.000 --> 00:00:09.000\n{$long}\n");

    $lineLength = array_values(array_filter($findings, fn ($f) => $f->code === 'line-length'));

    expect($lineLength)->toHaveCount(1);
    expect($lineLength[0]->cue)->toBe(2);
    expect($lineLength[0]->message)->toStartWith('Cue 3 ');
});
