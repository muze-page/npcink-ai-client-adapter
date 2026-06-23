# OpenClaw Gutenberg Content Intent Routing Baseline

Status: accepted

This baseline records the first verified OpenClaw flow where customer natural
language was routed through a read-only intent router before selecting a
Gutenberg recipe, creating a Core proposal, executing only after approval, and
verifying the result through read-back abilities.

The baseline is evidence for future regression checks. It is not an execution
shortcut, not an approval policy, and not a promise that future proposal ids or
post ids are stable.

## Contract Under Test

All successful routes used:

```text
customer natural language
-> npcink-abilities-toolkit/route-content-intent
-> returned plan_ability_id
-> POST /proposals/from-plan
-> Core approval and commit-preflight
-> Adapter approved execution
-> read-back verification
```

Shared invariants:

- `prompt_is_authorization=false`
- no direct WordPress REST writes by OpenClaw
- no proposal execution before approval
- no generic Adapter write executor
- no `core/html`
- no non-core Gutenberg blocks
- no arbitrary custom CSS
- draft-only for post/page content creation
- media uses reviewed local WordPress media URLs, not temporary Cloud preview
  URLs
- read-back verification is the acceptance signal

## Verified Routes

### Page Landing

Customer wording:

```text
帮我做一个现代官网介绍页，需要配图，手机端也要好看。
```

Intent route:

- route: `pattern_page_plan`
- plan ability: `npcink-abilities-toolkit/build-pattern-page-plan`
- final writes: `create-draft`, `update-post-blocks`

Observed execution:

- proposal id: `7874c491-89a1-4700-a07d-526937ff466c`
- created page: `281748`
- status: `draft`
- total blocks: `101`
- top-level blocks: `8`
- `roundtrip_ok=true`
- `validation.valid=true`
- media URL: `https://npcink.local/wp-content/uploads/2026/06/preview.webp`
- attachment id: `8053`
- `core/image.attrs.id=8053`
- `core/media-text.attrs.mediaId=8053`
- rendered image markup contains `wp-image-8053`
- columns stack on mobile
- FAQ uses `core/details`

### Article Post

Customer wording:

```text
写一篇对比评测文章，加一张配图。
```

Intent route:

- route: `article_block_plan`
- plan ability: `npcink-abilities-toolkit/build-article-block-plan`
- final writes: `create-draft`, `update-post-blocks`

Observed execution:

- proposal id: `9fd06242-a936-4d9d-a693-621284e5eb6b`
- created post: `281750`
- status: `draft`
- total blocks: `30`
- top-level blocks: `18`
- `roundtrip_ok=true`
- `validation.valid=true`
- media URL: `https://npcink.local/wp-content/uploads/2026/06/preview.webp`
- attachment id: `8053`
- `core/image.attrs.id=8053`
- rendered image markup contains `wp-image-8053`
- columns stack on mobile
- FAQ uses `core/details`

Text scans may find phrases such as "temporary Cloud preview URL" when the
article discusses the rule. The acceptance check is structural: media URLs in
blocks must not point to temporary Cloud preview artifacts.

### Site Template Breadcrumbs

Customer wording:

```text
给文章模板加面包屑导航。
```

Intent route:

- route: `block_theme_site_plan`
- plan ability: `npcink-abilities-toolkit/build-block-theme-site-plan`
- final writes: `update-template-blocks` or `upsert-template-blocks`

Observed execution:

- proposal id: `ae94822a-0c33-4a5f-b189-b8a1578ab02a`
- affected templates: `single`, `page`
- target abilities: `npcink-abilities-toolkit/update-template-blocks`
- `roundtrip_ok=true`
- `validation.valid=true`
- read-back reports `has_breadcrumbs=true`
- no raw template HTML
- no `theme.json` patch
- no navigation mutation
- no global styles mutation

The template execution was a successful no-op: both target templates already
matched the planned breadcrumb state, so `updated=false` and content hashes did
not change. This is acceptable when read-back confirms the desired state.

## Regression Expectations

Future changes to these flows should keep the following checks green:

1. `route-content-intent` runs before the plan ability.
2. Unsupported or ambiguous prompts return `route=unsupported` or
   `needs_clarification=true`.
   Negative prompts for navigation edits, global styles or `theme.json` patches,
   and custom HTML direct execution must keep `plan_ability_id` empty, emit no
   `write_actions`, and stop before `POST /proposals/from-plan`.
3. Plan abilities remain read-only and return Core-ready artifacts, not direct
   writes.
4. Core proposal approval remains required before Adapter execution.
5. Post/page content writes continue through `update-post-blocks`.
6. Site Editor template writes continue through template-specific abilities.
7. Read-back verification uses `get-post-blocks`, `get-template-blocks`, or
   `get-template-part-blocks` according to the target surface.
8. A no-op template execution is acceptable only when read-back confirms the
   requested state.

See also:

- [`openclaw-content-intent-router-contract.md`](openclaw-content-intent-router-contract.md)
- [`openclaw-gutenberg-design-system.md`](openclaw-gutenberg-design-system.md)
- [`openclaw-gutenberg-visual-acceptance.md`](openclaw-gutenberg-visual-acceptance.md)
- [`openclaw-site-edit-router-contract.md`](openclaw-site-edit-router-contract.md)
