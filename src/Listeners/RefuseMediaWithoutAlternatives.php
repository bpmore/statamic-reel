<?php

declare(strict_types=1);

namespace Bpmore\Reel\Listeners;

use Bpmore\Reel\Gate\GateSettings;
use Bpmore\Reel\Media\MediaFinder;
use Bpmore\Reel\Settings;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Statamic\Events\EntrySaving;

/**
 * The publish gate. Statamic binds this to `EntrySaving` from the type
 * hint on `handle`, so there is nothing to register.
 *
 * It throws a `ValidationException` rather than returning false, for the
 * reason Accessibility Gate's listener explains at length: a false return
 * stops the save with no message, and the control panel turns that into
 * "Couldn't save entry" with nothing an author can act on. A 422 with the
 * reasons keyed by the field that holds the media puts them under that
 * field, where the author is looking.
 *
 * The gate blocks on what a file can prove and nothing else: a video with
 * no caption track or a track a player cannot read, an audio file with no
 * transcript. The machine-caption score is a likelihood and never blocks.
 * The audio description cannot be detected and never blocks.
 *
 * Standalone by decision, not by oversight. Accessibility Gate's checks are
 * a fixed list with no registration point, and a shared rules contract was
 * judged not worth extracting until a third addon needs it.
 */
final class RefuseMediaWithoutAlternatives
{
    public function __construct(
        private readonly MediaFinder $finder,
        private readonly Settings $settings,
        private readonly LoggerInterface $log,
    ) {}

    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;
        $mode = $this->settings->gate()->modeFor((string) $entry->collectionHandle());

        if ($mode === GateSettings::OFF) {
            return;
        }

        $errors = [];

        foreach ($this->finder->inEntry($entry) as $use) {
            $assessment = $use['media']->assess();

            if (! $assessment->blocks()) {
                continue;
            }

            foreach ($assessment->problems as $problem) {
                $errors[$use['field']][] = $problem.' ('.$use['media']->asset->path().')';
            }
        }

        if ($errors === []) {
            return;
        }

        if ($mode === GateSettings::WARN) {
            // Warn mode still says what it found. A mode that stayed quiet
            // would be indistinguishable from the addon not being installed.
            $this->log->warning(
                'Reel would have refused this entry. It was saved because the gate mode is warn.',
                ['entry' => $entry->id(), 'problems' => $errors],
            );

            return;
        }

        throw ValidationException::withMessages($errors)->status(422);
    }
}
