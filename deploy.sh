#!/usr/bin/env bash
# Aktualizacja strony z GitHuba. Na serwerze, jako root, w katalogu projektu:
#   bash deploy.sh
# Nie rusza .env, bazy ani wgranych zdjęć.
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_USER="$(stat -c %U "$APP_DIR")"
PHP="${PHP:-/usr/bin/php8.4}"
BRANCH="${BRANCH:-LaptopDell}"

run_as_app() { runuser -u "$APP_USER" -- "$@"; }

cd "$APP_DIR"

command -v composer >/dev/null || { echo "Brak composera. Zainstaluj go i uruchom ponownie."; exit 1; }
git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$APP_DIR" || git config --global --add safe.directory "$APP_DIR"

echo "==> Tryb serwisowy"
run_as_app "$PHP" artisan down || true
trap 'run_as_app "$PHP" artisan up' EXIT

echo "==> Pobieranie kodu z gałęzi $BRANCH"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Biblioteki PHP"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction

echo "==> Budowanie frontendu"
export NVM_DIR="${NVM_DIR:-/root/.nvm}"
# shellcheck disable=SC1091
. "$NVM_DIR/nvm.sh"
nvm use 22 >/dev/null 2>&1 || nvm install 22
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

echo "==> Uprawnienia, baza i cache"
chown -R "$APP_USER:$APP_USER" "$APP_DIR"
run_as_app "$PHP" artisan migrate --force
run_as_app "$PHP" artisan optimize
# Czat na żywo: proces Reverb kończy się i jego usługa uruchamia go z nowym kodem.
run_as_app "$PHP" artisan reverb:restart >/dev/null 2>&1 || true

echo "==> Gotowe: $(git log -1 --format='%h %s')"
