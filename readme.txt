=== Magick AI Adapter ===
Contributors: magick-ai
Tags: ai, governance, automation, rest-api
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thin OpenClaw channel plugin for Magick AI Core governance and WordPress Abilities execution.

== Description ==

Magick AI Adapter gives OpenClaw one WordPress REST namespace for reading Magick AI Core capability guidance, routing approved read abilities through the WordPress Abilities API, and forwarding governed write requests to Magick AI Core proposal and commit-preflight endpoints.

Adapter is intentionally thin. It does not define abilities, store approval truth, run workflow queues, expose generic approve/reject proxying, or execute final write mutations without Core approval and commit-preflight.

Current governed execution support is deliberately limited to individually approved proposal execution for `magick-ai/trash-post`.

== Installation ==

1. Install and activate Magick AI Core.
2. Install and activate the WordPress Abilities provider used by Magick AI.
3. Install and activate Magick AI Adapter.
4. Open Magick AI > OpenClaw Connection to create the OpenClaw handoff.

== Frequently Asked Questions ==

= Does Adapter approve proposals? =

Only through the explicit `approve-and-execute` user action. Magick AI Core remains the governance backend for proposal storage, approval, commit-preflight, and audit, and Adapter standalone approve/reject stubs stay disabled.

= Does Adapter execute arbitrary abilities? =

No. Adapter only executes abilities that are explicitly allowlisted. The current minimal execution allowlist contains `magick-ai/trash-post`.

== Changelog ==

= 0.1.0 =

* Initial thin OpenClaw adapter for Magick AI Core governance and WordPress Abilities API routing.
