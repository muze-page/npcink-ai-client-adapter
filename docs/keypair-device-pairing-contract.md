# Key Pair Device Pairing Contract

This document defines the Phase 2 MVP for connecting OpenClaw-style local
clients to Magick AI Adapter without transferring a WordPress Application
Password.

## Boundary

Adapter owns only:

- device pairing REST routes;
- WordPress admin approval for a pending public key;
- registered public key metadata;
- Ed25519 request-signature verification for Adapter routes.

Adapter does not own:

- private keys;
- WordPress Core REST authentication;
- workflow runtime, queues, schedulers, or MCP runtime;
- Core proposal storage, approval, preflight truth, or audit truth;
- final WordPress write authority.

## Pairing Flow

1. Local client generates an Ed25519 key pair.
2. Local client calls `POST /connect/device/start` with only public metadata.
3. Adapter stores a pending pairing and returns a user code plus verification
   URL.
4. User opens the WordPress admin verification URL and approves or rejects.
5. Local client polls `POST /connect/device/poll` with the device code.
6. Adapter returns connection metadata and a `key_id`; no secret is returned.
7. Local client signs subsequent Adapter REST requests with its private key.

## REST Routes

### `POST /wp-json/magick-ai-adapter/v1/connect/device/start`

Public route. Starts a pending pairing.

Request:

```json
{
  "schema_version": "magick_ai_device_pairing.v1",
  "client": {
    "name": "OpenClaw",
    "device_name": "Muze Mac",
    "broker": "@magick-ai/adapter-broker",
    "broker_version": "0.1.0"
  },
  "key": {
    "alg": "Ed25519",
    "public_key": "BASE64URL_RAW_32_BYTE_PUBLIC_KEY",
    "fingerprint": "sha256:..."
  },
  "requested_scopes": ["magick.read", "magick.propose", "magick.status"]
}
```

Response:

```json
{
  "device_code": "dev_...",
  "user_code": "ABCD-1234",
  "verification_uri": "https://example.test/wp-admin/admin.php?page=magick-ai-adapter-pair",
  "verification_uri_complete": "https://example.test/wp-admin/admin.php?page=magick-ai-adapter-pair&user_code=ABCD-1234",
  "expires_in": 600,
  "interval": 3
}
```

Adapter stores only a hash of `device_code`.

### `POST /wp-json/magick-ai-adapter/v1/connect/device/poll`

Public route. Polls a pending pairing.

Request:

```json
{
  "device_code": "dev_..."
}
```

Pending response: HTTP `202`.

Approved response:

```json
{
  "ok": true,
  "connection_id": "mag_conn_...",
  "key_id": "mk_...",
  "site_url": "https://example.test",
  "adapter_base_url": "https://example.test/wp-json/magick-ai-adapter/v1",
  "scopes_effective": ["magick.read", "magick.propose", "magick.status"]
}
```

Rejected or expired pairings do not return private material.

## Admin Approval

The WordPress admin route is:

```text
wp-admin/admin.php?page=magick-ai-adapter-pair&user_code=ABCD-1234
```

The page requires an authenticated WordPress user with `manage_options`. It
shows:

- client name;
- device name;
- broker name/version;
- key fingerprint;
- requested scopes.

The user can approve or reject. On approval, Adapter stores:

- `key_id`;
- `connection_id`;
- `user_id`;
- client metadata;
- Ed25519 public key;
- fingerprint;
- scopes;
- created/last-used/revoked timestamps.

## Request Signing

Signed Adapter calls use these headers:

```http
X-Magick-Key-Id: mk_...
X-Magick-Timestamp: 2026-06-01T12:00:00Z
X-Magick-Nonce: BASE64URL_RANDOM
X-Magick-Content-SHA256: sha256:...
X-Magick-Signature-Alg: Ed25519
X-Magick-Signature: BASE64URL_SIGNATURE
```

Canonical request:

```text
MAGICK-AI-ADAPTER-V1
METHOD
ROUTE
CANONICAL_QUERY_JSON
TIMESTAMP
NONCE
CONTENT_SHA256
```

Adapter verifies:

- `key_id` exists and is not revoked;
- mapped user still has `manage_options`;
- timestamp is within 300 seconds;
- nonce has not been used;
- request body SHA-256 matches;
- Ed25519 signature is valid;
- scopes allow the route.

## Scopes

- `magick.status`: health, help, capabilities, connection metadata.
- `magick.read`: direct-read ability routes.
- `magick.propose`: proposal and approved-proposal execution routes. Core
  remains the proposal, approval, preflight, and audit truth.
