# Building the test copy

The ready-made test copy (`exaact-test-copy.zip`) is built by running the app on a
fresh, empty database and filling in its own screens. That way the result is exactly
what a person would produce, with no data written behind the app's back.

1. Copy the tracked `phpapp` files into `exaact_test/app`.
2. Add `config.local.php` with these settings:
   - `'db' => ['driver' => 'sqlite']`
   - `'sqlite_path'` pointing at `exaact_test/exaact_data_test/exaact-test.sqlite`
   - `'test_copy' => true`
   - `'data_dir'` pointing at `exaact_test/exaact_data_test`
   - an admin password of your choosing
3. Add `tenants.php` with `'base_domain' => 'test.operations.mghaiapps.com'`.
4. Start it with `php -S 127.0.0.1:8899 tools/smoke-router.php` from `exaact_test/app`.
5. Accept the licence agreement once, so the build can continue. Then delete
   `exaact_data_test/licence-agreement.json`, because the build's own acceptance is not
   a real one.
6. Run `python3 setup_copy.py <file with admin and test-user passwords> <a made-up GSTIN>`.
   It goes through these screens:
   - first-time setup;
   - clearing the starter client list (only made-up data in a test copy);
   - the company profile;
   - the 19 test users from `source/setup_guide.py`;
   - the approval levels, Set up Sales and the pre-order checklist;
   - the 8 TB customers;
   - the prices and GST (test values), the free trial and storage.
7. Put the two holds on through Client holds:
   - TB Defaulter Co: Blocked, "Unpaid for 180 days";
   - TB Paused Co: On hold, "Credit review".
8. Sign in once as every test user to confirm each one works.
9. Zip `exaact_test` together with `README-FIRST.txt` (install steps and passwords).

The passwords go only in the zip and in the message to the owner, never in git.
