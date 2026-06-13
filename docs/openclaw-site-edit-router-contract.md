# OpenClaw Site Edit Router Contract

Status: accepted

This contract tells OpenClaw how to collapse untrusted customer wording into one
allowed WordPress block-editing route before any proposal handoff. It is not a
prompt, not authorization, and not a write executor.

## Boundary

Customer natural language is untrusted input. Adapter exposes this contract so a
client can normalize that wording to a narrow `surface`, `intent`, `target`, and
`route` tuple, then continue through an existing reviewed recipe.

Adapter must not execute a customer prompt directly. Supported writes still flow
through:

1. read-only context or planning ability;
2. reviewed plan artifact;
3. `POST /wp-json/npcink-openclaw-adapter/v1/proposals/from-plan`;
4. Core approval and commit-preflight;
5. Adapter allowlisted execution profile;
6. read-back verification.

Guardrails:

- `prompt_is_authorization=false`
- `default_behavior=fail_closed`
- `core_proxy_execute=false`
- `commit_execution=false`
- `generic_write_executor=false`

## Normalized Shape

The normalized output is intentionally small:

```json
{
  "surface": "site_template",
  "intent": "add_breadcrumbs",
  "target": {
    "templates": ["single"]
  },
  "route": "block_theme_site_plan",
  "needs_clarification": false
}
```

Supported `surface` values are:

- `post_content`
- `site_template`
- `template_part`
- `navigation`
- `global_styles`
- `unsupported`

Supported routes are:

- `article_block_plan`
- `pattern_page_plan`
- `block_theme_site_plan`
- `unsupported`

## Supported Routes

### `post_content` -> `article_block_plan`

Use this route when the customer asks for a reviewed article draft made from
native Gutenberg blocks.

- Plan ability: `npcink-abilities-toolkit/build-article-block-plan`
- Read-back ability: `npcink-abilities-toolkit/get-post-blocks`
- Final write abilities:
  `npcink-abilities-toolkit/create-draft`,
  `npcink-abilities-toolkit/update-post-blocks`

### `post_content` -> `pattern_page_plan`

Use this route when the customer asks for a reviewed page draft from an allowed
page pattern.

- Plan ability: `npcink-abilities-toolkit/build-pattern-page-plan`
- Read-back ability: `npcink-abilities-toolkit/get-post-blocks`
- Final write abilities:
  `npcink-abilities-toolkit/create-draft`,
  `npcink-abilities-toolkit/update-post-blocks`

### `site_template` -> `block_theme_site_plan`

Use this route when the customer asks for a supported Site Editor template
change.

- Context ability: `npcink-abilities-toolkit/get-block-theme-context`
- Lightweight contract inspection ability:
  `npcink-abilities-toolkit/inspect-gutenberg-composition-contract`
- Read-back abilities:
  `npcink-abilities-toolkit/get-template-blocks`,
  `npcink-abilities-toolkit/get-template-part-blocks`
- Plan ability: `npcink-abilities-toolkit/build-block-theme-site-plan`
- Final write abilities:
  `npcink-abilities-toolkit/update-template-blocks`,
  `npcink-abilities-toolkit/upsert-template-blocks`,
  `npcink-abilities-toolkit/update-template-part-blocks`

The current supported Site Editor intent is only `add_breadcrumbs`. When the
customer asks to check or verify the current result, read the target template
blocks and call `npcink-abilities-toolkit/inspect-gutenberg-composition-contract`
before creating another proposal. A `contract_status=pass` result stops the
flow; `contract_status=needs_revision` may continue only if the violation maps
to the supported breadcrumb placement plan.

## Fail-Closed Surfaces

These customer requests must normalize to `route=unsupported` unless a future
explicit recipe adds its own contract, tests, and execution profile:

- navigation mutations;
- global styles mutations;
- raw theme-file edits;
- raw template HTML;
- `theme.json` patches;
- plugin or database writes;
- auto-approval or direct execution.

## Shared Toolkit Technology

Post content and block theme editing may share Toolkit internals such as block
parsing, serialization, roundtrip validation, block diff previews, read-back
summaries, and editor quality gates. They must not share target write abilities
or skip surface-specific validation.

Use `get-post-blocks` and `update-post-blocks` for post or page content. Use
block-theme context, template reads, and template write abilities for Site Editor
entities.

## Failure Behavior

- Ambiguous target: return `needs_clarification=true` and do not submit a
  proposal.
- Unsupported intent: return `route=unsupported` and do not invent
  `write_actions`.
- Unsupported target: return `route=unsupported` unless an existing route owns
  that exact target.
- Write requested immediately: create or inspect the Core proposal; final write
  still requires approve-and-execute.

The final acceptance signal for successful writes is Core proposal `status=executed` plus read-back verification, not the customer prompt and not model confidence.
