<?php

declare(strict_types=1);

use Bpmore\Reel\Settings;
use Bpmore\Reel\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

// Only the feature tests boot Statamic. The VTT engine and the assessment
// take values and return values, so their tests need no framework, and a
// suite that boots nothing for them cannot grow a dependency on something
// booted.
uses(TestCase::class)->in('Feature');

/** A caption fixture, by file name, as a string. */
function captions(string $name): string
{
    return (string) file_get_contents(__DIR__.'/__fixtures__/'.$name);
}

/**
 * An asset container called `media` on a fake disk, so every feature test
 * has somewhere to put a video and a caption file without touching the
 * machine. Returns the container.
 */
function mediaContainer(): \Statamic\Contracts\Assets\AssetContainer
{
    Storage::fake('media', ['url' => '/assets/media']);

    return AssetContainer::make('media')->disk('media')->save();
}

/** A file on the media disk. Returns its asset. */
function mediaFile(string $path, string $contents = 'not really a media file'): \Statamic\Contracts\Assets\Asset
{
    Storage::disk('media')->put($path, $contents);

    // The container caches its listing, so a file written behind its back
    // is told to it, the way an upload would.
    $container = AssetContainer::find('media');
    $container->contents()->add($path)->save();

    return $container->asset($path);
}

/** The addon's settings, rebuilt from the config as it stands now. */
function reelSettings(array $overrides = []): Settings
{
    foreach ($overrides as $key => $value) {
        config()->set('statamic-reel.'.$key, $value);
    }

    app()->forgetInstance(Settings::class);
    app()->singleton(Settings::class, fn () => Settings::fromConfig());

    return app(Settings::class);
}

/** An entry in a collection that exists, with data as given. Saved through the gate. */
function entryWith(array $data, string $collection = 'pages', string $slug = 'page'): \Statamic\Contracts\Entries\Entry
{
    if (Collection::find($collection) === null) {
        Collection::make($collection)->save();
    }

    $entry = Entry::make()->collection($collection)->slug($slug)->data(array_merge(['title' => ucfirst($slug)], $data));
    $entry->save();

    return $entry;
}

/** A super user, logged in. */
function superUser(): \Statamic\Contracts\Auth\User
{
    $user = User::make()->id('tester')->email('tester@example.test')->makeSuper();
    $user->save();

    return $user;
}

/**
 * Whether markup Vue will compile is well formed.
 *
 * A utility view becomes `defineComponent({ template })` in the browser,
 * where an unclosed tag is a compile error that shows as a blank page and
 * never as an exception on the server. Vue's template syntax is close
 * enough to XML that parsing the markup as XML, with void elements made
 * self-closing and bare attributes given a value, catches the mistake here.
 */
function assertVueTemplateIsWellFormed(string $html): void
{
    $xml = preg_replace('/<(input|br|hr|img)([^>]*?)\s*\/?>/', '<$1$2 />', $html);

    $xml = preg_replace_callback('/<[a-z][\w-]*(\s[^<>]*)?\/?>/i', function ($m) {
        return preg_replace_callback(
            '/(\s)([a-z:@][\w:@.-]*)(="[^"]*")?/i',
            fn ($a) => $a[1].$a[2].($a[3] ?? '="'.$a[2].'"'),
            $m[0],
        );
    }, (string) $xml);
    $xml = html_entity_decode((string) $xml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $xml = str_replace('&', '&amp;', $xml);

    $previous = libxml_use_internal_errors(true);
    $ok = simplexml_load_string('<root>'.$xml.'</root>') !== false;
    $errors = array_map(fn ($e) => trim($e->message).' (line '.$e->line.')', libxml_get_errors());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    expect($ok)->toBeTrue('the template is not well formed, so Vue would not compile it: '.implode('; ', $errors));
}

/** A utility page's own markup, as Vue will receive it: unpacked from Inertia's page object. */
function utilityHtml(string $url): string
{
    $response = test()->get($url)->assertOk()->getContent();

    preg_match('/data-page="([^"]+)"/', $response, $m);
    $page = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

    return (string) $page['props']['html'];
}
