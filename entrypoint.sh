#!/usr/bin/env sh
set -e

# Wait for the database, then apply migrations and (first run) seed masters.
echo "Applying database migrations..."
python manage.py migrate --noinput

if [ "${SEED_ON_START:-true}" = "true" ]; then
  echo "Seeding master data (idempotent)..."
  python manage.py seed_masters || true
fi

# Create or update the admin from env vars (idempotent; always applies the
# configured password, so login works on every deploy with no shell steps).
python manage.py ensure_admin || true

echo "Starting gunicorn..."
exec gunicorn config.wsgi:application \
  --bind 0.0.0.0:8000 \
  --workers "${GUNICORN_WORKERS:-3}" \
  --timeout "${GUNICORN_TIMEOUT:-60}" \
  --access-logfile - \
  --error-logfile -
