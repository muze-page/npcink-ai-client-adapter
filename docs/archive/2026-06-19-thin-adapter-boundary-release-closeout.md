# Thin Adapter Boundary Release Closeout

Date: 2026-06-19

## Context

This project is now scoped as a thin OpenClaw channel layer. Adapter owns the
OpenClaw-facing REST channel, generic read ability dispatch, Core proposal and
commit-preflight routing, explicit post-Core execution profiles, and small
readiness responses.

Adapter no longer owns generic ability shortcuts, workflow recipe shortcuts,
provider/model smoke execution, Cloud connector routes, Cloud run/result truth,
or media derivative Cloud facade routes.

## Removed or Simplified

- Removed legacy REST surfaces from the runtime controller:
  - provider log correlation smoke route;
  - workflow recipe route shortcuts;
  - direct-read shortcut route catalog;
  - media metadata optimization shortcut;
  - media derivative run/result/artifact/proposal-payload facade routes.
- Removed verbose Admin connection page surfaces:
  - proposal lookup UI;
  - long OpenClaw/WorkBuddy handoff text builders;
  - low-frequency diagnostic copy blocks.
- Removed the old legacy bootstrap entry file from the source and release
  surface.
- Kept `sj/` and `packages/adapter-cli/` in source for local/project use, but
  kept them outside the release package.

## Current Route Posture

The retained Adapter contract centers on:

- `GET /health`;
- `GET /help`;
- `GET /capabilities`;
- `POST /run-read-ability`;
- sensitive read request routes;
- Core proposal/status routes;
- Core plan-to-proposal route;
- approved proposal execution routes;
- proposal-specific media optimization readiness.

Read shortcuts and workflow recipe access now go through `POST /run-read-ability`
with explicit `ability_id` and bounded input.

## Cloud Boundary

Cloud runtime and media derivative transport belong to `npcink-cloud-addon`.
Adapter may check proposal-specific readiness and may execute the explicit
post-Core `npcink-abilities-toolkit/adopt-cloud-media-derivative` profile after
Core approval and commit preflight. Adapter must not expose Cloud run/result,
artifact preview, signing, settings, or connector truth.

## Testing and Release Verification

Completed checks:

- `composer test:all` passed.
- Local WordPress smoke passed with explicit Local.app DB socket:
  - `WP_PATH=/Users/muze/Local Sites/magick-ai/app/public`
  - `WP_CLI=/tmp/wp-cli.phar`
  - Local PHP 8.2.29
  - Local MySQL socket under `~/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`
- `composer plugin-check:release` passed with no errors.
- `composer package:release` produced `build/npcink-ai-client-adapter.zip`.
- Release zip inspection confirmed:
  - canonical `npcink-ai-client-adapter.php` is present;
  - old `npcink-openclaw-adapter.php` is absent;
  - `tests/`, `docs/`, `sj/`, `packages/adapter-cli/`, `AGENTS.md`, and
    `composer.json` are absent from the package;
  - old provider/workflow/read-shortcut/media-derivative route strings are
    absent from packaged runtime files.

## Result

The Adapter release surface is now aligned with the stated product boundary:
thin OpenClaw channel, Core-governed writes, WordPress Abilities read dispatch,
and no duplicate Cloud, workflow, provider, or ability registry ownership.
