#!/usr/bin/env node
import { createHash, generateKeyPairSync, privateDecrypt, randomBytes, constants } from 'node:crypto';
import { createServer } from 'node:http';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { mkdirSync, writeFileSync, chmodSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { homedir } from 'node:os';
import { URL } from 'node:url';
import readline from 'node:readline/promises';
import process from 'node:process';

const args = new Map();
for (const arg of process.argv.slice(2)) {
  const match = arg.match(/^--([^=]+)=(.*)$/);
  if (match) {
    args.set(match[1], match[2]);
  } else if (arg.startsWith('--')) {
    args.set(arg.slice(2), '1');
  }
}

const host = args.get('host') || '127.0.0.1';
const port = Number(args.get('port') || process.env.WORKBUDDY_BROKER_PORT || 9981);
const brokerBaseUrl = `http://${host}:${port}`;
const allowYes = args.has('yes') || process.env.WORKBUDDY_BROKER_YES === '1';
const insecureLocalTls = args.has('insecure-local-tls') || process.env.WORKBUDDY_BROKER_INSECURE_LOCAL_TLS === '1';
const outputPath = args.get('output') || join(homedir(), '.magick-ai-adapter', 'workbuddy-local-credential.json');
const explicitAllowedOrigins = new Set(
  (args.get('allow-origin') || process.env.WORKBUDDY_BROKER_ALLOW_ORIGIN || '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean)
);
let learnedOrigin = '';
let session = createSession();

function base64url(buffer) {
  return Buffer.from(buffer).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function fromBase64url(value) {
  const normalized = String(value).replace(/-/g, '+').replace(/_/g, '/');
  return Buffer.from(normalized + '='.repeat((4 - (normalized.length % 4)) % 4), 'base64');
}

function createSession() {
  const { publicKey, privateKey } = generateKeyPairSync('rsa', {
    modulusLength: 2048,
    publicExponent: 0x10001,
  });
  const publicJwk = publicKey.export({ format: 'jwk' });
  const codeVerifier = base64url(randomBytes(32));
  const codeChallenge = base64url(createHash('sha256').update(codeVerifier).digest());
  const state = base64url(randomBytes(24));
  const thumbprintPayload = {
    e: publicJwk.e,
    kty: publicJwk.kty,
    n: publicJwk.n,
  };

  return {
    privateKey,
    publicJwk,
    codeVerifier,
    codeChallenge,
    state,
    thumbprint: `sha256:${createHash('sha256').update(JSON.stringify(thumbprintPayload)).digest('hex')}`,
    createdAt: Date.now(),
  };
}

function json(res, status, payload, origin = '') {
  const body = JSON.stringify(payload);
  const headers = {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
    'Cache-Control': 'no-store',
  };
  if (origin) {
    headers['Access-Control-Allow-Origin'] = origin;
    headers.Vary = 'Origin';
  }
  res.writeHead(status, headers);
  res.end(body);
}

function text(res, status, body, contentType, origin = '') {
  const headers = {
    'Content-Type': contentType,
    'Content-Length': Buffer.byteLength(body),
    'Cache-Control': 'no-store',
  };
  if (origin) {
    headers['Access-Control-Allow-Origin'] = origin;
    headers.Vary = 'Origin';
  }
  res.writeHead(status, headers);
  res.end(body);
}

function allowedOrigin(req) {
  const origin = req.headers.origin || '';
  if (!origin) {
    return '';
  }
  if (explicitAllowedOrigins.has(origin)) {
    return origin;
  }
  if (explicitAllowedOrigins.size === 0 && /^https?:\/\//.test(origin)) {
    if (!learnedOrigin) {
      learnedOrigin = origin;
      console.log(`Locked browser origin to ${origin}`);
    }
    return learnedOrigin === origin ? origin : '';
  }
  return '';
}

function readJsonBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.setEncoding('utf8');
    req.on('data', (chunk) => {
      body += chunk;
      if (body.length > 1024 * 1024) {
        reject(new Error('Request body too large'));
        req.destroy();
      }
    });
    req.on('end', () => {
      try {
        resolve(JSON.parse(body || '{}'));
      } catch (error) {
        reject(error);
      }
    });
    req.on('error', reject);
  });
}

function isLocalTlsHost(hostname) {
  return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1' || hostname.endsWith('.local');
}

function postJson(url, payload) {
  return new Promise((resolve, reject) => {
    const target = new URL(url);
    const body = JSON.stringify(payload);
    const transport = target.protocol === 'https:' ? httpsRequest : httpRequest;
    const requestOptions = {
      method: 'POST',
      hostname: target.hostname,
      port: target.port || (target.protocol === 'https:' ? 443 : 80),
      path: `${target.pathname}${target.search}`,
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body),
        Accept: 'application/json',
      },
    };
    if (target.protocol === 'https:' && insecureLocalTls && isLocalTlsHost(target.hostname)) {
      requestOptions.rejectUnauthorized = false;
    }
    const req = transport(
      requestOptions,
      (res) => {
        let data = '';
        res.setEncoding('utf8');
        res.on('data', (chunk) => {
          data += chunk;
        });
        res.on('end', () => {
          let parsed = {};
          try {
            parsed = data ? JSON.parse(data) : {};
          } catch (error) {
            reject(error);
            return;
          }
          if (res.statusCode < 200 || res.statusCode >= 300) {
            const message = parsed.message || `HTTP ${res.statusCode}`;
            reject(new Error(message));
            return;
          }
          resolve(parsed);
        });
      }
    );
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

async function confirmRedeem(manifest, grant) {
  const siteUrl = manifest?.site?.site_url || manifest?.site_url || 'unknown site';
  const username = manifest?.user?.username || grant?.username || 'unknown user';
  if (allowYes) {
    return true;
  }
  if (!process.stdin.isTTY) {
    return false;
  }

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const answer = await rl.question(`Allow WordPress credential import from ${siteUrl} for ${username}? Type yes: `);
  rl.close();
  return answer.trim().toLowerCase() === 'yes';
}

function decryptSecret(encryptedSecret) {
  if (encryptedSecret?.format !== 'rsa_oaep_base64url' || encryptedSecret?.alg !== 'RSA-OAEP') {
    throw new Error('Unsupported encrypted secret format');
  }
  return privateDecrypt(
    {
      key: session.privateKey,
      padding: constants.RSA_PKCS1_OAEP_PADDING,
      oaepHash: 'sha1',
    },
    fromBase64url(encryptedSecret.ciphertext)
  ).toString('utf8');
}

function saveCredential(manifest, redeem, password) {
  const credential = {
    created_at: new Date().toISOString(),
    connection_id: redeem.connection_id,
    credential_type: redeem.credential_type,
    username: redeem.username,
    credential_id: redeem.credential_id,
    adapter_base_url: redeem.adapter_base_url,
    health_url: manifest?.urls?.health || '',
    help_url: manifest?.urls?.help || '',
    capabilities_url: manifest?.urls?.capabilities || '',
    scopes_effective: redeem.scopes_effective || [],
  };
  credential.application_password = password;

  mkdirSync(dirname(outputPath), { recursive: true, mode: 0o700 });
  writeFileSync(outputPath, `${JSON.stringify(credential, null, 2)}\n`, { mode: 0o600 });
  chmodSync(outputPath, 0o600);
}

function adminHelperJs() {
  return `
(async function () {
  const cfg = window.magickAiAdapterLocalBroker || {};
  const statusEl = cfg.statusElementId ? document.getElementById(cfg.statusElementId) : null;
  const setStatus = (message) => {
    if (statusEl) statusEl.textContent = message;
    console.log('[Magick AI Adapter broker]', message);
  };
  const mustJson = async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || ('HTTP ' + response.status));
    return data;
  };

  try {
    if (!cfg.brokerBaseUrl || !cfg.manifestUrl || !cfg.grantUrl || !cfg.restNonce) {
      throw new Error('Missing Adapter broker config on this admin page.');
    }

    setStatus('Contacting local broker...');
    const brokerRequest = await fetch(cfg.brokerBaseUrl + '/request', {
      method: 'GET',
      mode: 'cors',
      credentials: 'omit',
      cache: 'no-store'
    }).then(mustJson);

    setStatus('Reading non-secret manifest...');
    const manifest = await fetch(cfg.manifestUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': cfg.restNonce, 'Accept': 'application/json' },
      cache: 'no-store'
    }).then(mustJson);

    setStatus('Creating short-lived grant...');
    const grant = await fetch(cfg.grantUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.restNonce,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        manifest_sha256: manifest.integrity && manifest.integrity.manifest_sha256,
        state: brokerRequest.state,
        broker: brokerRequest.broker,
        requested_scopes: brokerRequest.requested_scopes
      })
    }).then(mustJson);

    setStatus('Sending grant to local broker...');
    const result = await fetch(cfg.brokerBaseUrl + '/grant', {
      method: 'POST',
      mode: 'cors',
      credentials: 'omit',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ manifest, grant })
    }).then(mustJson);

    setStatus('Connected. Credential saved by local broker: ' + result.connection_id);
  } catch (error) {
    setStatus('Local broker connection failed: ' + error.message);
  }
}());
`;
}

async function handleGrant(req, res, origin) {
  const body = await readJsonBody(req);
  const manifest = body.manifest || {};
  const grant = body.grant || {};
  const manifestDigest = manifest?.integrity?.manifest_sha256 || '';
  if (!grant.grant_code || grant.state !== session.state || grant.manifest_sha256 !== manifestDigest) {
    json(res, 400, { error: 'Grant does not match this broker session.' }, origin);
    return;
  }
  if (!manifest?.urls?.redeem) {
    json(res, 400, { error: 'Manifest is missing redeem URL.' }, origin);
    return;
  }
  if (!(await confirmRedeem(manifest, grant))) {
    json(res, 403, { error: 'User denied credential import.' }, origin);
    return;
  }

  const redeem = await postJson(manifest.urls.redeem, {
    grant_code: grant.grant_code,
    code_verifier: session.codeVerifier,
    manifest_sha256: manifestDigest,
    broker_public_key_thumbprint: session.thumbprint,
  });
  const password = decryptSecret(redeem.encrypted_secret);
  saveCredential(manifest, redeem, password);
  console.log(`Credential saved to ${outputPath}`);
  const publicResult = {
    ok: true,
    connection_id: redeem.connection_id,
    credential_type: redeem.credential_type,
    username: redeem.username,
    credential_id: redeem.credential_id,
    adapter_base_url: redeem.adapter_base_url,
    output_path: outputPath,
  };
  session = createSession();
  json(res, 200, publicResult, origin);
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, brokerBaseUrl);
  const origin = allowedOrigin(req);

  if (req.method === 'OPTIONS') {
    if (!origin) {
      res.writeHead(403);
      res.end();
      return;
    }
    res.writeHead(204, {
      'Access-Control-Allow-Origin': origin,
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Accept',
      'Access-Control-Max-Age': '60',
      Vary: 'Origin',
    });
    res.end();
    return;
  }

  try {
    if (req.method === 'GET' && url.pathname === '/health') {
      json(res, 200, { ok: true, broker: 'workbuddy-local-broker', origin: learnedOrigin || null }, origin);
      return;
    }

    if (req.method === 'GET' && url.pathname === '/admin-helper.js') {
      text(res, 200, adminHelperJs(), 'application/javascript; charset=utf-8', origin);
      return;
    }

    if (req.method === 'GET' && url.pathname === '/request') {
      json(
        res,
        200,
        {
          state: session.state,
          broker: {
            public_key_jwk: session.publicJwk,
            code_challenge: session.codeChallenge,
            code_challenge_method: 'S256',
          },
          broker_public_key_thumbprint: session.thumbprint,
          requested_scopes: ['magick.read', 'magick.propose', 'magick.status'],
        },
        origin
      );
      return;
    }

    if (req.method === 'POST' && url.pathname === '/grant') {
      if (!origin) {
        json(res, 403, { error: 'Origin is not allowed.' });
        return;
      }
      await handleGrant(req, res, origin);
      return;
    }

    json(res, 404, { error: 'Not found' }, origin);
  } catch (error) {
    json(res, 500, { error: error.message || 'Broker failed' }, origin);
  }
});

server.listen(port, host, () => {
  console.log(`Local credential broker listening on ${brokerBaseUrl}`);
  console.log(`Credential output: ${outputPath}`);
  console.log('Run this only on a trusted local machine. The output file contains the WordPress Application Password.');
  if (insecureLocalTls) {
    console.log('Local HTTPS certificate verification is disabled only for localhost, loopback, and .local WordPress URLs.');
  }
  if (!allowYes) {
    console.log('Redeem requires terminal confirmation. Add --yes only for disposable local testing.');
  }
});
