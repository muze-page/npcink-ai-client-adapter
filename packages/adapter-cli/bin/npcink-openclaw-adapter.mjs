#!/usr/bin/env node
import { spawn } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { homedir } from 'node:os';

const toolDir = dirname(fileURLToPath(import.meta.url));
const rawArgs = process.argv.slice(2);
const command = rawArgs[0] || '';
const commandArgs = rawArgs.slice(1);

if (!['connect', 'status', 'request'].includes(command)) {
  printUsage();
  process.exit(2);
}

function printUsage() {
  console.error([
    'Usage:',
    '  npcink-openclaw-adapter connect --site=https://example.test --profile=local [--insecure-local-tls]',
    '  npcink-openclaw-adapter status --profile=local [--insecure-local-tls]',
    '  npcink-openclaw-adapter request --profile=local [--insecure-local-tls] METHOD /adapter-route [--body-file=/tmp/body.json|--body-stdin]',
  ].join('\n'));
}

function parseArgs(args) {
  const parsed = new Map();
  const positionals = [];
  for (const arg of args) {
    const match = arg.match(/^--([^=]+)=(.*)$/);
    if (match) {
      parsed.set(match[1], match[2]);
    } else if (arg.startsWith('--')) {
      parsed.set(arg.slice(2), '1');
    } else {
      positionals.push(arg);
    }
  }
  return { parsed, positionals };
}

function profilePathFromArgs(args) {
  const { parsed } = parseArgs(args);
  const profile = parsed.get('profile') || 'default';
  return {
    profile,
    profilePath: parsed.get('profile-file') || join(homedir(), '.npcink-openclaw-adapter', 'keypair-profiles', `${profile}.json`),
    insecureLocalTls: parsed.has('insecure-local-tls'),
  };
}

function runNode(scriptName, args, options = {}) {
  return new Promise((resolve, reject) => {
    const stdio = options.capture ? ['inherit', 'pipe', 'pipe'] : 'inherit';
    const child = spawn(process.execPath, [join(toolDir, scriptName), ...args], { stdio });
    let stdout = '';
    let stderr = '';
    if (options.capture) {
      child.stdout.on('data', (chunk) => {
        stdout += chunk;
      });
      child.stderr.on('data', (chunk) => {
        stderr += chunk;
      });
    }
    child.on('error', reject);
    child.on('close', (code) => {
      resolve({ code, stdout, stderr });
    });
  });
}

async function connect(args) {
  const result = await runNode('keypair-device-pairing.mjs', args);
  process.exitCode = result.code || 0;
}

async function request(args) {
  const result = await runNode('keypair-adapter-request.mjs', args);
  process.exitCode = result.code || 0;
}

async function status(args) {
  const { profile, profilePath, insecureLocalTls } = profilePathFromArgs(args);
  if (!existsSync(profilePath)) {
    console.log(JSON.stringify({
      ok: false,
      status: 'missing_profile',
      profile,
      profile_path: profilePath,
      message: 'Run connect before status.',
    }, null, 2));
    process.exitCode = 1;
    return;
  }

  let metadata = {};
  try {
    const profileData = JSON.parse(readFileSync(profilePath, 'utf8'));
    metadata = {
      adapter_base_url: String(profileData.adapter_base_url || ''),
      connection_id: String(profileData.connection_id || ''),
      key_id: String(profileData.key_id || ''),
      created_at: String(profileData.created_at || ''),
      scopes_effective: Array.isArray(profileData.scopes_effective) ? profileData.scopes_effective : [],
    };
  } catch (error) {
    console.log(JSON.stringify({
      ok: false,
      status: 'invalid_profile',
      profile,
      profile_path: profilePath,
      message: error.message,
    }, null, 2));
    process.exitCode = 1;
    return;
  }

  const requestArgs = [`--profile=${profile}`];
  if (args.some((arg) => arg.startsWith('--profile-file='))) {
    requestArgs.push(`--profile-file=${profilePath}`);
  }
  if (insecureLocalTls) {
    requestArgs.push('--insecure-local-tls');
  }
  requestArgs.push('GET', '/health');

  const result = await runNode('keypair-adapter-request.mjs', requestArgs, { capture: true });
  if (result.code !== 0) {
    console.log(JSON.stringify({
      ok: false,
      status: 'health_failed',
      profile,
      profile_path: profilePath,
      connection: metadata,
      message: safeErrorMessage(result.stdout, result.stderr),
    }, null, 2));
    process.exitCode = result.code || 1;
    return;
  }

  const health = JSON.parse(result.stdout);
  const approvalProxyEnabled = Boolean(health.approval_proxy_enabled);
  const coreProxyExecute = Boolean(health.core_proxy_execute);
  const commitExecution = Boolean(health.commit_execution);
  const boundaryOk = !approvalProxyEnabled && !coreProxyExecute && !commitExecution;
  const approvedProposalExecutionRoutes = Array.isArray(health.approved_proposal_execution_routes) ? health.approved_proposal_execution_routes : [];
  const allowedExecuteAbilityIds = Array.isArray(health.allowed_execute_ability_ids) ? health.allowed_execute_ability_ids : [];
  let proposalExecutionStatus = 'unknown_check_health';
  if (!health.core_capabilities || !health.abilities_catalog) {
    proposalExecutionStatus = 'blocked_by_missing_dependencies';
  } else if (approvedProposalExecutionRoutes.length > 0) {
    proposalExecutionStatus = 'available_via_adapter_routes';
  }

  console.log(JSON.stringify({
    ok: true,
    status: 'ready',
    profile,
    profile_path: profilePath,
    connection: metadata,
    health: {
      core_capabilities: Boolean(health.core_capabilities),
      abilities_catalog: Boolean(health.abilities_catalog),
      approval_proxy_enabled: approvalProxyEnabled,
      core_proxy_execute: coreProxyExecute,
      commit_execution: commitExecution,
    },
    boundary: {
      status: boundaryOk ? 'ok' : 'unexpected',
      expected: {
        approval_proxy_enabled: false,
        core_proxy_execute: false,
        commit_execution: false,
      },
      note: 'false values are expected boundary controls, not an execution-disabled signal.',
    },
    proposal_execution: {
      status: proposalExecutionStatus,
      routes: approvedProposalExecutionRoutes,
      allowed_ability_ids: allowedExecuteAbilityIds,
      readiness_rule: 'Use GET /proposals/{proposal_id}; execute only through Adapter approve-and-execute or execute routes after Core approval and commit-preflight.',
    },
  }, null, 2));
}

function safeErrorMessage(stdout, stderr) {
  for (const text of [stdout, stderr]) {
    if (!text.trim()) {
      continue;
    }
    try {
      const parsed = JSON.parse(text);
      return parsed.message || parsed.error || parsed.code || 'Request failed.';
    } catch (error) {
      return text.trim().split('\n')[0];
    }
  }
  return 'Request failed.';
}

if (command === 'connect') {
  await connect(commandArgs);
} else if (command === 'status') {
  await status(commandArgs);
} else if (command === 'request') {
  await request(commandArgs);
}
