=== Npcink AI Client Adapter ===
Contributors: muze233
Tags: ai, governance, automation, rest-api
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: npcink-abilities-toolkit
Stable tag: 0.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thin AI client channel plugin for Npcink Governance Core and WordPress Abilities execution.

== Description ==

Npcink AI Client Adapter gives OpenClaw-compatible and similar AI clients one WordPress REST namespace for reading Npcink Governance Core capability guidance, routing approved read abilities through the WordPress Abilities API, and forwarding governed write requests to Npcink Governance Core proposal and commit-preflight endpoints.

Adapter is intentionally thin. It does not define abilities, store approval truth, run workflow queues, or execute final write mutations without Core approval and commit-preflight.

Adapter can be distributed as the Npcink AI suite entry plugin while Core and Toolkit remain separate plugins. Adapter health reports missing dependencies and dependency-sensitive routes fail closed with a structured missing dependency error.

Adapter exposes a machine-readable `client_policy` on health, help, and the connection manifest so OpenClaw-compatible clients, Qclaw-style clients, WorkBuddy-style clients, or other local AI clients can consume explicit route, output, sensitive-read, and write-flow boundaries. The local CLI also redacts profile paths, key ids, signing headers, tokens, passwords, and secrets from output.

Current governed execution support covers individually approved proposal execution for explicit Adapter profiles, including `npcink-abilities-toolkit/trash-post`, `npcink-abilities-toolkit/create-draft`, `npcink-abilities-toolkit/update-post`, `npcink-abilities-toolkit/update-post-blocks`, `npcink-abilities-toolkit/update-template-blocks`, `npcink-abilities-toolkit/upsert-template-blocks`, `npcink-abilities-toolkit/update-template-part-blocks`, `npcink-abilities-toolkit/set-post-terms`, `npcink-abilities-toolkit/reply-comment`, and `npcink-abilities-toolkit/approve-comment`.

== Installation ==

1. Install and activate `npcink-abilities-toolkit`.
2. Install and activate Npcink Governance Core.
3. Install and activate Npcink AI Client Adapter.
4. Open Npcink > Adapter to create the local AI client handoff.

== Frequently Asked Questions ==

= Does Adapter approve proposals? =

Adapter provides an explicit `approve-and-execute` user action for supported execution profiles. Npcink Governance Core remains the governance backend for proposal storage, approval, commit-preflight, and audit.

= Does Adapter execute arbitrary abilities? =

Adapter executes supported execution profiles after Core approval and commit-preflight. Current profiles include draft, post update, taxonomy, media metadata/upload/featured-image, media derivative, comment, and bounded destructive operations documented in the OpenClaw batch execution policy.

== Changelog ==

= 0.2.1 =

* Add contract snapshot smoke coverage across health, help, and connection manifest responses.
* Reject batch write actions that request `core_proxy_execute=true` before Adapter execution.
* Expand negative smoke coverage for `core_proxy_execute` and `commit_execution` batch fail-closed behavior.

= 0.2.0 =

* Add machine-readable Adapter contract metadata and stable contract hashes on health, help, and manifest responses.
* Add the local AI client acceptance command for non-destructive Adapter/Core boundary checks.
* Align block theme visual acceptance, batch review feedback, and release package smoke documentation with the governed execution profile boundary.

= 0.1.1 =

* Add local AI client CLI helper commands, output redaction, and machine-readable client policy.

= 0.1.0 =

* Initial thin AI client adapter for Npcink Governance Core and WordPress Abilities API routing.
