# AGENTS.md — Npcink OpenClaw Adapter

## Product Boundary

Npcink OpenClaw Adapter is the thin OpenClaw channel layer.

It owns:

- OpenClaw-facing REST routes;
- routing read ability execution to WordPress Abilities API;
- routing proposal and commit-preflight requests to Npcink Governance Core;
- explicit post-Core execution profile policy for allowlisted approved writes;
- small health and diagnostics responses for adapter readiness.

It does not own:

- WordPress ability definitions or callbacks;
- Core proposal storage, approval, or audit truth;
- generic final write authority outside the explicit post-Core execution
  profile allowlist;
- workflow runtime, queues, MCP runtime, or Agent Gateway catalogs;
- provider credentials, model routing, prompts, or product UX.

## Development Rules

- Keep this plugin thin. If a feature needs ability definitions, change
  `npcink-abilities-toolkit`.
- If a feature needs approval state, change `npcink-governance-core`.
- If a feature needs a new final write, add it only as an explicit execution
  profile after Core approval and commit-preflight; do not add a generic final
  write executor.
- If a feature needs workflow runtime or long-running task orchestration, write
  a boundary note before implementing it here.
- Provider/model/prompt execution in Adapter is limited to the
  admin-authenticated AI Request Logs correlation smoke route. It must stay a
  manual diagnostics route, not model routing, prompt management, product UX, or
  production workload execution.
- If a feature needs Cloud runtime or Cloud monitoring, call the standalone
  `npcink-cloud-addon` public PHP seam. Do not add Adapter-owned Cloud
  settings, signing clients, `/cloud/*` routes, or Cloud execution truth.
  Do not add Adapter-owned Cloud connector routes.
- Use WordPress REST authentication and capability checks.
- For WordPress plugin development, smoke tests, Plugin Check, and plugin
  activation/status checks, prefer local WP-CLI when a WordPress site is
  involved. This machine has global WP-CLI at `/opt/homebrew/bin/wp`; before
  using it, run `command -v wp` and `wp --info` to confirm the active binary
  and runtime.
- Never assume the current working directory is the WordPress root. Pass an
  explicit `WP_PATH` or `--path=<wordpress-root>` for every WP-CLI command.
  The common Local.app development root is
  `/Users/muze/Local Sites/magick-ai/app/public`, but verify it for the task.
- If a Local.app site has `DB_HOST=localhost` and WP-CLI cannot connect to the
  database, find the matching Local run socket, usually under
  `$HOME/Library/Application Support/Local/run/*/mysql/mysqld.sock`, and inject
  it with `WP_CLI_MYSQL_SOCKET` or the equivalent PHP
  `mysqli.default_socket` setting.
- Run `composer test:all` before committing.
- For Plugin Check / PCP, use `composer plugin-check:release` so checks target
  the release/package surface defined by `.distignore`; do not delete
  development-only files such as `tests/` or `AGENTS.md` from the source tree
  just to satisfy a full-worktree scan.
- Use `composer package:release` to build the release zip from that same
  `.distignore` boundary.

## Current Integration Contract

Read operations:

```text
OpenClaw -> npcink-openclaw-adapter -> WordPress Abilities API
```

Governed write operations:

```text
OpenClaw -> npcink-openclaw-adapter -> npcink-governance-core proposal/preflight
```

The adapter must preserve Core's current boundary:

- `core_proxy_execute=false`
- `commit_execution=false`
