# OpenClaw Adapter Contract

Status: initial productization contract.

Magick AI Adapter gives OpenClaw a focused WordPress REST surface without
turning Magick AI Core into an execution proxy.

The productized acceptance checklist lives in
[`openclaw-consumer-acceptance.md`](openclaw-consumer-acceptance.md). Keep this
contract as the boundary definition and use the acceptance checklist for
OpenClaw connection verification.

OpenClaw only connects to Adapter. Core is Adapter's governance service for
proposal storage, approval status, commit preflight, and audit attribution.
Core admin is the human approval surface; Adapter is not the default approval
subject.

## Dependencies

- WordPress 6.9+ with WordPress Abilities API routes available.
- `magick-ai-abilities` for canonical ability definitions and callbacks.
- `magick-ai-core` for governance, proposal approval, commit preflight, and
  audit.

## Read Ability Contract

The adapter may execute only capability rows where Core returns:

```json
{
  "governance_mode": "direct_read",
  "execution_surface": "wp_abilities_rest",
  "core_proxy_execute": false,
  "commit_execution": false
}
```

The adapter executes those reads through:

```text
/wp-json/wp-abilities/v1/abilities/{ability_id}/run
```

It does not execute abilities marked `proposal_required`.

## Governed Write Contract

For write or destructive abilities, the adapter relays to Core:

```text
POST /wp-json/magick-ai-core/v1/proposals
POST /wp-json/magick-ai-core/v1/proposals/{proposal_id}/commit-preflight
```

The adapter does not approve proposals and does not execute final WordPress
mutations.

## AI Request Log Correlation

Adapter keeps Core audit and AI Request Logs separate, but it carries stable
correlation fields between them.

For read routes and future execution handoff routes, OpenClaw may pass:

- `proposal_id`;
- `correlation_id`;
- `external_thread_id`;
- `openclaw_thread_id`;
- a top-level `log_context` object on POST `/run-read-ability`.

Adapter must not forward those reserved query fields as ability input. While an
ability is running, Adapter adds the sanitized values to AI Request Logs via the
`wpai_request_log_context` filter. The AI log context receives a
`magick_ai_adapter` object and top-level `proposal_id` and `correlation_id`
when present. This lets operators correlate AI Request Logs execution rows with
Core proposal/audit/preflight records without merging tables or making Core an
AI request logger.

## Proposal Status Read Proxy

OpenClaw connects to Magick AI Adapter, not directly to Core. The adapter may
therefore expose these read-only Core proposal status routes:

```text
GET /wp-json/magick-ai-adapter/v1/proposals
GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}
```

They relay to:

```text
GET /wp-json/magick-ai-core/v1/proposals
GET /wp-json/magick-ai-core/v1/proposals/{proposal_id}
```

The adapter should preserve Core proposal list/detail fields, including
`proposal_id`, `ability_id`, `status`, `title`, `summary`, `input`, `preview`,
`caller`, `created_at`, `updated_at`, and detail `audit_timeline` when Core
returns it. If a Core app token is used in a direct Core auth path or future
trusted handoff, that key must include `proposals:read`.

The adapter must not print Core tokens in logs, proposal payloads, error
responses, or documentation examples. It must not add proposal approval or
rejection proxy routes by default.

Adapter Core app token configuration may come from
`MAGICK_AI_ADAPTER_CORE_APP_TOKEN` or the
`magick_ai_adapter_core_app_token` option. When configured, the token is used
only for internal Core REST calls through the request header supported by Core,
and the raw value must not appear in health, help, handoff text, error details,
proposal payloads, or docs examples.

## Approval Disabled Stub Contract

The adapter exposes these routes only as disabled stubs:

```text
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject
```

Default response:

```json
{
  "code": "magick_ai_adapter_approval_proxy_disabled",
  "message": "Approval is handled in Magick AI Core admin.",
  "approval_proxy_enabled": false,
  "approval_surface": "magick_ai_core_admin"
}
```

The disabled stubs must not forward to Core approval or rejection routes. The
default Core app key used by Adapter must not require approval or rejection
scopes. OpenClaw and agents must not receive default approval power through
Adapter.

Future trusted approval proxying can only be added as an explicit, disabled by
default, ADR-backed feature with a trusted host policy and independent approval
and rejection scopes. This contract does not implement that forwarding.

## First Product Routes

Connection:

- WordPress admin: `Settings -> OpenClaw Connection`
- `GET /wp-json/magick-ai-adapter/v1/health`
- `GET /wp-json/magick-ai-adapter/v1/help`

Read shortcuts:

- `GET /wp-json/magick-ai-adapter/v1/site-info`
- `GET /wp-json/magick-ai-adapter/v1/site-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-diagnostics-summary`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipes`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipe?recipe_id=workflow/...`
- `GET /wp-json/magick-ai-adapter/v1/media`
- `GET /wp-json/magick-ai-adapter/v1/terms`
- `GET /wp-json/magick-ai-adapter/v1/taxonomy-terms`
- `GET /wp-json/magick-ai-adapter/v1/categories`
- `GET /wp-json/magick-ai-adapter/v1/tags`
- `GET /wp-json/magick-ai-adapter/v1/term`
- `GET /wp-json/magick-ai-adapter/v1/comments`
- `GET /wp-json/magick-ai-adapter/v1/internal-link-targets`
- `GET /wp-json/magick-ai-adapter/v1/post-stats`
- `GET /wp-json/magick-ai-adapter/v1/post-revisions`
- `GET /wp-json/magick-ai-adapter/v1/post-meta`
- `GET /wp-json/magick-ai-adapter/v1/pages`
- `GET /wp-json/magick-ai-adapter/v1/page`
- `GET /wp-json/magick-ai-adapter/v1/page-structure`
- `GET /wp-json/magick-ai-adapter/v1/pages-tree`
- `GET /wp-json/magick-ai-adapter/v1/content-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/site-operations-dashboard`
- `GET /wp-json/magick-ai-adapter/v1/publishing-calendar-context`
- `GET /wp-json/magick-ai-adapter/v1/media-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/taxonomy-inventory-health`

Generic read:

- `POST /wp-json/magick-ai-adapter/v1/run-read-ability`

Governance:

- `GET /wp-json/magick-ai-adapter/v1/capabilities`
- `GET /wp-json/magick-ai-adapter/v1/proposals`
- `GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}`
- `POST /wp-json/magick-ai-adapter/v1/proposals`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/commit-preflight`

## Security

All routes require `manage_options` through WordPress REST authentication.
OpenClaw should connect with a dedicated administrator Application Password for
the local PoC. Narrower adapter identity and scope can be added after the first
product flow is proven.

The OpenClaw Connection admin page may display endpoint URLs, health state,
example requests, and a handoff prompt. It may create a normal WordPress
Application Password for the current administrator and show the raw password
once in a handoff page. It must not store raw Application Passwords in adapter
options, create Core app keys, persist connection state, approve proposals, or
change Core/Abilities ownership.

## Application Password Handoff

Handoff data:

- WordPress site URL.
- Adapter base URL.
- WordPress username for the dedicated OpenClaw account.
- Application Password delivered through an approved secret channel.

For the current LocalWP development site only, the WordPress administrator
browser login is username `1` and password `1`. This local-only password is for
admin browser access and Application Password creation; OpenClaw REST
configuration should use a dedicated Application Password.

OpenClaw must use WordPress REST Basic Auth:

```text
Authorization: Basic base64(username:application_password)
```

Connection check order:

1. `GET /health`.
2. `GET /help`.
3. `GET /capabilities`.
4. direct-read shortcut or `POST /run-read-ability`.
5. proposal-required `POST /proposals`.
6. proposal status polling with `GET /proposals/{proposal_id}`.
7. pending proposal decisions handled in `WordPress -> Magick AI Core`.
8. rejected proposal stops the flow.
9. approved proposal `POST /proposals/{proposal_id}/commit-preflight`.

## Proposal-Required Write Flow

OpenClaw must treat Core as the only proposal and approval truth:

1. Read `/capabilities` and select a real `ability_id` where
   `governance_mode=proposal_required`.
2. Send `POST /proposals` with the real `ability_id`, dry-run style `input`,
   rendered or structured `preview`, and `caller` metadata.
3. Poll `GET /proposals/{proposal_id}` through the adapter for Core status.
4. If `status=pending`, prompt the user to approve or reject in
   `WordPress -> Magick AI Core`.
5. If `status=rejected`, stop and show the rejection state or reason returned
   by Core.
6. If `status=approved`, call
   `POST /proposals/{proposal_id}/commit-preflight`.
7. Stop at the returned Core preflight decision.

Adapter invariants:

- It does not approve or reject.
- It does not store proposal state.
- It does not execute final WordPress mutations.
- It preserves `core_proxy_execute=false`.
- It preserves `commit_execution=false`.
- It exposes `approval_proxy_enabled=false`.
- It exposes `approval_surface=magick_ai_core_admin`.

Future approval or rejection proxying is out of this default contract. It may
only be added as a separate explicit trusted-host policy and ADR-backed feature,
disabled by default, with independent Core scopes for approval and rejection.
