#!/usr/bin/env bash
set -euo pipefail

echo "▶ Frontend build (POSIX)"

# Try to load nvm if present
NVM_SH="${NVM_DIR:-$HOME/.nvm}/nvm.sh"
if [ -s "$NVM_SH" ]; then
  echo "▶ NVM detected"
  export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
  . "$NVM_SH"
  unset npm_config_prefix || true
  nvm install 22 >/dev/null 2>&1 || true
  nvm use 22
else
  echo "⚠️ NVM not found — using system Node"
fi

# Verify Node is new enough for Vite (>=20.19 or >=22.12)
node -e 'const v=process.versions.node.split(".").map(Number), ok=(v[0]>22)||(v[0]===22&&v[1]>=12)||(v[0]===20&&v[1]>=19); if(!ok){ console.error(`❌ Node ${process.versions.node} is too old (need >=20.19 or >=22.12)`); process.exit(1)} else { console.log(`✅ Node ${process.versions.node} OK`) }'

echo "▶ Installing dependencies..."
if [ -f package-lock.json ]; then
  npm ci || npm install
else
  npm install
fi

echo "▶ Running npm run build..."
npm run build
