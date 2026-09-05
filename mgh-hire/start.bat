@echo off
REM ==========================================================================
REM   MGH Hire — run on this Windows laptop with one double-click.
REM   Nothing is installed and nothing leaves this PC. Needs PHP 8.1+.
REM   Keep the black window open while you use the app; close it to stop.
REM ==========================================================================
cd /d "%~dp0"
set PORT=8090
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
set DB_DRIVER=sqlite
set SQLITE_PATH=%~dp0data.sqlite
echo   MGH Hire is starting at  http://127.0.0.1:%PORT%
start "" http://127.0.0.1:%PORT%
php -S 127.0.0.1:%PORT% index.php
