<?php

declare(strict_types=1);

namespace Bpmore\Reel\Http\Controllers;

use Bpmore\Reel\Media\MediaFinder;
use Bpmore\Reel\ServiceProvider;
use Bpmore\Reel\Settings;
use Inertia\Inertia;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/** One media asset: its compliance view and the caption editor. */
class AssetController extends CpController
{
    public function show(string $id, MediaFinder $finder, Settings $settings)
    {
        $media = $finder->find($id);

        abort_if($media === null, 404, 'No video or audio asset with that id.');

        $assessment = $media->assess();
        $track = $media->captionTrack();
        $inspection = $media->inspection();

        return Inertia::render('utilities/Show', [
            'title' => 'Reel: '.$media->asset->basename(),
            'html' => view('reel::utilities.asset', [
                'media' => $media,
                'assessment' => $assessment,
                'track' => $track,
                'inspection' => $inspection,
                'sidecars' => $media->sidecars(),
                'transcriptEntry' => $media->transcriptEntry(),
                'standard' => $settings->thresholds(),
                'canEdit' => (bool) User::current()?->can(ServiceProvider::EDIT_PERMISSION),
                'indexUrl' => cp_route('utilities.index').'/reel',
                'loadUrl' => cp_route('utilities.reel.captions.show', ['id' => $media->id()]),
                'saveUrl' => cp_route('utilities.reel.captions.store', ['id' => $media->id()]),
                'inspectUrl' => cp_route('utilities.reel.captions.inspect'),
                'editUrl' => $media->asset->editUrl(),
            ])->render(),
        ]);
    }
}
