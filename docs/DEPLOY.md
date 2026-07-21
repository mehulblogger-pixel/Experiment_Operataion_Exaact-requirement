# Deploying on your own server

This guide gets the app running 24×7 on a Linux server with a real database and
**automatic HTTPS**. You do not need to write any code. Everything runs with
Docker, so the only things you install on the server are Docker and Git.

## What you need

1. A Linux server (Ubuntu 22.04+ recommended) with a public IP — any VPS
   (Hetzner, DigitalOcean, AWS Lightsail, Linode…) with **2 GB RAM** is enough
   to start.
2. A domain or subdomain you control (e.g. `ops.yourcompany.com`).
3. SSH access to the server.

## Step 1 — Point your domain at the server

In your domain's DNS settings, add an **A record** for `ops.yourcompany.com`
pointing to your server's public IP. (HTTPS won't work until DNS resolves.)

## Step 2 — Install Docker on the server

```bash
curl -fsSL https://get.docker.com | sh
```

## Step 3 — Get the code

```bash
git clone <your-repo-url> inspexops
cd inspexops
git checkout claude/quotation-management-workflow-5dokb2
```

## Step 4 — Create your settings file

```bash
cp .env.production.example .env
nano .env          # fill in DOMAIN, passwords, and the admin login
```

At minimum set: `DOMAIN`, `DJANGO_SECRET_KEY`, `DJANGO_ALLOWED_HOSTS`,
`DJANGO_CSRF_TRUSTED_ORIGINS`, `POSTGRES_PASSWORD`, and the three
`DJANGO_SUPERUSER_*` values. Generate a secret key with:

```bash
docker run --rm python:3.12-slim python -c "import secrets; print(secrets.token_urlsafe(50))"
```

## Step 5 — Start everything

```bash
docker compose up -d --build
```

That's it. The stack starts three containers:

- **db** — PostgreSQL (your data lives in a Docker volume, survives restarts)
- **web** — the Django app (runs migrations + seeds masters + creates your admin)
- **caddy** — reverse proxy that fetches a free HTTPS certificate automatically

Open `https://ops.yourcompany.com/` and sign in with the admin user you set.

## Everyday operations

| Task | Command (run in the project folder) |
| --- | --- |
| See logs | `docker compose logs -f web` |
| Restart | `docker compose restart` |
| Update after code changes | `git pull && docker compose up -d --build` |
| Stop | `docker compose down` (data is kept) |
| Create another user | do it in **Admin / Config** at `/admin/`, or `docker compose exec web python manage.py createsuperuser` |
| Run the daily digest now | `docker compose exec web python manage.py daily_digest` |

## Schedule the daily digest & escalations

Add a host cron entry so it runs every evening (e.g. 19:00):

```bash
crontab -e
# add:
0 19 * * * cd /path/to/inspexops && docker compose exec -T web python manage.py daily_digest
```

## Backups (important)

Back up the database volume regularly:

```bash
docker compose exec -T db pg_dump -U inspexops inspexops > backup_$(date +%F).sql
```

Copy those `.sql` files off the server (e.g. to S3 or another machine).

---

## Alternative: without Docker (systemd)

If you prefer not to use Docker, run gunicorn behind nginx/Caddy directly:

```bash
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
# set the same env vars as .env, then:
python manage.py migrate && python manage.py seed_masters
python manage.py collectstatic --noinput
gunicorn config.wsgi:application --bind 127.0.0.1:8000 --workers 3
```

Put Caddy or nginx in front for HTTPS and point it at `127.0.0.1:8000`. Manage
gunicorn with a systemd service so it starts on boot. The Docker path above is
strongly recommended — it does all of this for you.
