# Phase 6 · Batch 3 — Security results

The order every organisation path must obey:

**ENTITLEMENT → PERMISSION → TENANT → SCOPE → STATE → RELATIONSHIP OWNERSHIP →
DUPLICATE → WRITE → AUDIT**

Authorisation comes **before** any refusal that would reveal information. A
refusal that only an authorised actor could have provoked is safe; the same
refusal handed to a stranger is an answer to a question they were not entitled
to ask.

---

## 1 · The public route — the one door with no actor behind it

`/join` (`connect_org_register()`) is unauthenticated. Before Batch 3 it created
a business partner, a marketplace organisation and a portal login with **no
duplicate check at all**: anyone who knew a customer's name could put a second
record of that customer into the workspace, with an account attached.

| Attack | Result | Evidence |
|---|---|---|
| Register an existing company under its own name | Refused, nothing written | A7 · A8 · A9 |
| Register under a **different** name but the customer's GSTIN | Refused, nothing written, **no account either** | A3 · A4 · A5 |
| Read the refusal for the customer's name, code, GSTIN, PAN or internal id | Discloses none of them | A6 (four probes) |
| Tell EXACT from POSSIBLE by the wording | **Byte-identical sentence** | M1 · M2 |
| Be blocked while genuinely being a different company with a similar name | Registers normally | A10 |
| Award itself the vendor or sub-contractor role through extra form fields | Ignored | M4 · M5 |
| Award itself a status, permissions, or attach to an organisation it named | Ignored; the account belongs to the organisation actually created | M6 · M7 · M8 |
| Inject SQL through the organisation name | Stored as data; every statement is prepared | M9 · M10 |
| Win a race against itself — three simultaneous registrations | One partner, one organisation, one account, one success, **no crash** | B1–B6 |

The refusal is a **claim path**, not an accusation and not an automatic claim:
it invites the visitor to ask their account contact, or to contact us. No
organisation is created, no ownership is transferred, and no approval engine was
built. (**Q21**)

## 2 · Function-level authorisation (invariant I27)

`portal_invite()` creates a portal account. It now asks its **own** authority
rather than trusting the screen that called it, and it recognises the **two**
authorities that legitimately exist:

* **staff** — the established `portal_can_manage()` gate for the portal
  register. No new permission was introduced, and nothing was granted to any
  role that `docs/02-permission-matrix.md` does not already give it.
* **a client's own admin** — and only into **their own** organisation, checked at
  the function from the signed-in portal user, not from the posted id.

| Attack | Result | Evidence |
|---|---|---|
| An actor with no such right invites a portal account | Refused, **and no account exists** | K1 · K2 |
| A posted organisation id that does not exist | Refused, nothing written | K3 · K4 |
| A client admin invites into **another company** | Refused (the posted id is a request, not a permission) | mutation **M18** |

The target check runs **after** the authority check, and the two refusals use
the **same words**, so the reply cannot be used to find out which organisations
exist.

## 3 · The account boundary (Q23)

Established from the code, not assumed: both login routes resolve
`LOWER(email) = ? AND is_active = 1` over their own table and take the first
row. So a second **active** account on one address in one account list is a
sign-in ambiguity — a person could land in the wrong organisation. That, and
nothing wider, is what the database now refuses.

| Attack | Result | Evidence |
|---|---|---|
| Invite a second active account for an address that already has one | Refused with a business sentence, not a crash | H1 |
| Bypass the application and insert the row directly | **Refused by the database** — the key is a generated column no writer can supply or forget | H2 |
| Hold a client account and a vendor account as one person | Allowed — separate lists, separate logins | H4 |
| Re-use an address after the old account is withdrawn | Allowed — the slot is released on deactivation | H3 |

**Not** imposed: global e-mail uniqueness, cross-table uniqueness, or any
person-level identity rule. The owner's instruction on this was explicit and is
respected.

## 4 · Data that is not ours to rewrite

Detection reports; it never repairs. Nothing in this batch deletes a duplicate,
merges an organisation or a contact, changes an invoice, a recruitment record or
an agency contract, or back-fills a mapping. A workspace that already holds two
active accounts on one address **keeps them**: that one index is skipped and the
state is reported. (J3 · J4 · §NO HISTORICAL CLEANUP)

## 5 · Residual risks, stated plainly

1. **The e-mail-in-use message on `/join` remains an account oracle.** "That
   e-mail is already registered — sign in instead." tells a stranger that an
   address has an account. This is **pre-existing behaviour**, unchanged by this
   batch, and it is the standard trade-off against telling a real returning
   customer nothing useful. Worth an owner decision; not changed unilaterally.
2. **No rate limiting on the public route.** Out of scope for Batch 3 and
   unchanged by it. The duplicate protection means a flood cannot create shadow
   organisations, but it can still create *new* ones. Recommended for a later
   batch.
3. **The agency cross-reference is validated at the screen, not by a foreign
   key.** A direct database writer could still leave a dangling reference; the
   report now names that state (`MARKETPLACE_PARTY_MISSING`,
   `ACCOUNT_ORGANISATION_MISSING`). A constraint would need the historical data
   to be clean first, which is exactly what this batch is forbidden to force.
