# Button Block Icon

Puts an icon beside the label on `core/button`, chosen from a collection
registered with the WordPress Icons API or uploaded as a one-off SVG.

Requires WordPress 7.1 (for `wp_register_icon_collection()` and friends) and
PHP 8.3. On anything older the plugin registers nothing and says so in the
admin.

## What it adds

An **Icon** panel on every `core/button`, offering:

- **Choose icon** — a searchable grid of the offered collections.
- **Upload SVG** — an attachment, for the one-off that does not justify a
  deploy.
- **Size** — 16, 24 or 32px by default.
- **Position** — before or after the label.
- **Hide label on mobile** — clips the label below 782px by default, leaving the
  icon. The button keeps its accessible name, and the breakpoint is filterable.

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
| `hm-has-button-icon--hide-label` | wrapper | Label clipped on mobile |
| `hm-has-button-icon--themed` / `--custom` | wrapper, editor only | Which source the canvas preview is drawing |
| `hm-button-icon` | the `<svg>` | Every icon |
| `hm-button-icon--themed` | the `<svg>` | Registered icon, recoloured to the button's text colour |
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

The viewport width below which **Hide label on mobile** clips the label.
Defaults to `782`, the width core treats as the top of mobile.

```php
add_filter( 'hm_button_icon_mobile_breakpoint', fn (): int => 600 );
```

The rule this produces travels with the block stylesheet, so a page with no
button on it still downloads neither.

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

All six serialise into the block comment and are registered server side from
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
| `hmHideLabelOnMobile` | boolean | `false` |

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
overrides `installer-paths`. The site needs PHP 8.3 or later, and WordPress 7.1
or later for the Icons API.

Composer does not build the editor assets. A checkout installed this way still
needs the build below, either run in place or run in CI and shipped with the
deploy. Without it the plugin loads and renders no icon, since every enqueue is
guarded on the built file being there.

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
