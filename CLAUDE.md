# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress plugin that adds an icon to `core/button`, from a collection registered
with the Icons API (WP 7.1+) or an uploaded SVG. `README.md` documents the public
contract: classes, filters, attributes, markup. Read it before changing anything
user-facing, and keep it in step.

## Commands

```
composer install            # PHPCS plus the HM standard, wired by the phpcodesniffer installer
npm ci && npm run build     # build/ is gitignored, so a fresh clone needs this
npm run start               # watch
npm run lint                # lint:js and lint:css in parallel
npm run lint:js:fix / lint:css:fix
composer lint               # PHPCS, HM standard
composer format             # PHPCBF
```

The package is not on public Packagist. `README.md` documents the VCS
`repositories` entry a consuming site needs, and the fact that Composer installs
no built assets.

There are no automated tests, no test runner and no `wp-env` setup. CI
(`.github/workflows/ci.yml`) runs `lint:js`, `lint:css`, `build`, then
`composer lint`. Do not invent a test command.

Every enqueue in `inc/assets.php` is guarded on `is_readable`, so an unbuilt
checkout renders no icon and raises no error. Check `build/` exists before
debugging a missing icon.

## Architecture

**Nothing is written into the block's saved markup.** `core/button` renders from a
fixed `save`, so the icon is added on `render_block_core/button` in `inc/render.php`,
keyed off attributes in the block comment. Serialising anything into saved content
would raise a block validation error on the next core update. New features follow the
same route.

**`inc/attributes.php::definitions()` is the only attribute registration.** It is
filtered onto the block server side through `block_type_metadata`, and printed to the
editor as `window.hmButtonIcon` by `inc/assets.php`. The `ATTRIBUTES` / `SIZES` /
`COLLECTIONS` fallbacks at the top of `src/index.js` are stale-cache insurance, not a
second registration. Add or change an attribute in `definitions()` and nowhere else.

**Front end and editor canvas draw the icon by different mechanisms, of necessity.**
The front end gets real inline `<svg>` from `inc/render.php`. The canvas cannot: core
renders the label as the `wp-block-button__link` element itself with
`contenteditable`, so anything injected inside it becomes editable text. The
`editor.BlockListBlock` filter in `src/index.js` instead sets `--hm-button-icon` and
`--hm-button-icon-size` on the wrapper, and `src/editor.scss` paints a `::before`
(mask for a registered icon so it takes `currentcolor`, background image for an
upload). Do not "fix" the preview by inserting an SVG.

**`inc/assets.php` enqueues the editor stylesheet twice on purpose.** The inspector
lives in the admin page, the canvas in the editor iframe, and
`enqueue_block_editor_assets` never fires inside the iframe. The header comment in
that file explains it. Do not dedupe.

**The hide-label rule is printed from PHP, not compiled.** Its breakpoint is
filterable through `hm_button_icon_mobile_breakpoint` and a media query takes no
custom property, so `hide_label_css()` in `inc/assets.php` builds it and
`wp_add_inline_style()` attaches it to the block stylesheet. `src/style.scss`
holds a pointer where the rule used to be. Anything else that needs a filtered
value inside a media query goes the same way.

**The render filter must stay idempotent.** Caches and theme filters can re-enter it,
hence the `hm-button-icon__label` guard near the top of `render()`.

**Single-mechanism choices a cleanup would undo:** icon side is the order
`render()` writes the two children in, not a flex direction. The layout rule in
`src/style.scss` is wrapped in `:where()` so it carries no specificity and any theme
rule wins.

**Uploaded SVGs are inlined unsanitised, on purpose.** `uploaded_icon_markup()`
checks the mime type and writes the file into the page, which makes an
unsanitised upload more dangerous than it is behind an `<img>`. The tradeoff,
its preconditions and the ways to close it are in `SECURITY.md`. Read that
before changing anything in that function, and keep it in step.

**The two icon sources are mutually exclusive, enforced in JS** by the `NO_ICON`
spread on every setter. That is why PHP has no precedence logic.

**WP 7.1 gate:** `bootstrap()` in `button-block-icon.php` returns early when
`wp_get_icon()` is missing, leaving only an admin notice. Hook new subsystems in
after that gate, not at file scope.

## Conventions

Namespaced functions, no classes. `declare( strict_types=1 )` in every PHP file.
The `hm` / `hm-` prefix on attributes and classes is the house convention and is
deliberately not derived from the plugin slug. `phpcs.xml.dist` carries the sniff
exclusions the HM standard needs here; add to it rather than sprinkling
`phpcs:ignore`.
