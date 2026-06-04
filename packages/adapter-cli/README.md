# Magick AI Adapter CLI

Local signed key-pair CLI for connecting OpenClaw-style clients to Magick AI
Adapter without sending a WordPress Application Password to chat, prompts,
tool commands, logs, proposal payloads, or copied handoff text.

## Usage

Connect from the user's local machine:

```bash
cd ~ && npm exec --yes --package @npcink/magick-ai-adapter-cli -- magick-adapter connect --site=https://example.com --profile=example
```

Check the local profile and signed Adapter health:

```bash
cd ~ && npm exec --yes --package @npcink/magick-ai-adapter-cli -- magick-adapter status --profile=example
```

Call Adapter through the signed local wrapper:

```bash
cd ~ && npm exec --yes --package @npcink/magick-ai-adapter-cli -- magick-adapter request --profile=example GET /health
cd ~ && npm exec --yes --package @npcink/magick-ai-adapter-cli -- magick-adapter request --profile=example GET /capabilities
```

For local WordPress development sites with self-signed `.local` HTTPS, add
`--insecure-local-tls`.

For repo-local package testing, use:

```bash
npx --yes --package /path/to/magick-ai-adapter/packages/adapter-cli magick-adapter status --profile=example
```

## Security Boundary

The CLI generates the private key locally and stores it under
`~/.magick-ai-adapter/keypair-profiles/`. Magick AI Adapter stores only the
approved public key and verifies signed REST requests. Do not ask an AI client
to read, print, summarize, or copy the profile JSON file.
