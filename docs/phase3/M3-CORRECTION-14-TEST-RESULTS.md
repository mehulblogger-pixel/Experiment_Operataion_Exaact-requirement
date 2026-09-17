# M3 CORRECTION #14 — TEST RESULTS

Suite: `phpapp/tests/test_p3m3c14_chain.php` — **55 assertions**, every one made
through the real production path. The decisive ones are made on **business
behaviour** (does a duplicate appear, is a human told), not on a status value.

| run | engine | result |
|---|---|---|
| focused §8 chain suite | SQLite 3.45.1 | **55 passed, 0 failed** |
| focused §8 chain suite | MariaDB 10.11.14 | **55 passed, 0 failed** |
| **full regression** | SQLite | **10383 passed, 0 failed** |
| **full regression** | MariaDB | **10384 passed, 0 failed** |

No test was weakened, deleted or skipped; no skip was introduced.

## Engine differences

**None observed for any Correction #14 behaviour.** Both engines produce the same
status, the same bounded row count and the same rendered screen. The failure
fixtures are engine-specific by necessity — `RAISE(ABORT)` on SQLite,
`SIGNAL SQLSTATE '45000'` on MariaDB — but they force the same real failure, and
the assertion that the fixture actually failed is made before any claim rests on
it. The one-assertion difference in the full-run totals is a pre-existing
MariaDB-only assertion elsewhere in the suite, not a #14 divergence.

---

## TEST A — successful marker (C14.A)
Event recorded, marker stored, the production caller receives `RECORDED`, nothing
is flagged for attention, and **ten identical refusals produce one entry**.

## TEST B — marker write failure (C14.B)
A genuine `BEFORE UPDATE` failure. Asserted in order: the marker write really
failed; **a row really exists** (`act_last_cond_row() > 0`); the caller receives
`UNARMED`; it is neither `RECORDED` nor `SUPPRESSED`; exactly one event exists.

## TEST C — core event failure (C14.C) · B-3
A genuine `BEFORE INSERT` failure. **No event row exists**, the status carries a
row id of **zero**, and the caller returns **`NOT_RECORDED`** — explicitly
asserted *not* to be `UNARMED`, which is what #13 returned here and whose own
definition claims a row exists.

## TEST D — 30 scheduler ticks during permanent failure (C14.D) · B-4 / B-5
- **30 ticks, ONE entry.** The loop is bounded.
- tick 1 → `UNARMED` (writes the row); ticks 2–30 → `PENDING_RETRY`, all identical.
- not one tick claimed `SUPPRESSED`; not one claimed `RECORDED`.
- **29 of the 30 ticks are the silent state**, so the diagnostic cannot stream.

## B-2 — and a human is told (C14.D2)
`appr_sla_summary()` — the summary the Recruitment Command Centre **already
renders** — carries `suppression_unarmed`, and the screen is **rendered twice** to
prove it: with unresolved conditions it states the problem in business words,
names what is affected, shows the count and says no approval decision is
affected; with none it shows no warning at all. The rendered HTML is asserted to
contain no `SQLSTATE`, `cond_key`, `PCX|`, table name, password or filesystem path.

## TEST E — recovery (C14.E)
The retry **arms the row that already existed**, the caller receives `RECOVERED`,
no new row was needed, and the next identical refusal is `SUPPRESSED` — ordinary
suppression has resumed unaided.

## B-1 — the result reaches the real decision maker (C14.F)
Both scheduler sites capture the outcome, the decision notifier consumes it at all
three of its sites, and **the cron run itself reports** what it could not arm, in
words an operator can act on.

## §11 — real tenant isolation (C14.G)
Switching goes through `db(true)`; `__db_epoch` is never assigned by hand; the
database names itself before any claim. With tenant A genuinely holding an
unarmed condition: **DB A ≠ DB B**, and B's count, B's approval summary, the
marker status, the row id, the notifier's outcome and the scheduler's count are
all clean in B. A is unchanged by B's visit.

---

## A test defect of my own, found by mutation and reported

`M14-9` removes the business message from the screen's markup. **It survived the
first battery.** The assertion searched the view's *source* for the message text —
and the same words appear in the explanatory **comment** directly above the markup,
so the assertion matched a comment and reported a screen.

This is both a test passing for the wrong reason and a source assertion where a
behavioural one was available. C14.D2 now **renders the view** and asserts against
the HTML a user would actually receive. `M14-9` is caught.
