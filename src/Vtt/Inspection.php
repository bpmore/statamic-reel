<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Everything the engine has to say about one file, in one value.
 *
 * Errors are the validator's, parse errors included. Warnings are the
 * quality analyzer's. The machine score stands apart from both, because it
 * is a likelihood rather than a finding, and a site decides what to do
 * with it.
 */
final class Inspection
{
    /**
     * @param  array<int, Finding>  $errors
     * @param  array<int, Finding>  $warnings
     */
    public function __construct(
        public readonly Document $document,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly MachineCaptionScore $machine,
        public readonly QualityThresholds $thresholds,
    ) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'cues' => count($this->document->cues()),
            'duration_ms' => $this->document->durationMs(),
            'errors' => array_map(fn (Finding $f) => $f->toArray(), $this->errors),
            'warnings' => array_map(fn (Finding $f) => $f->toArray(), $this->warnings),
            'machine' => $this->machine->toArray(),
            'standard' => $this->thresholds->toArray(),
        ];
    }
}
