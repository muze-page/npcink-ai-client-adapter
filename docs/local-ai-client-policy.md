# Local AI Client Policy

Status: accepted Adapter client contract.

Npcink OpenClaw Adapter can support OpenClaw-compatible local AI clients, but it
does not control which AI client a customer chooses or how that client reasons
internally. Adapter-owned safety must therefore be expressed as enforceable
interfaces and fail-closed server behavior, not as prompt-only instructions.

## Adapter-Owned Controls

Adapter owns these enforceable controls:

- WordPress REST authentication and capability checks.
- Signed key-pair request verification for the local CLI profile flow.
- Adapter-relative route validation in the CLI request wrapper.
- CLI output redaction for local profile paths, key ids, connection ids,
  public/private keys, authorization headers, signing headers, cookies, tokens,
  passwords, and secrets.
- Machine-readable `client_policy` on `GET /connection/manifest`,
  `GET /health`, and `GET /help`.
- Sensitive read routing through Adapter `POST /read-requests`,
  `GET /read-requests/{request_id}`, and `POST /run-read-ability`.
- Core read-preflight validation immediately before sensitive reads.
- Proposal, approval, commit-preflight, and explicit commit intent for writes.

## Customer-Selected Client Boundary

Adapter does not own:

- The customer's AI client selection.
- The customer's client-side prompts, model routing, memory, plugins, or tool
  execution policy.
- Generic filesystem, database, log, or WordPress-internal inspection outside
  Adapter routes.
- Approval truth, read-grant truth, or commit-preflight truth; those remain in
  Npcink Governance Core.

Because of that boundary, prompts are only convenience guidance. They are not a
security boundary. A client that ignores `client_policy` should still fail at
the Adapter/Core boundary when it attempts forbidden routes, missing grants, or
unapproved writes.

## Client Policy Contract

Clients should read `client_policy` from `/help` or `/connection/manifest`
before selecting routes. The current schema is:

```json
{
  "schema_version": "npcink_openclaw_adapter_client_policy.v1",
  "client_posture": "adapter_only_fail_closed",
  "forbidden_outputs": [],
  "forbidden_local_access": [],
  "allowed_transport": {},
  "sensitive_read_flow": {},
  "write_flow": {},
  "recommended_cli": {}
}
```

Required client behavior:

- Treat `forbidden_outputs` as values that must not be displayed, summarized, or
  copied into chat, logs, files, or proposal payloads.
- Treat `forbidden_local_access` as non-negotiable local access boundaries.
- Use only Adapter CLI commands and Adapter-relative routes when
  `allowed_transport.adapter_cli_only=true` and
  `allowed_transport.adapter_relative_routes_only=true`.
- If a capability indicates sensitive read authorization, create a read request,
  wait for Core approval, then call `read-ability` with the same `ability_id`,
  identical input, and `read_request_id`.
- If the sensitive read input changes, create a new read request.
- For writes, use Core proposals, Core approval/preflight, and an Adapter final
  write route only after explicit operator commit intent.

## Preferred Local CLI Commands

Use these narrow commands for local client handoff:

```bash
npcink-openclaw-adapter status --profile=local
npcink-openclaw-adapter request --profile=local GET /help
npcink-openclaw-adapter request --profile=local GET /capabilities
npcink-openclaw-adapter read-request create --profile=local --ability-id=ABILITY_ID --input-file=/tmp/read-input.json --purpose=PURPOSE --data-classes=CLASS[,CLASS]
npcink-openclaw-adapter read-request status --profile=local READ_REQUEST_ID
npcink-openclaw-adapter read-ability --profile=local --ability-id=ABILITY_ID --input-file=/tmp/read-input.json --read-request-id=READ_REQUEST_ID
```

These helpers are preferred over asking a client to hand-build sensitive read
route bodies because they preserve Adapter-relative routing, signed transport,
output redaction, and the Core grant binding shape.
