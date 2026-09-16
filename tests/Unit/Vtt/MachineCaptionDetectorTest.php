<?php

declare(strict_types=1);

use Bpmore\Reel\Vtt\MachineCaptionDetector;
use Bpmore\Reel\Vtt\Parser;

function detect(string $vtt): \Bpmore\Reel\Vtt\MachineCaptionScore
{
    return (new MachineCaptionDetector)->detect((new Parser)->parse($vtt)->document);
}

/*
 * The true negative comes first. A file a professional wrote, with shouting in
 * capitals and no non-speech annotations because there is nothing but one
 * voice, is the file that turns a blunt heuristic into a tool an editor
 * distrusts. It must score low, and the strong signals must not fire.
 */
it('scores a professionally captioned single-speaker lecture with stylistic capitals as unlikely to be machine output', function () {
    $score = detect(captions('lecture-professional.vtt'));

    expect($score->confidence)->toBeLessThan(0.3);
    expect($score->label)->toBe('unlikely');

    $fired = array_map(fn ($s) => $s->key, $score->contributing());
    expect(in_array('no-terminal-punctuation', $fired, true))->toBeFalse('punctuation signal fired on a punctuated file');
    expect(in_array('no-capitalization', $fired, true))->toBeFalse('capitalization signal fired on a file with capitals');
    expect(in_array('uniform-cue-lengths', $fired, true))->toBeFalse('uniformity signal fired on semantically segmented cues');
});

it('scores a raw ASR file as likely machine output, naming the signals', function () {
    $score = detect(captions('asr-raw.vtt'));

    expect($score->confidence)->toBeGreaterThanOrEqual(0.7);
    expect($score->label)->toBe('likely');

    $fired = array_map(fn ($s) => $s->key, $score->contributing());
    expect($fired)->toContain('no-terminal-punctuation');
    expect($fired)->toContain('no-capitalization');
    expect($fired)->toContain('uniform-cue-lengths');

    // Each contributing signal says what it saw, so an editor can disagree
    // with the evidence rather than with a number.
    foreach ($score->contributing() as $signal) {
        expect($signal->evidence)->not->toBe('');
        expect($signal->contribution)->toBeGreaterThan(0.0);
    }
});

it('never reports a bare boolean: every signal is listed whether or not it fired', function () {
    $score = detect(captions('lecture-professional.vtt'));

    $keys = array_map(fn ($s) => $s->key, $score->signals);
    sort($keys);

    expect($keys)->toBe([
        'no-capitalization',
        'no-non-speech-annotations',
        'no-speaker-labels',
        'no-terminal-punctuation',
        'rolling-duplicates',
        'sentence-tail-starts',
        'uniform-cue-lengths',
        'word-level-timing',
    ]);

    $array = $score->toArray();
    expect($array)->toHaveKeys(['confidence', 'label', 'signals']);
});

it('does not read all-capitals as missing capitalization', function () {
    // Every cue shouted. Ugly, but a choice, and not what a machine does.
    $vtt = "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nGET OUT!\n\n00:00:03.000 --> 00:00:06.500\nI SAID GET OUT OF HERE.\n\n00:00:07.000 --> 00:00:08.000\nNOW.\n";

    $score = detect($vtt);
    $fired = array_map(fn ($s) => $s->key, $score->contributing());

    expect(in_array('no-capitalization', $fired, true))->toBeFalse();
});

it('does not fire the annotation signal on a short file', function () {
    // Thirty seconds of one person talking has no reason to contain
    // [music]. The signal is about long content with nothing at all.
    $vtt = "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nhello there\n\n00:00:03.000 --> 00:00:06.500\nhow are you\n";

    $score = detect($vtt);
    $annotations = array_values(array_filter($score->signals, fn ($s) => $s->key === 'no-non-speech-annotations'))[0];

    expect($annotations->fired)->toBeFalse();
});

it('scores an empty document as unlikely, with nothing to go on', function () {
    $score = detect("WEBVTT\n");

    expect($score->confidence)->toBe(0.0);
    expect($score->contributing())->toBe([]);
});

it('scores a current YouTube-style automatic track as likely, on its structure rather than its punctuation', function () {
    // Punctuated and capitalised, as automatic tracks now are, so the old
    // tells are quiet. What gives it away is a timestamp on every word, each
    // line rolled through two cues, and cues that open mid-sentence.
    $score = detect(captions('asr-youtube-style.vtt'));

    expect($score->confidence)->toBeGreaterThanOrEqual(0.6);
    expect($score->label)->toBe('likely');

    $fired = array_map(fn ($s) => $s->key, $score->contributing());
    expect($fired)->toContain('word-level-timing');
    expect($fired)->toContain('rolling-duplicates');
    expect(in_array('no-terminal-punctuation', $fired, true))->toBeFalse('the file is punctuated');
});

it('scores real files written by people as unlikely', function (string $file) {
    $score = detect(captions('real/'.$file));

    expect($score->label)->toBe('unlikely');
})->with(['sintel-en.vtt', 'videojs-example-captions.vtt']);
