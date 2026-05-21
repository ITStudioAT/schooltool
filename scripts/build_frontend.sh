#!/usr/bin/env bash
set -euo pipefail

echo "▶ Frontend build (POSIX)"

export PUPPETEER_SKIP_DOWNLOAD=1
export NPM_CONFIG_CACHE="${NPM_CONFIG_CACHE:-"$PWD/storage/framework/npm-cache"}"
export npm_config_cache="$NPM_CONFIG_CACHE"

if ! command -v node >/dev/null 2>&1; then
  echo "❌ node not found on PATH"
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "❌ npm not found on PATH"
  exit 1
fi

node scripts/check_node_version.cjs

if [ ! -f package-lock.json ]; then
  echo "❌ package-lock.json is missing; npm ci requires a lockfile"
  exit 1
fi

echo "▶ Installing dependencies..."
mkdir -p "$NPM_CONFIG_CACHE"
echo "Using npm cache: $NPM_CONFIG_CACHE"
if [ -d node_modules ]; then
  echo "▶ Removing existing node_modules before npm ci..."
  rm -rf node_modules
fi
npm ci

echo "▶ Running npm run build..."
npm run build
