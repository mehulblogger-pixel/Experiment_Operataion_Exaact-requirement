# Step 2I — Two files holding the same credentials, one of them ignored

**Date:** 2026-09-14

---

## 1. What the operator found

> *"We have two files, config.php and config.local.sample.php — both have
> credentials. Now what to do and how to do?"*

Both had been filled in with the real database name, user and password. Only one
of them does anything:

| File | Read by the app | Survives an upload |
|---|---|---|
| `config.php` | **yes** — but only when `config.local.php` is absent | **no** — replaced every upload |
| `config.local.php` | **yes**, and it wins | **yes** — never part of any download |
| `config.local.sample.php` | **never** | yes (nothing replaces it) |

So the site was running on `config.php` — the one file guaranteed to be
overwritten — while a second copy of the password sat in a file that does
nothing at all. The sample had been filled in instead of being copied and
renamed first, which is an entirely reasonable thing to do given it is the file
that contains the instructions.

## 2. Why it was invisible

Nothing in the product said which file was in use. The only way to know was to
read `config.php` and understand a merge in the middle of it. An operator who
cannot read PHP had no way to answer "which of these two is real?" — and the
wrong answer means either the site goes down on the next upload, or a password
is left lying in a file for no reason.

## 3. What was added

`deploy-check.php` now opens with **"Where your database settings live"**:

* every config file — present or not, holds real details or still the blank
  form, read by the app or never;
* the database and user actually in use, and **which file supplied them**;
* the specific warning that applies, in plain words;
* numbered steps for the situation found, written for a File Manager.

**No password is ever printed.** Whether a file still holds the shipped
placeholders is decided by looking for them in the text; whether two files agree
is decided by comparing hashes. The values themselves are never displayed.

One warning is not obvious and is therefore stated explicitly: the administrator
password in the config file is **synced into the login** whenever it changes
(`lib/db.php:303`). Two config files with different admin passwords means the
admin login silently changes on the next page load. The page says so, and the
steps say to keep them identical while moving credentials.

## 4. Web access to credential files

`.htaccess` denied `config.php` only. It now denies `config.local.php`,
`config.local.sample.php`, `tenants.php` and `tenants.sample.php` as well.

These are all PHP and would normally execute to an empty response, so this is
not an active leak — it is the case where PHP is mis-served after a hosting
change and `.php` comes back as text. `tenants.php` matters most: it carries
every workspace's database details.

## 5. The order that matters

Moving credentials has to be done in an order that cannot strand the site:

1. Get `config.local.php` in place and **verify the site still works**.
2. Only then replace `config.php` with the clean copy.

Doing it the other way round — cleaning `config.php` first — takes the site down
the moment the new file has no credentials and `config.local.php` is not yet
right. The steps on the page are in this order, with step 3 marked as last.

## 6. Evidence

* Verified over real HTTP against a booted application, signed in as an
  administrator, in both states: the clean repository (reports credentials
  coming from `config.php`) and a reproduction of the live situation (sample
  filled in, no `config.local.php`) — which produced the exact warning
  *"config.local.sample.php has real credentials in it, and the application never
  reads that file"* and the rename step.
* Full regression: **7,147 passed, 0 failed** — unchanged.
* PHP 8.4.19.
