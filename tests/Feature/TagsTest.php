<?php

declare(strict_types=1);

use Statamic\Facades\Asset;
use Statamic\Facades\Antlers;

// `trusted: true` on every parse. Statamic 6 treats a string parsed
// without it as user content, where addon tags are guarded and produce
// nothing but a log line. A site's templates are parsed trusted.
beforeEach(function () {
    mediaContainer();
    reelSettings(['captions.language' => 'en']);
});

it('emits one track per sidecar, the first as default, with language and label', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('lecture-professional.vtt'));
    mediaFile('clips/talk.fr.vtt', captions('lecture-professional.vtt'));

    $html = (string) Antlers::parse('{{ reel:tracks asset="clips/talk.mp4" }}', [], trusted: true);

    // Antlers hands back CRLF line endings; the tag itself writes LF.
    expect(str_replace("\r\n", "\n", $html))->toBe(implode("\n", [
        '<track kind="captions" src="/assets/media/clips/talk.vtt" srclang="en" label="English" default>',
        '<track kind="captions" src="/assets/media/clips/talk.fr.vtt" srclang="fr" label="Français">',
    ]));
});

it('emits only the chosen track when one is chosen on the asset, and honours kind and default', function () {
    $video = mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('lecture-professional.vtt'));
    mediaFile('clips/captions-final.vtt', captions('lecture-professional.vtt'));
    $video->set('reel_captions', 'clips/captions-final.vtt')->save();

    $html = (string) Antlers::parse('{{ reel:tracks :asset="video" kind="subtitles" default="false" }}', ['video' => 'clips/talk.mp4'], trusted: true);

    expect($html)->toBe('<track kind="subtitles" src="/assets/media/clips/captions-final.vtt" srclang="en" label="English">');
});

it('emits nothing for a video with no track, and nothing for a missing asset', function () {
    mediaFile('clips/talk.mp4');

    expect((string) Antlers::parse('{{ reel:tracks asset="clips/talk.mp4" }}', [], trusted: true))->toBe('');
    expect((string) Antlers::parse('{{ reel:tracks asset="clips/nothing.mp4" }}', [], trusted: true))->toBe('');
});

it('accepts an asset object from the context, as an assets field gives it', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('lecture-professional.vtt'));

    $html = (string) Antlers::parse('{{ reel:tracks :asset="video" }}', ['video' => Asset::find('media::clips/talk.mp4')], trusted: true);

    expect($html)->toContain('srclang="en"');
});

it('renders the transcript as a section labelled by its heading, from the Bard field', function () {
    $video = mediaFile('clips/talk.mp4');
    $video->set('reel_transcript', [
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Opening']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Good morning, everyone.']]],
    ])->save();

    $html = (string) Antlers::parse('{{ reel:transcript asset="clips/talk.mp4" heading="3" title="Full transcript" }}', [], trusted: true);

    expect($html)->toStartWith('<section class="reel-transcript" aria-labelledby="reel-transcript-');
    expect($html)->toMatch('/<h3 id="reel-transcript-[a-f0-9]{8}">Full transcript<\/h3>/');
    expect($html)->toContain('<h3>Opening</h3>');
    expect($html)->toContain('<p>Good morning, everyone.</p>');
    expect($html)->toEndWith('</section>');

    // The section is labelled by the heading it contains, and by nothing else.
    preg_match('/aria-labelledby="([^"]+)"/', $html, $m);
    expect($html)->toContain('id="'.$m[1].'"');
});

it('renders a linked transcript entry as a link to its page', function () {
    $video = mediaFile('clips/talk.mp4');
    $entry = entryWith(['title' => 'Talk transcript'], collection: 'transcripts', slug: 'talk-transcript');
    \Statamic\Facades\Collection::find('transcripts')->routes('/transcripts/{slug}')->save();
    $video->set('reel_transcript_entry', $entry->id())->save();

    $html = (string) Antlers::parse('{{ reel:transcript asset="clips/talk.mp4" }}', [], trusted: true);

    expect($html)->toContain('<h2 id="reel-transcript-');
    expect($html)->toContain('<a href="/transcripts/talk-transcript">Read the transcript: Talk transcript</a>');
});

it('renders nothing at all when there is no transcript', function () {
    mediaFile('clips/talk.mp4');

    expect((string) Antlers::parse('{{ reel:transcript asset="clips/talk.mp4" }}', [], trusted: true))->toBe('');
});

it('escapes what it prints', function () {
    $video = mediaFile('clips/talk.mp4');
    $video->set('reel_transcript', [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a <b> & c']]]])->save();

    $html = (string) Antlers::parse('{{ reel:transcript asset="clips/talk.mp4" title=\'"><script>\' }}', [], trusted: true);

    expect($html)->toContain('&quot;&gt;&lt;script&gt;');
    expect($html)->toContain('a &lt;b&gt; &amp; c');
});
