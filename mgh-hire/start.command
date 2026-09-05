#!/bin/bash
# ============================================================================
#  MGH Hire — run on this Mac or Linux laptop with one double-click.
#  Nothing is installed and nothing leaves this computer. Needs PHP 8.1+.
#  Leave the black window open while you use the app; close it to stop.
# ============================================================================
cd "$(dirname "$0")" || exit 1
PORT=8090
PHP="$(command -v php)"
if [ -z "$PHP" ]; then
  echo
  echo "  PHP is not installed on this computer."
  echo "  Install PHP 8.1+ then double-click this file again:"
  echo "    Mac:    install Homebrew from https://brew.sh  then:  brew install php"
  echo "    Linux:  sudo apt install php-cli php-sqlite3   (Debian/Ubuntu)"
  echo
  read -r -p "  Press Enter to close."
  exit 1
fi
export DB_DRIVER=sqlite
export SQLITE_PATH="$(pwd)/data.sqlite"
URL="http://127.0.0.1:$PORT"
echo "  MGH Hire is starting at  $URL"
( sleep 1; (command -v open >/dev/null && open "$URL") || (command -v xdg-open >/dev/null && xdg-open "$URL") ) >/dev/null 2>&1 &
"$PHP" -S 127.0.0.1:$PORT index.php
