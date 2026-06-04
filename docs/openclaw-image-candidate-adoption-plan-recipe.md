# OpenClaw Image Candidate Adoption Plan Recipe

Status: active.

Use this recipe when OpenClaw or another AI client has a reviewed image
candidate and needs to ask the local WordPress site to import it, write media
details, and optionally set it as a post's featured image.

This is a governed handoff, not a direct media write path.

## Contract

- Candidate contract: `image_candidate.v1`
- Plan ability: `magick-ai-toolbox/build-image-candidate-adoption-plan`
- Plan artifact: `image_candidate_adoption_plan`
- Handoff route: `POST /wp-json/magick-ai-adapter/v1/proposals/from-plan`
- Status route: `GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}`
- Final route:
  `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute`
- Final write abilities:
  `magick-ai/upload-media-from-url`,
  `magick-ai/update-media-details`, and optional
  `magick-ai/set-post-featured-image`

## Flow

1. Collect image candidates through
   `magick-ai-toolbox/search-image-source` or another approved direct-read
   source that returns `image_candidate.v1`.
2. Let the operator select one candidate and review license, attribution,
   prompt/model provenance, and warnings.
3. Run `magick-ai-toolbox/build-image-candidate-adoption-plan` through
   `POST /run-read-ability`.
4. Forward the returned `image_candidate_adoption_plan` to Core through
   `POST /wp-json/magick-ai-adapter/v1/proposals/from-plan`.
5. Poll the proposal.
6. Only after Core approval and commit preflight, call
   `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute`.

## Guardrails

- `core_proxy_execute=false`
- `commit_execution=false`
- `batch_approval=true`
- `cloud_control_plane=false`
- `generic_write_executor=false`
- The selected image candidate must preserve attribution and provenance.
- Stock search, AI image generation, Cloud runtime, and customer connectors
  remain candidate sources only.
- Adapter does not search providers, generate images, import media, set
  featured images, or maintain a media registry before Core approval.
- Cloud image generation, when present, returns only short-lived candidate
  artifacts and runtime evidence.
