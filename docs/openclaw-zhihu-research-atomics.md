# OpenClaw Zhihu Research Atomics

Status: active contract guidance.
Date: 2026-06-22

This document defines the four OpenClaw-callable research atoms for Zhihu and
trusted-search assisted article preparation. They are atom aliases over the
canonical Toolbox Cloud web search bridge. They are not Adapter-owned ability
definitions, Cloud routes, provider clients, prompt runtimes, or workflow
registries.

OpenClaw must call these atoms through Adapter direct-read execution:

```text
POST /wp-json/npcink-openclaw-adapter/v1/run-read-ability
```

The canonical ability id is:

```text
npcink-toolbox/cloud-web-search
```

Adapter acceptance fixtures live under:

```text
tests/fixtures/openclaw-zhihu-atomics/
```

When a local profile, WordPress site, Toolbox, Cloud Addon, and Cloud Zhihu
runtime are configured, run:

```text
composer accept:openclaw-zhihu-atomics
```

Adapter executes it only when Core capabilities expose the ability as
`governance_mode=direct_read` and `execution_surface=wp_abilities_rest`.
Toolbox and the Cloud Addon own provider dispatch, Cloud credentials, usage
metering, cache policy, and result normalization.

## Atom Aliases

Managed-source mapping summary:

- `openclaw_atoms.zhihu_hot_topics` -> `managed_source=zhihu_hot_topics`
- `openclaw_atoms.zhihu_search` -> `managed_source=zhihu_research`
- `openclaw_atoms.global_search` -> `managed_source=zhihu_global_search`
- `openclaw_atoms.zhida_answer` -> `managed_source=zhida_simple`,
  `managed_source=zhida_deep`, or `managed_source=zhida_deepsearch`

### `openclaw_atoms.zhihu_hot_topics`

Use this atom to get topic candidates from Zhihu hot-list data before an article
angle is chosen.

```json
{
  "ability_id": "npcink-toolbox/cloud-web-search",
  "input": {
    "intent": "zhihu_hot_topics",
    "managed_source": "zhihu_hot_topics",
    "query": "知乎热榜",
    "max_results": 10
  }
}
```

Expected normalized output includes `topic_candidate.v1` items with source
labels, rank or heat metadata when available, and bounded evidence links.

### `openclaw_atoms.zhihu_search`

Use this atom for community questions, opposing views, audience wording, and
human reasoning from Zhihu.

```json
{
  "ability_id": "npcink-toolbox/cloud-web-search",
  "input": {
    "intent": "zhihu_research",
    "managed_source": "zhihu_research",
    "query": "文章选题或用户问题",
    "max_results": 5
  }
}
```

Expected normalized output includes `source_evidence.v1` items and may include
`topic_candidate.v1` items when the source data has clear angle signals.

### `openclaw_atoms.global_search`

Use this atom for high-trust web evidence outside Zhihu, especially facts,
recent developments, authority references, and cross-checking.

```json
{
  "ability_id": "npcink-toolbox/cloud-web-search",
  "input": {
    "intent": "zhihu_global_search",
    "managed_source": "zhihu_global_search",
    "query": "需要核查的事实或选题",
    "max_results": 5,
    "recency_days": 30
  }
}
```

Expected normalized output includes `source_evidence.v1` items with titles,
source labels, URLs, snippets, and freshness metadata when available.

### `openclaw_atoms.zhida_answer`

Use this atom when OpenClaw needs a grounded answer artifact instead of raw
search results.

Mode mapping:

- `simple` -> `managed_source=zhida_simple`, `intent=zhida_simple`
- `deep` -> `managed_source=zhida_deep`, `intent=zhida_deep`
- `deepsearch` -> `managed_source=zhida_deepsearch`, `intent=zhida_deepsearch`

Example:

```json
{
  "ability_id": "npcink-toolbox/cloud-web-search",
  "input": {
    "intent": "zhida_deep",
    "managed_source": "zhida_deep",
    "query": "需要解释或研究的问题",
    "max_results": 5
  }
}
```

Expected normalized output includes `grounded_answer.v1` with cited evidence or
source references when the upstream response provides them.

## Composed Research Pack

OpenClaw may compose the atoms locally as `openclaw.article_research_pack`.
This is client-side composition guidance, not an Adapter-owned recipe catalog.

Recommended sequence:

1. Call `openclaw_atoms.zhihu_hot_topics` when the user needs topic discovery.
2. Call `openclaw_atoms.zhihu_search` for audience language, objections, and
   community reasoning.
3. Call `openclaw_atoms.global_search` for authority evidence and fact checks.
4. Call `openclaw_atoms.zhida_answer` when OpenClaw needs a synthesized
   research answer after source collection.

The composed output should be `article_research_pack.v1` and preserve:

```text
write_posture=suggestion_only
direct_wordpress_write=false
final_write_path=core_proposal_required
```

This pack solves pre-writing research: topic selection, audience question
discovery, evidence collection, contradiction discovery, and citation-ready
source organization. It does not generate final article text or publish
anything.

## Boundary Rules

Adapter must not register /cloud/* routes for these atoms.
Adapter must not store Zhihu credentials, Cloud API keys, provider routing
state, prompt templates, usage counters, cache truth, or generated article
truth.
Cloud Addon owns Cloud credentials and Cloud runtime transport.
Toolbox owns the ability callback and managed-source mapping.
Core owns capability truth, read authorization where needed, proposal truth,
approval, commit-preflight, and audit.

OpenClaw must not copy source text, rewrite source material as if original, or
publish research output directly. Any WordPress write must become a Core
proposal and pass approval plus commit-preflight before Adapter can execute an
explicit final-write profile.
