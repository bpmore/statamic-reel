# To do

What is left after the first build, ranked. Tick an item off by deleting it
and saying so in the changelog. Dated 2026-09-15.

## Now

1. **Screen reader pass.** VoiceOver on, open the Reel page on greenhouse
   (`http://greenhouse.test/cp/utilities/reel`), tab through one cue in the
   editor, then read `/media/plain-language-lecture` on the front end. The
   last item on the brief's definition of done (`docs/build-reel.md`,
   section 6). Record what was heard in `docs/DECISIONS.md`.

## Later

2. **Marketplace submission**, once item 1 is done. The repository is
   public, Packagist has the releases, the phone-width problems are fixed,
   and the review was run again against `0.1.0`. What the listing needs:
   - Version `0.1.2`, the newest tag.
   - Price $69, the brief's target.
   - The README's first paragraph as the copy: Reel does not generate
     captions.
   - Categories Assets (primary), Utility, CLI.
   - The screenshots in `~/Herd/statamic-reel-screenshots`, taken on
     greenhouse from the shipped code (Rule 10).
3. **A "captions reviewed by a person" flag.** A track scored "likely
   machine" stays at Review forever, even after someone confirms it was
   corrected. The brief did not ask for one; ask before adding it, since it
   is stored data.
4. **One real site.** Five real caption files went through the engine
   (`docs/DECISIONS.md`); none came from a customer. Run `reel:audit` on a
   site with real media and read every finding.
5. **Statamic 5.** Not supported (`^6.27`): the control panel UI is
   different. Decide whether that stays so, and say it in the README if it
   does.
