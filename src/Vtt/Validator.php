<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Hard failures: what makes a file wrong rather than poor.
 *
 * Everything here is either a format error the spec names or a timing fault
 * a player cannot show correctly. Whether a cue reads well is the quality
 * analyzer's business, and the two are kept apart so a site can block on one
 * and only warn on the other.
 */
final class Validator
{
    /**
     * @param  array<int, ParseError>  $parseErrors  from the parse, carried through as errors
     * @return array<int, Finding>
     */
    public function validate(Document $document, array $parseErrors = []): array
    {
        $findings = [];
        $unread = $document->unreadCues();
        $unreadLines = array_map(fn (UnreadCue $u) => $u->line, $unread);

        // A parse error about a cue that was kept unread is reported on that
        // cue below, so the editor can show it there and it is not counted
        // twice.
        foreach ($parseErrors as $error) {
            if (! in_array($error->line, $unreadLines, true)) {
                $findings[] = Finding::error($error->code, $error->message, null, $error->line);
            }
        }

        foreach ($unread as $n => $cue) {
            $findings[] = Finding::error($cue->code(), $cue->problem(), $n, $cue->line > 0 ? $cue->line : null);
        }

        $regions = $document->regions();
        $cues = $document->cues();
        $numbers = $document->cueNumbers();
        $maxEnd = PHP_INT_MIN;

        foreach ($cues as $i => $cue) {
            $n = $numbers[$i];
            $label = $this->label($cue, $n);

            if ($cue->end->milliseconds < $cue->start->milliseconds) {
                $findings[] = Finding::error('end-before-start', "{$label} ends before it starts ({$cue->start->format()} to {$cue->end->format()}).", $n, $cue->line);
            } elseif ($cue->end->milliseconds === $cue->start->milliseconds) {
                $findings[] = Finding::error('zero-duration', "{$label} starts and ends at {$cue->start->format()}, so it never shows.", $n, $cue->line);
            }

            if ($i > 0 && $cue->start->milliseconds < $cues[$i - 1]->start->milliseconds) {
                $findings[] = Finding::error('cues-out-of-order', "{$label} starts at {$cue->start->format()}, before the cue above it. Cues must be in time order.", $n, $cue->line);
            }

            $region = $cue->region();

            if ($region !== null && ! isset($regions[$region])) {
                $findings[] = Finding::error('region-undefined', "{$label} is placed in region \"{$region}\", which the file does not define.", $n, $cue->line, ['region' => $region]);
            }

            // Only cues that could overlap are compared, which for a file in
            // order with no overlaps is none of them.
            if ($maxEnd > $cue->start->milliseconds) {
                for ($j = 0; $j < $i; $j++) {
                    $other = $cues[$j];

                    if (! $cue->overlaps($other) || $this->overlapPermitted($cue, $other, $regions)) {
                        continue;
                    }

                    $findings[] = Finding::error(
                        'cues-overlap',
                        "{$label} ({$cue->start->format()} to {$cue->end->format()}) overlaps ".lcfirst($this->label($other, $numbers[$j]))." ({$other->start->format()} to {$other->end->format()}). Two cues can only show at once inside a region with room to stack.",
                        $n,
                        $cue->line,
                        ['with' => $numbers[$j]],
                    );
                }
            }

            $maxEnd = max($maxEnd, $cue->end->milliseconds);
        }

        usort($findings, fn (Finding $a, Finding $b) => ($a->line ?? 0) <=> ($b->line ?? 0));

        return $findings;
    }

    /**
     * Two cues may overlap when both sit in a defined region and either the
     * regions differ (two boxes on screen) or the shared one stacks lines.
     * A region with one line replaces its cue rather than adding to it.
     *
     * @param  array<string, Region>  $regions
     */
    private function overlapPermitted(Cue $a, Cue $b, array $regions): bool
    {
        $ra = $a->region();
        $rb = $b->region();

        if ($ra === null || $rb === null || ! isset($regions[$ra], $regions[$rb])) {
            return false;
        }

        return $ra !== $rb || $regions[$ra]->allowsStacking();
    }

    /** @param  int  $number  the cue's number in the file, from `Document::cueNumbers()` */
    private function label(Cue $cue, int $number): string
    {
        return $cue->id !== null ? "Cue \"{$cue->id}\"" : 'Cue '.($number + 1);
    }
}
