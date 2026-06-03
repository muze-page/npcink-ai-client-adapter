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
approval, commit-preflight, and an explicit Adapter execution profile.

When Core or Adapter blocks a plan handoff, rejected proposal, or preflighted
execution, error responses may include `data.operator_feedback`. OpenClaw should
display that object to the operator and create a revised new proposal instead
of retrying execution against the blocked proposal id. Core remains the
governance truth.

OpenClaw Adapter consumer readiness is complete as of Adapter governance commit
`b81dc2a`. Productized OpenClaw should use Adapter as the only entry point.
See [OpenClaw Adapter Consumer Readiness](docs/openclaw-adapter-consumer-readiness.md)
for the dependency snapshot, verified routes, closed loop, and next-stage
execution allowlist rules.

Batch plan execution is intentionally narrow. Adapter can execute
`input.write_actions[]` only after Core approval and commit-preflight, and only
when every action targets the current execution allowlist
(`magick-ai/trash-post`, `magick-ai/create-draft`,
`magick-ai/update-post`, `magick-ai/set-post-seo-meta`,
`magick-ai/set-post-slug`, `magick-ai/set-post-terms`,
`magick-ai/delete-term`, `magick-ai/patch-post-content`,
`magick-ai/patch-setting-value`,
`magick-ai/update-media-details`,
`magick-ai/optimize-media-asset`,
`magick-ai/replace-media-file`,
`magick-ai/adopt-cloud-media-derivative`,
`magick-ai/delete-media-permanently`,
`magick-ai/reply-comment`, `magick-ai/trash-comment`,
`magick-ai/approve-comment`). See
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
- `POST /wp-json/magick-ai-adapter/v1/media-metadata-optimization`
- `POST /wp-json/magick-ai-adapter/v1/media-derivative-runs`
- `GET /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}`
- `GET /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}/result`
- `GET /wp-json/magick-ai-adapter/v1/media-derivative-artifacts/{artifact_id}/preview`
- `POST /wp-json/magick-ai-adapter/v1/media-derivative-proposal-payload`
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
- `GET /wp-json/magick-ai-adapter/v1/content-discoverability-context`
- `GET /wp-json/magick-ai-adapter/v1/content-discoverability-validation`
- `GET /wp-json/magick-ai-adapter/v1/content-discoverability-brief`
- `GET /wp-json/magick-ai-adapter/v1/article-writing-pack`
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
detail shortcuts call `npcink-abilities-toolkit/wp-ops-diagnostics-detail`.

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

For media metadata optimization, `POST /media-metadata-optimization` calls the
read-only `magick-ai/optimize-media-metadata` ability and returns suggestions
for attachment title, alt, caption, description, `source_type`, source,
photographer, attribution, and copyright fields. It does not write media
records or replace files. To apply reviewed suggestions, create a governed
Core proposal for `magick-ai/update-media-details`, then execute through
Adapter's existing Core-approved allowlisted path.

For media format attention, `run-read-ability` may call
`magick-ai/inspect-media-asset` directly, and the shortcut key
`media-asset-inspection` maps to the same read-only ability. The response
contains file size, dimensions, target format, compression, resize, and
derivative recommendations only.

For Cloud-generated media derivatives, use `POST /media-derivative-runs`.
Adapter builds the local read-only
`magick-ai/build-media-derivative-cloud-request` contract, uses Core media
policy defaults when available, supplies the local source attachment file or a
caller-provided short-TTL artifact reference, and dispatches only through
`magick-ai-cloud-addon`. The route returns a Cloud run projection plus the
ability response. Poll `GET /media-derivative-runs/{run_id}` and
`GET /media-derivative-runs/{run_id}/result`. The result projection may include
a same-origin `preview_url`; browser clients can load it through
`GET /media-derivative-artifacts/{artifact_id}/preview` with WordPress REST
auth or the short-lived local `preview_sig` emitted in the URL. Then call
`POST /media-derivative-proposal-payload` with the ability response, Cloud
result, and derivative artifact. That payload is Core-ready but not submitted,
approved, or executed by Adapter. See
[OpenClaw Media Derivative Cloud Recipe](docs/openclaw-media-derivative-cloud-recipe.md)
and [AI Media Derivative Calling Guide](docs/ai-media-derivative-calling-guide.md).
Third-party AI callers should use the Adapter recipe routes above instead of
calling Cloud directly.

Adapter does not create a media registry, artifact registry, Cloud settings
surface, approval truth, attachment metadata update, or file replacement. To
adopt a Cloud derivative artifact as the attachment main file, create a
governed Core proposal for `magick-ai/adopt-cloud-media-derivative`, then
execute only through Adapter's Core-approved allowlisted path. To switch the
attachment main file to an already recorded local derivative, create a governed
Core proposal for `magick-ai/replace-media-file`; both write abilities record
backup and rollback metadata. Adapter does not accept arbitrary replacement
URLs or replace files outside Core-approved execution.

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
- a `Local OpenClaw CLI` section that copies the unified CLI commands and lets
  administrators revoke authorized key-pair public keys;
- a `Proposal status` lookup where operators paste the `Proposal ID` returned
  to OpenClaw, see Core status through Adapter's read-only proposal proxy, open
  the matching Core approval detail, and copy Adapter status/execution URLs.

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
- key-pair device pairing MVP endpoints and local CLI reference details;
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

For local validation, use the unified local CLI in this repo:

```bash
node /Users/muze/gitee/magick-ai-adapter/tools/magick-adapter.mjs connect --site=https://magick-ai.local --profile=local --insecure-local-tls
node /Users/muze/gitee/magick-ai-adapter/tools/magick-adapter.mjs status --profile=local --insecure-local-tls
```

The script opens the WordPress approval URL in the system browser. Approve the
public key, and the script will save a local profile under
`~/.magick-ai-adapter/keypair-profiles/` before testing a signed `GET /health`
request. Use `--no-open` if you want to print the URL without opening a browser.
The profile contains the local private key; do not paste or log it. Production
clients should store the private key in the OS keychain or the client credential
vault. The `--insecure-local-tls` flag is for LocalWP or `.local` self-signed
HTTPS only; do not use it for a public or shared WordPress site. Transient local
HTTPS polling resets are retried until the pairing code expires.
After approval, WordPress shows a pairing result page; return to the terminal or
local AI client and wait for polling to finish.

After pairing, local clients can call Adapter through the signed request command
without reading or printing profile secrets:

```bash
node /Users/muze/gitee/magick-ai-adapter/tools/magick-adapter.mjs request --profile=local --insecure-local-tls GET /health
node /Users/muze/gitee/magick-ai-adapter/tools/magick-adapter.mjs request --profile=local --insecure-local-tls GET /capabilities
node /Users/muze/gitee/magick-ai-adapter/tools/magick-adapter.mjs request --profile=local --insecure-local-tls POST /proposals/from-plan --body-file=/tmp/magick-proposal.json
```

The request command accepts only Adapter-relative routes such as `/health`,
signs the request locally, and prints only the Adapter JSON response. It also
accepts `--body-stdin` for non-secret POST bodies.
The lower-level development scripts remain available as
`tools/keypair-device-pairing.mjs` and `tools/keypair-adapter-request.mjs`, but
the preferred local client entrypoint is `tools/magick-adapter.mjs`.

See [`docs/keypair-device-pairing-contract.md`](docs/keypair-device-pairing-contract.md)
for the public-key pairing and request-signing contract.

When the current site URL is local (`localhost`, loopback, or `.local`), the
handoff form can include `MAGICK_AI_ADAPTER_INSECURE_SSL=true` in copied
OpenClaw client configuration. This only affects the generated client env text;
it does not change WordPress or Adapter server-side TLS behavior.

For local setup steps, see
[`docs/openclaw-quickstart.md`](docs/openclaw-quickstart.md).

For the OpenClaw article draft planning recipe, use
[`docs/openclaw-article-draft-plan-recipe.md`](docs/openclaw-article-draft-plan-recipe.md).

For the OpenClaw SEO/AEO/GEO suggestion recipe, use
[`docs/openclaw-content-discoverability-recipe.md`](docs/openclaw-content-discoverability-recipe.md).
The primary SEO/GEO/AEO entrypoint is
`GET /content-discoverability-brief` or
`magick-ai-toolbox/build-content-discoverability-brief`.
Use `article-writing-pack` only for broad natural-language requests such as
"help me write an article".

For broad natural-language article requests, use
[`docs/openclaw-ai-article-writing-pack-recipe.md`](docs/openclaw-ai-article-writing-pack-recipe.md).
The shortcut `GET /article-writing-pack` forwards input to
`magick-ai-toolbox/build-ai-article-writing-pack` and returns a
suggestion-only writing pack for OpenClaw drafting.

For productized OpenClaw acceptance, use
[`docs/openclaw-consumer-acceptance.md`](docs/openclaw-consumer-acceptance.md).

For the admin page hierarchy and non-goals, use
[`docs/admin-surface-standard.md`](docs/admin-surface-standard.md).

Cloud runtime access belongs to the standalone `magick-ai-cloud-addon`.
Adapter must not add its own Cloud settings, signing client, or `/cloud/*`
routes. If an OpenClaw flow needs hosted runtime, Adapter should call the Cloud
Addon public PHP seam described in
[`docs/cloud-connector-boundary.md`](docs/cloud-connector-boundary.md).

## Observability

Adapter emits local metadata-only events through
`magick_ai_observability_event`; the Cloud Addon is the only uploader. Current
canonical Adapter event kinds are `adapter.core.request`,
`adapter.proposal.create`, `adapter.proposal.plan_ingest`,
`adapter.commit.preflight`, `adapter.proposal.execute`,
`adapter.openclaw.dispatch.completed`, and
`adapter.openclaw.dispatch.failed`.

Events may include method, route, status code, latency, stable error code, and
safe ids such as `proposal_id`, `correlation_id`, `ability_id`, or
`adapter_request_id`. They must not include raw OpenClaw requests, raw
responses, proposal input, write input, prompts, generated content, auth
headers, tokens, or secrets.

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
5. OpenClaw may call `GET /help` to discover adapter route labels, current
   non-goals, and `openclaw_recipes.article_draft_plan`.

Read-only execution:

1. Use a shortcut route when one exists, or call `POST /run-read-ability`.
2. The adapter re-checks Core for the real `ability_id`.
3. The adapter runs only rows where
   `governance_mode=direct_read` and `execution_surface=wp_abilities_rest`.
4. The adapter calls WordPress Abilities API and returns a read envelope with
   `read_policy`, `sensitivity`, `redaction_required`, `redaction_applied`,
   `redaction_summary`, `read_audit_mode`, `correlation_id`, `read_context`,
   and `commit_execution=false`.
5. If Core marks the row `direct_read_sensitive` or
   `redaction_required=true`, Adapter applies bounded read-result redaction
   before returning `result`.
6. Planning ability output is returned as plan data. `write_actions`,
   `preview`, `risk`, `manual_review`, and
   `skipped_destructive_candidates` are not execution results.

Plan-to-proposal flow:

1. OpenClaw runs one of the direct-read planning abilities:
   `magick-ai/build-content-inventory-fix-plan`,
   `magick-ai/build-test-content-cleanup-plan`,
   `magick-ai/build-media-inventory-fix-plan`,
   `magick-ai/build-media-reference-repair-plan`, or
   `magick-ai/build-media-settings-reference-repair-plan`, or
   `magick-ai-toolbox/build-article-write-plan`.
2. The adapter preserves plan fields including `batch_id`, `issue_types`,
   `post_ids`, `attachment_ids`, `write_actions`, `preview`, `risk`,
   `requires_approval`, `commit_execution`, `dry_run`, `manual_review`,
   `skipped_destructive_candidates`, `issue_counts`, and `action_count`.
3. OpenClaw calls `POST /proposals/from-plan` with `plan_ability_id`, `plan`,
   optional `plan_input`, and `caller` metadata.
4. The adapter forwards that payload to Core
   `POST /magick-ai-core/v1/proposals/from-plan` and preserves Core status.
   Adapter does not promote destructive candidates into executable actions.
   For the Toolbox article write plan, Adapter still only forwards the
   reviewed `article_write_plan`; Core validates the plan and Adapter later
   executes `magick-ai/create-draft` only after Core approval and
   commit-preflight. The machine-readable OpenClaw playbook is exposed as
   `openclaw_recipes.article_draft_plan` from `GET /help`.

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
   `magick-ai/trash-post`, `magick-ai/create-draft`,
   `magick-ai/update-post`, `magick-ai/set-post-seo-meta`,
	   `magick-ai/set-post-slug`, `magick-ai/set-post-terms`,
	   `magick-ai/delete-term`, `magick-ai/patch-post-content`,
	   `magick-ai/patch-setting-value`,
	   `magick-ai/update-media-details`,
	   `magick-ai/optimize-media-asset`,
	   `magick-ai/replace-media-file`,
	   `magick-ai/adopt-cloud-media-derivative`,
	   `magick-ai/delete-media-permanently`, `magick-ai/reply-comment`,
   `magick-ai/trash-comment`, or `magick-ai/approve-comment` through
   `POST /proposals/{proposal_id}/execute` or
   `POST /execute-approved-proposal`.
10. Adapter fetches the Core proposal, calls Core commit-preflight, requires
    `approval_commit_authorized=true`, requires `commit_execution=false`, passes
    Core `approval_context` to WordPress Abilities API, stores a bounded
    execution record, and returns `proposal_id`, `correlation_id`,
    `ability_id`, and `execution_record` with the ability result. Repeating the
    same proposal execution returns
    `magick_ai_adapter_execution_already_completed` and does not run the
    ability again.
11. Adapter does not create its own proposal or approval state and does not
    batch silently execute destructive actions. The unified action only
    orchestrates Core approve -> commit-preflight -> one allowlisted WordPress
    Abilities API execution.

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

For local readiness smoke, administrators can manually call the provider log
route with a configured text generation provider/model. This route is a
diagnostic-only operability surface for AI Request Logs correlation. It must not
be used as model routing, prompt management, product UX, or production workload
execution. The prompt is used only for the bounded smoke request and is not
stored by Adapter.

This example uses local Ollama when `qwen3.5:0.8b` is available:

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
  -d '{"ability_id":"magick-ai/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"title":"OpenClaw draft","dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}' \
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
7. For approved proposal execution, only `magick-ai/trash-post`,
   `magick-ai/create-draft`, `magick-ai/update-post`,
   `magick-ai/patch-post-content`, `magick-ai/patch-setting-value`,
   `magick-ai/set-post-seo-meta`, `magick-ai/set-post-slug`,
	   `magick-ai/set-post-terms`, `magick-ai/delete-term`,
	   `magick-ai/update-media-details`, `magick-ai/optimize-media-asset`,
	   `magick-ai/replace-media-file`,
	   `magick-ai/adopt-cloud-media-derivative`,
	   `magick-ai/delete-media-permanently`,
   `magick-ai/reply-comment`, `magick-ai/trash-comment`, and
   `magick-ai/approve-comment` are supported in
   this adapter. The execution input may be a single allowlisted proposal input or a bounded
   `input.write_actions[]` batch where every action targets the allowlist.
   OpenClaw calls `/proposals/{proposal_id}/execute`; Adapter performs Core
   preflight again, passes `approval_context`, and executes through WordPress
   Abilities API. New execution abilities must be added as explicit Adapter
   execution profile entries with dedicated smoke coverage; this is not a
   generic proxy-execute surface.

Adapter derives the execution allowlist from its local execution profile registry.
Capability discovery may show more proposal-required abilities, but
only abilities with an Adapter execution profile can run final writes.
For profiled abilities, Adapter also validates proposal input at
`POST /proposals`, rejecting undeclared fields and invalid enum values before
the proposal is sent to Core. The same Adapter-owned input schema check also
runs for profiled `plan.write_actions[]` during `POST /proposals/from-plan`
before Adapter forwards the plan to Core; invalid actions return
`magick_ai_adapter_plan_action_input_invalid` with `blocked_items[]` carrying
the action index, action id, target ability id, blocked field, and reused
single-proposal block code. Exact `$outputs.<prior_action_id>.<field>`
references are allowed in profiled plan action input only when they point to an
earlier action in the same plan; Adapter revalidates the resolved value during
approved batch execution. Embedded `$outputs.` tokens are rejected, and plan
action ids must be unique before Adapter forwards the plan to Core.
Within one approved `write_actions[]` batch, later actions may reference earlier
action outputs with exact values such as `$outputs.create-draft.post_id`.
Adapter resolves those references in memory during that batch only, then
revalidates the resolved action input before execution.

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
