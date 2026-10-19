# Live check — plan changes and Finance on clients (19 Oct 2026, Q-L and Q-K)

Throwaway SQLite copies only; BEFORE was a separate worktree of `5345481`, so
the main working tree was never modified. `seed.php` adds a Finance login, a
client with a portal user on **Client · Pro** (monthly, started five days ago),
marks setup done and the marketplace add-on on. The client portal itself is off
by default (`portal_enabled`), so it was switched on in both copies before the
run — the first run answered 404 for that reason alone. No payment keys were
set, so a priced change cannot be paid for on either copy.

| Copy | Check | Result |
|---|---|---|
| BEFORE | Finance opens `/clients` | 302 — refused |
| AFTER | Finance opens `/clients` | **200** |
| BEFORE / AFTER | Finance opens `/partner-new` (add a client) | 302 — still refused: read only |
| BEFORE | The Starter card on the client's plans page | no explanation |
| AFTER | The Starter card | *"Monthly — Downgrade: Starts on 2026-11-14, when your paid period ends. Nothing is charged now; you pay 999.00 for it then."* |
| BEFORE | Choose Starter | nothing happened (a priced item with no keys is refused, GAP-037) |
| AFTER | Choose Starter | *"Your plan changes to Client · Starter on 2026-11-14."* Pro stays **ACTIVE** to 2026-11-13; the change is recorded on it |

The upgrade, extra-days, free-plan, renew and paid-checkout cases are covered by
`phpapp/tests/test_mkt_plan_change.php` (33 assertions) rather than here, because
they need payment keys and a stubbed gateway. No PHP warning in the AFTER log.
