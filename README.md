# Design Project Generator (DesignForge)

A WordPress plugin that generates realistic graphic design briefs, runs timed
challenges against them, offers interactive HTML/CSS/JS mini demos, and exports
the result as PDF, PNG or TXT.

**Generate → read brief → challenge → save → export → generate another.**

The plugin lives in [`design-project-generator/`](design-project-generator/).
Copy that folder into `wp-content/plugins/` and activate it, or zip it and
upload it through **Plugins → Add New**.

Then add the **Design Project Generator** block to a page, or use:

```
[design_project_generator]
```

## What is in the box

| | |
|---|---|
| Categories / project types | 8 / 60 |
| Industries | 24, each with its own clients, products, audiences, situations, objectives and competitors |
| Styles / palettes / type pairings | 16 / 20 / 12 |
| Challenges / restrictions / hints | 24 / 30 / 26 |
| Demo templates | 8, used by 23 project types |
| Distinct brief combinations | ~1.8 million before the per-brief randomisation of deliverables, restrictions and challenges |

## Try it without WordPress

`demo/index.html` is a self-contained build of the real interface — open it in
a browser and it works: generating, filters, the regeneration modes, the timer,
the palette tools, the sandboxed mini demos, and PDF / PNG / TXT export that
actually download.

It is not a mock-up. The markup, the stylesheet and the front-end JavaScript
are the plugin's shipped files, unmodified; only the network layer is replaced.
The PHP generator and brief renderer are ported to JavaScript in `demo/src/`,
and `demo/build.js` refuses to produce a demo unless that port agrees with the
PHP on every field and every byte of rendered markup across 349 briefs:

```
node demo/build.js
```

## Architecture

```
design-project-generator/
├── design-project-generator.php   Plugin header, constants, activation
├── includes/
│   ├── class-plugin.php           Bootstrap and asset registration
│   ├── class-security.php         Sanitising, validation, capabilities
│   ├── class-project-database.php Local JSON + site customisations
│   ├── class-project-generator.php The randomisation engine
│   ├── class-post-types.php       Saved projects and assignments
│   ├── class-rest-api.php         REST routes
│   ├── class-shortcode.php        Shortcode and the one brief template
│   ├── class-block.php            Gutenberg block (server rendered)
│   ├── class-admin.php            Admin screens
│   └── class-export.php           Plain-text export and downloads
├── data/                          8 JSON files: the project database
├── templates/                     8 demo templates (HTML + CSS + JS)
├── assets/css, assets/js, assets/icons
├── languages/
├── uninstall.php
└── readme.txt                     WordPress.org readme
```

### Decisions worth knowing about

**One renderer.** The brief markup is produced by PHP and nowhere else. The
REST endpoint returns the rendered HTML *alongside* the data, so the browser
never builds markup out of project values. There is one template to keep
correct and one place where output is escaped.

**Seeded generation.** Every brief has a seed, and the engine draws from its
PRNG the same number of times whether or not a filter is pinned. That alignment
is what lets a seed plus the resolved selections rebuild a brief byte for byte,
which is what makes shareable project links, the deterministic daily challenge
and reproducible assignments work. There is a test for it.

**Difficulty is structural.** It drives the number of deliverables and
restrictions, how much required content is listed, the estimated time, which
family of challenge is chosen, and whether the client-simulation fields
(personality, competitors, budget, deadline) appear at all.

**Customisation survives updates.** Bundled records are never edited in place.
Site owners disable records by id and add their own to a separate option, so a
plugin update cannot overwrite their work.

**Demos are properly isolated.** Demo templates run in an iframe sandboxed to
`allow-scripts` only, which puts them on an opaque origin: no cookies, no
storage, no access to the WordPress DOM around them. Editor values reach the
frame as data over `postMessage` and are written with `textContent` and CSS
custom properties. No demo code is ever evaluated in the page.

## Where this differs from the specification

The specification suggested some third-party dependencies. I did not bundle
any, and the reasons are worth stating rather than hiding:

- **jsPDF and html2canvas were not used.** Export is written from scratch
  instead. PNG serialises the card into an SVG `foreignObject` and paints it to
  a canvas; PDF is generated directly using the standard PDF fonts. The PDF
  therefore contains real selectable, searchable text that stays sharp at any
  print size, and comes out at a few kilobytes rather than a few megabytes.
  The plugin also ships with zero third-party code and makes no CDN request.
  Worth knowing: the sandbox this was built in had no network access to those
  CDNs, so bundling them was not an option here either — but the resulting
  design is the one I would argue for regardless.
- **Icons are drawn for this plugin** rather than taken from Lucide or Tabler,
  so there is no third-party icon licence or attribution to track. They are
  GPL-licensed like the rest of the code.
- **No font files are bundled.** Typography directions name system and common
  open stacks, which sidesteps font licensing entirely.
- **`#project-export-area` is per-instance.** The spec named a single element
  id; because several generators can appear on one page, each gets
  `id="dpg-N-export-area"` and a shared `.dpg-export-area` class.
- **The PDF names the demo rather than embedding a picture of it.** A demo runs
  in a cross-origin sandboxed iframe, which by design cannot be rasterised from
  the parent page. Capturing it would mean giving the demo same-origin access,
  which is the one thing the sandbox exists to prevent.

Scope delivered: the full MVP (spec §44), the whole of version 1.5 (§45) — mini
demos, demo editor, colour generator, typography direction, portfolio case
study, project history and completion score — plus the daily challenge, teacher
mode and the assignment generator from version 2.0 (§46).

**Not built** (the rest of §46): gamification, streaks, achievements, the
student-facing portfolio dashboard, and advanced project analytics.

## Development notes

There is no build step. What ships is what runs: plain PHP, vanilla JavaScript
and hand-written CSS. The Gutenberg block is authored without JSX for the same
reason.

Verified during development: PRNG uniformity and seed reproducibility across
300 replays, PDF structure (xref offsets and stream lengths checked against the
produced bytes), escaping (hostile data pushed through generation, validation,
rendering and the save round trip, then parsed back to confirm no injected
elements or event handlers survive), REST permission and ownership enforcement,
path-traversal guards on the demo endpoint, and admin nonce, capability and
import handling.

The demo build doubles as an integration test, and driving it in a real browser
caught bugs that unit-level testing had not. The worst: `DPG.use()` accepted
only a function while every module registers an object, so the challenge timer,
all three export buttons and the Open demo button were silently dead. It also
turned up "a insurance plan" in generated copy and a CSS padding collision that
pushed the artboard past the viewport edge.

## Licence

GPL-2.0-or-later.
