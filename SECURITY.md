# Security

## Reporting a vulnerability

Email <engineering@humanmade.com> with the details and a way to reproduce.
Please do not open a public issue for anything exploitable.

## Uploaded SVGs are inlined as authored

This is the one part of the plugin with a real security tradeoff, and it is
deliberate.

`hmIconId` names an attachment. At render time, `uploaded_icon_markup()` in
`inc/render.php` checks the mime type is `image/svg+xml`, reads the file off
disk, and writes its contents into the page as inline SVG. The file is not
sanitised and is not put through the Icons API allowlist, because keeping
strokes, groups and gradients is the whole point of the upload escape hatch.

Inline SVG is part of the document. A `<script>` or an `onload` inside the file
runs, where the same file referenced through `<img src="...">` would not. So the
plugin makes an unsanitised SVG in the media library more dangerous than it was
before the plugin was installed, rather than equally dangerous.

Two things have to be true for that to be exploitable:

1. The site accepts SVG uploads without sanitising them. Core rejects SVG
   uploads outright, so this means a plugin, a theme or a filter has allowed
   them and nothing is cleaning them.
2. Someone who can upload files or edit a post picks that attachment. The
   picker is wrapped in `MediaUploadCheck`, so the control is only drawn for a
   user with `upload_files`, and `hmIconId` can otherwise only be set by editing
   post content.

**A site that allows SVG uploads should be sanitising them on upload**, with
`safe-svg` or equivalent. A site that does not allow SVG uploads is unaffected:
no attachment will have the mime type, and `uploaded_icon_markup()` returns an
empty string.

There is no setting or filter that turns the upload path off. Denying
`upload_files` only hides the picker, and does nothing about a `hmIconId`
already sitting in post content, since `uploaded_icon_markup()` checks no
capability at render time. The two mitigations that actually hold are
sanitising SVGs on upload, or not allowing SVG uploads at all.

## Registered icons

An icon chosen from a collection goes through `wp_get_icon()`, so it comes back
already run against the Icons API allowlist, which keeps fill geometry and drops
everything else. The icon's slug is passed through `sanitize_html_class()`
before it becomes a class name, and the size is an `absint` checked against the
list `hm_button_icon_sizes` resolved to. `hmIconName` still arrives from stored
post content, but it is a lookup key against the registry rather than something
that reaches the output, so an unrecognised name renders nothing.

## Attributes are not validated by the block registry

Worth knowing when reading `inc/render.php`: `render_block_core/button` is handed
`WP_Block::$parsed_block`, whose `attrs` come straight from the block comment and
are never checked against the registered attribute schema. Registering the
attributes server side gives them defaults and puts them in
`/wp/v2/block-types`, but it does not filter them. Every attribute the render
filter reads is treated as untrusted and normalised there.

Post content itself is only editable by a user with the capability to edit the
post, so this is not an unauthenticated surface.
