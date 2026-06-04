#!/usr/bin/env node
import { spawn } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = dirname(dirname(fileURLToPath(import.meta.url)));
const packageTool = join(rootDir, 'packages', 'adapter-cli', 'bin', 'keypair-device-pairing.mjs');
const child = spawn(process.execPath, [packageTool, ...process.argv.slice(2)], { stdio: 'inherit' });

child.on('error', (error) => {
  console.error(error.message);
  process.exit(1);
});

child.on('close', (code) => {
  process.exit(code || 0);
});
