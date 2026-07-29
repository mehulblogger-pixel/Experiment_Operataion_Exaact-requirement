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

There is nothing to configure. Three steps, all of them in a browser.

1. Upload this folder to `.../id.mghaiapps.com/licences/`.

2. Open `https://id.mghaiapps.com/licences/`. It asks for an admin password,
   once, and makes the signing key at the same time.

   It also works out for itself where to keep the private things — the signing
   key, the admin password and the subscriptions database — and creates that
   folder alongside the website, never inside it. The setup page shows you the
   path it chose. **Back that folder up; nothing else on this server matters.**

3. In **Settings**, paste the Razorpay webhook secret, and in Razorpay →
   Settings → Webhooks add
   `https://id.mghaiapps.com/licences/api.php?action=razorpay_webhook` for the
   events `payment.captured` and `subscription.charged`.

The public key each application needs (`LICENCE_PUBKEY`) is shown once at the
end of setup and is always available afterwards at `api.php?action=pubkey`.

### If the address answers 404

The files are there but the address is not reaching them. In order of how
often it is the cause:

1. **The upload has an extra folder inside it.** Unzipping `licence-server.zip`
   often makes `licences/licence-server/index.php`. The three files must sit
   directly in `licences/`.
2. **The site above is swallowing the address.** MGH ID sends every address to
   its own front page, and its own "page not found" is what you are seeing.
   The `.htaccess` shipped in this folder stops that — check it uploaded, since
   files beginning with a dot are hidden in cPanel File Manager until you turn
   on **Settings → Show hidden files**.
3. **Wrong folder.** `licences` has to be inside the folder that serves
   `id.mghaiapps.com`, not inside `public_html`.

To tell them apart, ask for `https://id.mghaiapps.com/licences/index.php` in
full. If that opens and the folder address does not, it is cause 2. If neither
opens, it is cause 1 or 3.

If the address turns from 404 into **500 Internal Server Error** once the
`.htaccess` is in place, this host does not permit one of its settings. Delete
that one file — everything works without it, as long as cause 2 is not the
problem.

### If the setup page shows a red box

It found nowhere safe to keep the signing key, and it has created nothing at
all. The box says which of the two problems it is and what to do about it. A
signing key inside the website is the one mistake with no recovery — anybody
who downloads it can issue licences in your name for ever — so the server
refuses rather than warns.

On an unusual host, `LICENCE_STORE` can name the folder explicitly. Nobody
needs to set it on a normal cPanel account.

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

**Back up the private folder** — the one the setup page named, and the one
Settings shows you at any time. Losing the signing key does not break any
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
