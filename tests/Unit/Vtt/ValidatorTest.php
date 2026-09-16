<?php

declare(strict_types=1);

use Bpmore\Reel\Vtt\Parser;
use Bpmore\Reel\Vtt\Validator;

function validate(string $vtt): array
{
    return (new Validator)->validate((new Parser)->parse($vtt)->document);
}

function codes(array $findings): array
{
    return array_values(array_map(fn ($f) => $f->code, $findings));
}

it('reports overlapping cues with no region as a hard error', function () {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nFirst\n\n00:00:03.000 --> 00:00:06.000\nSecond\n";

    $findings = validate($vtt);

    expect(codes($findings))->toBe(['cues-overlap']);
    expect($findings[0]->severity)->toBe('error');
    expect($findings[0]->cue)->toBe(1);
    expect($findings[0]->line)->toBe(6);
});

it('allows the same overlap when both cues sit in a region with room to stack', function () {
    $vtt = "WEBVTT\n\nREGION\nid:chat\nwidth:40%\nlines:3\nscroll:up\n\n00:00:01.000 --> 00:00:04.000 region:chat\nFirst\n\n00:00:03.000 --> 00:00:06.000 region:chat\nSecond\n";

    expect(validate($vtt))->toBe([]);
});

it('still refuses the overlap when the region has a single line, because one line cannot show two cues', function () {
    $vtt = "WEBVTT\n\nREGION\nid:one\nwidth:40%\nlines:1\n\n00:00:01.000 --> 00:00:04.000 region:one\nFirst\n\n00:00:03.000 --> 00:00:06.000 region:one\nSecond\n";

    expect(codes(validate($vtt)))->toBe(['cues-overlap']);
});

it('refuses a cue that names a region the file never defines', function () {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:04.000 region:ghost\nFirst\n";

    expect(codes(validate($vtt)))->toBe(['region-undefined']);
});

it('does not call touching cues an overlap', function () {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nFirst\n\n00:00:04.000 --> 00:00:06.000\nSecond\n";

    expect(validate($vtt))->toBe([]);
});

it('reports a cue whose end precedes its start', function () {
    $vtt = "WEBVTT\n\n00:00:04.000 --> 00:00:01.000\nBackwards\n";

    $findings = validate($vtt);

    expect(codes($findings))->toBe(['end-before-start']);
    expect($findings[0]->line)->toBe(3);
});

it('reports cues out of order', function () {
    $vtt = "WEBVTT\n\n00:00:05.000 --> 00:00:06.000\nLater\n\n00:00:01.000 --> 00:00:02.000\nEarlier\n";

    $findings = validate($vtt);

    expect(codes($findings))->toBe(['cues-out-of-order']);
    expect($findings[0]->cue)->toBe(1);
});

it('carries the parser errors for a malformed timestamp and a missing header as hard errors', function () {
    $missing = (new Parser)->parse("00:00:01.000 --> 00:00:02.000\nHello\n");
    expect(codes((new Validator)->validate($missing->document, $missing->errors)))->toBe(['missing-header']);

    $corrupt = (new Parser)->parse("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nOk\n\n00:00:03;000 --> 00:00:04.000\nBad\n");
    $findings = (new Validator)->validate($corrupt->document, $corrupt->errors);
    expect(codes($findings))->toBe(['malformed-timestamp']);
    expect($findings[0]->line)->toBe(6);
});

it('passes the professional fixture and the complex fixture clean', function () {
    expect(validate(captions('lecture-professional.vtt')))->toBe([]);
    expect(validate(captions('complex.vtt')))->toBe([]);
});

it('finds the 39 ms overlap in the video.js example that a person timed by hand', function () {
    $findings = validate(captions('real/videojs-example-captions.vtt'));

    expect(codes($findings))->toBe(['cues-overlap']);
    expect($findings[0]->data['with'])->toBe(10);
});
