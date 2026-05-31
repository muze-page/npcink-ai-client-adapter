# Magick AI Adapter

Magick AI Adapter is a thin OpenClaw channel plugin for WordPress.

It gives OpenClaw one WordPress REST namespace that can:

- read Magick AI Core capability guidance;
- run approved direct-read abilities through WordPress Abilities API;
- create Core proposals for write or destructive operations;
- orchestrate one user-triggered approve-and-execute action through Core.

OpenClaw only connects to Adapter. Magick AI Core is the governance service
behind Adapter. Core remains the approval, preflight, and audit truth source;
the productized OpenClaw user action is exposed by Adapter.

It does not define abilities, store approval state, run workflows, expose a
generic approve/reject proxy, or execute final write mutations without Core
approval and commit-preflight.

OpenClaw Adapter consumer readiness is complete as of Adapter commit
`4ad2a0c`. Productized OpenClaw should use Adapter as the only entry point.
See [OpenClaw Adapter Consumer Readiness](docs/openclaw-adapter-consumer-readiness.md)
for the dependency snapshot, verified routes, closed loop, and next-stage
execution allowlist rules.

Batch plan execution is intentionally narrow. Adapter can execute
`input.write_actions[]` only after Core approval and commit-preflight, and only
when every action targets the current execution allowlist
(`magick-ai/trash-post`). See
[OpenClaw Batch Execution Policy](docs/openclaw-batch-execution-policy.md).

## Runtime Boundary

Layer ownership:

| Layer | Plugin | Responsibility |
| --- | --- | --- |
| Ability layer | `magick-ai-abilities` | Registers canonical abilities, schemas, callbacks, permissions, and dry-run previews. |
| Governance layer | `magick-ai-core` | Discovers abilities, classifies risk, stores proposals, handles approval/preflight, and audits governance decisions. |
| Channel layer | `magick-ai-adapter` | Gives OpenClaw a small REST adapter that calls Core and WordPress Abilities API. |

## REST Surface

All routes require `manage_options` through normal WordPress REST
authentication, such as an administrator Application Password.

- `GET /wp-json/magick-ai-adapter/v1/health`
- `GET /wp-json/magick-ai-adapter/v1/help`
- `GET /wp-json/magick-ai-adapter/v1/capabilities`
- `POST /wp-json/magick-ai-adapter/v1/run-read-ability`
- `POST /wp-json/magick-ai-adapter/v1/ai-provider-log-correlation-smoke`
- `GET /wp-json/magick-ai-adapter/v1/site-info`
- `GET /wp-json/magick-ai-adapter/v1/site-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-diagnostics-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-ops-diagnostics-detail`
- `GET /wp-json/magick-ai-adapter/v1/active-plugins-detail`
- `GET /wp-json/magick-ai-adapter/v1/plugin-conflict-diagnostics`
- `GET /wp-json/magick-ai-adapter/v1/recent-error-log`
- `GET /wp-json/magick-ai-adapter/v1/recent-error-log-tail`
- `GET /wp-json/magick-ai-adapter/v1/current-user-permissions`
- `GET /wp-json/magick-ai-adapter/v1/php-extensions`
- `GET /wp-json/magick-ai-adapter/v1/object-cache-status`
- `GET /wp-json/magick-ai-adapter/v1/database-info`
- `GET /wp-json/magick-ai-adapter/v1/rewrite-rules-status`
- `GET /wp-json/magick-ai-adapter/v1/cron-events-detail`
- `GET /wp-json/magick-ai-adapter/v1/ssl-https-status`
- `GET /wp-json/magick-ai-adapter/v1/custom-post-types`
- `GET /wp-json/magick-ai-adapter/v1/roles-capabilities`
- `GET /wp-json/magick-ai-adapter/v1/widgets-sidebars`
- `GET /wp-json/magick-ai-adapter/v1/block-theme-assets`
- `GET /wp-json/magick-ai-adapter/v1/search-index-status`
- `GET /wp-json/magick-ai-adapter/v1/server-info`
- `GET /wp-json/magick-ai-adapter/v1/integrations-status`
- `GET /wp-json/magick-ai-adapter/v1/seo-summary`
- `GET /wp-json/magick-ai-adapter/v1/security-summary`
- `GET /wp-json/magick-ai-adapter/v1/performance-summary`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipes`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipe?recipe_id=workflow/...`
- `GET /wp-json/magick-ai-adapter/v1/posts`
- `GET /wp-json/magick-ai-adapter/v1/post-context`
- `GET /wp-json/magick-ai-adapter/v1/media`
- `GET /wp-json/magick-ai-adapter/v1/terms`
- `GET /wp-json/magick-ai-adapter/v1/taxonomy-terms`
- `GET /wp-json/magick-ai-adapter/v1/categories`
- `GET /wp-json/magick-ai-adapter/v1/tags`
- `GET /wp-json/magick-ai-adapter/v1/term?id={terms.result.items[].id}`
- `GET /wp-json/magick-ai-adapter/v1/comments`
- `GET /wp-json/magick-ai-adapter/v1/users`
- `GET /wp-json/magick-ai-adapter/v1/menu`
- `GET /wp-json/magick-ai-adapter/v1/internal-link-targets`
- `GET /wp-json/magick-ai-adapter/v1/post-stats`
- `GET /wp-json/magick-ai-adapter/v1/post-revisions`
- `GET /wp-json/magick-ai-adapter/v1/post-meta`
- `GET /wp-json/magick-ai-adapter/v1/pages`
- `GET /wp-json/magick-ai-adapter/v1/page`
- `GET /wp-json/magick-ai-adapter/v1/page-structure`
- `GET /wp-json/magick-ai-adapter/v1/pages-tree`
- `GET /wp-json/magick-ai-adapter/v1/content-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/content-inventory-fix-plan`
- `GET /wp-json/magick-ai-adapter/v1/test-content-cleanup-plan`
- `GET /wp-json/magick-ai-adapter/v1/site-operations-dashboard`
- `GET /wp-json/magick-ai-adapter/v1/publishing-calendar-context`
- `GET /wp-json/magick-ai-adapter/v1/media-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/media-inventory-fix-plan`
- `GET /wp-json/magick-ai-adapter/v1/taxonomy-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/proposals`
- `GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}`
- `POST /wp-json/magick-ai-adapter/v1/proposals`
- `POST /wp-json/magick-ai-adapter/v1/proposals/from-plan`
- `POST /wp-json/magick-ai-adapter/v1/execute-approved-proposal`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/execute`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/commit-preflight`

GET shortcut query parameters are forwarded as ability `input`. For example,
`/media?per_page=10&has_empty_alt=1` becomes read input for
`magick-ai/list-media`.

Diagnostics shortcuts are Adapter aliases over existing direct-read abilities
from `magick-ai-abilities`; Adapter does not collect these facts itself.
`wp-diagnostics-summary` is only a quick overview. P0/P1/P2 troubleshooting
detail shortcuts call `magick-ai-abilities/wp-ops-diagnostics-detail`.

Default diagnostics detail input is:

```json
{
  "include_log_contents": false,
  "include_active_plugins": true,
  "include_inactive_plugins": false,
  "include_plugin_updates": true,
  "include_must_use_plugins": true,
  "include_dropins": true,
  "max_plugins_per_group": 100
}
```

For deep plugin conflict troubleshooting, use
`GET /wp-json/magick-ai-adapter/v1/plugin-conflict-diagnostics` or send the
equivalent input:

```json
{
  "include_log_contents": false,
  "include_active_plugins": true,
  "include_inactive_plugins": true,
  "include_plugin_updates": true,
  "include_must_use_plugins": true,
  "include_dropins": true,
  "max_plugins_per_group": 200
}
```

When OpenClaw explicitly asks to inspect logs, use `recent-error-log-tail` or
send the equivalent input:

```json
{
  "include_log_contents": true,
  "tail_lines": 50,
  "severity": ["fatal", "error", "warning"],
  "since_minutes": 1440
}
```

When `include_log_contents=false`, log contents are not missing; mark them as
not explicitly requested. OpenClaw should use `error_log.summary` for
`fatal_count`, `error_count`, `warning_count`, `deprecated_count`,
`notice_count`, `summary_source`, and `error_log.summary.by_severity` even when
contents are not included. Only display `error_log.tail_entries` or `contents`
after the user explicitly asks for logs. Inactive plugin rows are not requested
by default; show them as default not requested, not missing. Plugin details are
grouped by `plugins.active`, `plugins.inactive`, `plugins.update_available`,
`plugins.must_use`, and `plugins.dropins`, with `plugins.groups_included` and
`plugins.max_plugins_per_group` describing which groups were requested. The
compatibility `contents` array is only redline text for display. The
diagnostics abilities own redaction and schema boundaries. Adapter does not
read arbitrary files, expose database names/table names, collect secrets,
invent diagnostics data, or mix Magick AI runtime, MCP, or cloud state into the
WordPress diagnostics mapping.

Content shortcuts pass query parameters through to the underlying ability input,
including the current `magick-ai/list-posts` filters, richer
`magick-ai/get-post-context` output, term sample-post flags, user
`author_profile`, comment post context, media `attached_to`/`usage`, and
`magick-ai/get-menu` tree output.

Reserved governance correlation query parameters are not forwarded as ability
input. Adapter copies `proposal_id`, `correlation_id`, `external_thread_id`,
`openclaw_thread_id`, `ability_id`, `adapter_request_id`, `adapter_route`,
`ai_provider`, `ai_model`, `governance_source=magick-ai-core`, and nested
`magick_ai_core.proposal_id` / `magick_ai_core.correlation_id` into AI Request
Logs context through the `wpai_request_log_context` filter while an ability or
bounded provider smoke request is running. POST `/run-read-ability` accepts
these values in a top-level `log_context` object. This lets AI Request Logs
execution rows correlate with Core proposal and commit-preflight audit records
without merging the two log systems.

Core Governance Audit is the governance log. WordPress `ai` plugin AI Request
Logs are the provider request log. Adapter carries identifiers between them but
does not put provider credentials, prompts, responses, token details, or AI
Request Logs into Core.
AI Request Logs are the provider request log.

## Magick AI Adapter UI

WordPress administrators can open:

```text
Magick AI -> Adapter
```

The page default view shows:

- Adapter base URL, health URL, help URL, and capabilities URL;
- Core and WordPress Abilities API connection status;
- a `Create OpenClaw handoff` action that creates a WordPress Application
  Password for the current administrator, shows it once in the browser, and
  emits a non-secret connection manifest;

Advanced disclosures keep lower-frequency reference details available without
turning the page into a control panel:

- supported read shortcut routes and their real `ability_id` values;
- flat `GET /help` route rows under `routes`, plus human-readable
  `route_groups`;
- Application Password secret-field steps;
- a non-secret connection manifest with `connection_id`, adapter URLs,
  username, auth type, and `password_uuid`;
- a copyable WorkBuddy setup block that reuses the same non-secret manifest and
  tells WorkBuddy where the secret must be stored;
- key-pair device pairing MVP endpoints and registered client key metadata;
- copyable health and proposal example requests;
- proposal list/detail, plan-to-proposal, commit-preflight, and
  approve-and-execute routes;
- a handoff prompt for OpenClaw.

The page does not save adapter credentials, approval state, ability definitions,
workflow state, or final write policy. The handoff action creates a normal
WordPress Application Password and displays the raw value once; WordPress stores
only its hash. Copied env, manifest, and handoff text contain only placeholders
or non-secret identifiers. Paste the Application Password only into OpenClaw's
dedicated secret field, not chat, tool commands, logs, proposal payloads, files,
or copied handoff text.

Public Key Device Pairing: for clients with a local broker, the Adapter REST surface supports a key-pair
device pairing MVP. The client generates an Ed25519 private key locally, sends
only the public key to WordPress for admin approval, and signs later Adapter
requests:

```text
GET  /wp-json/magick-ai-adapter/v1/connection/manifest
POST /wp-json/magick-ai-adapter/v1/connect/device/start
POST /wp-json/magick-ai-adapter/v1/connect/device/poll
GET  /wp-json/magick-ai-adapter/v1/connection/key-pairs
```

For local validation, run the development script in this repo:

```bash
node /Users/muze/gitee/magick-ai-adapter/tools/keypair-device-pairing.mjs --site=https://magick-ai.local --profile=local --insecure-local-tls
```

Open the printed WordPress approval URL, approve the public key, and the script
will save a local profile under `~/.magick-ai-adapter/keypair-profiles/` before
testing a signed `GET /health` request. The profile contains the local private
key; do not paste or log it. Production clients should store the private key in
the OS keychain or the client credential vault. The `--insecure-local-tls` flag
is for LocalWP or `.local` self-signed HTTPS only; do not use it for a public or
shared WordPress site.

See [`docs/keypair-device-pairing-contract.md`](docs/keypair-device-pairing-contract.md)
for the public-key pairing and request-signing contract.

When the current site URL is local (`localhost`, loopback, or `.local`), the
handoff form can include `MAGICK_AI_ADAPTER_INSECURE_SSL=true` in copied
OpenClaw client configuration. This only affects the generated client env text;
it does not change WordPress or Adapter server-side TLS behavior.

For local setup steps, see
[`docs/openclaw-quickstart.md`](docs/openclaw-quickstart.md).

For productized OpenClaw acceptance, use
[`docs/openclaw-consumer-acceptance.md`](docs/openclaw-consumer-acceptance.md).

For the admin page hierarchy and non-goals, use
[`docs/admin-surface-standard.md`](docs/admin-surface-standard.md).

The Cloud connector boundary and next implementation sequence are documented in
[`docs/cloud-connector-boundary.md`](docs/cloud-connector-boundary.md).

## OpenClaw Integration

Initial connection:

1. Create a dedicated WordPress administrator Application Password for the
   OpenClaw environment.
2. Give OpenClaw the non-secret connection manifest. Paste the Application
   Password only into OpenClaw's dedicated secret field or credential vault.
3. OpenClaw calls `GET /health` and verifies:
   - `core_capabilities=true`
   - `abilities_catalog=true`
   - `approval_proxy_enabled=false`
   - `approval_surface=magick_ai_core_admin`
   - `core_proxy_execute=false`
   - `commit_execution=false`
4. OpenClaw calls `GET /capabilities` and uses Core guidance as the only
   governance truth for each `ability_id`.
5. OpenClaw may call `GET /help` to discover adapter route labels and current
   non-goals.

Read-only execution:

1. Use a shortcut route when one exists, or call `POST /run-read-ability`.
2. The adapter re-checks Core for the real `ability_id`.
3. The adapter runs only rows where
   `governance_mode=direct_read` and `execution_surface=wp_abilities_rest`.
4. The adapter calls WordPress Abilities API and returns the result envelope.
5. Planning ability output is returned as plan data. `write_actions`,
   `preview`, `risk`, `manual_review`, and
   `skipped_destructive_candidates` are not execution results.

Plan-to-proposal flow:

1. OpenClaw runs one of the direct-read planning abilities:
   `magick-ai/build-content-inventory-fix-plan`,
   `magick-ai/build-test-content-cleanup-plan`, or
   `magick-ai/build-media-inventory-fix-plan`.
2. The adapter preserves plan fields including `batch_id`, `issue_types`,
   `post_ids`, `attachment_ids`, `write_actions`, `preview`, `risk`,
   `requires_approval`, `commit_execution`, `dry_run`, `manual_review`,
   `skipped_destructive_candidates`, `issue_counts`, and `action_count`.
3. OpenClaw calls `POST /proposals/from-plan` with `plan_ability_id`, `plan`,
   optional `plan_input`, and `caller` metadata.
4. The adapter forwards that payload to Core
   `POST /magick-ai-core/v1/proposals/from-plan` and preserves Core status.
   Adapter does not promote destructive candidates into executable actions.

Proposal-required write flow:

1. OpenClaw reads `/capabilities` and selects a real ability where Core reports
   `governance_mode=proposal_required`.
2. OpenClaw calls `POST /proposals` with the real `ability_id`, `input`,
   `preview`, and `caller` metadata.
3. Core stores the proposal and WordPress approval state.
4. OpenClaw polls `GET /proposals/{proposal_id}` through the adapter until Core
   returns an approved or rejected status. `GET /proposals?limit=...` is
   available for list views.
5. For the unified user path, OpenClaw calls
   `POST /proposals/{proposal_id}/approve-and-execute` so the user approves
   and executes from the Adapter/OpenClaw entry point. Core remains the
   governance backend for approval, commit-preflight, and audit.
6. If `status=rejected`, OpenClaw stops and shows the rejection state or reason
   returned by Core.
7. If using the lower-level split path and `status=approved`, OpenClaw calls
   `POST /proposals/{proposal_id}/commit-preflight` only after
   approval.
8. The adapter relays Core preflight and preserves `commit_execution=false`.
9. For the current approved proposal execution path, Adapter may execute only
   `magick-ai/trash-post` through
   `POST /proposals/{proposal_id}/execute` or
   `POST /execute-approved-proposal`.
10. Adapter fetches the Core proposal, calls Core commit-preflight, requires
    `approval_commit_authorized=true`, requires `commit_execution=false`, passes
    Core `approval_context` to WordPress Abilities API, and returns
    `proposal_id`, `correlation_id`, and `ability_id` with the ability result.
11. Adapter does not create its own governance state and does not batch silently
    execute destructive actions. The unified action only orchestrates Core
    approve -> commit-preflight -> one allowlisted WordPress Abilities API
    execution.

Proposal list/detail are read-only Core proxies. They preserve Core response
fields such as `proposal_id`, `ability_id`, `status`, `title`, `summary`,
`input`, `preview`, `caller`, `created_at`, `updated_at`, and detail
`audit_timeline` when Core returns it. Adapter may be configured with a Core app
token through `MAGICK_AI_ADAPTER_CORE_APP_TOKEN` or the
`magick_ai_adapter_core_app_token` option. When configured, Adapter sends that
token only on internal Core REST requests and does not print it. That key must
include `proposals:read` for proposal status, plus the other scopes needed by
the Core routes Adapter calls. The adapter must not print Core tokens in logs,
proposal payloads, error responses, or documentation examples.

When OpenClaw has a Core `proposal_id` or commit-preflight `correlation_id`, it
should pass those values to Adapter read or future execution requests as
`log_context` or query parameters. Adapter will include them under
`magick_ai_adapter`, top-level context fields, and nested `magick_ai_core`
context for provider request log correlation.

For local readiness smoke, administrators can call the provider log route with
a configured text generation provider/model. This example uses local Ollama
when `qwen3.5:0.8b` is available:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"proposal_id":"PROPOSAL_ID","correlation_id":"CORRELATION_ID","ability_id":"magick-ai/create-draft","ai_provider":"ollama","ai_model":"qwen3.5:0.8b","prompt":"Reply with exactly: OK"}' \
  "https://example.test/wp-json/magick-ai-adapter/v1/ai-provider-log-correlation-smoke"
```

If the AI Request Logs provider column is blank for a local connector, inspect
the Adapter context fields instead. They preserve the explicit `ai_provider`
and explicit `ai_model` sent to the smoke route.

`POST /proposals/{proposal_id}/approve` and
`POST /proposals/{proposal_id}/reject` are disabled stubs. They return HTTP 403
with `code=magick_ai_adapter_approval_proxy_disabled`,
`approval_proxy_enabled=false`, and
`approval_surface=magick_ai_core_admin`. For the Adapter/OpenClaw unified user
action, use `POST /proposals/{proposal_id}/approve-and-execute`; otherwise use
Magick AI Core admin for split approval decisions. The adapter does not forward
the standalone approve/reject stub routes to Core and does not require a
default Core key with approval or rejection scopes.

Example health request:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://example.test/wp-json/magick-ai-adapter/v1/health"
```

Example proposal request:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"ability_id":"magick-ai/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}' \
  "https://example.test/wp-json/magick-ai-adapter/v1/proposals"
```

Example proposal status request:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://example.test/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID"
```

## OpenClaw Flow

Read-only abilities:

1. OpenClaw calls Adapter `/capabilities`.
2. Adapter relays Core guidance.
3. If `governance_mode=direct_read`, OpenClaw calls Adapter read endpoints.
4. Adapter runs the ability through WordPress Abilities API.

Write or destructive abilities:

1. OpenClaw calls Adapter `/capabilities`.
2. If `governance_mode=proposal_required`, OpenClaw calls Adapter
   `/proposals`.
3. OpenClaw polls Adapter `/proposals/{proposal_id}` for Core status.
4. If pending and the user chooses the unified action, OpenClaw calls
   Adapter `/proposals/{proposal_id}/approve-and-execute`; Adapter calls Core
   approve, Core commit-preflight, then executes one allowlisted proposal.
5. If rejected, OpenClaw stops and shows the status. If approved, OpenClaw calls
   Adapter `/proposals/{proposal_id}/commit-preflight` after
   approval.
6. Adapter relays Core `commit_execution=false`.
7. For approved proposal execution, only `magick-ai/trash-post` is supported in
   this adapter. The execution input may be either a single `input.post_id` or
   a bounded `input.write_actions[]` batch where every action targets
   `magick-ai/trash-post`. OpenClaw calls `/proposals/{proposal_id}/execute`;
   Adapter performs Core preflight again, passes `approval_context`, and
   executes through WordPress Abilities API. New execution abilities must be
   added one by one to the Adapter allowlist with dedicated smoke coverage;
   this is not a generic proxy-execute surface.

## Non-Goals

This plugin must not become:

- an ability registry;
- an approval store;
- a workflow runtime;
- an MCP server;
- an Agent Gateway catalog;
- a generic final write executor;
- a replacement for WordPress Abilities API.

## Development

Run static checks:

```bash
composer test:all
```

Run Plugin Check against the release/package surface:

```bash
composer plugin-check:release
```

Build a release zip with the same package boundary:

```bash
composer package:release
```

The release surface is defined by `.distignore`. It excludes development-only
artifacts such as `tests/`, `AGENTS.md`, `.gitignore`, Composer metadata, and
local dependency folders from package-oriented Plugin Check runs. Do not delete
those files from the source tree just to satisfy a full-worktree PCP scan.

Run the LocalWP smoke test:

```bash
composer smoke:wp
```
