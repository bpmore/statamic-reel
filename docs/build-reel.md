# Reel: Build Brief

Package `bpmore/statamic-reel`, namespace `Bpmore\Reel`, repo `~/Herd/statamic-reel`.
Read `build-conventions.md` first. Paid addon, target $69.

---

## 1. What it is

Caption and transcript **governance** for video and audio assets. Not a caption generator.

**Market check, verified September 2026:** two partial competitors, both on the wrong side of the problem. SkynetAccessibility Video Subtitle (free) and Video Tools ($49) both generate captions with AI. Neither verifies, edits, governs, or blocks. Uncorrected machine captions fail WCAG 1.2.2, and that fact is the entire commercial argument for this addon.

**Say the non-goal in the README's first paragraph: Reel does not generate captions.** Pair it with whatever generator the buyer already uses.

## 2. Architecture

`src/Vtt/` is a pure PHP WebVTT parser, validator, and quality analyzer. It is the most testable part of the addon and the part with the most defensible value. It must have no Statamic dependencies and its own exhaustive test suite.

Everything else wraps it: asset metadata, a CP panel, a publish gate, an audit command, and a transcript tag.

## 3. The VTT engine

**Parser.** Full WebVTT: cue identifiers, timestamps with and without hours, cue settings (`line`, `position`, `align`, `size`, `vertical`), `NOTE` blocks, `STYLE` blocks, `REGION` blocks, voice spans `<v Speaker>`, class spans, and timestamp spans for karaoke timing. Malformed input returns structured errors with line numbers, never an exception.

**Validator.** Hard failures:
- Timestamps out of order or a cue whose end precedes its start
- Overlapping cues where overlap is not permitted by region settings
- Malformed timestamp format
- Missing the `WEBVTT` header

**Quality analyzer.** Soft findings, each configurable with a documented default. These are the values a captioning professional would use, and each one needs a source comment in the config:
- Characters per line above roughly 32 to 42, depending on the configured standard
- More than two lines per cue
- Reading rate above roughly 17 to 21 characters per second
- Cue duration below roughly 1 second or above roughly 7 seconds
- Gap between cues below a threshold, causing flicker

**Machine caption detector.** The distinguishing feature. Heuristics that together indicate uncorrected ASR output:
- No terminal punctuation across a high proportion of cues
- No capitalization at cue starts, or uniform lowercase throughout
- No speaker labels in content with multiple speakers (detectable only weakly, treat as low confidence)
- No non-speech annotations (`[music]`, `[laughter]`) anywhere in a file over a duration threshold
- Suspiciously uniform cue lengths, indicating fixed-window segmentation rather than semantic segmentation

Report a **confidence score with the contributing signals named**, never a bare boolean. A stylistic choice must not be reported as a defect with certainty. Getting this wrong in either direction is the main product risk: false positives make editors distrust the tool, false negatives defeat its purpose.

## 4. Statamic integration

**Asset metadata.** For video and audio assets, track:
- Caption track: a sidecar VTT asset, discovered by filename convention and overridable by an explicit field
- Transcript: a text or bard field, or a linked entry
- Audio description: a flag for "needed," "provided," or "not applicable," since this cannot be detected automatically
- Per-asset status against WCAG 1.2.1, 1.2.2, 1.2.3, 1.2.4, and 1.2.5, with 1.2.4 (live) always manual

**Publish gate.** An entry referencing a video or audio asset that lacks a caption track or transcript fails validation on save. Integrate with `statamic-a11y-gate` through its rules interface if one exists; if it does not, implement standalone and stop to ask whether extracting a shared rules contract is in scope.

Configurable strictness: `block`, `warn`, `off`, per collection.

**CP panel.** Per-asset compliance view and a VTT editor: cue list, timing adjustment, text editing, live quality findings. Keyboard operable throughout, which for a timed-media editor means explicit attention rather than default behavior.

**Command.** `php please reel:audit` following the conventions file command rules, reporting every media asset, its status, and its findings.

**Tags.** `{{ reel:transcript }}` rendering a transcript with correct semantics, and `{{ reel:tracks }}` emitting `<track>` elements for a native player. **Reel does not ship a player.**

## 5. Discriminating tests

1. **Overlapping cues.** Two cues with overlapping ranges and no region settings produce a hard error. The same overlap with valid region settings does not.
2. **Reading rate.** A cue of known character count and known duration produces the expected characters-per-second value, hand-computed in the test, not derived from the implementation.
3. **Machine caption, true positive.** A realistic uncorrected ASR file scores high confidence with the correct signals listed.
4. **Machine caption, true negative.** A professionally captioned file with deliberate stylistic all-caps for shouting, and no non-speech annotations because the content is a single speaker at a lectern, scores low. **This is the case that stops the heuristic from being a blunt instrument, and it is the one to write first.**
5. **Malformed input.** A VTT missing its header, and one with a corrupt timestamp on line 7, both return structured errors naming line numbers rather than throwing.
6. **Voice spans.** `<v Dr. Smith>` parses to a speaker attribution and survives a round trip through the editor unchanged.
7. **Gate behavior.** An entry with a video and no captions fails save in `block` mode, warns in `warn` mode, and saves silently in `off` mode.
8. **Round trip fidelity.** Parse then re-serialize a complex VTT with regions, styles, and notes: output is semantically identical to input. Editors will lose work otherwise.

## 6. Definition of done

Conventions file section 10, plus:

- VTT engine tested against at least five real caption files from different sources, including one professionally produced and one raw ASR
- The transcript tag's output verified with a screen reader
- CP VTT editor verified keyboard-only, with no mouse
- README states plainly that Reel does not generate captions

## 7. Explicit non-goals

No caption generation, no speech recognition, no translation, no media player, no video hosting, no encoding, no burned-in subtitles.
