#!/bin/bash
# ============================================================================
#  Run the system on this Mac or Linux laptop — one double-click.
#
#  Double-click this file (Mac: you may need to right-click → Open the first
#  time). It starts the app on the built-in database and opens your browser.
#  Nothing is installed and nothing leaves this computer.
#
#  The only requirement is PHP 8.1+ . If it is missing, this tells you how.
#  Leave the black window open while you use the app; close it to stop.
# ============================================================================
cd "$(dirname "$0")" || exit 1

PORT=8080
PHP="$(command -v php)"
if [ -z "$PHP" ]; then
  echo
  echo "  PHP is not installed on this computer."
  echo "  Install PHP 8.1 or newer, then double-click this file again:"
  echo "    Mac:    install Homebrew from https://brew.sh  then run:  brew install php"
  echo "    Linux:  sudo apt install php-cli php-sqlite3   (Debian/Ubuntu)"
  echo
  read -r -p "  Press Enter to close."
  exit 1
fi

# The built-in database — no MySQL, no configuration. One file next to the app.
export DB_DRIVER=sqlite
export SQLITE_PATH="$(pwd)/data.sqlite"

URL="http://127.0.0.1:$PORT"
echo
echo "  Starting the system …"
echo "  Open this address in your browser if it does not open by itself:"
echo "     $URL"
echo
echo "  Keep this window open while you work. Close it to stop the app."
echo

# Open the browser a moment after the server comes up.
( sleep 2
  if command -v open >/dev/null 2>&1; then open "$URL"
  elif command -v xdg-open >/dev/null 2>&1; then xdg-open "$URL"
  fi ) &

exec "$PHP" -S 127.0.0.1:"$PORT" router.php
