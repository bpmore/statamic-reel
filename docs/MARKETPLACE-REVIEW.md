# Marketplace review, self-run

Written with Statamic's own review skill
(`https://statamic.com/downloads/marketplace-review/SKILL.md`, policy
revision 2026-09-14) against `main` at `4ba115a`, on 2026-09-17, in the
skill's creator-facing format. Run it again against the `0.1.0` tag before
submitting; the scope note at the end says what this pass could not check.

---

**Reel looks thoughtfully built.** From the source and a run on two local
Statamic 6.31 sites, it integrates through supported extension points only
(an `EntrySaving` listener, an `AssetContainerBlueprintFound` listener, a
utility, two tags, a command, one permission) and never touches core. The
brief's non-goal is the first sentence of the README: Reel does not
generate captions, and nothing in `src/` calls a service to do so. The
caption engine and the WCAG assessment are pure PHP with their own tests,
and every quality threshold cites the standard it came from
(`src/Vtt/QualityThresholds.php`).

There are three things to do before submitting.

**Make the support link reachable.** The README and `composer.json` name
GitHub Issues at `bpmore/statamic-reel`, and that repository is private, so
a customer following the link today gets a 404. The plan is to make it
public once testing is finished; do that before the listing goes live, and
open the link in a logged-out browser to confirm. This relates to
[Rule 09](https://statamic.com/marketplace/submission-guidelines#rule-09).

**Tag a release and install it into a fresh site.** Everything so far has
run from a path repository (a symlink). The Marketplace reviews the tagged
artifact, and `.gitattributes` now export-ignores `tests/`, `docs/`, CI and
`CLAUDE.md`, which has not yet been exercised by a real export. After
tagging `0.1.0`, `composer require bpmore/statamic-reel` into a fresh
`statamic/statamic` site, check that `vendor/bpmore/statamic-reel` has no
`tests/` directory, that the Reel page appears under Tools, and that
`php please reel:audit` runs. This relates to
[Rule 06](https://statamic.com/marketplace/submission-guidelines#rule-06)
and [Rule 08](https://statamic.com/marketplace/submission-guidelines#rule-08):
two files under `tests/__fixtures__/real` are other people's (CC BY 3.0
and Apache 2.0, attributed in `SOURCES.md`), and the export rule is what
keeps them out of the download.

**Walk the Tools page and the editor at phone width.** Keyboard operation
is verified: every control is a native button or input, findings move focus
with "Next finding", and a delete is two labelled presses. Small screens
are not verified. Open `/cp/utilities/reel` and one asset's page at about
375px wide and check the criteria table, the four-column cue grid
(`md:grid-cols-4` in `resources/js/reel-editor.js`) and the long-message
findings list. This relates to
[Rule 05](https://statamic.com/marketplace/submission-guidelines#rule-05).

Two smaller finishing checks. Take the listing screenshots from the
greenhouse site, where the seeded media shows every state (pass, review,
fail, draft refused), so the screenshots are the shipped product
([Rule 10](https://statamic.com/marketplace/submission-guidelines#rule-10)).
And read the caption editor once with a screen reader, which the brief's
own definition of done asks for and which no automated check replaces.

On security, the things I would want a reviewer to see: the utility's
routes sit behind Statamic's `access reel utility` permission
(`vendor/statamic/cms/src/CP/Utilities/UtilityRepository.php:79` and `:86`);
the one write path, `CaptionsController::store`, checks `edit reel
captions` on the server (`src/Http/Controllers/CaptionsController.php:72`)
and writes only to the asset the editor was opened for, through the
serializer; every string that reaches a Vue-compiled view goes through
`@plain` (`src/ServiceProvider.php:58`), and cue text never reaches a
template at all, it is fetched as JSON. Both tags escape what they print.
There are no external requests, no credentials, no licence check, and no
edition bypass.

**Overall, this looks in good shape.** I reviewed the source at `4ba115a`
and ran the addon on two local Statamic 6.31 sites installed by path, where
I checked the Tools page, the editor by keyboard (including a save to
disk), the gate's refusal in the control panel, and the front-end tags on
a rendered page. I did not install a tagged release into a fresh site,
did not test at phone width, and did not use a screen reader. The live
policy was read on 2026-09-17 and matches the bundled skill's revision.
