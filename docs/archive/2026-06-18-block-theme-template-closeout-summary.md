# Block Theme Template Customization Closeout - 2026-06-18

Status: accepted closeout record.

This document summarizes the current effect, value, verification evidence, and
next phase for OpenClaw-driven WordPress block-theme template customization.

## Current Effect

OpenClaw can now take natural-language template customization requests and route
them into a governed WordPress block-theme template write loop for three accepted
templates:

- `single` through `article_standard`;
- `page` through `page_standard`;
- `front-page` through `homepage_landing`.

The implemented loop is:

```text
natural-language request
-> intent routing
-> Adapter read ability dispatch
-> npcink-abilities-toolkit block-theme site plan
-> Governance Core proposal
-> operator approval
-> Adapter allowlisted post-Core execution
-> template block readback
-> frontend and Site Editor visual acceptance
```

The result is not a generic AI site builder. It is a bounded, reviewable,
approveable, executable, and readback-verifiable template customization flow.

## What Is Proven

The current milestone proves that:

- natural-language prompts can route to `customize_template_layout`;
- `front-page`, `single`, and `page` can be resolved to actual block-theme
  templates instead of relying on brittle template-name assumptions;
- Toolkit can produce Gutenberg-native template plans using core blocks;
- Core can receive those plans as reviewable proposals;
- Adapter can execute only after Core approval and commit-preflight through an
  explicit execution profile;
- changed template blocks can be read back after execution;
- frontend browser acceptance passes at desktop, tablet, and mobile sizes;
- local Site Editor acceptance opens the changed template without invalid block
  recovery prompts;
- local development harnesses restore template content after verification.

## Repository Boundaries

The boundary remains:

```text
OpenClaw -> Adapter -> WordPress Abilities API
OpenClaw -> Adapter -> Governance Core proposal/preflight
```

Adapter owns:

- OpenClaw-facing REST routes;
- read ability dispatch;
- proposal and commit-preflight handoff;
- explicit post-Core execution profile policy;
- local acceptance harnesses and diagnostics.

Adapter does not own:

- Toolkit ability definitions or callbacks;
- template profile generation logic;
- Core proposal storage, approval, preflight, or audit truth;
- generic final write execution;
- workflow runtime, queues, model routing, prompt management, or product UX;
- Cloud runtime, Cloud routes, Cloud connector state, or hosted execution truth.

Template generation and profile quality belong in `npcink-abilities-toolkit`.
Proposal state and approval truth belong in `npcink-governance-core`.

## Completed Adapter Work

Recent Adapter commits:

```text
605993e adapter: harden block theme visual harness
5a95f99 adapter: close block theme template milestone
```

The work added or hardened:

- generic local block-theme template visual harness coverage for
  `article_standard`, `page_standard`, and `homepage_landing`;
- WP-CLI PHP/socket handling so local template restore works against Local.app
  databases;
- `block_editor_url` in local template visual manifests so editor checks are
  real rather than skipped;
- temporary local administrator support for Site Editor checks through
  `MAA_ADAPTER_VISUAL_ACCEPTANCE_CREATE_TEMP_ADMIN=1`;
- static contract checks for the visual harness and milestone documentation;
- milestone documentation in
  `docs/archive/2026-06-18-block-theme-template-milestone.md`.

## Verification Evidence

Static and contract verification:

```bash
composer test:all
```

Local frontend and editor visual verification:

```bash
MAA_ADAPTER_VISUAL_ACCEPTANCE_CREATE_TEMP_ADMIN=1 \
MAA_ADAPTER_BLOCK_THEME_VISUAL_PROFILE=article_standard \
composer dev:block-theme-template-visual

MAA_ADAPTER_VISUAL_ACCEPTANCE_CREATE_TEMP_ADMIN=1 \
MAA_ADAPTER_BLOCK_THEME_VISUAL_PROFILE=page_standard \
composer dev:block-theme-template-visual

MAA_ADAPTER_VISUAL_ACCEPTANCE_CREATE_TEMP_ADMIN=1 \
MAA_ADAPTER_BLOCK_THEME_VISUAL_PROFILE=homepage_landing \
composer dev:block-theme-template-visual
```

The latest verified run showed all three profile reports with:

```text
ok=true
warnings=0
editor_passed=1
editor_skipped=0
```

Local cleanup was also verified:

```text
temporary visual admin users removed
article template restored
page template restored
home template restored
```

Real governed OpenClaw execution verification:

```bash
MAA_ADAPTER_BLOCK_THEME_OPENCLAW_ACCEPTANCE_COMMIT=1 \
composer accept:block-theme-openclaw
```

The latest acceptance report showed:

```text
product_gap_count=0
failed_assertions=[]
article_standard execution_record_status=succeeded restored_template=true
page_standard execution_record_status=succeeded restored_template=true
homepage_landing execution_record_status=succeeded restored_template=true
```

## Product Value

This is worth keeping because it demonstrates a differentiated WordPress AI
capability:

- the user can speak naturally instead of naming technical template parameters;
- the system routes and constrains the request instead of demanding precision
  from the user;
- every write remains reviewable, approveable, auditable, and verifiable;
- the same governance loop can later support more site-editing surfaces without
  moving ownership into Adapter.

The main product value is the governed loop, not visual polish alone.

## Main Risk

The main risk is scope expansion:

- turning Adapter into a generic site builder;
- adding model routing or prompt management to Adapter;
- growing template profiles faster than acceptance coverage;
- fixing visual issues in Adapter instead of Toolkit;
- bypassing Core because local iteration feels faster.

The current milestone deliberately stops at three accepted profiles to keep the
loop demonstrable and stable.

## Next Phase

The next phase should productize the existing three-profile loop rather than add
new templates immediately.

Recommended main target:

```text
Create a demonstrable OpenClaw template customization flow for single, page,
and front-page that a reviewer can understand without reading test internals.
```

Suggested work:

1. Create an end-to-end demo script with three natural-language examples:
   article page, ordinary page, and homepage.
2. Improve proposal review copy so humans can quickly see target template,
   profile, changed sections, risks, and readback expectations.
3. Add explicit failure-path acceptance for non-block themes, missing templates,
   disallowed writes such as `theme.json`, navigation, global styles, and user
   rejection.
4. Keep local visual harness and real OpenClaw commit acceptance as the two
   required gates before claiming product readiness.

## Deferred Work

Defer these until the three-profile loop is reviewable and stable:

- `archive`, `search`, `404`, and other template profiles;
- multi-turn template design UX;
- screenshot-based AI review loops;
- provider/model routing or prompt orchestration;
- Cloud runtime, queues, or long-running workflows;
- arbitrary CSS, non-core blocks, theme file writes, navigation writes, global
  styles, or `theme.json` changes.

## Practical Recommendation

Stop expanding capabilities for now. The next useful work is to make the current
loop easy to demonstrate, easy to review, and hard to misuse.

Once that is stable, evaluate whether `archive` is the next profile. Do not add
it before the failure-path acceptance and proposal review copy are improved.
