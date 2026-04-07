#!/usr/bin/env node

const version = process.argv[2] ?? process.versions.node;
const match = version.match(/^v?(\d+)\.(\d+)\.(\d+)/);

if (!match) {
  console.error(`Unable to parse Node version: ${version}`);
  process.exit(1);
}

const major = Number(match[1]);
const minor = Number(match[2]);
const isCompatible = major > 22 || (major === 22 && minor >= 12) || (major === 20 && minor >= 19);

if (!isCompatible) {
  console.error(`Node ${version} is too old (need >=20.19 or >=22.12)`);
  process.exit(1);
}

console.log(`Node ${version} OK`);
