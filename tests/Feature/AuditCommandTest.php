<?php

declare(strict_types=1);

use Bpmore\Reel\Commands\Audit;

beforeEach(function () {
    mediaContainer();
    reelSettings(['audit.threshold' => 'fail']);
});

it('prints clean JSON on stdout with --json and exits 1 when a video fails', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/done.mp4');
    mediaFile('clips/done.vtt', captions('lecture-professional.vtt'));
    \Statamic\Facades\Asset::find('media::clips/done.mp4')->set('reel_audio_description', 'not_applicable')->save();
    mediaFile('images/photo.jpg');

    $this->artisan('statamic:reel:audit', ['--json' => true])
        ->expectsOutputToContain('"generated_at"')
        ->assertExitCode(Audit::EXIT_FOUND);

    // Run again capturing the whole of stdout: it has to be JSON and
    // nothing else, because CI pipes it into jq.
    $output = new \Symfony\Component\Console\Output\BufferedOutput;
    $code = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->call('statamic:reel:audit', ['--json' => true], $output);
    $report = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);

    expect($code)->toBe(1);
    expect($report['summary'])->toMatchArray(['assets' => 2, 'fail' => 1, 'pass' => 1, 'at_or_above_threshold' => 1]);
    expect(array_column($report['assets'], 'path'))->toBe(['clips/done.mp4', 'clips/talk.mp4']);
    expect($report['assets'][1]['problems'])->toBe(['This video has no caption track.']);
    expect($report['assets'][0]['captions']['how'])->toBe('sidecar');
    expect($report['assets'][0]['captions']['machine']['label'])->toBe('unlikely');
    expect($report['standard']['standard'])->toBe('netflix');
});

it('exits 0 when nothing reaches the threshold, and 1 when the threshold is lowered to include it', function () {
    mediaFile('clips/done.mp4');
    mediaFile('clips/done.vtt', captions('lecture-professional.vtt'));

    // Audio description not reviewed: verdict is manual.
    $this->artisan('statamic:reel:audit', ['--json' => true])->assertExitCode(0);
    $this->artisan('statamic:reel:audit', ['--json' => true, '--threshold' => 'warn'])->assertExitCode(0);
    $this->artisan('statamic:reel:audit', ['--json' => true, '--threshold' => 'manual'])->assertExitCode(Audit::EXIT_FOUND);
});

it('exits 2 for a bad threshold or an unknown container, and 0 on an empty site', function () {
    $this->artisan('statamic:reel:audit', ['--threshold' => 'sometimes'])->assertExitCode(Audit::EXIT_INVALID);
    $this->artisan('statamic:reel:audit', ['--container' => ['nope']])->assertExitCode(Audit::EXIT_INVALID);
    $this->artisan('statamic:reel:audit')->expectsOutputToContain('No video or audio assets')->assertExitCode(0);
});

it('prints a readable report without --json', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('asr-raw.vtt'));

    $this->artisan('statamic:reel:audit')
        ->expectsOutputToContain('media::clips/talk.mp4 (video)')
        ->expectsOutputToContain('machine captions likely')
        ->assertExitCode(0);
});
