# Inspection Operations Management System

A multi-user web application that turns the *Final Dashboard* Excel model into a
live system for an inspection-services network (HQ Ahmedabad + branch offices).

This first release delivers the **Operations / Scheduling** module — the Call
Register, Scheduling Board, inspector allocation, reporting/TAT and the
inter-office credit model — on top of a shared foundation of roles, offices,
SBUs and fully configurable master data seeded from your workbook.

> **Built with Django** specifically because a non-technical admin can add, edit
> or retire *any* dropdown value, job type, report format, user or role from the
> point-and-click **Admin / Config** screen — no code changes required
> (requirements 6, 8, 18).

---

## Quick start (zero config)

```bash
python -m venv .venv
source .venv/bin/activate           # Windows: .venv\Scripts\activate
pip install -r requirements.txt

python manage.py migrate            # creates the SQLite database
python manage.py seed_masters       # loads offices/SBUs/clients/inspectors from the Excel export
python manage.py createsuperuser     # your admin login

python manage.py runserver
```

Open <http://127.0.0.1:8000/> and sign in.

- **Dashboard / Schedule Board / Calls** — the operational app.
- **Admin / Config** (`/admin/`) — manage every dropdown, master list, user and role.

No database server, SMTP account or extra services are needed to run it — SQLite
is the default and emails print to the terminal until you configure SMTP.

## Deploying on your own server (with HTTPS)

The repo ships a one-command Docker stack (Postgres + gunicorn + Caddy with
automatic HTTPS). On your server:

```bash
cp .env.production.example .env   # fill in DOMAIN, passwords, admin login
docker compose up -d --build
```

Full step-by-step instructions (DNS, backups, scheduling the daily digest) are
in **[`docs/DEPLOY.md`](docs/DEPLOY.md)**.

### Switching to Postgres + real email manually

If you are not using Docker, copy `.env.example` to `.env`, set the `POSTGRES_*`
and `EMAIL_*` variables (the app reads them automatically — see
`config/settings.py`), then:

```bash
DJANGO_DEBUG=False python manage.py collectstatic --noinput
DJANGO_DEBUG=False python manage.py migrate
```

## Scheduled jobs

The end-of-day digest & escalation (requirements 5gg, 5ii) runs as a command:

```bash
python manage.py daily_digest
```

Wire it to cron / Windows Task Scheduler / Celery-beat to run once a day.

## Running the tests

```bash
python manage.py test
```

---

## What maps to what (Excel → app)

| Excel sheet / table | Becomes |
| --- | --- |
| `Master Scheduling File` / `tblMaster` | `operations.InspectionCall` (Call Register) |
| `Setup_Inspectors`, `Setup_Subcon` | `masters.Inspector` (+ subcon rate fields) |
| SBU / Activity / Product / Vendor-location columns | `masters` dropdown tables (seeded) |
| `Network_Credits` | `operations.NetworkCredit` (contracting ↔ executing office) |
| `tblEmailSchedule` + branch reports | `daily_digest` command (email) |

## Project layout

```
config/         Django settings, root URLs
accounts/       Custom User + roles (SBU Head, Branch/Operation Manager, Coordinators, Inspector, …)
masters/        Configurable reference data + seed command (masters/seed_data.json)
operations/     Call Register, Schedule Register, deliverables/TAT, credits, daily_digest
dashboard/      Landing dashboard (open / pending / closed, requirement 14)
templates/, static/   UI
```

## Requirement coverage in this release

Implemented: configurable job types & sub-categories (18, 25), configurable
dropdowns/report-formats/payment-terms (6, 8, 5w), full Call Register form
(5a–5cc), contracting-vs-executing office + network credit (5cc), auto call
reference numbers, lead-time & scheduling-delay (5s, 5t), colour-coded schedule
board (5gg), inspector allocation & reshuffle with tentative flag (6, 6.2/6.3),
reject-with-reason recording (5ee), advance-payment & deliverable-against-payment
flags (21, 22), mark-complete + invoice-required prompt (5g/5h), end-of-day
digest + escalation to Branch Manager / SBU Head (5gg, 5ii), open/pending/closed
lists (14).

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for the phased plan covering the
remaining modules (Quotation/Sales workflow, Inspector-hiring/CV pipeline,
per-inspector reporting portal, monthly reports, and the full email-automation
chain).
