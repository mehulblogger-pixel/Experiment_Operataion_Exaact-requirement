# Licensing & billing — the vendor guide (for Us)

> How we turn a sale into a working installation: what a licence is, how to issue
> a key (including for a specific role mix), how money is handled, and how a
> customer installs on their own server. Read this before you issue your first key.

Companion to [`11-licensing-for-customer.md`](11-licensing-for-customer.md) (hand that
one to the customer).

---

## 1 · The model in one paragraph

The product is **one application, licensed by module and by seats**. A customer runs
their own copy (self-hosted) or we host it. What they may use is decided by a **signed
licence key** we generate: it names the customer, when the subscription **expires**,
how many **people (seats)** may sign in, and which **modules** are switched on. The
key is signed with a **private key only we hold**; every copy of the app ships the
matching **public key** and can *check* a key but never *make* one. That is what stops
a self-hosted customer giving themselves free seats.

**No phone-home.** Verification is pure maths, so an install on a factory network with
no internet works exactly like one on a cloud server.

---

## 2 · The six modules

A key switches these on or off (`lib/licence.php`):

| Module | What it contains | Can be sold alone? |
|---|---|---|
| **Operations** | Work orders, jobs, scheduling, availability | Yes |
| **Administration** | Masters, users, offices, settings, parties | **Always on (core)** |
| **Sales & CRM** | Leads, pipelines, enquiries, quotations, approvals | Yes |
| **Inspection reporting** | The report engine, formats, endorsements, evidence | Yes |
| **Money** | Invoicing, profitability, credits, cost run | Yes |
| **People & hiring** | Requisitions, candidates, placement | Yes |

**Administration is always included** — an inspection system needs masters, users and
settings to exist at all. A module that isn't bought isn't merely hidden: its screens
refuse to open and even an administrator can't reach them.

---

## 3 · The licence states (what the customer's banner says)

| State | Meaning |
|---|---|
| **Not licensed — everything is switched on** | No enforcement (our dev machines, and any copy before its first key). |
| **Trial** | Enforcement on, no key yet: a full-featured **14-day** trial from first boot. |
| **Licensed** | A valid signed key is in force. |
| **Expired — still working (grace)** | Past expiry but inside the grace window (default **14 days**): still fully writable. |
| **Expired — read only** | Grace is over. **Every screen, export and PDF still works; only new records are refused.** A customer is never locked out of their own data. |
| **Licence key not accepted** | The key was altered or isn't ours → read-only, with the reason shown. |

---

## 4 · One-time setup on the licence server

Issuing keys happens **only on our licence server** (the machine holding the private
key), signed in as the **Master Admin**, on the main site (not inside a tenant
workspace).

1. Go to **Licence console** (`/issue-licence`) — or the Super Admin hub (`/vendor`).
2. If it says *"Set up signing first"*, click **Set up signing (one click)**. The app
   generates the key pair, keeps the private half on this server
   (`licence-private.pem`, gitignored) and stores the public half.
   - The customer's copy needs that **public key**. Either ship it as `LICENCE_PUBKEY`,
     bake it into `lib/licencekey.php`, or have them paste it once on their Licence
     screen (**Provider key**). The default build already ships a public key — if you
     use it, its private half is what belongs on the server.
   - **Never put the private key on a customer's server.**

---

## 5 · Issue a key — step by step

On **Licence console** → **Issue a key**:

| Field | What to enter |
|---|---|
| **Customer** | The company name (prints on their licence screen). |
| **Seats** | How many people may sign in. `0` = unlimited. |
| **…of which field seats** | *Optional.* Light seats for inspectors, capped separately. `0` = one flat pool. |
| **Valid for** | 1 month … 3 years. |
| **…or an exact expiry date** | Overrides "valid for". |
| **Grace period (days)** | Read-only-after-expiry window before writes are refused (default 14). |
| **Install id** | *Optional.* Set it so the customer's install can pull renewals automatically. |
| **Price charged** | *Optional, our record only.* The total you invoiced — currency + amount. **Not part of the key**; it just lets the "Keys issued" list answer "how much did we charge". |
| **Plan / Modules included** | Pick a plan preset, or tick modules by hand. Core (Administration) is always on. |

Click **Generate signed key** → the next screen shows the key and a **Copy key**
button. Send it to the customer; they paste it on their **Licence** screen. If you set
an **install id**, it can be pulled automatically instead.

**Reissue (+5 / −5).** On the **Keys issued** list, each row has quick buttons to
reissue with more or fewer seats, keeping the same expiry and modules.

---

## 6 · Worked example — "1 director, 2 admins, 1 branch manager, 2 coordinators, 10 inspectors"

**Every active user is one seat**, whatever their role (`lk_seats_used()` counts all
active users). So:

> 1 + 2 + 1 + 2 + 10 = **16 people → issue Seats = 16.**

Two ways to shape it:

- **Simple (recommended):** Seats = **16**, field seats = **0** (one pool). Any mix of
  roles is fine up to 16 active users.
- **Split full vs field:** Seats = **16**, field seats = **10**. Now inspectors draw
  from a 10-seat "field" pool and everyone else from the remaining 6 "full" seats,
  each capped separately. Use this only if you price field users differently.

Leaving a role out of the count is the usual mistake — **the director, the admins and
finance all consume a seat exactly like an inspector.** When the customer tries to add
the 17th active person, the app refuses with a clear message and tells them to
deactivate a leaver or ask us for more seats. (Deactivating a user frees their seat.)

> Roles available (for reference): Master Admin, Business Director, Business Unit Head,
> Branch Manager, Branch Application Manager, Operation Manager, Asst. Manager,
> Coordinator, Business Development Manager, Key Accounts Manager, Marketing Manager,
> Marketing Executive, Finance, Inspector.

---

## 7 · Money — how it actually works (read this; it's the confusing part)

There are **two independent things**, and mixing them up is why the billing looked
"missing":

### A) The signed key = the entitlement (what they may use)
Seats, expiry, modules. **It carries no price.** We generate it after we've agreed
terms; billing the customer (invoice, bank transfer, etc.) happens **outside the app**,
however we normally raise invoices. The key is not a receipt.
→ *New:* the issue form now has an optional **Price charged** box so the amount you
invoiced is **recorded against the key** and totalled on the **Keys issued** list. This
is our bookkeeping only — it changes nothing the customer's copy enforces.

### B) Self-service billing = the customer pays online (optional)
If we switch on **Razorpay**, a customer can **buy or renew seats online** and the key
updates itself — no re-issue by us. This is the **Users & billing** screen.

- **Set the price first.** Master Admin → **Users & billing** → *Pricing & payment
  keys*: currency, **price per user / month**, **price per user / year**, and the
  Razorpay **Key Id** + **Key Secret**. **There is no default price — both prices start
  at 0, and while a price is 0 that option is hidden and nothing can be charged.** This
  is deliberate (we don't ship a price), but it's exactly why the screen looked empty:
  *someone has to set the numbers.*
- Once set, the customer picks a seat count + monthly/annual, pays via Razorpay, and on
  a verified payment the seats switch on and a row appears in the **Payments** table
  (seats, period, **amount**, paid-until, by). That table is the payment record; the
  amount **is** shown there once a real payment goes through.
- The demo row you saw (25 seats, year, ₹150,000) is a recorded payment example.

**Which path for which customer?**
- Direct/offline sale, or an air-gapped install → **path A** (issue a key, record the
  price on it, invoice them your usual way).
- Customer should self-serve card payments → **path B** (set prices + Razorpay keys,
  send them the buy link).

---

## 8 · Seat-based pricing

We charge **per seat** — one price per person, per period. We ship **no prices** on
purpose; you decide them and enter them (self-service) or invoice them (direct).

### 8.1 Two seat classes
Seats come in two classes so office staff and field staff can be priced differently:

| Seat class | Who it's for | Typical price |
|---|---|---|
| **Full seat** | Office staff — directors, managers, admins, coordinators, finance | Higher |
| **Field seat** | Inspectors (the "field" roles) | Lower |

`Total seats = full + field`. On the key, set **Seats = total** and **field seats =
the field count** (§6). If you charge one blended rate for everyone, leave field seats
at 0 and use a single price.

### 8.2 The rate card (fill in your real numbers)

| Seat class | Per user / **month** | Per user / **year** |
|---|---|---|
| Full seat  | ₹ *___* | ₹ *___*  *(≈ 10× monthly — 2 months free)* |
| Field seat | ₹ *___* | ₹ *___* |

> Annual is normally priced at **~10 months** so a yearly commitment gives ≈2 months
> free. Adjust to your own discount.

### 8.3 Worked example — the 16-seat company

1 director + 2 admins + 1 branch manager + 2 coordinators = **6 full seats**;
10 inspectors = **10 field seats**; total **16 seats**.

On the key: **Seats = 16, field seats = 10.**

Monthly invoice:

| Line | Qty | Rate/mo | Amount |
|---|---|---|---|
| Full seats  | 6  | ₹ *F* | 6 × *F* |
| Field seats | 10 | ₹ *f* | 10 × *f* |
| **Monthly total** | | | **6F + 10f** |
| **Annual total** *(if billed yearly)* | | | **10 × (6F + 10f)** |

Put the agreed period total into the **Price charged** box when you issue the key, so
it's on record (§7A).

### 8.4 How each path handles the two classes

- **Direct billing (path A):** invoice the two lines above yourself, issue **one key**
  (Seats 16 / field 10), and record the **total** in *Price charged*. This is the way
  to bill two rates.
- **Self-service Razorpay (path B):** the online checkout supports **one blended
  per-seat price**, not two — it multiplies your single *price per user* by the seat
  count. So for self-service either:
  - charge **one blended rate** for all 16 (simplest — set that one number under Users
    & billing), or
  - keep the split on the **licence** (for the seat cap) but bill the two classes on a
    **manual invoice** rather than through the online checkout.

  *(The field/full split governs how many of each may sign in; it does not make the
  online checkout charge two different prices.)*

---

## 9 · Vendor checklist to go live with a customer

- [ ] Signing set up on the licence server (§4).
- [ ] Customer's copy has our **public key** (shipped, or pasted as Provider key).
- [ ] Seats agreed (count **every** role, §6); modules agreed.
- [ ] Key issued; **Price charged** recorded on it.
- [ ] Key sent (or install id set for auto-pull).
- [ ] If self-service: prices + Razorpay keys set, buy link sent.
- [ ] Confirmed with the customer that their banner reads **Licensed** and the seat
      count matches.

---

## 10 · Support quick answers

- **"It says read-only."** Their subscription + grace has ended. Issue a renewal key
  (or they renew online). Their data is safe and fully readable.
- **"Add a person is blocked."** They're at their seat limit. Deactivate a leaver, or
  reissue **+N** seats.
- **"Key not accepted."** It was broken across lines in an email, or altered. Re-send;
  they paste it whole (spaces are stripped automatically).
- **"A module is missing."** It wasn't in their key. Reissue with that module ticked.
