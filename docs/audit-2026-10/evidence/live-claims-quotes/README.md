# Live check — expense claims and quotations (19 Oct 2026)

Run against **throwaway SQLite copies only**, never the real database. The
BEFORE copy was a separate git worktree of commit `281b56c`, so the main
working tree was never modified.

```
php seed.php                       # with SQLITE_PATH=<copy> DB_DRIVER=sqlite — admin, engineer, a draft claim sheet
php -S 127.0.0.1:8861 tools/smoke-router.php   # BEFORE copy
php -S 127.0.0.1:8862 tools/smoke-router.php   # AFTER copy
./drive.sh  http://127.0.0.1:8862 after.sqlite AFTER -5 12
./quote.sh  http://127.0.0.1:8862 after.sqlite AFTER "probe: quantity −3" 18 -3 5000 0 0
```

The seed marks setup done through the app (`setup_mark_done()`); without it
every page redirects to `/setup`. The seeded password is the demo one used by
the other seed scenarios.

## Result as run

Daily cap: 8.5 hours.

| Copy | Claim sheet, same day | Stored | Screen says |
|---|---|---|---|
| BEFORE | −5 h + 12 h | **−5 + 12 = 7** (a 12-hour row on a capped day) | nothing |
| AFTER | −5 h + 12 h | unchanged | *Hours cannot be negative. Nothing was saved — correct the row and save again.* |
| AFTER | "abc" + 1 | unchanged | *Hours must be a number. …* |
| AFTER | 4 h + 4 h (control) | **4 + 4 = 8** | *Voucher saved.* |
| AFTER | 6 h + 6 h (control) | unchanged | the daily-cap message, as before |
| AFTER | km −40 | unchanged | *KM cannot be negative. …* |

| Copy | Quotation | Stored |
|---|---|---|
| BEFORE | 2 × 5,000 @ 18% (control) | 10,000 → 11,800 |
| BEFORE | with a −1,000 discount line (control) | 9,000 → 10,620 |
| BEFORE | quantity −3 | **−15,000 → −17,700** |
| BEFORE | GST 500% | **10,000 → 60,000** |
| BEFORE | discount bigger than the work | **−4,000 → −4,720** |
| AFTER | the two controls | saved, same figures |
| AFTER | the three probes | not saved, each with a message naming the field |

The server log showed no PHP warning or error on the AFTER copy.
