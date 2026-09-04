# Cold start — testing the first-run scenario (a brand-new company from scratch)

The demo seeds (S01–S06) inject data directly and the [auto-walk](auto-walk.md)
crawls an already-**seeded** database. Neither exercises what a *real new customer*
hits on day one: an **empty database → install → first company → first user → first
record**. This is what the cold-start harness tests.

## What already exists in the app (the real first-run path)

| Step | Where |
|---|---|
| **DB not configured yet** → a form to enter database settings | `/setup-db` (`setup_config_placeholder` / `setup_render_db_form` / `setup_handle_db_post`) |
| **Fresh install detected** (no real users / records / company name) | `setup_needed()` (`lib/setup.php`) |
| **First-run wizard** — admin password, company name, industry (sets wording + accreditation pack), FY / currency / date, DPDP contact | `/setup`, `/setup-save` (`ops_setup`) |
| **Company onboarding** — a company registers and declares its **capability mix** (multi-select, not one identity) | `/join` (`connect_org_join_route` → `connect_org_register` → `connect_org_cap_bulk_set`) |
| **Professional self-registration** — the marketplace passport | `/pro/register` (`connect_pro_register`) |
| **Client** — portal logins | `client_users` (created at company onboarding, or by staff) |

So the first-run stack is **already built**. What was missing was a **test** that
drives it from empty — which this adds.

## How to run it

```bash
cd phpapp && bash tools/cold-start.sh
```

It: (1) starts from a throwaway **empty** database; (2) confirms the public first-run
**screens** render for a stranger (`/login`, `/join`, `/pro/register`, `/pro/login`,
`/pro/forgot`); (3) drives the onboarding **logic** end-to-end and asserts each step.
Exit `0` = a brand-new company can land, onboard and start work from scratch.

The logic-only harness (no browser) is `php tools/cold-start.php` against a fresh
`SQLITE_PATH`. The onboarding **engines** are also guarded in the main suite by
`tests/test_onboarding_engines.php`.

## What it asserts (10 checks, all from empty)

1. A fresh database reports `setup_needed`.
2. The first-run wizard sets and brands the company name.
3. Setup is no longer flagged as needed once done.
4. A **multi-capability** company registers (TPIA + technical-manpower + freelance-supply at once).
5. The business party + organisation are created.
6. **All** selected capabilities persist — not single-select.
7. A working portal login is created for the company.
8. A technical professional self-registers on the passport.
9. The professional record exists.
10. The company posts its **first requirement** and it lands OPEN.

## Baseline (last run)

Cold start: **first-run screens render + 10/10 onboarding checks pass** from an empty
database. Nothing is seeded; the harness uses a throwaway `/tmp` database and never
touches real data or the default branch.
