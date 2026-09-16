<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * One piece of evidence for or against a file being uncorrected machine
 * output, with what it saw written down.
 *
 * `$strength` is how strongly the evidence points, 0 to 1. `$weight` is how
 * much this kind of evidence is worth at full strength. `$contribution` is
 * the product, and the score is the sum. A signal is listed whether or not
 * it fired, so "nothing fired" is visible as a row of zeros rather than an
 * absence.
 */
final class Signal
{
    public readonly float $contribution;

    public readonly bool $fired;

    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly float $weight,
        public readonly float $strength,
        public readonly string $evidence,
        /** Whether this signal is weak evidence by nature, whatever its strength. */
        public readonly bool $lowConfidence = false,
    ) {
        $this->contribution = round($weight * $strength, 3);
        $this->fired = $strength > 0;
    }

    /**
     * @return array{key: string, name: string, fired: bool, weight: float, strength: float, contribution: float, evidence: string, low_confidence: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'fired' => $this->fired,
            'weight' => $this->weight,
            'strength' => round($this->strength, 3),
            'contribution' => $this->contribution,
            'evidence' => $this->evidence,
            'low_confidence' => $this->lowConfidence,
        ];
    }
}
