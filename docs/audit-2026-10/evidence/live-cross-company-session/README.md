# Live check — a session from one company presented at another (19 Oct 2026, GAP-049)

Two companies on throwaway SQLite copies, in the platform's own subdomain mode:
a `tenants.php` registry with base domain `exaact.test` and two workspaces,
**acme** and **beta**, each with its own database and one client-portal user —
both happen to be row 1, which is the ordinary case, not a contrivance. BEFORE
and AFTER were separate git worktrees (`67b1dbf`, and `67b1dbf` + the fix), so
the main working tree was never modified.

`replay.sh` signs in as Acme's client at `acme.exaact.test`, takes **that
session id**, and sends it to `beta.exaact.test`. No password for Beta is used.

| Copy | Acme's session at Acme | The same session at **Beta** |
|---|---|---|
| BEFORE | signed in as *Acme Buyer* | **signed in as *Beta Buyer*; Beta's confidential client on the page** |
| AFTER | signed in as *Acme Buyer* | not signed in — the sign-in page; no Beta data |
| AFTER (control) | — | Beta's own client signs in at Beta normally |

**A note on the first run, kept because it nearly hid the finding:** the first
script let curl manage cookies while setting the `Host` header by hand. curl
then filed the cookie under the Acme name and never sent it to Beta, so the
attack looked blocked on BEFORE too. Sending the session id explicitly showed
the truth. A browser enforces that split; an attacker using a script does not.
