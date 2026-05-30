# Magick AI Adapter

Magick AI Adapter is a thin OpenClaw channel plugin for WordPress.

It gives OpenClaw one WordPress REST namespace that can:

- read Magick AI Core capability guidance;
- run approved direct-read abilities through WordPress Abilities API;
- create Core proposals for write or destructive operations;
- call Core commit preflight after WordPress approval.

It does not define abilities, store approval state, run workflows, approve
proposals, or execute final write mutations by itself.

## Runtime Boundary

Layer ownership:

| Layer | Plugin | Responsibility |
| --- | --- | --- |
| Ability layer | `magick-ai-abilities` | Registers canonical abilities, schemas, callbacks, permissions, and dry-run previews. |
| Governance layer | `magick-ai-core` | Discovers abilities, classifies risk, stores proposals, handles approval/preflight, and audits governance decisions. |
| Channel layer | `magick-ai-adapter` | Gives OpenClaw a small REST adapter that calls Core and WordPress Abilities API. |

## REST Surface

All routes require `manage_options` through normal WordPress REST
authentication, such as an administrator Application Password.

- `GET /wp-json/magick-ai-adapter/v1/health`
- `GET /wp-json/magick-ai-adapter/v1/capabilities`
- `POST /wp-json/magick-ai-adapter/v1/run-read-ability`
- `GET /wp-json/magick-ai-adapter/v1/site-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-diagnostics-summary`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipes`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipe?recipe_id=workflow/...`
- `POST /wp-json/magick-ai-adapter/v1/proposals`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/commit-preflight`

## OpenClaw Flow

Read-only abilities:

1. OpenClaw calls Adapter `/capabilities`.
2. Adapter relays Core guidance.
3. If `governance_mode=direct_read`, OpenClaw calls Adapter read endpoints.
4. Adapter runs the ability through WordPress Abilities API.

Write or destructive abilities:

1. OpenClaw calls Adapter `/capabilities`.
2. If `governance_mode=proposal_required`, OpenClaw calls Adapter
   `/proposals`.
3. WordPress administrator approves or rejects in Core.
4. OpenClaw calls Adapter `/proposals/{proposal_id}/commit-preflight`.
5. Adapter relays Core `commit_execution=false`.

## Non-Goals

This plugin must not become:

- an ability registry;
- an approval store;
- a workflow runtime;
- an MCP server;
- an Agent Gateway catalog;
- a generic final write executor;
- a replacement for WordPress Abilities API.

## Development

Run static checks:

```bash
composer test:all
```

Run the LocalWP smoke test:

```bash
composer smoke:wp
```
