<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Caption quality standard
    |---------------------------------------------------------------------------
    |
    | Which published captioning standard the quality findings are measured
    | against. Reel ships three presets and every figure has a source in
    | `Bpmore\Reel\Vtt\QualityThresholds`:
    |
    |   netflix  42 characters per line, 20 per second, 5/6 s to 7 s per cue.
    |            Netflix Timed Text Style Guide. The default, because it is
    |            the only one of the three that states every number outright.
    |   bbc      37 characters per line (teletext), about 17 per second
    |            (derived from the BBC's 160 to 180 words per minute).
    |            BBC Subtitle Guidelines.
    |   dcmp     32 characters per line, about 15 per second (derived from
    |            160 words per minute), at least 1 s 10 frames per cue.
    |            DCMP Captioning Key, written for educational media.
    |
    | Any figure can be overridden below. A null means "the preset's figure".
    | Every quality finding names the standard and the limit, so an editor
    | can argue with the standard rather than with the tool.
    |
    */

    'quality' => [
        'standard' => env('REEL_STANDARD', 'netflix'),
        'characters_per_line' => null,
        'lines_per_cue' => null,
        'characters_per_second' => null,
        'minimum_duration_ms' => null,
        'maximum_duration_ms' => null,
        // A gap this long or shorter is a deliberate frame gap (2 frames at
        // 24 fps) and is not reported.
        'frame_gap_ms' => null,
        // A gap longer than the frame gap and shorter than this flickers.
        'flicker_gap_ms' => null,
    ],

    /*
    |---------------------------------------------------------------------------
    | Machine caption detection
    |---------------------------------------------------------------------------
    |
    | Reel does not generate captions. It does try to tell when the captions
    | it is given are raw speech-recognition output nobody has corrected,
    | because uncorrected automatic captions do not meet WCAG 1.2.2. The
    | answer is a confidence with the signals named, never a bare yes.
    |
    | `annotation_minutes` is how long a file must be before the absence of
    | any [music] or (laughter) annotation counts for anything. A short clip
    | of one voice has nothing to annotate, and so does a long lecture, which
    | is why this signal carries little weight whatever the length.
    |
    */

    'machine' => [
        'annotation_minutes' => 10,
    ],

    /*
    |---------------------------------------------------------------------------
    | Caption tracks
    |---------------------------------------------------------------------------
    |
    | Where Reel looks for a video's captions when the asset's own Reel field
    | does not name one. `sidecars` are filename patterns in the same folder,
    | with `{name}` standing for the video's filename without its extension.
    | `talk.mp4` is matched by `talk.vtt` and by `talk.en.vtt`; the language
    | code between the dots becomes the track's `srclang`.
    |
    | `language` is the `srclang` used for a sidecar with no code in its
    | name, and the fallback label language for the tracks tag.
    |
    */

    'captions' => [
        'sidecars' => ['{name}.vtt', '{name}.*.vtt'],
        'language' => 'en',
    ],

    /*
    |---------------------------------------------------------------------------
    | Publish gate
    |---------------------------------------------------------------------------
    |
    | What happens when an entry that uses a video with no caption track, or
    | an audio file with no transcript, is saved.
    |
    |   block  the save is refused, and the reasons are shown on the field
    |          that holds the media.
    |   warn   the save goes through and the reasons are written to the log.
    |   off    nothing.
    |
    | `mode` is the default; `collections` overrides it by collection handle.
    | The gate only ever blocks on what a file can prove: a missing track, a
    | track a player cannot read, a missing transcript. It never blocks on
    | the machine-caption score, which is a likelihood, or on the audio
    | description, which nothing can detect.
    |
    */

    'gate' => [
        'mode' => env('REEL_GATE', 'block'),
        'collections' => [
            // 'blog' => 'warn',
            // 'docs' => 'off',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Audit
    |---------------------------------------------------------------------------
    |
    | `php please reel:audit` exits 1 when any asset's verdict is at or above
    | this threshold, so a team can start by failing the build on `fail`
    | alone and tighten to `warn` once the backlog is clear. `manual` fails
    | on anything a person has not yet reviewed.
    |
    */

    'audit' => [
        'threshold' => env('REEL_AUDIT_THRESHOLD', 'fail'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Asset fields
    |---------------------------------------------------------------------------
    |
    | Reel adds a "Captions and transcript" tab to the edit screen of every
    | video and audio asset: the caption track override, the transcript, a
    | linked transcript entry, and the audio-description flag. Set this to
    | false to add the fields to your asset blueprints yourself instead; the
    | handles are in the README.
    |
    */

    'add_fields_to_assets' => true,

];
