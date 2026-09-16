# CLAUDE.md

Non-negotiables for any model working in this repository. Rules, not
documentation. Obey them without asking for confirmation.

Read `docs/build-conventions.md` and `docs/build-reel.md` first: the brief
this addon was built to. Then `docs/DECISIONS.md` before choosing an
approach.

## THE PRODUCT

- **Reel does not generate captions.** Nothing here calls a speech
  recogniser, a translation API, or any service. It reads caption files a
  site already has and governs them.
- Uncorrected machine captions fail WCAG 1.2.2. That sentence is the
  commercial argument, and the detector that backs it up is the part of
  the product most easily got wrong in either direction. A false positive
  teaches editors to ignore the tool; a false negative lets through the
  thing it exists to catch. The score is a confidence with named signals
  and evidence, never a boolean, and a `likely` score is `manual` on
  1.2.2, never `fail`.
- A criterion the tool cannot decide is `manual`. An unset
  audio-description flag is "not reviewed". Neither is ever promoted to a
  pass, by default, by config, or by omission.
- The gate blocks on what a file can prove and on nothing else. Not the
  machine score, not the audio description.
- Every quality threshold has a source comment. A number with no source is
  a number an editor can refuse to be held to.

## ARCHITECTURE

- `src/Vtt` and `src/Compliance` are pure PHP: no Statamic facades, no
  container, no config(). Values in, values out. Their tests boot nothing,
  and that is a test in itself. If a class there needs an asset or an
  entry, it is in the wrong namespace.
- Everything else hands them values and reads what comes back.
- The one write path is `CaptionsController::store`, which writes one
  WebVTT file to the asset it was opened for, through the serializer,
  behind the `edit reel captions` permission. Do not add another.
- Parsing never throws. A file with one bad line has hundreds of good
  ones, and the editor has to open it to fix it.
- The editor holds the document as the parser's array form and never as
  text. The serializer, not the browser, produces the file.
- Control-panel UI comes from Statamic's own component library. Native
  `fieldset`, `legend`, `ol` and `details` for structure. Never hand-roll a
  widget inside an accessibility product, and never use `aria-label` on a
  non-interactive element.
- A utility view is compiled as a Vue template. Every string that could
  hold a brace goes through `@plain`. Cue text never goes into a template
  at all.

## STACK

- PHP 8.2 or newer. Statamic 6 (`^6.27`). Pest. No npm, no build step: the
  editor is a plain script using the Vue build Statamic ships.
- No new dependencies without asking. Every one is a support obligation.
- No licence check, ever. Statamic resolves the licence and shows its
  banner.

## TESTING

- The discriminating cases in `docs/build-reel.md` section 5 come first and
  stay green. The professional single-speaker fixture with capitals for
  shouting is the one that keeps the detector honest.
- Mutation-test a check before trusting it: break the thing it guards and
  confirm the test fails.
- A file another tool wrote is worth more than a fixture written to pass.
  `tests/__fixtures__/real` holds the redistributable ones; the decision
  log records the rest.
- Verify against the real site in `~/Herd/statamic-dev` (installed by
  path, symlinked) before calling something done. Republish the script
  after editing it: `php please vendor:publish --tag=statamic-reel --force`.

## WRITING

- No em dashes anywhere: not in code comments, commit messages, docs, or
  user-facing copy. Use colons, commas, parentheses, or two sentences.
- Plain words. No "seamlessly", no "powerful", no "simply".
- Comments say why, and name the failure that motivated the rule.

## PROCESS

- Branch, work, run the tests, open a PR. Never push to main directly.
- Never push or merge unless asked.
- Add a decision-log entry when a change settles a question, including
  the alternatives turned down.
