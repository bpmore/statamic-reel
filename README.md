# Reel for Statamic

Caption and transcript governance for the video and audio on a Statamic
site. **Reel does not generate captions.** It finds the caption file you
already have, reads it the way a player and a reader will, says what is
wrong with it, lets an editor fix it, and refuses to publish an entry whose
media has no captions or transcript at all. Pair it with whatever caption
generator you already use; the point of Reel is what happens after that
tool has had its turn, because uncorrected automatic captions do not meet
WCAG 1.2.2.

Reel is a paid addon for Statamic 6 on PHP 8.2 or newer.

## What it does

For every video and audio asset in your containers, Reel reports where it
stands on WCAG 2.1 success criteria 1.2.1 to 1.2.5:

| Criterion | Video | Audio |
| --- | --- | --- |
| 1.2.1 Audio-only and Video-only | Not applicable. Reel assumes a video has sound. | Needs a transcript. |
| 1.2.2 Captions (Prerecorded) | Needs a caption track a player can read. | Not applicable. |
| 1.2.3 Audio Description or Media Alternative | Met by a transcript, or by an audio description. | Not applicable. |
| 1.2.4 Captions (Live) | Always a person's answer. | Always a person's answer. |
| 1.2.5 Audio Description (Prerecorded) | The audio-description flag, set by a person. | Not applicable. |

A criterion Reel cannot decide is reported as **Review**, never as a pass.
An audio-description flag nobody has set is "not reviewed", not "needed"
and not "provided".

The caption file itself gets three kinds of attention:

- **Hard errors**, which a player will not show correctly: a missing
  `WEBVTT` header, a timestamp that will not parse, cues out of order, a cue
  that ends before it starts, and two cues on screen at once outside a
  region with room to stack them. Every error names its line.
- **Quality findings** against a named captioning standard: line length,
  lines per cue, reading rate in characters per second, cues too short or
  too long, and gaps between cues short enough to flicker. Every finding
  names the standard and the limit, so the argument can be with the
  standard rather than with the tool.
- **A machine-caption score**: how likely the file is to be raw
  speech-recognition output nobody has corrected. A confidence with every
  signal listed and the evidence each one saw, never a bare yes or no.

## Install

```
composer require bpmore/statamic-reel
```

Nothing else. The config file is optional:

```
php please vendor:publish --tag=statamic-reel-config
```

Reel adds a **Captions** tab to the edit screen of every video and audio
asset, a **Reel** page under Tools, a `reel:audit` command, and two tags.

## Caption tracks

Reel looks for a video's captions in two places, in this order:

1. The **Caption track** field on the asset, if somebody chose a file there.
2. A WebVTT file **beside the video with the same name**: `talk.vtt` or
   `talk.en.vtt` for `talk.mp4`, in the same folder. The code between the
   dots is the track's language. Several languages side by side give
   several tracks.

A chosen file that has since been deleted is reported as missing rather
than quietly replaced by a sidecar, because somebody chose it.

Only WebVTT is read. An SRT file is not a caption track to a browser, and
Reel will tell you its comma is not a full stop rather than guess.

## The asset fields

On every video and audio asset, under **Captions**:

| Handle | Field | What it is for |
| --- | --- | --- |
| `reel_captions` | Assets, one file | A caption track to use instead of the sidecar. Video only. |
| `reel_transcript` | Bard | The transcript, as text. Headings for sections and a paragraph per speaker turn read well. |
| `reel_transcript_entry` | Entries, one | Or an entry that is the transcript, if it lives on its own page. |
| `reel_audio_description` | Select | `needed`, `provided` or `not_applicable`. Nothing can detect this; a person answers. Video only. |

Set `add_fields_to_assets` to `false` in the config file to place the fields
in your asset blueprints yourself, with the same handles.

## The publish gate

Saving an entry that uses a video with no caption track, or a video whose
track a player cannot read, or an audio file with no transcript, is
refused, and the reasons appear under the field that holds the media.
Three modes, overall and per collection:

```php
'gate' => [
    'mode' => 'block',
    'collections' => [
        'blog' => 'warn',
        'drafts' => 'off',
    ],
],
```

`block` refuses. `warn` saves and writes the reasons to the log. `off` does
nothing. An unrecognised mode is `block`: a typo must never be the reason a
gate stopped gating.

The gate blocks only on what a file can prove. It never blocks on the
machine-caption score, which is a likelihood, or on the audio description,
which nothing can detect. Both are reported everywhere else.

The gate is standalone. It does not plug into Accessibility Gate's checks,
which have no registration point; it uses the same refuse-with-reasons
pattern so an author sees one kind of refusal.

## The control panel

**Tools, then Reel** lists every media asset: its caption track, whether it
has a transcript, the audio-description answer, and a verdict. Open an
asset for the five criteria with the reason for each, and the caption
editor.

The editor is a list of cues. Each has its identifier, start, end, settings
and text, with buttons that move a timestamp by 100 milliseconds, and its
findings listed beneath it. Findings update as you type. "Next finding"
moves focus to the next cue with a problem. Deleting a cue takes two
presses, the second labelled with the cue's number.

The whole editor works from the keyboard: every control is a real button or
input in document order, nothing is hover-only, and nothing moves. Saving
writes the WebVTT file back into the asset container through the same
serializer the parser round-trips through, so notes, regions and styles you
never touched are written back as they were. Saving needs the **Edit
caption files** permission.

## Tags

Reel ships no player. Use your own `<video>` or `<audio>` element.

```antlers
<video controls src="{{ video:url }}">
    {{ reel:tracks :asset="video" }}
</video>

{{ reel:transcript :asset="video" }}
```

`reel:tracks` emits one `<track>` per caption track, with `srclang` and a
`label` in the language's own name, the first marked `default`. Parameters:
`kind` (`captions` by default; use `subtitles` for a track with no sound
description), `default="false"` to mark none.

`reel:transcript` emits a `<section>` labelled by its own heading, with the
transcript inside, or a link to the transcript entry. Parameters: `heading`
(level, 2 by default), `title` (`Transcript` by default), `class`. It emits
nothing at all when there is no transcript; it never fakes one.

Both accept an asset object, an asset id (`assets::media/talk.mp4`) or a
path, and both work with no JavaScript.

## The command

```
php please reel:audit
php please reel:audit --json | jq '.summary'
php please reel:audit --container=media --threshold=warn
```

Every media asset, its verdict, its criteria, and its caption file's errors,
findings and machine score. `--json` is the whole of stdout and nothing else.
Exit codes: `0` nothing at or above the threshold, `1` something is, `2` the
command could not run. The threshold is `fail` by default and can be `warn`,
`manual` or `pass`, from the config file or the option, so a team can start
by failing the build on failures alone and tighten later.

## Standards and thresholds

The quality findings are measured against one of three presets, `netflix`
by default, each with a source in `Bpmore\Reel\Vtt\QualityThresholds`:

| | Netflix | BBC | DCMP |
| --- | --- | --- | --- |
| Characters per line | 42 | 37 | 32 |
| Lines per cue | 2 | 2 | 2 |
| Characters per second | 20 | about 17 | about 15 |
| Shortest cue | 0.833 s | 0.833 s | 1.333 s |
| Longest cue | 7 s | 7 s | 7 s |

Where a standard gives words per minute rather than characters per second,
or gives no figure at all, the config comments say what was derived and what
was borrowed. Any figure can be overridden in the config file.

A gap between cues of 2 frames or less (83 ms) is a deliberate frame gap. A
gap longer than that and shorter than half a second flickers and is
reported.

## Machine caption detection

Eight signals, each a ramp rather than a switch, weighted by how rarely a
person produces it:

- **No sentence punctuation** and **no capital letters**: what recognisers
  used to leave out. All capitals is not a signal; no recogniser shouts.
- **Uniform cue lengths**: every cue the same duration and the same length,
  because a machine cut at a fixed window. Fast dialogue timed to a beat is
  uniform in one but not the other, and scores nothing.
- **Word-level timing**, **lines repeated across cues** and **cues that open
  mid-sentence**: what current recognisers leave in. YouTube's automatic
  track, checked September 2026, is punctuated and capitalised and gives
  itself away by timing every word and rolling each line through two cues.
- **No non-speech annotations** in ten minutes or more, and **no speaker
  labels** in what reads like a conversation: weak by nature and weighted
  so that a single voice at a lectern, professionally captioned, cannot
  reach "possible" on them alone.

The score is `unlikely` under 0.3, `possible` under 0.6, `likely` from 0.6.
A likely score makes 1.2.2 **Review**, not a failure, and every signal is
listed with what it saw so an editor can say "that was on purpose" and move
on.

What it cannot see: a recogniser whose output has been lightly corrected,
punctuated, capitalised and re-segmented at sentences looks like a person's
work to these signals. Reel narrows the problem; it does not close it.

## What Reel does not do

- Generate captions, recognise speech, or translate.
- Play media, host it, encode it, or burn subtitles in.
- Read SRT, TTML or SCC. WebVTT only, because that is what a browser plays.
- Tell a silent video from one with sound, or whether an audio description
  exists. Both are a person's answers, and Reel says so rather than
  guessing.
- Certify anything. A pass here is "nothing Reel can detect is wrong", and
  1.2.4 is always a person's answer.

## Support

Reel is sold on the Statamic Marketplace, one licence per production site,
with local development and CI free under Statamic's own terms.

Bugs and questions go to
[GitHub Issues](https://github.com/bpmore/statamic-reel/issues). A caption
file the parser gets wrong is a bug: attach the file, or the shape of it,
and it becomes a fixture. Issues are answered by the author; there is no
service-level agreement.

`ext-intl` is optional. With it, each `<track>` is labelled in its own
language ("Français"); without it, the label is the language code.

## Developing

Pest, with the VTT engine tested without booting Statamic:

```
composer test
```

The addon's script is published into `public/vendor/statamic-reel/js` when
it is installed. After editing `resources/js/reel-editor.js` on a site that
installs the addon by path, run `php please vendor:publish --tag=statamic-reel --force`
to see the change.
