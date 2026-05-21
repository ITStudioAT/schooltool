@echo off
setlocal enabledelayedexpansion

echo [BUILD] Frontend build (Windows)

REM Warn if inside Dropbox (locks are common)
echo %CD% | findstr /I "\\Dropbox\\" >nul
if not errorlevel 1 (
  echo [WARN] Project is inside Dropbox. This can lock files in node_modules.
  echo        Add node_modules to .dropboxignore or move project outside Dropbox.
)

where node >nul 2>nul
if errorlevel 1 (
  echo [ERROR] node not found on PATH
  exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
  echo [ERROR] npm not found on PATH
  exit /b 1
)

node scripts/check_node_version.cjs
if errorlevel 1 exit /b 1

if not exist package-lock.json (
  echo [ERROR] package-lock.json is missing; npm ci requires a lockfile
  exit /b 1
)

REM 1) Install deps deterministically from the lockfile
echo [BUILD] Installing dependencies...
set PUPPETEER_SKIP_DOWNLOAD=1
if not defined NPM_CONFIG_CACHE set "NPM_CONFIG_CACHE=%CD%\storage\framework\npm-cache"
set "npm_config_cache=%NPM_CONFIG_CACHE%"
if not exist "%NPM_CONFIG_CACHE%" mkdir "%NPM_CONFIG_CACHE%"
echo Using npm cache: %NPM_CONFIG_CACHE%
call npm ci
if errorlevel 1 (
  echo [ERROR] npm ci failed. See output above.
  echo [HINT] Close editors/watchers and unlock node_modules\esbuild files, then rerun app:update.
  exit /b 1
)

echo [BUILD] Running npm run build...
call npm run build
if errorlevel 1 exit /b 1
echo [SUCCESS] Windows frontend build finished
exit /b 0
