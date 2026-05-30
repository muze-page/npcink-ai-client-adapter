# Cloud Connector Boundary

Status: active guidance
Date: 2026-05-30

## Purpose

Magick AI Adapter is the connector between local WordPress and hosted Magick AI
Cloud services.

It is a thin middle layer. It should make Cloud useful to OpenClaw and local
WordPress without becoming a second control plane, a workflow engine, or a
cloud product backend.

## Project Roles

| Project | Owns | Does not own |
| --- | --- | --- |
| `magick-ai-abilities` | Canonical WordPress ability definitions, schemas, callbacks, permissions, dry-run previews, and read-only workflow recipe metadata. | Cloud calls, model routing, queues, billing, quota, approval state, audit truth, workflow runtime, or final writes. |
| `magick-ai-core` | Governance, ability intake, proposal records, approval/rejection, commit preflight, scoped app keys, rate limits, and audit records. | Ability definitions, cloud execution, task queues, model routing, product workflows, or final write execution. |
| `magick-ai-adapter` | OpenClaw-facing REST routes, WordPress Application Password handoff, read ability execution through WordPress Abilities API, Core proposal/preflight proxying, and bounded Cloud connector routes. | Ability registry, approval store, workflow runtime, durable queue, model router, provider credentials, Cloud analytics truth, or final write authority. |
| `magick-ai-cloud` | Hosted runtime, Cloud API, worker execution, run status, provider telemetry, usage/stats, health, entitlement, quota, diagnostics, and Cloud-side analysis generation. | WordPress control plane, local ability truth, local approval truth, OpenClaw projection truth, or WordPress writes. |

## Recommended Cloud Flow

```text
OpenClaw
  -> magick-ai-adapter
      -> magick-ai-core        // governance, approval, audit, preflight
      -> magick-ai-abilities   // local WordPress data and ability callbacks
      -> magick-ai-cloud       // hosted execution, stats, analysis, workers
```

The adapter is the local entry point for OpenClaw. Cloud remains the hosted
execution and analysis service. Core remains the governance authority. Abilities
remain the canonical local capability and callback source.

## Adapter Responsibilities

Adapter may add bounded Cloud connector code for:

- storing or reading Cloud connector settings;
- validating Cloud reachability and connector health;
- signing or authenticating Cloud API requests;
- submitting hosted runtime or analysis requests to Cloud;
- returning Cloud `run_id`, status, result, usage, stats, and diagnostics
  summaries to OpenClaw;
- carrying `proposal_id`, `correlation_id`, `external_thread_id`, and
  `openclaw_thread_id` across WordPress, Core, Cloud, and AI Request Logs;
- translating local WordPress context from Abilities into a Cloud request
  payload when the operation is read-only or already approved by Core.

Adapter must keep these routes thin. It should delegate durable execution,
retry, queueing, analytics, and Cloud-side projections to `magick-ai-cloud`.

## Cloud Responsibilities

Cloud owns:

- durable hosted runs and run status;
- worker-backed execution;
- provider routing and provider-call telemetry;
- usage metering, stats rollups, health, diagnostics, quota, and entitlement;
- Cloud analysis generation and Cloud-owned result storage;
- Cloud service-plane operations and internal operator diagnostics.

Cloud must not directly write WordPress. Any Cloud result that implies a
WordPress mutation must return a draft, recommendation, report, pending change,
or proposal input for local review.

## Governance Rules

1. Read-only requests may flow:

   ```text
   OpenClaw -> Adapter -> Abilities -> Adapter -> Cloud
   ```

   Use this for context gathering, stats, diagnostics, and analysis inputs that
   do not mutate WordPress.

2. Write or destructive requests must flow:

   ```text
   OpenClaw -> Adapter -> Core proposal -> Core approval/preflight -> Adapter -> Cloud or local host
   ```

   Adapter must not bypass Core approval for any WordPress mutation.

3. Cloud-generated write recommendations must stop as reviewable artifacts:

   - proposal input;
   - dry-run preview;
   - report;
   - pending change;
   - structured recommendation.

4. Final WordPress writes remain local and governed. Adapter must not become
   the final write executor unless a future ADR explicitly changes that
   boundary.

## Allowed Adapter Cloud Routes

Future Cloud-facing routes should be shaped as connector routes, for example:

- `GET /wp-json/magick-ai-adapter/v1/cloud/health`
- `POST /wp-json/magick-ai-adapter/v1/cloud/runs`
- `GET /wp-json/magick-ai-adapter/v1/cloud/runs/{run_id}`
- `POST /wp-json/magick-ai-adapter/v1/cloud/analysis`
- `GET /wp-json/magick-ai-adapter/v1/cloud/stats`

These routes may aggregate local status and Cloud status, but they must not
persist orchestration truth in Adapter.

## Forbidden Adapter Shapes

Do not add these to Adapter:

- local durable workflow runtime;
- local task queue, retry engine, scheduler, or lease manager;
- Cloud task execution truth;
- Cloud analytics truth;
- ability registry or fallback ability definitions;
- approval or rejection authority;
- final WordPress write execution;
- provider credential storage or model routing policy;
- prompt, preset, router, MCP, or Agent Gateway control plane truth.

## Next Implementation Sequence

1. Add a small Cloud connector contract in Adapter:
   - Cloud base URL;
   - Cloud site key or connector credential reference;
   - signed request helper;
   - health check helper;
   - no durable run storage.

2. Add `GET /cloud/health`:
   - verifies Adapter configuration;
   - verifies Core and Abilities availability;
   - verifies Cloud reachability;
   - returns clear `ready` / `degraded` fields for OpenClaw.

3. Add read-only Cloud analysis proof:
   - collect local context through existing Abilities routes;
   - submit a Cloud analysis request;
   - return Cloud `run_id` and status;
   - do not write WordPress.

4. Add Cloud run status polling:
   - proxy Cloud status/result by `run_id`;
   - preserve correlation ids;
   - avoid local result truth.

5. Add governed write handoff only after read-only proof works:
   - Cloud returns recommendation/proposal input;
   - Adapter submits or relays to Core proposal flow;
   - Core approval/preflight remains mandatory before any write.

6. Add tests for boundary invariants:
   - Adapter has no queue or scheduler truth;
   - Cloud connector routes do not approve proposals;
   - Cloud connector routes do not execute final writes;
   - write-like Cloud outputs require Core proposal/preflight handoff.

## Decision Summary

Adapter is the WordPress-to-Cloud connector. It is responsible for local
transport, request shaping, authentication, status proxying, and correlation.
It is not responsible for Cloud execution truth, local governance truth,
ability truth, or WordPress write truth.
