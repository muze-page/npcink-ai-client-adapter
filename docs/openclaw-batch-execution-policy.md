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
      "target_ability_id": "npcink-abilities-toolkit/trash-post",
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

V1 supports only the Adapter execution allowlist, currently:

- `target_ability_id=npcink-abilities-toolkit/trash-post`
- `target_ability_id=npcink-abilities-toolkit/create-draft`
- `target_ability_id=npcink-abilities-toolkit/update-post`
- `target_ability_id=npcink-abilities-toolkit/patch-post-content`
- `target_ability_id=npcink-abilities-toolkit/update-post-blocks`
- `target_ability_id=npcink-abilities-toolkit/patch-setting-value`
- `target_ability_id=npcink-abilities-toolkit/set-post-seo-meta`
- `target_ability_id=npcink-abilities-toolkit/set-post-slug`
- `target_ability_id=npcink-abilities-toolkit/set-post-terms`
- `target_ability_id=npcink-abilities-toolkit/delete-term`
- `target_ability_id=npcink-abilities-toolkit/update-media-details`
- `target_ability_id=npcink-abilities-toolkit/upload-media-from-url`
- `target_ability_id=npcink-abilities-toolkit/set-post-featured-image`
- `target_ability_id=npcink-abilities-toolkit/optimize-media-asset`
- `target_ability_id=npcink-abilities-toolkit/replace-media-file`
- `target_ability_id=npcink-abilities-toolkit/restore-media-backup`
- `target_ability_id=npcink-abilities-toolkit/adopt-cloud-media-derivative`
- `target_ability_id=npcink-abilities-toolkit/rename-media-file`
- `target_ability_id=npcink-abilities-toolkit/delete-media-permanently`
- `target_ability_id=npcink-abilities-toolkit/reply-comment`
- `target_ability_id=npcink-abilities-toolkit/trash-comment`
- `target_ability_id=npcink-abilities-toolkit/approve-comment`

Adapter calls Core approval when needed, then calls Core commit-preflight once
for the proposal. Adapter requires Core approval commit authorization,
`commit_execution=false`, a non-blocked proposal item, and a correlation id.
Only after that does Adapter execute the normalized write actions through
WordPress Abilities API.

## Execution Profile Registry

Adapter keeps final write execution opt-in through local execution profiles.
Capability discovery is not enough to execute a write ability.

Each profile entry is the implementation checklist for one executable ability:

- `ability_id` is included in the derived execution allowlist;
- required scalar input checks are declared in the profile;
- enum input checks such as media `source_type` are declared in the profile;
- special Adapter-owned guards are declared by profile flags;
- dispatch behavior such as rebuilding post input is declared in the profile;
- result handling such as `post_id` backfill is declared in the profile;
- smoke tests cover success and Adapter-owned rejection behavior.

Adding a new executable write ability means adding or updating exactly one
profile entry plus the matching docs and smoke coverage. Abilities that are
discoverable through Core or WordPress Abilities API but have no Adapter
execution profile must fail closed.

For profiled abilities, Adapter validates proposal input at `POST /proposals`
before forwarding to Core. This validation rejects fields outside the profile
input schema and invalid enum values, then reuses the same profile checks again
for profiled `plan.write_actions[]` during `POST /proposals/from-plan` before
forwarding the plan to Core, and again at execution time for older or
externally-created proposals. Plan action schema failures return
`npcink_openclaw_adapter_plan_action_input_invalid` with `blocked_items[]` carrying
the action index, action id, target ability id, field, and reused
single-proposal block code. Plan action input may contain exact
`$outputs.<prior_action_id>.<field>` references for fields such as `post_id` or
`comment_id`; Adapter validates that they point to earlier actions, then
resolves and revalidates them during approved batch execution. Embedded output
tokens such as `prefix-$outputs.create.post_id` are invalid and fail closed.
Plan action ids must be unique before Adapter forwards the plan to Core.

## Batch Rules

- Maximum batch size is 200 actions.
- Partial success is not a normal success mode.
- If any action is malformed, non-allowlisted, not proposal-ready, has
  unresolved `requires_input`, or has `commit_execution=true`, Adapter fails
  closed before executing any action.
- If Core preflight blocks the proposal, Adapter executes no actions.
- If Adapter has already completed execution for the proposal, Adapter returns
  `npcink_openclaw_adapter_execution_already_completed` with the stored
  `execution_record` and executes no actions.
- If an execution error occurs after prior actions have executed, Adapter stops
  and returns the upstream error with `executed_results` for inspection.
- Terms, comments, media delete, and arbitrary write abilities outside the
  Adapter execution allowlist are not executable in this V1 policy.
- An action input may use an exact `$outputs.<prior_action_id>.<field>`
  reference to a previous action result in the same batch. Adapter resolves
  those references immediately before executing the action, then revalidates
  the resolved input against the target execution profile.
- Output references cannot point forward, cannot cross proposal boundaries, and
  cannot be embedded into larger strings.

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
- expand the Adapter execution allowlist without a dedicated execution profile
  and implementation;
- make Core execute final WordPress mutations;
- turn Adapter into an MCP runtime or workflow runtime;
- make Adapter store approval state;
- turn disabled approve/reject stubs into real proxies.

## Next Changes

Each additional executable write ability requires a separate ADR or execution
policy update that defines ability id, Adapter execution profile, input schema,
idempotency, failure handling, rollback or compensation behavior, log fields,
Core preflight conditions, and smoke coverage.
