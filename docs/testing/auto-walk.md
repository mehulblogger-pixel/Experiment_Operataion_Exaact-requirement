# Auto-Walk — find (and safely fix) broken screens automatically

Two crawlers drive the whole app as every role and fail on any PHP error, so a
broken screen is caught by a command instead of by a person clicking one. This is
the automated companion to the manual [Test Walkthrough](../../README.md) checklist.

## What runs

| Tool | Surface | Signs in as |
|---|---|---|
| `phpapp/tools/smoke.js` | Internal ops app (`/`, `/jobs`, `/invoices`, registers…) | Master Admin |
| `phpapp/tools/portal-crawl.js` | Client/agency **portal** (`/portal/*`) + professional **passport** (`/pro/*`) | Every seeded demo user (freelancer, client, 3 client roles, agency) |

Together they open ~**194 screens** across every persona. Exit code `0` = all
render; `1` = at least one is broken, with the URL and the error text.

## How to run it

```bash
cd phpapp
# 1. throwaway seeded DB (never the real one)
export DB_DRIVER=sqlite SQLITE_PATH=/tmp/walk.sqlite ADMIN_PASSWORD=admin12345
rm -f /tmp/walk.sqlite
php tools/seed-scenario-s01.php && php tools/seed-scenario-s02.php && php tools/seed-scenario-s03.php

# 2. boot the app on that DB
php -S 127.0.0.1:8811 tools/smoke-router.php &

# 3. crawl both surfaces
NODE_PATH=<node_modules> node tools/smoke.js        http://127.0.0.1:8811 admin admin12345
NODE_PATH=<node_modules> node tools/portal-crawl.js http://127.0.0.1:8811
```

## The safe auto-fix loop

When a crawl (or a human tester) reports a failure, every fix follows the same
guardrails so it can never break something else:

1. **Reproduce** the failure on the throwaway seeded DB.
2. **Fix** it — additively (`CREATE TABLE IF NOT EXISTS` / `ensure_column`), never
   dropping or renaming a live column, never changing a permission or lifecycle
   without sign-off. Work stays on the feature branch, never the default branch.
3. **Re-verify:** `php -l` on changed files, `php tests/run.php` must stay green
   (baseline **5213 passing, 0 failed**), and the crawl re-run must show the screen
   fixed **and** nothing else newly broken.
4. **Commit** each fix as its own small, reversible change.

If a fix would drop a test, it is reverted, not shipped. A crawl catches crashes
and PHP errors; the manual walkthrough still owns judgement-level checks (wrong
values, confusing labels, a button that does the wrong thing).

## Baseline (last run)

194 screens across all personas — **all render cleanly**; the client Request form
submits cleanly end-to-end; test suite **5213 passed, 0 failed**.
