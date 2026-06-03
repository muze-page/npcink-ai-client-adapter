# OpenClaw Cloud Bulk Article Run Guidance

Status: active planning guidance.

This document explains how OpenClaw should benefit from Cloud bulk article
preparation without bypassing Adapter, Core governance, Toolbox review, or the
WordPress Abilities API write path.

## Position

OpenClaw may use Cloud-prepared article artifacts as inputs to a local governed
article draft flow. OpenClaw must not treat Cloud run status as approval,
preflight, publish permission, or WordPress write authorization.

The safe path is:

```text
Cloud bulk_article_run_v1
  -> Cloud Addon signed run/result read
  -> Toolbox selected item import
  -> magick-ai-toolbox/build-article-write-plan
  -> Adapter POST /proposals/from-plan
  -> Core proposal, approval, commit preflight, audit
  -> Adapter executes magick-ai/create-draft through WordPress Abilities API
```

## OpenClaw Responsibilities

OpenClaw should:

- read Adapter `/help` and route guidance first;
- treat Cloud bulk run items as review artifacts;
- ask the local operator to select one or a small bounded set of ready items;
- submit only a local `article_write_plan` to Adapter `/proposals/from-plan`;
- stop when Adapter/Core returns revision feedback, rejection, or preflight
  blockers;
- execute only the approved draft write profile exposed by Adapter.

OpenClaw must not:

- call Cloud directly as the productized WordPress connection;
- publish from a Cloud result;
- schedule WordPress posts from Cloud status;
- approve a proposal from Cloud item readiness;
- execute arbitrary write actions outside Adapter allowlists;
- store Cloud API keys or WordPress write credentials in the prompt context.

## Cloud Status Mapping

Allowed Cloud item statuses are only runtime/detail evidence:

- `ready_for_local_review`
- `partially_ready_for_local_review`
- `failed`
- `expired`

Adapter and OpenClaw must not map these values to Core proposal statuses such
as `pending`, `approved`, `rejected`, or any execution state.

## Local Handoff

The only accepted P0 article handoff remains
`magick-ai-toolbox/build-article-write-plan`.

The plan must remain:

- `artifact_type=article_write_plan`;
- `proposal_mode=single`;
- `requires_approval=true`;
- `dry_run=true`;
- `commit_execution=false`;
- draft-only `magick-ai/create-draft`;
- `publish_allowed=false`.

If a Cloud item is missing review evidence, has blocked claims, requests
publish status, or contains more than one final write action, OpenClaw should
ask the operator to revise the local plan instead of trying to execute it.

## Boundary

Cloud gives OpenClaw scale for preparation. Adapter and Core keep the local
write boundary. The benefit is faster artifact production, not hands-free
publishing.
