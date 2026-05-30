# OpenClaw Adapter Contract

Status: initial productization contract.

Magick AI Adapter gives OpenClaw a focused WordPress REST surface without
turning Magick AI Core into an execution proxy.

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

## First Product Routes

Read shortcuts:

- `GET /wp-json/magick-ai-adapter/v1/site-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-diagnostics-summary`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipes`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipe?recipe_id=workflow/...`

Generic read:

- `POST /wp-json/magick-ai-adapter/v1/run-read-ability`

Governance:

- `GET /wp-json/magick-ai-adapter/v1/capabilities`
- `POST /wp-json/magick-ai-adapter/v1/proposals`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/commit-preflight`

## Security

All routes require `manage_options` through WordPress REST authentication.
OpenClaw should connect with a dedicated administrator Application Password for
the local PoC. Narrower adapter identity and scope can be added after the first
product flow is proven.
