# Milestone 8 — Known Limitations

Stated openly. None is a regression.

---

## L1 · Cron gating is per step, and the step list is hand-classified

Each of the 28 paid steps in `cron.php` names the access module its own library
belongs to. A **new** step added later, and not given a gate, would run for every
workspace — exactly the condition M8 closed.

The M8 test reads `cron.php` and fails if any of the currently gated steps loses
its guard, so regression is covered. What is **not** covered is a brand-new
ungated step. A build-time check that every call in the run is either gated or on
a named core list would close that; it was not in M8's scope.

---

## L2 · A skipped step is silent to the workspace, visible to the operator

When a module is not enabled, its nightly work simply does not run and the module
is named in the run's output. Nothing is written to the workspace's own audit
trail to say "this did not happen".

Deliberate — writing an audit row per skipped step, per night, per workspace
would be noise. But it does mean a customer cannot see from inside the
application why a reminder stopped arriving. Worth a line in the module settings
screen at some point; it is a UX change, not an enforcement one.

---

## L3 · The outbound webhook queue is dormant, not enforced

`lib/webhookq.php` implements an outbound delivery queue — enqueue, claim,
deliver, dispatch, cron. **It has no callers anywhere in the repository.** Nothing
enqueues to it and nothing runs it.

So there is nothing to enforce today, and adding a gate to dead code would be
guesswork about how it will eventually be used. **If it is ever wired up, each
channel will need its module established at the point events are enqueued** —
recorded here so that is not discovered later.

---

## L4 · `careers_apply()` checks entitlement, not the Careers opt-in

The hardening inside `careers_apply()` asks only whether the company has People &
hiring. The company's own Careers switch is enforced at `careers_route()`, the
only caller.

This was narrowed deliberately during implementation: requiring the opt-in inside
the intake function changed what the intake does for a fully entitled company,
which is beyond what this milestone is for, and §5 frames the Careers switch as a
feature choice rather than a security boundary. The practical consequence is nil
today — the route refuses first — but a future caller reaching
`careers_apply()` directly would be stopped by entitlement, not by the opt-in.

---

## L5 · Background work is not resumed when a module is re-enabled

If a workspace re-subscribes, the steps simply start running again on the next
nightly run. Most are naturally self-healing — the backfills and sweeps are
written as backstops and will catch up. **Time-window work will not**: a
follow-up e-mail whose window passed while the module was off is not sent
retrospectively.

Correct behaviour, but worth stating: a lapse is not replayed when it ends.

---

## L6 · Marketplace and Connect are untouched — the M9 boundary

`/pro`, `/join`, `/connect` and the public passport route `/p/<token>` are
Marketplace / Connect. They are governed by their own `connect_enabled()` switch
and are **not** product modules in the registry.

No Marketplace functionality was found running inside a gated cron step or a
public careers path. Recorded and deferred to M9, not silently expanded into M8.

---

## L7 · Public signup remains core

`get-started` creates a PENDING workspace application for operator approval; it
is off by default. Creating a SaaS account is not paid-module functionality and
was not gated (§17). Once such a workspace exists and tries to **use** a paid
module, the normal entitlement rules apply — that is M5–M8, not this route.

---

## L8 · MySQL/MariaDB was not executed

The authoritative production database was not available — verified, not assumed.
No claim of MySQL validation is made. See `M8-TEST-RESULTS.md` §2.

---

## L9 · Not yet verified on the live server

M5 through M8 have not been uploaded to `operations.mghaiapps.com`.
`deploy-check.php` — regenerated in this milestone — is the instrument that
confirms the uploaded files match this code. Note that `cron.php` and
`cron_ads.php` sit **outside** the code fingerprint deploy-check watches
(`lib/*.php`, `index.php`, `config.php`), so their upload must be confirmed by
running them once and reading the output.
