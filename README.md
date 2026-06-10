# Npcink OpenClaw Adapter

Npcink OpenClaw Adapter is a thin OpenClaw channel plugin for WordPress.

It gives OpenClaw one WordPress REST namespace that can:

- read Npcink Governance Core capability guidance;
- run approved direct-read abilities through WordPress Abilities API;
- create Core proposals for write or destructive operations;
- orchestrate one user-triggered approve-and-execute action through Core.

OpenClaw only connects to Adapter. Npcink Governance Core is the governance service
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
(`npcink-abilities-toolkit/trash-post`, `npcink-abilities-toolkit/create-draft`,
`npcink-abilities-toolkit/update-post`, `npcink-abilities-toolkit/set-post-seo-meta`,
`npcink-abilities-toolkit/set-post-slug`, `npcink-abilities-toolkit/set-post-terms`,
`npcink-abilities-toolkit/delete-term`, `npcink-abilities-toolkit/patch-post-content`,
`npcink-abilities-toolkit/update-post-blocks`,
`npcink-abilities-toolkit/patch-setting-value`,
`npcink-abilities-toolkit/update-media-details`,
`npcink-abilities-toolkit/optimize-media-asset`,
`npcink-abilities-toolkit/replace-media-file`,
`npcink-abilities-toolkit/restore-media-backup`,
`npcink-abilities-toolkit/adopt-cloud-media-derivative`,
`npcink-abilities-toolkit/rename-media-file`,
`npcink-abilities-toolkit/delete-media-permanently`,
`npcink-abilities-toolkit/reply-comment`, `npcink-abilities-toolkit/trash-comment`,
`npcink-abilities-toolkit/approve-comment`). See
[OpenClaw Batch Execution Policy](docs/openclaw-batch-execution-policy.md).

## Runtime Boundary

Layer ownership:

| Layer | Plugin | Responsibility |
| --- | --- | --- |
| Ability layer | `npcink-abilities-toolkit` | Registers canonical abilities, schemas, callbacks, permissions, and dry-run previews. |
| Governance layer | `npcink-governance-core` | Discovers abilities, classifies risk, stores proposals, handles approval/preflight, and audits governance decisions. |
| Channel layer | `npcink-openclaw-adapter` | Gives OpenClaw a small REST adapter that calls Core and WordPress Abilities API. |

## Suite Distribution

Npcink OpenClaw Adapter is the productized entry plugin for the Npcink AI suite,
but distribution does not merge runtime ownership. The suite package ships
Adapter, Core, and the Abilities Toolkit as separate WordPress plugin zips and
keeps their REST namespaces, plugin headers, data stores, and tests separate.

The current Adapter plugin header declares `Requires Plugins:
npcink-abilities-toolkit` because `npcink-abilities-toolkit` is the confirmed
WordPress.org dependency slug. Core and Adapter slugs are still treated as
distribution contract values until their public slugs are finalized; runtime
readiness is detected through REST routes and public functions instead of
display names.

Adapter `/health` and `/help` remain available when dependencies are missing.
Routes that require Core or WordPress Abilities API fail closed with
`npcink_openclaw_adapter_missing_dependency`. See
[Npcink AI Suite Distribution Contract](docs/distribution-contract.md).

## REST Surface

All routes require `manage_options` through normal WordPress REST
authentication, such as an administrator Application Password.

- `GET /wp-json/npcink-openclaw-adapter/v1/health`
- `GET /wp-json/npcink-openclaw-adapter/v1/help`
- `GET /wp-json/npcink-openclaw-adapter/v1/capabilities`
- `GET /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}`
- `POST /wp-json/npcink-openclaw-adapter/v1/run-read-ability`
- `POST /wp-json/npcink-openclaw-adapter/v1/read-requests`
- `GET /wp-json/npcink-openclaw-adapter/v1/read-requests`
- `GET /wp-json/npcink-openclaw-adapter/v1/read-requests/{request_id}`
- `POST /wp-json/npcink-openclaw-adapter/v1/media-metadata-optimization`
- `POST /wp-json/npcink-openclaw-adapter/v1/media-derivative-runs`
- `GET /wp-json/npcink-openclaw-adapter/v1/media-derivative-runs/{run_id}`
- `GET /wp-json/npcink-openclaw-adapter/v1/media-derivative-runs/{run_id}/result`
- `GET /wp-json/npcink-openclaw-adapter/v1/media-derivative-artifacts/{artifact_id}/preview`
- `POST /wp-json/npcink-openclaw-adapter/v1/media-derivative-proposal-payload`
- `POST /wp-json/npcink-openclaw-adapter/v1/ai-provider-log-correlation-smoke`
- `GET /wp-json/npcink-openclaw-adapter/v1/site-info`
- `GET /wp-json/npcink-openclaw-adapter/v1/site-summary`
- `GET /wp-json/npcink-openclaw-adapter/v1/wp-diagnostics-summary`
- `GET /wp-json/npcink-openclaw-adapter/v1/wp-ops-diagnostics-detail`
- `GET /wp-json/npcink-openclaw-adapter/v1/active-plugins-detail`
- `GET /wp-json/npcink-openclaw-adapter/v1/plugin-conflict-diagnostics`
- `GET /wp-json/npcink-openclaw-adapter/v1/recent-error-log`
- `GET /wp-json/npcink-openclaw-adapter/v1/recent-error-log-tail`
- `GET /wp-json/npcink-openclaw-adapter/v1/current-user-permissions`
- `GET /wp-json/npcink-openclaw-adapter/v1/php-extensions`
- `GET /wp-json/npcink-openclaw-adapter/v1/object-cache-status`
- `GET /wp-json/npcink-openclaw-adapter/v1/database-info`
- `GET /wp-json/npcink-openclaw-adapter/v1/rewrite-rules-status`
- `GET /wp-json/npcink-openclaw-adapter/v1/cron-events-detail`
- `GET /wp-json/npcink-openclaw-adapter/v1/ssl-https-status`
- `GET /wp-json/npcink-openclaw-adapter/v1/custom-post-types`
- `GET /wp-json/npcink-openclaw-adapter/v1/roles-capabilities`
- `GET /wp-json/npcink-openclaw-adapter/v1/widgets-sidebars`
- `GET /wp-json/npcink-openclaw-adapter/v1/block-theme-assets`
- `GET /wp-json/npcink-openclaw-adapter/v1/search-index-status`
- `GET /wp-json/npcink-openclaw-adapter/v1/server-info`
- `GET /wp-json/npcink-openclaw-adapter/v1/integrations-status`
- `GET /wp-json/npcink-openclaw-adapter/v1/seo-summary`
- `GET /wp-json/npcink-openclaw-adapter/v1/security-summary`
- `GET /wp-json/npcink-openclaw-adapter/v1/performance-summary`
- `GET /wp-json/npcink-openclaw-adapter/v1/workflow-recipes`
- `GET /wp-json/npcink-openclaw-adapter/v1/workflow-recipe?recipe_id=workflow/...`
- `GET /wp-json/npcink-openclaw-adapter/v1/posts`
- `GET /wp-json/npcink-openclaw-adapter/v1/post-context`
- `GET /wp-json/npcink-openclaw-adapter/v1/media`
- `GET /wp-json/npcink-openclaw-adapter/v1/media-attachment-by-url?url={uploads_url}`
- `GET /wp-json/npcink-openclaw-adapter/v1/terms`
- `GET /wp-json/npcink-openclaw-adapter/v1/taxonomy-terms`
- `GET /wp-json/npcink-openclaw-adapter/v1/categories`
- `GET /wp-json/npcink-openclaw-adapter/v1/tags`
- `GET /wp-json/npcink-openclaw-adapter/v1/term?id={terms.result.items[].id}`
- `GET /wp-json/npcink-openclaw-adapter/v1/comments`
- `GET /wp-json/npcink-openclaw-adapter/v1/users`
- `GET /wp-json/npcink-openclaw-adapter/v1/menu`
- `GET /wp-json/npcink-openclaw-adapter/v1/internal-link-targets`
- `GET /wp-json/npcink-openclaw-adapter/v1/post-stats`
- `GET /wp-json/npcink-openclaw-adapter/v1/post-revisions`
- `GET /wp-json/npcink-openclaw-adapter/v1/post-meta`
- `GET /wp-json/npcink-openclaw-adapter/v1/post-blocks`
- `GET /wp-json/npcink-openclaw-adapter/v1/pages`
- `GET /wp-json/npcink-openclaw-adapter/v1/page`
- `GET /wp-json/npcink-openclaw-adapter/v1/page-structure`
- `GET /wp-json/npcink-openclaw-adapter/v1/pages-tree`
- `GET /wp-json/npcink-openclaw-adapter/v1/content-inventory-health`
- `GET /wp-json/npcink-openclaw-adapter/v1/content-inventory-fix-plan`
- `GET /wp-json/npcink-openclaw-adapter/v1/nonproduction-content-cleanup-plan`
- `GET /wp-json/npcink-openclaw-adapter/v1/content-discoverability-context`
- `GET /wp-json/npcink-openclaw-adapter/v1/content-discoverability-validation`
- `GET /wp-json/npcink-openclaw-adapter/v1/content-discoverability-brief`
- `GET /wp-json/npcink-openclaw-adapter/v1/article-writing-pack`
- `GET /wp-json/npcink-openclaw-adapter/v1/site-operations-dashboard`
- `GET /wp-json/npcink-openclaw-adapter/v1/publishing-calendar-context`
- `GET /wp-json/npcink-openclaw-adapter/v1/media-inventory-health`
- `GET /wp-json/npcink-openclaw-adapter/v1/media-inventory-fix-plan`
- `GET /wp-json/npcink-openclaw-adapter/v1/taxonomy-inventory-health`
- `GET /wp-json/npcink-openclaw-adapter/v1/proposals`
- `GET /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals/from-plan`
- `POST /wp-json/npcink-openclaw-adapter/v1/execute-approved-proposal`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/execute`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/approve-and-execute`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/approve`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/reject`
- `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/commit-preflight`

GET shortcut query parameters are forwarded as ability `input`. For example,
`/media?per_page=10&has_empty_alt=1` becomes read input for
`npcink-abilities-toolkit/list-media`.

Diagnostics shortcuts are Adapter aliases over existing direct-read abilities
from `npcink-abilities-toolkit`; Adapter does not collect these facts itself.
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
`GET /wp-json/npcink-openclaw-adapter/v1/plugin-conflict-diagnostics` or send the
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
invent diagnostics data, or mix Npcink runtime, MCP, or cloud state into the
WordPress diagnostics mapping.

Content shortcuts pass query parameters through to the underlying ability input,
including the current `npcink-abilities-toolkit/list-posts` filters, richer
`npcink-abilities-toolkit/get-post-context` output, term sample-post flags, user
`author_profile`, comment post context, media `attached_to`/`usage`, and
`npcink-abilities-toolkit/get-menu` tree output.

For media metadata optimization, `POST /media-metadata-optimization` calls the
read-only `npcink-abilities-toolkit/optimize-media-metadata` ability and returns suggestions
for attachment title, alt, caption, description, `source_type`, source,
photographer, attribution, and copyright fields. It does not write media
records or replace files. To apply reviewed suggestions, create a governed
Core proposal for `npcink-abilities-toolkit/update-media-details`, then execute through
Adapter's existing Core-approved allowlisted path.

For media format attention, `run-read-ability` may call
`npcink-abilities-toolkit/inspect-media-asset` directly, and the shortcut key
`media-asset-inspection` maps to the same read-only ability. The response
contains file size, dimensions, target format, compression, resize, and
derivative recommendations only.

For a hard-coded local uploads URL, `GET /media-attachment-by-url?url={url}`
maps to `npcink-abilities-toolkit/resolve-media-attachment-by-url`. It returns bounded
read-only attachment candidates and match evidence so the caller can continue
through preview, Core proposal, approval, preflight, and execution without using
database, WP-CLI, or filesystem lookup.

For Cloud-generated media derivatives, use `POST /media-derivative-runs`.
Adapter builds the local read-only
`npcink-abilities-toolkit/build-media-derivative-cloud-request` contract, uses Core media
policy defaults when available, supplies the local source attachment file or a
caller-provided short-TTL artifact reference, and dispatches only through
`npcink-cloud-addon`. Image watermark plans may supply a watermark artifact
or use the local Core watermark attachment; text watermark plans are dispatched
as structured text options without a watermark artifact. The route returns a
Cloud run projection plus the ability response. Poll
`GET /media-derivative-runs/{run_id}` and
`GET /media-derivative-runs/{run_id}/result`. The result projection may include
a same-origin `preview_url`; browser clients can load it through
`GET /media-derivative-artifacts/{artifact_id}/preview` with WordPress REST
auth or the short-lived local `preview_sig` emitted in the URL. Then call
`POST /media-derivative-proposal-payload` with the ability response, Cloud
result, derivative artifact, and reviewed `media_details_input` when the user
intent is full image optimization. Adapter returns a legacy single derivative
`proposal_payload` plus a `from_plan_request` for
`npcink-abilities-toolkit/build-media-optimization-plan`; submit that request to
`POST /proposals/from-plan` so Core creates one batch proposal containing
`npcink-abilities-toolkit/update-media-details` and
`npcink-abilities-toolkit/adopt-cloud-media-derivative`. The payload is Core-ready but not
submitted, approved, or executed by Adapter. If `media_details_input` is
missing, stop and collect reviewed metadata before creating a Core proposal; do
not create a derivative-only proposal for the same optimize-image intent. If Core reports the media
optimization plan ability is unavailable, surface that version/capability guard
and update the local Abilities/Core stack; do not split the same optimize-image
intent into separate metadata and derivative proposals. See
[OpenClaw Media Derivative Cloud Recipe](docs/openclaw-media-derivative-cloud-recipe.md)
and [AI Media Derivative Calling Guide](docs/ai-media-derivative-calling-guide.md).
Third-party AI callers should use the Adapter recipe routes above instead of
calling Cloud directly.

Adapter does not create a media registry, artifact registry, Cloud settings
surface, approval truth, attachment metadata update, or file replacement. To
adopt a Cloud derivative artifact as the attachment main file, create a
governed Core proposal for `npcink-abilities-toolkit/adopt-cloud-media-derivative`, then
execute only through Adapter's Core-approved allowlisted path. To switch the
attachment main file to an already recorded local derivative, create a governed
Core proposal for `npcink-abilities-toolkit/replace-media-file`; both write abilities record
backup metadata for later restore. To roll back a recorded media replacement,
create a governed Core proposal for `npcink-abilities-toolkit/restore-media-backup`
with `attachment_id` and `backup_id`. Adapter does not accept arbitrary
replacement URLs or replace files outside Core-approved execution.

`GET /proposals/{proposal_id}` proxies the Core proposal detail and appends
Adapter-owned derived fields: `adapter_status`, `execution_status`,
`effective_status`, `executable`, `non_executable_reason`, `preflight_status`,
`review_summary`, and, for media optimization batches,
`media_optimization_readiness`. `status` remains the raw Core governance
status, while `effective_status` folds Adapter execution evidence into values
such as `executed` without changing Core's approval truth. The same media
readiness object is available through
`GET /proposals/{proposal_id}/media-optimization-readiness`. The readiness
checks are diagnostic only and do not download Cloud artifacts; they report
local helper availability, artifact presence/expiry, Adapter execution-profile
validation, and whether post-content reference scan evidence is present. For
older proposal previews, Adapter derives actual replacement counts from
`patch_preview[].applied` when the newer
`actual_replacement_count`/`unmatched_rules` fields are absent.

Reserved governance correlation query parameters are not forwarded as ability
input. Adapter copies `proposal_id`, `correlation_id`, `external_thread_id`,
`openclaw_thread_id`, `ability_id`, `adapter_request_id`, `adapter_route`,
`ai_provider`, `ai_model`, `governance_source=npcink-governance-core`, and nested
`npcink_governance_core.proposal_id` / `npcink_governance_core.correlation_id` into AI Request
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

## Npcink OpenClaw Adapter UI

WordPress administrators can open:

```text
Npcink -> Adapter
```

The page default view shows:

- a simple Application Password connection for clients with a dedicated
  password, credential, or secret field;
- Adapter base URL and non-secret connection manifest URL;
- Core and WordPress Abilities API connection status;
- a higher-security signed key-pair flow using `cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter`;
- authorized public key management with revoke actions;
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
GET  /wp-json/npcink-openclaw-adapter/v1/connection/manifest
POST /wp-json/npcink-openclaw-adapter/v1/connect/device/start
POST /wp-json/npcink-openclaw-adapter/v1/connect/device/poll
GET  /wp-json/npcink-openclaw-adapter/v1/connection/key-pairs
```

For local validation, use the npm CLI on the same machine or execution
environment as OpenClaw:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter connect --site=https://magick-ai.local --profile=local --insecure-local-tls
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter status --profile=local --insecure-local-tls
```

The script opens the WordPress approval URL in the system browser. Approve the
public key, and the script will save a local profile under
`~/.npcink-openclaw-adapter/keypair-profiles/` before testing a signed `GET /health`
request. Use `--no-open` if you want to print the URL without opening a browser.
The profile contains the local private key; do not paste or log it. Production
clients should store the private key in the OS keychain or the client credential
vault. The `--insecure-local-tls` flag is for LocalWP or `.local` self-signed
HTTPS only; do not use it for a public or shared WordPress site. Transient local
HTTPS polling resets are retried until the pairing code expires.
After approval, WordPress shows a pairing result page; return to the terminal or
local AI client and wait for polling to finish.

The `status` command keeps the raw `/health` boundary fields and also prints
derived `boundary` and `proposal_execution` guidance. In a healthy Adapter
connection, `approval_proxy_enabled=false`, `core_proxy_execute=false`, and
`commit_execution=false` are expected boundary controls. They mean Core did not
execute final writes and standalone approval proxying is disabled; they are not
an execution-disabled signal. For proposal execution readiness, inspect
`GET /proposals/{proposal_id}` and use the Adapter approve-and-execute or
execute routes only after Core approval and commit-preflight.

After pairing, local clients can call Adapter through the signed request command
without reading or printing profile secrets:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=local --insecure-local-tls GET /health
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=local --insecure-local-tls GET /capabilities
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=local --insecure-local-tls POST /proposals/from-plan --body-file=/tmp/magick-proposal.json
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=local --insecure-local-tls POST /proposals/PROPOSAL_ID/commit-preflight --intent=preflight
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=local --insecure-local-tls POST /proposals/PROPOSAL_ID/approve-and-execute --intent=commit
```

For sensitive reads, prefer the narrower CLI helpers instead of asking an AI
client to hand-build JSON route bodies:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter read-request create --profile=local --insecure-local-tls --ability-id=npcink-abilities-toolkit/wp-ops-diagnostics-detail --input-file=/tmp/read-input.json --purpose="Review bounded diagnostics" --data-classes=diagnostics,logs --redaction-level=strict --max-rows=10 --tail-lines=5 --denied-fields=authorization,cookie,application_password
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter read-request status --profile=local --insecure-local-tls READ_REQUEST_ID
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter read-ability --profile=local --insecure-local-tls --ability-id=npcink-abilities-toolkit/wp-ops-diagnostics-detail --input-file=/tmp/read-input.json --read-request-id=READ_REQUEST_ID
```

The request command accepts only Adapter-relative routes such as `/health`,
signs the request locally, and prints only the Adapter JSON response. It also
accepts `--body-stdin` for non-secret POST bodies. Final Adapter write routes
such as `/proposals/{proposal_id}/execute`, `/execute-approved-proposal`, and
`/proposals/{proposal_id}/approve-and-execute` require `--intent=commit`.
The CLI refuses those routes when the body still contains preview markers such
as `dry_run=true`, `commit=false`, or `commit_execution=false`; for dry-run or
preflight-only validation, use `/proposals/{proposal_id}/commit-preflight` with
`--intent=preflight` and stop there.
CLI output is redacted by default for local profile paths, key ids, connection
ids, public/private keys, signatures, authorization headers, cookies, tokens,
passwords, and secrets. Adapter also returns a machine-readable `client_policy`
on `/connection/manifest`, `/health`, and `/help`; local AI clients should read
that policy before selecting routes.
The user-facing local client entrypoint is the published npm CLI. The repository
does not keep root-level `tools/` compatibility wrappers; use the package
directly:

```bash
npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter status --profile=local --insecure-local-tls
```

See [`docs/keypair-device-pairing-contract.md`](docs/keypair-device-pairing-contract.md)
for the public-key pairing and request-signing contract.

When the current site URL is local (`localhost`, loopback, or `.local`), the
handoff form can include `NPCINK_OPENCLAW_ADAPTER_INSECURE_SSL=true` in copied
OpenClaw client configuration. This only affects the generated client env text;
it does not change WordPress or Adapter server-side TLS behavior.

For local setup steps, see
[`docs/openclaw-quickstart.md`](docs/openclaw-quickstart.md).

For the connection-model decision history and guardrails, see
[`docs/openclaw-connection-model-notes.md`](docs/openclaw-connection-model-notes.md).

For the OpenClaw article draft planning recipe, use
[`docs/openclaw-article-draft-plan-recipe.md`](docs/openclaw-article-draft-plan-recipe.md).

For the OpenClaw article batch draft planning recipe, use
[`docs/openclaw-article-batch-draft-plan-recipe.md`](docs/openclaw-article-batch-draft-plan-recipe.md).

For the OpenClaw image candidate adoption recipe, use
[`docs/openclaw-image-candidate-adoption-plan-recipe.md`](docs/openclaw-image-candidate-adoption-plan-recipe.md).

For the OpenClaw SEO/AEO/GEO suggestion recipe, use
[`docs/openclaw-content-discoverability-recipe.md`](docs/openclaw-content-discoverability-recipe.md).
The primary SEO/GEO/AEO entrypoint is
`GET /content-discoverability-brief` or
`npcink-toolbox/build-content-discoverability-brief`.
Use `article-writing-pack` only for broad natural-language requests such as
"help me write an article".

For broad natural-language article requests, use
[`docs/openclaw-ai-article-writing-pack-recipe.md`](docs/openclaw-ai-article-writing-pack-recipe.md).
The shortcut `GET /article-writing-pack` forwards input to
`npcink-toolbox/build-ai-article-writing-pack` and returns a
suggestion-only writing pack for a local OpenClaw review candidate. This is an
article assistant path, not an article generator, Cloud writer, or batch
publishing surface.

For productized OpenClaw acceptance, use
[`docs/openclaw-consumer-acceptance.md`](docs/openclaw-consumer-acceptance.md).

For the admin page hierarchy and non-goals, use
[`docs/admin-surface-standard.md`](docs/admin-surface-standard.md).

Cloud runtime access belongs to the standalone `npcink-cloud-addon`.
Adapter must not add its own Cloud settings, signing client, or `/cloud/*`
routes. If an OpenClaw flow needs hosted runtime, Adapter should call the Cloud
Addon public PHP seam described in
[`docs/cloud-connector-boundary.md`](docs/cloud-connector-boundary.md).

## Observability

Adapter emits local metadata-only events through
`npcink_openclaw_adapter_observability_event`; the Cloud Addon is the only uploader. Current
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
   - `approval_surface=npcink_governance_core_admin`
   - `core_proxy_execute=false`
   - `commit_execution=false`
4. OpenClaw calls `GET /capabilities` and uses Core guidance as the only
   governance truth for each `ability_id`.
5. OpenClaw may call `GET /help` to discover adapter route labels, current
   non-goals, and `openclaw_recipes.article_draft_plan`.

Read-only execution:

1. Use a shortcut route when one exists, or call `POST /run-read-ability`.
2. The adapter re-checks Core for the real `ability_id`.
3. The adapter runs only rows where `execution_surface=wp_abilities_rest` and
   `governance_mode=direct_read` or a Core sensitive read grant allows the
   `core_read_authorization_required` read path.
4. The adapter calls WordPress Abilities API and returns a read envelope with
   `read_policy`, `sensitivity`, `redaction_required`, `redaction_applied`,
   `redaction_summary`, `read_audit_mode`, `correlation_id`, `read_context`,
   and `commit_execution=false`.
5. If Core marks the row `direct_read_sensitive` or
   `redaction_required=true`, Adapter applies bounded read-result redaction
   before returning `result`.
6. If Core marks the row with `read_authorization_required=true`,
   `requires_read_authorization=true`,
   `read_policy=core_read_authorization_required`, or
   `authorization_mode=core_read_request`, OpenClaw must create a Core read
   request through Adapter `POST /read-requests`, wait for Core approval, then
   call `POST /run-read-ability` with the same `ability_id`, same `input`, and
   the approved `read_request_id`. Adapter calls Core `read-preflight`
   immediately before execution and verifies `read_authorization_granted=true`,
   `core_authorization_truth=npcink_governance_core`, `ability_id`,
   `approved_input_hash`, expiry, and bounds before reading.
7. Without a valid Core grant, Adapter fails closed with
   `npcink_openclaw_adapter_core_read_authorization_required`. Prompt text,
   chat consent, direct database access, filesystem reads, logs, and custom
   scripts are not substitutes for Core-managed sensitive read authorization.
8. Planning ability output is returned as plan data. `write_actions`,
   `preview`, `risk`, `manual_review`, and
   `skipped_destructive_candidates` are not execution results.

Plan-to-proposal flow:

1. OpenClaw runs one of the direct-read planning abilities:
   `npcink-abilities-toolkit/build-content-inventory-fix-plan`,
   `npcink-abilities-toolkit/build-nonproduction-content-cleanup-plan`,
	   `npcink-abilities-toolkit/build-media-inventory-fix-plan`,
	   `npcink-abilities-toolkit/build-media-reference-repair-plan`, or
	   `npcink-abilities-toolkit/build-media-settings-reference-repair-plan`, or
	   `npcink-abilities-toolkit/build-media-adoption-enhancement-plan`, or
	   `npcink-abilities-toolkit/build-media-rename-plan`, or
	   `npcink-abilities-toolkit/build-pattern-page-plan`, or
	   `npcink-toolbox/build-article-write-plan`, or
	   `npcink-toolbox/build-article-batch-write-plan`, or
	   `npcink-toolbox/build-article-media-batch-write-plan`, or
	   `npcink-toolbox/build-image-candidate-adoption-plan`, or
	   `npcink-toolbox/build-site-knowledge-review-plan`.
2. The adapter preserves plan fields including `batch_id`, `issue_types`,
   `post_ids`, `attachment_ids`, `write_actions`, `preview`, `risk`,
   `requires_approval`, `commit_execution`, `dry_run`, `manual_review`,
   `skipped_destructive_candidates`, `issue_counts`, and `action_count`.
3. OpenClaw calls `POST /proposals/from-plan` with `plan_ability_id`, `plan`,
   optional `plan_input`, and `caller` metadata.
4. The adapter forwards that payload to Core
   `POST /npcink-governance-core/v1/proposals/from-plan` and preserves Core status.
   Adapter does not promote destructive candidates into executable actions.
	   For the Toolbox article write plan, Adapter still only forwards the
	   reviewed `article_write_plan`; Core validates the plan and Adapter later
	   executes `npcink-abilities-toolkit/create-draft` only after Core approval and
	   commit-preflight. The machine-readable OpenClaw playbook is exposed as
	   `openclaw_recipes.article_draft_plan` from `GET /help`.
	   For reviewed 2-5 article draft batches, use
	   `npcink-toolbox/build-article-batch-write-plan`; Core creates one
	   batch proposal and Adapter later executes only the approved
	   `npcink-abilities-toolkit/create-draft` write actions. The machine-readable playbook is
	   exposed as `openclaw_recipes.article_batch_draft_plan` from `GET /help`.
	   See [OpenClaw Article Batch Draft Plan Recipe](docs/openclaw-article-batch-draft-plan-recipe.md).
	   For reviewed article batches with selected image-source candidates, use
	   `npcink-toolbox/build-article-media-batch-write-plan`; Core creates one
	   batch proposal and Adapter later executes only approved
	   `npcink-abilities-toolkit/create-draft`, `npcink-abilities-toolkit/upload-media-from-url`,
	   `npcink-abilities-toolkit/update-media-details`, and
	   `npcink-abilities-toolkit/set-post-featured-image` actions. The machine-readable
	   playbook is exposed as `openclaw_recipes.article_media_batch_plan` from
	   `GET /help`. See
	   [OpenClaw Article Media Batch Plan Recipe](docs/openclaw-article-media-batch-plan-recipe.md).
	   For reviewed Gutenberg page pattern drafts, use
	   `npcink-abilities-toolkit/build-pattern-page-plan`; Core creates one
	   batch proposal and Adapter later executes only approved
	   `npcink-abilities-toolkit/create-draft` and
	   `npcink-abilities-toolkit/update-post-blocks` actions. The machine-readable
	   playbook is exposed as `openclaw_recipes.pattern_page_plan` from
	   `GET /help`, including a `visual_acceptance` block with front-end/editor
	   targets, 1440/768/390 viewport checks, and the local smoke artifact envs.
	   See [OpenClaw Pattern Page Plan Recipe](docs/openclaw-pattern-page-plan-recipe.md)
	   and [OpenClaw Gutenberg Visual Acceptance](docs/openclaw-gutenberg-visual-acceptance.md).
	   For reviewed Gutenberg article block drafts, use
	   `npcink-abilities-toolkit/build-article-block-plan`; Core creates one
	   batch proposal and Adapter later executes only approved
	   `npcink-abilities-toolkit/create-draft` and
	   `npcink-abilities-toolkit/update-post-blocks` actions. The machine-readable
	   playbook is exposed as `openclaw_recipes.article_block_plan` from
	   `GET /help`, including the same browser visual acceptance contract for
	   responsive Gutenberg article drafts. See
	   [OpenClaw Article Block Plan Recipe](docs/openclaw-article-block-plan-recipe.md)
	   and [OpenClaw Gutenberg Visual Acceptance](docs/openclaw-gutenberg-visual-acceptance.md).
	   For adopting one reviewed `image_candidate.v1` into the media library,
	   call `npcink-toolbox/build-image-candidate-adoption-plan`; Core
	   creates one batch proposal and Adapter later executes only approved
	   `npcink-abilities-toolkit/upload-media-from-url`, `npcink-abilities-toolkit/update-media-details`, and
	   optional `npcink-abilities-toolkit/set-post-featured-image` actions. The
	   machine-readable playbook is exposed as
	   `openclaw_recipes.image_candidate_adoption_plan` from `GET /help`. See
	   [OpenClaw Image Candidate Adoption Plan Recipe](docs/openclaw-image-candidate-adoption-plan-recipe.md).
	   For one reviewed remote visual asset that should be imported, optimized,
	   and optionally wired into an existing page/post reference, call
	   `npcink-abilities-toolkit/build-media-adoption-enhancement-plan`; Core
	   creates one batch proposal and Adapter later executes only approved
	   `npcink-abilities-toolkit/upload-media-from-url`,
	   `npcink-abilities-toolkit/optimize-media-asset`, and optional
	   `npcink-abilities-toolkit/patch-post-content` actions. The
	   machine-readable playbook is exposed as
	   `openclaw_recipes.media_adoption_enhancement_plan` from `GET /help`.
	   See [OpenClaw Media Adoption Enhancement Plan Recipe](docs/openclaw-media-adoption-enhancement-plan-recipe.md).
	   For research-backed Gutenberg landing pages, first use
	   `openclaw_recipes.pattern_page_research_brief` to request bounded
	   Cloud-owned `competitor_research` evidence through Toolbox and produce a
	   suggestion-only `landing_page_research_brief`. See
	   [OpenClaw Pattern Page Research Brief Recipe](docs/openclaw-pattern-page-research-brief-recipe.md).
	   For visually richer Gutenberg landing pages, compose the reviewed image
	   candidate adoption flow with `pattern_page_plan`: first adopt one
	   reviewed `image_candidate.v1` into the local media library, then pass the
	   approved WordPress media URL as `variables.hero_media_url` with
	   `media_strategy=existing_media_url`. The machine-readable playbook is
	   exposed as `openclaw_recipes.pattern_page_with_visual_asset_plan` from
	   `GET /help`. See
	   [OpenClaw Pattern Page With Visual Asset Recipe](docs/openclaw-pattern-page-with-visual-asset-recipe.md).
	   For AI-generated visuals whose model output dimensions are unreliable,
	   choose the page-slot ratio first, crop the reviewed candidate through the
	   Cloud media derivative path, then adopt the cropped preview through
	   `npcink-abilities-toolkit/build-media-adoption-enhancement-plan` before a
	   page references the final local media URL. The machine-readable playbook
	   is exposed as `openclaw_recipes.ai_image_ratio_crop_media_adoption` from
	   `GET /help`. See
	   [OpenClaw AI Image Ratio Crop Media Adoption Recipe](docs/openclaw-ai-image-ratio-crop-media-adoption-recipe.md).
	   For Site Knowledge agent evidence review, call
	   `npcink-toolbox/build-site-knowledge-review-plan`; Core creates a
	   blocked review proposal that still requires human `title` and `content`
	   input. Adapter must not approve, preflight, execute, or write WordPress
	   content from this handoff.

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
7. If `status=approved` and execution is intended, OpenClaw calls
   `POST /proposals/{proposal_id}/execute` or `POST /execute-approved-proposal`.
   Adapter execute routes are final write paths and normalize ability input to
   `dry_run=false` and `commit=true`. Do not call Core commit-preflight
   directly; Core handoffs are one-time.
8. `POST /proposals/{proposal_id}/commit-preflight` is an advanced diagnostic
   Adapter route. When it succeeds through Adapter, Adapter caches the one-time
   Core handoff for the next Adapter execute call and still preserves
   `commit_execution=false`. For dry-run-only verification, stop here and do
   not call execute.
9. For the current approved proposal execution path, Adapter may execute only
   `npcink-abilities-toolkit/trash-post`, `npcink-abilities-toolkit/create-draft`,
   `npcink-abilities-toolkit/update-post`, `npcink-abilities-toolkit/set-post-seo-meta`,
   `npcink-abilities-toolkit/set-post-slug`, `npcink-abilities-toolkit/set-post-terms`,
   `npcink-abilities-toolkit/delete-term`, `npcink-abilities-toolkit/patch-post-content`,
   `npcink-abilities-toolkit/update-post-blocks`,
   `npcink-abilities-toolkit/patch-setting-value`,
	   `npcink-abilities-toolkit/update-media-details`,
	   `npcink-abilities-toolkit/optimize-media-asset`,
	   `npcink-abilities-toolkit/replace-media-file`,
	   `npcink-abilities-toolkit/restore-media-backup`,
	   `npcink-abilities-toolkit/adopt-cloud-media-derivative`,
	   `npcink-abilities-toolkit/rename-media-file`,
	   `npcink-abilities-toolkit/delete-media-permanently`, `npcink-abilities-toolkit/reply-comment`,
   `npcink-abilities-toolkit/trash-comment`, or `npcink-abilities-toolkit/approve-comment` through
   `POST /proposals/{proposal_id}/execute` or
   `POST /execute-approved-proposal`.
10. Adapter fetches the Core proposal, consumes a cached Adapter preflight
    handoff when one was issued through Adapter, otherwise calls Core
    commit-preflight, requires `approval_commit_authorized=true`, requires
    `commit_execution=false`, passes Core `approval_context` to WordPress
    Abilities API, normalizes ability input to `dry_run=false` and
    `commit=true`, stores a bounded execution record, and returns `proposal_id`,
    `correlation_id`, `ability_id`, and `execution_record` with the ability
    result. If execution fails after Core preflight has been consumed, Adapter
    stores and returns only a bounded failed execution summary; it does not
    store the full proposal or create a retry queue. Repeating the same
    successful proposal execution returns
    `npcink_openclaw_adapter_execution_already_completed` and does not run the
    ability again.
11. Adapter does not create its own proposal or approval state and does not
    batch silently execute destructive actions. The unified action only
    orchestrates Core approve -> commit-preflight -> one allowlisted WordPress
    Abilities API execution.

Proposal list/detail are read-only Core proxies. They preserve Core response
fields such as `proposal_id`, `ability_id`, `status`, `title`, `summary`,
`input`, `preview`, `caller`, `created_at`, `updated_at`, and detail
`audit_timeline` when Core returns it. Adapter may be configured with a Core app
token through `NPCINK_OPENCLAW_ADAPTER_CORE_APP_TOKEN` or the
`npcink_openclaw_adapter_core_app_token` option. When configured, Adapter sends that
token only on internal Core REST requests and does not print it. That key must
include `proposals:read` for proposal status, plus the other scopes needed by
the Core routes Adapter calls. The adapter must not print Core tokens in logs,
proposal payloads, error responses, or documentation examples.

When OpenClaw has a Core `proposal_id` or commit-preflight `correlation_id`, it
should pass those values to Adapter read or future execution requests as
`log_context` or query parameters. Adapter will include them under
`npcink_openclaw_adapter`, top-level context fields, and nested `npcink_governance_core`
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
  -d '{"proposal_id":"PROPOSAL_ID","correlation_id":"CORRELATION_ID","ability_id":"npcink-abilities-toolkit/create-draft","ai_provider":"ollama","ai_model":"qwen3.5:0.8b","prompt":"Reply with exactly: OK"}' \
  "https://example.test/wp-json/npcink-openclaw-adapter/v1/ai-provider-log-correlation-smoke"
```

If the AI Request Logs provider column is blank for a local connector, inspect
the Adapter context fields instead. They preserve the explicit `ai_provider`
and explicit `ai_model` sent to the smoke route.

`POST /proposals/{proposal_id}/approve` and
`POST /proposals/{proposal_id}/reject` are disabled stubs. They return HTTP 403
with `code=npcink_openclaw_adapter_approval_proxy_disabled`,
`approval_proxy_enabled=false`, and
`approval_surface=npcink_governance_core_admin`. For the Adapter/OpenClaw unified user
action, use `POST /proposals/{proposal_id}/approve-and-execute`; otherwise use
Npcink Governance Core admin for split approval decisions. The adapter does not forward
the standalone approve/reject stub routes to Core and does not require a
default Core key with approval or rejection scopes.

Example health request:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://example.test/wp-json/npcink-openclaw-adapter/v1/health"
```

Example proposal request:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"ability_id":"npcink-abilities-toolkit/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"title":"OpenClaw draft","dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}' \
  "https://example.test/wp-json/npcink-openclaw-adapter/v1/proposals"
```

Example proposal status request:

```bash
curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \
  "https://example.test/wp-json/npcink-openclaw-adapter/v1/proposals/PROPOSAL_ID"
```

## OpenClaw Flow

Read-only abilities:

1. OpenClaw calls Adapter `/capabilities`.
2. Adapter relays Core guidance.
3. If `governance_mode=direct_read`, OpenClaw calls Adapter read endpoints.
4. If Core requires sensitive read authorization, OpenClaw calls Adapter
   `/read-requests`, waits for Core approval, then calls `/run-read-ability`
   with `read_request_id`.
5. Adapter runs the ability through WordPress Abilities API only after any
   required Core read-preflight grant succeeds.

Write or destructive abilities:

1. OpenClaw calls Adapter `/capabilities`.
2. If `governance_mode=proposal_required`, OpenClaw calls Adapter
   `/proposals`.
3. OpenClaw polls Adapter `/proposals/{proposal_id}` for Core status.
4. If pending and the user chooses the unified action, OpenClaw calls
   Adapter `/proposals/{proposal_id}/approve-and-execute`; Adapter calls Core
   approve, Core commit-preflight, then executes one allowlisted final write.
5. If rejected, OpenClaw stops and shows the status. If approved, OpenClaw calls
   Adapter `/proposals/{proposal_id}/commit-preflight` after
   approval.
6. Adapter relays Core `commit_execution=false`. Dry-run-only verification
   stops at Adapter commit-preflight; do not call execute unless the operator
   intends a final write.
7. For approved proposal execution, only `npcink-abilities-toolkit/trash-post`,
   `npcink-abilities-toolkit/create-draft`, `npcink-abilities-toolkit/update-post`,
   `npcink-abilities-toolkit/patch-post-content`, `npcink-abilities-toolkit/update-post-blocks`,
   `npcink-abilities-toolkit/patch-setting-value`,
   `npcink-abilities-toolkit/set-post-seo-meta`, `npcink-abilities-toolkit/set-post-slug`,
	   `npcink-abilities-toolkit/set-post-terms`, `npcink-abilities-toolkit/delete-term`,
	   `npcink-abilities-toolkit/update-media-details`, `npcink-abilities-toolkit/optimize-media-asset`,
	   `npcink-abilities-toolkit/replace-media-file`,
	   `npcink-abilities-toolkit/restore-media-backup`,
	   `npcink-abilities-toolkit/adopt-cloud-media-derivative`,
	   `npcink-abilities-toolkit/rename-media-file`,
	   `npcink-abilities-toolkit/delete-media-permanently`,
   `npcink-abilities-toolkit/reply-comment`, `npcink-abilities-toolkit/trash-comment`, and
   `npcink-abilities-toolkit/approve-comment` are supported in
   this adapter. The execution input may be a single allowlisted proposal input or a bounded
   `input.write_actions[]` batch where every action targets the allowlist.
   OpenClaw calls `/proposals/{proposal_id}/execute`; Adapter performs Core
   preflight again, passes `approval_context`, normalizes ability input to
   `dry_run=false` and `commit=true`, and executes through WordPress Abilities
   API. New execution abilities must be added as explicit Adapter
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
`npcink_openclaw_adapter_plan_action_input_invalid` with `blocked_items[]` carrying
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

Local smoke and HTTP acceptance tests must register every created fixture for
automatic cleanup before assertions can fail. Rejected and preflight-blocked
negative-loop cases must not rely on final write execution to remove target
posts, because the correct behavior is to leave those writes unexecuted.
