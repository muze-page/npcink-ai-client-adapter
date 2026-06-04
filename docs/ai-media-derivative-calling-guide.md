# AI Media Derivative Calling Guide

Status: active guide
Date: 2026-06-03

## Purpose

This guide tells AI callers how to request Cloud-generated media derivatives
without bypassing local WordPress governance.

The media derivative capability is exposed through local WordPress abilities
and Adapter recipe routes. Cloud is a runtime processor only. It does not
publish a second ability registry, approve writes, replace files, or update
attachment metadata.

## Canonical Ability Entry

Discovery ability:

```text
magick-ai/build-media-derivative-cloud-request
```

This is a read-only WordPress ability. It builds the one-run request contract
for Cloud media derivative processing.

Bulk planning ability:

```text
magick-ai/build-media-derivative-batch-plan
```

This is also read-only. Use it before natural-language bulk requests such as
"convert April media library images to PNG". It returns bounded candidates,
skipped reasons, and per-candidate single-image request input. It does not call
Cloud, create proposals, approve adoption, or return a WordPress write decision.

Verified local REST discovery:

```text
GET /wp-json/wp-abilities/v1/abilities/magick-ai/build-media-derivative-cloud-request
```

Expected discovery properties:

- `meta.show_in_rest = true`
- `meta.annotations.readonly = true`
- `meta.magick.risk_level = read`
- `meta.magick.channels = ["abilities_rest"]`
- supported formats: `webp`, `avif`, `jpeg`, `png`, `original`
- optional watermark object with image watermark options

The ability output includes:

- `request_contract_version = media_derivative_cloud_request.v1`
- `cloud_job_payload.job_type = generate_optimized_media_derivative`
- `cloud_job_payload.target_format`
- `cloud_job_payload.max_width`
- `cloud_job_payload.quality`
- optional `cloud_job_payload.watermark`
- `local_adoption` and `risk` evidence

The ability does not upload source bytes, call Cloud, submit a proposal, or
write WordPress.

## Recommended AI Flow

AI callers should use Adapter's bounded media derivative recipe, not direct
Cloud runtime calls:

1. Select or receive a WordPress image `attachment_id`. For bulk requests, first
   call `magick-ai/build-media-derivative-batch-plan` through
   `/run-read-ability`, review `candidates` and `skipped`, and process only a
   small approved slice.
2. Call:

   ```http
   POST /wp-json/magick-ai-adapter/v1/media-derivative-runs
   ```

   Example body:

   ```json
   {
     "input": {
       "attachment_id": 123,
       "preferred_format": "webp",
       "target_max_width": 1600,
       "quality": 82,
       "watermark": {
         "type": "image",
         "position": "bottom_right",
         "opacity": 0.75,
         "scale_percent": 18,
         "margin_px": 24
       }
     }
   }
   ```

3. Poll:

   ```http
   GET /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}
   ```

4. Read result:

   ```http
   GET /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}/result
   ```

5. Show the local same-origin preview URL from the result when present.
6. Build proposal payload:

   ```http
   POST /wp-json/magick-ai-adapter/v1/media-derivative-proposal-payload
   ```

   Include `ability_response`, `cloud_result`, `derivative_artifact`, and
   reviewed `media_details_input` when the requested user intent is full media
   optimization.

7. For full media optimization, submit the returned `from_plan_request` to:

   ```text
   POST /wp-json/magick-ai-adapter/v1/proposals/from-plan
   ```

   Core will create one batch proposal containing `magick-ai/update-media-details`
   and `magick-ai/adopt-cloud-media-derivative`.
   If Core reports the plan ability is unavailable, treat that as a local
   capability/version guard and ask for the local stack to be updated. Do not
   split the same media optimization user intent into two proposal approvals.

8. Let Core approval, preflight, audit, execution, and rollback govern the
   final WordPress write.

## Direct Ability Flow

Advanced local callers may call the read ability directly:

```http
POST /wp-json/magick-ai-adapter/v1/run-read-ability
```

Example body:

```json
{
  "ability_id": "magick-ai/build-media-derivative-cloud-request",
  "input": {
    "attachment_id": 123,
    "preferred_format": "webp",
    "target_max_width": 1600,
    "quality": 82
  },
  "log_context": {
    "external_thread_id": "ai-media-derivative-preview"
  }
}
```

This returns only the local request contract. The caller must still use Cloud
Addon or Adapter recipe routes to dispatch the Cloud job.

For batch planning:

```json
{
  "ability_id": "magick-ai/build-media-derivative-batch-plan",
  "input": {
    "date_from": "2026-04-01",
    "date_to": "2026-04-30 23:59:59",
    "target_format": "png",
    "exclude_formats": ["png"],
    "max_items": 20
  }
}
```

Then call `POST /media-derivative-runs` once per reviewed candidate using that
candidate's `cloud_request_input`.

## Adoption Write Ability

Write ability:

```text
magick-ai/adopt-cloud-media-derivative
```

This ability is intentionally separate from the read ability. It requires local
write governance and derivative artifact evidence:

- `attachment_id`
- `derivative_artifact.artifact_id`
- `derivative_artifact.expires_at`
- `derivative_artifact.mime_type`
- `derivative_artifact.format`
- dimensions, filesize, checksum, and warnings when available

Adoption must be proposed and approved through Core. AI callers should not
execute this write ability directly unless they are inside the Core-approved
execution path.

## Guardrails For AI Callers

Do:

- use `POST /media-derivative-runs` as the normal entrypoint;
- use `magick-ai/build-media-derivative-batch-plan` before bulk conversion
  requests;
- treat Cloud artifacts as short-lived previews;
- preserve `run_id`, `artifact_id`, `expires_at`, checksum, dimensions, and
  warnings as proposal evidence;
- submit Core proposals for adoption;
- use reference repair plans for hard-coded URLs after adoption.

Do not:

- call Cloud directly from third-party AI clients;
- store Cloud artifact ids as media registry truth;
- expose Cloud download URLs publicly;
- decide WordPress writes from Cloud responses;
- update attachment metadata from Adapter or Cloud;
- adopt expired artifacts;
- bypass Core proposal approval.

## Related Routes

```text
POST /wp-json/magick-ai-adapter/v1/media-derivative-runs
GET  /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}
GET  /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}/result
GET  /wp-json/magick-ai-adapter/v1/media-derivative-artifacts/{artifact_id}/preview
POST /wp-json/magick-ai-adapter/v1/media-derivative-proposal-payload
POST /wp-json/magick-ai-adapter/v1/proposals
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute
```

## Related Documents

- `docs/openclaw-media-derivative-cloud-recipe.md`
- `docs/openclaw-adapter-contract.md`
- `docs/cloud-connector-boundary.md`
