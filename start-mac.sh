#!/bin/bash
# Starts the BGE Corporation CRM on a Mac with XAMPP.
# First run: imports the database and installs the website's packages.
# Stop everything with Control+C.

set -e
cd "$(dirname "$0")"
ROOT="$(pwd)"

XAMPP=/Applications/XAMPP/xamppfiles
PHP="$XAMPP/bin/php"
MYSQL="$XAMPP/bin/mysql"

if [ ! -x "$PHP" ]; then
  echo "XAMPP was not found in /Applications/XAMPP. Install XAMPP for macOS first (see SETUP-MAC.md)."
  exit 1
fi
if ! command -v npm >/dev/null 2>&1; then
  echo "Node.js is not installed. Install the LTS version from https://nodejs.org (see SETUP-MAC.md)."
  exit 1
fi

# The database must be running (XAMPP > Manage Servers > MySQL Database > Start).
if ! "$MYSQL" -u root -e "SELECT 1" >/dev/null 2>&1; then
  echo "MySQL is not running. Open XAMPP, go to Manage Servers, start MySQL Database, then run this again."
  exit 1
fi

# First run: load the database exported from the Windows PC.
if ! "$MYSQL" -u root -e "USE sari_crm" >/dev/null 2>&1; then
  echo "Importing the database..."
  "$MYSQL" -u root < "$ROOT/backend/database/export/sari_crm.sql"
fi

# First run: download the server's PHP packages (exact versions from composer.lock).
if [ ! -f "$ROOT/backend/vendor/autoload.php" ]; then
  echo "Installing the server's packages (first run only)..."
  cd "$ROOT/backend"
  if [ ! -f composer.phar ]; then
    curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o composer.phar
  fi
  "$PHP" composer.phar install --no-interaction --prefer-dist
  cd "$ROOT"
fi

# First run: install the website's packages (built for this Mac).
if [ ! -d "$ROOT/frontend/node_modules" ]; then
  echo "Installing the website's packages (first run only)..."
  (cd "$ROOT/frontend" && npm install)
fi

cd "$ROOT/backend"
"$PHP" artisan config:clear >/dev/null
"$PHP" artisan migrate --force

echo ""
echo "Starting the CRM..."
echo "  This Mac:   http://localhost:5173"
IP="$(ipconfig getifaddr en0 2>/dev/null || true)"
[ -n "$IP" ] && echo "  Phones on the same Wi-Fi: http://$IP:5173"
echo "  Press Control+C to stop."
echo ""

"$PHP" artisan serve --host=0.0.0.0 --port=8000 &
API_PID=$!
trap 'kill $API_PID 2>/dev/null' EXIT

cd "$ROOT/frontend"
npm run dev -- --host 0.0.0.0 --port 5173 --strictPort
