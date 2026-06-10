# WordPress.org Listing Draft - English

## Plugin Name

Npcink AI Client Adapter

## Short Description

Thin AI client adapter for Npcink Governance Core and WordPress Abilities API
routing.

## Tags

ai, governance, automation, rest-api, abilities

## Description

Npcink AI Client Adapter gives OpenClaw-compatible and similar AI clients one WordPress REST namespace for Npcink
Core governance and WordPress Abilities API routing.

It reads Core capability guidance, runs approved direct-read abilities through
the WordPress Abilities API, creates Core proposals for write or destructive
operations, and orchestrates one user-triggered approve-and-execute path
through Core.

Adapter is intentionally thin. It is the channel layer, not the ability layer,
governance layer, cloud connector, workflow runtime, MCP server, model client,
or generic final write executor.

In productized AI client setup, OpenClaw-compatible and similar clients connect to Adapter. Npcink Governance Core
remains the governance backend for proposal storage, approval, commit
preflight, and audit. Npcink Abilities Toolkit and other providers remain the owners
of ability definitions, callbacks, schemas, and permissions.

## Key Features

- Provide one WordPress REST namespace for OpenClaw-compatible and similar AI clients.
- Expose Adapter health, help, capability, read, proposal, and handoff routes.
- Read Core governance guidance before direct-read ability execution.
- Route direct-read abilities through the WordPress Abilities API.
- Forward governed write requests to Npcink Governance Core proposal routes.
- Preserve Core approval, commit-preflight, and audit boundaries.
- Create a one-time WordPress Application Password and non-secret connection manifest for AI clients.
- Keep Adapter thin instead of becoming an ability registry, workflow runtime,
  MCP server, or generic write executor.

## Who This Is For

- WordPress administrators connecting OpenClaw-compatible or similar AI clients to a Npcink site.
- AI client environments that need one WordPress REST entry point.
- Host setups that use Core for governance and Abilities API providers for
  execution contracts.
- Developers who want a clear separation between channel, governance, ability,
  and cloud layers.

## Requirements

- WordPress 7.0 or later.
- PHP 8.0 or later.
- Npcink Governance Core for governance.
- A WordPress Abilities API provider, such as Npcink Abilities Toolkit, for ability
  definitions and callbacks.

## Series Boundary

In the Npcink plugin family:

- Npcink Abilities Toolkit owns ability definitions and callbacks.
- Npcink Governance Core owns governance, approval, preflight, and audit.
- Npcink AI Client Adapter owns AI client channel adaptation.
- Npcink Cloud Addon owns cloud service connection.

This separation keeps Adapter focused on connection and routing, while Core
remains the governance truth source and Abilities remains the capability
contract layer.
