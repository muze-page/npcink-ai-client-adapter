# Adapter Developer Reference

This document keeps integration and diagnostics details out of the default
WordPress admin connection page.

## Connection Routes

- Adapter base: `/wp-json/npcink-openclaw-adapter/v1`
- Health: `/wp-json/npcink-openclaw-adapter/v1/health`
- Help: `/wp-json/npcink-openclaw-adapter/v1/help`
- Capabilities: `/wp-json/npcink-openclaw-adapter/v1/capabilities`
- Connection manifest:
  `/wp-json/npcink-openclaw-adapter/v1/connection/manifest`
- Key-pair management:
  `/wp-json/npcink-openclaw-adapter/v1/connection/key-pairs`

## Proposal Routes

Governed writes remain Core-backed. Adapter exposes proposal and
commit-preflight routes for OpenClaw clients, but Core remains the approval,
storage, and audit truth.

- List proposals: `GET /proposals`
- Proposal detail: `GET /proposals/{proposal_id}`
- Create proposals from a plan: `POST /proposals/from-plan`
- Commit preflight: `POST /proposals/{proposal_id}/commit-preflight`
- Approved execution: `POST /proposals/{proposal_id}/approve-and-execute`

Execution routes are final write paths. Use commit-preflight for dry-run-only
verification and call approved execution only after Core approval.

## Read Shortcuts

The current read shortcut catalog is exposed through the Adapter help and
capabilities routes. Do not duplicate that catalog in the admin page.

Use:

```text
GET /wp-json/npcink-openclaw-adapter/v1/help
GET /wp-json/npcink-openclaw-adapter/v1/capabilities
```

## AI Request Logs Correlation

When a client has a `proposal_id` or `commit-preflight` correlation ID, pass it
as `log_context` on `POST /run-read-ability` or as query fields on read
shortcuts. Adapter can then add the identifiers to AI Request Logs context
through `wpai_request_log_context`.

Core Governance Audit is the governance log. AI Request Logs are provider
request correlation logs.

## Admin Boundary

The wp-admin connection page should stay focused on two operator jobs:

- secure signed key-pair connection;
- simple WordPress Application Password connection.

Diagnostics, route catalogs, proposal examples, and verbose handoff prompts are
developer reference material. They should not turn Adapter into a second Core
approval screen or a workflow control plane.

Adapter must preserve:

```text
core_proxy_execute=false
commit_execution=false
```
