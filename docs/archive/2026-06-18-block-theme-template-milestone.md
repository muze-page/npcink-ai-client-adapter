# Block Theme Template Customization Milestone

Status: accepted

This milestone keeps Npcink AI Client Adapter as the thin OpenClaw channel while
proving that natural-language block-theme template customization can complete a
governed WordPress write loop.

For the phase summary and next-stage recommendation, see
[`2026-06-18-block-theme-template-closeout-summary.md`](2026-06-18-block-theme-template-closeout-summary.md).

## Goal

Prove this bounded loop for `front-page`, `single`, and `page` templates:

```text
natural-language request
-> OpenClaw intent routing
-> Adapter read ability call
-> npcink-abilities-toolkit block-theme site plan
-> Governance Core reviewable proposal
-> operator approval
-> Adapter allowlisted post-Core execution profile
-> template block readback and browser acceptance
```

This is not a generic AI site builder. Adapter must not own template rendering,
business layout generation, prompt management, workflow runtime, or generic final
write execution.

## Repository Ownership

- Adapter owns OpenClaw REST routes, read ability dispatch, Core proposal and
  preflight handoff, explicit allowlisted post-Core execution, and local
  acceptance harnesses.
- `npcink-abilities-toolkit` owns block-theme context reads, profile selection,
  generated blocks, and template write abilities.
- `npcink-governance-core` owns proposal storage, approval state, audit truth,
  and commit-preflight policy.

## Accepted Profiles

The milestone is limited to:

- `article_standard` for `single`
- `page_standard` for `page`
- `homepage_landing` for `front-page`

Do not add more template profiles until the three accepted profiles stay green
through local visual acceptance, editor acceptance, and OpenClaw proposal
execution acceptance.

## Verification Gates

Run static and contract tests:

```bash
composer test:all
```

Run local visual acceptance with frontend and Site Editor checks:

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

The visual report must show:

- desktop, tablet, and mobile frontend checks pass;
- editor checks are not skipped;
- editor checks pass without invalid block recovery prompts;
- template content is restored after the local harness exits.

Run the real OpenClaw proposal execution acceptance:

```bash
MAA_ADAPTER_BLOCK_THEME_OPENCLAW_ACCEPTANCE_COMMIT=1 \
composer accept:block-theme-openclaw
```

The OpenClaw acceptance report must show proposal creation, approve-and-execute,
execution status `succeeded`, readback verification, and restored local template
state for the accepted profiles.

## Deferred Work

Defer until this milestone remains stable:

- additional template profiles such as `archive`, `search`, and `404`;
- broader natural-language conversation UX;
- model routing, prompt management, or provider execution in Adapter;
- screenshot-based AI review loops;
- Cloud runtime, queues, or long-running workflow orchestration;
- arbitrary CSS, theme file writes, navigation writes, global styles, or
  `theme.json` mutations.

When a deferred item becomes necessary, implement it in the owning repository
instead of widening Adapter:

- profile/compiler changes belong in `npcink-abilities-toolkit`;
- proposal and approval changes belong in `npcink-governance-core`;
- runtime, monitoring, or hosted execution belongs outside Adapter.
