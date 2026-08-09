@echo off
REM ==========================================================================
REM   Run the system on this Windows laptop — one double-click.
REM
REM   Double-click this file. It starts the app on the built-in database and
REM   opens your browser. Nothing is installed and nothing leaves this PC.
REM
REM   The only requirement is PHP 8.1+ . If it is missing, this tells you how.
REM   Keep the black window open while you use the app; close it to stop.
REM ==========================================================================
cd /d "%~dp0"

set PORT=8080

where php >nul 2>nul
if errorlevel 1 (
  echo.
  echo   PHP is not installed on this computer.
  echo   Install PHP 8.1 or newer, then double-click this file again:
  echo     1^) Download the "Thread Safe" zip from https://windows.php.net/download/
  echo     2^) Unzip it to C:\php  and add C:\php to your PATH
  echo     3^) In php.ini enable:  extension=pdo_sqlite   extension=mbstring
  echo.
  pause
  exit /b 1
)

REM The built-in database — no MySQL, no configuration. One file next to the app.
set DB_DRIVER=sqlite
set SQLITE_PATH=%~dp0data.sqlite

echo.
echo   Starting the system ...
echo   If your browser does not open, go to:  http://127.0.0.1:%PORT%
echo.
echo   Keep this window open while you work. Close it to stop the app.
echo.

start "" "http://127.0.0.1:%PORT%"
php -S 127.0.0.1:%PORT% router.php
