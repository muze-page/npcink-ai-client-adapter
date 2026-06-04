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
- how to connect through the local signed key-pair CLI;
- where the Application Password fallback lives for clients with a dedicated
  credential field.

Primary action:

- `Signed CLI connection`, with the connect command as the only primary action.
- `Application Password fallback`, collapsed by default.

Default copyable values:

- local CLI connect command;
- local CLI status command.

Default workflow bridge:

- Proposal ID status lookup through Adapter's read-only proposal proxy,
  collapsed by default unless a lookup is active;
- link to the matching Core approval detail;
- copyable Adapter status and approved-execution endpoints;
- status-specific next-step copy for `pending`, `approved`, `rejected`,
  `expired`, and `archived`.

## Advanced Details

Keep these behind explicit advanced sections:

- Adapter base URL, user, and connection manifest URL;
- authorized key-pair client management;
- fallback env placeholders without secrets;
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

The Proposal ID lookup is allowed because it keeps Adapter as the OpenClaw
operator entry point while Core remains approval truth. It must stay a focused
status bridge, not a duplicate Core review queue.

## Verification

Static contracts should preserve that Adapter is a thin OpenClaw channel:
reads go through WordPress Abilities API, governed writes go through Core, and
any execution remains allowlisted after Core approval and commit-preflight.
