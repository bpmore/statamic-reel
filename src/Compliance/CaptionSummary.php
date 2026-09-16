<?php

declare(strict_types=1);

namespace Bpmore\Reel\Compliance;

use Bpmore\Reel\Vtt\Inspection;

/**
 * What the assessment needs to know about a caption track, and no more.
 * A value rather than the whole inspection, so the assessment can be
 * tested without a file.
 */
final class CaptionSummary
{
    public function __construct(
        public readonly string $path,
        public readonly int $errors,
        public readonly int $warnings,
        public readonly float $machineConfidence,
        public readonly string $machineLabel,
        public readonly int $cues,
    ) {}

    public static function fromInspection(string $path, Inspection $inspection): self
    {
        return new self(
            $path,
            count($inspection->errors),
            count($inspection->warnings),
            $inspection->machine->confidence,
            $inspection->machine->label,
            count($inspection->document->cues()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'machine_confidence' => $this->machineConfidence,
            'machine_label' => $this->machineLabel,
            'cues' => $this->cues,
        ];
    }
}
