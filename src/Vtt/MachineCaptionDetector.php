<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * Heuristics that, together, suggest a file is raw speech-recognition output
 * nobody has corrected.
 *
 * Uncorrected machine captions fail WCAG 1.2.2, and no single tell proves
 * them. A lecturer captioned by a professional has no [music] because there
 * is none; a shouted line is set in capitals on purpose; a continuation cue
 * starts lowercase because the sentence did not end. Each signal is
 * therefore a ramp rather than a switch, weighted by how rarely a person
 * produces it, and the score names every signal with what it saw so an
 * editor can overrule the evidence rather than the number.
 *
 * Getting this wrong in either direction is the product's main risk. A
 * false positive teaches editors to ignore the tool; a false negative lets
 * the thing it exists to catch through. The weights are chosen so that the
 * two weak signals, which a professional single-speaker file can trip,
 * cannot reach "possible" on their own.
 */
final class MachineCaptionDetector
{
    /**
     * The first three are what recognisers used to leave out. The next three
     * are what current ones leave in: YouTube's output, checked September
     * 2026, is punctuated and capitalised, and gives itself away instead by
     * timing every word, rolling each line through two cues, and cutting at
     * a fixed width so cues open with the tail of the last sentence. The
     * last two are weak by nature. The weights sum past one so that either
     * generation of output reaches "likely" on its own; the score is capped.
     */
    public const WEIGHTS = [
        'no-terminal-punctuation' => 0.30,
        'no-capitalization' => 0.20,
        'uniform-cue-lengths' => 0.20,
        'word-level-timing' => 0.30,
        'sentence-tail-starts' => 0.20,
        'rolling-duplicates' => 0.15,
        'no-non-speech-annotations' => 0.10,
        'no-speaker-labels' => 0.10,
    ];

    /** Under this many cues, the text signals are scaled down: seven cues prove little. */
    private const SMALL_SAMPLE = 12;

    /** Words a cue that answers a question tends to start with. */
    private const REPLIES = ['yes', 'no', 'yeah', 'yep', 'nope', 'well', 'okay', 'ok', 'sure', 'right', 'i', 'we', 'it', 'that', 'maybe', 'absolutely', 'exactly', 'of', 'not', 'because'];

    public function __construct(
        /** A file shorter than this is not expected to have any sound annotation. */
        private readonly int $annotationDurationMs = 600_000,
        /** Fewer cues than this and cue-length uniformity means nothing. */
        private readonly int $uniformityMinimumCues = 8,
    ) {}

    public function detect(Document $document): MachineCaptionScore
    {
        $cues = $document->cues();
        $texts = array_map(fn (Cue $c) => trim($c->plainText()), $cues);
        $texts = array_values(array_filter($texts, fn ($t) => $t !== ''));

        return new MachineCaptionScore([
            $this->punctuation($texts),
            $this->capitalization($texts),
            $this->uniformity($cues),
            $this->wordTiming($cues),
            $this->tailStarts($texts),
            $this->rolling($cues),
            $this->annotations($cues, $texts, $document->durationMs()),
            $this->speakers($cues, $texts),
        ]);
    }

    /**
     * Sentence punctuation anywhere in the cue. A cue that carries part of a
     * sentence legitimately has none, so the ramp starts at 60% of cues
     * without any and is full at 90%. Raw recognition output has none at all.
     *
     * @param  array<int, string>  $texts
     */
    private function punctuation(array $texts): Signal
    {
        $n = count($texts);
        $without = count(array_filter($texts, fn ($t) => ! preg_match('/[.!?\x{2026}]/u', $t)));
        $share = $n === 0 ? 0.0 : $without / $n;

        return new Signal(
            'no-terminal-punctuation',
            'No sentence punctuation',
            self::WEIGHTS['no-terminal-punctuation'],
            $n === 0 ? 0.0 : self::ramp($share, 0.6, 0.9) * self::sample($n),
            $n === 0 ? 'No cues to read.' : sprintf('%d of %d cues (%d%%) contain no full stop, question mark or exclamation mark.', $without, $n, round($share * 100)),
        );
    }

    /**
     * Two readings, and the stronger counts. Cues that begin with a lowercase
     * letter: a continuation cue does, so the ramp runs from 60% to 95%. And
     * the share of letters that are capitals across the whole file: below
     * half a percent means nothing was ever capitalised, not even "I".
     *
     * All capitals is the opposite of this and scores nothing: no recogniser
     * shouts, and a file that does was set that way by somebody.
     *
     * @param  array<int, string>  $texts
     */
    private function capitalization(array $texts): Signal
    {
        $starts = 0;
        $lowerStarts = 0;
        $upper = 0;
        $lower = 0;

        foreach ($texts as $text) {
            $first = mb_substr($text, 0, 1);

            if (preg_match('/\p{L}/u', $first)) {
                $starts++;

                if (preg_match('/\p{Ll}/u', $first)) {
                    $lowerStarts++;
                }
            }

            $upper += preg_match_all('/\p{Lu}/u', $text);
            $lower += preg_match_all('/\p{Ll}/u', $text);
        }

        $letters = $upper + $lower;

        if ($letters === 0) {
            return new Signal('no-capitalization', 'No capital letters', self::WEIGHTS['no-capitalization'], 0.0, 'No letters to read.');
        }

        $lowerShare = $starts === 0 ? 0.0 : $lowerStarts / $starts;
        $upperShare = $upper / $letters;

        $strength = max(
            self::ramp($lowerShare, 0.6, 0.95),
            $upperShare < 0.005 ? 1.0 : 0.0,
        ) * self::sample(count($texts));

        return new Signal(
            'no-capitalization',
            'No capital letters',
            self::WEIGHTS['no-capitalization'],
            $strength,
            sprintf('%d of %d cues (%d%%) start with a lowercase letter; %s%% of all letters are capitals.', $lowerStarts, $starts, round($lowerShare * 100), rtrim(rtrim(number_format($upperShare * 100, 1, '.', ''), '0'), '.')),
        );
    }

    /**
     * Fixed-window segmentation: every cue the same length, in time or in
     * characters, because a machine cut the stream every N seconds or every
     * N words. A person cuts at phrases, and phrases vary. Measured as the
     * coefficient of variation of each, combined as a geometric mean, so
     * that fast dialogue timed to a beat, uniform in time but not in
     * length, scores nothing: one of the two uniform is a style, both is a
     * machine.
     *
     * @param  array<int, Cue>  $cues
     */
    private function uniformity(array $cues): Signal
    {
        $n = count($cues);

        if ($n < $this->uniformityMinimumCues) {
            return new Signal('uniform-cue-lengths', 'Uniform cue lengths', self::WEIGHTS['uniform-cue-lengths'], 0.0, sprintf('Only %d cues; uniformity is not measured under %d.', $n, $this->uniformityMinimumCues));
        }

        $durations = array_map(fn (Cue $c) => (float) max(0, $c->durationMs()), $cues);
        $lengths = array_map(fn (Cue $c) => (float) QualityAnalyzer::characters($c), $cues);

        $durationCv = self::variation($durations);
        $lengthCv = self::variation($lengths);

        $strength = sqrt(self::ramp(-$durationCv, -0.35, -0.15) * self::ramp(-$lengthCv, -0.35, -0.15));

        return new Signal(
            'uniform-cue-lengths',
            'Uniform cue lengths',
            self::WEIGHTS['uniform-cue-lengths'],
            $strength,
            sprintf('Cue durations vary by %d%% and cue lengths by %d%% (coefficient of variation); under 15%% is fixed-window segmentation, over 35%% is not.', round($durationCv * 100), round($lengthCv * 100)),
        );
    }

    /**
     * Timestamps inside the cue text (`<00:00:19.039><c> no</c>`): a time for
     * every word. That is what a recogniser knows and a person does not
     * bother to write, outside karaoke, which is a style a music site will
     * recognise in the evidence. Any real share of cues fires it.
     *
     * @param  array<int, Cue>  $cues
     */
    private function wordTiming(array $cues): Signal
    {
        $n = count($cues);
        $timed = count(array_filter($cues, fn (Cue $c) => preg_match('/<(?:\d{2,}:)?\d{2}:\d{2}\.\d{3}>/', $c->text)));
        $share = $n === 0 ? 0.0 : $timed / $n;

        return new Signal(
            'word-level-timing',
            'Word-level timing',
            self::WEIGHTS['word-level-timing'],
            self::ramp($share, 0.05, 0.4),
            $n === 0 ? 'No cues to read.' : sprintf('%d of %d cues (%d%%) carry timestamps inside the text, timing individual words.', $timed, $n, round($share * 100)),
        );
    }

    /**
     * A cue that opens with the last word or two of a sentence ("love. You
     * know the rules") was cut at a width, not at the sentence. A person
     * starts the next cue where the sentence starts. Counted among cues that
     * contain any sentence end at all, so a file with no punctuation is
     * judged by the punctuation signal instead of this one, and only where
     * the cue before ran on without ending its sentence.
     *
     * @param  array<int, string>  $texts
     */
    private function tailStarts(array $texts): Signal
    {
        $withEnd = 0;
        $tails = 0;

        foreach ($texts as $i => $text) {
            if (! preg_match('/[.!?\x{2026}]/u', $text)) {
                continue;
            }

            $withEnd++;

            // Up to two words, then a sentence end, then more text, and the
            // cue before it ran on: it ended in a word or a comma, not in
            // punctuation or a closing bracket. "Right. More than half of
            // you." after a question is a short sentence, not a tail;
            // "love. You know the rules" after "no strangers to" is.
            if ($i > 0
                && preg_match('/[\p{L}\p{N},;:]$/u', $texts[$i - 1])
                && preg_match('/^\S+(?:\s+\S+)?[.!?\x{2026}]["\')\]]?\s+\S/u', $text)) {
                $tails++;
            }
        }

        $share = $withEnd === 0 ? 0.0 : $tails / $withEnd;

        return new Signal(
            'sentence-tail-starts',
            'Cues open mid-sentence',
            self::WEIGHTS['sentence-tail-starts'],
            self::ramp($share, 0.1, 0.4) * self::sample($withEnd),
            $withEnd === 0 ? 'No sentence ends to judge by.' : sprintf('%d of %d punctuated cues (%d%%) open with the tail end of the previous sentence.', $tails, $withEnd, round($share * 100)),
        );
    }

    /**
     * Each line shown twice, in consecutive cues, as a caption rolls up: the
     * shape of YouTube's automatic track, and of live broadcast captions
     * converted to a file. Neither has been edited for a page.
     *
     * @param  array<int, Cue>  $cues
     */
    private function rolling(array $cues): Signal
    {
        $n = count($cues);
        $repeats = 0;
        $previous = [];

        foreach ($cues as $cue) {
            $lines = array_values(array_filter(array_map('trim', $cue->lines()), fn ($l) => $l !== ''));

            if ($lines !== [] && $previous !== [] && array_intersect($lines, $previous) !== []) {
                $repeats++;
            }

            $previous = $lines;
        }

        $share = $n < 2 ? 0.0 : $repeats / ($n - 1);

        return new Signal(
            'rolling-duplicates',
            'Lines repeated across cues',
            self::WEIGHTS['rolling-duplicates'],
            self::ramp($share, 0.2, 0.6),
            $n < 2 ? 'Too few cues to compare.' : sprintf('%d of %d cues (%d%%) repeat a line from the cue before, as roll-up captions do.', $repeats, $n - 1, round($share * 100)),
        );
    }

    /**
     * Ten minutes or more with no [music], (laughter) or a note symbol
     * anywhere. Weak by nature: a single voice at a lectern has none to
     * annotate, and the weight says so.
     *
     * @param  array<int, Cue>  $cues
     * @param  array<int, string>  $texts
     */
    private function annotations(array $cues, array $texts, int $durationMs): Signal
    {
        $found = count(array_filter($texts, fn ($t) => preg_match('/\[[^\]]+\]|\([^)]+\)|[\x{266A}\x{266B}\x{266C}]/u', $t)));
        $minutes = round($durationMs / 60_000, 1);

        if ($durationMs < $this->annotationDurationMs) {
            return new Signal('no-non-speech-annotations', 'No non-speech annotations', self::WEIGHTS['no-non-speech-annotations'], 0.0, sprintf('%s minutes of captions; annotations are not expected under %d.', $minutes, intdiv($this->annotationDurationMs, 60_000)), true);
        }

        return new Signal(
            'no-non-speech-annotations',
            'No non-speech annotations',
            self::WEIGHTS['no-non-speech-annotations'],
            $found === 0 && $cues !== [] ? 1.0 : 0.0,
            $found === 0
                ? sprintf('No [sound], (sound) or music-note annotation in %s minutes of captions. Expected in most content of this length, but not in a single voice with no sound effects.', $minutes)
                : sprintf('%d cues carry a non-speech annotation.', $found),
            true,
        );
    }

    /**
     * No speaker labels of any kind (`<v>`, a leading dash, `NAME:`) in a
     * file that reads like a conversation. Whether it is one can only be
     * guessed at from the text, so the guess is a count of questions
     * followed by cues that begin like answers, three or more to fire, and
     * the weight is low whatever the count.
     *
     * @param  array<int, Cue>  $cues
     * @param  array<int, string>  $texts
     */
    private function speakers(array $cues, array $texts): Signal
    {
        $labelled = 0;

        foreach ($cues as $cue) {
            if ($cue->speakers() !== [] || preg_match('/^(?:-|\x{2013}|\x{2014}|>>)\s|^[A-Z][A-Z .\'-]{1,30}:/mu', $cue->plainText())) {
                $labelled++;
            }
        }

        if ($labelled > 0) {
            return new Signal('no-speaker-labels', 'No speaker labels', self::WEIGHTS['no-speaker-labels'], 0.0, sprintf('%d cues name or mark a speaker.', $labelled), true);
        }

        $pairs = 0;

        for ($i = 1; $i < count($texts); $i++) {
            if (! str_ends_with($texts[$i - 1], '?')) {
                continue;
            }

            $word = mb_strtolower((string) preg_replace('/^[^\p{L}]*(\p{L}+).*$/su', '$1', $texts[$i]));

            if (in_array($word, self::REPLIES, true)) {
                $pairs++;
            }
        }

        return new Signal(
            'no-speaker-labels',
            'No speaker labels',
            self::WEIGHTS['no-speaker-labels'],
            $pairs < 3 ? 0.0 : min(1.0, ($pairs - 2) / 4),
            $pairs === 0
                ? 'No speaker labels, and nothing that reads like one voice answering another.'
                : sprintf('No speaker labels, and %d question-and-reply pairs read like more than one voice. A single speaker asking and answering reads the same way.', $pairs),
            true,
        );
    }

    /** Full weight from a dozen cues; a smaller sample counts for less. */
    private static function sample(int $n): float
    {
        return min(1.0, $n / self::SMALL_SAMPLE);
    }

    /** 0 at or below `$from`, 1 at or above `$to`, straight line between. */
    private static function ramp(float $value, float $from, float $to): float
    {
        if ($value <= $from) {
            return 0.0;
        }

        if ($value >= $to) {
            return 1.0;
        }

        return ($value - $from) / ($to - $from);
    }

    /**
     * Standard deviation over the mean. Zero for a constant list, and zero
     * for a list whose mean is zero, because nothing can vary from nothing.
     *
     * @param  array<int, float>  $values
     */
    private static function variation(array $values): float
    {
        $n = count($values);
        $mean = array_sum($values) / $n;

        if ($mean <= 0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return sqrt($sum / $n) / $mean;
    }
}
