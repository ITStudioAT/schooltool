@echo off
setlocal enabledelayedexpansion

echo [BUILD] Frontend build (Windows)

REM Warn if inside Dropbox (locks are common)
echo %CD% | findstr /I "\\Dropbox\\" >nul
if not errorlevel 1 (
  echo [WARN] Project is inside Dropbox. This can lock files in node_modules.
  echo        Add node_modules to .dropboxignore or move project outside Dropbox.
)

where npm >nul 2>nul
if errorlevel 1 (
  echo [ERROR] npm not found on PATH
  exit /b 1
)

REM 1) Try to build without touching node_modules (avoids unlink issues)
if exist node_modules (
  echo [INFO] Found node_modules - skipping install on first attempt
  call npm run build
  if not errorlevel 1 goto :done
  echo [WARN] Build failed - will try reinstalling dependencies...
)

REM 2) Install deps; if EPERM occurs, try to unlock and retry once
echo [BUILD] Installing dependencies...
call npm ci
if errorlevel 1 (
  echo [WARN] npm ci failed - attempting to unlock common processes...
  taskkill /IM esbuild.exe /F >nul 2>nul
  taskkill /IM node.exe /F >nul 2>nul
  del /F /Q node_modules\@esbuild\win32-x64\esbuild.exe >nul 2>nul
  timeout /t 1 >nul
  call npm ci
  if errorlevel 1 (
    echo [ERROR] npm ci still failing (file lock or permissions). See hints below.
    exit /b 1
  )
)

echo [BUILD] Running npm run build...
call npm run build
if errorlevel 1 exit /b 1

:done
echo [SUCCESS] Windows frontend build finished
exit /b 0
