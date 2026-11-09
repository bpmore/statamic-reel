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

        // The id names any container. The page shows the asset's fields and
        // its captions, so it is Statamic's own view permission for that
        // container, on top of access to the utility.
        abort_unless((bool) User::current()?->can('view', $media->asset), 403);

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
                'canEdit' => (bool) User::current()?->can(ServiceProvider::EDIT_PERMISSION) && $track !== null && $track->editableBy(User::current()),
                'indexUrl' => cp_route('utilities.index').'/reel',
                'loadUrl' => cp_route('utilities.reel.captions.show', ['id' => $media->id()]),
                'saveUrl' => cp_route('utilities.reel.captions.store', ['id' => $media->id()]),
                'inspectUrl' => cp_route('utilities.reel.captions.inspect'),
                'editUrl' => $media->asset->editUrl(),
            ])->render(),
        ]);
    }
}
