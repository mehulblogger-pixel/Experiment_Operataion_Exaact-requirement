# Your licence & installation — customer guide

> Plain-English guide for the company running this software. It explains what a licence
> is, how to install the app on your own server, what you'll see, how to enter the key
> we send you, and how to add people. No technical background needed for the day-to-day
> parts; the install section is for your IT person.

---

## 1 · What a licence is

Your subscription is a **signed licence key** — one long line of text we send you. It
records:

- **Your company name**
- **How many people** may sign in (your **seats**)
- **When the subscription runs to** (the expiry date)
- **Which parts of the app** are switched on for you

You paste that key once, on the **Licence** screen. From then on the app knows exactly
what you're entitled to. The key works **offline** — the app never phones home, so it's
fine on a factory network with no internet.

**You are never locked out of your own data.** If a subscription lapses, the app goes
**read-only** — every screen, report and PDF still opens and prints; only *creating new
records* pauses until you renew.

---

## 2 · Installing on your own server (for your IT person)

The app is a standard **PHP + database** web application.

**What you need**
- A server (or shared hosting / cPanel) with **PHP 8** and either **SQLite** (simplest)
  or **MySQL/MariaDB**.
- The application files (we provide them / a Git checkout).

**Steps**
1. Put the app files on the server and point a web address (or virtual host) at the
   app's public folder.
2. **Database:** for SQLite, ensure the app folder is writable — the database file is
   created automatically on first load. For MySQL, create an empty database and set
   `DB_DRIVER=mysql` plus the connection details in the environment/config.
3. **First load** creates all tables and seeds the starter data automatically.
4. **Turn on licence enforcement** so your seats and modules apply: set the environment
   variable `LICENCE_ENFORCE=1`. (Without it the app runs unrestricted — fine while
   testing; turn it on for production.)
   - You get a **14-day full-featured trial** from first boot, so you can set things up
     before pasting the key.
5. **(If we ask)** paste the **provider key** we give you on the Licence screen, so your
   copy can verify the key we sign for you. Often this is already built in and you can
   skip it.
6. **Email (optional but recommended):** set your SMTP details in Settings so the app
   can send assignment and reminder emails.

**What you'll see on first sign-in:** the Dashboard, with the left-hand menu showing
only the modules you've licensed. The top bar shows your financial year and a **Licence**
status. Until the key is entered you'll see **Trial** with the days remaining.

> A deployment checklist (`phpapp/DEPLOY-CHECKLIST.md`) ships with the app for your IT
> person, and Docker/Caddy configuration is available if you prefer containers.

---

## 3 · Entering the licence key we send you

1. Sign in as an administrator.
2. Go to **Admin → Licence** (or open `/licence`).
3. In **The key**, paste the long line we emailed you. If your email app broke it across
   several lines, that's fine — paste it as-is; spaces are removed for you.
4. Click **Apply the key**.
5. The banner should change to **Licensed**, showing your company name, the expiry date,
   and your seat count.

The screen also shows **What is switched on** — a table of the six modules
(Operations, Administration, Sales & CRM, Inspection reporting, Money, People & hiring)
and whether each is on for you. Administration and Operations are always part of an
inspection system.

---

## 4 · People & seats

Your licence covers a number of **seats** — that's simply **how many active user
accounts** you may have. **Everyone counts the same:** a director, an administrator, a
coordinator and an inspector each use one seat.

- Add and manage people under **Admin → Users**.
- If you try to add someone beyond your seat count, the app tells you you're full and
  suggests **deactivating a person who has left** (which frees their seat) or asking us
  for more seats.
- Deactivating, not deleting, is the right way to free a seat while keeping that
  person's history.

**Example:** if your team is 1 director, 2 admins, 1 branch manager, 2 coordinators and
10 inspectors, that's **16 people = 16 seats**. Make sure your licence covers 16.

---

## 5 · Renewing or adding seats

**If we bill you directly:** we send you a new key when you renew or change seats. Paste
it the same way (§3) — it replaces the old one.

**If online payment is switched on for you:** you can add or renew seats yourself.

1. Go to **Users & billing**.
2. Choose how many people and monthly or annual.
3. Pay by card (Razorpay). On success your seats switch on immediately.
4. If you paid from a link outside the app, open **Licence → "Just paid? Check now"** to
   pull the update straight away.

Your payments are listed on that screen (date, seats, period, amount, paid-until), so
you always have a record of what you paid.

---

## 6 · What happens at expiry

- Before expiry we (or the app) remind you.
- On the expiry date you enter a **grace period** (a couple of weeks): everything still
  works normally — this is your buffer to renew.
- After grace, the app becomes **read-only**: you can still open, search, export and
  print everything; only new entries pause until you renew. Renew (new key or online
  payment) and full use returns at once.

---

## 7 · Quick answers

| Question | Answer |
|---|---|
| Where do I paste the key? | **Admin → Licence** → *The key* → **Apply the key**. |
| It says "read only" | Your subscription lapsed. Renew — your data is safe and fully readable meanwhile. |
| "Add user" is blocked | You're at your seat limit. Deactivate someone who left, or ask us for more seats. |
| The key "was not accepted" | It may have been altered or truncated. Paste the whole line again exactly as we sent it. |
| A module I expected isn't there | It isn't in your current licence — contact us to add it. |
| Do we need internet for the licence? | No. The key is verified on your own server, offline. |
