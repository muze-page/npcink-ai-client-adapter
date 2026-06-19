# Adapter Release Acceptance - 2026-06-17

## Status

Accepted for Adapter PR review and branch publication.

This record closes the current Adapter-side runtime contract and acceptance
phase. The Adapter remains the thin OpenClaw-compatible channel layer. It does
not become a Core approval store, Abilities Toolkit catalog owner, workflow
runtime, MCP runtime, provider router, prompt manager, or generic final-write
executor.

## Branch Scope

Current local branch:

```text
codex/runtime-contract-endpoint-adapter
```

Adapter code commits included before this record was written:

```text
a4eacfb adapter: add local article template visual harness
884289f Add release package and CLI fixture acceptance gates
```

The PR review scope should include those commits plus this closeout record if
the branch is pushed as-is.

## What Changed

- Added a release package install smoke gate:
  `composer smoke:package-install`.
- Added a signed local AI client fixture acceptance gate:
  `composer accept:local-ai-client-fixture`.
- Documented the fixture acceptance flow and commit-enabled mode in
  `docs/local-ai-client-acceptance.md`.
- Documented optional Core context site/blog binding in
  `docs/openclaw-adapter-contract.md`.
- Added Adapter-side fail-closed validation for optional Core `site_url`,
  `home_url`, and `blog_id` context bindings when those fields are present.
- Added Adapter-side forwarding and fail-closed validation for Core
  `signed_client_fingerprint` / `client_key_fingerprint` bindings when Core
  emits them in preflight and read authorization contexts.

## Validation Completed

The following checks passed on the local development WordPress site:

```bash
composer test:all
composer smoke:wp
composer release:verify
composer package:release
composer smoke:package-install
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
  MAA_ADAPTER_ACCEPTANCE_INSECURE_LOCAL_TLS=1 \
  composer accept:local-ai-client-fixture
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
  MAA_ADAPTER_ACCEPTANCE_INSECURE_LOCAL_TLS=1 \
  MAA_ADAPTER_FIXTURE_ALLOW_COMMIT=1 \
  composer accept:local-ai-client-fixture
git diff --check
```

The commit-enabled fixture created draft post `283118`, verified duplicate
execution rejection with
`npcink_openclaw_adapter_execution_already_completed`, and cleaned the draft
with WP-CLI.

The release package was rebuilt at:

```text
build/npcink-ai-client-adapter.zip
```

## PR Review Focus

Review should focus on these boundaries:

- Adapter still reports and consumes dependency contracts without owning Core
  proposal, approval, or audit truth.
- Adapter still preserves Core's `core_proxy_execute=false` and
  `commit_execution=false` boundary.
- Final writes remain limited to explicit Adapter execution profiles after Core
  approval and commit-preflight.
- The release package smoke verifies the packaged plugin surface, not only the
  source checkout.
- The signed local client fixture verifies proposal creation, proposal readback,
  explicit `--intent=commit` gating, final execution, duplicate rejection, and
  cleanup.

## Not Completed Here

Client-key fingerprint binding is not complete in Adapter alone.

Reason: Adapter can only enforce that binding after Core includes a signed
client fingerprint in the preflight and sensitive-read authorization context.
Until Core emits that field, Adapter can document the requirement and enforce
other optional context bindings, but it cannot prove the signing client identity
from Core handoff data.

No additional Adapter-owned Cloud settings, Cloud connector routes, workflow
runtime, MCP runtime, provider model routing, prompt management, or generic
write executor should be added for this phase.

## Next Phase

Move the runtime contract endpoint work to the owning plugins, in this order:

1. Governance Core
   - Expose an admin-authenticated runtime contract endpoint.
   - Report governance contract schema version and compatibility floors.
   - Report proposal, approval, audit, and commit-preflight ownership.
   - Report `core_proxy_execute=false` and `commit_execution=false`.
   - Include signed context fields that Adapter can validate, including the
     signed local client fingerprint field.

2. Abilities Toolkit
   - Expose an admin-authenticated runtime contract endpoint.
   - Report Abilities Toolkit contract schema version and compatibility floors.
   - Report ability registration ownership, schema source, read/write
     classification posture, and host-governed write posture.
   - Avoid moving proposal approval, audit truth, or Adapter channel behavior
     into Toolkit.

3. Adapter follow-up
   - Continue consuming Core and Toolkit contract summaries on `/health`,
     `/help`, and `/connection/manifest`.
   - Keep signed-client fingerprint forwarding and validation covered by
     static, fail-closed, and local-client fixture tests.
   - Keep the Adapter release gates in this record as the regression baseline.
