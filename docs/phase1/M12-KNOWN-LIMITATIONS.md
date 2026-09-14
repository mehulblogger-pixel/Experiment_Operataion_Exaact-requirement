# Milestone 12 — Known Limitations

---

## L1 · The lock screen is reached from the route gate, not from every refusal

`ops_module_gate()` has two refusal points and both now reach the lock screen. But
`ops_require()` is called **hundreds** of times inside individual handlers for
finer-grained refusals — "you cannot close this voucher", "only Accounts can
register a contract" — and those still flash-and-redirect.

That is deliberate. Those refusals are about a **specific action on a specific
record**, not about module availability, and the flash-on-the-page pattern is the
right one for them: the user keeps their place. Converting them wholesale would
be the several-hundred-screen rewrite §30 forbids.

**What this means in practice:** a user who reaches a locked *module* gets the new
screen; a user refused a specific *action inside a module they can open* still
gets a toast. Those are different situations and it is defensible that they look
different — but it is not the single experience §16 describes, and it is recorded
as such rather than glossed over.

---

## L2 · Unavailable modules stay hidden from the rail, not shown as locked

§5 offered four options (hidden / locked / upgrade CTA / disabled) and asked for
one consistent product-wide rule, while allowing an established pattern to win.

M12 keeps **hidden**, which is what M11 established, and makes the **lock screen
the consistent destination** for anyone who arrives by a typed address, an old
bookmark, a link in a document or an e-mail.

The commercial argument for showing "Recruitment 🔒" in an administrator's rail is
real — it is how a customer discovers what they could buy. It was **not** done
because it is a navigation change that M11 has just settled, and doing it properly
means deciding whether ordinary users see it too (clutter and confusion) or only
administrators (a role-dependent rail). **That is a product decision, not mine**,
and it is a small change once taken: `ops_area_has()` already knows the answer.

---

## L3 · The dashboard hides unentitled panels rather than showing locked cards

§15 asked that a card must not imply "you can use this". The audit confirmed the
dashboard already **hides** HR, Sales and Money panels when unentitled (the work
M6 and M10 did), which satisfies that.

It does **not** show a locked card in their place. Same reasoning as L2 — and the
dashboard is explicitly protected from rebuilding by §15 and §30.

---

## L4 · JSON detection is header-based

`access_wants_json()` reads `X-Requested-With` and `Accept`. A client that sends
neither gets the HTML lock screen with a 403, which is a correct and safe answer
but not the structured one.

The application has no established AJAX convention to key off — this is the first
structured access response in it — so the two standard headers were used rather
than inventing a marker. If a house convention is adopted later, one line changes.

---

## L5 · `LICENCE_BLOCKED` and `TENANT_DISABLED` are tested, not yet seen in the wild

Both states are produced and asserted in the suite (an unverifiable licence key,
and a company's own `modules_off`). Neither has been observed on the live server,
because M5–M12 have not been deployed there. The wording for both should be
reviewed by someone who has handled a real lapsed-licence support call.

---

## L6 · Accessibility and responsiveness were inherited, not independently audited

The lock screen uses the existing `panel` / `btn` styles, a real `<h1>`, a real
`<a>` for its action, and carries its state in **words** as well as an icon — so
it is not colour-dependent, and it is keyboard-reachable with the application's
normal focus ring.

What was **not** done is an independent audit — screen-reader testing, contrast
measurement, or checking the screen at specific viewport widths on real devices.
It inherits whatever the existing panel style provides. Saying more than that
would be a claim I have not earned.

---

## L7 · No upgrade path exists for a workspace that cannot self-serve

`/subscription` is offered only to someone `billing_can_manage()` allows, and it
is meaningful only for a metered cloud workspace. A self-hosted company, or one
managed directly by a provider, sees "Contact your provider" — which is correct,
because there is nothing for them to buy in-app.

**No new billing capability was built** (§11, §30). If a "request this module"
workflow is wanted — a message to the provider rather than a card payment — that
is a new capability and needs its own decision.

---

## L8 · MySQL/MariaDB was not executed

Verified, not assumed. No claim of validation. M12 changes no schema and no data.

---

## L9 · Not yet verified on the live server

M5–M12 have not been uploaded. **M9's L1 still applies first:** hosted workspaces
need `connect` added to their entitlement, or the marketplace switches off for
them — and after M12 those users will now meet a lock screen saying exactly that,
which makes getting L1 right before deployment more visible, not less important.
