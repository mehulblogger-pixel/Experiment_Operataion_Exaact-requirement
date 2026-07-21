#!/usr/bin/env sh
set -e

# Wait for the database, then apply migrations and (first run) seed masters.
echo "Applying database migrations..."
python manage.py migrate --noinput

if [ "${SEED_ON_START:-true}" = "true" ]; then
  echo "Seeding master data (idempotent)..."
  python manage.py seed_masters || true
fi

# Optionally create the first admin from env vars (only if it doesn't exist).
if [ -n "${DJANGO_SUPERUSER_USERNAME:-}" ] && [ -n "${DJANGO_SUPERUSER_PASSWORD:-}" ]; then
  echo "Ensuring superuser '${DJANGO_SUPERUSER_USERNAME}' exists..."
  python manage.py createsuperuser --noinput \
    --username "${DJANGO_SUPERUSER_USERNAME}" \
    --email "${DJANGO_SUPERUSER_EMAIL:-admin@example.com}" 2>/dev/null || true
fi

echo "Starting gunicorn..."
exec gunicorn config.wsgi:application \
  --bind 0.0.0.0:8000 \
  --workers "${GUNICORN_WORKERS:-3}" \
  --timeout "${GUNICORN_TIMEOUT:-60}" \
  --access-logfile - \
  --error-logfile -
