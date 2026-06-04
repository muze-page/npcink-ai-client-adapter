# OpenClaw Consumer Acceptance

Status: active acceptance checklist
Date: 2026-06-02

## Latest Acceptance Result

Post-governance acceptance completed against:

- Core commit `6acb159 Add read governance metadata to capabilities`
- Adapter commit `b81dc2a Add read governance envelopes and redaction`

Verified commands:

```bash
composer test:all
composer smoke:wp
git diff --check
git push origin master
```

An HTTP client acceptance pass also completed successfully through a temporary
WordPress Application Password. That pass connected to Adapter for the
OpenClaw-facing flow and verified `/health`, `/help`, `/capabilities`, public,
internal, and sensitive read envelopes, diagnostics redaction,
proposal-required read refusal, proposal create/list/detail,
`approve-and-execute`, duplicate execution replay protection, returned
`proposal_id`, `correlation_id`, `ability_id`, `execution_record`, and
`core_commit_execution=false`, plus Core Audit and AI Request Logs correlation.

The next change to OpenClaw routes, the Adapter execution allowlist, or log
correlation fields must rerun this acceptance checklist.

## Purpose

This document defines the minimum productized OpenClaw acceptance loop for
Magick AI Adapter.

The goal is to prove that OpenClaw can use one Adapter connection for useful
WordPress work while preserving the current project split:

- Adapter is the OpenClaw-facing WordPress REST channel.
- Core is the governance authority for proposals, approvals, preflight, and
  audit.
- Abilities are the canonical source of ability schemas, callbacks, and
  direct-read execution.
- AI Request Logs stay owned by the WordPress `ai` plugin and are correlated
  with Core audit by `proposal_id` and `correlation_id`.

## Required Runtime Shape

OpenClaw connects to:

```text
https://magick-ai.local/wp-json/magick-ai-adapter/v1
```

OpenClaw must not connect directly to Magick AI Core for productized use. Core
may still be used by developers for direct governance testing, but productized
OpenClaw setup starts at Adapter.

Authentication uses a dedicated WordPress Application Password over WordPress
REST Basic Auth. Adapter must not store the raw Application Password, and
OpenClaw setup must use the non-secret connection manifest plus a dedicated
secret field or credential vault for the password.

## Local Fixture Cleanup

Local smoke and HTTP acceptance fixtures must be registered for automatic
cleanup before any assertion that can fail. Use `register_shutdown_function`,
`try/finally`, or an equivalent fixture registry so posts, attachments,
comments, terms, Core proposal rows, Core audit rows, and Adapter execution
records created by the current test are deleted on success, assertion failure,
or unexpected exit.

Negative-loop checks must register their target content separately from the
write execution path. Rejected and preflight-blocked proposals are expected not
to run `magick-ai/trash-post`, so cleanup cannot depend on Adapter or Core
executing the final write. This applies to local fixtures with labels such as
`Negative Loop Reject Fixture`, `Negative Loop Preflight Fixture`,
`OpenClaw HTTP acceptance draft`, and `Operator Loop Article Draft`.

## Acceptance Flow

Run this order for a local acceptance pass:

1. Create or confirm a dedicated Application Password from
   `Magick AI -> Adapter`.
2. Copy the non-secret connection manifest to OpenClaw and paste the Application
   Password only into OpenClaw's dedicated secret field. Do not paste it into
   chat, tool commands, logs, proposal payloads, files, or copied handoff text.
3. Call `GET /health`.
4. Require these health values:
   - `core_capabilities=true`
   - `abilities_catalog=true`
   - `approval_proxy_enabled=false`
   - `approval_surface=magick_ai_core_admin`
   - `core_proxy_execute=false`
   - `commit_execution=false`
5. Call `GET /help` and confirm route discovery includes:
   - flat `routes[]` rows with `method`, `path`, `purpose`, and `group`
   - `GET /proposals`
   - `GET /proposals/{proposal_id}`
   - `GET /connection/manifest`
   - `POST /connect/device/start`
   - `POST /connect/device/poll`
   - `GET /connection/key-pairs`
   - `POST /proposals/from-plan`
   - `POST /proposals/{proposal_id}/execute`
   - `POST /proposals/{proposal_id}/approve-and-execute`
   - `POST /media-derivative-runs`
   - `GET /media-derivative-runs/{run_id}`
   - `GET /media-derivative-runs/{run_id}/result`
   - `GET /media-derivative-artifacts/{artifact_id}/preview`
   - `POST /media-derivative-proposal-payload`
   - `GET /term`, whose purpose explains term detail uses list row `id` and
     infers `taxonomy` when possible
   - `route_groups` for human-readable grouped route labels
   - `openclaw_recipes.article_draft_plan`
   - `openclaw_recipes.content_discoverability_suggestions`
   - `openclaw_recipes.ai_article_draft_with_discoverability`
   - `openclaw_recipes.media_derivative_cloud`
   - disabled approval and rejection stubs
   - direct-read shortcuts
6. Call `GET /capabilities` and use only real `ability_id` values returned by
   Core guidance.
7. Run at least one direct-read shortcut:
   - `GET /site-info`
   - `GET /site-summary`
   - `GET /media?per_page=1`
8. Run at least one diagnostics shortcut:
   - `GET /active-plugins-detail`
   - `GET /plugin-conflict-diagnostics` when testing plugin conflicts
   - `GET /current-user-permissions`
   - `GET /database-info`
   Confirm diagnostics details come from
   `npcink-abilities-toolkit/wp-ops-diagnostics-detail`, not from
   `wp-diagnostics-summary`.
   With default `include_log_contents=false`, log contents are not explicitly
   requested and must not be marked missing.
   With default `include_inactive_plugins=false`, inactive plugin rows are not
   requested and must not be marked missing. The plugin conflict shortcut must
   request `include_inactive_plugins=true` and return grouped plugin details for
   `plugins.active`, `plugins.inactive`, `plugins.update_available`,
   `plugins.must_use`, and `plugins.dropins`.
   Use `error_log.summary.fatal_count`, `error_log.summary.error_count`,
   `error_log.summary.warning_count`, `error_log.summary.deprecated_count`,
   `error_log.summary.notice_count`, and `error_log.summary.by_severity` for
   severity display even when log contents are not included.
   When log inspection is explicitly requested, call
   `GET /recent-error-log-tail` and display `error_log.tail_entries` plus
   `error_log.summary.by_severity`.
9. Create a governed write proposal with `POST /proposals`.
10. Run a planning ability such as
   `magick-ai/build-content-inventory-fix-plan`,
   `magick-ai/build-test-content-cleanup-plan`,
   `magick-ai/build-media-inventory-fix-plan`,
   `magick-ai/build-media-reference-repair-plan`, or
   `magick-ai/build-media-settings-reference-repair-plan`, or
   `magick-ai-toolbox/build-article-write-plan`. Confirm Adapter preserves
   `write_actions`, `preview`, `risk`, `requires_approval`,
   `commit_execution=false`, and `dry_run=true`, and does not treat
   `write_actions` or destructive candidates as executed work. Confirm the
   read envelope carries `read_policy=direct_read_internal`,
   `sensitivity=internal`, `redaction_applied=false`, a non-empty
   `correlation_id`, and `commit_execution=false`.
   For the SEO/AEO/GEO suggestion recipe, run
   `magick-ai-toolbox/build-content-discoverability-brief` through
   `POST /run-read-ability` or
   `GET /content-discoverability-brief?post_id=POST_ID`; confirm the result has
   `artifact_type=content_discoverability_brief`,
   `write_posture=suggestion_only`, `direct_wordpress_write=false`, and
   `final_write_path=core_proposal_required`.
   For broad natural-language article requests, run
   `magick-ai-toolbox/build-ai-article-writing-pack` through
   `POST /run-read-ability` or `GET /article-writing-pack?topic=AI_TOPIC`;
   confirm the result has `artifact_type=ai_article_writing_pack`,
   `write_posture=suggestion_only`, `provider_execution=none`,
   `direct_wordpress_write=false`, and
   `final_write_path=core_proposal_required`.
   For a public read such as `/site-summary`, confirm
   `read_policy=direct_read_public`.
   For a diagnostics read such as `/wp-diagnostics-summary`, confirm
   `read_policy=direct_read_sensitive`, `redaction_required=true`, and
   `redaction_applied=true`.
   Attempting `POST /run-read-ability` with a proposal-required ability must
   return `magick_ai_adapter_proposal_required`.
11. If a plan should become proposals, call `POST /proposals/from-plan` and
    confirm Adapter preserves Core's `proposal_count`, `proposals`,
    `blocked_items`, and `commit_execution=false` result.
    For the article draft recipe, confirm the plan has
    `artifact_type=article_write_plan`, Core creates a proposal for
    `magick-ai/create-draft`, and `approve-and-execute` creates only a
    WordPress `draft`.
    For the media derivative recipe, call `POST /media-derivative-runs` with a
    test image attachment and confirm the response returns
    `contract_version=media_derivative_adapter_run.v1`,
    `request_contract_version=media_derivative_cloud_request.v1`,
    `core_proposal_required=true`, and local adoption fields with
    `final_write_owner=local_wordpress_host`,
    `wordpress_write_included=false`, and
    `attachment_metadata_write_included=false`. Poll status/result only through
    Adapter's `media-derivative-runs` projection routes, then call
    `POST /media-derivative-proposal-payload` and confirm it returns a
    Core-ready payload without creating, approving, or executing a proposal.
    If the preview should replace the attachment main file, create a separate
    Core proposal for `magick-ai/adopt-cloud-media-derivative` using the
    non-expired `derivative_artifact`; Adapter can execute that proposal only
    after Core approval and commit-preflight.
12. Query status through Adapter:
   - `GET /proposals?limit=10`
   - `GET /proposals/{proposal_id}`
13. For split-path coverage, approve or reject one pending proposal in
    `Magick AI -> Core`.
14. If rejected, OpenClaw stops and shows the Core status.
15. If approved, call `POST /proposals/{proposal_id}/commit-preflight`.
16. Confirm Core still returns `commit_execution=false`.
17. For the unified user action, call
    `POST /proposals/{proposal_id}/approve-and-execute` from Adapter/OpenClaw.
    Confirm Adapter approved through Core when status was pending, ran Core
    commit-preflight, returned `proposal_id`, `post_id`, `ability_id`, and
    `correlation_id`, returned an `execution_record`, and moved the test post
    to `trash`.
    Repeating the same execute or approve-and-execute request must return
    `magick_ai_adapter_execution_already_completed` with the original
    `execution_record` and must not run the WordPress ability again.
    For batch plan-shaped proposals, confirm `input.write_actions[]` executes
    only when every item targets the Adapter execution allowlist and passes
    ability-specific input checks. Confirm the response includes
    `execution_mode=batch_write_actions`, `executed_count`, `failed_count`,
    and per-action `results[]`.
18. For lower-level approved proposal execution, use only the current
    `magick-ai/trash-post`, `magick-ai/create-draft`,
    `magick-ai/update-post`, `magick-ai/set-post-seo-meta`,
    `magick-ai/set-post-slug`, `magick-ai/set-post-terms`,
    `magick-ai/delete-term`, `magick-ai/update-media-details`,
    `magick-ai/patch-post-content`,
    `magick-ai/patch-setting-value`,
    `magick-ai/delete-media-permanently`, `magick-ai/reply-comment`,
    `magick-ai/trash-comment`, or `magick-ai/approve-comment` path and call
    `POST /proposals/{proposal_id}/execute`. Confirm Adapter performed Core
    preflight, passed `approval_context`, returned `proposal_id`,
    `correlation_id`, and `ability_id`, and did not execute pending or
    preflight-failed proposals.
19. Confirm rejected proposals, non-allowlisted proposals, and preflight-blocked
    proposals do not execute through approve-and-execute.
20. Pass `proposal_id` and `correlation_id` into later reads as query fields or
    as POST `/run-read-ability` `log_context`.
21. Call `POST /ai-provider-log-correlation-smoke` with a configured text
    generation provider/model. Local examples use `ai_provider=ollama` and
    `ai_model=qwen3.5:0.8b` when that model is available; otherwise use the
    provider/model returned by `GET /ai/v1/providers?capability=text_generation`.
22. Confirm the AI Request Logs row has `status=success` and context fields:
    `proposal_id`, `correlation_id`, `ability_id`, `adapter_request_id`,
    `adapter_route`, `ai_provider`, `ai_model`,
    `governance_source=magick-ai-core`, and nested `magick_ai_core`.
23. Confirm correlation in:
    - Core Governance Audit, filtered by `proposal_id` or `correlation_id`;
    - AI Request Logs, using Adapter context fields.

## Example Commands

Health:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/health"
```

Capabilities:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/capabilities"
```

Direct read:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/site-info"
```

Diagnostics read:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/active-plugins-detail"
```

Create proposal:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"ability_id":"magick-ai/create-draft","title":"OpenClaw draft acceptance","summary":"OpenClaw requests a governed draft proposal during acceptance.","input":{"title":"OpenClaw acceptance draft","dry_run":true,"commit":false},"preview":{"dry_run":true,"commit":false},"caller":{"external_thread_id":"OPENCLAW_ACCEPTANCE_THREAD"}}' \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals"
```

Create proposals from a read-only plan:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"plan_ability_id":"magick-ai/build-content-inventory-fix-plan","plan":{"batch_id":"acceptance","issue_types":[],"requires_approval":true,"commit_execution":false,"dry_run":true,"action_count":0,"write_actions":[],"preview":[],"risk":{"level":"medium"}},"plan_input":{"per_page":1},"caller":{"external_thread_id":"OPENCLAW_ACCEPTANCE_THREAD"}}' \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/from-plan"
```

Query proposal status:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID"
```

Commit preflight after Core approval:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  -X POST \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID/commit-preflight"
```

Correlation read:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/site-info?proposal_id=PROPOSAL_ID&correlation_id=CORRELATION_ID"
```

## Expected Failure Behavior

OpenClaw must stop and report the reason when Adapter or Core returns:

- `401` for missing or invalid WordPress REST authentication;
- `403` for missing WordPress capability or disabled approval proxy;
- `404` for missing proposal;
- `429` when Core app-key rate policy rejects an internal Core request;
- `magick_ai_adapter_proposal_required` when a caller tries to execute a
  proposal-required ability as a direct read;
- `magick_ai_adapter_approval_proxy_disabled` when a caller tries to approve or
  reject through Adapter's standalone disabled stubs;
- `magick_ai_adapter_execute_ability_not_allowed` when the proposal ability is
  outside Adapter's execution allowlist;
- `magick_ai_adapter_write_action_invalid`,
  `magick_ai_adapter_write_action_target_required`,
  `magick_ai_adapter_write_actions_limit_exceeded`, or
  `magick_ai_adapter_post_id_required` when a batch write action does not
  satisfy Adapter's V1 execution input contract;
- `magick_ai_adapter_proposal_rejected` when approve-and-execute is attempted
  after Core rejection;
- `magick_ai_adapter_preflight_not_authorized` or
  `magick_ai_adapter_preflight_item_blocked` when Core commit-preflight blocks
  execution.

These failure responses should include additive `data.operator_feedback` for
operator-facing revision loops. The object must preserve Core evidence without
becoming a second approval truth: `status`, `message`, `reasons[]`,
`revision_fields[]`, `next_steps[]`, `can_retry_after_revision`, and
`core_evidence`. OpenClaw should display it, stop execution, and guide the
operator to revise the plan or draft before creating a new proposal.

The disabled approval and rejection stubs are part of the acceptance surface.
They prove that OpenClaw can discover the routes while using
`approve-and-execute` for the unified user action or Core admin for split
approval decisions.

## Non-Goals

This acceptance pass must not add or require:

- generic Adapter approval or rejection proxying;
- final WordPress write execution outside the current allowlisted
  approve-and-execute path;
- Core `/execute` or `/proxy-execute`;
- MCP runtime;
- workflow runtime, queues, retries, or schedulers;
- provider credential storage;
- prompt, preset, model router, or product workflow ownership in Adapter.

## Verification Gates

After changing Adapter, run:

```bash
composer test:all
composer plugin-check:release
composer package:release
composer smoke:wp
git diff --check
```

After changing Core cross-reference docs, run:

```bash
composer test:all
git diff --check
```

Run `composer smoke:wp` in Core only when Core behavior changes. A docs-only
Core cross-reference does not need a Core WordPress smoke pass.

## Completion Criteria

OpenClaw consumer acceptance is complete when:

- health, help, capabilities, direct read, diagnostics read, proposal create,
  plan-to-proposal forwarding, proposal list/detail, disabled approve/reject
  stubs, unified approve-and-execute, split Core admin approval/rejection, and
  commit preflight are all verified through Adapter;
- Core audit and AI Request Logs can be correlated with `proposal_id` or
  `correlation_id`;
- Core Governance Audit remains the governance log, and WordPress `ai` plugin
  AI Request Logs remain the provider request log;
- AI Request Logs context records the explicit `ai_provider` and explicit
  `ai_model` sent to the provider smoke even if the provider column is blank;
- disabled Adapter approve/reject stubs return HTTP 403 and do not change Core
  proposal state;
- plan `write_actions` are executed only through the accepted allowlisted batch
  policy after Core approval and preflight;
- `skipped_destructive_candidates` are never reported as Adapter-executed
  mutations;
- `commit_execution=false` remains true at preflight;
- no new runtime ownership is added to Core, Adapter, or Abilities.
