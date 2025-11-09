#!/usr/bin/env bash
set -euo pipefail

echo "▶ Frontend build (POSIX)"

# Try to load nvm from common locations, including project-local .nvm
CANDIDATES=(
  "${NVM_DIR:-}"
  "$PWD/.nvm"
  "$HOME/.nvm"
  "/usr/local/nvm"
)

for dir in "${CANDIDATES[@]}"; do
  if [ -n "${dir}" ] && [ -s "${dir}/nvm.sh" ]; then
    export NVM_DIR="${dir}"
    . "${NVM_DIR}/nvm.sh"
    echo "▶ NVM loaded from ${NVM_DIR}"
    break
  fi
done

if command -v nvm >/dev/null 2>&1; then
  unset npm_config_prefix || true
  nvm install 22 >/dev/null 2>&1 || true
  nvm use 22
  echo "▶ Node after nvm: $(node -v)"
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
