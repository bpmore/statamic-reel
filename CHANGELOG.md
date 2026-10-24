# Changelog

Newest first. Before 1.0, a breaking change raises the minor version.

## Unreleased

- The editor was used for real: `campus-tour.vtt` on greenhouse was fixed
  from the keyboard (the overlap on cue 3 and the comma on cue 4) and
  saved with every cue intact. It is off the to-do list, with the
  marketplace steps that are done.

## 0.1.2, 2026-10-24

- A cue whose timing cannot be read stays in the file. It used to be
  skipped, so the editor never showed it and the next save deleted it,
  text and all: an SRT comma on one timestamp was enough. It now shows in
  the editor as a cue, with its error on it, and is written back exactly
  as it was until someone fixes it. A block with no timing line at all is
  kept the same way.
- The editor refuses to save while a cue's timing cannot be read, and says
  which cue. Fix it or delete it, then save.
- Checking a cue while a timestamp is half typed no longer stops with "Could
  not check": the cue carries the error and the rest of the file is checked.

## 0.1.1, 2026-10-23

- The media table on the Reel page fits a phone. Status is now the second
  column, next to the asset, and only the table scrolls sideways when the
  other columns do not fit. At 375 pixels wide it used to run past the
  panel and push Status off the screen. The `reel:audit` example below it
  scrolls in its own box for the same reason.

## 0.1.0, 2026-10-23

The first release. Built 2026-09-15 against the brief in
`docs/build-reel.md`.

- A WebVTT parser, validator, serializer and quality analyzer in
  `src/Vtt`, with no Statamic in it. Malformed input returns errors with
  line numbers and never throws. A file parsed and written back is the
  same file.
- A machine-caption detector: eight named signals with evidence, a
  confidence and a label, never a boolean. Checked against a current
  YouTube automatic track, which is punctuated and capitalised and is
  caught on its structure instead.
- Per-asset assessment against WCAG 1.2.1 to 1.2.5, with `manual` for what
  the tool cannot know and never a silent pass.
- Reel fields on every video and audio asset: caption track override,
  transcript (Bard), transcript entry, audio-description flag.
- A publish gate on `EntrySaving`: block, warn or off, per collection,
  refusing with the reasons under the field that holds the media.
- A page under Tools with every media asset, a per-asset criteria view,
  and a keyboard-operable caption editor with live findings. Saving is the
  addon's one write path and needs its own permission. A cue's fields sit
  four across on a wide screen and one per row on a phone.
- `php please reel:audit`, with `--json` as clean stdout and exit codes 0,
  1 and 2.
- `{{ reel:tracks }}` and `{{ reel:transcript }}`.
- The listing screenshots, taken on greenhouse and kept outside the
  repository in `~/Herd/statamic-reel-screenshots`.
