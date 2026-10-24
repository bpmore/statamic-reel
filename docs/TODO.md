# To do

What is left after the first build, ranked. Tick an item off by deleting it
and saying so in the changelog. Dated 2026-09-15.

## Now

1. **Screen reader pass.** VoiceOver on, open the Reel page on greenhouse
   (`http://greenhouse.test/cp/utilities/reel`), tab through one cue in the
   editor, then read `/media/plain-language-lecture` on the front end. The
   last item on the brief's definition of done (`docs/build-reel.md`,
   section 6). Record what was heard in `docs/DECISIONS.md`.
2. **Use the editor for real.** Fix `campus-tour.vtt` on greenhouse from
   the keyboard: the overlap on cue 3 and the comma on cue 4. Anything that
   feels wrong under the fingers is a bug.

## Later

3. **Marketplace submission.** In this order, once items 1 and 2 are done
   and the addon has been tested to your satisfaction:
   - Make `bpmore/statamic-reel` public. The README and composer.json
     already name GitHub Issues as the support channel, which is a dead
     link until then (Rule 09).
   - Check the Tools page and editor at phone width (Rule 05). Keyboard is
     verified; small screens are not.
   - Tag `0.1.0`. The Marketplace reviews the tagged release (Rule 06).
   - Run Statamic's review skill once more against the tag:
     `curl -sL https://statamic.com/downloads/marketplace-review/SKILL.md`.
     The pass against `4ba115a` is in `docs/MARKETPLACE-REVIEW.md`; compare.
   The brief's target price is $69. The listing copy is the README's
   first paragraph: Reel does not generate captions.
4. **A "captions reviewed by a person" flag.** A track scored "likely
   machine" stays at Review forever, even after someone confirms it was
   corrected. The brief did not ask for one; ask before adding it, since it
   is stored data.
5. **One real site.** Five real caption files went through the engine
   (`docs/DECISIONS.md`); none came from a customer. Run `reel:audit` on a
   site with real media and read every finding.
6. **Statamic 5.** Not supported (`^6.27`): the control panel UI is
   different. Decide whether that stays so, and say it in the README if it
   does.
