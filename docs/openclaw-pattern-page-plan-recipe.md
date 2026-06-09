# OpenClaw Pattern Page Plan Recipe

Status: accepted

This recipe lets OpenClaw request a reviewed Gutenberg page pattern plan without
making Adapter a page renderer or generic write executor.

## Boundary

- Entrypoint ability: `npcink-abilities-toolkit/build-pattern-page-plan`
- Artifact type: `pattern_page_plan`
- Handoff route: `POST /wp-json/npcink-openclaw-adapter/v1/proposals/from-plan`
- Final route: `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/approve-and-execute`
- Final write abilities: `npcink-abilities-toolkit/create-draft` and
  `npcink-abilities-toolkit/update-post-blocks`

The Toolkit owns pattern registry, variable validation, class whitelisting, and
Gutenberg block rendering. Adapter only forwards the read-only plan to Core and
executes allowlisted write actions after Core approval and commit-preflight.

## Supported Input

```json
{
  "post_type": "page",
  "status": "draft",
  "title": "WordPress AI",
  "pattern_id": "openai-style-landing",
  "style_preset": "minimal-dark-light",
  "variables": {
    "eyebrow": "WordPress AI Plugin",
    "hero_title": "把 AI 工作流带进 WordPress 内容现场",
    "hero_description": "让内容生产、SEO 优化、媒体处理与发布协作在同一个可审计流程中完成。",
    "primary_cta": "查看工作流",
    "secondary_cta": "了解能力",
    "features": []
  }
}
```

## Flow

1. Run `npcink-abilities-toolkit/build-pattern-page-plan` through Adapter read
   execution.
2. Submit the returned `pattern_page_plan` to
   `POST /wp-json/npcink-openclaw-adapter/v1/proposals/from-plan`.
3. Poll `GET /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}`.
4. Execute only after approval with
   `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/approve-and-execute`.

## Guardrails

- `proposal_mode=batch`
- `batch_approval=true`
- `draft_only=true`
- `publish_allowed=false`
- `core_proxy_execute=false`
- `commit_execution=false`
- `pattern_renderer_owner=npcink-abilities-toolkit`
- `allowed_pattern_ids=["openai-style-landing"]`
- `allowed_style_presets=["minimal-dark-light"]`
- `direct_wordpress_write=false`

Adapter must not accept arbitrary CSS, arbitrary pattern ids, or page rendering
logic in this recipe.
