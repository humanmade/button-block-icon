# Button Block Icon

Puts an icon beside the label on `core/button`, chosen from a collection
registered with the WordPress Icons API or uploaded as a one-off SVG.

Requires WordPress 7.1 (for `wp_register_icon_collection()` and friends) and
PHP 8.2. On anything older the plugin registers nothing and says so in the
admin.

## What it adds

An **Icon** panel on every `core/button`, offering:

- **Choose icon** — a searchable grid of the offered collections.
- **Upload SVG** — an attachment, for the one-off that does not justify a
  deploy.
- **Size** — 16, 24 or 32px by default.
- **Color** — a registered icon only. It defaults to following the button's
  own text colour through hover, active and disabled; picking one overrides
  that. An uploaded SVG keeps whatever colour it was drawn with, so it has no
  color control.
- **Position** — before or after the label.
- **Label** — visible (the default), hidden below 782px by default (the
  breakpoint is filterable), or always hidden. A hidden label stays available
  to screen readers; only the icon is left on screen.

The two sources are mutually exclusive: choosing one clears the other.

## How it renders

`core/button` renders from a fixed `save`, so nothing is written into post
content — the icon is added on `render_block_core/button`, from attributes that
live in the block comment. That means no block validation errors when core
changes the button's markup, and removing the plugin leaves clean content
behind.

Markup, for a left-positioned registered icon:

```html
<div class="wp-block-button hm-has-button-icon">
  <a class="wp-block-button__link">
    <svg class="hm-button-icon hm-button-icon--themed hm-button-icon--arrow-forward" …>…</svg>
    <span class="hm-button-icon__label">Read more</span>
  </a>
</div>
```

### Classes

| Class | Where | Meaning |
| --- | --- | --- |
| `hm-has-button-icon` | wrapper | The button carries an icon |
| `hm-has-button-icon--right` | wrapper | Icon after the label |
| `hm-has-button-icon--hide-label` | wrapper | Label clipped below the mobile breakpoint |
| `hm-has-button-icon--hide-label-always` | wrapper | Label always clipped |
| `hm-has-button-icon--themed` / `--custom` | wrapper, editor only | Which source the canvas preview is drawing |
| `hm-button-icon` | the `<svg>` | Every icon |
| `hm-button-icon--themed` | the `<svg>` | Registered icon, recoloured to the button's text colour, or to its own **Color** override |
| `hm-button-icon--<slug>` | the `<svg>` | The icon's own slug, so a theme can single one out |
| `hm-button-icon__label` | `<span>` | The label, wrapped |

The per-slug class is the hook for treatments that apply to one icon and not the
rest — an arrow that travels on hover, say, where a bookmark sliding sideways
would read as a glitch.

### Styling

The plugin's stylesheet is deliberately thin: it stops the icon squashing and
recolours a registered one to `currentcolor`. The layout rule that puts the icon
and label in a row is written in `:where()`, so it holds no specificity and a
theme's own button rules always win.

A **Color** override does not add a class or a rule; it is a `style="color: …"`
on the `<svg>` itself, which is what `currentcolor` then resolves to instead of
the button's own text colour.

The rule that clips a hidden label is not in the stylesheet. Its breakpoint is
filterable and a media query takes no custom property, so it is printed from
`inc/assets.php` and attached to the same handle.

It is enqueued through `wp_enqueue_block_style()`, so a page with no button on
it never downloads it.

## Filters

### `hm_button_icon_collections`

Which collections the picker offers. Empty (the default) means every collection
registered with the Icons API, core's own included. A theme shipping a design
system will usually want to name its own, so editors stay inside the curated
set:

```php
add_filter( 'hm_button_icon_collections', fn (): array => [ 'my-theme' ] );
```

This scopes the picker only. An icon already chosen keeps rendering if the list
later changes, rather than vanishing from published content.

### `hm_button_icon_sizes`

The sizes offered, in pixels. Defaults to `[ 16, 24, 32 ]`.

```php
add_filter( 'hm_button_icon_sizes', fn (): array => [ 20, 28 ] );
```

A button set to a size no longer on the list falls back to 24, or to the first
size offered.

### `hm_button_icon_mobile_breakpoint`

The viewport width below which a button's label set to **Hide below 782px**
clips. Defaults to `782`, the width core treats as the top of mobile. The
option's name in the editor follows the filtered value. Has no effect on a
label set to **Always hide**, which carries no breakpoint.

```php
add_filter( 'hm_button_icon_mobile_breakpoint', fn (): int => 600 );
```

The rule this produces travels with the block stylesheet, so a page with no
button on it still downloads neither.

## Preparing icons for a collection

This plugin does not register icons; it renders whatever is in the registry. But
what the registry accepts is narrow enough to be worth writing down here, since
it is the plugin's domain rather than any one theme's.

`WP_Icons_Registry::sanitize_icon_content()` runs `wp_kses` allowing only:

| Element   | Attributes                                                                         |
| --------- | ---------------------------------------------------------------------------------- |
| `svg`     | `class`, `xmlns`, `width`, `height`, `viewbox`, `aria-hidden`, `role`, `focusable`   |
| `path`    | `fill`, `fill-rule`, `d`, `transform`                                                |
| `polygon` | `fill`, `fill-rule`, `points`, `transform`, `focusable`                              |

Everything else is dropped, and the failures are quiet rather than loud. A
stroke-only icon loses `stroke` and `stroke-width` but keeps a structurally valid
`<path>`, so it registers cleanly and draws nothing. Art that loses the scale
putting it on the collection's grid keeps every path it had and renders cropped.

So artwork wants converting to fill-only geometry on one grid before it is
registered, and it wants optimising with a config that knows about the above.
Four SVGO preset defaults have to be off:

```js
// svgo.config.mjs
export default {
	multipass: true,
	floatPrecision: 3,
	plugins: [
		{
			name: 'preset-default',
			params: {
				overrides: {
					// Without a viewBox an icon draws at its raw coordinates
					// wherever it is parsed as XML rather than as HTML.
					removeViewBox: false,

					// Both can introduce a <g> to hold shared attributes, and
					// <g> is stripped along with everything on it.
					moveElemsAttrsToGroup: false,
					moveGroupAttrsToElems: false,

					// `fill="currentColor"` looks redundant to SVGO on a shape
					// with no stroke. It is what makes an icon follow the
					// button's text colour through `hm-button-icon--themed`.
					removeUselessStrokeAndFill: false,
				},
			},
		},
	],
};
```

The invocation belongs wherever the SVG sources live, which is the theme or
plugin registering the collection, not here. This plugin ships no SVGs of its
own, and a Composer-installed plugin cannot contribute an npm script to the
project consuming it.

## Uploaded SVGs

An uploaded icon is not put through the Icons API allowlist — it keeps its
strokes, groups and gradients and renders as authored. That is the point of the
escape hatch, and it also means it does **not** follow the button's colour; an
upload meant to should be drawn with `fill="currentColor"`.

The plugin does not sanitise uploads, and it inlines the file rather than
referencing it, so anything inside the SVG runs as part of the page where the
same file behind an `<img>` would not. A site allowing SVG uploads at all should
be running something that sanitises them on upload, such as `safe-svg`.
[SECURITY.md](SECURITY.md) sets out the precondition and the ways to close it.

## Attributes

All seven serialise into the block comment and are registered server side from
`inc/attributes.php`, which is also where the editor script gets its
definitions, so the two registrations cannot drift.

They keep the short `hm` prefix rather than deriving from the plugin slug, which
is the Human Made house convention and keeps the generated class names legible.

| Attribute | Type | Default |
| --- | --- | --- |
| `hmIconName` | string | `''` |
| `hmIconId` | number | `0` |
| `hmIconUrl` | string | `''` |
| `hmIconPosition` | string | `'left'` |
| `hmIconSize` | number | `24` |
| `hmLabelVisibility` | string, one of `visible` / `mobile` / `hidden` | `'visible'` |
| `hmHideLabelOnMobile` | boolean, deprecated | `false` |

`hmHideLabelOnMobile` predates `hmLabelVisibility` and is never written by the
editor any more. It is still read on the front end: a button saved before
1.1.0 carries only this flag, and `true` here with no `hmLabelVisibility` in
the comment resolves to `'mobile'`, so it keeps rendering exactly as it did
before the enum existed.

## Install

The package is not on public Packagist, so point Composer at the repository
first:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/humanmade/button-block-icon"
        }
    ]
}
```

Then:

```
composer require humanmade/button-block-icon
```

It is typed `wordpress-plugin` and requires `composer/installers`, so it lands
in `wp-content/plugins/button-block-icon` unless the root `composer.json`
overrides `installer-paths`. The site needs PHP 8.2 or later, and WordPress 7.1
or later for the Icons API.

A tagged version carries its own built assets: the release workflow commits
`build/` into the tag before it is created, so `composer require` on a version
constraint gives you a plugin that works as installed. A `dev-main` install
does not, since `build/` is not committed on `main`. That one needs the build
below, either run in place or run in CI and shipped with the deploy; without it
the plugin loads and renders no icon, since every enqueue is guarded on the
built file being there.

Or clone it into `wp-content/plugins/` and run the build below.

## Development install

```
composer install
npm ci
```

`composer install` brings in `humanmade/coding-standards`, which registers the
HM standard that `composer lint` runs, and
`dealerdirect/phpcodesniffer-composer-installer`, which is what wires that
standard into PHPCS. Both are already allowed in `config.allow-plugins`, so the
install needs no prompt answered.

## Build

```
npm ci
npm run build     # or `npm run start` to watch
```

Output lands in `build/`, which is not committed. `npm run lint` covers JS and
CSS; `composer lint` runs PHPCS against the Human Made standard.

## Releases

Releases are cut by the **Release** workflow
(`.github/workflows/release.yml`), run by hand from the Actions tab with the
version to ship, written `1.2.3` with no leading `v`.

It builds the assets, writes that version over the `__VERSION__` placeholder in
`button-block-icon.php`, commits the built assets and the stamped file, and
tags *that* commit `v1.2.3`. Only the tag is pushed, so `main` stays where it
was. It then attaches `button-block-icon-1.2.3.zip` to the release.

Two things follow from building before tagging rather than after. A tag is
already built and already versioned, so it installs as it stands, whether
through Composer or as the zip. And the tag is written once and never moved,
which is what Packagist requires — the workflow refuses a version whose tag
already exists, so a bad release is superseded by the next patch version rather
than rewritten.

The version on `main` is always the literal `__VERSION__`, in both the plugin
header and the `VERSION` constant. The real number exists only inside a tag,
which is what keeps the two from ever disagreeing.

The zip is `git archive` of the tag, so `.gitattributes` is the single place
that decides what ships. `build/` and `src/` are both in it; CI config, the
PHPCS config and `composer.lock` are not. To change what a release carries,
edit `.gitattributes` — the workflow needs no change.

`.github/workflows/build-and-release.yml` separately keeps a `release` branch
in step with `main` plus a built `build/`, on every push to `main`. It is there
for installing the latest built code from a branch. It is not part of cutting a
release, and it carries the `__VERSION__` placeholder, so it is not a versioned
artifact.
