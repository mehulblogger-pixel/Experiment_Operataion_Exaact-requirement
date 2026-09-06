@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

REM =====================================================================
REM   EXAACT Full System - EASY Windows launcher
REM
REM   This finds PHP for you. You do NOT need to edit Windows PATH and
REM   you do NOT need to edit php.ini. Just make sure PHP is unzipped
REM   under  C:\php  (so that C:\php\php.exe exists), then double-click
REM   this file. Keep the black window open while you work; close it to
REM   stop the app.
REM =====================================================================

set PORT=8080
set "PHPEXE="
set "PHPDIR="

REM 1) the standard spot: C:\php\php.exe
if exist "C:\php\php.exe" (
  set "PHPEXE=C:\php\php.exe"
  set "PHPDIR=C:\php"
)

REM 2) PHP unzipped into a sub-folder inside C:\php  (a common mistake - handled)
if not defined PHPEXE (
  for /d %%D in ("C:\php\*") do (
    if exist "%%D\php.exe" (
      set "PHPEXE=%%D\php.exe"
      set "PHPDIR=%%D"
    )
  )
)

REM 3) a "php" folder placed right next to this app
if not defined PHPEXE (
  if exist "%~dp0php\php.exe" (
    set "PHPEXE=%~dp0php\php.exe"
    set "PHPDIR=%~dp0php"
  )
)

REM 4) PHP already on the PATH
if not defined PHPEXE (
  where php >nul 2>nul && set "PHPEXE=php"
)

if not defined PHPEXE (
  echo.
  echo   Could not find PHP yet.
  echo   Please unzip your PHP download into     C:\php
  echo   so that this file exists:               C:\php\php.exe
  echo   Then double-click this launcher again.
  echo.
  pause
  exit /b 1
)

REM The built-in database - no MySQL, no setup. One file next to the app.
set DB_DRIVER=sqlite
set "SQLITE_PATH=%~dp0data.sqlite"

echo.
echo   Found PHP:   !PHPEXE!
echo   Starting EXAACT Full System on  http://127.0.0.1:%PORT%
echo.
echo   Keep THIS window open while you work.  Close it to stop the app.
echo.

start "" "http://127.0.0.1:%PORT%"

if defined PHPDIR (
  "!PHPEXE!" -n ^
    -d extension_dir="!PHPDIR!\ext" ^
    -d extension=pdo_sqlite ^
    -d extension=mbstring ^
    -d extension=openssl ^
    -d extension=fileinfo ^
    -d extension=curl ^
    -d extension=gd ^
    -d extension=zip ^
    -d date.timezone=UTC ^
    -d upload_max_filesize=32M ^
    -d post_max_size=32M ^
    -d memory_limit=512M ^
    -S 127.0.0.1:%PORT% router.php
) else (
  "!PHPEXE!" -S 127.0.0.1:%PORT% router.php
)

echo.
echo   The app has stopped. You can close this window.
pause
