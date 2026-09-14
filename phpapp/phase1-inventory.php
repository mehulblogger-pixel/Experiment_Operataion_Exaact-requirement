<?php
// ============================================================================
//  EXAACT — PHASE 1 · STEP 1
//  READ-ONLY TENANT ENTITLEMENT INVENTORY  (administrator browser tool)
//
//  HOW TO USE IT
//   1. Sign in to EXAACT as you normally do, on your MAIN address (the control
//      installation — e.g. https://operations.mghaiapps.com).
//   2. In the same browser, open:   /phase1-inventory.php
//   3. Press "Run Phase-1 Entitlement Inventory".
//   4. Download the JSON (or copy the report) and send it for review.
//   5. Delete this file and tools/phase1_inventory_engine.php afterwards.
//
//  WHO CAN RUN IT
//  Only a signed-in administrator (is_superuser) of the CONTROL installation.
//  To anyone else — including a logged-out visitor or a normal user — the page
//  returns a plain 404, so it is not discoverable.
//
//  WHAT IT DOES NOT DO
//   • Does NOT boot the application.        • Does NOT enter a workspace.
//   • Does NOT run migrations.              • Does NOT write to ANY database.
//   • Does NOT change application behaviour or entitlement.
//   • Never prints database credentials.
//
//  WHY THAT MATTERS: the application's own boot chain WRITES entitlement —
//  saas_entitlement_ensure() (lib/db.php) stamps saas_entitled_modules onto any
//  provisioned workspace that has none. Booting would change the very state we
//  are measuring. This page therefore starts a session and opens raw
//  SELECT-only connections; it never includes the application bootstrap.
//
//  Every query passes through p1_ro_query(), which refuses anything that is not
//  a read. This file cannot issue INSERT/UPDATE/DELETE/ALTER/CREATE/DROP.
// ============================================================================

$IS_CLI = (PHP_SAPI === 'cli');

require_once __DIR__ . '/tools/phase1_inventory_engine.php';   // pure engine; no side effects

// ---------------------------------------------------------------------------
//  Authenticate — reusing the application's EXISTING login session.
//  The cookie parameters below are copied from index.php so this page sees the
//  same session the user is already signed in with. No new login system, no
//  change to the application's security architecture, and nothing is written to
//  the session.
// ---------------------------------------------------------------------------
$AUTH_USER = null;

if (!$IS_CLI) {
    $httpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
             || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'secure' => $httpsNow, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

// Remember who is signed in, then deliberately forget the workspace so config
// resolves the CONTROL installation and never a tenant.
$SIGNED_IN_UID = (int) ($_SESSION['uid'] ?? 0);
$_SESSION['saas_tenant'] = '';        // in-memory only for this request
$_SERVER['HTTP_HOST']    = '';        // treated as the base domain => control DB
$_SERVER['SERVER_NAME']  = $_SERVER['SERVER_NAME'] ?? 'localhost';

require_once __DIR__ . '/lib/licence.php';        // PRODUCT_MODULES only (no side effects)
$CFG = require __DIR__ . '/config.php';           // reads configuration; writes nothing

// ---- Open the CONTROL database (reads only) -------------------------------
$ctl = null; $ctlError = '';
try {
    $d = $CFG['db'];
    if (($d['driver'] ?? '') === 'sqlite') {
        $ctl = new PDO('sqlite:' . $CFG['sqlite_path']);
        $CONTROL_ENGINE = 'sqlite';
    } else {
        $ctl = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
                       [PDO::ATTR_TIMEOUT => 10]);
        $CONTROL_ENGINE = 'mysql';
    }
    $ctl->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ctl->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) { $ctlError = $e->getMessage(); }

// ---- Confirm the signed-in person is an administrator ---------------------
if (!$IS_CLI && $ctl && $SIGNED_IN_UID > 0) {
    try {
        $AUTH_USER = p1_ro_query($ctl, "SELECT id, username, is_superuser, is_active FROM users WHERE id = ?",
                                 [$SIGNED_IN_UID])->fetch() ?: null;
        if ($AUTH_USER && !((int) $AUTH_USER['is_superuser'] === 1 && (int) $AUTH_USER['is_active'] === 1))
            $AUTH_USER = null;                                  // signed in, but not an administrator
    } catch (Throwable $e) { $AUTH_USER = null; }
}

// ---- Fallback door: a key file the operator creates on the server ---------
// Useful only if nobody can sign in. Creating the file requires server access,
// which is equivalent authority to an administrator login.
$KEY_OK = false;
if (!$IS_CLI && !$AUTH_USER) {
    $keyFile = __DIR__ . '/phase1-inventory.key';
    $expect  = is_file($keyFile) ? trim((string) @file_get_contents($keyFile)) : '';
    $given   = (string) ($_GET['key'] ?? ($_POST['key'] ?? ''));
    $KEY_OK  = ($expect !== '' && $given !== '' && hash_equals($expect, $given));
}

// ---- Not authorised: look like nothing is here ----------------------------
if (!$IS_CLI && !$AUTH_USER && !$KEY_OK) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo "Not available.\n";
    exit;
}

// ---------------------------------------------------------------------------
//  The inventory itself (identical logic in browser and command line)
// ---------------------------------------------------------------------------
function p1_run_inventory(PDO $ctl, $engine) {
    $report = ['generated_at' => date('c'), 'control_engine' => $engine, 'tenants' => [], 'notes' => []];

    $registry = [];
    $regFile = __DIR__ . '/tenants.php';
    if (is_file($regFile)) {
        $r = @require $regFile;
        if (is_array($r) && is_array($r['tenants'] ?? null)) $registry = $r['tenants'];
    }

    $tenants = [];
    try { $tenants = p1_ro_query($ctl, "SELECT * FROM saas_tenants ORDER BY company, tenant_key")->fetchAll(); }
    catch (Throwable $e) {
        $report['notes'][] = 'No saas_tenants table in the control database (' . $e->getMessage()
                           . ') — this installation has no hosted workspaces.';
    }
    if (!$tenants) $report['notes'][] = 'No hosted workspaces found. Default-deny would affect nothing here.';

    foreach ($tenants as $t) {
        $key   = (string) ($t['tenant_key'] ?? '');
        $route = p1_resolve_route((string) ($t['route_json'] ?? ''), $registry[$key] ?? null);

        if ($route['kind'] === 'none') {
            $report['tenants'][] = p1_build_row($t, [], $route['label'], 'workspace database is not wired up');
            continue;
        }
        // Never let PDO create a missing data file — that would be a write.
        if ($route['kind'] === 'sqlite' && !is_file((string) ($route['path'] ?? ''))) {
            $report['tenants'][] = p1_build_row($t, [], $route['label'],
                'workspace data file not found (not created — this tool never writes)');
            continue;
        }
        try {
            $tp = new PDO($route['dsn'], $route['user'], $route['pass'], [PDO::ATTR_TIMEOUT => 10]);
            $tp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $tp->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $report['tenants'][] = p1_build_row($t, p1_read_settings($tp), $route['label'], '');
            $tp = null;
        } catch (Throwable $e) {
            $report['tenants'][] = p1_build_row($t, [], $route['label'], $e->getMessage());
        }
    }
    $report['summary'] = p1_summarise($report['tenants']);
    return $report;
}

// ---- Step 2A: storage resolution probe (read-only) ------------------------
function p1_run_probe(PDO $ctl, $appDir) {
    $probe = ['generated_at' => date('c'), 'app_dir' => $appDir, 'registry_present' => false,
              'dirs' => [], 'tenants' => []];

    $registry = [];
    $regFile = $appDir . '/tenants.php';
    if (is_file($regFile)) {
        $probe['registry_present'] = true;
        $r = @require $regFile;
        if (is_array($r) && is_array($r['tenants'] ?? null)) $registry = $r['tenants'];
    }
    foreach (p1_candidate_dirs($appDir) as $label => $dir)
        $probe['dirs'][$label] = ['path' => $dir] + p1_scan_sqlite($dir);

    try {
        foreach (p1_ro_query($ctl, "SELECT * FROM saas_tenants ORDER BY tenant_key")->fetchAll() as $t) {
            $k = (string) ($t['tenant_key'] ?? '');
            $row = p1_probe_tenant($k, (string) ($t['route_json'] ?? ''), $registry[$k] ?? null, $appDir,
                                   (string) ($t['company'] ?? ''));
            // Control-record facts required by Step 2B Part 2 (never credentials).
            foreach (['company', 'status', 'plan', 'plan_expiry', 'enabled_modules',
                      'created_at', 'updated_at', 'provisioned_at'] as $f)
                if (array_key_exists($f, $t)) $row['control_' . $f] = (string) $t[$f];
            $probe['tenants'][] = $row;
        }
    } catch (Throwable $e) { $probe['error'] = $e->getMessage(); }

    // Recovery scan — does the data still exist anywhere, live or as a backup?
    // The workspace keys are passed in so a file that was RENAMED but still
    // carries its workspace key is found too.
    $keys  = array_map(fn($r) => (string) ($r['tenant'] ?? ''), $probe['tenants']);
    $sweep = p1_sweep($appDir, $keys);
    $rec = ['app_dir' => $appDir, 'searched' => $sweep['searched'], 'live_files' => $sweep['files'],
            'backups' => [], 'any_backups' => false];
    foreach (p1_backup_dirs($appDir) as $label => $dir) {
        $b = p1_scan_backups($dir);
        $rec['backups'][$label] = $b;
        if (!empty($b['workspaces'])) $rec['any_backups'] = true;
    }
    // Per workspace, from that workspace's OWN evidence only — '__control'
    // snapshots hold the routing directory, not any workspace's records, and
    // must never make a workspace look recoverable.
    $rec['per_workspace'] = p1_recovery_per_workspace($rec, $probe['tenants']);
    $rec['unrecoverable'] = array_values(array_map(fn($w) => $w['workspace'],
        array_filter($rec['per_workspace'], fn($w) => !$w['recoverable'])));
    $probe['recovery'] = $rec;
    return $probe;
}

// ---- Command line: unchanged behaviour ------------------------------------
if ($IS_CLI) {
    if (!$ctl) { fwrite(STDERR, "Could not open the control database: $ctlError\n"); exit(1); }
    $json = in_array('--json', $argv ?? [], true);
    if (in_array('--probe', $argv ?? [], true)) {
        $probe = p1_run_probe($ctl, __DIR__);
        echo $json ? json_encode($probe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n" : p1_render_storage_text($probe);
        exit(0);
    }
    $report = p1_run_inventory($ctl, $CONTROL_ENGINE);
    echo $json
        ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        : p1_render_text($report);
    exit(0);
}

// ---------------------------------------------------------------------------
//  Browser
// ---------------------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate');

// A token tied to this browser session. Computed, never stored, so the session
// is not modified. It stops another site from triggering a run in your browser.
$TOKEN = hash('sha256', session_id() . '|phase1-inventory');
$RAN   = ($_SERVER['REQUEST_METHOD'] === 'POST') && hash_equals($TOKEN, (string) ($_POST['t'] ?? ''));
$MODE  = (string) ($_POST['mode'] ?? 'inventory');

$report = null; $probe = null; $runError = '';
if ($RAN) {
    if (!$ctl) $runError = 'Could not open the control database: ' . $ctlError;
    else try {
        if ($MODE === 'probe') $probe = p1_run_probe($ctl, __DIR__);
        else                   $report = p1_run_inventory($ctl, $CONTROL_ENGINE);
    } catch (Throwable $e) { $runError = $e->getMessage(); }
}

$h  = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$who = $AUTH_USER ? ('signed in as ' . $AUTH_USER['username']) : 'authorised by key file';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Phase 1 — Entitlement Inventory</title>
<style>
  :root{--bg:#f7f9fb;--card:#fff;--ink:#101822;--mut:#5a6878;--line:#dde3ea;
        --accent:#1b4f7e;--ok:#1c6b48;--warn:#9c5e14;--bad:#a32a2b;--code:#f2f5f8}
  @media (prefers-color-scheme:dark){:root{--bg:#0d131b;--card:#151e28;--ink:#e8edf2;--mut:#8695a5;
        --line:#25303c;--accent:#7fb2dc;--ok:#6fbe96;--warn:#dda85c;--bad:#e8908f;--code:#111925}}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);padding:20px;
       font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
  .w{max-width:860px;margin:0 auto}
  .card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:22px;margin-bottom:16px}
  h1{font-size:22px;margin:0 0 6px;letter-spacing:-.01em}
  h2{font-size:14px;text-transform:uppercase;letter-spacing:.1em;color:var(--mut);margin:0 0 12px}
  p{margin:0 0 12px;color:var(--mut)}
  .who{font-size:13px;color:var(--mut);margin-bottom:18px}
  .btn{display:inline-block;background:var(--accent);color:#fff;border:none;border-radius:8px;
       padding:14px 22px;font-size:16px;font-weight:600;cursor:pointer}
  .btn:hover{filter:brightness(1.08)}
  .btn.sec{background:transparent;color:var(--accent);border:1.5px solid var(--accent);padding:11px 18px;font-size:14px}
  .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
  .note{background:var(--code);border-left:3px solid var(--accent);padding:12px 14px;border-radius:0 6px 6px 0;
        font-size:14.5px;color:var(--mut);margin-bottom:16px}
  pre{background:var(--code);border:1px solid var(--line);border-radius:8px;padding:14px;
      overflow-x:auto;font:12.5px/1.65 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--ink);
      max-height:60vh;white-space:pre}
  .pill{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.06em;padding:3px 9px;border-radius:20px}
  .v-ok{background:rgba(28,107,72,.14);color:var(--ok)}.v-bad{background:rgba(163,42,43,.14);color:var(--bad)}
  .err{background:rgba(163,42,43,.1);border-left:3px solid var(--bad);padding:12px 14px;color:var(--bad);border-radius:0 6px 6px 0}
  .sum{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:1px;background:var(--line);
       border:1px solid var(--line);border-radius:8px;overflow:hidden;margin-bottom:16px}
  .sum div{background:var(--card);padding:11px 13px}
  .sum b{display:block;font-size:19px}.sum span{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--mut)}
  code{background:var(--code);padding:1px 5px;border-radius:4px;font-size:13.5px}
</style>
</head><body><div class="w">

<div class="card">
  <h1>Phase 1 — Entitlement Inventory</h1>
  <div class="who"><?= $h($who) ?> · control installation · <strong>read-only</strong></div>

  <?php if (!$RAN): ?>
    <div class="note">
      This reads your workspaces and reports what each one is entitled to today, and what it
      would be left with if blank entitlement started meaning “deny”.
      <strong>It changes nothing.</strong> It does not start the application, does not open any
      workspace, and cannot write to any database.
    </div>
    <?php if ($ctlError): ?><div class="err">Could not open the control database: <?= $h($ctlError) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="t" value="<?= $h($TOKEN) ?>">
      <input type="hidden" name="mode" value="inventory">
      <?php if ($KEY_OK && !$AUTH_USER): ?><input type="hidden" name="key" value="<?= $h($_GET['key'] ?? $_POST['key'] ?? '') ?>"><?php endif; ?>
      <button class="btn" type="submit">Run Phase-1 Entitlement Inventory</button>
    </form>
    <p style="margin-top:14px;font-size:13.5px">Takes a few seconds. Nothing is saved on the server —
      you download the result from this page.</p>
    <hr style="border:none;border-top:1px solid var(--line);margin:20px 0">
    <h2 style="margin-bottom:8px">Step 2A — storage check</h2>
    <p style="font-size:14.5px">Use this if a workspace was reported as <strong>unreadable</strong>. It shows where each
      workspace's data file is supposed to be, and where it actually is on disk. It opens nothing.</p>
    <form method="post">
      <input type="hidden" name="t" value="<?= $h($TOKEN) ?>">
      <input type="hidden" name="mode" value="probe">
      <?php if ($KEY_OK && !$AUTH_USER): ?><input type="hidden" name="key" value="<?= $h($_GET['key'] ?? $_POST['key'] ?? '') ?>"><?php endif; ?>
      <button class="btn sec" type="submit">Run Step-2A storage diagnostic</button>
    </form>
  <?php elseif ($runError): ?>
    <div class="err"><strong>The inventory could not run.</strong><br><?= $h($runError) ?></div>
    <div class="row"><a class="btn sec" href="phase1-inventory.php">Back</a></div>
  <?php elseif ($probe !== null):
      $txt = p1_render_storage_text($probe);
      $jsn = json_encode($probe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  ?>
    <div class="note"><strong>Storage diagnostic complete.</strong> Press <em>Download JSON</em> and send that file.
      Nothing was opened, created or written.</div>
    <div class="row">
      <button class="btn" type="button" id="dl-json">Download JSON</button>
      <button class="btn sec" type="button" id="dl-txt">Download report</button>
      <button class="btn sec" type="button" id="cp">Copy report</button>
      <a class="btn sec" href="phase1-inventory.php">Back</a>
    </div>
  </div>
  <div class="card"><h2>Storage report</h2><pre id="rep"><?= $h($txt) ?></pre></div>
  <script id="p1json" type="application/json"><?= str_replace(['<', '>', '&'], ['\u003c', '\u003e', '\u0026'], $jsn) ?></script>
  <script>
  (function(){
    var stamp=new Date().toISOString().slice(0,10);
    function save(n,x,m){var b=new Blob([x],{type:m}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=n;document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();},1500);}
    var json=document.getElementById('p1json').textContent, txt=document.getElementById('rep').textContent;
    document.getElementById('dl-json').onclick=function(){save('exaact-phase1-storage-'+stamp+'.json',json,'application/json');};
    document.getElementById('dl-txt').onclick=function(){save('exaact-phase1-storage-'+stamp+'.txt',txt,'text/plain');};
    document.getElementById('cp').onclick=function(){var b=this;var d=function(){b.textContent='Copied';setTimeout(function(){b.textContent='Copy report';},1800);};
      if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(txt).then(d,f);}else f();
      function f(){var ta=document.createElement('textarea');ta.value=txt;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');d();}catch(e){b.textContent='Press Ctrl/Cmd-C';}ta.remove();}};
  })();
  </script>
  <?php else:
      $s = $report['summary'];
      $txt = p1_render_text($report);
      $jsn = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  ?>
    <div class="sum">
      <div><b><?= (int) $s['total'] ?></b><span>Workspaces</span></div>
      <div><b><?= (int) $s['SAFE'] ?></b><span>Safe</span></div>
      <div><b><?= (int) $s['RECOVERABLE'] ?></b><span>Recoverable</span></div>
      <div><b><?= (int) $s['AMBIGUOUS'] ?></b><span>Ambiguous</span></div>
      <div><b><?= (int) $s['ERROR'] ?></b><span>Unreadable</span></div>
    </div>
    <p><span class="pill <?= $s['safe_to_flip'] ? 'v-ok' : 'v-bad' ?>">
      <?= $s['safe_to_flip'] ? 'ALL DETERMINABLE' : 'DO NOT FLIP DEFAULT-DENY' ?></span></p>
    <div class="note"><strong>Next step:</strong> press <em>Download JSON</em> and send that file for review.
      Nothing further should be changed on the server until it has been looked at.</div>
    <div class="row">
      <button class="btn" type="button" id="dl-json">Download JSON</button>
      <button class="btn sec" type="button" id="dl-txt">Download report</button>
      <button class="btn sec" type="button" id="cp">Copy report</button>
      <a class="btn sec" href="phase1-inventory.php">Run again</a>
    </div>
  </div>
  <div class="card">
    <h2>Report</h2>
    <pre id="rep"><?= $h($txt) ?></pre>
  </div>
  <script id="p1json" type="application/json"><?= str_replace(['<', '>', '&'], ['<', '>', '&'], $jsn) ?></script>
  <script>
  (function(){
    var stamp = new Date().toISOString().slice(0,10);
    function save(name, text, mime){
      var b = new Blob([text], {type: mime});
      var a = document.createElement('a');
      a.href = URL.createObjectURL(b); a.download = name;
      document.body.appendChild(a); a.click();
      setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }
    var json = document.getElementById('p1json').textContent;
    var txt  = document.getElementById('rep').textContent;
    document.getElementById('dl-json').onclick = function(){ save('exaact-phase1-inventory-'+stamp+'.json', json, 'application/json'); };
    document.getElementById('dl-txt').onclick  = function(){ save('exaact-phase1-inventory-'+stamp+'.txt', txt, 'text/plain'); };
    document.getElementById('cp').onclick = function(){
      var btn = this;
      var done = function(){ btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = 'Copy report'; }, 1800); };
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(txt).then(done, fallback); }
      else fallback();
      function fallback(){
        var ta = document.createElement('textarea'); ta.value = txt;
        ta.style.position='fixed'; ta.style.opacity='0'; document.body.appendChild(ta);
        ta.select(); try { document.execCommand('copy'); done(); } catch(e) { btn.textContent = 'Press Ctrl/Cmd-C'; }
        ta.remove();
      }
    };
  })();
  </script>
  <?php endif; ?>
<?php if (!$RAN || $runError): ?></div><?php endif; ?>

<div class="card">
  <h2>Safety</h2>
  <p style="margin:0">This page never starts the application, never opens a workspace, and never writes.
    Every database query is checked and refused unless it is a read. Database passwords are never shown.
    Delete <code>phase1-inventory.php</code> and <code>tools/</code> from the server once you have sent the report.</p>
</div>

</div></body></html>
