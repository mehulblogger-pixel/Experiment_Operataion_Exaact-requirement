# Go-live guide — for the platform owner

Plain steps to make self sign-up, e-mail and payments work on
**operations.mghaiapps.com**. Do them in this order. The **Platform setup** page
ticks each one green as it is done.

## Before all this: test on a test copy
Run the test book on a separate **test copy** first (`docs/testing-start-guide.md`). It shows a
red banner, e-mails only your testers, takes only Razorpay test payments, and keeps its own data
folder, so nothing done there can reach the live site or a customer.

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
2. Super Admin → **Platform setup** → **Payment (Razorpay)** → paste Key ID and Key Secret → **Save payment keys**.
3. Platform setup → **Check payment keys** → "Razorpay accepted the keys — TEST keys".
4. Buy a plan as a test company with Razorpay's test card — no real money moves.
5. When ready for real customers, repeat with **Live** keys (`rzp_live_…`) and check again.

## 5. Your numbers (prices, GST, storage, trial)
Nothing is for sale until you set your prices. The app has no built-in price, and a box left
blank means "not for sale". Every box shows a suggested starting value from market research and
the reason for it (see `docs/pricing-research.md`). "Fill in the suggested values" puts them into
the empty boxes; check them, then press **Save**.

- **Owner home → Pricing** (or Platform setup → Open →) opens **Plans, pricing & usage**. It holds:
  - the price of each module (per company) and of each login, per month;
  - yearly prices, either typed in or left blank to use the **yearly discount**;
  - **GST**;
  - the AI top-up price;
  - the logins included in each console plan.

  Press **Save & push to all workspaces** to update existing companies at once.
- **GST:** switch it on only once you are GST-registered. Enter your GSTIN, legal name, address,
  rate (18%) and SAC code (997331; confirm with your accountant), and choose whether prices are
  before GST or include it. From then on the customer sees the GST before paying, pays the total,
  and gets a **tax invoice** (EX/2026-27/0001 …) under Subscription → Payment history.
- **Invoice logo:** Plans, pricing & usage → GST → **Logo on invoices**. Upload a PNG or JPG, or tick
  "Use the logo from your Company profile". Every invoice and receipt also shows "✓ PAID via
  Razorpay", the total in words and the Razorpay payment reference.
- **Customer's GSTIN:** at checkout the customer sees **Bill to** (name, GSTIN, state, address) and
  can enter their GSTIN. A mistyped GSTIN is refused before payment. If a customer paid without it
  and asks later, go to Owner home → **GST invoices** → **Correct**, enter the GSTIN and a reason.
- **Each month:** Owner home → **GST invoices** → choose the month → **Download for the
  accountant (CSV)**.
- **Companies → 💾 Storage allowance:** base GB per company, GB per login, and the extra pack's
  size and price.
- **Companies → 🚀 Self sign-up & free trial:** trial days (30), trial storage (MB), trial logins,
  the warning interval (15 days), and deletion after 90 days.

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
