<?php

declare(strict_types=1);

namespace Bpmore\Reel\Commands;

use Bpmore\Reel\Compliance\Assessment;
use Bpmore\Reel\Media\MediaAsset;
use Bpmore\Reel\Media\MediaFinder;
use Bpmore\Reel\Settings;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php please reel:audit`: every media asset, its status, and its findings.
 *
 * Exit codes: 0 nothing at or above the threshold, 1 something is, 2 the
 * command itself could not run. `--json` is the whole of stdout and
 * nothing else, so CI can pipe it straight into `jq`.
 */
class Audit extends Command
{
    use RunsInPlease;

    public const EXIT_FOUND = 1;

    public const EXIT_INVALID = 2;

    protected $signature = 'statamic:reel:audit
        {--container=* : Asset container handle, repeatable; every container when omitted}
        {--threshold= : fail, warn, manual or pass; exit 1 when any asset is at or above it. From the config file when omitted}
        {--json= : Write the report as JSON to this path, or to stdout with no path}';

    protected $description = 'Report every video and audio asset, its caption and transcript status against WCAG 1.2, and what the caption files need.';

    public function handle(MediaFinder $finder, Settings $settings): int
    {
        $threshold = (string) ($this->option('threshold') ?? $settings->auditThreshold());

        if (! in_array($threshold, Assessment::VERDICTS, true)) {
            $this->components->error('--threshold accepts '.implode(', ', Assessment::VERDICTS).", got [{$threshold}].");

            return self::EXIT_INVALID;
        }

        $containers = array_values(array_map('strval', (array) $this->option('container')));

        foreach ($containers as $handle) {
            if (\Statamic\Facades\AssetContainer::find($handle) === null) {
                $this->components->error("There is no asset container [{$handle}].");

                return self::EXIT_INVALID;
            }
        }

        $report = $this->report($finder->all($containers), $settings, $threshold);

        $this->output($report);

        return $report['summary']['at_or_above_threshold'] > 0 ? self::EXIT_FOUND : self::SUCCESS;
    }

    /**
     * @param  array<int, MediaAsset>  $media
     * @return array<string, mixed>
     */
    private function report(array $media, Settings $settings, string $threshold): array
    {
        $assets = [];
        $counts = array_fill_keys(Assessment::VERDICTS, 0);
        $rank = array_flip(Assessment::VERDICTS);
        $flagged = 0;

        foreach ($media as $item) {
            $assessment = $item->assess();
            $inspection = $item->inspection();
            $track = $item->captionTrack();
            $counts[$assessment->verdict]++;

            if ($rank[$assessment->verdict] <= $rank[$threshold]) {
                $flagged++;
            }

            $assets[] = [
                'id' => $item->id(),
                'container' => $item->asset->containerHandle(),
                'path' => $item->asset->path(),
                'kind' => $item->kind(),
                'verdict' => $assessment->verdict,
                'problems' => $assessment->problems,
                'criteria' => $assessment->toArray()['criteria'],
                'captions' => $track === null ? null : [
                    'path' => $track->path(),
                    'how' => $track->how,
                    'language' => $track->language,
                    'cues' => $inspection === null ? 0 : count($inspection->document->cues()),
                    'duration_ms' => $inspection?->document->durationMs() ?? 0,
                    'errors' => $inspection === null ? [] : array_map(fn ($f) => $f->toArray(), $inspection->errors),
                    'warnings' => $inspection === null ? [] : array_map(fn ($f) => $f->toArray(), $inspection->warnings),
                    'machine' => $inspection?->machine->toArray(),
                ],
                'chosen_track_missing' => $item->chosenTrackIsMissing(),
                'transcript' => $item->hasTranscript(),
                'audio_description' => $item->audioDescription(),
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'standard' => $settings->thresholds()->toArray(),
            'threshold' => $threshold,
            'summary' => ['assets' => count($assets), 'at_or_above_threshold' => $flagged] + $counts,
            'assets' => $assets,
        ];
    }

    /**
     * `--json` alone means the JSON is the whole of stdout. `--json=path`
     * writes the file and prints the human report as usual.
     *
     * @param  array<string, mixed>  $report
     */
    private function output(array $report): void
    {
        $path = $this->option('json');
        $jsonWanted = $this->input->hasParameterOption('--json') || $path !== null;
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        // From the terminal, a bare `--json` arrives as null. Called from
        // code with `['--json' => true]`, it arrives as true.
        if ($jsonWanted && ($path === null || $path === '' || is_bool($path))) {
            $this->output->write($json."\n", false, OutputInterface::OUTPUT_RAW);

            return;
        }

        if ($jsonWanted) {
            file_put_contents((string) $path, $json."\n");
        }

        $this->human($report);

        if ($jsonWanted) {
            $this->components->info("JSON written to {$path}");
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function human(array $report): void
    {
        $s = $report['summary'];

        if ($s['assets'] === 0) {
            $this->components->info('No video or audio assets found.');

            return;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>Media assets</>', (string) $s['assets']);
        $this->components->twoColumnDetail('Fail', (string) $s['fail']);
        $this->components->twoColumnDetail('Warn', (string) $s['warn']);
        $this->components->twoColumnDetail('Not reviewed', (string) $s['manual']);
        $this->components->twoColumnDetail('Pass', (string) $s['pass']);
        $this->components->twoColumnDetail('Standard', $report['standard']['standard']);
        $this->newLine();

        foreach ($report['assets'] as $a) {
            $label = match ($a['verdict']) {
                Assessment::FAIL => '<fg=red>FAIL</>',
                Assessment::WARN => '<fg=yellow>WARN</>',
                Assessment::MANUAL => '<fg=blue>REVIEW</>',
                default => '<fg=green>PASS</>',
            };

            $this->components->twoColumnDetail("{$a['container']}::{$a['path']} ({$a['kind']})", $label);

            foreach ($a['problems'] as $problem) {
                $this->line("    <fg=red>*</> {$problem}");
            }

            if ($a['captions'] !== null) {
                $c = $a['captions'];
                $this->line(sprintf('    captions: %s (%s, %s, %d cues)', $c['path'], $c['how'], $c['language'], $c['cues']));

                foreach ($c['errors'] as $e) {
                    $this->line("    <fg=red>*</> line {$e['line']}: {$e['message']}");
                }

                if ($c['warnings'] !== []) {
                    $codes = array_count_values(array_column($c['warnings'], 'code'));
                    arsort($codes);
                    $this->line('    <fg=yellow>*</> '.count($c['warnings']).' quality findings: '.implode(', ', array_map(fn ($k, $v) => "{$k} x{$v}", array_keys($codes), $codes)));
                }

                if ($c['machine'] !== null && $c['machine']['label'] !== 'unlikely') {
                    $fired = array_filter($c['machine']['signals'], fn ($sig) => $sig['fired']);
                    $this->line(sprintf('    <fg=yellow>*</> machine captions %s (%d%%): %s', $c['machine']['label'], round($c['machine']['confidence'] * 100), implode(', ', array_column($fired, 'key'))));
                }
            } elseif ($a['chosen_track_missing']) {
                $this->line('    <fg=red>*</> The chosen caption track no longer exists.');
            }

            foreach ($a['criteria'] as $c) {
                if (in_array($c['status'], ['manual', 'fail'], true) && $c['criterion'] !== '1.2.4') {
                    $this->line(sprintf('    %s %s: %s', $c['criterion'], $c['status'] === 'fail' ? '<fg=red>fail</>' : '<fg=blue>review</>', $c['reason']));
                }
            }
        }

        $this->newLine();
    }
}
