#!/bin/sh

set -eu

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <job_name> <console_command> [args...]"
  exit 64
fi

JOB_NAME="$1"
shift

APP_DIR="${APP_DIR:-/var/www/app}"
LOCK_DIR="/tmp/cron-lock-${JOB_NAME}"

if ! mkdir "${LOCK_DIR}" 2>/dev/null; then
  echo "[cron] skip ${JOB_NAME}: previous run is still active"
  exit 0
fi

cleanup() {
  rmdir "${LOCK_DIR}" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

cd "${APP_DIR}"

echo "[cron] start ${JOB_NAME} at $(date -u +%Y-%m-%dT%H:%M:%SZ)"
php bin/console "$@" --env=prod --no-debug
status=$?
echo "[cron] finish ${JOB_NAME} status=${status} at $(date -u +%Y-%m-%dT%H:%M:%SZ)"

exit "${status}"
