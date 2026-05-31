# OpenClaw Adapter Consumer Readiness

Status: complete
Date: 2026-05-31

## Dependency Snapshot

- `magick-ai-core` `master`: `19cca44 Address Plugin Check release blockers`
- `magick-ai-adapter` `master`: `4ad2a0c Finalize OpenClaw Adapter handoff surface`
- `magick-ai-abilities` `master`: `a1a3cfb Split article production read methods`

The snapshot records the local and remote `master` state used for this
readiness closeout.

## Product Entry

Productized OpenClaw only connects to Adapter:

```text
OpenClaw -> magick-ai-adapter -> magick-ai-core governance
OpenClaw -> magick-ai-adapter -> WordPress Abilities API reads/execution
```

Core is the governance authority behind Adapter. Core owns proposal state,
approval/rejection truth, commit-preflight, and governance audit. Abilities own
schemas, callbacks, permissions, and direct-read execution.

Adapter owns the OpenClaw-facing REST channel and the bounded orchestration
needed to connect OpenClaw to Core governance and WordPress Abilities API.

## Verified Routes

The consumer readiness pass verified these Adapter routes:

- `GET /health`
- `GET /help`
- `GET /capabilities`
- direct read shortcuts
- diagnostics shortcuts
- `POST /proposals`
- `GET /proposals`
- `GET /proposals/{proposal_id}`
- `POST /proposals/{proposal_id}/commit-preflight`
- `POST /proposals/{proposal_id}/approve-and-execute`
- `POST /proposals/{proposal_id}/approve` and
  `POST /proposals/{proposal_id}/reject` as disabled HTTP 403 stubs

## Verified Loop

The readiness pass verified:

- capabilities discovery;
- direct read;
- diagnostics read;
- proposal create, list, and detail;
- Core approval through Adapter `approve-and-execute`;
- Core commit-preflight before execution;
- allowlisted final execution for `magick-ai/trash-post`;
- rejected proposals do not execute;
- preflight-blocked proposals do not execute;
- non-allowlisted proposals do not execute;
- AI Request Logs and Core Governance Audit correlation by `proposal_id` and
  `correlation_id`;
- returned `proposal_id`, `correlation_id`, `ability_id`, and
  `adapter_request_id` on the execution path.

The equivalent HTTP client pass used a temporary WordPress Application Password
and connected to Adapter for the OpenClaw-facing flow. The Core Audit lookup in
that pass was verification-only and is not part of the OpenClaw runtime
connection contract.

## Boundary

Adapter remains a thin OpenClaw channel layer:

- Adapter is not an MCP runtime.
- Adapter is not a workflow runtime.
- Adapter does not own Core proposal or audit truth.
- Adapter does not store provider credentials.
- Adapter does not generically execute arbitrary write abilities.
- Adapter execution allowlist currently only includes `magick-ai/trash-post`.

The disabled approve/reject stubs must remain disabled unless a future decision
explicitly changes the product boundary. The current productized user action is
`POST /proposals/{proposal_id}/approve-and-execute`.

## Next-Stage Rules

Do not default-expand the final execution allowlist.

Each new Adapter-executable write ability needs its own ADR or execution policy
document before implementation. That document must define:

- ability id;
- input fields;
- idempotency behavior;
- failure handling;
- rollback or compensation behavior;
- log fields;
- Core preflight conditions;
- smoke coverage.

Core must not add a final execution route. Core remains the governance
authority, while Adapter remains the OpenClaw product entry and WordPress
execution channel for explicitly allowlisted actions.
