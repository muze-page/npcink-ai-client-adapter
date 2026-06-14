# Adapter Admin Surface Standard

Status: active for `Npcink -> Adapter`.

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
- how to create a simple Application Password connection;
- where the higher-security signed key-pair CLI lives.

Primary action:

- `Simple connection`, with the Application Password creation button as the
  primary action.
- `Higher security: signed key-pair`, visible as the security recommendation.

Default copyable values:

- Adapter base URL;
- WordPress username;
- connection manifest URL;
- client env placeholders without secrets.

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

## Time Display

Adapter may receive UTC timestamps from Core, local key-pair records, or REST
responses. Store and proxy those machine values without changing their contract
semantics, but format any timestamp shown in the wp-admin page through the
WordPress site timezone.

Visible admin timestamps must use `Y-m-d H:i:s`. Do not print raw UTC strings,
ISO timestamps, or `*_gmt` values directly in the admin UI unless the label
explicitly describes a machine/debug value.

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
any execution remains supported after Core approval and commit-preflight.
