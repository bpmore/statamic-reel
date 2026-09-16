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

        foreach ($parseErrors as $error) {
            $findings[] = Finding::error($error->code, $error->message, null, $error->line);
        }

        $regions = $document->regions();
        $cues = $document->cues();
        $maxEnd = PHP_INT_MIN;

        foreach ($cues as $i => $cue) {
            $label = $this->label($cue, $i);

            if ($cue->end->milliseconds < $cue->start->milliseconds) {
                $findings[] = Finding::error('end-before-start', "{$label} ends before it starts ({$cue->start->format()} to {$cue->end->format()}).", $i, $cue->line);
            } elseif ($cue->end->milliseconds === $cue->start->milliseconds) {
                $findings[] = Finding::error('zero-duration', "{$label} starts and ends at {$cue->start->format()}, so it never shows.", $i, $cue->line);
            }

            if ($i > 0 && $cue->start->milliseconds < $cues[$i - 1]->start->milliseconds) {
                $findings[] = Finding::error('cues-out-of-order', "{$label} starts at {$cue->start->format()}, before the cue above it. Cues must be in time order.", $i, $cue->line);
            }

            $region = $cue->region();

            if ($region !== null && ! isset($regions[$region])) {
                $findings[] = Finding::error('region-undefined', "{$label} is placed in region \"{$region}\", which the file does not define.", $i, $cue->line, ['region' => $region]);
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
                        "{$label} ({$cue->start->format()} to {$cue->end->format()}) overlaps ".lcfirst($this->label($other, $j))." ({$other->start->format()} to {$other->end->format()}). Two cues can only show at once inside a region with room to stack.",
                        $i,
                        $cue->line,
                        ['with' => $j],
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

    private function label(Cue $cue, int $index): string
    {
        return $cue->id !== null ? "Cue \"{$cue->id}\"" : 'Cue '.($index + 1);
    }
}
