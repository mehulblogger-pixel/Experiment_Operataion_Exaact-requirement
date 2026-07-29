# MGH Licence Server

Runs at `id.mghaiapps.com`. Turns a payment into a renewal, with nobody typing
anything.

    customer pays  →  Razorpay webhook  →  subscription extended
                   →  their installation checks in  →  new licence collected

## Putting it up

1. Upload this folder to the subdomain's web root.
2. Make sure the folder **one level above** it is writable by PHP — the signing
   key and the config file are written there, outside the web root, so neither
   can ever be downloaded.
3. Open the subdomain in a browser. It asks for an admin password, once, and
   makes the signing key at the same time.
4. Copy the public key it shows you into each application (`LICENCE_PUBKEY`).
   It is always available afterwards at `api.php?action=pubkey`.
5. In **Settings**, paste the Razorpay webhook secret.
6. In Razorpay → Settings → Webhooks, add
   `https://id.mghaiapps.com/api.php?action=razorpay_webhook` for the events
   `payment.captured` and `subscription.charged`.

## Selling to a customer

1. **Installations → New customer.** Tick what they bought, set seats and term.
2. Copy the **installation id** into their system's `LICENCE_INSTALL` setting,
   and set `LICENCE_SERVER=https://id.mghaiapps.com` and `LICENCE_ENFORCE=1`.
3. That is all. Their system collects its licence and renews itself.

**When you create a payment link or subscription in Razorpay, put the
installation id in the payment notes as `install`, and the term as `months`.**
That is how a payment finds the right customer without anybody matching it by
hand. A payment that arrives without it is recorded, not lost, and shows up
needing a human.

## Two things that must stay true

**Back up `licence-signing-key.pem`.** It sits one level above this folder.
Losing it does not break any customer — they keep working on the keys they
already hold — but no new licence or renewal can ever be issued without
changing every installation.

**Never commit it.** The repository ships no key at all; it is generated on the
server. `data/` is ignored for the same reason.

## If a customer says they have paid and are still locked

Open their installation. **Last check-in** answers it almost every time:

- **never** — their cron job was never set up. That, not the licence.
- **recent** — look at the ledger; the payment probably arrived without an
  installation id in its notes.

They can always press **Just paid? Check now** on their own licence screen
rather than waiting for the next scheduled check.
