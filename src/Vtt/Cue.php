<?php

declare(strict_types=1);

namespace Bpmore\Reel\Vtt;

/**
 * One cue: when it shows, what it says, and how it is placed.
 *
 * `$text` is the payload as written, markup and all. `plainText()` is what a
 * viewer reads, and the quality rules count that, because `<v Dr. Smith>` is
 * not read aloud and is not on screen.
 */
final class Cue
{
    public function __construct(
        public readonly Timestamp $start,
        public readonly Timestamp $end,
        public readonly string $text = '',
        public readonly ?string $id = null,
        public readonly CueSettings $settings = new CueSettings,
        /** The line the timings are on, 1-based, or 0 when the cue was not read from a file. */
        public readonly int $line = 0,
    ) {}

    public function durationMs(): int
    {
        return $this->end->milliseconds - $this->start->milliseconds;
    }

    /**
     * The text with markup removed and entities decoded: what is on screen.
     * Tags are stripped rather than parsed, because a tag the spec does not
     * know still shows nothing, and the parser has no reason to be stricter
     * than a player.
     */
    public function plainText(): string
    {
        $text = (string) preg_replace('/<[^>]*>/', '', $this->text);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<int, string> the on-screen lines */
    public function lines(): array
    {
        return explode("\n", $this->plainText());
    }

    /**
     * Who is speaking, from `<v Name>` spans, in order and without repeats.
     * The classes on a voice span (`<v.loud Name>`) are styling, not part of
     * the name.
     *
     * @return array<int, string>
     */
    public function speakers(): array
    {
        preg_match_all('/<v(?:\.[^\s>]*)?[ \t]+([^>]*)>/', $this->text, $m);

        $names = [];

        foreach ($m[1] as $name) {
            $name = trim($name);

            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function region(): ?string
    {
        return $this->settings->region();
    }

    public function overlaps(self $other): bool
    {
        return $this->start->milliseconds < $other->end->milliseconds
            && $other->start->milliseconds < $this->end->milliseconds;
    }

    /**
     * @return array{id: ?string, start: string, end: string, settings: array<string, string>, text: string, line: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'start' => $this->start->format(),
            'end' => $this->end->format(),
            'settings' => $this->settings->values,
            'text' => $this->text,
            'line' => $this->line,
        ];
    }

    /**
     * The editor's representation back into a cue. A timestamp that will not
     * parse is refused here with a message naming it, because this is input
     * from a form rather than a file, and the form has to say which field.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $start = Timestamp::parse((string) ($data['start'] ?? ''));
        $end = Timestamp::parse((string) ($data['end'] ?? ''));

        if ($start === null) {
            throw new \InvalidArgumentException('The cue start "'.($data['start'] ?? '').'" is not a timestamp.');
        }

        if ($end === null) {
            throw new \InvalidArgumentException('The cue end "'.($data['end'] ?? '').'" is not a timestamp.');
        }

        $settings = $data['settings'] ?? [];
        $settings = is_string($settings) ? CueSettings::parse($settings) : new CueSettings(array_map('strval', (array) $settings));

        $id = $data['id'] ?? null;

        return new self(
            $start,
            $end,
            (string) ($data['text'] ?? ''),
            $id === null || $id === '' ? null : (string) $id,
            $settings,
            (int) ($data['line'] ?? 0),
        );
    }
}
