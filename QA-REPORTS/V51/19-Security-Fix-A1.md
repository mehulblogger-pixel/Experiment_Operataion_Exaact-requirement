# Security fix A1 (V51.1) — Messages inbox (DEF-MKT-016)

16 Nov 2026 · You said **"A1 Yes fix it now."** This batch contains that one fix, a permanent automatic test for it, and a "Time zone" line on the deploy-check page. Nothing from the held v52/v53 is included.

**What this batch does NOT touch:** no money calculation, billing or contract rule, approval workflow, permission or lifecycle. No database change (no new table, no new column).

## The fix

| | Before | After |
| --- | --- | --- |
| Staff Messages inbox (`/connect-messages`) | The first 60 characters of each conversation's last message were put on the page **as code**. A professional who sent `<img src=x onerror=alert(…)>` made it run in the Coordinator's browser as soon as Messages was opened | The text is shown **as text**. The same message now appears literally as `<img src=x onerror=alert(…)>` and nothing runs |

**Where:** one line, `views/ops/connect_messages.php` line 51. The safety function that makes text harmless (`e()`) was closed before the message was added. It now wraps the whole preview.

## Was the same mistake made anywhere else?

| Check | Result |
| --- | --- |
| Every screen in the app (all view files) scanned for this exact pattern: a made-safe piece of text followed by a raw one | **Only this line.** The one other match prints a currency symbol and a number, which is harmless |
| All 82 Marketplace, professional, vendor, public and client-portal screens: every place they print something, reviewed for raw text that a user typed | **None found.** Each place either makes the text safe or prints fixed wording, numbers or colours |
| Every place a marketplace message is shown (staff desk, professional's inbox and conversation) | The professional's side was already safe. The staff conversation pane was already safe. Only the staff inbox preview was not |

**What is NOT done yet:** across the whole app there are about 5,000 places that print something that is not obviously made safe. Most are fixed wording, numbers or colours. Reviewing all of them by hand is a larger security review, which I recommend as its own piece of work. The Client & Vendor Portal tester running now also probes every place where a client's text reaches staff.

**Noticed while checking (not yet seen on screen):** the professional's inbox **preview** shows the last message unmasked, while the open conversation hides phone numbers and e-mail addresses until the award (Marketplace C14). Recorded for the Marketplace batch; nothing was changed.

## The time-zone line on `/deploy-check.php`

The page now shows, under the Release line:

> Time zone: server clock 16 Nov 2026, 01:28 (UTC) · India time 16 Nov 2026, 06:58 · differs from India time by 5 h 30 min

On a server already set to India time it says "same as India time". It only reads the clock; it changes nothing. It answers Reporting **C18** and Quality **C14** once the page is on your sites. `docs/go-live-guide.md` now mentions it.

## How it was tested

| Check | Result |
| --- | --- |
| New automatic test `tests/test_connect_messages_escaping.php` | **On the old code: 1 passed, 5 failed** (it catches the hole). **On the fixed code: 6 passed, 0 failed** |
| The original attack, repeated in a browser on a private copy with made-up data | **Old V51.1 code:** a pop-up appeared in the Coordinator's inbox ("127.0.0.1") and a live `<img onerror>` element was in the page. **Fixed code:** no pop-up, no live element; the message shows as plain text |
| Robot: the 12 Marketplace tests that use Messages (MKT-006, 073–079, 081, 083, 114, SCR-connect-messages) | **No test got worse.** 11 of the 12 give exactly their V51.1 result, step by step: 9 pass, 2 "needs you" (real WhatsApp provider; contact masking is your question, Marketplace C14), and MKT-114 still has the known DEF-MKT-019 step. **MKT-076, which caught the hole, failed on V51.1 and passes now** |
| Full automatic suite, SQLite | SUITE_SQLITE |
| Full automatic suite, MariaDB | SUITE_MARIADB |

**A setup slip of mine, recorded for honesty:** on the first robot run, two tests could not sign in as admin. The cause was my private copy: I had left out the robot's own settings file, so the app set the admin password from its default. This is the app's intended way of recovering the admin login. With the settings file restored, both tests ran normally. It has nothing to do with the fix, and the update zip never contains a settings file.

## The update zip — for the TEST COPY only

ZIP_DETAILS

**What is in it:** everything in the previous test-copy zip (fix batch 1 and Sales batch B) **plus** this security fix and the time-zone line. If you have not installed the Sales batch B zip yet, this one includes it.

**To try it on the test copy (opstest):**
1. Back up the opstest app folder and its data first.
2. Upload the zip into the opstest **app** folder.
3. Extract it, overwriting the existing files.
4. Open `/deploy-check.php`. It should show code COMMIT, the green tick, the red TEST COPY bar and the new **Time zone** line. **Please send me that screenshot**: it answers the time-zone question for opstest.
5. Optional check: as Coordinator open **Marketplace → Messages**. It works as before.

**Not for the live site yet.** This is a security fix, so I recommend it goes live soon after you have checked it on the test copy. When you are happy, tell me and I will ask "Shall I prepare the live update?"

## Rollback

1. Restore the backup you took before uploading. That is always the safest way back.
2. Or upload the previous zip, `EXAACT-V51.1-SalesB-TEST-COPY-update.zip`, over it in the same way. That brings back the security hole, so use it only if something else goes wrong.

No database change is made, so going back is only a matter of files.

## Every file changed

| File | In the zip? | Change |
| --- | --- | --- |
| `views/ops/connect_messages.php` | Yes | The fix (one line) |
| `deploy-check.php` | Yes | Regenerated: new release stamp, and the Time zone line |
| `tools/deploy_check_template.php` | Yes | The Time zone line (the page is built from this) |
| `tests/test_connect_messages_escaping.php` | **No** (tests never go in the zip) | New automatic test |
| `docs/go-live-guide.md` | No (documentation) | Mentions the Time zone line |
