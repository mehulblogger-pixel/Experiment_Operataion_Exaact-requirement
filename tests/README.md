# Tests — the automated safety-net

Zero-dependency PHP tests (no Composer, no PHPUnit). They boot the **real
application** against a **throwaway SQLite database** in the system temp folder,
so they never touch a live install and never need a server.

## Run them

```
php tests/run.php
```

Exit code is `0` when everything passes, non-zero on any failure. A clean run
means the whole app booted, every migration ran, and the checks below held.

## What is covered so far

- **boot & schema** — the full app boots on an empty database, all tables and
  indexes are created, an admin is seeded, and a second boot is a safe no-op.
- **audit trail tamper-evidence** — sealing detects an edited row and a deleted
  row (Roadmap Step 8).
- **geolocation** — coordinate/maps-link parsing and haversine distance.
- **indexing** — the Step 1 indexes exist and the query planner uses them.

## Adding a test

Drop a `tests/test_<name>.php` file. It runs after the app is already booted, so
it can call any application function and use `db()`, `ops_all()`, `ops_one()`,
`ops_val()` directly. Assertions: `t_ok($cond, $msg)`, `t_eq($got, $want, $msg)`,
`t_nothrow($msg, fn)`, grouped with `t_section('name')`.

**Rule going forward:** when a roadmap step is built, add a test here for it.

## Full browser end-to-end (every screen renders)

`php tests/run.php` is fast but does not render pages. For a true end-to-end
pass — log in and open **every** screen in the app, following the first row of
each register into its detail/edit screens, failing on any fatal, PHP warning or
JS error — use the Playwright crawl in `tools/smoke.js` against a throwaway copy
of the database:

```
cp data.sqlite /tmp/smoke.sqlite                       # throwaway — never the real DB
SQLITE_PATH=/tmp/smoke.sqlite DB_DRIVER=sqlite \
  php -S 127.0.0.1:8801 tools/smoke-router.php &        # dev server
node tools/smoke.js http://127.0.0.1:8801 admin admin12345
```

It discovers the whole sidebar at run time, so new modules are picked up
automatically; the fixed lists in `smoke.js` additionally name each register's
list, create form, detail and edit screens so they are always crawled. Last run:
**195 screens, all clean.**

