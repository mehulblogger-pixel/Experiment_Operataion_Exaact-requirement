# MGH Licence Server

Turns a payment into a renewal, with nobody typing anything.

    customer pays  →  Razorpay webhook  →  subscription extended
                   →  their installation checks in  →  new licence collected

## Where to put it

`id.mghaiapps.com` already runs MGH ID, so **do not** upload this into that
subdomain's web root — it would land on top of a working application. Put it in
a folder underneath instead:

    id.mghaiapps.com/licences/

That keeps one identity host and leaves MGH ID untouched. `licence.mghaiapps.com`
as its own subdomain works equally well; nothing in the code cares which.

## Putting it up

1. Upload this folder to `.../id.mghaiapps.com/licences/`.

2. **Make the private folder first, outside the website.** In cPanel → File
   Manager, at the very top level (the one holding `public_html`), create
   `licence-private`. Then set the environment variable

       LICENCE_STORE=/home/YOUR-CPANEL-USER/licence-private

   Three things live there and nothing else: the signing key, this server's
   password, and the subscriptions database. **Back that folder up.**

   Skipping this step is the one mistake with no recovery. Without it the
   signing key is written inside the website, where anyone who guesses the
   filename can download it and mint licences in your name for ever — so the
   server checks, and refuses to make a key until the folder is somewhere a
   browser cannot reach.

3. Open `https://id.mghaiapps.com/licences/` in a browser. It asks for an admin
   password, once, and makes the signing key at the same time.

4. Copy the public key it shows you into each application (`LICENCE_PUBKEY`).
   It is always available afterwards at `api.php?action=pubkey`.

5. In **Settings**, paste the Razorpay webhook secret.

6. In Razorpay → Settings → Webhooks, add
   `https://id.mghaiapps.com/licences/api.php?action=razorpay_webhook` for the
   events `payment.captured` and `subscription.charged`.

## Selling to a customer

1. **Installations → New customer.** Tick what they bought, set seats and term.
2. Copy the **installation id** into their system's `LICENCE_INSTALL` setting,
   and set `LICENCE_SERVER=https://id.mghaiapps.com/licences` and
   `LICENCE_ENFORCE=1`.
3. That is all. Their system collects its licence and renews itself.

**When you create a payment link or subscription in Razorpay, put the
installation id in the payment notes as `install`, and the term as `months`.**
That is how a payment finds the right customer without anybody matching it by
hand. A payment that arrives without it is recorded, not lost, and shows up
needing a human.

## Two things that must stay true

**Back up the private folder.** Losing the signing key does not break any
customer — they keep working on the keys they already hold — but no new licence
or renewal can ever be issued without changing every installation.

**Never commit it.** The repository ships no key at all; it is generated on the
server, into a folder outside the website.

## If a customer says they have paid and are still locked

Open their installation. **Last check-in** answers it almost every time:

- **never** — their cron job was never set up. That, not the licence.
- **recent** — look at the ledger; the payment probably arrived without an
  installation id in its notes.

They can always press **Just paid? Check now** on their own licence screen
rather than waiting for the next scheduled check.
