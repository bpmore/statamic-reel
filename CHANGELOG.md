# Changelog

Newest first. Before 1.0, a breaking change raises the minor version.

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
