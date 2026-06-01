# OpenClaw Quickstart

Status: local development handoff guide.

This guide is for connecting OpenClaw to a local WordPress development site
through Magick AI Adapter. The adapter remains a thin channel layer:

- OpenClaw only connects to Adapter;
- Magick AI Core is Adapter's governance service behind the scenes;
- Core remains the approval, preflight, and audit truth source;
- read operations go through WordPress Abilities API;
- write-like operations create Core proposals and may use Adapter's
  approve-and-execute user action for one allowlisted execution;
- `approval_proxy_enabled=false`;
- `approval_surface=magick_ai_core_admin`;
- `core_proxy_execute=false`;
- `commit_execution=false`.

For the acceptance checklist that productized OpenClaw clients should run
before relying on the connection, see
[`openclaw-consumer-acceptance.md`](openclaw-consumer-acceptance.md).

## Local WordPress Access

Current LocalWP development site:

- Site URL: `https://magick-ai.local`
- WordPress admin URL: `https://magick-ai.local/wp-admin/`
- WordPress administrator username: `1`
- WordPress administrator password: `1`

These credentials are for the local development environment only. Do not reuse
them for production, hosted test sites, shared staging sites, or customer data.

Use the username/password above for browser login to WordPress admin. For REST
handoff to OpenClaw, create a dedicated WordPress Application Password for the
same local administrator account. Pass only the non-secret connection manifest
to OpenClaw, and paste the Application Password only into OpenClaw's dedicated
secret field or credential vault.

## Adapter URLs

Base URL:

```text
https://magick-ai.local/wp-json/magick-ai-adapter/v1
```

Health:

```text
GET https://magick-ai.local/wp-json/magick-ai-adapter/v1/health
```

Help:

```text
GET https://magick-ai.local/wp-json/magick-ai-adapter/v1/help
```

Capabilities:

```text
GET https://magick-ai.local/wp-json/magick-ai-adapter/v1/capabilities
```

WordPress admin connection page:

```text
Magick AI -> Adapter
```

The page includes a `Create OpenClaw handoff` button. It creates a normal
WordPress Application Password for the current administrator and shows the raw
password once in the browser. Copied OpenClaw env, manifest, and handoff text
contain only placeholders or non-secret identifiers. The adapter does not store
the raw password.

## REST Authentication

OpenClaw should use WordPress REST Basic Auth with an Application Password:

```text
Authorization: Basic base64(username:<openclaw-secret-field-value>)
```

Example:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/health"
```

Do not put the normal WordPress login password in OpenClaw configuration when
an Application Password is available. Do not paste the Application Password
into chat, tool commands, logs, proposal payloads, files, or copied handoff
text.

## Public Key Device Pairing

For WorkBuddy or another local broker, use key-pair device pairing instead of
sending a browser-visible Application Password:

```text
GET  /connection/manifest
POST /connect/device/start
POST /connect/device/poll
GET  /connection/key-pairs
```

The local broker generates an Ed25519 key pair, keeps the private key local,
and starts pairing with the public key. WordPress shows an admin approval page;
after approval, Adapter stores only the public key and later verifies signed
Adapter requests.

For local validation, this repository includes a development-only verifier:

```bash
node /Users/muze/gitee/magick-ai-adapter/tools/keypair-device-pairing.mjs --site=https://magick-ai.local --profile=local --insecure-local-tls
```

The script opens the WordPress approval URL in the system browser. Approve the
client there; the script then stores a local profile under
`~/.magick-ai-adapter/keypair-profiles/` and tests a signed `GET /health`. Use
`--no-open` to print the URL without opening a browser. A production WorkBuddy
integration should replace that file write with the OS keychain or WorkBuddy
credential vault. Use `--insecure-local-tls` only for LocalWP or `.local`
self-signed HTTPS testing. If LocalWP resets a polling connection, the verifier
keeps retrying until the pairing code expires.

After approval, WordPress shows a pairing result page. Return to the terminal or
local AI client and wait for the polling command to finish.

After pairing, OpenClaw-style local clients should call Adapter through the
local request wrapper instead of reading the profile file:

```bash
node /Users/muze/gitee/magick-ai-adapter/tools/keypair-adapter-request.mjs --profile=local --insecure-local-tls GET /health
node /Users/muze/gitee/magick-ai-adapter/tools/keypair-adapter-request.mjs --profile=local --insecure-local-tls GET /capabilities
```

For POST requests, write the non-secret request JSON to a temporary file and
pass it with `--body-file`:

```bash
node /Users/muze/gitee/magick-ai-adapter/tools/keypair-adapter-request.mjs --profile=local --insecure-local-tls POST /proposals/from-plan --body-file=/tmp/magick-proposal.json
```

The wrapper rejects absolute URLs, signs the Adapter-relative route locally, and
prints only the Adapter JSON response. Do not ask OpenClaw to read or summarize
`~/.magick-ai-adapter/keypair-profiles/*.json`.

## Connection Check

1. Call `GET /health`.
2. Confirm:
   - `core_capabilities=true`
   - `abilities_catalog=true`
   - `approval_proxy_enabled=false`
   - `approval_surface=magick_ai_core_admin`
   - `core_proxy_execute=false`
   - `commit_execution=false`
3. Call `GET /help` to confirm route discovery includes proposal list/detail,
   `POST /proposals/from-plan`, `POST /proposals/{proposal_id}/execute`, and
   `POST /proposals/{proposal_id}/approve-and-execute`.
4. Call `GET /capabilities`.
5. Use the returned Core guidance as the only governance truth.

## Read Shortcuts

Shortcut routes forward GET query parameters as ability input. Use only inputs
accepted by the underlying ability schema. For term details, use the `id` field
returned by term list routes; the adapter infers `taxonomy` from the term id
when possible, and also accepts `term_id` as an alias for `id`. Pass `taxonomy`
when the caller already knows it.

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/site-info"
```

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/term?id=1"
```

Planning shortcuts return plan data only. Treat `write_actions` and `preview`
as proposal input, not as completed writes:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/content-inventory-fix-plan?per_page=1&max_actions=1"
```

Send the returned plan to Core through Adapter when a proposal should be
created:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"plan_ability_id":"magick-ai/build-content-inventory-fix-plan","plan":{"batch_id":"example","issue_types":[],"requires_approval":true,"commit_execution":false,"dry_run":true,"action_count":0,"write_actions":[],"preview":[],"risk":{"level":"medium"}},"plan_input":{"per_page":1},"caller":{"external_thread_id":"OPENCLAW_THREAD"}}' \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/from-plan"
```

Troubleshooting diagnostics:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/active-plugins-detail"
```

The default diagnostics input requests active plugins, update rows, must-use
plugins, dropins, and log severity summaries, but it does not request inactive
plugin rows:

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

For plugin conflict troubleshooting, explicitly request inactive plugin rows:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/plugin-conflict-diagnostics"
```

That route sends `include_inactive_plugins=true` and
`max_plugins_per_group=200`.

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/recent-error-log"
```

`recent-error-log` uses `include_log_contents=false`. Treat log contents as
not explicitly requested, not missing. Use `error_log.summary.fatal_count`,
`error_log.summary.error_count`, `error_log.summary.warning_count`,
`error_log.summary.deprecated_count`, `error_log.summary.notice_count`, and
`error_log.summary.by_severity` for severity display without fetching content.
When the user explicitly asks to inspect logs, request the bounded redacted
tail:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/recent-error-log-tail"
```

That route sends:

```json
{
  "include_log_contents": true,
  "tail_lines": 50,
  "severity": ["fatal", "error", "warning"],
  "since_minutes": 1440
}
```

Display `error_log.tail_entries` and `contents` only when
`contents_included=true`. The `contents` array is compatibility redline text.

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/current-user-permissions"
```

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/database-info"
```

All P0/P1/P2 diagnostics detail shortcuts call
`magick-ai-abilities/wp-ops-diagnostics-detail`. Do not use
`wp-diagnostics-summary` to decide whether plugin details, user permissions, or
log details are missing. Default inactive plugin rows are not missing; they are
default not requested. Adapter does not add Magick AI runtime, MCP, or cloud
state to the WordPress diagnostics mapping.

Content context reads:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/posts?author_id=1&orderby=modified&order=desc"
```

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/terms?taxonomy=category&include_sample_posts=1&sample_post_limit=3"
```

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/menu?location=primary"
```

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/media?per_page=1"
```

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/pages?per_page=1"
```

If a route returns `magick_ai_adapter_proposal_required`, stop and use the
proposal flow instead of trying to execute the ability directly.

Diagnostics shortcuts are aliases over `magick-ai-abilities` direct-read
abilities. Adapter does not read arbitrary files, inspect database tables
directly, or own redaction policy.

## Proposal-Required Write Flow

1. Call `GET /capabilities`.
2. Select a real `ability_id` where Core reports
   `governance_mode=proposal_required`.
3. Create a proposal:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"ability_id":"magick-ai/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"title":"Local OpenClaw draft","dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}' \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals"
```

4. Query proposal status through the adapter:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID"
```

For a list view:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals?limit=10"
```

These are read-only Core status proxies. They preserve proposal fields such as
`proposal_id`, `ability_id`, `status`, `title`, `summary`, `input`, `preview`,
`caller`, `created_at`, `updated_at`, and detail `audit_timeline` when Core
returns it.

5. If `status=pending`, use the unified OpenClaw action
   `POST /proposals/{proposal_id}/approve-and-execute` for allowlisted
   execution, or use `Magick AI -> Core` for split approval decisions.
   Do not call Core directly from OpenClaw.
6. If `status=rejected`, stop and show the rejection state or reason returned
   by Core.
7. If using the lower-level split path and `status=approved`, call commit
   preflight:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  -X POST \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID/commit-preflight"
```

8. Stop at Core's preflight response unless the ability is in Adapter's current
   approved proposal execution allowlist. Core still returns
   `commit_execution=false`.
9. For approved proposal execution, only `magick-ai/trash-post` and
   `magick-ai/create-draft` are currently supported. The preferred user path is
   one Adapter/OpenClaw action:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  -X POST \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID/approve-and-execute"
```

Adapter calls Core approve when the proposal is pending, calls Core
commit-preflight, verifies `approval_commit_authorized=true` and
`commit_execution=false`, then executes one WordPress Abilities API call. Core
remains the governance backend for proposal state, approval, preflight, and
audit.

For a batch plan-shaped proposal, the same route accepts
`input.write_actions[]` only when every action targets the Adapter execution
allowlist and passes ability-specific input checks. Adapter still calls Core
approve and Core commit-preflight before running the bounded batch, and returns
per-action `results[]` with `execution_mode=batch_write_actions`.
10. The lower-level execution route is available only for already approved
   proposals:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  -X POST \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID/execute"
```

Adapter fetches the Core proposal, runs Core commit-preflight again, requires
`approval_commit_authorized=true`, requires `commit_execution=false`, passes
Core `approval_context`, and executes one proposal through WordPress Abilities
API. The adapter does not create its own governance state.
For abilities outside that execution allowlist, Adapter does not execute final
WordPress writes.
Future execution abilities must be added one by one to the Adapter allowlist
with dedicated smoke coverage; this is not a generic proxy-execute surface.

## Log Correlation

When OpenClaw has a Core proposal or preflight correlation id, pass it to
Adapter on later read or execution requests:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/site-info?proposal_id=PROPOSAL_ID&correlation_id=CORRELATION_ID"
```

For `POST /run-read-ability`, send the same values in a top-level
`log_context` object. Adapter copies these values into AI Request Logs context
through `wpai_request_log_context`; it does not merge AI Request Logs with Core
audit, and it does not forward those reserved fields as ability input.

Core Governance Audit is the governance log. WordPress `ai` plugin AI Request
Logs are the provider request log. Adapter carries `proposal_id`,
`correlation_id`, `ability_id`, `adapter_request_id`, `adapter_route`,
`ai_provider`, `ai_model`, `governance_source=magick-ai-core`, and nested
`magick_ai_core` identifiers into AI Request Logs context. It does not put
provider credentials, prompts, responses, token details, or AI Request Logs into
Core.
AI Request Logs are the provider request log.

Provider log readiness smoke after Core approval and Adapter commit-preflight.
This example uses local Ollama when `qwen3.5:0.8b` is available; otherwise use
a configured text generation provider/model from
`GET /ai/v1/providers?capability=text_generation`:

```bash
curl -sS --user "1:<openclaw-secret-field-value>" \
  -H "Content-Type: application/json" \
  -d '{"proposal_id":"PROPOSAL_ID","correlation_id":"CORRELATION_ID","ability_id":"magick-ai/create-draft","ai_provider":"ollama","ai_model":"qwen3.5:0.8b","prompt":"Reply with exactly: OK"}' \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/ai-provider-log-correlation-smoke"
```

Then open AI Request Logs and search the same `proposal_id` or
`correlation_id`. If the provider column is blank, use the Adapter context
fields for the explicit `ai_provider` and `ai_model` sent to the smoke route.

Approval and rejection endpoints are visible only as disabled stubs:

```text
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject
```

They return HTTP 403 with
`code=magick_ai_adapter_approval_proxy_disabled`,
`approval_proxy_enabled=false`, and
`approval_surface=magick_ai_core_admin`. Use
`POST /proposals/{proposal_id}/approve-and-execute` for the Adapter unified
user action, or Magick AI Core admin for split approval decisions. Adapter does
not forward the standalone stub calls to Core and OpenClaw does not get generic
approval power.

Failure code handling:

- `magick_ai_adapter_approval_proxy_disabled`: call approve-and-execute or use
  Core admin for split approval.
- `magick_ai_adapter_execute_ability_not_allowed`: stop; the proposal ability
  is outside Adapter's execution allowlist.
- `magick_ai_adapter_proposal_rejected`: stop and show the Core rejection.
- `magick_ai_adapter_preflight_not_authorized` or
  `magick_ai_adapter_preflight_item_blocked`: stop and show Core preflight
  details.

If a direct Core app token is used for Core-side integration tests or a future
trusted handoff, the key must include `proposals:read` for list/detail status.
Do not put Core tokens in logs, proposal payloads, error responses, or docs
examples.

Adapter may also be configured with a Core app token through
`MAGICK_AI_ADAPTER_CORE_APP_TOKEN` or the
`magick_ai_adapter_core_app_token` option. This is Adapter internal
configuration only; do not put the raw token into OpenClaw prompts, proposal
payloads, screenshots, or handoff examples.
