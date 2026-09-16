<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/** The engine's front door: text in, an Inspection out. Boots nothing. */
final class Inspector
{
    public function __construct(
        private readonly QualityThresholds $thresholds,
        private readonly MachineCaptionDetector $detector = new MachineCaptionDetector,
        private readonly Parser $parser = new Parser,
        private readonly Validator $validator = new Validator,
    ) {}

    public function inspect(string $vtt): Inspection
    {
        $parsed = $this->parser->parse($vtt);

        return $this->inspectDocument($parsed->document, $parsed->errors);
    }

    /**
     * @param  array<int, ParseError>  $parseErrors
     */
    public function inspectDocument(Document $document, array $parseErrors = []): Inspection
    {
        return new Inspection(
            $document,
            $this->validator->validate($document, $parseErrors),
            (new QualityAnalyzer($this->thresholds))->analyze($document),
            $this->detector->detect($document),
            $this->thresholds,
        );
    }
}
