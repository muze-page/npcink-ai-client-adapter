# Npcink OpenClaw Adapter CLI

Local signed key-pair CLI for connecting OpenClaw-style clients to Npcink
Adapter without sending a WordPress Application Password to chat, prompts,
tool commands, logs, proposal payloads, or copied handoff text.

## Usage

Connect from the user's local machine:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter connect --site=https://example.com --profile=example
```

Check the local profile and signed Adapter health:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter status --profile=example
```

Call Adapter through the signed local wrapper:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=example GET /health
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=example GET /capabilities
```

Final Adapter write routes are guarded by client intent. Use
`--intent=preflight` for `POST /proposals/{proposal_id}/commit-preflight`.
Use `--intent=commit` only when the operator explicitly confirmed final write
execution:

```bash
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=example POST /proposals/PROPOSAL_ID/commit-preflight --intent=preflight
cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter request --profile=example POST /proposals/PROPOSAL_ID/approve-and-execute --intent=commit
```

The CLI refuses final execute routes when `--intent=commit` is missing, or when
the request body still contains preview markers such as `dry_run=true`,
`commit=false`, or `commit_execution=false`.

For local WordPress development sites with self-signed `.local` HTTPS, add
`--insecure-local-tls`.

## Security Boundary

The CLI generates the private key locally and stores it under
`~/.npcink-openclaw-adapter/keypair-profiles/`. Npcink OpenClaw Adapter stores only the
approved public key and verifies signed REST requests. Do not ask an AI client
to read, print, summarize, or copy the profile JSON file.
