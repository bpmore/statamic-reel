# Had A Farm Statamic Addon: Build Conventions

Every build brief in this set references this file. Read it first. Where a brief disagrees with this file, follow the brief and say so.

---

## 1. Working agreement

Repos live at `~/Herd/statamic-<name>`. Package `bpmore/statamic-<name>`, namespace `Bpmore\<Name>`.

**Before writing anything, read `bpmore/statamic-a11y-report`.** It is the conventions reference for composer metadata, service provider layout, config publishing, license handling, and README shape. If this document or a brief disagrees with what that repo actually does, follow the repo and flag the difference.

Look at what is already in the target repo before assuming it is empty. If it is scaffolded, work with what exists rather than starting over.

Develop against a local Statamic site using a Composer path repository so edits take effect without reinstalling:

```json
"repositories": [
    { "type": "path", "url": "../statamic-<name>" }
]
```

Then `composer require bpmore/statamic-<name>:@dev` from the site. **Confirm the symlink landed rather than a copy.** A copy means your edits do nothing and you will lose an hour finding out.

## 2. Targets

- Statamic 6, with Statamic 5 compatibility where it is free. Verify the current support window before setting composer constraints rather than trusting either of us on it.
- PHP 8.2 or later.
- Pest for tests.
- Proprietary license, matching the `statamic-a11y-docs` and `statamic-a11y-report` model, unless the brief says the addon is free.

## 3. Architecture rule: engine and wrapper

**Separate the logic from the Statamic integration.** Every addon in this set has a pure PHP core that could run outside Statamic, and a thin addon layer that wires it into fieldtypes, tags, commands, and the control panel.

```
src/
  <Domain>/          # pure PHP. No Statamic facades, no Illuminate container
  Fieldtypes/
  Tags/
  Commands/
  Http/
  ServiceProvider.php
```

The test for whether you got this right: the core namespace should be testable without booting Statamic. If a core class calls `Entry::query()`, it is in the wrong namespace. Pass data in, get results out.

This is not academic. `readability-core` already works this way and the same engines need to run inside ComplyField and eiSEO later.

## 4. Command conventions

Every addon that audits anything ships an Artisan command following the Fallow pattern:

- `php please <name>:audit` with readable human output
- `--json` flag producing **clean stdout**: no banners, no progress output, no stray newlines. CI consumes this
- Exit codes: `0` clean, `1` findings at or above the configured threshold, `2` the command itself failed
- A `--threshold` or equivalent so teams can adopt incrementally

## 5. Accessibility of what you build

Non-negotiable, and the reason is commercial rather than moral: these are sold by an accessibility vendor and will be audited by buyers who audit for a living.

- Any control panel UI meets WCAG 2.1 AA. Keyboard operable, visible focus, correct labels and roles, no color-only meaning
- Any front-end output meets WCAG 2.1 AA and works with `prefers-reduced-motion` and `forced-colors: active`
- Any front-end output works without JavaScript, or degrades to something correct. Static caching is the norm on these sites
- Never use `aria-label` on a non-interactive element and call it done. Use real text

If you are uncertain whether a pattern is accessible, stop and ask rather than guessing. A confident wrong ARIA pattern is worse than no ARIA.

## 6. Writing rules for anything the addon emits or documents

**No em dashes.** Not in the README, not in config comments, not in CP copy, not in generated output. Use a colon for an introduction, a comma or parentheses for an aside, or split into two sentences.

Write plainly. No "seamlessly," no "powerful," no "simply."

## 7. Guardrails

**Do not hardcode fixture answers.** If a test expects a specific count and the implementation returns a hardcoded value that satisfies it, the test is worthless. The discriminating test cases named in each brief exist specifically to catch this, and they are the ones to write first.

**Do not invent requirements.** If the brief does not specify a behavior and you need one, stop and ask. Do not silently pick.

**Do not add dependencies** beyond what the brief names without asking. Every dependency is a support obligation.

**Read-only means read-only.** Where a brief says an addon audits rather than edits, it must have no write path at all, not a write path that is currently unused.

## 8. Build order

1. Read the reference repo. Read the brief in full.
2. Write the discriminating test cases from the brief's test section, as failing tests.
3. Build the pure PHP core until those pass.
4. Add the Statamic integration layer.
5. Add the control panel UI last.
6. README, config comments, and docs.

## 9. Stop and ask

Stop and ask the human rather than deciding, when:

- The brief is ambiguous on a behavior that affects stored data
- An accessibility pattern is genuinely contested
- A dependency would be needed that the brief does not name
- A Statamic API behaves differently from what the brief assumes
- The work would require writing to content, config, or assets in a way the brief did not authorize
- Something in the brief appears factually wrong about Statamic

Asking costs a message. Guessing costs a rewrite.

## 10. Definition of done

- Pest suite green, including every discriminating case from the brief
- Verified manually against real content, not only fixtures
- `--json` output validated by piping to `jq` where applicable
- Installed alongside the other `bpmore/*` addons without CP asset, permission, config-key, or nav-slug collisions
- Keyboard walkthrough of every CP surface, with a screen reader on at least one
- README covers install, config, prerequisites, what the addon does not do, and support expectations
- No em dashes anywhere in the repo
