# Go-live guide — for the platform owner

Plain steps to make self sign-up, e-mail and payments work on
**operations.mghaiapps.com**. Do them in this order. The **Platform setup** page
ticks each one green as it is done.

## 0. Upload the latest code
Upload the latest version of the app to the server first (as usual).

## 1. Sign in as the platform owner
- Open **https://operations.mghaiapps.com/login**.
- Sign in with the **main site's Master Admin** account (the platform owner's
  account — not any company's account).
- In the left menu you will see **Owner home** and **Platform setup**. A company's
  own administrators never see these and are refused if they type the address.
- Once step 7 is done, sign in at the Super Admin address instead
  (e.g. **https://admin.operations.mghaiapps.com/login**).

## 2. Open Platform setup
Left menu → **Platform setup**. The **Go-live checklist** at the top shows what is
ready (✓) and what is still to do (!), each with an **Open →** link.

## 3. E-mail
On **Platform setup → Platform e-mail**. You need from your mail provider:

| Box | Microsoft 365 example |
|---|---|
| Mail server (SMTP host) | `smtp.office365.com` |
| Port | `587` |
| General — From address / Mailbox login | `noreply@your-domain` |
| General — Mailbox password | that mailbox's password |
| Account & password — From address | `accounts@your-domain` |
| Account & password — Mailbox login / password | blank if it is an alias the general mailbox may "send as"; otherwise its own login |

- Sign-up codes, temporary passwords and reset links go from the **account & password**
  address; everything else from the **general** address.
- Microsoft 365: "Authenticated SMTP" must be switched on for the mailbox (Microsoft 365
  admin centre → Users → the mailbox → Mail → Manage e-mail apps), and the general
  mailbox needs "Send as" on the account address if you leave its login blank.
- Press **Save platform e-mail**, then **Send a test e-mail** for each sender to your own
  address. "Sent" means your mail server accepted it — open your inbox (and spam) and
  check which address it came from.
- If a test fails, the reason is shown in plain words.

**Checking delivery later:** Platform setup → **Recent e-mails from the main site** lists
who, what, and Sent / Not sent with the reason. Sign-up codes, passwords and reset links
are never kept.

## 4. Payment (Razorpay)
1. Razorpay Dashboard → Account & Settings → **API Keys** → generate **Test** keys.
2. Super Admin → Advanced settings → **Billing** → paste Key ID and Key Secret → save.
3. Platform setup → **Check payment keys** → "Razorpay accepted the keys — TEST keys".
4. Buy a plan as a test company with Razorpay's test card — no real money moves.
5. When ready for real customers, repeat with **Live** keys (`rzp_live_…`) and check again.

## 5. Your numbers (prices, storage, trial)
Left menu → **Owner home → Companies** (or Platform setup → Open →):

- **💳 Pricing** — price of each module and of each login, per month and per year.
  Until you save these, customers see built-in placeholder prices.
- **💾 Storage allowance** — base GB per company, GB per login, extra pack size and price.
- **🚀 Self sign-up & free trial** — trial days (30), trial storage (MB), trial logins,
  warning interval (15 days), deletion after (90 days).

Each panel has a Save button; storage changes apply to every company at once.

## 6. The nightly job
In the hosting panel (cPanel → **Cron Jobs**) add once a day, e.g. 02:30:

    php /home/USER/public_html/cron.php

(the exact path is shown on the checklist). It sends warnings, records last use and
storage, and removes unused free trials. The checklist shows when it last ran.

## 7. Super Admin on its own address
So that Super Admin has a completely separate sign-in, away from the address companies use:

1. **Platform setup → Super Admin address** → type e.g. `admin.operations.mghaiapps.com` → **Save address**.
2. Create the address in your hosting: press **Create it in cPanel for me** (shown when cPanel is
   connected), or by hand in cPanel → **Domains** (or **Subdomains**) → create
   `admin.operations.mghaiapps.com` with the **same folder** as the main site. cPanel adds the free
   security certificate (AutoSSL) within about an hour.
3. Open **https://admin.operations.mghaiapps.com/login** and sign in with the platform-owner account.
4. **Platform setup → Lock Super Admin to this address.** (The button only works on the new address,
   so you can never lock yourself out of an address that does not open yet.)

From then on: Super Admin opens only at the new address; on the main address the menu shows
**Super Admin ↗**; at the new address only the platform owner can sign in.

**If the new address ever stops working:** cPanel → File Manager → in the folder `exaact_data`
(next to the app folder) create an empty file named `superadmin-unlock`. Super Admin opens on the main
address again until you delete that file.

## 8. Switch on self sign-up — last
When every other line is green: Companies → **🚀 Self sign-up & free trial** → tick
**Companies can sign themselves up** → Save. Then try it yourself in a private window
at **/start** with an e-mail you can read.
