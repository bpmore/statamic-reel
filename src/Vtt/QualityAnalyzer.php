<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Soft findings: what a captioning professional would send back.
 *
 * None of these break a player. All of them make a caption harder to read,
 * and every one is measured against a named standard whose figure is in the
 * finding, so the argument can be with the standard.
 */
final class QualityAnalyzer
{
    public function __construct(private readonly QualityThresholds $thresholds) {}

    /**
     * Characters per second, the way a captioner counts: every character a
     * viewer sees, spaces and punctuation included, with a line break
     * counting as the one space it stands in for. Markup is not seen and is
     * not counted. Rounded to two places.
     *
     * A cue of no duration cannot be read at any rate: INF, not a division
     * error. The validator reports the cue; this only has to not fall over.
     */
    public static function readingRate(Cue $cue): float
    {
        $ms = $cue->durationMs();

        if ($ms <= 0) {
            return INF;
        }

        return round(self::characters($cue) * 1000 / $ms, 2);
    }

    public static function characters(Cue $cue): int
    {
        return mb_strlen(implode(' ', $cue->lines()));
    }

    /**
     * @return array<int, Finding>
     */
    public function analyze(Document $document): array
    {
        $t = $this->thresholds;
        $findings = [];
        $previous = null;

        $numbers = $document->cueNumbers();

        foreach ($document->cues() as $i => $cue) {
            // Numbered as in the file, so an unread cue before this one does
            // not shift it out of step with the editor.
            $n = $numbers[$i];
            $label = $cue->id !== null ? "Cue \"{$cue->id}\"" : 'Cue '.($n + 1);
            $lines = $cue->lines();
            $longest = max(array_map('mb_strlen', $lines));

            if (count($lines) > $t->linesPerCue) {
                $findings[] = Finding::warning('too-many-lines', "{$label} has ".count($lines)." lines. {$this->standard()} allows {$t->linesPerCue}.", $n, $cue->line, ['lines' => count($lines), 'limit' => $t->linesPerCue]);
            }

            if ($longest > $t->charactersPerLine) {
                $findings[] = Finding::warning('line-length', "{$label} has a line of {$longest} characters. {$this->standard()} allows {$t->charactersPerLine}.", $n, $cue->line, ['characters' => $longest, 'limit' => $t->charactersPerLine]);
            }

            $duration = $cue->durationMs();

            if ($duration > 0 && $duration < $t->minimumDurationMs) {
                $findings[] = Finding::warning('duration-short', "{$label} shows for {$this->seconds($duration)} s. {$this->standard()} asks for at least {$this->seconds($t->minimumDurationMs)} s.", $n, $cue->line, ['duration_ms' => $duration, 'limit_ms' => $t->minimumDurationMs]);
            } elseif ($duration > $t->maximumDurationMs) {
                $findings[] = Finding::warning('duration-long', "{$label} shows for {$this->seconds($duration)} s. {$this->standard()} allows at most {$this->seconds($t->maximumDurationMs)} s.", $n, $cue->line, ['duration_ms' => $duration, 'limit_ms' => $t->maximumDurationMs]);
            }

            if ($duration > 0) {
                $cps = self::readingRate($cue);

                if ($cps > $t->charactersPerSecond) {
                    $findings[] = Finding::warning('reading-rate', "{$label} reads at {$cps} characters per second. {$this->standard()} allows {$t->charactersPerSecond}.", $n, $cue->line, ['cps' => $cps, 'limit' => $t->charactersPerSecond, 'characters' => self::characters($cue), 'duration_ms' => $duration]);
                }
            }

            if ($previous !== null) {
                $gap = $cue->start->milliseconds - $previous->end->milliseconds;

                if ($gap > $t->frameGapMs && $gap < $t->flickerGapMs) {
                    $findings[] = Finding::warning('gap-flicker', "{$label} starts {$gap} ms after the cue before it ends, which flickers. Close the gap or open it to at least {$this->seconds($t->flickerGapMs)} s.", $n, $cue->line, ['gap_ms' => $gap, 'frame_gap_ms' => $t->frameGapMs, 'limit_ms' => $t->flickerGapMs]);
                }
            }

            $previous = $cue;
        }

        return $findings;
    }

    private function standard(): string
    {
        return 'The '.$this->thresholds->name().' standard';
    }

    private function seconds(int $ms): string
    {
        return rtrim(rtrim(number_format($ms / 1000, 3, '.', ''), '0'), '.');
    }
}
