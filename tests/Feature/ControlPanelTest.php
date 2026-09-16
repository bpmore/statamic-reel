<?php

declare(strict_types=1);

use Bpmore\Reel\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Role;
use Statamic\Facades\User;

beforeEach(function () {
    mediaContainer();
    $this->actingAs(superUser());
});

it('lists every media asset on the utility page with its status', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/done.mp4');
    mediaFile('clips/done.vtt', captions('lecture-professional.vtt'));
    mediaFile('images/photo.jpg');

    $html = utilityHtml(cp_route('utilities.index').'/reel');

    assertVueTemplateIsWellFormed($html);
    expect($html)->toContain('clips/talk.mp4');
    expect($html)->toContain('clips/done.mp4');
    expect($html)->not->toContain('photo.jpg');
    expect($html)->toContain('1 failing');
    expect($html)->toContain('found beside the video');
});

it('shows one asset with its criteria and mounts the editor when there is a track', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('complex.vtt'));

    $html = utilityHtml(cp_route('utilities.reel.asset', ['id' => 'media::clips/talk.mp4']));

    assertVueTemplateIsWellFormed($html);
    expect($html)->toContain('1.2.2 Captions (Prerecorded)');
    expect($html)->toContain('<reel-editor');
    expect($html)->toContain(':can-edit="true"');
    expect($html)->toContain('Not reviewed.');
});

it('does not put cue text into the page template', function () {
    // A cue containing braces would be read by Vue as an interpolation and
    // blank the page. The editor loads cues as JSON, so none is here.
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello {{ world }}\n");

    $html = utilityHtml(cp_route('utilities.reel.asset', ['id' => 'media::clips/talk.mp4']));

    assertVueTemplateIsWellFormed($html);
    expect(str_contains($html, '{{ world }}'))->toBeFalse();
});

it('answers 404 for an asset that is not media', function () {
    mediaFile('images/photo.jpg');

    $this->get(cp_route('utilities.reel.asset', ['id' => 'media::images/photo.jpg']))->assertNotFound();
});

it('loads the caption document and inspection as JSON for the editor', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('complex.vtt'));

    $json = $this->getJson(cp_route('utilities.reel.captions.show', ['id' => 'media::clips/talk.mp4']))->assertOk()->json();

    expect($json['track']['path'])->toBe('clips/talk.vtt');
    expect($json['document']['blocks'])->toHaveCount(9);
    expect($json['inspection']['ok'])->toBeTrue();
    expect($json['can_edit'])->toBeTrue();
});

it('inspects an unsaved document and reports findings by cue', function () {
    $document = ['header' => '', 'preamble' => [], 'blocks' => [
        ['type' => 'cue', 'id' => null, 'start' => '00:00:01.000', 'end' => '00:00:04.000', 'settings' => '', 'text' => 'One'],
        ['type' => 'cue', 'id' => null, 'start' => '00:00:03.000', 'end' => '00:00:06.000', 'settings' => 'align:start', 'text' => 'Two'],
    ]];

    $json = $this->postJson(cp_route('utilities.reel.captions.inspect'), ['document' => $document])->assertOk()->json();

    expect($json['inspection']['errors'][0]['code'])->toBe('cues-overlap');
    expect($json['inspection']['errors'][0]['cue'])->toBe(1);
    expect($json['document']['blocks'][1]['settings'])->toBe(['align' => 'start']);

    $this->postJson(cp_route('utilities.reel.captions.inspect'), ['document' => ['blocks' => [['type' => 'cue', 'start' => 'nope', 'end' => '00:00:01.000']]]])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The cue start "nope" is not a timestamp.');
});

it('writes the edited captions back to the asset, through the serializer', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('complex.vtt'));

    $document = $this->getJson(cp_route('utilities.reel.captions.show', ['id' => 'media::clips/talk.mp4']))->json('document');
    $document['blocks'][3]['text'] = '<v Dr. Smith>Welcome back to the clinic, again.</v>';

    $json = $this->postJson(cp_route('utilities.reel.captions.store', ['id' => 'media::clips/talk.mp4']), ['document' => $document])->assertOk()->json();

    expect($json['saved'])->toBeTrue();
    expect($json['inspection']['ok'])->toBeTrue();

    $written = Storage::disk('media')->get('clips/talk.vtt');
    expect($written)->toContain("intro\n00:00:00.500 --> 00:00:03.000 align:start position:10% size:80% region:speaker\n<v Dr. Smith>Welcome back to the clinic, again.</v>");
    // Everything else exactly as it was: the regions, the style, the notes.
    expect($written)->toBe(str_replace('Welcome back to the clinic.', 'Welcome back to the clinic, again.', captions('complex.vtt')));
});

it('refuses to write for a user without the permission, and still lets them read', function () {
    mediaFile('clips/talk.mp4');
    mediaFile('clips/talk.vtt', captions('complex.vtt'));

    config()->set('statamic.editions.pro', true);
    Role::make('viewer')->permissions(['access cp', 'access reel utility'])->save();
    $viewer = User::make()->id('viewer')->email('viewer@example.test')->assignRole('viewer');
    $viewer->save();
    $this->actingAs($viewer);

    $document = $this->getJson(cp_route('utilities.reel.captions.show', ['id' => 'media::clips/talk.mp4']))->assertOk()->json();
    expect($document['can_edit'])->toBeFalse();

    $this->postJson(cp_route('utilities.reel.captions.store', ['id' => 'media::clips/talk.mp4']), ['document' => $document['document']])->assertForbidden();

    expect(Storage::disk('media')->get('clips/talk.vtt'))->toBe(captions('complex.vtt'));
});

it('grants the write through its own permission and nothing else', function () {
    config()->set('statamic.editions.pro', true);
    Role::make('captioner')->permissions(['access cp', 'access reel utility', ServiceProvider::EDIT_PERMISSION])->save();
    $captioner = User::make()->id('captioner')->email('captioner@example.test')->assignRole('captioner');
    $captioner->save();

    expect($captioner->can(ServiceProvider::EDIT_PERMISSION))->toBeTrue();
    expect($captioner->can('configure asset containers'))->toBeFalse();
});

/**
 * Every view is swept for a directive glued to a word or a bracket, which
 * Blade prints as text with no error. Found on the index page: `)@if`
 * after a `@plain(...)` printed a literal "@if" and then failed on the
 * `@endif` that no longer had a partner.
 */
it('has no Blade directive glued to what comes before it', function () {
    foreach (glob(__DIR__.'/../../resources/views/**/*.blade.php') ?: [] as $file) {
        $hits = preg_match_all('/[A-Za-z0-9_)]@(if|endif|else|elseif|foreach|endforeach|php|endphp|plain)\b/', (string) file_get_contents($file), $m);
        expect($hits)->toBe(0, basename($file).' has a directive glued to a word: '.implode(', ', $m[0]));
    }
});
