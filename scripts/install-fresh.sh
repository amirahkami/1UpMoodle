#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
  echo "Missing .env. Copy .env.example first." >&2
  exit 1
fi

if [[ -f app/v53/config.php ]]; then
  echo "app/v53/config.php already exists. Remove it only when intentionally creating a fresh Moodle installation." >&2
  exit 1
fi

docker compose up -d db mailpit
docker compose run --rm --no-deps --entrypoint sh v53 -ec '
  php /bitnami/moodle/admin/cli/install.php \
    --lang=en \
    --wwwroot="http://${MOODLE_HOST}" \
    --dataroot=/bitnami/moodledata \
    --dbtype="${MOODLE_DATABASE_TYPE}" \
    --dbhost="${MOODLE_DATABASE_HOST}" \
    --dbport="${MOODLE_DATABASE_PORT_NUMBER}" \
    --dbname="${MOODLE_DATABASE_NAME}" \
    --dbuser="${MOODLE_DATABASE_USER}" \
    --dbpass="${MOODLE_DATABASE_PASSWORD}" \
    --fullname="${MOODLE_SITE_NAME}" \
    --shortname=Moodle \
    --adminuser="${MOODLE_USERNAME}" \
    --adminpass="${MOODLE_PASSWORD}" \
    --adminemail="${MOODLE_EMAIL}" \
    --agree-license \
    --non-interactive
'

docker compose up -d v53
