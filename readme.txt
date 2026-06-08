=== Npcink OpenClaw Adapter ===
Contributors: muze233
Tags: ai, governance, automation, rest-api
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: npcink-abilities-toolkit
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thin OpenClaw channel plugin for Npcink Governance Core and WordPress Abilities execution.

== Description ==

Npcink OpenClaw Adapter gives OpenClaw one WordPress REST namespace for reading Npcink Governance Core capability guidance, routing approved read abilities through the WordPress Abilities API, and forwarding governed write requests to Npcink Governance Core proposal and commit-preflight endpoints.

Adapter is intentionally thin. It does not define abilities, store approval truth, run workflow queues, expose generic approve/reject proxying, or execute final write mutations without Core approval and commit-preflight.

Adapter can be distributed as the Npcink AI suite entry plugin while Core and Toolkit remain separate plugins. Adapter health reports missing dependencies and dependency-sensitive routes fail closed with a structured missing dependency error.

Current governed execution support is deliberately limited to individually approved proposal execution for explicit Adapter profiles, including `npcink-abilities-toolkit/trash-post`, `npcink-abilities-toolkit/create-draft`, `npcink-abilities-toolkit/update-post`, `npcink-abilities-toolkit/update-post-blocks`, `npcink-abilities-toolkit/set-post-terms`, `npcink-abilities-toolkit/reply-comment`, and `npcink-abilities-toolkit/approve-comment`.

== Installation ==

1. Install and activate `npcink-abilities-toolkit`.
2. Install and activate Npcink Governance Core.
3. Install and activate Npcink OpenClaw Adapter.
4. Open Npcink > Adapter to create the OpenClaw handoff.

== Frequently Asked Questions ==

= Does Adapter approve proposals? =

Only through the explicit `approve-and-execute` user action. Npcink Governance Core remains the governance backend for proposal storage, approval, commit-preflight, and audit, and Adapter standalone approve/reject stubs stay disabled.

= Does Adapter execute arbitrary abilities? =

No. Adapter only executes abilities that are explicitly allowlisted. The current execution allowlist includes draft, post update, taxonomy, media metadata/upload/featured-image, media derivative, comment, and bounded destructive profiles documented in the OpenClaw batch execution policy.

== Changelog ==

= 0.1.0 =

* Initial thin OpenClaw adapter for Npcink Governance Core and WordPress Abilities API routing.
