# Decision log

The **why**, and the **alternatives that were rejected**, behind choices that
are not obvious from the code they produced.

Newest first, headed `## YYYY-MM-DD: what was settled`, with a `---` between
entries. Entries are not edited when they stop being true; a reversal gets a
newer entry above it.

---

## 2026-10-23: Layout the editor needs goes in a style attribute

With no build step, a utility class in the editor only works if Statamic's
own stylesheet happens to contain it. `md:grid-cols-4` did not, so the four
cue fields stacked in one column at every width, and nothing failed: it
was found by reading the computed style while taking listing screenshots.
The cue grid is now `repeat(auto-fit, minmax(14rem, 1fr))` in a `style`
attribute: four columns on a laptop, two on a tablet, one on a phone with
no sideways scroll, checked at 1440, 1024 and 375 pixels.

Turned down: a responsive class Statamic does ship today (`md:grid-cols-2`
is there), because Statamic's stylesheet is purged to its own markup and
can drop it in any release; and an addon stylesheet, which is a second
asset to publish for one rule.

Still open: `md:grid-cols-[max-content_1fr]` on the "What Reel found" list
in `resources/views/utilities/asset.blade.php` has the same problem.

---

## 2026-09-15: The detector reads structure, because current recognisers punctuate

The brief lists the classic tells of raw ASR: no punctuation, no capitals,
fixed-window segmentation, no annotations, no speaker labels. Built and
tested, they scored a real YouTube automatic track, downloaded the same day,
at 0.09: "unlikely". YouTube's output is now punctuated and capitalised.

What it still does, and a person never does: a timestamp on every word,
each line rolled through two consecutive cues, and a cue that opens with the
tail of the previous sentence because the cut was at a width. Three signals
were added for those, with weights that let either generation of output
reach "likely" on its own, and the score capped at one.

Turned down: raising the weights of the classic signals, which would have
pushed the professionally captioned trailer with no punctuation (a style)
into "possible"; and a single "looks like YouTube" fingerprint, which would
be a boolean wearing a number.

Small samples are damped: under a dozen cues, the text signals count for
proportionally less, because seven unpunctuated cues in a trailer prove
nothing.

---

## 2026-09-15: Five real files, three of them not in the repository

The brief asks that the engine be tested against at least five real caption
files from different sources, one professional and one raw ASR. Done, on
2026-09-15, against:

| File | Source | Result |
| --- | --- | --- |
| YouTube automatic track for a music video | yt-dlp, `--write-auto-sub` | 103 cues, 0 errors, 127 findings (51 cues under 833 ms: the 10 ms "hold" cues of the rolling format), machine 0.66 likely |
| YouTube human track for a TED talk | yt-dlp, `--write-sub` | 128 cues, 0 errors, 20 findings, machine 0 |
| Plyr demo trailer captions | cdn.plyr.io | 7 cues, 0 errors, 3 findings, machine 0.15 unlikely (no punctuation, by style) |
| Sintel subtitles (Blender, CC BY) | MDN's video player example | 14 cues, 0 errors, 5 findings, machine 0 |
| video.js example captions (Apache 2.0) | video.js repository | 13 cues, 1 error (a real 39 ms overlap), 9 findings, machine 0 |

Every one round-trips: parsed, written, parsed again, semantically
identical. Byte-identical apart from a trailing newline.

Only the last two are redistributable and are in `tests/__fixtures__/real`.
The song lyrics and the talk transcript are somebody's copyright and stay
out; `tests/__fixtures__/asr-youtube-style.vtt` is a synthetic file in
YouTube's exact format, built from the lecture fixture's text, so the shape
is tested without the words.

---

## 2026-09-15: The gate is standalone

Accessibility Gate's checks are a hard-coded constant (`CheckPack::CHECKS`)
with no registration point, and its media check reads `data-a11y-*`
attributes a host stamps into rendered HTML, not asset metadata. Asked, and
decided: build Reel's gate standalone now, and extract a shared rules
contract when a third addon needs one. Reel uses the gate's
refuse-with-`ValidationException` pattern so an author sees one kind of
refusal, keyed by the field that holds the media so it appears under that
field.

Turned down: adding a registration point to the gate now, which would put
Reel behind a gate release for a contract with one consumer.

---

## 2026-09-15: What is stored on the asset, and what unset means

Asked, and decided, before any of it was written:

- The inline transcript is a Bard field. Headings and paragraphs come out
  as real HTML, which is what the transcript tag needs for its semantics.
  A textarea would have meant rendering paragraphs by hand.
- An unset audio-description flag is "not reviewed": `manual` on 1.2.3 and
  1.2.5, never `fail`, never `pass`, and never a reason to block. The
  alternative, unset counts as needed, would paint every video red on
  install and teach people to set the flag to make the red go away.
- 1.2.1 applies to audio only. Reel cannot tell a silent video from one with
  sound, and a flag for it was judged one field too many for a case the
  README can name.
- The gate requires a caption track for video and a transcript for audio.
  A video without a transcript does not block: 1.2.3 can be met by an audio
  description, which cannot be detected.

---

## 2026-09-15: Overlapping cues are an error outside a region

WebVTT allows two cues on screen at once; players stack them. The brief
calls an overlap outside a region a hard error, and it is right for the
purpose: in a caption file for a page, an overlap is almost always a timing
mistake, and the video.js example file had a real 39 ms one. Inside a
region the overlap is legitimate, because that is how roll-up captions are
written, but only if the region has room: a region with `lines:1` replaces
its cue rather than adding to it, and two cues in two different regions are
two boxes. The spec's default of three lines is used when `lines` is not
given.

---

## 2026-09-15: Netflix is the default standard

Three presets, and Netflix's is the default because it is the only one of
the three that states every figure outright: characters per line, reading
rate in characters per second, minimum and maximum duration, and the frame
gap rule. The BBC gives words per minute and no absolute durations; DCMP
gives words per minute by educational level and a minimum but no maximum.
Every derived or borrowed figure says so in the preset's comment. Sources
were fetched and read on 2026-09-15, not recalled.

Turned down: a fourth "house" preset with round numbers, which would be
numbers with no source.
