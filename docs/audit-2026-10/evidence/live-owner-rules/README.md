# Live check — the owner's rules of 19 Oct 2026 (Q-B1, Q-G)

Throwaway SQLite copies only. BEFORE was a separate git worktree of `af0e9b8`;
the main working tree was never modified. `seed.php` (run with `APPROOT`,
`SQLITE_PATH`, `DB_DRIVER=sqlite`) adds a coordinator, an inspector and a
finance login and marks setup done; `role.sh` signs in, opens the register and
the form, records a nonconformity, then tries to record containment on it.

HTTP 200 opens, 403 refuses, 302 sends elsewhere.

| Copy | Login | Register | Form | Recorded | Then containment | Vendors |
|---|---|---|---|---|---|---|
| BEFORE | Coordinator | 403 | 403 | no | — | 200 |
| AFTER | Coordinator | **200** | **200** | **yes** | refused, status stays OPEN | 200 |
| BEFORE | Inspector | 403 | 403 | no | — | 302 |
| AFTER | Inspector | 403 | 403 | no | refused, status stays OPEN | 302 |
| BEFORE | Finance | 403 | 403 | no | — | 302 |
| AFTER | Finance | 403 | 403 | no | refused | **200** |
| AFTER | Quality Head (control) | 200 | 200 | yes | **CONTAINED** | 302 |

The Quality Head row proves the containment request itself works, so the
coordinator's refusal is the permission and not a broken request.

Menus read from the same server: Finance sees **Directory → Vendor register**
and **Operations → Vendors**. The Quality area's *Nonconformity workspace* tile
appears only when the inspection pack is on (it was off in this blank copy);
it is gated on `mod.ncr.view`, which every recorder now holds.
