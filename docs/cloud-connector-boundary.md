# Cloud Connector Boundary

Status: active guidance
Date: 2026-05-30

## Purpose

Magick AI Adapter is the OpenClaw channel layer. It is not the WordPress-side
Cloud connector.

Cloud runtime access, Cloud API key storage, request signing, entitlement
reads, observability upload, and hosted-runtime transport belong to the
standalone `magick-ai-cloud-addon`. Adapter may use that addon through its
public PHP seam when OpenClaw needs a Cloud-backed operation, but Adapter must
not grow its own Cloud settings, signing client, `/cloud/*` REST namespace, or
Cloud execution truth.

## Project Roles

| Project | Owns | Does not own |
| --- | --- | --- |
| `magick-ai-abilities` | Canonical WordPress ability definitions, schemas, callbacks, permissions, dry-run previews, and read-only workflow recipe metadata. | Cloud calls, model routing, queues, billing, quota, approval state, audit truth, workflow runtime, or final writes. |
| `magick-ai-core` | Governance, ability intake, proposal records, approval/rejection, commit preflight, scoped app keys, rate limits, and audit records. | Ability definitions, cloud execution, task queues, model routing, product workflows, or final write execution. |
| `magick-ai-adapter` | OpenClaw-facing REST routes, non-secret WordPress connection manifest, read ability execution through WordPress Abilities API, Core proposal/preflight proxying, one allowlisted approve-and-execute orchestration path, and optional calls into the Cloud Addon seam. | Cloud settings, Cloud API key storage, Cloud request signing, `/cloud/*` routes, ability registry, approval store, workflow runtime, durable queue, model router, provider credentials, Cloud analytics truth, generic approve/reject proxying, or final write authority. |
| `magick-ai-cloud-addon` | Cloud Base URL/API key settings, signed hosted runtime transport, run/result reads, entitlement and stats projections, media derivative transport helpers, and opt-in metadata-only plugin observability upload. | OpenClaw product UX, Core governance truth, local ability truth, approval truth, WordPress writes, prompt/router/preset control, scheduler truth, workflow/task queue control, or billing truth. |
| `magick-ai-cloud` | Hosted runtime, Cloud API, worker execution, run status, provider telemetry, usage/stats, health, entitlement, quota, diagnostics, and Cloud-side analysis generation. | WordPress control plane, local ability truth, local approval truth, OpenClaw projection truth, or WordPress writes. |

## Recommended Cloud Flow

```text
OpenClaw
  -> magick-ai-adapter
      -> magick-ai-core        // governance, approval, audit, preflight
      -> magick-ai-abilities   // local WordPress data and ability callbacks
      -> magick-ai-cloud-addon // signed Cloud transport seam
          -> magick-ai-cloud   // hosted execution, stats, analysis, workers
```

The adapter is the local entry point for OpenClaw. Cloud Addon is the WordPress-side Cloud connector. Cloud remains the hosted execution and analysis
service. Core remains the governance authority. Abilities remain the canonical
local capability and callback source.

## Adapter Responsibilities

Adapter may add bounded Cloud Addon integration code for:

- detecting whether `magick-ai-cloud-addon` is active;
- calling `magick_ai_cloud_addon_runtime_client()` or a more specific public
  helper exposed by the addon;
- returning Cloud Addon status or Cloud run/result projections to OpenClaw when
  a user action explicitly needs a Cloud-backed operation;
- carrying `proposal_id`, `correlation_id`, `external_thread_id`, and
  `openclaw_thread_id` across WordPress, Core, Cloud, and AI Request Logs;
- translating local WordPress context from Abilities into a Cloud request
  payload when the operation is read-only or already approved by Core.

Adapter must keep these calls thin. It delegates Cloud credentials, signing,
endpoint allowlists, durable execution, retry, queueing, analytics, and
Cloud-side projections to `magick-ai-cloud-addon` and `magick-ai-cloud`.

Adapter must not register Adapter-owned `/cloud/*` routes unless a future ADR
explicitly moves Cloud connector ownership back from Cloud Addon.

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
   OpenClaw -> Adapter -> Abilities -> Adapter -> Cloud Addon -> Cloud
   ```

   Use this for context gathering, stats, diagnostics, and analysis inputs that
   do not mutate WordPress.

2. Write or destructive requests must flow:

   ```text
   OpenClaw -> Adapter -> Core proposal -> Core approval/preflight -> Adapter -> Cloud Addon or local host
   ```

   Adapter must not bypass Core approval for any WordPress mutation.

3. Cloud-generated write recommendations must stop as reviewable artifacts:

   - proposal input;
   - dry-run preview;
   - report;
   - pending change;
   - structured recommendation.

4. Final WordPress writes remain local and governed. Adapter may execute only a
   narrow allowlisted ability after Core approval and Core commit-preflight
   through the explicit approve-and-execute path. Adapter must not become a
   generic final write executor unless a future ADR explicitly changes that
   boundary.

## Adapter Cloud Addon Seam

Adapter may consume only the Cloud Addon public seam. Current examples include:

- `magick_ai_cloud_addon_runtime_client()`;
- `magick_ai_cloud_addon_verified_runtime_client()`;
- `magick_ai_cloud_addon_dispatch_media_derivative_cloud_request()`;
- `magick_ai_cloud_addon_build_media_derivative_proposal_payload()`.

Adapter must not expose a parallel `/cloud/*` REST surface or duplicate Cloud
Addon settings. If OpenClaw needs Cloud health, run status, results, stats,
entitlement, or observability detail, Adapter should either link the operator to
Cloud Addon or return a bounded projection obtained through Cloud Addon.

For media derivatives, Adapter may expose only the bounded OpenClaw channel
routes:

- `POST /media-derivative-runs`;
- `GET /media-derivative-runs/{run_id}`;
- `GET /media-derivative-runs/{run_id}/result`;
- `GET /media-derivative-artifacts/{artifact_id}/preview`;
- `POST /media-derivative-proposal-payload`.

Those routes build the local read-only request contract, pass source or
watermark uploads/artifact references through Cloud Addon, return Cloud
run/result projections, stream one same-origin local preview through Cloud
Addon, and build a Core-ready proposal payload. They must not create Core
proposals, approve adoption, update attachment metadata, replace media files,
or store artifact truth.

## Forbidden Adapter Shapes

Do not add these to Adapter:

- local durable workflow runtime;
- local task queue, retry engine, scheduler, or lease manager;
- Adapter-owned Cloud settings, signing clients, or `/cloud/*` routes;
- Cloud task execution truth;
- Cloud analytics truth;
- ability registry or fallback ability definitions;
- approval or rejection authority;
- final WordPress write execution;
- provider credential storage or model routing policy;
- prompt, preset, router, MCP, or Agent Gateway control plane truth.

## Next Implementation Sequence

1. Detect Cloud Addon:
   - check for the relevant public functions;
   - fail closed with clear operator guidance when the addon is missing or
     unverified;
   - do not read Cloud credentials from Adapter.

2. Add Cloud-backed OpenClaw behavior only through Cloud Addon:
   - collect local context through existing Abilities routes;
   - call a Cloud Addon helper or runtime client allowlisted method;
   - return Cloud `run_id`, status, result, or proposal input as a projection;
   - do not write WordPress.

3. Add governed write handoff only after read-only proof works:
   - Cloud returns recommendation/proposal input;
   - Adapter submits or relays to Core proposal flow;
   - Core approval/preflight remains mandatory before any write.

4. Add tests for boundary invariants:
   - Adapter has no queue or scheduler truth;
   - Adapter does not register `/cloud/*` routes;
   - Adapter does not store Cloud API keys or sign Cloud requests;
   - Cloud Addon calls do not expose standalone approve/reject proxying;
   - Cloud Addon calls do not execute final writes outside Adapter's
     Core-approved allowlisted path;
   - write-like Cloud outputs require Core proposal/preflight handoff.

## Decision Summary

Adapter is not the WordPress-to-Cloud connector. It is responsible for the
OpenClaw channel, request shaping, Core/Abilities delegation, and correlation.
Cloud Addon owns local Cloud transport and signing. Adapter is not responsible
for Cloud execution truth, local governance truth, ability truth, or WordPress
write truth.
