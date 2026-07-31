#!/usr/bin/env bash
# ===========================================
# SMS Enterprise - Run new migrations in Docker
# ===========================================
# Applies any pending Laravel migrations inside the `php` container.
# Safe to run repeatedly: Laravel only executes migrations that
# haven't been recorded in the `migrations` table yet.
#
# Usage:
#   ./scripts/migrate.sh              # run pending migrations
#   ./scripts/migrate.sh --seed       # run pending migrations + seed
#   ./scripts/migrate.sh --status     # show migration status only
#   ./scripts/migrate.sh --force      # skip the "are you sure" env check
#
# The script assumes docker-compose (from the repo root) manages a
# `php` service and a `postgres` service, matching this repo's
# docker-compose.yml.

set -euo pipefail

# Resolve repo root regardless of where the script is invoked from.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

if command -v docker-compose >/dev/null 2>&1; then
  DC=(docker-compose)
else
  DC=(docker compose)
fi

# backend/.env is mounted straight into the php container and may be
# configured for a host-native Postgres (e.g. DB_HOST=127.0.0.1) for
# non-Docker local dev. Inside the Docker network the database is only
# reachable at the "postgres" service name on its internal port 5432,
# so we override the DB_* connection vars for this exec call only,
# sourced from the repo-root .env that docker-compose itself reads.
DB_DATABASE_VAL="$(grep -m1 '^DB_DATABASE=' .env 2>/dev/null | cut -d= -f2-)"
DB_USERNAME_VAL="$(grep -m1 '^DB_USERNAME=' .env 2>/dev/null | cut -d= -f2-)"
DB_PASSWORD_VAL="$(grep -m1 '^DB_PASSWORD=' .env 2>/dev/null | cut -d= -f2-)"
DB_ENV_ARGS=(
  -e "DB_CONNECTION=pgsql"
  -e "DB_HOST=postgres"
  -e "DB_PORT=5432"
  -e "DB_DATABASE=${DB_DATABASE_VAL:-sms_db}"
  -e "DB_USERNAME=${DB_USERNAME_VAL:-sms_user}"
  -e "DB_PASSWORD=${DB_PASSWORD_VAL:-sms_secret}"
)

SEED=false
STATUS_ONLY=false
FORCE=false

for arg in "$@"; do
  case "$arg" in
    --seed) SEED=true ;;
    --status) STATUS_ONLY=true ;;
    --force) FORCE=true ;;
    *)
      echo "Unknown option: $arg" >&2
      echo "Usage: $0 [--seed] [--status] [--force]" >&2
      exit 1
      ;;
  esac
done

echo "==> Checking that the php container is running..."
if ! "${DC[@]}" ps --status running php 2>/dev/null | grep -q php; then
  echo "==> php container is not up. Starting containers (docker-compose up -d)..."
  "${DC[@]}" up -d
fi

echo "==> Waiting for PostgreSQL to accept connections..."
tries=0
max_tries=30
until "${DC[@]}" exec -T postgres pg_isready -U "${DB_USERNAME_VAL:-sms_user}" -d "${DB_DATABASE_VAL:-sms_db}" >/dev/null 2>&1; do
  tries=$((tries + 1))
  if [ "$tries" -ge "$max_tries" ]; then
    echo "ERROR: database did not become ready after ${max_tries} attempts." >&2
    exit 1
  fi
  echo "    ...database not ready yet ($tries/$max_tries), retrying in 2s"
  sleep 2
done
echo "==> Database is ready."

if [ "$STATUS_ONLY" = true ]; then
  echo "==> Migration status:"
  exec "${DC[@]}" exec -T "${DB_ENV_ARGS[@]}" php php artisan migrate:status
fi

# Guard against accidentally migrating a production-looking env without
# an explicit --force flag, mirroring Artisan's own production prompt.
APP_ENV="$("${DC[@]}" exec -T "${DB_ENV_ARGS[@]}" php php artisan env 2>/dev/null | tr -d '\r' || echo "unknown")"
ARTISAN_FORCE_FLAG=()
if echo "$APP_ENV" | grep -qi "production"; then
  ARTISAN_FORCE_FLAG=(--force)
  if [ "$FORCE" != true ]; then
    read -r -p "APP_ENV looks like production. Continue running migrations? [y/N] " reply
    case "$reply" in
      [yY][eE][sS]|[yY]) ;;
      *) echo "Aborted."; exit 1 ;;
    esac
  fi
fi

echo "==> Running pending migrations..."
"${DC[@]}" exec -T "${DB_ENV_ARGS[@]}" php php artisan migrate "${ARTISAN_FORCE_FLAG[@]}"

if [ "$SEED" = true ]; then
  echo "==> Running seeders..."
  "${DC[@]}" exec -T "${DB_ENV_ARGS[@]}" php php artisan db:seed "${ARTISAN_FORCE_FLAG[@]}"
fi

echo "==> Current migration status:"
"${DC[@]}" exec -T "${DB_ENV_ARGS[@]}" php php artisan migrate:status

echo "==> Done."
