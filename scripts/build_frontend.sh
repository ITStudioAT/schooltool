#!/usr/bin/env bash
set -euo pipefail

echo "▶ Frontend build (POSIX)"

# ------------------------------------------------------------------------------
# 1. Load NVM if available
# ------------------------------------------------------------------------------
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

# ------------------------------------------------------------------------------
# 2. Ensure Node.js version
# ------------------------------------------------------------------------------
if command -v nvm >/dev/null 2>&1; then
  unset npm_config_prefix || true
  nvm install 22 >/dev/null 2>&1 || true
  nvm use 22
  echo "▶ Node after nvm: $(node -v)"
else
  echo "⚠️ NVM not found — using system Node"
fi

# Validate Node version
node -e '
  const v = process.versions.node.split(".").map(Number);
  const ok = (v[0] > 22) || (v[0] === 22 && v[1] >= 12) || (v[0] === 20 && v[1] >= 19);
  if (!ok) {
    console.error(`❌ Node ${process.versions.node} too old (need >=20.19 or >=22.12)`);
    process.exit(1);
  } else {
    console.log(`✅ Node ${process.versions.node} OK`);
  }
'

# ------------------------------------------------------------------------------
# 3. Install dependencies FIRST
# ------------------------------------------------------------------------------
echo "▶ Installing dependencies..."
if [ -f package-lock.json ]; then
  echo "▶ Using npm ci (faster, strict)..."
  npm ci || npm install
else
  echo "▶ No package-lock.json found — running npm install..."
  npm install
fi

# ------------------------------------------------------------------------------
# 4. Run build
# ------------------------------------------------------------------------------
echo "▶ Running npm run build..."
npm run build

echo "✅ Frontend build complete!"
