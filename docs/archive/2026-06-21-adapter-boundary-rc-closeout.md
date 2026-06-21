# Adapter Boundary RC Closeout

Date: 2026-06-21
Status: boundary release-candidate closeout

## Final Position

`npcink-ai-client-adapter` is the thin AI client channel layer for
OpenClaw-compatible clients.

Adapter owns:

- OpenClaw-facing REST connection routes;
- direct-read ability routing to WordPress Abilities API;
- governed write handoff to Npcink Governance Core proposals and
  commit-preflight;
- explicit post-Core execution profile policy for approved writes;
- small health, help, manifest, capability, and diagnostics projections.

Adapter does not own:

- WordPress ability definitions or callbacks;
- Core proposal storage, approval, commit-preflight truth, or audit truth;
- provider credentials, model routing, prompts, prompt execution, token
  accounting, or product UX;
- Cloud connector settings, signing, `/cloud/*` routes, run/result lookup,
  artifact preview, artifact registry, or derivative payload builders;
- workflow runtime, queues, schedulers, MCP runtime, or Agent Gateway catalogs;
- generic final write execution.

## Boundary Corrections Completed

Removed or reclassified the old media derivative Adapter surface:

- removed CLI-owned `crop` and `result` actions from the local Adapter CLI;
- removed current-doc guidance that called Adapter
  `/media-derivative-runs`, artifact preview, or proposal-payload routes;
- documented Cloud derivative transport as owned by Cloud Addon or Cloud
  tooling;
- kept Adapter limited to proposal-specific readiness and post-Core
  `adopt-cloud-media-derivative` execution.

Removed provider/model smoke ownership from Adapter:

- replaced provider smoke route examples with downstream provider integration
  correlation guidance;
- documented that Adapter only injects bounded correlation context into AI
  Request Logs;
- kept provider credentials, model routing, prompts, and real provider calls
  outside Adapter.

Clarified direct-read and plan bridge boundaries:

- current direct reads use `POST /run-read-ability`;
- current docs no longer describe positive direct-read shortcut routes;
- `Supported_Plan_Abilities` wording is now a bridge allowlist, not an ability
  registry.

## Execution Profile Policy

Execution profiles remain in Adapter. They should not be migrated now.

Reasoning:

- Toolkit owns ability definitions, schemas, callbacks, permissions, and
  dry-run previews.
- Core owns proposal storage, approval, commit-preflight, and audit truth while
  preserving `core_proxy_execute=false` and `commit_execution=false`.
- A separate execution-policy plugin is not justified unless multiple channel
  adapters must share one post-Core execution policy and a future ADR accepts
  that dependency.

Hard limits now documented and tested:

- no dynamic execution profile extension through filters, actions, options,
  database rows, remote configuration, wildcards, category matches, or ability
  patterns;
- every profile must use a literal `npcink-abilities-toolkit/<ability>` id;
- every profile must start with `supported_input_fields`;
- every new profile requires code, contract docs, static tests, and WordPress
  smoke coverage;
- every write still requires Core approval, Core commit-preflight, explicit
  commit intent, and Adapter profile validation.

## External Client Contract

Added `docs/external-ai-client-contract.md` for OpenClaw-compatible clients
such as OpenClaw, WorkBuddy, Qclaw, and other local AI clients.

The client contract states:

- clients connect only to Adapter;
- clients must read health, help, manifest, and capabilities before choosing
  routes;
- fingerprints are drift detection, not write authorization;
- reads go through `POST /run-read-ability`;
- governed writes go through Core proposal, Core approval, Core
  commit-preflight, Adapter execution profile, then WordPress Abilities API;
- Cloud runtime transport is outside Adapter;
- provider/model runtime is outside Adapter;
- clients should surface `data.operator_feedback` and create revised proposals
  instead of bypassing governance.

## Release And Smoke Verification

Release gate passed:

```text
composer test:all
composer plugin-check:release
composer package:release
composer release:verify
```

`composer release:verify` result:

```text
Static contracts: ok
WordPress.org review guard: ok
Plugin Check: Success, no errors found
```

Release zip inspection confirmed the package includes only plugin runtime,
languages, assets, and `readme.txt`. It does not include `tests/`, `docs/`,
`packages/`, `scripts/`, development metadata, provider smoke routes, media
derivative facade routes, or `/cloud/*` routes.

Local WordPress smoke passed:

```text
composer smoke:wp
```

The first smoke attempt failed because local dependencies were inactive. After
activating `npcink-abilities-toolkit`, `npcink-governance-core`, and
`npcink-toolbox`, the smoke passed. That was a local environment dependency
state issue, not an Adapter runtime-boundary failure.

## Current Guardrails

Important guardrail files:

- `docs/contracts/execution-profiles.md`
- `docs/external-ai-client-contract.md`
- `docs/cloud-connector-boundary.md`
- `docs/openclaw-adapter-contract.md`
- `docs/openclaw-consumer-acceptance.md`
- `tests/run.php`

Future Adapter work should follow this order:

1. Keep reads behind `POST /run-read-ability`.
2. Keep writes behind Core proposal, approval, and commit-preflight.
3. Add final write support only through an explicit execution profile.
4. Do not add provider/model runtime, Cloud connector routes, workflow runtime,
   or generic final write routes to Adapter.
5. Run `composer test:all`, `composer release:verify`, and relevant WordPress
   smoke before treating boundary-sensitive changes as complete.

## Closeout Conclusion

The project boundary is now clear enough for release-candidate handling and
future feature work. The remaining long-term risk is execution profile growth,
which is now constrained by explicit placement rules, admission rules, static
tests, hash exposure, and smoke requirements.
