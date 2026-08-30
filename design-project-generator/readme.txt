=== Design Project Generator ===
Contributors: designforge
Tags: design, generator, portfolio, education, graphic design
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic graphic design briefs, practise against timed challenges, build interactive mini demos and export your work. No paid APIs.

== Description ==

**DesignForge — Generate. Design. Practice. Build your portfolio.**

Design Project Generator turns WordPress into a design practice platform. It generates client-style briefs from a local database, gives the designer real constraints and a timed challenge, and exports the finished brief as PDF, PNG or TXT.

It is not a random idea generator. The loop it is built around is:

`Generate -> read brief -> challenge -> save -> export -> generate another`

= Everything runs locally =

The core plugin makes no external requests and needs no API key. There is no OpenAI, Gemini, Claude, stock-image or icon service anywhere in it. Briefs are assembled in PHP from bundled JSON. Exports, the timer, the colour tools and the demo editor all run in the visitor's browser.

= What it generates =

Briefs are built from parts rather than picked from a list of pre-written paragraphs, which is what produces the very large number of distinct combinations. The bundled database ships with:

* 8 categories and 60 project types
* 24 industries, each with its own client names, products, audiences, situations, objectives and competitors
* 16 design styles, 20 colour palettes and 12 typography pairings
* 24 challenges, 30 restrictions and 26 direction hints

Every brief carries a category, project type, difficulty, client, background, objective, audience, style, colour and typography direction, required content, deliverables, restrictions, success criteria and a challenge. Advanced and expert briefs add brand personality and competitor context; expert briefs add a budget and a deadline.

= Difficulty changes the brief, not just a label =

Difficulty drives how many deliverables and restrictions appear, how much of the required content is listed, how long the work is estimated to take, which kind of challenge is chosen and whether the full client-simulation fields are present.

= Reproducible briefs =

Every generated brief has a seed. The same seed always rebuilds the same brief, which is what makes three features possible:

* **Share project link** copies a URL that rebuilds the exact brief for somebody else.
* **Daily challenge** gives every visitor the same brief for the whole day, derived from the date.
* **Assignments** can be regenerated from their stored seed.

= Challenges and the timer =

Each brief comes with a challenge: a time limit, a colour restriction, a typographic restriction, a layout constraint, a minimalism exercise or a concept exercise. The timer offers 10, 30 and 60 minutes, two hours, or a custom length, and counts against the clock rather than accumulating ticks, so a backgrounded tab still shows the right number.

= Mini demos =

Nine project types come with an interactive HTML, CSS and JavaScript demo the visitor can customise in the browser: landing page, portfolio, dashboard, restaurant menu, social card, product page, pricing page and coming-soon page.

Demos run inside an iframe sandboxed to `allow-scripts` only, which puts them on an opaque origin. Demo code cannot read cookies, storage or the DOM of the WordPress page around it, and none of it is ever evaluated in the page itself. Editor values reach the demo as data over `postMessage` and are written with `textContent` and CSS custom properties, never as markup.

= Export =

* **PDF** is written directly rather than screenshotted, so the text stays selectable, searchable and sharp at any print size. A4, A3 and Letter are supported and the layout paginates automatically.
* **PNG** captures the whole brief, not just the visible viewport. Very tall briefs are scaled to fit the browser's canvas limits rather than failing silently.
* **TXT** is a clean plain-text version of the brief.

= Saving =

Logged-out visitors save to their browser's `localStorage`; nothing reaches the server. Logged-in users get real WordPress posts, so their work survives a change of browser. Saved projects are stored by seed and rebuilt on demand rather than stored as markup.

= Teacher mode =

Users who can edit posts get an assignment builder: choose a category, difficulty and a number of students, and the plugin generates a different brief for each one, trying to give every student a distinct project type and industry. Assignments are saved to the account and can be exported as a text file.

= Accessibility =

Semantic markup, labelled controls, visible focus rings, keyboard-operable menus, focus-trapped dialogs, a polite live region for status messages, and full support for `prefers-reduced-motion`. Generated palettes are nudged until the text colour clears WCAG AA against its own background.

== Installation ==

1. Upload the `design-project-generator` folder to `/wp-content/plugins/`, or install the ZIP through **Plugins > Add New**.
2. Activate the plugin.
3. Add the **Design Project Generator** block to a page, or use the shortcode `[design_project_generator]`.

== Shortcode ==

`[design_project_generator]`

Attributes:

* `category` — lock to one category, for example `branding`
* `type` — lock to one project type, for example `logo-design`
* `industry`, `style` — lock to one industry or style
* `difficulty` — `beginner`, `intermediate`, `advanced` or `expert`
* `demo_only` — `yes` to only generate project types that have a demo
* `daily` — `yes` to show today's challenge instead of a random brief
* `teacher` — `yes` to show the assignment builder to users who can edit posts
* `prerender` — `no` to start with an empty card instead of a brief
* `show_filters`, `show_timer`, `show_demo`, `show_export`, `show_save`, `show_palette`, `show_hints`, `show_case` — `no` to hide that feature
* `heading`, `tagline` — override the wording above the generator

Examples:

`[design_project_generator category="branding" difficulty="beginner"]`
`[design_project_generator daily="yes" show_filters="no"]`
`[design_project_generator teacher="yes"]`

== Frequently Asked Questions ==

= Does this need an API key or a paid service? =

No. The core plugin makes no external requests at all. Everything is generated locally from bundled JSON, and all three export formats are produced in the browser.

= Which third-party libraries are bundled? =

None. PDF and PNG export, the timer, the colour tools and the demo editor are all written for this plugin. The icons were drawn for it and are GPL-licensed like the rest of the code. There is no third-party licence to track and nothing is loaded from a CDN.

= Why is the PDF text rather than a screenshot of the page? =

Because the PDF is meant to be printed and read. Real text stays sharp at any size, can be searched and selected, and produces a file a few kilobytes in size instead of a few megabytes.

= Can visitors save projects without an account? =

Yes. Guest saves use `localStorage` and never reach the server. This can be switched off under **Design Projects > Settings**.

= Can I add my own industries, styles, palettes or project types? =

Yes, under **Design Projects**. Site-authored records are stored separately from the bundled JSON files, so a plugin update can never overwrite them. Any bundled record can also be switched off without being deleted.

= I use a full-page caching plugin and the first brief repeats. =

The first brief is rendered server side so the page is useful immediately and without JavaScript, which means a cached page holds that brief until the visitor presses Generate. Use `prerender="no"` if you would rather the generator start empty on cached pages.

= Does the completion score judge my design? =

No, and it does not claim to. It tracks how much of the brief you have worked through — required content, call to action, palette, deliverables, export. Nothing in the plugin assesses artistic quality.

== Screenshots ==

1. The generator with a brief, colour palette, timer and hints.
2. A full expert brief with client simulation fields.
3. The mini demo editor with live preview.
4. Teacher mode assignment builder.
5. The admin dashboard.

== Changelog ==

= 1.0.0 =
* First release.
* Randomisation engine with 8 categories, 60 project types, 24 industries, 16 styles, 20 palettes and 24 challenges.
* Four difficulty levels that change the shape of the brief.
* Seeded generation: shareable project links and a deterministic daily challenge.
* Challenge timer with presets and a custom length.
* Colour palette tool with per-colour locking, contrast-checked randomisation and hex copying.
* Eight sandboxed mini demo templates with a live editor.
* PDF, PNG and TXT export, all client side and dependency free.
* Guest saving to localStorage, logged-in saving to a custom post type.
* Portfolio case study mode and a completion checklist.
* Teacher mode assignment generator.
* Shortcode, Gutenberg block and a full admin data manager.

== Upgrade Notice ==

= 1.0.0 =
First release.
