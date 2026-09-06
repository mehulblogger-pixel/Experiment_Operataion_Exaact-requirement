@echo off
REM ==========================================================================
REM   Stop EXAACT when it was started with start-hidden.vbs (no black window).
REM   Double-click this file to shut the app down cleanly.
REM ==========================================================================
echo.
echo   Stopping EXAACT ...
taskkill /IM php.exe /F >nul 2>nul
if errorlevel 1 (
  echo   It did not appear to be running.
) else (
  echo   EXAACT has been stopped.
)
echo.
timeout /t 3 >nul
