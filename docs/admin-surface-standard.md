# Adapter Admin Surface Standard

Status: active for `Magick AI -> Adapter`.

## Purpose

The Adapter admin page is the OpenClaw connection surface. It gives OpenClaw
one productized WordPress entry point while preserving Core as governance truth
and Abilities API as the ability execution source.

## Default View

The default page should answer:

- is Adapter healthy;
- can Adapter reach Core capabilities;
- can Adapter reach WordPress Abilities API;
- what endpoint should OpenClaw use;
- how to create/export a one-time OpenClaw handoff.

Primary action:

- `Create OpenClaw handoff`.

Default copyable values:

- site URL;
- Adapter base URL;
- health URL;
- capabilities URL;
- help URL when useful.

## Advanced Details

Keep these behind explicit advanced sections:

- read shortcut route catalog;
- proposal route examples;
- diagnostics shortcuts;
- AI Request Logs correlation smoke details;
- disabled approval/rejection stub explanation;
- failure code mapping;
- verbose handoff prompt.

## Do Not Add

Adapter admin must not add:

- Core proposal approval tables or audit tables;
- ability definitions, schema editing, or callback ownership;
- Cloud Base URL/API key, entitlement, billing, or runtime settings;
- workflow runtime, queues, MCP runtime, Agent Gateway catalogs, router,
  prompt, preset, or provider credential settings;
- generic approval/rejection proxy UX.

## Verification

Static contracts should preserve that Adapter is a thin OpenClaw channel:
reads go through WordPress Abilities API, governed writes go through Core, and
any execution remains allowlisted after Core approval and commit-preflight.
