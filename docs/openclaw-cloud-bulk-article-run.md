# OpenClaw Cloud Bulk Article Run Guidance

Status: prohibited and deprecated planning guidance.

This document records that OpenClaw must not use Cloud bulk article generation
or Cloud article artifacts for the productized WordPress writing path.

## Decision

OpenClaw should not call, request, or depend on:

- `bulk_article_run_v1`;
- Cloud-generated article titles, outlines, paragraphs, bodies, or SEO copy;
- Cloud-produced `article_write_plan` candidates;
- Cloud article artifact imports;
- Cloud item readiness as approval or preflight;
- Cloud publishing or scheduling.

## Replacement

Article drafting is a local Ability recipe. The safe path is:

```text
OpenClaw follows Adapter recipe guidance
  -> local Abilities produce/operator review artifacts
  -> magick-ai-toolbox/build-article-write-plan
     or magick-ai-toolbox/build-article-batch-write-plan
  -> Adapter POST /proposals/from-plan
  -> Core proposal, approval, commit preflight, audit
  -> Adapter executes magick-ai/create-draft through WordPress Abilities API
```

## OpenClaw Responsibilities

OpenClaw should:

- read Adapter `/help` and route guidance first;
- treat `article_draft_v1` as an Ability recipe over local Abilities;
- submit only local `article_write_plan` or `article_batch_write_plan` output
  to Adapter `/proposals/from-plan`;
- stop when Adapter/Core returns revision feedback, rejection, or preflight
  blockers;
- execute only the approved draft write profile exposed by Adapter.

OpenClaw must not:

- call Cloud directly as a writing provider;
- publish from Cloud output;
- schedule WordPress posts from Cloud status;
- approve a proposal from Cloud readiness;
- execute arbitrary write actions outside Adapter allowlists;
- store Cloud API keys or WordPress write credentials in prompt context.

## Boundary

Cloud may improve connection, entitlement, health, diagnostics, and other
non-writing service surfaces. It does not give OpenClaw a writing engine. The
writing benefit comes from clear local Ability orchestration, not hosted bulk
article generation.
