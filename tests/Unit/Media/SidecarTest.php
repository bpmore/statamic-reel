<?php

declare(strict_types=1);

use Bpmore\Reel\Media\Sidecar;

it('finds sidecars by name in the same folder, exact first, then language-coded, and never another folder', function () {
    $sidecar = new Sidecar(['{name}.vtt', '{name}.*.vtt']);

    $files = [
        'media/talk.mp4',
        'media/talk.fr.vtt',
        'media/talk.vtt',
        'media/talk.en.vtt',
        'media/other.vtt',
        'media/talk.mp4.vtt',
        'archive/talk.vtt',
        'talk.vtt',
    ];

    expect($sidecar->matches('media/talk.mp4', $files))->toBe(['media/talk.vtt', 'media/talk.en.vtt', 'media/talk.fr.vtt', 'media/talk.mp4.vtt']);
    expect($sidecar->matches('talk.mp4', $files))->toBe(['talk.vtt']);
    expect($sidecar->matches('media/nothing.mp4', $files))->toBe([]);
});

it('reads the language code out of the sidecar name and not out of anything else', function () {
    expect(Sidecar::language('media/talk.en.vtt', 'media/talk.mp4'))->toBe('en');
    expect(Sidecar::language('media/talk.pt-BR.vtt', 'media/talk.mp4'))->toBe('pt-BR');
    expect(Sidecar::language('media/talk.vtt', 'media/talk.mp4'))->toBeNull();
    expect(Sidecar::language('media/talk.final.vtt', 'media/talk.mp4'))->toBeNull();
    expect(Sidecar::language('media/talk.mp4.vtt', 'media/talk.mp4'))->toBeNull();
});
