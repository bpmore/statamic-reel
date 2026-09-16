<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * How likely a file is to be uncorrected machine output, and why.
 *
 * Never a boolean. The confidence is a sum of named signals, each with the
 * evidence it saw, and the label is a reading of the number, not a verdict.
 * A stylistic choice that trips one weak signal is "unlikely" with the
 * signal listed, which is what lets an editor say "yes, that was on purpose"
 * and move on.
 */
final class MachineCaptionScore
{
    public const UNLIKELY = 'unlikely';

    public const POSSIBLE = 'possible';

    public const LIKELY = 'likely';

    public readonly float $confidence;

    public readonly string $label;

    /**
     * @param  array<int, Signal>  $signals  every signal, fired or not
     */
    public function __construct(public readonly array $signals)
    {
        $sum = 0.0;

        foreach ($signals as $signal) {
            $sum += $signal->contribution;
        }

        $this->confidence = round(min(1.0, $sum), 2);
        $this->label = match (true) {
            $this->confidence >= 0.6 => self::LIKELY,
            $this->confidence >= 0.3 => self::POSSIBLE,
            default => self::UNLIKELY,
        };
    }

    /** @return array<int, Signal> the signals that fired, strongest first */
    public function contributing(): array
    {
        $fired = array_values(array_filter($this->signals, fn (Signal $s) => $s->fired));

        usort($fired, fn (Signal $a, Signal $b) => $b->contribution <=> $a->contribution);

        return $fired;
    }

    /**
     * @return array{confidence: float, label: string, signals: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'label' => $this->label,
            'signals' => array_map(fn (Signal $s) => $s->toArray(), $this->signals),
        ];
    }
}
