# OpenClaw Media Derivative Cloud Recipe

Status: active route recipe

This recipe lets OpenClaw request an optimized media derivative while keeping
WordPress write ownership local.

## Ownership

- Core owns local media policy defaults, proposal governance, approval,
  commit-preflight, and audit.
- Abilities own the canonical
  `magick-ai/build-media-derivative-cloud-request` read-only contract.
- Cloud Addon owns Cloud credentials, signing, media derivative transport, run
  reads, and result reads.
- Cloud owns runtime processing and short-TTL derivative artifacts.
- Adapter owns only the OpenClaw channel and bounded orchestration projection.

Adapter must not store Cloud API keys, register `/cloud/*` routes, create a
media or artifact registry, approve adoption, update attachment metadata, or
replace media files.

## Flow

1. `POST /media-derivative-runs`
   - Required: `input.attachment_id`.
   - Optional input: `preferred_format` or `target_format`, `target_max_width`
     or `max_width`, `quality`, and `watermark`.
   - Optional artifact descriptors: `source_artifact`,
     `watermark_artifact`.
   - Adapter builds the local ability request, attaches the local source file
     when no source artifact reference is supplied, and dispatches through
     Cloud Addon.

2. `GET /media-derivative-runs/{run_id}`
   - Polls Cloud run status through Cloud Addon.
   - Adapter stores no run truth.

3. `GET /media-derivative-runs/{run_id}/result`
   - Reads the Cloud result projection through Cloud Addon.
   - The result should include derivative artifact evidence such as
     `artifact_id`, `download_url`, `expires_at`, `mime_type`, dimensions,
     filesize, checksum, and warnings when available.
   - The derivative projection may include `preview_url`, a same-origin Adapter
     preview proxy URL.

4. `GET /media-derivative-artifacts/{artifact_id}/preview`
   - Requires WordPress REST auth/nonce or the short-lived local `preview_sig`
     emitted in `preview_url`, plus a non-expired artifact descriptor in the
     query string.
   - Cloud Addon signs the Cloud artifact download and Adapter streams the
     bytes as a local preview only.
   - Does not store artifact truth, expose a public Cloud URL, or write
     WordPress media.

5. `POST /media-derivative-proposal-payload`
   - Input: `ability_response`, `cloud_result`, and `derivative_artifact`.
   - Returns a Core-ready proposal payload only.
   - Does not create, approve, preflight, or execute a proposal.

6. `POST /proposals`
   - For preview review only, use the returned proposal payload as evidence.
   - To replace the attachment main file, create a Core proposal for
     `magick-ai/adopt-cloud-media-derivative` with `attachment_id` and the
     non-expired `derivative_artifact` descriptor.
   - Adapter may later execute that approved local write ability, but it does
     not download artifacts or write WordPress media itself.

## Guardrails

- `final_write_owner=local_wordpress_host`.
- `wordpress_write_included=false`.
- `attachment_metadata_write_included=false`.
- Cloud artifacts are short-TTL processing artifacts, not canonical truth.
- Adapter returns derivative artifact and processing evidence only.
- Final adoption requires Core proposal approval and commit preflight.
