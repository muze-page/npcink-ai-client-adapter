# OpenClaw Gutenberg Design System

Status: accepted

This document defines the design-quality contract for governed Gutenberg page,
article, and block-theme generation. It is a recipe and acceptance guide, not a
renderer, not a CSS package, and not a write authority.

## Why This Exists

The first Gutenberg baseline proved the important safety loop:

```text
natural language -> intent route -> plan -> Core proposal -> approved execute -> readback
```

That loop is necessary, but not sufficient for high-quality pages. If every
request uses one conservative pattern and one section order, output will look
template-like even when it is valid Gutenberg.

The next quality step is to give OpenClaw and the Toolkit a shared design
vocabulary:

- choose a suitable recipe variant from intent and site context;
- use reviewed media or generated-and-adopted media before page planning;
- vary section shape, color story, media placement, and proof modules;
- return quality signals that can fail a plan before it reaches proposal;
- keep everything Gutenberg-native and editable.

## Reference Sources

Use these only as structural inspiration. Do not copy text, images, CSS, exact
layouts, claims, or brand treatment.

- WordPress block patterns and theme.json documentation:
  https://developer.wordpress.org/block-editor/
- WordPress Twenty Twenty-Five patterns:
  https://github.com/WordPress/twentytwentyfive
- Frost block theme patterns:
  https://github.com/wpengine/frost
- Ollie block theme patterns:
  https://github.com/OllieWP/ollie
- 10up Gutenberg engineering practices:
  https://10up.com/blog/2022/10up-publicly-releases-its-gutenberg-best-practices/

The shared lesson is that strong Gutenberg output comes from a small system of
patterns, style tokens, media roles, and responsive rules. It should not rely on
large inline HTML blobs or arbitrary CSS.

## Layer Ownership

Adapter owns only the public recipe contract and verification handoff.

Toolkit owns:

- recipe registry;
- block rendering;
- allowed pattern variants;
- Gutenberg-native style attributes;
- quality signal calculation;
- class allowlists if a later version adds them.

OpenClaw owns:

- natural-language interpretation;
- route selection through `route-content-intent`;
- optional research brief requests;
- optional image recommendation or hosted generation requests;
- operator-facing proposal explanation;
- browser visual acceptance when available.

Core owns:

- proposal storage;
- approval state;
- commit-preflight;
- audit outcome.

## Design Vocabulary

Page recipes should be described as a combination of stable section primitives,
not as one monolithic template.

Allowed primitives for landing pages:

- `split_hero`
- `product_media_panel`
- `proof_strip`
- `bento_feature_grid`
- `comparison_cards`
- `workflow_timeline`
- `governance_panel`
- `use_case_grid`
- `faq_details`
- `final_cta_band`

Allowed primitives for articles:

- `editorial_lede`
- `featured_image`
- `key_takeaways`
- `section_heading`
- `comparison_columns`
- `checklist`
- `quote_or_callout`
- `faq_details`
- `source_notes`

Allowed primitives for block-theme templates:

- `breadcrumbs`
- `post_title_frame`
- `featured_image_frame`
- `post_meta_row`
- `content_frame`
- `related_content_placeholder`

## Anti-Template Rules

A Pattern library should not mean every generated page looks the same. The plan
builder should vary the composition within a governed design system.

For landing pages, the plan should choose at least three of these knobs:

- hero variant: `split_media`, `centered_statement`, `dashboard_panel`;
- media role: `hero_visual`, `inline_proof`, `comparison_visual`;
- feature layout: `bento`, `three_column`, `alternating_rows`;
- proof module: `metrics_strip`, `workflow_status`, `testimonial_style`;
- contrast module: `dark_band`, `accent_surface`, `quiet_table`;
- CTA alignment: `left`, `center`, `split`.

The plan should return a short `variant_reason` explaining why the chosen
composition fits the request. If the generated section order and visual roles
are too close to a recent plan for the same site, the Toolkit should return a
lower `template_similarity_score` and choose a different layout variant before
proposal creation.

## Quality Signals

Future `pattern_page_plan` and `article_block_plan` outputs should include a
design-quality object such as:

```json
{
  "design_quality": {
    "pattern_version": "3.1",
    "design_system": "gutenberg_native_v1",
    "recipe_variant": "saas_product_landing",
    "variant_reason": "The request asks for a modern product page, so the plan uses split hero media, bento features, comparison cards, and a dark CTA.",
    "section_shape_variety": 5,
    "media_coverage_score": 0.8,
    "color_story": "editorial-accent",
    "responsive_profile": "landing_standard",
    "template_similarity_score": 0.42,
    "custom_css_required": false,
    "uses_core_html": false,
    "uses_non_core_blocks": false
  }
}
```

Suggested minimum gates before proposal creation:

- `section_shape_variety >= 4` for landing pages;
- `media_coverage_score >= 0.6` when the customer asks for a modern or visual
  page;
- `template_similarity_score <= 0.75` for repeat page generation on the same
  site;
- `custom_css_required=false`;
- `uses_core_html=false`;
- `uses_non_core_blocks=false`;
- all columns that matter on mobile set `isStackedOnMobile=true`;
- images use final local WordPress media URLs and attachment ids when
  available.

## Media Quality

High-quality pages usually need a real visual asset. If the customer asks for a
modern landing page, product page, showcase page, or visual page, OpenClaw
should prefer this sequence:

1. Build a suggestion-only `landing_page_research_brief` when the topic benefits
   from external examples.
2. Ask the Cloud-backed image recommender for reviewed candidates.
3. Use hosted AI generation only when no reviewed candidate fits.
4. Crop and convert the chosen candidate through the Cloud media derivative
   path.
5. Adopt the processed asset into the WordPress media library through Core.
6. Pass only the final local WordPress media URL and attachment id into the
   Gutenberg plan.

Temporary Cloud preview URLs must never be referenced by the final page or
article blocks.

## Responsive Baseline

Responsive quality is part of the design system, not a late polish task.

Every governed Gutenberg plan should preserve:

- predictable full-width section boundaries;
- constrained content width for readable text;
- mobile stack behavior for `core/columns`;
- button wrapping on narrow viewports;
- no horizontal overflow at 1440px, 768px, and 390px;
- readable image alt text;
- no invalid block recovery prompts in the editor.

## Adapter Non-Goals

Adapter must not:

- render pattern blocks;
- choose images directly;
- call model providers for page generation;
- store a pattern registry;
- accept arbitrary CSS;
- accept raw template HTML as a visual shortcut;
- execute writes without Core approval and commit-preflight.

If visual quality needs more expressive styling later, add it as a Toolkit or
theme-level contract first. Adapter should only expose the recipe and acceptance
surface.
