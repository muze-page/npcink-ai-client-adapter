# Adapter Workflow Toolbox Boundary Closeout

Date: 2026-07-01
Status: boundary review closeout

## Scope

This closeout records the adversarial boundary review and follow-up fixes for
`npcink-ai-client-adapter` after the suite workflow surface was confirmed as
`npcink-workflow-toolbox`.

The work stayed inside Adapter. No runtime code was changed in Core, Abilities
Toolkit, Workflow Toolbox, Cloud Addon, or Cloud.

## Starting Point

The review started from two provider-backed eval-lab checks over the Adapter
project:

```text
project_positioning_audit profiles=gpt55,grok43
project_boundary_review_triad profiles=gpt55,grok43
```

The useful findings were:

- Adapter docs still carried stale `npcink-toolbox` plugin/repo wording.
- Some docs implied old GET direct-read shortcut routes instead of the current
  `POST /run-read-ability` contract.
- The product name, REST namespace, and OpenClaw compatibility wording needed a
  cleaner split.
- Provider/model/prompt smoke wording could be read as Adapter owning model
  execution.
- The README execution profile list needed to match the implementation
  registry.
- Admin surface documentation needed a harder workflow/runtime/prompt boundary.

One important project fact was verified before editing:

- plugin/repository/admin slug: `npcink-workflow-toolbox`;
- current externally registered Toolbox ability ids: `npcink-toolbox/*`.

Adapter must keep those concepts separate. It may reference external ability ids
returned through Core capabilities, but it must not own the callbacks, workflow
runtime, prompt registry, queues, MCP catalogs, or recipe registry.

## Changes Landed

Three commits were created on
`codex/adapter-workflow-toolbox-boundary`:

```text
4bde200 docs: align adapter boundary with workflow toolbox
0f709cd docs: tighten adapter external ability boundary
91c49b0 docs: harden adapter boundary review notes
```

The changes:

- renamed the working product boundary from old OpenClaw-specific wording to
  `Npcink AI Client Adapter` while preserving REST namespace
  `npcink-openclaw-adapter/v1`;
- changed Adapter admin Toolbox links to `npcink-workflow-toolbox`;
- documented that `npcink-workflow-toolbox` owns workflow surfaces while
  `npcink-toolbox/*` ability ids remain external compatibility ids;
- replaced direct-read shortcut guidance with `POST /run-read-ability`;
- made article-writing and content-discoverability recipes use the full
  `POST /wp-json/npcink-openclaw-adapter/v1/run-read-ability` endpoint;
- clarified that Adapter forwards user-triggered approve-and-execute requests
  but does not make approval decisions or persist approval state;
- clarified that Core Audit and AI Request Logs remain separate truth sources;
- added admin-surface prohibitions for workflow recipe registry ownership,
  execution profile allowlist ownership, ability callback ownership, and
  AI Request Logs/Core audit merge UI;
- expanded static tests so stale shortcut-route docs and external ability
  ownership drift are caught by `composer test:all`.

## Verification

Adapter verification passed:

```text
composer test:all
composer eval:project:quality
git diff --check
```

`composer eval:project:quality` wrote the local quality-gate report and returned:

```text
Checks needing review: 0
```

The cross-repo matrix was run from the current central repository:

```text
cd /Users/muze/gitee/npcink-workflow-toolbox
composer quality:matrix:run
```

The first matrix run hit a transient Packagist timeout during
`npcink-abilities-toolkit` `composer audit`. A direct rerun of that repository's
gate passed, and a second full matrix run passed for:

- `npcink-abilities-toolkit`;
- `npcink-governance-core`;
- `npcink-ai-client-adapter`;
- `npcink-workflow-toolbox`;
- `npcink-cloud-addon`;
- `npcink-ai-cloud`;
- `magick-ai-toolbox`.

## Final Eval-Lab Review

After the first two commits, gpt-5.5/grok 4.3 review still found worthwhile
documentation hardening items. Those were addressed in `91c49b0`.

The final provider-backed review outputs were:

```text
project-review/generated/adapter-head-final-workflow-toolbox-gpt55-grok43.md
project-review/generated/adapter-head-boundary-final-workflow-toolbox-gpt55-grok43.md
```

Notes:

- the final positioning audit had a provider error for the gpt55 profile;
- grok43 and the final boundary triad no longer identified a runtime-code
  blocker in this change set;
- remaining useful items are longer-horizon release guard improvements, such as
  stronger packaged-file scans for workflow/MCP namespace leakage and clearer
  release verification around boundary docs.

## Git State

The boundary-fix branch was pushed with Git CLI before this closeout document
was added:

```text
codex/adapter-workflow-toolbox-boundary
```

Remote head before this documentation-only closeout commit:

```text
91c49b0b196a4231434b449b11ebb7ae23b705b2
```

`master` is protected, so direct push to `master` was rejected by GitHub with
the expected protected-branch rule. The work is therefore staged for PR review
on the Codex branch.

## Remaining Non-Blocking Follow-Up

These are not blockers for the workflow-toolbox boundary closeout, but they are
reasonable next-stage hardening items:

1. Add release guard scans for packaged docs/readme language that would imply
   Adapter-owned workflow runtime, MCP runtime, prompt ownership, or generic
   write execution.
2. Make execution profile documentation generation less duplicated between
   `README.md`, `readme.txt`, and implementation-derived tests.
3. Add a release checklist item that boundary docs remain present in the source
   tree even if they are excluded from the WordPress.org package.
4. Keep the central matrix path anchored on
   `/Users/muze/gitee/npcink-workflow-toolbox`.

## Closeout Conclusion

The Adapter boundary is now aligned with the current Workflow Toolbox slug
without changing the external `npcink-toolbox/*` ability id compatibility
contract. Adapter remains a thin AI client channel: it routes approved reads,
hands governed writes to Core, and executes only explicit post-Core execution
profiles after Core approval and commit-preflight.
