<?php

declare(strict_types=1);

namespace Bpmore\Reel\Http\Controllers;

use Bpmore\Reel\Media\MediaFinder;
use Bpmore\Reel\ServiceProvider;
use Bpmore\Reel\Settings;
use Bpmore\Reel\Vtt\Document;
use Bpmore\Reel\Vtt\Parser;
use Bpmore\Reel\Vtt\Serializer;
use Bpmore\Reel\Vtt\UnreadBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * The caption editor's three calls: load, inspect, save.
 *
 * The editor never holds the file as text. It holds the document as the
 * parser's array form, sends that back to be inspected as it is edited,
 * and sends it back once more to be written. The serializer, not the
 * browser, produces the file, so what is written is what the parser can
 * read back, byte for byte.
 *
 * `store` is the addon's one write path. It writes a caption file and
 * nothing else, only to the asset the editor was opened for, and only for
 * a person with the permission.
 */
class CaptionsController extends CpController
{
    public function show(string $id, MediaFinder $finder): JsonResponse
    {
        $media = $finder->find($id);

        abort_if($media === null, 404);

        $track = $media->captionTrack();

        if ($track === null) {
            return response()->json(['track' => null]);
        }

        $inspection = $media->inspection();

        return response()->json([
            'track' => ['path' => $track->path(), 'how' => $track->how, 'language' => $track->language, 'id' => $track->asset->id()],
            'document' => $inspection?->document->toArray(),
            'inspection' => $inspection?->toArray(),
            'can_edit' => (bool) User::current()?->can(ServiceProvider::EDIT_PERMISSION),
        ]);
    }

    /** Findings for the document as it stands in the editor, unsaved. */
    public function inspect(Request $request, Settings $settings): JsonResponse
    {
        $document = $this->document($request);

        if ($document instanceof JsonResponse) {
            return $document;
        }

        return response()->json([
            'inspection' => $settings->inspector()->inspectDocument($document)->toArray(),
            'document' => $document->toArray(),
        ]);
    }

    public function store(string $id, Request $request, MediaFinder $finder, Settings $settings): JsonResponse
    {
        abort_unless((bool) User::current()?->can(ServiceProvider::EDIT_PERMISSION), 403, 'You do not have permission to edit caption files.');

        $media = $finder->find($id);

        abort_if($media === null, 404);

        $track = $media->captionTrack();

        abort_if($track === null, 404, 'This asset has no caption track to save.');

        $document = $this->document($request);

        if ($document instanceof JsonResponse) {
            return $document;
        }

        // A cue whose timing cannot be read has no right answer to write: a
        // save used to drop it, text and all. The editor shows it with its
        // error, so the person fixes it or deletes it on purpose.
        $unread = array_keys($document->unreadCues());

        if ($unread !== []) {
            $names = array_map(fn (int $n) => (string) ($n + 1), $unread);
            $list = count($names) === 1 ? 'Cue '.$names[0].' has' : 'Cues '.implode(', ', array_slice($names, 0, -1)).' and '.end($names).' have';

            return response()->json(['message' => $list.' timing that cannot be read. Fix it, or delete the cue, then save.'], 422);
        }

        $vtt = (new Serializer)->serialize($document);

        // Read back before writing: a document the serializer produced must
        // parse, and if it somehow does not, the file on disk is left alone.
        // The one error allowed back is a late STYLE or REGION block the
        // document carried, written back as it was: refusing it would make
        // every file with one unsaveable, since the editor cannot move it.
        $check = (new Parser)->parse($vtt);
        $unexpected = array_filter($check->errors, fn ($e) => $e->code !== UnreadBlock::CODE);

        if ($unexpected !== [] || count($check->errors) !== count($document->unreadBlocks())) {
            return response()->json(['message' => 'The captions could not be written as a valid file.', 'errors' => array_map(fn ($e) => $e->toArray(), $check->errors)], 422);
        }

        $asset = $track->asset;
        $asset->disk()->put($asset->path(), $vtt);
        $asset->save();

        return response()->json([
            'saved' => true,
            'bytes' => strlen($vtt),
            'inspection' => $settings->inspector()->inspect($vtt)->toArray(),
        ]);
    }

    private function document(Request $request): Document|JsonResponse
    {
        $data = $request->input('document');

        if (! is_array($data)) {
            return response()->json(['message' => 'No document was sent.'], 422);
        }

        try {
            return Document::fromArray($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
