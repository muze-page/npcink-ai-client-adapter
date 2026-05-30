# AGENTS.md — Magick AI Adapter

## Product Boundary

Magick AI Adapter is the thin OpenClaw channel layer.

It owns:

- OpenClaw-facing REST routes;
- routing read ability execution to WordPress Abilities API;
- routing proposal and commit-preflight requests to Magick AI Core;
- small health and diagnostics responses for adapter readiness.

It does not own:

- WordPress ability definitions or callbacks;
- Core proposal storage, approval, or audit truth;
- final write execution policy;
- workflow runtime, queues, MCP runtime, or Agent Gateway catalogs;
- provider credentials, model routing, prompts, or product UX.

## Development Rules

- Keep this plugin thin. If a feature needs ability definitions, change
  `magick-ai-abilities`.
- If a feature needs approval state, change `magick-ai-core`.
- If a feature needs workflow runtime or long-running task orchestration, write
  a boundary note before implementing it here.
- Use WordPress REST authentication and capability checks.
- Run `composer test:all` before committing.

## Current Integration Contract

Read operations:

```text
OpenClaw -> magick-ai-adapter -> WordPress Abilities API
```

Governed write operations:

```text
OpenClaw -> magick-ai-adapter -> magick-ai-core proposal/preflight
```

The adapter must preserve Core's current boundary:

- `core_proxy_execute=false`
- `commit_execution=false`
