# Auto-Walk — find (and safely fix) broken screens automatically

Two crawlers drive the whole app as every role and fail on any PHP error, so a
broken screen is caught by a command instead of by a person clicking one. This is
the automated companion to the manual [Test Walkthrough](../../README.md) checklist.

## What runs

| Tool | Surface | Signs in as |
|---|---|---|
| `phpapp/tools/smoke.js` | Internal ops app (`/`, `/jobs`, `/invoices`, registers…) | Master Admin |
| `phpapp/tools/portal-crawl.js` | Client/agency **portal** (`/portal/*`) + professional **passport** (`/pro/*`) | Every seeded demo user (freelancer, client, 3 client roles, agency, DEMO-S04 marketplace client) |

Together they open ~**203 screens** across every persona. Exit code `0` = all
render; `1` = at least one is broken, with the URL and the error text.

## How to run it

**One command** does the whole loop (seed → boot → crawl both surfaces → tear down):

```bash
cd phpapp && bash tools/auto-walk.sh
```

Exit 0 = every screen renders; exit 1 = the broken URLs and their error text are
printed above the summary. It uses a throwaway `/tmp` database and never touches
the real data, the default branch, or any app code.

<details><summary>Or the individual steps</summary>

```bash
cd phpapp
export DB_DRIVER=sqlite SQLITE_PATH=/tmp/walk.sqlite ADMIN_PASSWORD=admin12345
rm -f /tmp/walk.sqlite
php tools/seed-scenario-s01.php && php tools/seed-scenario-s02.php && php tools/seed-scenario-s03.php
php -S 127.0.0.1:8811 tools/smoke-router.php &
NODE_PATH=<node_modules> node tools/smoke.js        http://127.0.0.1:8811 admin admin12345
NODE_PATH=<node_modules> node tools/portal-crawl.js http://127.0.0.1:8811
```
</details>

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

203 screens across all personas (incl. DEMO-S04 marketplace features) — **all render cleanly**; the client Request form
submits cleanly end-to-end; test suite **5368 passed, 0 failed**.
