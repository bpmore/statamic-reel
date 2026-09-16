<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Entry;

beforeEach(function () {
    mediaContainer();
    reelSettings(['gate.mode' => 'block', 'gate.collections' => []]);
});

it('refuses to save an entry using a video with no captions in block mode, naming the field', function () {
    mediaFile('clips/talk.mp4');

    try {
        entryWith(['video' => 'clips/talk.mp4']);
        $this->fail('The save went through.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422);
        expect($e->errors())->toHaveKey('video');
        expect($e->errors()['video'][0])->toContain('no caption track');
        expect($e->errors()['video'][0])->toContain('clips/talk.mp4');
    }

    expect(Entry::query()->where('slug', 'page')->first())->toBeNull();
});

it('saves the same entry and logs a warning in warn mode', function () {
    mediaFile('clips/talk.mp4');
    reelSettings(['gate.mode' => 'warn']);

    Log::shouldReceive('warning')->once()->withArgs(fn ($message, $context) => str_contains($message, 'would have refused') && isset($context['problems']['video']));

    entryWith(['video' => 'clips/talk.mp4']);

    expect(Entry::query()->where('slug', 'page')->first())->not->toBeNull();
});

it('saves silently in off mode', function () {
    mediaFile('clips/talk.mp4');
    reelSettings(['gate.mode' => 'off']);

    Log::shouldReceive('warning')->never();

    entryWith(['video' => 'clips/talk.mp4']);

    expect(Entry::query()->where('slug', 'page')->first())->not->toBeNull();
});

it('takes the mode per collection, with the default for the rest', function () {
    mediaFile('clips/talk.mp4');
    reelSettings(['gate.mode' => 'block', 'gate.collections' => ['drafts' => 'off']]);

    entryWith(['video' => 'clips/talk.mp4'], collection: 'drafts', slug: 'draft');
    expect(Entry::query()->where('slug', 'draft')->first())->not->toBeNull();

    expect(fn () => entryWith(['video' => 'clips/talk.mp4'], collection: 'pages'))->toThrow(ValidationException::class);
});

it('lets a video through once a caption file sits beside it', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('lecture-professional.vtt'));

    entryWith(['video' => 'clips/talk.mp4']);

    expect(Entry::query()->where('slug', 'page')->first())->not->toBeNull();
});

it('still refuses when the caption file beside it cannot be read by a player', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nA\n\n00:00:03.000 --> 00:00:06.000\nB\n");

    try {
        entryWith(['video' => 'clips/talk.mp4']);
        $this->fail('The save went through.');
    } catch (ValidationException $e) {
        expect($e->errors()['video'][0])->toContain('1 error');
    }
});

it('finds the video wherever it is in the data, and reports it on the top-level field', function () {
    mediaFile('clips/talk.mp4');

    try {
        entryWith(['content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'video', 'file' => 'clips/talk.mp4']]],
        ]]);
        $this->fail('The save went through.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['content']);
    }
});

it('refuses audio with no transcript and accepts it with a transcript entry', function () {
    $audio = mediaFile('clips/episode.mp3');

    expect(fn () => entryWith(['audio' => 'clips/episode.mp3']))->toThrow(ValidationException::class);

    $transcript = entryWith([], collection: 'transcripts', slug: 'episode-transcript');
    $audio->set('reel_transcript_entry', $transcript->id())->save();

    entryWith(['audio' => 'clips/episode.mp3']);

    expect(Entry::query()->where('slug', 'page')->first())->not->toBeNull();
});

it('never blocks on the audio description or on a machine-caption score', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('asr-raw.vtt'));

    // Likely machine output, audio description marked needed: the worst
    // the file can look without a hard error, and the save goes through,
    // because neither is something a file can prove.
    $video = \Statamic\Facades\Asset::find('media::clips/talk.mp4');
    $video->set('reel_audio_description', 'needed')->save();

    entryWith(['video' => 'clips/talk.mp4']);

    expect(Entry::query()->where('slug', 'page')->first())->not->toBeNull();
});

it('ignores an entry with no media at all', function () {
    entryWith(['body' => 'Nothing to see, not even talk.mp4 as a file.']);

    expect(Entry::query()->where('slug', 'page')->first())->not->toBeNull();
});
