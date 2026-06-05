# OpenClaw Adapter Contract

Status: initial productization contract.

Magick AI Adapter gives OpenClaw a focused WordPress REST surface without
turning Magick AI Core into an execution proxy.

The productized acceptance checklist lives in
[`openclaw-consumer-acceptance.md`](openclaw-consumer-acceptance.md). Keep this
contract as the boundary definition and use the acceptance checklist for
OpenClaw connection verification.

OpenClaw only connects to Adapter. Core is Adapter's governance service for
proposal storage, approval status, commit preflight, and audit attribution.
Adapter may expose the productized OpenClaw user action for
approve-and-execute, but Core remains the governance truth source behind that
action.

## Dependencies

- WordPress 7.0+ with WordPress Abilities API routes available.
- PHP 8.0+.
- `magick-ai-abilities` for canonical ability definitions and callbacks.
- `magick-ai-core` for governance, proposal approval, commit preflight, and
  audit.

## Read Ability Contract

The adapter may execute only capability rows where Core returns:

```json
{
  "governance_mode": "direct_read",
  "execution_surface": "wp_abilities_rest",
  "read_policy": "direct_read_public",
  "sensitivity": "public",
  "redaction_required": false,
  "core_proxy_execute": false,
  "commit_execution": false
}
```

The adapter executes those reads through:

```text
/wp-json/wp-abilities/v1/abilities/{ability_id}/run
```

The read path does not execute abilities marked `proposal_required`.

Every successful read response is an Adapter read envelope. It includes:

- `ability_id`
- `governance_mode=direct_read`
- `execution_surface=wp_abilities_rest`
- `read_policy`
- `sensitivity`
- `redaction_required`
- `redaction_applied`
- `redaction_summary`
- `read_audit_mode`
- `correlation_id`
- `log_context`
- `read_context`
- `commit_execution=false`
- `result`

For `direct_read_sensitive` rows or any row with
`redaction_required=true`, Adapter applies bounded recursive redaction before
returning `result`. It redacts values under sensitive keys such as passwords,
secrets, tokens, authorization headers, cookies, nonces, email fields, and API
or private keys. This is a product-surface redaction layer; Core remains the
capability guidance source and WordPress Abilities API remains the canonical
ability execution surface.

## Media Derivative Cloud Contract

For optimized media derivative generation, Adapter exposes a bounded Cloud
Addon orchestration seam:

```text
POST /wp-json/magick-ai-adapter/v1/media-derivative-runs
GET  /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}
GET  /wp-json/magick-ai-adapter/v1/media-derivative-runs/{run_id}/result
GET  /wp-json/magick-ai-adapter/v1/media-derivative-artifacts/{artifact_id}/preview
POST /wp-json/magick-ai-adapter/v1/media-derivative-proposal-payload
```

`POST /media-derivative-runs` builds the local
`magick-ai/build-media-derivative-cloud-request` direct-read ability response,
uses Core media derivative defaults when available, and dispatches only through
the Cloud Addon public media derivative helper. Source media can be sent as the
local attachment file or as a caller-provided short-TTL source artifact
descriptor. Image watermark media can be sent as a caller-provided short-TTL
artifact or as the locally configured Core watermark attachment when an image
watermark plan is present. Text watermark plans do not require watermark media;
Adapter passes their text, font, color, background, margin, opacity, and
position through the Cloud Addon dispatch seam.

The route returns a Cloud run projection and the local ability response. It
does not store run truth, artifact truth, Cloud credentials, approval truth, or
media registry state. `GET /media-derivative-runs/{run_id}` and
`GET /media-derivative-runs/{run_id}/result` read bounded run/result
projections through Cloud Addon. The result projection may include a same-origin
`preview_url`; `GET /media-derivative-artifacts/{artifact_id}/preview` serves
one non-expired derivative artifact through Cloud Addon for local preview only.
Browser image requests may use WordPress REST auth or the short-lived local
`preview_sig` embedded in the URL. It does not store artifact truth, expose a
public Cloud URL, or write WordPress media. `POST
/media-derivative-proposal-payload` builds a Core-ready local proposal payload
from the ability response, Cloud result, and derivative artifact, but does not
create, approve, preflight, or execute a proposal.

The returned payloads must preserve:

- `final_write_owner=local_wordpress_host`;
- `wordpress_write_included=false`;
- `attachment_metadata_write_included=false`;
- `commit_execution=false`.

Any recording, attachment metadata update, media replacement, or rollback must
enter Core proposal governance and pass Core approval plus commit-preflight
before Adapter's allowlisted final execution path can run.

## Read-Only Planning Contract

The adapter may execute these planning abilities only when Core reports them as
direct reads:

- `magick-ai/build-content-inventory-fix-plan`
- `magick-ai/build-test-content-cleanup-plan`
- `magick-ai/build-media-inventory-fix-plan`
- `magick-ai/build-media-reference-repair-plan`
- `magick-ai/build-media-settings-reference-repair-plan`
- `magick-ai/build-media-optimization-plan`
- `magick-ai/build-media-rename-plan`
- `magick-ai-toolbox/build-article-write-plan`
- `magick-ai-toolbox/build-article-batch-write-plan`
- `magick-ai-toolbox/build-article-media-batch-write-plan`
- `magick-ai-toolbox/build-image-candidate-adoption-plan`

OpenClaw may also use direct read execution for media format inspection:

- `magick-ai/inspect-media-asset`

Planning ability outputs are plan data, not execution results. Adapter must
preserve `batch_id`, `issue_types`, `post_ids`, `attachment_ids`,
`write_actions`, `preview`, `risk`, `requires_approval`, `commit_execution`,
`dry_run`, `manual_review`, `skipped_destructive_candidates`, `issue_counts`, and
`action_count`.

For Toolbox article writing, `magick-ai-toolbox/build-article-write-plan`
returns a reviewed `article_write_plan`. Adapter may forward that plan to Core,
but Core validates readiness, risk, blocked claims, and draft-only
`magick-ai/create-draft` intent before any proposal can be approved or
executed.

For Toolbox article batch writing,
`magick-ai-toolbox/build-article-batch-write-plan` returns a reviewed
`article_batch_write_plan`. Adapter may forward that plan to Core only as a
batch proposal handoff; each executable action must still target the
`magick-ai/create-draft` execution profile, keep `status=draft`, and pass Core
approval plus commit-preflight before Adapter execution.

For Toolbox article media batch writing,
`magick-ai-toolbox/build-article-media-batch-write-plan` returns a reviewed
`article_media_batch_write_plan`. Adapter may forward that plan to Core only as
a batch proposal handoff; each executable action must still target an explicit
Adapter profile such as `magick-ai/create-draft`,
`magick-ai/upload-media-from-url`, `magick-ai/update-media-details`, or
`magick-ai/set-post-featured-image`, and pass Core approval plus
commit-preflight before Adapter execution.

For Toolbox image candidate adoption,
`magick-ai-toolbox/build-image-candidate-adoption-plan` returns a reviewed
`image_candidate_adoption_plan` from one normalized `image_candidate.v1`
candidate. Adapter may forward that plan to Core only as a batch proposal
handoff; each executable action must still target an explicit Adapter profile
such as `magick-ai/upload-media-from-url`,
`magick-ai/update-media-details`, or
`magick-ai/set-post-featured-image`, and pass Core approval plus
commit-preflight before Adapter execution.

## OpenClaw Recipe Discovery

`GET /help` includes `openclaw_recipes.article_draft_plan` for clients that need
a machine-readable fixed flow. The recipe is channel guidance only:

- entrypoint ability: `magick-ai-toolbox/build-article-write-plan`
- plan handoff route: `POST /proposals/from-plan`
- status route: `GET /proposals/{proposal_id}`
- final route: `POST /proposals/{proposal_id}/approve-and-execute`
- final write ability: `magick-ai/create-draft`

The recipe must keep `core_proxy_execute=false`,
`commit_execution=false`, `draft_only=true`, and `publish_allowed=false`.
Adapter does not become an article workflow runtime or a Cloud control plane.

Toolbox may expose click-driven buttons for the same fixed flows that Adapter
publishes to OpenClaw. Those buttons must mirror the same ability ids, artifact
types, and Core proposal handoff routes; they do not make Toolbox a second
OpenClaw recipe owner, proposal truth, approval surface, or write executor.

`GET /help` also includes `openclaw_recipes.article_batch_draft_plan` for
reviewed 2-5 article draft batches:

- entrypoint ability: `magick-ai-toolbox/build-article-batch-write-plan`
- plan handoff route: `POST /proposals/from-plan`
- status route: `GET /proposals/{proposal_id}`
- final route: `POST /proposals/{proposal_id}/approve-and-execute`
- final write ability: `magick-ai/create-draft`
- artifact type: `article_batch_write_plan`
- proposal mode: `batch`

The batch recipe must keep `batch_approval=true`, `partial_success=false`,
`core_proxy_execute=false`, `commit_execution=false`, `draft_only=true`, and
`publish_allowed=false`.

`GET /help` also includes `openclaw_recipes.article_media_batch_plan` for
reviewed article drafts with selected image-source candidates:

- entrypoint ability:
  `magick-ai-toolbox/build-article-media-batch-write-plan`
- plan handoff route: `POST /proposals/from-plan`
- status route: `GET /proposals/{proposal_id}`
- final route: `POST /proposals/{proposal_id}/approve-and-execute`
- final write abilities: `magick-ai/create-draft`,
  `magick-ai/upload-media-from-url`, `magick-ai/update-media-details`, and
  `magick-ai/set-post-featured-image`
- artifact type: `article_media_batch_write_plan`
- proposal mode: `batch`

The article media batch recipe must preserve image-source attribution and keep
`batch_approval=true`, `partial_success=false`, `core_proxy_execute=false`,
`commit_execution=false`, `draft_only=true`, and `publish_allowed=false`.

`GET /help` also includes `openclaw_recipes.image_candidate_adoption_plan` for
reviewed adoption of one image candidate into the media library:

- entrypoint ability:
  `magick-ai-toolbox/build-image-candidate-adoption-plan`
- candidate contract: `image_candidate.v1`
- plan handoff route: `POST /proposals/from-plan`
- status route: `GET /proposals/{proposal_id}`
- final route: `POST /proposals/{proposal_id}/approve-and-execute`
- final write abilities: `magick-ai/upload-media-from-url`,
  `magick-ai/update-media-details`, and optional
  `magick-ai/set-post-featured-image`
- artifact type: `image_candidate_adoption_plan`
- proposal mode: `batch`

The image candidate adoption recipe must preserve source attribution and keep
`batch_approval=true`, `core_proxy_execute=false`,
`commit_execution=false`, and `cloud_control_plane=false`. Adapter does not
search stock providers, generate images, upload media, set featured images, or
create a media registry by itself.

`commit_execution=false` means no write happened, `dry_run=true` means preview
only, and `requires_approval=true` means the plan must be handed to Core or the
host governance layer. Adapter must not execute, approve, or promote
destructive candidates such as `magick-ai/delete-media-permanently`,
`magick-ai/delete-post-permanently`, `magick-ai/delete-term`,
`magick-ai/trash-post`, `magick-ai/trash-comment`, or
`magick-ai/spam-comment`.

## Governed Write Contract

For write or destructive abilities, the adapter relays to Core:

```text
POST /wp-json/magick-ai-core/v1/proposals
POST /wp-json/magick-ai-core/v1/proposals/from-plan
POST /wp-json/magick-ai-core/v1/proposals/{proposal_id}/commit-preflight
```

The adapter does not store proposal governance state. It may call Core approval
only as part of the explicit unified approve-and-execute action.

Failure responses for plan intake, rejected proposals, and commit-preflight
blocks may include additive `data.operator_feedback`. This is an OpenClaw
display contract, not an Adapter approval store. It summarizes Core or Adapter
evidence as `status`, `severity`, `message`, `reasons[]`,
`revision_fields[]`, `next_steps[]`, `can_retry_after_revision`, and
`core_evidence` so the operator can revise the source plan or draft and create
a new proposal.

`POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/commit-preflight`
is an advanced diagnostic Adapter route. Core handoffs are one-time; when this
route succeeds through Adapter, Adapter stores only a bounded handoff cache for
the next Adapter execute request for the same approved proposal input. OpenClaw
must not call Core commit-preflight directly and then ask Adapter to execute the
same proposal.

Dry-run-only proposal verification stops at Adapter commit-preflight. Adapter `execute`, `execute-approved-proposal`, and `approve-and-execute` routes are final write paths; immediately before dispatching a WordPress ability, Adapter normalizes the ability input to `dry_run=false` and `commit=true`. OpenClaw must not call an execute route when the operator only asked to verify a dry-run proposal or preflight.

Before Adapter calls the WordPress Abilities API for a final allowlisted write,
it must verify Core's `approval_context.approved_input_hash` matches the current
proposal input hash and that `approval_context.policy_version` is
`core-preflight-v1`. If Core also returns an `execution_handoff`, its hash and
policy version must match the same approved input and policy. Mismatches fail
closed; Adapter must not repair, re-approve, or execute the proposal.

## Unified Approve And Execute Contract

Adapter exposes one user-facing action for the minimal destructive execution
loop:

```text
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute
```

For a pending `magick-ai/trash-post`, `magick-ai/create-draft`,
`magick-ai/update-post`, `magick-ai/set-post-seo-meta`,
`magick-ai/set-post-slug`, `magick-ai/set-post-terms`,
`magick-ai/delete-term`, `magick-ai/update-media-details`,
`magick-ai/patch-post-content`,
`magick-ai/patch-setting-value`,
`magick-ai/optimize-media-asset`,
`magick-ai/replace-media-file`,
`magick-ai/adopt-cloud-media-derivative`,
`magick-ai/rename-media-file`,
`magick-ai/delete-media-permanently`,
`magick-ai/reply-comment`, `magick-ai/trash-comment`, or
`magick-ai/approve-comment` proposal, Adapter
fetches the proposal from Core,
calls Core approve, calls Core commit-preflight, verifies Core's approval
context and executable preflight result, then executes one WordPress Abilities
API call. For an already approved proposal, Adapter skips only the Core approve
step and still obtains commit-preflight authorization before execution.

The execution input may be either top-level `proposal.input` for an allowlisted
ability or a bounded `proposal.input.write_actions[]` batch. `trash-post`
requires `post_id`; `create-draft` requires `title`; `update-post` requires
`post_id` plus at least one of `title`, `content`, or `excerpt`;
`set-post-seo-meta` requires `post_id` plus `seo_title` or
`seo_description`; `set-post-slug` requires `post_id` and a valid `slug`;
`set-post-terms` requires `post_id`, a valid `taxonomy`, `mode`, and
`term_ids` or `terms`, and does not create missing terms; `delete-term`
requires a valid `taxonomy` and `term_id`; `update-media-details` requires
`attachment_id` plus at least one media detail field; media `source_type`, when
provided, must be one of `owned`, `ai_generated`, `stock`, `external`, or
`test`; `upload-media-from-url` may accept a reviewed `file_name` for the new
media object; `patch-post-content` requires `post_id` and bounded exact replacement
`operations`; `patch-setting-value` requires `target_type`, `target_name`, and
bounded exact replacement `operations`; `optimize-media-asset` requires `attachment_id`, may accept bounded
format, width, quality, and suffix inputs, and must preserve the original file;
`replace-media-file` requires `attachment_id`, uses either a recorded
`derivative_relative_file` for replace mode or a `replacement_id` for rollback
mode, and records backup/rollback metadata;
`adopt-cloud-media-derivative` requires `attachment_id` and
`derivative_artifact` evidence, may accept a reviewed `file_name` for the
adopted derivative, then delegates any approved local download, backup,
attachment pointer, and metadata writes to the WordPress ability;
`rename-media-file` requires an existing attachment `attachment_id` and a
reviewed `target_file_name`, may accept expected current relative path, MIME
type, MD5, SHA256, conflict mode, and backup suffix guards, then delegates the
approved main-file rename and attachment URL update to the WordPress ability;
`delete-media-permanently` requires an existing attachment `attachment_id`;
`reply-comment` requires `comment_id`, non-empty `content`, and a valid
`content_format`; `trash-comment` requires `comment_id`;
`approve-comment` requires `comment_id`. See
[`openclaw-batch-execution-policy.md`](openclaw-batch-execution-policy.md).

The response must include `proposal_id`, `post_id`, `ability_id`,
`correlation_id`, `status_before`, whether Adapter performed approval, Core
`commit_execution=false`, an `execution_record`, and the execution result.
Rejected proposals, non-allowlisted abilities, preflight failures, and
duplicate execution attempts must not execute.

## Approved Proposal Execution Contract

Adapter may execute one approved Core proposal only through:

```text
POST /wp-json/magick-ai-adapter/v1/execute-approved-proposal
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/execute
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute
```

The current allowlist is intentionally narrow:

- `magick-ai/trash-post`
- `magick-ai/create-draft`
- `magick-ai/update-post`
- `magick-ai/set-post-seo-meta`
- `magick-ai/set-post-slug`
- `magick-ai/set-post-terms`
- `magick-ai/delete-term`
- `magick-ai/update-media-details`
- `magick-ai/patch-post-content`
- `magick-ai/patch-setting-value`
- `magick-ai/optimize-media-asset`
- `magick-ai/replace-media-file`
- `magick-ai/adopt-cloud-media-derivative`
- `magick-ai/rename-media-file`
- `magick-ai/delete-media-permanently`
- `magick-ai/reply-comment`
- `magick-ai/trash-comment`
- `magick-ai/approve-comment`

The allowlist applies to both single-ability execution and each
`write_actions[]` item. A batch containing any non-allowlisted action fails
closed and executes no actions.

The allowlist is derived from Adapter's local execution profile registry, not
from capability discovery alone. Each profile entry is an explicit opt-in for
final WordPress writes and must define the Adapter-owned execution shape:

- ability id;
- required input checks such as `post_id`, `comment_id`, or `title`;
- ability-specific guards such as term taxonomy/mode validation;
- whether execution input must be rebuilt before dispatch;
- whether `post_id` should be read back from the ability result;
- smoke coverage for both the success path and Adapter-owned rejection paths.

OpenClaw may use capability discovery to decide what can be proposed, but
Adapter must use execution profiles to decide what can be executed after Core
approval and commit-preflight.

For abilities that have an Adapter execution profile, `POST /proposals` must
validate the profile-owned input shape before forwarding to Core. That includes
rejecting undeclared input fields and invalid enum values, so a proposal that
Adapter would later execute cannot be created with obviously invalid execution
input. For example, `magick-ai/update-post` does not accept `status`, and
`magick-ai/create-draft` only accepts `status=draft`.

For each execution request, Adapter must first reject any proposal with a
completed Adapter execution record. If there is no completed record, Adapter
must fetch the Core proposal, consume a cached Adapter preflight handoff when
one was issued through Adapter, otherwise call Core commit-preflight, require
`approval_commit_authorized=true`, require `commit_execution=false`, pass Core
`approval_context` to WordPress Abilities API, and return `proposal_id`,
`correlation_id`, `ability_id`, and `execution_record` with the ability result.
After a successful execution, Adapter stores only a bounded public-safe
execution record keyed by proposal id for replay protection. When execution
fails after Core preflight has been consumed, Adapter stores the same bounded
public-safe record shape with `status=failed`, `error_code`, failed action
metadata, executed counts, and Core correlation only; it does not store the full
proposal or create a retry queue. Core remains the proposal, approval,
preflight, and audit truth source.

Within one approved `write_actions[]` batch, Adapter may resolve exact output
references in action input values:

```text
$outputs.<prior_action_id>.<field>
```

References are evaluated only in memory while executing that batch, must point
to an earlier action in the same proposal, and must occupy the whole input
value. Output references cannot be embedded into larger strings. Adapter does
not persist run state, evaluate expressions, branch, loop, or resolve
references across proposals.

Adapter must not generate its own approval state, skip Core commit-preflight,
skip `approval_context`, execute unapproved proposals outside the unified
action, execute preflight failures, or batch silently execute destructive
actions. Future execution abilities must be added as explicit Adapter execution
profile entries with dedicated smoke coverage; Adapter must not become a
generic proxy-execute surface.

## AI Request Log Correlation

Adapter keeps Core audit and AI Request Logs separate, but it carries stable
correlation fields between them.

For read routes and future execution handoff routes, OpenClaw may pass:

- `proposal_id`;
- `correlation_id`;
- `external_thread_id`;
- `openclaw_thread_id`;
- `adapter_request_id`;
- `adapter_route`;
- `ai_provider`;
- `ai_model`;
- a top-level `log_context` object on POST `/run-read-ability`.

Adapter must not forward those reserved query fields as ability input. While an
ability is running, Adapter adds the sanitized values to AI Request Logs via the
`wpai_request_log_context` filter. The AI log context receives a
`magick_ai_adapter` object, top-level provider correlation fields, and nested
`magick_ai_core.proposal_id` / `magick_ai_core.correlation_id` when present.

Every real provider call owned by Adapter must write at least:

```text
proposal_id
correlation_id
ability_id
adapter_request_id
adapter_route
ai_provider
ai_model
governance_source=magick-ai-core
```

Core Governance Audit is the governance log. WordPress `ai` plugin AI Request
Logs are the provider request log. Adapter carries identifiers between them but
does not store provider credentials, prompts, responses, token details, or AI
Request Logs in Core. If the AI Request Logs provider column is blank for a
local connector, OpenClaw should inspect Adapter context fields for the
explicit `ai_provider` and `ai_model` sent to the provider smoke route. Local
Ollama examples use `ai_model=qwen3.5:0.8b` when that model is available.
AI Request Logs are the provider request log.

For local readiness smoke, Adapter exposes:

```text
POST /wp-json/magick-ai-adapter/v1/ai-provider-log-correlation-smoke
```

This route is bounded to proving WordPress AI Client / provider request log
correlation. It is an administrator-triggered diagnostics route, not a
production workload route. The request prompt is used only for the bounded
smoke request and must not become Adapter prompt storage, prompt management, or
product UX. It is not workflow runtime, MCP runtime, model routing policy, or
final WordPress mutation.

## Proposal Status Read Proxy

OpenClaw connects to Magick AI Adapter, not directly to Core. The adapter may
therefore expose these read-only Core proposal status routes:

```text
GET /wp-json/magick-ai-adapter/v1/proposals
GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}
```

They relay to:

```text
GET /wp-json/magick-ai-core/v1/proposals
GET /wp-json/magick-ai-core/v1/proposals/{proposal_id}
```

The adapter should preserve Core proposal list/detail fields, including
`proposal_id`, `ability_id`, `status`, `title`, `summary`, `input`, `preview`,
`caller`, `created_at`, `updated_at`, and detail `audit_timeline` when Core
returns it. If a Core app token is used in a direct Core auth path or future
trusted handoff, that key must include `proposals:read`.

The Adapter admin page may expose a focused `Proposal status` lookup that uses
the same read-only proxy. That lookup can show Core status, link to the Core
approval detail, and copy Adapter status or approved-execution endpoints. It
must not become a Core approval table, Core audit table, or generic
approve/reject proxy.

The adapter must not print Core tokens in logs, proposal payloads, error
responses, or documentation examples. It must not add proposal approval or
rejection proxy routes by default.

Adapter Core app token configuration may come from
`MAGICK_AI_ADAPTER_CORE_APP_TOKEN` or the
`magick_ai_adapter_core_app_token` option. When configured, the token is used
only for internal Core REST calls through the request header supported by Core,
and the raw value must not appear in health, help, handoff text, error details,
proposal payloads, or docs examples.

The narrow Adapter governance token for proposal create, proposal status, and
commit-preflight needs only `proposals:create`, `proposals:read`, and
`commit:preflight`. It must not include `proposals:approve`, `proposals:reject`,
or `audit:read`. If a full Adapter discovery smoke also calls Core
capabilities, add `capabilities:read` for that wider smoke only.

## Approval Disabled Stub Contract

The adapter exposes these routes only as disabled stubs:

```text
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve
POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject
```

Default response:

```json
{
  "code": "magick_ai_adapter_approval_proxy_disabled",
  "message": "Direct approve/reject proxy routes are disabled. Use POST /proposals/{proposal_id}/approve-and-execute for the Adapter unified user action, or use Magick AI Core admin for split approval decisions.",
  "approval_proxy_enabled": false,
  "approval_surface": "magick_ai_core_admin",
  "unified_action_route": "POST /proposals/{proposal_id}/approve-and-execute"
}
```

The disabled stubs must not forward to Core approval or rejection routes. The
default Core app key used by Adapter must not require approval or rejection
scopes. OpenClaw and agents must not receive default approval power through
standalone approve/reject proxy routes.

The supported Adapter-side approval action is the unified
`approve-and-execute` route. Adapter must not expose a generic approve/reject
proxy without a separate explicit trusted-host policy and ADR-backed feature.
The disabled stubs and top-level health contract preserve
`approval_surface=magick_ai_core_admin` to make the standalone proxy boundary
explicit.

## First Product Routes

Connection:

- WordPress admin: `Magick AI -> Adapter`
- `GET /wp-json/magick-ai-adapter/v1/health`
- `GET /wp-json/magick-ai-adapter/v1/help`

Read shortcuts:

- `GET /wp-json/magick-ai-adapter/v1/site-info`
- `GET /wp-json/magick-ai-adapter/v1/site-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-diagnostics-summary`
- `GET /wp-json/magick-ai-adapter/v1/wp-ops-diagnostics-detail`
- `GET /wp-json/magick-ai-adapter/v1/active-plugins-detail`
- `GET /wp-json/magick-ai-adapter/v1/plugin-conflict-diagnostics`
- `GET /wp-json/magick-ai-adapter/v1/recent-error-log`
- `GET /wp-json/magick-ai-adapter/v1/recent-error-log-tail`
- `GET /wp-json/magick-ai-adapter/v1/current-user-permissions`
- `GET /wp-json/magick-ai-adapter/v1/php-extensions`
- `GET /wp-json/magick-ai-adapter/v1/object-cache-status`
- `GET /wp-json/magick-ai-adapter/v1/database-info`
- `GET /wp-json/magick-ai-adapter/v1/rewrite-rules-status`
- `GET /wp-json/magick-ai-adapter/v1/cron-events-detail`
- `GET /wp-json/magick-ai-adapter/v1/ssl-https-status`
- `GET /wp-json/magick-ai-adapter/v1/custom-post-types`
- `GET /wp-json/magick-ai-adapter/v1/roles-capabilities`
- `GET /wp-json/magick-ai-adapter/v1/widgets-sidebars`
- `GET /wp-json/magick-ai-adapter/v1/block-theme-assets`
- `GET /wp-json/magick-ai-adapter/v1/search-index-status`
- `GET /wp-json/magick-ai-adapter/v1/server-info`
- `GET /wp-json/magick-ai-adapter/v1/integrations-status`
- `GET /wp-json/magick-ai-adapter/v1/seo-summary`
- `GET /wp-json/magick-ai-adapter/v1/security-summary`
- `GET /wp-json/magick-ai-adapter/v1/performance-summary`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipes`
- `GET /wp-json/magick-ai-adapter/v1/workflow-recipe?recipe_id=workflow/...`
- `GET /wp-json/magick-ai-adapter/v1/posts`
- `GET /wp-json/magick-ai-adapter/v1/post-context`
- `GET /wp-json/magick-ai-adapter/v1/media`
- `GET /wp-json/magick-ai-adapter/v1/terms`
- `GET /wp-json/magick-ai-adapter/v1/taxonomy-terms`
- `GET /wp-json/magick-ai-adapter/v1/categories`
- `GET /wp-json/magick-ai-adapter/v1/tags`
- `GET /wp-json/magick-ai-adapter/v1/term?id={terms.result.items[].id}`
- `GET /wp-json/magick-ai-adapter/v1/comments`
- `GET /wp-json/magick-ai-adapter/v1/users`
- `GET /wp-json/magick-ai-adapter/v1/menu`
- `GET /wp-json/magick-ai-adapter/v1/internal-link-targets`
- `GET /wp-json/magick-ai-adapter/v1/post-stats`
- `GET /wp-json/magick-ai-adapter/v1/post-revisions`
- `GET /wp-json/magick-ai-adapter/v1/post-meta`
- `GET /wp-json/magick-ai-adapter/v1/pages`
- `GET /wp-json/magick-ai-adapter/v1/page`
- `GET /wp-json/magick-ai-adapter/v1/page-structure`
- `GET /wp-json/magick-ai-adapter/v1/pages-tree`
- `GET /wp-json/magick-ai-adapter/v1/content-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/content-inventory-fix-plan`
- `GET /wp-json/magick-ai-adapter/v1/test-content-cleanup-plan`
- `GET /wp-json/magick-ai-adapter/v1/site-operations-dashboard`
- `GET /wp-json/magick-ai-adapter/v1/publishing-calendar-context`
- `GET /wp-json/magick-ai-adapter/v1/media-inventory-health`
- `GET /wp-json/magick-ai-adapter/v1/media-inventory-fix-plan`
- `GET /wp-json/magick-ai-adapter/v1/taxonomy-inventory-health`

Generic read:

- `POST /wp-json/magick-ai-adapter/v1/run-read-ability`

Diagnostics shortcuts must remain aliases over `magick-ai-abilities`
direct-read abilities. Adapter must not collect plugin details, error-log
details, current-user capabilities, PHP extension state, database details,
rewrite state, cron details, roles, widgets, block-theme details, or search
status itself.

`wp-diagnostics-summary` is only a quick overview. OpenClaw must not use it to
decide whether plugin details, current-user permission details, or error-log
details are missing. All P0/P1/P2 troubleshooting detail shortcuts call
`npcink-abilities-toolkit/wp-ops-diagnostics-detail`.

Default detail input:

```json
{
  "include_log_contents": false,
  "include_active_plugins": true,
  "include_inactive_plugins": false,
  "include_plugin_updates": true,
  "include_must_use_plugins": true,
  "include_dropins": true,
  "max_plugins_per_group": 100
}
```

Deep plugin conflict input:

```json
{
  "include_log_contents": false,
  "include_active_plugins": true,
  "include_inactive_plugins": true,
  "include_plugin_updates": true,
  "include_must_use_plugins": true,
  "include_dropins": true,
  "max_plugins_per_group": 200
}
```

Explicit log inspection input:

```json
{
  "include_log_contents": true,
  "tail_lines": 50,
  "severity": ["fatal", "error", "warning"],
  "since_minutes": 1440
}
```

When `include_log_contents=false`, log contents are not missing; mark them as
not explicitly requested. OpenClaw should use `error_log.summary` for
`fatal_count`, `error_count`, `warning_count`, `deprecated_count`,
`notice_count`, `summary_source`, and `error_log.summary.by_severity` without
forcing log contents. Only display `error_log.tail_entries` or `contents` after
explicit log inspection. Inactive plugin rows are not requested by default and
must not be marked missing; use the deep plugin conflict input when the user
needs inactive plugin rows. Adapter must not implement `include_log_tail`
compatibility. Adapter also must not mix Magick AI runtime, MCP, or cloud status
into this WordPress diagnostics mapping.

The diagnostics detail response is expected to preserve these fields when the
ability returns them:

- P0: `plugins.groups_included`, `plugins.max_plugins_per_group`,
  `plugins.available_count`, `plugins.active_count`,
  `plugins.inactive_count`, `plugins.update_available_count`,
  `plugins.mu_count`, `plugins.dropin_count`, `plugins.active`,
  `plugins.inactive`, `plugins.update_available`, `plugins.must_use`,
  `plugins.dropins`, `current_user`, `error_log`
- P1: `php.extensions.loaded`, `php.extensions.common_status`,
  `object_cache`, `rewrite`, `database`, `server`
- P2: `https`, `content_types`, `roles`, `widgets`, `block_theme`, `search`,
  `integrations`, `seo_summary`, `security_summary`, `performance_summary`,
  `cron_events.events`

Plugin rows should be displayed with `slug`, `plugin_file`, `name`, `version`,
`author`, `status`, `network_active`, `must_use`, `requires_wp`,
`requires_php`, `dependencies`, `dependency_count`, `is_magick_ai`,
`update_available`, and `latest_version`. Current-user rows should display
`user_id`, `user_login`, `display_name`, `roles`, `capabilities`,
`common_capabilities`, and `magick_ai_permissions`. Error-log rows should
display `contents_included`, `log_exists`, `log_readable`, `log_size_bytes`,
`log_modified_gmt`, `summary`, `summary.returned_lines`,
`summary.fatal_count`, `summary.error_count`, `summary.warning_count`,
`summary.deprecated_count`, `summary.notice_count`, `summary.info_count`,
`summary.unknown_count`, `summary.latest_fatal_at`, `summary.latest_error_at`,
`summary.latest_warning_at`, `summary.latest_deprecated_at`,
`summary.latest_notice_at`, `summary.summary_source`,
`summary.by_severity`, `severity_filter`, and `since_minutes`. Display
`tail_entries` and `contents` only when `contents_included=true`.

Content shortcuts forward query parameters into the ability input, including
`magick-ai/list-posts` filters (`author_id`, `taxonomy`, `term_id`,
`term_slug`, `date_after`, `date_before`, `modified_after`,
`modified_before`, `orderby`, `order`), term sample-post flags
(`include_sample_posts`, `sample_post_limit`), user `author_profile`, comment
post context, media `attached_to`/`usage`, and `magick-ai/get-menu` tree output.
Adapter does not reshape those fields.

Governance:

- `GET /wp-json/magick-ai-adapter/v1/capabilities`
- `GET /wp-json/magick-ai-adapter/v1/proposals`
- `GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}`
- `POST /wp-json/magick-ai-adapter/v1/proposals`
- `POST /wp-json/magick-ai-adapter/v1/proposals/from-plan`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/commit-preflight`
- `POST /wp-json/magick-ai-adapter/v1/execute-approved-proposal`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/execute`
- `POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve-and-execute`

## Security

All routes require `manage_options` through WordPress REST authentication.
OpenClaw should connect with a dedicated administrator Application Password for
the local PoC. Narrower adapter identity and scope can be added after the first
product flow is proven.

The Magick AI Adapter admin page may display endpoint URLs, health state,
example requests, a non-secret connection manifest, and a handoff prompt. It
may create a normal WordPress Application Password for the current
administrator and show the raw password once in the browser. It must not store
raw secrets in adapter options, manifest JSON, handoff text,
example curl commands, files, logs, proposal payloads, create Core app keys,
persist connection state, approve proposals, or change Core/Abilities ownership.

## Application Password Handoff

Handoff data:

- `connection_id`, such as `local-wordpress`.
- Adapter base URL.
- WordPress username for the dedicated OpenClaw account.
- Auth type `wordpress_application_password`.
- Application Password UUID.
- Health, help, and capabilities URLs.
- A note that the Application Password must be stored through OpenClaw's
  dedicated secret field or credential vault, not chat, tools, files, logs,
  proposal payloads, or copied handoff text.

For the current LocalWP development site only, the WordPress administrator
browser login is username `1` and password `1`. This local-only password is for
admin browser access and Application Password creation; OpenClaw REST
configuration should use a dedicated Application Password.

If OpenClaw does not expose a credential store or import endpoint, paste the
Application Password only into OpenClaw's dedicated secret field. Do not paste
it into chat.

Local credential brokers should use key-pair device pairing instead of browser
secret handling:

```text
GET  /wp-json/magick-ai-adapter/v1/connection/manifest
POST /wp-json/magick-ai-adapter/v1/connect/device/start
POST /wp-json/magick-ai-adapter/v1/connect/device/poll
GET  /wp-json/magick-ai-adapter/v1/connection/key-pairs
```

`/connect/device/start` accepts public client metadata and an Ed25519 public
key. The WordPress admin approval page binds that public key to the approving
administrator. `/connect/device/poll` returns connection metadata after
approval. It never returns a WordPress Application Password or private key.

Signed Adapter requests use the `Magick-AI-Adapter-V1` canonical request and
`X-Magick-*` signing headers documented in
`docs/keypair-device-pairing-contract.md`.

OpenClaw must use WordPress REST Basic Auth:

```text
Authorization: Basic base64(username:<openclaw-secret-field-value>)
```

Connection check order:

1. `GET /health`.
2. `GET /help`.
3. `GET /capabilities`.
4. direct-read shortcut or `POST /run-read-ability`.
5. optional plan handoff with `POST /proposals/from-plan`.
6. proposal-required `POST /proposals`.
7. proposal status polling with `GET /proposals/{proposal_id}`.
8. unified user action with `POST /proposals/{proposal_id}/approve-and-execute`
   for allowlisted execution, or split approval in Core admin.
9. rejected proposal stops the flow.
10. approved proposal split path uses `POST /proposals/{proposal_id}/execute`;
    Adapter commit-preflight is diagnostic and must be followed immediately by
    Adapter execute.

## Proposal-Required Write Flow

OpenClaw must treat Core as the only proposal and approval truth:

1. Read `/capabilities` and select a real `ability_id` where
   `governance_mode=proposal_required`.
2. Send `POST /proposals` with the real `ability_id`, dry-run style `input`,
   rendered or structured `preview`, and `caller` metadata.
3. Poll `GET /proposals/{proposal_id}` through the adapter for Core status.
4. If `status=pending` and the user chooses the unified OpenClaw action, call
   `POST /proposals/{proposal_id}/approve-and-execute`. Adapter calls Core
   approve, then Core commit-preflight, then one allowlisted final write.
5. If `status=rejected`, stop and show the rejection state or reason returned
   by Core.
6. If using the lower-level split path and `status=approved`, call
   `POST /proposals/{proposal_id}/execute`. Use Adapter commit-preflight only
   as an advanced diagnostic step. For dry-run-only verification, stop at
   commit-preflight and do not call execute. If execution is intended, Adapter
   execute normalizes ability input to `dry_run=false` and `commit=true`.
7. Adapter stops unless the ability is
   allowlisted for Adapter execution, currently `magick-ai/trash-post`,
   `magick-ai/create-draft`, `magick-ai/update-post`,
   `magick-ai/set-post-seo-meta`, `magick-ai/set-post-slug`,
   `magick-ai/set-post-terms`, `magick-ai/delete-term`,
   `magick-ai/update-media-details`, `magick-ai/patch-post-content`,
   `magick-ai/patch-setting-value`,
   `magick-ai/optimize-media-asset`,
   `magick-ai/replace-media-file`, `magick-ai/adopt-cloud-media-derivative`,
   `magick-ai/rename-media-file`,
   `magick-ai/delete-media-permanently`,
   `magick-ai/reply-comment`, `magick-ai/trash-comment`, and
   `magick-ai/approve-comment`.

Adapter invariants:

- It can call Core approve only inside
  `POST /proposals/{proposal_id}/approve-and-execute`.
- It does not store proposal or approval state.
- It stores bounded execution records only to prevent replaying an already
  completed Adapter write.
- It owns only explicit post-Core execution profile policy for allowlisted
  approved writes.
- It does not expose a generic approve/reject proxy.
- It does not execute final WordPress mutations outside the allowlisted
  approve-and-execute or approved-proposal execution path.
- It preserves `core_proxy_execute=false`.
- It preserves `commit_execution=false`.
- It exposes `approval_proxy_enabled=false`.
- It keeps Core as the approval, preflight, and audit truth source.

For read-only planning abilities, OpenClaw may instead send the returned plan
to `POST /proposals/from-plan`. Adapter only forwards the plan to Core after it
has applied Adapter-owned schema checks to profiled `plan.write_actions[]`
inputs. Invalid profiled action input returns
`magick_ai_adapter_plan_action_input_invalid` with `blocked_items[]` and no
Core proposal creation. Exact `$outputs.<prior_action_id>.<field>` references
are accepted only when they point to an earlier action in the same plan, then
resolved and revalidated during approved batch execution. Embedded `$outputs.`
tokens and duplicate plan action ids fail closed before Core forwarding. Core
still owns plan intake, proposal creation, remaining blocked items, approval
state, and audit truth.

Future standalone approval or rejection proxying is out of this default
contract. It may only be added as a separate explicit trusted-host policy and
ADR-backed feature, disabled by default, with independent Core scopes for
approval and rejection.
