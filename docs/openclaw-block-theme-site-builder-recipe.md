# OpenClaw Block Theme Site Builder Recipe

Status: accepted

This recipe lets OpenClaw guide a conversational block-theme Site Editor flow
without making Adapter a generic WordPress site control plane.

## Boundary

- Context abilities:
  `npcink-abilities-toolkit/get-block-theme-context`,
  `npcink-abilities-toolkit/inspect-block-theme-surface`,
  `npcink-abilities-toolkit/get-template-blocks`, and
  `npcink-abilities-toolkit/get-template-part-blocks`
- Inspection ability:
  `npcink-abilities-toolkit/inspect-block-theme-surface`
- Entrypoint planning ability, only when inspection recommends a fix:
  `npcink-abilities-toolkit/build-block-theme-site-plan`
- Artifact type: `block_theme_site_plan`
- Handoff route: `POST /wp-json/npcink-openclaw-adapter/v1/proposals/from-plan`
- Final route:
  `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/approve-and-execute`
- Final write abilities:
  `npcink-abilities-toolkit/update-template-blocks`,
  `npcink-abilities-toolkit/upsert-template-blocks`, and
  `npcink-abilities-toolkit/update-template-part-blocks`

Toolkit owns block-theme context reads, surface inspection, Site Editor entity
block planning, and the final WordPress Abilities write callbacks. Adapter only
projects the recipe to OpenClaw, forwards plans with reviewed write actions to
Core, and executes allowlisted write actions after Core approval and
commit-preflight.

## Supported MVP

The first supported intent is:

`intent=add_breadcrumbs`

```json
{
  "intent": "add_breadcrumbs",
  "target_templates": ["single", "page"],
  "separator": "/",
  "show_current_item": true,
  "show_home_item": true,
  "show_on_home_page": false
}
```

The plan may update existing `wp_template` records for `single`, `page`,
`archive`, or `index`. When a target is only a file-backed active-theme
template, the plan may create a reviewed `wp_template` Site Editor override via
`npcink-abilities-toolkit/upsert-template-blocks`. It must not edit theme files
or create arbitrary unrelated templates.

## Conversation Contract

OpenClaw may use natural language with the user, but the WordPress operation
must collapse to the narrow plan input schema before any proposal handoff.
Treat the prompt as convenience guidance, not as authorization.

Recommended planner instruction:

```text
You are a WordPress block theme site planner.
Read block theme context first. Convert the user's request only to the narrow
block theme input schema, then run inspect-block-theme-surface with the same
normalized input. If the inspector returns no_changes_required, report that no
proposal is needed. If it returns build_block_theme_site_plan, call
build-block-theme-site-plan and submit to Core only when reviewed write_actions
remain. If it returns manual_review, stop and report the issue codes. If the
request is outside the supported intent or target list, return a warning and do
not write WordPress. Do not output raw template HTML, theme.json patches,
navigation mutations, auto-approval, or direct execution.
```

Supported natural-language mappings:

| User request | Plan input |
| --- | --- |
| "Add breadcrumbs to blog posts." | `{"intent":"add_breadcrumbs","target_templates":["single"],"separator":"/","show_current_item":true,"show_home_item":true,"show_on_home_page":false}` |
| "Add breadcrumbs to posts and pages." | `{"intent":"add_breadcrumbs","target_templates":["single","page"],"separator":"/","show_current_item":true,"show_home_item":true,"show_on_home_page":false}` |

Failure behavior:

- Unsupported intent: return an `unsupported_intent` warning and suggest one
  supported intent.
- Template not found: preserve Toolkit warnings and do not invent a template.
- Active theme is not a block theme: stop after context read.
- Inspector returns `no_changes_required`: stop before plan creation and report
  that the target template state already satisfies the request.
- Inspector returns `manual_review`: stop before proposal creation and report the
  unsupported or uncertain issue codes.
- User asks to apply immediately: create or inspect the Core proposal and wait
  for an explicit approve-and-execute action.

## Flow

1. Run `npcink-abilities-toolkit/get-block-theme-context` through Adapter read
   execution.
2. Confirm `is_block_theme=true` and review discovered templates/template parts.
3. Run `npcink-abilities-toolkit/inspect-block-theme-surface` through Adapter
   read execution.
4. Continue only when
   `dual_review.consensus.recommended_next_step=build_block_theme_site_plan`.
   Stop on `no_changes_required` or `manual_review`.
5. Run `npcink-abilities-toolkit/build-block-theme-site-plan` through Adapter
   read execution.
6. Confirm the returned plan has `artifact_type=block_theme_site_plan`,
   `requires_approval=true`, and `commit_execution=false`.
7. Submit the returned plan to
   `POST /wp-json/npcink-openclaw-adapter/v1/proposals/from-plan` only when
   `write_actions[]` is non-empty.
8. Poll `GET /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}`.
9. Execute only after approval with
   `POST /wp-json/npcink-openclaw-adapter/v1/proposals/{proposal_id}/approve-and-execute`.
10. Read changed templates back with `get-template-blocks` or
   `get-template-part-blocks` and verify the reviewed block tree is present.

## Guardrails

- `proposal_mode=batch`
- `batch_approval=true`
- `core_preflight_required=true`
- `surface_inspection_required=true`
- `proposal_handoff_requires_write_actions=true`
- `core_proxy_execute=false`
- `commit_execution=false`
- `direct_wordpress_write=false`
- `template_write_owner=npcink-abilities-toolkit`
- `file_template_write_mode=create_wp_template_override`
- `allowed_intents=["add_breadcrumbs"]`
- `allowed_template_targets=["single","page","archive","index"]`
- `global_styles_write_allowed=false`
- `navigation_write_allowed=false`
- `generic_write_executor=false`
- `cloud_control_plane=false`

Adapter must not accept arbitrary Site Editor writes, raw `theme.json` patches,
global styles patches, navigation mutations, theme-file edits, provider/model
routing, or workflow runtime behavior in this recipe. Those may become separate
explicit profiles later, each with its own Core proposal surface, Adapter
execution profile, docs, and smoke coverage.

## Verification

After execution, verify:

- changed templates still parse as Gutenberg blocks;
- the first planned block for breadcrumb insertion is a stable `core/group`
  with class `openclaw-breadcrumbs`;
- Site Editor opens the changed template without invalid block recovery prompts;
- frontend views affected by `single` and `page` templates render without obvious
  layout regression;
- Core proposal audit retains the original `block_theme_site_plan`,
  `write_actions[]`, approval, preflight, and Adapter execution record.
