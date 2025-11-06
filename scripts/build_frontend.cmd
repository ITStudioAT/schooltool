@echo off
echo ▶ Frontend build (Windows)

where npm >nul 2>nul
if errorlevel 1 (
  echo ❌ npm not found on PATH
  exit /b 1
)

echo ▶ Installing dependencies...
if exist package-lock.json (
  npm ci
) else (
  npm install
)

echo ▶ Running npm run build...
npm run build
