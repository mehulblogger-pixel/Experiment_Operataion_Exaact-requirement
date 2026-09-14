# Milestone 13 — Known Limitations

Recorded deliberately. Nothing here is unknown, and nothing here is a critical or
high-risk open issue.

---

## L1 — Sessions created before this deployment carry no workspace binding

**Severity: Low — bounded and self-closing.**

A person already signed in when the fix deploys has a session with no workspace
stamp. Those sessions are deliberately **not** invalidated: forcibly signing
everybody out mid-day is real operational harm, and the exposure it would buy is
already closed elsewhere.

Both reachable paths to a workspace switch are closed at their own sites — the
reset page will not move a signed-in session, and a failed login steps back out.
So an unbound legacy session cannot be moved into another workspace to begin
with; the binding is the *second* line of defence, not the only one.

Every sign-in, two-factor completion and single sign-on stamps the binding, so
the gap closes as sessions turn over.

**If a forced sign-out is preferred**, the session cookie name or the session
save path can be rotated at deploy time. That is an operational decision, not a
code change, and it belongs to whoever runs the deployment.

---

## L2 — Object-level scope checks are not exhaustive

**Severity: Medium — named, measured, not swept up.**

The audit counted **72** list-level scope clauses against **9** object-level
checks. Object-level checks cover the highest-value records: the job detail, the
report (three routes), the endorsement file, the invoice (two routes), the
check-in photo, and — added in M13 — the call detail.

Every remaining fetch-by-id in Operations was **not** individually audited. Doing
that properly means reading each route, establishing whether the object is
scope-bearing, and testing it with two offices — real work, and more than M13's
brief of "break it, then fix what broke".

It is stated as an open surface rather than implied to be finished. **A candidate
for M14.**

---

## L3 — MySQL / MariaDB was not exercised

**Severity: Informational.**

See the test matrix. There is no MySQL server in this container. No MySQL result
is claimed. The M13 changes introduce no SQL.

---

## L4 — The audit is source-level, not a live penetration test

**Severity: Informational.**

Attacks were executed against the real application booted in-process, with real
sessions, real databases and real dispatch — not simulated. But there was no
live HTTP target, so nothing was tested at the transport layer: TLS
configuration, HTTP security headers, cookie flags as set by the production web
server, or hosting-level controls. Those belong to the mPanel deployment, not to
the application source.

---

## L5 — Rate limiting covers sign-in, not every endpoint

**Severity: Low.**

Sign-in is rate-limited with a lockout. Other public endpoints (the reset
request, the careers application) rely on CSRF and neutral replies rather than
per-IP throttling. No abuse was demonstrated, and no change was made on
suspicion. Worth revisiting if abuse is observed.
