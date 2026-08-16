# Seat pricing — the three seat types, the defaults, and where to change them

> Plain-English guide to **what a seat costs, the three kinds of seat, and the
> exact clicks to change any price.** For whoever sets pricing (that's the Master
> Admin on a self-managed install). No technical knowledge needed.

---

## The three kinds of seat

Not every login costs the same, because not every login uses the same amount of
the system. There are **three seat classes**:

| Seat | Who it's for | Default price / month | Free? |
|---|---|---|---|
| **Full seat** | Office & management users — managers, coordinators, sales, finance, admin (the full desktop suite). | **₹1,799** | none |
| **Field seat** | Inspectors and site roles on the field / mobile app only. | **₹499** | none |
| **Portal seat** | External client / vendor logins (they only see their own work). | **₹99** | **first 10 free** |

**How the bill is worked out:** for each class it's `(number of that kind of user
− free ones) × the price`. Only the Portal class has a free bundle (10), so your
first ten client/vendor logins never appear on the bill; the eleventh onward is a
token per head.

> **About ₹1,799:** that's the *fallback* full-seat figure the system shows if you
> haven't set your own. The real full-seat price is simply your **per-seat monthly
> rate** on the Billing screen — set that and it's used everywhere. The tier
> illustration you may see elsewhere (₹899 / ₹1,799 / ₹3,499 for Starter / Pro /
> Enterprise) is just a ratio example, not something you edit.

---

## Where the prices live — and how to change them

All four numbers (full, field, portal, and the free-portal bundle) are set in
**one place**: the **Billing** screen.

### Click-by-click

1. Sign in as the **Master Admin**.
2. Go to **Billing** (`/billing`).
3. Open the **“Pricing & payment keys”** panel (click the header to expand it).
4. You'll see these boxes — set each to the rupee figure you want:
   - **Full seat / month** *(office & management users)*
   - **Full seat / year** *(leave at 0 to hide the yearly option)*
   - **Field seat / month** *(inspector & site roles)* — default **499**
   - **Portal seat / month** *(client / vendor logins)* — default **99**
   - **Free portal seats** *(included, never billed)* — default **10**
5. Press **Save pricing & keys**.

That's it — the seat-class costing reads these immediately, so the numbers on the
Super Admin panel (below) update at once.

> **A note on 0:** setting **Portal seat** to `0` keeps the portal free above the
> bundle (some vendors do this). Setting **Field seat** to `0` isn't "free" — it
> falls back to the ₹499 default, because a field seat always has a cost; type the
> real figure instead.

---

## Where you *see* the seat mix — the Super Admin panel

**Super Admin** (`/super-admin`, sometimes labelled Control Panel) shows the live
breakdown: how many Full / Field / Portal users you have, how many are free, and
what each class costs. It's **read-only** — a picture of the mix. You change the
prices on Billing (step above); you change *who is which class* by giving people
the right **role** (inspectors and site roles count as Field; the rest are Full).

Which roles count as "field" is itself configurable under **Settings**
(`seat_field_roles`); by default it's the inspector role.

---

## How the seat *limit* works (vs the price)

Two different things, easy to mix up:

- **Price** — what a seat costs (this document). Set on **Billing**.
- **Limit** — how many seats you're *allowed* to activate. That comes from your
  **licence key** (it can carry a full-seat pool and a separate field-seat pool).
  When you hit the limit, adding another active user of that class is blocked until
  you raise the licence. An OPEN/TRIAL install is unlimited.

So: the licence says *how many* you may switch on; Billing says *what each one
costs*.

---

## Quick reference

| I want to… | Go to |
|---|---|
| Change the full-seat price | **Billing** → Pricing & payment keys → *Full seat / month* |
| Change the field-seat price | **Billing** → *Field seat / month* (default 499) |
| Change the portal price | **Billing** → *Portal seat / month* (default 99) |
| Change how many portal seats are free | **Billing** → *Free portal seats* (default 10) |
| See the live seat mix & cost | **Super Admin** — `/super-admin` (read-only) |
| Choose which roles are "field" | **Settings** — `seat_field_roles` |
| Raise the seat *limit* | Issue / upgrade the **licence key** (see the licensing SOPs) |
