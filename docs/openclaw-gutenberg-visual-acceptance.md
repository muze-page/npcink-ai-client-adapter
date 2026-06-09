# OpenClaw Gutenberg Visual Acceptance

Status: accepted

This checklist turns governed Gutenberg page and article generation into a
repeatable quality loop without making Adapter a renderer, browser runner, or
workflow runtime.

## Scope

Applies to:

- `openclaw_recipes.pattern_page_plan`
- `openclaw_recipes.article_block_plan`

Adapter exposes the acceptance contract from `GET /help`, forwards the plan to
Core, and executes only approved write actions. Toolkit still owns the rendered
Gutenberg blocks. Browser automation, screenshots, and visual judgment belong to
OpenClaw or the operator environment.

## Smoke Fixture Manifest

The normal WordPress smoke test deletes all fixtures. To create temporary pages
that can be opened in a browser, run smoke with both variables:

```bash
MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT=/tmp/openclaw-gutenberg-visual-acceptance.json \
MAA_ADAPTER_KEEP_VISUAL_ACCEPTANCE_FIXTURES=1 \
composer smoke:wp
```

The JSON manifest includes:

- `front_end_url`
- `block_editor_url`
- `attachment_ids`
- `viewports`
- `manual_checks`
- machine-checked `structure_signals`

Only use retained fixtures in local development. Delete the retained draft posts
and media attachments after the browser pass.

## Required Viewports

Use the same viewport set for pages and articles:

- desktop: `1440x1000`
- tablet: `768x1024`
- mobile: `390x844`

## Browser Checks

For each retained fixture, verify:

- the front-end page has no horizontal overflow;
- the block editor opens without invalid block recovery prompts;
- core blocks remain individually editable;
- mobile layout wraps or stacks without clipping;
- images load from reviewed existing media URLs;
- CTA buttons and long headings do not overflow on mobile.

## Structural Baseline

Smoke verifies the preconditions that can be checked without a browser:

- post or page remains `draft`;
- Gutenberg block comments are stored;
- `core/media-text` or `core/image` exists when media is supplied;
- `core/columns` keeps `isStackedOnMobile=true` where columns are used;
- FAQ output uses `core/details`;
- `GET /post-blocks` can read the generated block tree through Adapter.

Failing a browser check should feed back into the Toolkit pattern or article
template. Do not fix visual issues by adding arbitrary CSS, direct WordPress
writes, or Adapter-side rendering logic.
