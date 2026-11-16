# India time (T1) — fix batch for the test copy

16 Nov 2026 · You said **"T1 Yes"** and then **"Ok lets move to fix it to India time"**. This batch contains that one change. Nothing from the held v52/v53 is included.

**What this batch does NOT touch:** no money calculation, billing or contract rule, approval workflow, permission or lifecycle. No database change: no new table, no new column, and no stored record rewritten.

## The problem

Your opstest deploy-check page showed the server clock on **UTC**, 5 h 30 min behind India. The app never set its own time zone, so:
- every time it recorded or showed was **5½ hours behind** India time;
- the date turned over at **5:30 am** India time instead of midnight. Work done at 2:00 am was dated the day before. Due dates, ageing and month-ends near midnight could fall on the wrong day;
- the CERT-In "when it was noticed" time read 5½ hours early. The six-hour countdown itself was right, because it counts hours.

## The fix

The app now sets **India time (Asia/Kolkata)** itself, at the very start of every page, every nightly job and every test, whatever the server's own clock is. One small new file does this: `lib/timezone.php`. The database file (`lib/db.php`), which every part of the app loads first, now loads it.

I checked the code first: the app never asks the database for the time. Every time comes from the program, so this one setting covers all of it.

**The deploy-check page** now says which clock the app uses, for example:

> Time zone: the app uses India time (Asia/Kolkata): 16 Nov 2026, 21:27 · server clock 16 Nov 2026, 15:57 (UTC) · same as India time

The server clock may keep saying UTC. That is fine: what matters is "the app uses India time … same as India time".

## What you may notice

- New times show in India time, for example a record created at 9:27 pm shows 21:27, not 15:57.
- **Records made before the update keep the times they were made with.**
  - Most of them carry their own time zone mark, so they still show correctly.
  - A few older "plain" times read 5½ hours early.
  - Both sites hold test data only, so this does no harm. That is why now was the right moment.

## How it was tested

| Check | Result |
| --- | --- |
| New automatic test `tests/test_app_timezone.php` | **Old code: 6 failed.** **New code: 11 of 11 passed.** It checks: India time is set; records carry +05:30; 01:30 at night is dated the Indian day; the setting wins over a UTC server; a six-hour deadline stays six hours; older UTC records still read correctly; and the deploy-check page reports it |
| Robot, 20 date-related tests on real screens (Money, Operations, Quality / CERT-In incidents, Reporting, Directory, Recruitment, receivables), run **before and after** the change on identical copies | **Identical, step by step:** 9 passed, 4 failed, 5 "needs you" and 2 out of date, in both runs. **No screen got worse.** Of the 4 failures, MON-019 and MON-020 are known defects already in the Money pack. OPS-008 and OPS-052 need records that the Operations tester prepared before running them (a won quotation for TB Forge, deputation site records). Run on their own they fail the same way on the original V51.1 code too, so they say nothing about this change |
| Proof on the copy | The same action at the same moment was stored as **15:57 (UTC)** by the old code and as **21:27 India time** by the new code |
| Full automatic suite, SQLite | **20,261 passed, 2 failed.** Both failures were the deploy-check release stamp being out of date, which is expected before the stamp is regenerated for a release. After regenerating it, that test passes (14 of 14) |
| Full automatic suite, MariaDB | **No new failures compared with the security batch.** It stops at the same known test-harness crash as every earlier MariaDB run (`test_workspace_requests_visible.php`, a test-order problem in the tests, not the app). Before that point **20,251 checks passed** (security batch: 20,243). The 24 other failures are the same known MariaDB test-order failures as before, line for line. The only extras were the 2 out-of-date stamp lines |

## The update zip — for the TEST COPY only

`EXAACT-V51.1-IndiaTime-TEST-COPY-update.zip`
- **Contents:** 815 files, at the top of the zip, so extracting overwrites them in place.
- **Compared file by file with the previous test-copy zip (SecurityA1):** exactly **5 files** differ:
  - `lib/timezone.php` (new);
  - `lib/db.php`;
  - `tools/deploy_check_template.php`;
  - `deploy-check.php`;
  - `VERSION.txt`.
- **Left out:** no `tests/` folder, `config.local.php`, `tenants.php` registry, database file, or `exaact_data` / `exaact_backups` folder.
- **Release stamp:** `53f2093`.
- **SHA-256:** `a7b95ccb96f6b672289fec33d141581fe3cee9e8814775aa8f69659b37344882`.

It includes everything in the previous test-copy zip: fix batch 1, Sales batch B and the security fix.

**To install it on opstest (same as last time):**
1. Back up opstest: mPanel → Backups, and Admin → Backup & restore → Take a backup now.
2. File Manager → the opstest **app** folder (the one with `index.php` and `config.local.php`) → upload the zip → Extract → overwrite → delete the zip.
3. Open `https://opstest.mghaiapps.com/deploy-check.php`. You should see:
   - Release **53f2093**;
   - the green ✓ with **720** files;
   - the Time zone line saying **"the app uses India time (Asia/Kolkata) … same as India time"**.

   Please send me that screenshot.
4. Open the dashboard and check that the red TEST COPY bar is there. Make one small record, for example a note or an enquiry, and check its time shows India time.

**Not for the live site yet.** It goes live with the first live update, after you say testing is complete.

## Rollback

1. Restore the backup you took before uploading. That is always the safest way back.
2. Or upload the previous zip, `EXAACT-V51.1-SecurityA1-TEST-COPY-update.zip`, over it. Times then go back to UTC. Records written in India time in the meantime keep their +05:30 mark, so they stay correct.

No database change is made, so going back is only a matter of files.

## Every file changed

| File | In the zip? | Change |
| --- | --- | --- |
| `lib/timezone.php` | Yes (new) | Sets India time for the whole app |
| `lib/db.php` | Yes | Loads `lib/timezone.php` first (2 lines) |
| `tools/deploy_check_template.php` | Yes | The Time zone line shows the clock the app uses |
| `deploy-check.php` | Yes | Regenerated: new release stamp and the new Time zone line |
| `VERSION.txt` | Yes | Build note |
| `tests/test_app_timezone.php` | **No** (tests never go in the zip) | New automatic test |
| `docs/go-live-guide.md` | No (documentation) | Explains the new Time zone line |
