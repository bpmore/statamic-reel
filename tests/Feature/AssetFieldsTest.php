<?php

declare(strict_types=1);

beforeEach(fn () => mediaContainer());

/**
 * A container has one blueprint object per request, and fields ensured on
 * it for one asset stay on it for the next. In the control panel that is
 * harmless: an edit request resolves one asset. Here three assets are read
 * in one process, so the request cache is cleared between them, which is
 * what a new request would do.
 */
function fresh(\Statamic\Contracts\Assets\Asset $asset): \Statamic\Contracts\Assets\Asset
{
    \Statamic\Facades\Blink::flush();

    return \Statamic\Facades\Asset::find($asset->id());
}

it('adds the Reel fields to a video asset, the transcript fields to audio, and nothing to an image', function () {
    $video = fresh(mediaFile('clips/talk.mp4'));
    $audio = mediaFile('clips/episode.mp3');
    $image = mediaFile('images/photo.jpg');

    $videoFields = $video->blueprint()->fields()->all()->keys()->all();
    expect($videoFields)->toContain('reel_captions', 'reel_transcript', 'reel_transcript_entry', 'reel_audio_description');
    expect($video->blueprint()->field('reel_captions')->config()['container'])->toBe('media');
    expect($video->blueprint()->field('reel_transcript')->type())->toBe('bard');

    $audioFields = fresh($audio)->blueprint()->fields()->all()->keys()->all();
    expect($audioFields)->toContain('reel_transcript', 'reel_transcript_entry');
    expect($audioFields)->not->toContain('reel_captions');
    expect($audioFields)->not->toContain('reel_audio_description');

    $imageFields = fresh($image)->blueprint()->fields()->all()->keys()->all();
    expect(array_intersect($imageFields, ['reel_captions', 'reel_transcript', 'reel_transcript_entry', 'reel_audio_description']))->toBe([]);
});

it('adds nothing when the site has turned it off', function () {
    reelSettings(['add_fields_to_assets' => false]);

    $video = mediaFile('clips/talk.mp4');

    expect($video->blueprint()->fields()->all()->keys()->all())->not->toContain('reel_captions');
});
