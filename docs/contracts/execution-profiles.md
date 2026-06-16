# Execution Profiles Contract

Status: accepted Adapter execution profile contract.

Adapter execution profiles are the only Adapter-owned final-write allowlist.
They are not ability definitions. Ability definitions and callbacks remain in
`npcink-abilities-toolkit`; approval, audit, and commit-preflight truth remain
in `npcink-governance-core`.

## Profile Rule

Adapter may execute a write only when all of these are true:

- Core has an approved proposal.
- Core commit-preflight authorizes the proposal item.
- The caller supplies explicit commit intent.
- The proposal item targets an ability id in Adapter's execution profile
  registry.
- The execution input passes the profile's supported field, required field,
  enum, size, and safety validation.

The registry is implemented in
[`../../includes/Rest/Execution_Profile_Registry.php`](../../includes/Rest/Execution_Profile_Registry.php).

## Machine-Readable Fingerprints

`GET /health`, `GET /help`, and `GET /connection/manifest` expose:

```text
execution_profile_registry_version
execution_profile_registry_hash
supported_execute_ability_ids_hash
max_execution_actions
core_proxy_execute=false
commit_execution=false
```

Clients can compare these fields against acceptance-tested builds. They do not
authorize execution by themselves.

## Block Theme Template Updates

There is no separate Adapter profile named
`block-theme-template.update_draft`. The current contract uses explicit Toolkit
ability ids:

```text
npcink-abilities-toolkit/update-template-blocks
npcink-abilities-toolkit/upsert-template-blocks
npcink-abilities-toolkit/update-template-part-blocks
```

For product discussions, `block-theme-template.update_draft` may be treated as a
human-facing alias for those three ability-id profiles after Core approval and
commit-preflight. Do not add a generic template write executor for that alias.

## Source Documents

- [`../openclaw-adapter-contract.md`](../openclaw-adapter-contract.md)
- [`../openclaw-block-theme-site-builder-recipe.md`](../openclaw-block-theme-site-builder-recipe.md)
- [`../openclaw-batch-execution-policy.md`](../openclaw-batch-execution-policy.md)
