# 2026-06-10 Closeout Summary

Status: accepted closeout record.

This document records the engineering decisions, commits, verification, and
remaining release posture from the 2026-06-10 cross-plugin cleanup session.

## Scope

The session covered these plugins:

- `npcink-abilities-toolkit`
- `npcink-governance-core`
- `npcink-ai-client-adapter`
- `magick-ai-toolbox`
- `magick-ai-cloud-addon`

The main implementation work landed in Adapter and Toolkit. Core, Toolbox, and
Cloud Addon were intentionally kept stable.

## Boundary Decisions

Core remains the independent governance kernel. It owns ability intake,
proposal records, approval/rejection state, commit preflight, audit, and
sensitive read authorization truth. It still does not execute final WordPress
writes, own workflow runtime, route models, store provider credentials, or
become a product UX surface.

Toolkit remains the owner of reusable WordPress ability definitions, schemas,
callbacks, metadata, and dry-run previews. New WordPress write capabilities
belong there, not in Adapter or Core.

Adapter remains the channel layer. It may execute only explicit post-Core
execution profiles after Core approval and commit-preflight. It does not define
abilities, store approval truth, become a generic final-write executor, or own
workflow/runtime queues.

Toolbox remains the operator UX and artifact surface. Future Toolbox work
should stay suggestion/plan oriented and hand final WordPress writes to Core
proposal governance through abilities.

Cloud Addon remains a thin hosted-runtime connector. It must not become a
second control plane, second ability registry, WordPress write owner, or billing
truth source.

## Work Completed

### Adapter Structure

Adapter Controller complexity was reduced without changing route behavior:

- `45d0ed0 Extract adapter plan ability supported profiles`
- `50b11ff Extract adapter execution profile registry`

The plan-to-proposal supported profiles now lives in `Plan_Ability_Allowlist`. The
approved-write execution profile policy now lives in
`Execution_Profile_Registry`. Controller keeps using these registries while the
REST namespace and governance behavior remain unchanged.

### Toolkit Site Editor Ability

Toolkit gained a governed Site Editor write ability:

- `81e9f80 Add template override upsert ability`

The new ability is `npcink-abilities-toolkit/upsert-template-blocks`. It
supports reviewed creation or update of a `wp_template` Site Editor override,
including dry-run preview, Core approval commit guard, roundtrip validation,
and tests for file-backed active-theme templates.

The Toolkit branch `codex/pattern-page-quality-review` was pushed to origin.

### Adapter Toolkit Alignment

Adapter was updated to consume the new Toolkit ability only through its
explicit execution profile supported profiles:

- `eede3cd Allow template override upsert execution`

This added `npcink-abilities-toolkit/upsert-template-blocks` to Adapter's
post-Core execution profile registry, help recipe, batch execution policy,
contract docs, static tests, and smoke supported profiles. The ability remains
Core-governed and does not introduce generic Site Editor writes.

### Adapter Public Identity

Adapter public identity was renamed:

- `dde316c Rename adapter public identity`

The public plugin is now `Npcink AI Client Adapter`, with main file
`npcink-ai-client-adapter.php` and text domain `npcink-ai-client-adapter`.

The legacy `npcink-openclaw-adapter.php` file remains as a no-header bootstrap
so existing installs with stale active plugin paths can continue loading the
renamed plugin. The REST namespace remains `npcink-openclaw-adapter/v1` for
client route compatibility.

Adapter code through the public identity rename was pushed to:

```text
gitee.com:gitgreat/magick-ai-adapter.git
```

Pushed code range:

```text
64445c2..dde316c master -> master
```

## Verification

Adapter verification completed:

```bash
composer test:all
composer validate --no-check-publish
composer release:verify
composer package:release
```

The release package was generated at:

```text
build/npcink-ai-client-adapter.zip
```

The package root is:

```text
npcink-ai-client-adapter/
```

The package contains both:

- `npcink-ai-client-adapter.php`
- `npcink-openclaw-adapter.php`

Toolkit verification completed:

```bash
composer test:all
```

Core baseline verification completed earlier in the session:

```bash
composer test:all
```

Toolbox and Cloud Addon were left unchanged and clean.

## Current Repository State

At code closeout:

- Adapter implementation commits through `dde316c` are pushed and aligned with
  `origin/master`.
- This document is a local closeout record until the documentation commit is
  pushed.
- Toolkit `codex/pattern-page-quality-review` is pushed and aligned with
  `origin/codex/pattern-page-quality-review`.
- Core, Toolbox, and Cloud Addon have no required follow-up changes from this
  session.

## Remaining Decision

The engineering work can stop here. Any next step is a release operation, not a
code expansion:

- publish or distribute `build/npcink-ai-client-adapter.zip`;
- install-test the package on the target WordPress site;
- prepare release notes for the public identity rename and compatibility
  bootstrap;
- decide whether to open or merge a Toolkit PR for
  `codex/pattern-page-quality-review`.

Do not add new product features until the Adapter rename and Toolkit branch are
reviewed in their release context.
