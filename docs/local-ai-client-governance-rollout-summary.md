# Local AI Client Governance Rollout Summary

Status: accepted rollout summary.

Date: 2026-06-10.

This document summarizes the governance and local AI client work that moved the
Adapter from prompt-only operating instructions toward enforceable Core,
Adapter, and CLI contracts.

## Problem

The original OpenClaw handoff depended too heavily on copied prompt text. That
was useful for operator guidance, but it was not a reliable safety boundary:

- customers can choose their own AI client;
- Adapter cannot control a customer's model, memory, prompt, plugin set, or
  local tool policy;
- sensitive data such as diagnostics, logs, database details, profile files,
  signing headers, and private keys needs policy enforcement outside prompt
  wording;
- WordPress writes must remain reviewable, approvable, and auditable through
  Core governance.

The product direction is therefore: keep Adapter as a thin channel layer and
move sensitive authorization truth into Core, with machine-readable policy and
redacted local CLI outputs for clients.

## Decisions

### Core Owns Sensitive Read Truth

Npcink Governance Core is the source of truth for sensitive read requests,
approval/rejection, preflight, input-hash binding, expiry, redaction policy, and
audit. Adapter only bridges those Core routes for local clients.

Sensitive reads follow this shape:

1. Client creates a read request through Adapter.
2. Core stores the request as pending with `ability_id`, exact input hash,
   purpose, data classes, sensitivity, redaction level, and bounds.
3. Operator approves or rejects in Core.
4. Adapter calls Core read-preflight immediately before executing the read.
5. Read execution proceeds only when Core returns a valid grant for the same
   `ability_id` and approved input hash.

If the input changes, the client must create a new read request.

### Adapter Remains Thin

Adapter owns the OpenClaw-compatible channel surface:

- REST route discovery and readiness responses;
- signed key-pair local CLI transport;
- routing read abilities to WordPress Abilities API;
- routing proposal, read request, and preflight traffic to Core;
- explicit final-write execution profiles after Core approval and preflight;
- local diagnostics and small readiness responses.

Adapter does not own:

- ability definitions;
- Core approval, read grant, proposal, or audit truth;
- generic database/filesystem/log reading;
- generic write execution;
- workflow runtime, queues, MCP runtime, or cloud control plane.

### Client Policy Beats Prompt-Only Rules

Adapter now exposes a machine-readable `client_policy` on:

- `GET /connection/manifest`
- `GET /health`
- `GET /help`

That policy describes forbidden outputs, forbidden local access, allowed
transport, sensitive read flow, write flow, and recommended CLI commands.
Prompt text can reference the policy, but the policy is the client contract.

### CLI Narrows Common Operations

The local CLI now has narrow helper commands:

```bash
npcink-openclaw-adapter read-request create --profile=local --ability-id=ABILITY_ID --input-file=/tmp/read-input.json --purpose=PURPOSE --data-classes=CLASS[,CLASS]
npcink-openclaw-adapter read-request status --profile=local READ_REQUEST_ID
npcink-openclaw-adapter read-ability --profile=local --ability-id=ABILITY_ID --input-file=/tmp/read-input.json --read-request-id=READ_REQUEST_ID
```

These helpers reduce the need for an AI client to hand-build sensitive read
route bodies and keep the client on Adapter-relative, signed transport.

CLI output is redacted for local profile paths, key ids, connection ids,
public/private keys, authorization headers, signing headers, cookies, tokens,
passwords, and secrets.

### OpenClaw Is One Compatible Client, Not the Boundary

The product still supports OpenClaw-compatible workflows, but the enforceable
boundary is not OpenClaw itself. The boundary is:

- Adapter REST authentication and route validation;
- Adapter CLI signing and output redaction;
- `client_policy`;
- WordPress capability checks;
- Core read authorization, proposal approval, preflight, and audit.

Admin copy now uses "local AI client" where the instruction is not specific to
OpenClaw. This avoids implying that Adapter controls customer-selected AI
clients.

### WP-CLI Noise Is Toolchain, Not Plugin Logic

The earlier `composer smoke:wp` deprecated noise came from WP-CLI dependencies
running under Local PHP 8.5, not from Adapter. The local smoke and release-check
defaults now prefer Local PHP 8.2. This removes the deprecated noise without
hiding plugin errors or changing plugin runtime behavior.

## Implemented Milestones

Core side, in `magick-ai-core`:

- `09e9708 core: add sensitive read authorization`
- `1804cd6 Harden sensitive read authorization bounds`

Adapter side, in this repository:

- `e278b4e Harden OpenClaw CLI client policy`
- `79c0404 Prepare local AI client policy release`

The Adapter release version is now `0.1.1`, and the CLI package version is
`@npcink/openclaw-adapter-cli@0.1.1`.

## Verification Performed

Adapter verification covered:

- `npm --prefix packages/adapter-cli run check`
- `npm pack --dry-run` in `packages/adapter-cli`
- `composer test:all`
- `composer smoke:wp`
- `composer package:release`
- `composer plugin-check:release`
- `composer release:verify`
- `git diff --check`

Important observations:

- `/health`, `/help`, and `/connection/manifest` expose `client_policy`.
- `status` CLI output no longer includes profile path, key id, or connection id.
- `/help` output through the CLI redacts header-like strings such as
  authorization examples.
- `read-ability` succeeds for public `site-info`.
- `read-request create` and `read-request status` work for a sensitive
  diagnostics/logs request; the temporary test request was rejected through Core
  cleanup after verification.
- `composer smoke:wp` passes with no PHP deprecated noise after the PHP 8.2
  toolchain default change. The remaining `Plugin already active` warning is
  expected local WP-CLI behavior.
- `build/npcink-ai-client-adapter.zip` contains the release surface allowed by
  `.distignore`: plugin PHP, assets, languages, and `readme.txt`; it excludes
  development docs, packages, tests, and Composer metadata.

## Current Release Posture

The code is ready for the next release step:

1. Push Adapter `master`.
2. Publish `@npcink/openclaw-adapter-cli@0.1.1` if npm distribution is desired.
3. Use `build/npcink-ai-client-adapter.zip` for the WordPress package surface.
4. Run one real local AI client / OpenClaw-compatible acceptance conversation
   against the new opener and CLI helper commands.

No additional Adapter feature work is required before that acceptance pass.
