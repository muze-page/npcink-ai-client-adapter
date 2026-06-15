# Local AI Client Acceptance

Status: active acceptance guide.

This acceptance flow verifies that a local AI client enters WordPress through
Npcink AI Client Adapter and stays inside the Adapter/Core governance boundary.
The default command is intentionally non-destructive.

## Default Check

Run after connecting a local CLI profile:

```bash
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
MAA_ADAPTER_ACCEPTANCE_INSECURE_LOCAL_TLS=1 \
composer accept:local-ai-client
```

The default check runs:

- CLI syntax checks for `packages/adapter-cli`;
- signed `status`;
- `GET /health`;
- `GET /connection/manifest`;
- `GET /help`;
- public `read-ability` for `npcink-abilities-toolkit/site-info`.

## Sensitive Read Check

To create a Core-owned sensitive read request, pass an ability id and input
file:

```bash
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
MAA_ADAPTER_ACCEPTANCE_SENSITIVE_READ_ABILITY=npcink-abilities-toolkit/wp-ops-diagnostics-detail \
MAA_ADAPTER_ACCEPTANCE_SENSITIVE_READ_INPUT=/tmp/read-input.json \
composer accept:local-ai-client
```

After the operator approves the read request in Core, run the approved read:

```bash
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
MAA_ADAPTER_ACCEPTANCE_SENSITIVE_READ_ABILITY=npcink-abilities-toolkit/wp-ops-diagnostics-detail \
MAA_ADAPTER_ACCEPTANCE_SENSITIVE_READ_INPUT=/tmp/read-input.json \
MAA_ADAPTER_ACCEPTANCE_SENSITIVE_READ_REQUEST_ID=READ_REQUEST_ID \
composer accept:local-ai-client
```

The same `ability_id`, input file, and `read_request_id` must be used. If input
changes, create a new Core read request.

## Proposal Preflight Check

To verify a reviewed proposal without executing a write:

```bash
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
MAA_ADAPTER_ACCEPTANCE_PREFLIGHT_PROPOSAL_ID=PROPOSAL_ID \
composer accept:local-ai-client
```

This uses Adapter commit-preflight with `--intent=preflight`.

## Final Write Check

Final execution must be explicit:

```bash
MAA_ADAPTER_ACCEPTANCE_PROFILE=local \
MAA_ADAPTER_ACCEPTANCE_COMMIT_PROPOSAL_ID=PROPOSAL_ID \
MAA_ADAPTER_ACCEPTANCE_ALLOW_COMMIT=1 \
composer accept:local-ai-client
```

The proposal must already be appropriate for final write execution. Adapter
will still require Core approval, Core commit-preflight, and an explicit
execution profile. The script does not create a proposal on its own.

## Expected Boundary

Acceptance is successful when:

- `client_policy` is present in `/health`, `/help`, and
  `/connection/manifest`;
- the `contract` object exposes version and hash metadata;
- public reads run through Adapter;
- sensitive reads fail closed unless Core grants the exact input hash;
- final writes require explicit commit intent and a profiled ability;
- unsupported abilities or unapproved proposals fail closed.
