# WordPress.org Listing Draft - English

## Plugin Name

Magick AI Adapter

## Short Description

Thin OpenClaw adapter for Magick AI Core governance and WordPress Abilities API
routing.

## Tags

ai, governance, automation, rest-api, abilities

## Description

Magick AI Adapter gives OpenClaw one WordPress REST namespace for Magick AI
Core governance and WordPress Abilities API routing.

It reads Core capability guidance, runs approved direct-read abilities through
the WordPress Abilities API, creates Core proposals for write or destructive
operations, and orchestrates one user-triggered approve-and-execute path
through Core.

Adapter is intentionally thin. It is the channel layer, not the ability layer,
governance layer, cloud connector, workflow runtime, MCP server, model client,
or generic final write executor.

In productized OpenClaw setup, OpenClaw connects to Adapter. Magick AI Core
remains the governance backend for proposal storage, approval, commit
preflight, and audit. Magick AI Abilities and other providers remain the owners
of ability definitions, callbacks, schemas, and permissions.

## Key Features

- Provide one WordPress REST namespace for OpenClaw.
- Expose Adapter health, help, capability, read, proposal, and handoff routes.
- Read Core governance guidance before direct-read ability execution.
- Route direct-read abilities through the WordPress Abilities API.
- Forward governed write requests to Magick AI Core proposal routes.
- Preserve Core approval, commit-preflight, and audit boundaries.
- Create a one-time WordPress Application Password and non-secret connection manifest for OpenClaw.
- Keep Adapter thin instead of becoming an ability registry, workflow runtime,
  MCP server, or generic write executor.

## Who This Is For

- WordPress administrators connecting OpenClaw to a Magick AI site.
- OpenClaw environments that need one WordPress REST entry point.
- Host setups that use Core for governance and Abilities API providers for
  execution contracts.
- Developers who want a clear separation between channel, governance, ability,
  and cloud layers.

## Requirements

- WordPress 7.0 or later.
- PHP 8.0 or later.
- Magick AI Core for governance.
- A WordPress Abilities API provider, such as Magick AI Abilities, for ability
  definitions and callbacks.

## Series Boundary

In the Magick AI plugin family:

- Magick AI Abilities owns ability definitions and callbacks.
- Magick AI Core owns governance, approval, preflight, and audit.
- Magick AI Adapter owns OpenClaw channel adaptation.
- Magick AI Cloud Addon owns cloud service connection.

This separation keeps Adapter focused on connection and routing, while Core
remains the governance truth source and Abilities remains the capability
contract layer.
