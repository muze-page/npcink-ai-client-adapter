# OpenClaw AI Article Writing Pack Recipe

Status: active
Date: 2026-06-03

This recipe gives OpenClaw one high-level entrypoint for broad article requests
such as "write an AI topic article". Adapter exposes the Toolbox writing pack
as a direct-read shortcut; Toolbox builds the context pack; OpenClaw drafts the
candidate article from that pack; Core remains the only proposal, approval,
commit-preflight, and audit truth for any final WordPress write.

For SEO/GEO/AEO suggestions on a known post, title, topic, or draft body, use
`content-discoverability-brief` as the primary entrypoint.
Use `article-writing-pack` only for broad natural-language requests such as
"help me write an article".

## Boundary

Layer ownership stays fixed:

- Toolbox owns the operator-filled SEO/AEO/GEO context and the
  `ai_article_writing_pack` planning artifact.
- Adapter exposes the read shortcut and machine-readable OpenClaw recipe.
- OpenClaw may draft text from the pack, but it must treat that output as a
  candidate for operator review.
- Core decides whether each ability is `direct_read` and owns proposal,
  approval, commit-preflight, and audit truth.
- WordPress Abilities API runs the Toolbox callbacks.

Adapter must not own prompts, provider selection, model execution, drafting
runtime, SEO writes, media writes, publishing, or a second workflow registry.
Reviewed final writes must go through Core proposal governance.

## Recipe

1. Discover Adapter state and route guidance:

```text
GET /wp-json/magick-ai-adapter/v1/health
GET /wp-json/magick-ai-adapter/v1/help
GET /wp-json/magick-ai-adapter/v1/capabilities
```

2. Confirm this ability id is present in Core capabilities with
   `governance_mode=direct_read` and
   `execution_surface=wp_abilities_rest`:

```text
magick-ai-toolbox/build-ai-article-writing-pack
```

3. Build the writing pack through the shortcut:

```text
GET /wp-json/magick-ai-adapter/v1/article-writing-pack?topic=AI_TOPIC
```

For richer input, use `POST /run-read-ability`:

```json
{
  "ability_id": "magick-ai-toolbox/build-ai-article-writing-pack",
  "input": {
    "topic": "AI topic",
    "title": "Suggested title",
    "language": "zh-CN",
    "article_type": "practical_guide",
    "target_word_count": 1200
  }
}
```

4. Draft from the returned pack only. The returned result must include:

```text
artifact_type=ai_article_writing_pack
write_posture=suggestion_only
direct_wordpress_write=false
provider_execution=none
final_write_path=core_proposal_required
```

5. After operator review, build a governed write plan:

```json
{
  "ability_id": "magick-ai-toolbox/build-article-write-plan",
  "input": {
    "draft_title": "Reviewed title",
    "draft_body": "Reviewed body",
    "seo": {
      "seo_title": "Reviewed SEO title",
      "seo_description": "Reviewed SEO description"
    }
  }
}
```

6. If the reviewed plan should become WordPress data, call
   `POST /wp-json/magick-ai-adapter/v1/proposals/from-plan` and keep using
   Core proposal status, approval, and commit-preflight routes.

## Required Output

OpenClaw should show the operator a draft candidate plus proposal-ready
suggestions, not applied WordPress changes:

```json
{
  "artifact_type": "ai_article_draft_candidate",
  "source_pack_artifact_type": "ai_article_writing_pack",
  "write_posture": "suggestion_only",
  "direct_wordpress_write": false,
  "draft": {
    "title": "",
    "body_markdown": "",
    "answer_summary": "",
    "faq": [],
    "geo_summary": ""
  },
  "proposal_candidates": {
    "seo_title": "",
    "seo_description": "",
    "slug": "",
    "excerpt": ""
  },
  "final_write_path": "core_proposal_required"
}
```

Only include fields allowed by the writing pack's `proposal_allowed_fields`.

## Guardrails

- Do not invent product facts, customer stories, rankings, citations,
  guarantees, or unsupported features.
- Respect `forbidden_claims`, `allowed_claims`, and `brand_voice`.
- Do not treat the writing pack or draft candidate as permission to mutate
  WordPress.
- Do not publish, schedule, upload media, set featured images, write SEO meta,
  or update posts from this recipe.
- Do not add Toolbox suggestion abilities to Adapter's approved final execution
  profile registry.
