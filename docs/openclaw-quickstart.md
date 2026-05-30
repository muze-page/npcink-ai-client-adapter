# OpenClaw Quickstart

Status: local development handoff guide.

This guide is for connecting OpenClaw to a local WordPress development site
through Magick AI Adapter. The adapter remains a thin channel layer:

- OpenClaw only connects to Adapter;
- Magick AI Core is Adapter's governance service behind the scenes;
- Core approval admin is the human governance surface;
- read operations go through WordPress Abilities API;
- write-like operations create Core proposals and stop at commit preflight;
- `approval_proxy_enabled=false`;
- `approval_surface=magick_ai_core_admin`;
- `core_proxy_execute=false`;
- `commit_execution=false`.

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
same local administrator account and pass that Application Password through the
approved secret channel.

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
Settings -> OpenClaw Connection
```

The page includes a `Create OpenClaw handoff` button. It creates a normal
WordPress Application Password for the current administrator and shows the raw
password once with OpenClaw env and handoff text. The adapter does not store the
raw password.

## REST Authentication

OpenClaw should use WordPress REST Basic Auth with an Application Password:

```text
Authorization: Basic base64(username:application_password)
```

Example:

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/health"
```

Do not put the normal WordPress login password in OpenClaw configuration when
an Application Password is available.

## Connection Check

1. Call `GET /health`.
2. Confirm:
   - `core_capabilities=true`
   - `abilities_catalog=true`
   - `approval_proxy_enabled=false`
   - `approval_surface=magick_ai_core_admin`
   - `core_proxy_execute=false`
   - `commit_execution=false`
3. Call `GET /help` to confirm route discovery includes proposal list/detail.
4. Call `GET /capabilities`.
5. Use the returned Core guidance as the only governance truth.

## Read Shortcuts

Shortcut routes forward GET query parameters as ability input. Use only inputs
accepted by the underlying ability schema.

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/site-info"
```

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/media?per_page=1"
```

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/pages?per_page=1"
```

If a route returns `magick_ai_adapter_proposal_required`, stop and use the
proposal flow instead of trying to execute the ability directly.

## Proposal-Required Write Flow

1. Call `GET /capabilities`.
2. Select a real `ability_id` where Core reports
   `governance_mode=proposal_required`.
3. Create a proposal:

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"ability_id":"magick-ai/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"title":"Local OpenClaw draft","dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}' \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals"
```

4. Query proposal status through the adapter:

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID"
```

For a list view:

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals?limit=10"
```

These are read-only Core status proxies. They preserve proposal fields such as
`proposal_id`, `ability_id`, `status`, `title`, `summary`, `input`, `preview`,
`caller`, `created_at`, `updated_at`, and detail `audit_timeline` when Core
returns it.

5. If `status=pending`, prompt the user to approve or reject the proposal in
   `WordPress -> Magick AI Core`. Do not call Core directly from OpenClaw.
6. If `status=rejected`, stop and show the rejection state or reason returned
   by Core.
7. If `status=approved`, call commit preflight:

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  -X POST \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/proposals/PROPOSAL_ID/commit-preflight"
```

8. Stop at Core's preflight response. The adapter does not approve proposals and
   does not execute final WordPress writes.

## Log Correlation

When OpenClaw has a Core proposal or preflight correlation id, pass it to
Adapter on later read or execution requests:

```bash
curl -sS --user "1:APPLICATION_PASSWORD" \
  "https://magick-ai.local/wp-json/magick-ai-adapter/v1/site-info?proposal_id=PROPOSAL_ID&correlation_id=CORRELATION_ID"
```

For `POST /run-read-ability`, send the same values in a top-level
`log_context` object. Adapter copies these values into AI Request Logs context
through `wpai_request_log_context`; it does not merge AI Request Logs with Core
audit, and it does not forward those reserved fields as ability input.

Approval and rejection endpoints are visible only as disabled stubs:

```text
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject
```

They return HTTP 403 with
`code=magick_ai_adapter_approval_proxy_disabled`,
`approval_proxy_enabled=false`, and
`approval_surface=magick_ai_core_admin`. Approval is handled in Magick AI Core admin.
Adapter does not forward these calls to Core and OpenClaw does not get
default approval power.

If a direct Core app token is used for Core-side integration tests or a future
trusted handoff, the key must include `proposals:read` for list/detail status.
Do not put Core tokens in logs, proposal payloads, error responses, or docs
examples.

Adapter may also be configured with a Core app token through
`MAGICK_AI_ADAPTER_CORE_APP_TOKEN` or the
`magick_ai_adapter_core_app_token` option. This is Adapter internal
configuration only; do not put the raw token into OpenClaw prompts, proposal
payloads, screenshots, or handoff examples.
