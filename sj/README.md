# Npcink AI Client Adapter Listing Assets

This folder is the working area for WordPress.org listing copy and visual
assets. It is intentionally excluded from release archives through
`.distignore`.

## Directory Layout

- `positioning.md` - product positioning, audience, boundaries, and series map.
- `listing-copy-en.md` - English WordPress.org listing draft.
- `listing-copy-zh.md` - Chinese listing draft for reuse on Chinese channels.
- `image-prompts.md` - reusable AI image prompts for the icon and banner.
- `translation-notes.md` - bilingual listing and runtime translation notes.
- `license-notes.md` - image provenance and release notes.
- `source/` - original generated or editable source images.
- `exports/wordpress-org/` - final WordPress.org asset filenames and dimensions.
- `review/` - screenshots or review previews before publishing.

## WordPress.org Image Exports

After artwork is generated, prepare these files for the WordPress.org SVN
top-level `assets/` directory:

- `exports/wordpress-org/banner-1544x500.png`
- `exports/wordpress-org/banner-772x250.png`
- `exports/wordpress-org/icon-256x256.png`
- `exports/wordpress-org/icon-128x128.png`
- `exports/wordpress-org/screenshot-1.png`
- `exports/wordpress-org/screenshot-2.png`
- `exports/wordpress-org/screenshot-3.png`

Screenshot captions are defined in the `== Screenshots ==` section of the
root `readme.txt`. Keep the exported screenshot filenames and readme numbering
in sync:

1. Adapter connection overview.
2. Active key-pair devices with a revoked-device summary.
3. WordPress Application Password fallback flow.

Do not copy this whole `sj` folder into the plugin zip.

## Bilingual Release Notes

The plugin code uses the `npcink-ai-client-adapter` text domain for translatable
runtime strings. The WordPress.org listing should use English as the primary
directory copy, while `listing-copy-zh.md` can be reused for Chinese launch
posts, documentation, and marketplace-adjacent pages.
