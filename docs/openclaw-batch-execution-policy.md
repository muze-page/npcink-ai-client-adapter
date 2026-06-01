# OpenClaw Batch Execution Policy

Status: accepted
Date: 2026-05-31

## Context

OpenClaw can create or receive plan-shaped proposals whose `input` contains
multiple `write_actions[]`. The previous Adapter execution contract only
accepted a top-level `input.post_id`, so OpenClaw could reach Core
commit-preflight and still have no Adapter-owned way to perform the final
allowlisted WordPress write loop.

This policy extends the Adapter execution input contract without changing the
governance boundary.

## Decision

Adapter `approve-and-execute` and approved proposal execution accept either:

```json
{
  "post_id": 123
}
```

or:

```json
{
  "write_actions": [
    {
      "action_id": "trash-post-123",
      "target_ability_id": "magick-ai/trash-post",
      "input": {
        "post_id": 123,
        "dry_run": true,
        "commit": false
      },
      "requires_approval": true,
      "commit_execution": false,
      "proposal_ready": true
    }
  ]
}
```

V1 supports only the Adapter execution allowlist, currently
`target_ability_id=magick-ai/trash-post` and
`target_ability_id=magick-ai/create-draft`.

Adapter calls Core approval when needed, then calls Core commit-preflight once
for the proposal. Adapter requires Core approval commit authorization,
`commit_execution=false`, a non-blocked proposal item, and a correlation id.
Only after that does Adapter execute the normalized write actions through
WordPress Abilities API.

## Batch Rules

- Maximum batch size is 50 actions.
- Partial success is not a normal success mode.
- If any action is malformed, non-allowlisted, not proposal-ready, has
  unresolved `requires_input`, or has `commit_execution=true`, Adapter fails
  closed before executing any action.
- If Core preflight blocks the proposal, Adapter executes no actions.
- If an execution error occurs after prior actions have executed, Adapter stops
  and returns the upstream error with `executed_results` for inspection.
- Terms, comments, media delete, and arbitrary write abilities outside the
  Adapter execution allowlist are not executable in this V1 policy.

## Response Contract

Batch execution responses include:

- `execution_mode=batch_write_actions`
- `executed_count`
- `failed_count`
- `results[]`
- per-action `action_id`
- per-action `target_ability_id`
- per-action `post_id`
- per-action `post_status_before`
- per-action `post_status_after`

Single-post execution keeps the existing response fields and additionally
returns `execution_mode=single_post`, `post_ids`, `executed_count`,
`failed_count`, and `results[]`.

## Non-Goals

This policy does not:

- add a generic write executor;
- expand the Adapter execution allowlist without a dedicated implementation;
- make Core execute final WordPress mutations;
- turn Adapter into an MCP runtime or workflow runtime;
- make Adapter store approval state;
- turn disabled approve/reject stubs into real proxies.

## Next Changes

Each additional executable write ability requires a separate ADR or execution
policy update that defines ability id, input schema, idempotency, failure
handling, rollback or compensation behavior, log fields, Core preflight
conditions, and smoke coverage.
