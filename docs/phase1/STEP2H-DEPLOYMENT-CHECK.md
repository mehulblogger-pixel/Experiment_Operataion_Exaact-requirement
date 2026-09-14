# Step 2H — Proving an upload actually landed

**Date:** 2026-09-14

---

## 1. The failure this exists for

New code was uploaded and the screen did not change. Opening it in an incognito
window changed nothing, which ruled out the browser.

Comparing file sizes settled it:

| File | Old release | New release | On the server |
|---|---|---|---|
| `index.php` | 109.40 KB | 110.33 KB | **110.33 KB** ✅ |
| `views/ops/tenants.php` | 15.07 KB | 19.13 KB | not visible in the file listing |

The **top-level** files uploaded correctly. The files inside `lib/` and `views/`
did not — which is the normal way a browser File Manager fails: it replaces what
is at the top level and quietly leaves the sub-folders alone. The application
carries on running the old code, and there is no error anywhere to notice.

There was no way for the operator to see this. That is the actual defect.

## 2. `deploy-check.php`

A single, self-contained page. Sign in as administrator, open
`/deploy-check.php`, and it reports every PHP file that is stale or missing,
grouped by folder, with the size on the server next to the size in the release.

**It is one file, and it carries its own checksums.** That is the whole design
constraint: the thing it must detect is "the sub-folders did not upload", so it
cannot itself depend on a second file or on anything inside `lib/`. A root-level
single file is the one thing that always uploads.

* Read-only: opens no workspace, runs no migration, writes to no database,
  changes no file.
* Administrator-only, exactly as the Phase-1 tool: the existing login session is
  reused and anyone else gets a plain `404`, so it is not discoverable.
* Works from the command line too (`php deploy-check.php`), exiting non-zero when
  anything is out of date.
* `config.php` and `tenants.php` are deliberately **not** checked — they hold
  this server's own credentials and routing and are meant to differ.

Regenerate before every release: `php tools/make_deploy_check.php`.

## 3. A bug the test found immediately

The first version excluded server-specific files **by filename**. `tenants.php`
is the routing cache and must not be checked — but excluding that name also
excluded **`views/ops/tenants.php`**, which is precisely the file that had failed
to upload. The checker would have been blind to the one thing it was built to
catch, and would have reported a clean bill of health.

Exclusions are now matched by exact path, and a test asserts that
`views/ops/tenants.php` is checked.

## 4. Why the checksums cannot go stale

A checker validating against last week's expectations is worse than no checker,
because it produces false confidence. `tests/test_deploy_check.php` therefore
asserts that every checksum in the shipped manifest matches the working tree,
and that no deployable PHP file is left out of it. Forgetting to regenerate is a
failing test, not a silent lie.

## 5. Second cause, still possible

If a file still reports as stale after being re-uploaded into the right folder,
PHP may be serving a compiled copy from memory. On mPanel:
**Developer Tools → Restart PHP container**. The checker reads files fresh on
every load, so reloading it after the restart gives the true answer.

## 6. Evidence

* `tests/test_deploy_check.php`: **14 passed, 0 failed**
* Full regression: **7,139 passed, 0 failed** (was 7,125; +14, zero regressions)
* Behaviour verified directly: truncating `lib/cpanel.php` made the checker
  report `647 of 648 files match — STALE lib/cpanel.php (server 900 B, release
  8,181 B)`; restoring the file returned it to `648 of 648`.
* PHP 8.4.19.

## 7. Housekeeping

An empty `data.sqlite` was tracked in the repository and shipped with every
download, landing in the application folder as a stray zero-byte file. Removed
and ignored.
