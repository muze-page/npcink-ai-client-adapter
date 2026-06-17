# Adapter Admin Surface Standard

Status: active for `Npcink -> Adapter`.

## Purpose

The Adapter admin page is the OpenClaw connection surface. It gives OpenClaw
one productized WordPress entry point while preserving Core as governance truth
and Abilities API as the ability execution source.

## Default View

The default page should answer:

- is Adapter healthy;
- which site this client should use;
- how to connect a local client through the signed key-pair path;
- where to fall back to a simple Application Password connection;
- how many signed key-pair devices are currently authorized.

Primary action:

- `Secure key-pair connection`, with `Copy connect command` as the primary
  action and `Manage devices` as the secondary action.
- `Simple key connection`, with the Application Password creation form as a
  secondary disclosure for clients that have a dedicated secret field.

Default device management:

- show active authorized signed key-pair devices as a compact count;
- keep the device table behind an explicit disclosure;
- allow admins to revoke authorized signed key-pair devices with confirmation.

Default copyable values:

- Adapter base URL;
- signed key-pair connect command.

Do not show command bodies, status commands, route catalogs, manifest URLs,
environment placeholders, proposal lookup, or developer boundary notes in the
default connection screen.

## Advanced Details

Keep these behind explicit advanced sections:

- diagnostics URLs;
- read shortcut route catalog, showing only a short preview before the full
  list;
- proposal route examples;
- AI Request Logs correlation smoke details;
- disabled approval/rejection stub explanation;
- failure code mapping;
- verbose handoff prompt and local AI client session text.

## Created Handoff

After creating an Application Password, the completion page must prioritize the
one-time secret:

- show the Application Password in the first focused panel;
- provide a `Copy Application Password` action;
- keep manifest, env placeholders, WorkBuddy setup, and full handoff text
  behind detail disclosures;
- never pass the raw password into copied manifest, env placeholder, WorkBuddy,
  or handoff text.

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
