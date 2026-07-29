<?php
// ============================================================================
//  The screens a person uses. One password, four pages, no framework.
//
//  Deliberately plain. This is the console somebody opens when a customer
//  telephones saying they have paid and are still locked — it has to be
//  readable at speed, and every answer it gives has to be the truth rather
//  than a cached summary of it.
// ============================================================================
require __DIR__ . '/lib.php';

$p = $_GET['p'] ?? 'home';
$msg = ''; $bad = false;

// ---- Setup: only reachable while there is no admin password yet ------------
if ($p === 'setup') {
    if (ls_admin_hash() !== '') { header('Location: index.php'); exit; }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $pw = (string)($_POST['pw'] ?? '');
        if (strlen($pw) < 12) { $msg = 'Use at least 12 characters — this password protects every customer subscription.'; $bad = true; }
        else {
            $k = ls_make_keys();
            $conf = "<?php\nreturn [\n"
                  . "    'admin_hash' => " . var_export(password_hash($pw, PASSWORD_DEFAULT), true) . ",\n"
                  . "    'razorpay_secret' => '',\n"
                  . "];\n";
            if (@file_put_contents(LS_CONFIG, $conf) === false) {
                $msg = 'Could not write ' . LS_CONFIG . '. The folder above the web root must be writable.'; $bad = true;
            } else {
                @chmod(LS_CONFIG, 0600);
                $_SESSION['ls_setup_pub'] = $k['public'] ?? ('Key not created: ' . ($k['err'] ?? ''));
                header('Location: index.php?p=setupdone'); exit;
            }
        }
    }
}
if ($p === 'setupdone') {
    if (session_status() === PHP_SESSION_NONE) { session_name('MGHLIC'); session_start(); }
}

// ---- Login -----------------------------------------------------------------
if ($p === 'login' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (session_status() === PHP_SESSION_NONE) { session_name('MGHLIC'); session_start(); }
    if (password_verify((string)($_POST['pw'] ?? ''), ls_admin_hash())) {
        session_regenerate_id(true);
        $_SESSION['ls_admin'] = 1;
        header('Location: index.php'); exit;
    }
    $msg = 'Wrong password.'; $bad = true;
}
if ($p === 'logout') { if (session_status() === PHP_SESSION_NONE) { session_name('MGHLIC'); session_start(); } session_destroy(); header('Location: index.php?p=login'); exit; }

// Anything past here needs a login, except the two setup pages and login itself.
if (!in_array($p, ['login', 'setup', 'setupdone'], true)) {
    if (ls_admin_hash() === '') { header('Location: index.php?p=setup'); exit; }
    ls_require_login();
}

// ---- Actions ---------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ls_logged_in()) {
    if (!ls_csrf_ok($_POST['csrf'] ?? '')) { $msg = 'That form went stale. Please try again.'; $bad = true; }
    else {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'new_install') {
            $name = trim((string)($_POST['customer'] ?? ''));
            if ($name === '') { $msg = 'The customer needs a name.'; $bad = true; }
            else {
                $db = ls_db();
                $db->prepare("INSERT INTO customers (name, email, phone, gstin, created_at) VALUES (?,?,?,?,?)")
                   ->execute([$name, trim((string)($_POST['email'] ?? '')), trim((string)($_POST['phone'] ?? '')),
                              trim((string)($_POST['gstin'] ?? '')), ls_now()]);
                $cid = (int)$db->lastInsertId();
                $mods = array_values(array_filter((array)($_POST['mods'] ?? [])));
                if (!$mods) $mods = ['sales'];
                $iid = ls_uuid();
                $months = max(1, (int)($_POST['months'] ?? 12));
                $db->prepare("INSERT INTO installs (customer_id, install_id, label, mods, seats, expires_on, grace_days, status, created_at)
                              VALUES (?,?,?,?,?,?,?, 'ACTIVE', ?)")
                   ->execute([$cid, $iid, trim((string)($_POST['label'] ?? '')), implode(',', $mods),
                              (int)($_POST['seats'] ?? 0), date('Y-m-d', strtotime("+$months months")),
                              (int)($_POST['grace'] ?? 14), ls_now()]);
                $db->prepare("INSERT INTO ledger (install_id, kind, months, now, detail, at) VALUES (?,'MANUAL',?,?,?,?)")
                   ->execute([$iid, $months, date('Y-m-d', strtotime("+$months months")), 'Created', ls_now()]);
                header('Location: index.php?p=install&id=' . urlencode($iid)); exit;
            }
        }
        if ($do === 'extend') {
            $r = ls_extend((string)($_POST['install'] ?? ''), (int)($_POST['months'] ?? 0), 'MANUAL', 0,
                           trim((string)($_POST['reference'] ?? '')), 'Extended by hand');
            if (!empty($r['err'])) { $msg = $r['err']; $bad = true; }
            else $msg = 'Extended to ' . $r['now'] . '. The installation collects it within fifteen minutes, or at once if they press Check now.';
        }
        if ($do === 'suspend' || $do === 'resume') {
            $iid = (string)($_POST['install'] ?? '');
            ls_db()->prepare("UPDATE installs SET status = ? WHERE install_id = ?")
                   ->execute([$do === 'suspend' ? 'SUSPENDED' : 'ACTIVE', $iid]);
            ls_db()->prepare("INSERT INTO ledger (install_id, kind, detail, at) VALUES (?,?,?,?)")
                   ->execute([$iid, strtoupper($do), 'By hand', ls_now()]);
            $msg = $do === 'suspend'
                 ? 'Suspended. That installation stops collecting keys, and goes read-only when its current key expires — not immediately, because it is still holding a valid one.'
                 : 'Resumed.';
        }
        if ($do === 'edit') {
            $iid = (string)($_POST['install'] ?? '');
            $mods = array_values(array_filter((array)($_POST['mods'] ?? [])));
            ls_db()->prepare("UPDATE installs SET mods = ?, seats = ?, grace_days = ?, label = ? WHERE install_id = ?")
                   ->execute([implode(',', $mods ?: ['sales']), (int)($_POST['seats'] ?? 0),
                              (int)($_POST['grace'] ?? 14), trim((string)($_POST['label'] ?? '')), $iid]);
            $msg = 'Saved. The change reaches that installation on its next check-in.';
        }
        if ($do === 'webhook_secret') {
            $c = ls_config();
            $c['razorpay_secret'] = trim((string)($_POST['secret'] ?? ''));
            $out = "<?php\nreturn " . var_export($c, true) . ";\n";
            if (@file_put_contents(LS_CONFIG, $out) === false) { $msg = 'Could not write the config file.'; $bad = true; }
            else { @chmod(LS_CONFIG, 0600); $msg = 'Webhook secret saved.'; }
        }
    }
}

$MODS = ['sales' => 'Sales & CRM', 'money' => 'Money (invoicing)', 'operations' => 'Operations',
         'reporting' => 'Inspection reporting', 'hr' => 'People & hiring'];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MGH Licences</title>
<style>
 :root{--ink:#1f2328;--muted:#636c76;--line:#d8dde3;--soft:#f6f8fa;--brand:#0b6bcb;--bad:#b42318;--ok:#0a7a3d;--warn:#9a6700}
 *{box-sizing:border-box} body{margin:0;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);background:#fff}
 .wrap{max-width:1000px;margin:0 auto;padding:20px 16px 60px}
 h1{font-size:22px;margin:0 0 16px} h2{font-size:16px;margin:0 0 10px}
 a{color:var(--brand)} .card{border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px}
 table{width:100%;border-collapse:collapse} th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);font-size:14px}
 th{background:var(--soft);font-size:12.5px;text-transform:uppercase;letter-spacing:.3px;color:var(--muted)}
 label{display:block;font-weight:600;font-size:13px;margin:10px 0 4px}
 input,select{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:6px;font:inherit}
 button{background:var(--brand);color:#fff;border:0;border-radius:6px;padding:9px 14px;font:inherit;font-weight:600;cursor:pointer}
 button.sec{background:#fff;color:var(--ink);border:1px solid var(--line)}
 .row{display:flex;gap:12px;flex-wrap:wrap}.row>div{flex:1;min-width:150px}
 .msg{padding:10px 12px;border-radius:6px;margin-bottom:16px}
 .msg.bad{background:#fff5f4;border:1px solid #f3b0aa;color:var(--bad)}
 .msg.ok{background:#f0fbf4;border:1px solid #a9dcbd;color:var(--ok)}
 .pill{display:inline-block;padding:1px 8px;border-radius:20px;font-size:11.5px;font-weight:700;background:var(--soft);border:1px solid var(--line)}
 .pill.ok{background:#e8f6ee;border-color:#a9dcbd;color:var(--ok)}
 .pill.warn{background:#fff8e6;border-color:#f0c36d;color:var(--warn)}
 .pill.bad{background:#fdeceb;border-color:#f3b0aa;color:var(--bad)}
 .mono{font-family:ui-monospace,Menlo,monospace;font-size:12px;word-break:break-all}
 .muted{color:var(--muted)} nav a{margin-right:14px;font-weight:600}
 .hint{color:var(--muted);font-size:12.5px;margin-top:3px}
</style></head><body><div class="wrap">

<?php if ($msg): ?><div class="msg <?= $bad ? 'bad' : 'ok' ?>"><?= ls_h($msg) ?></div><?php endif; ?>

<?php if ($p === 'setup'): ?>
  <h1>Set up this licence server</h1>
  <div class="card">
    <p>This runs once. It creates your admin password and your signing key.</p>
    <p class="hint">The signing key is written <b>outside the web folder</b> so it can never be downloaded, and it never
      appears in the code repository. It is what proves a licence came from you.</p>
    <form method="post">
      <label>Choose an admin password (12 characters or more)</label>
      <input type="password" name="pw" required minlength="12" autocomplete="new-password">
      <div style="margin-top:14px"><button>Create</button></div>
    </form>
  </div>

<?php elseif ($p === 'setupdone'): ?>
  <h1>Ready</h1>
  <div class="card">
    <p><b>Your signing key has been made.</b> Give the public half below to your developer — it goes into each
      application so it can check the licences you issue.</p>
    <pre class="mono" style="background:var(--soft);padding:12px;border-radius:6px;white-space:pre-wrap"><?= ls_h($_SESSION['ls_setup_pub'] ?? '') ?></pre>
    <p class="hint">It is also always available at <code>api.php?action=pubkey</code>, so nothing is lost if this page is closed.</p>
    <p style="margin-top:14px"><a href="index.php?p=login">Sign in</a></p>
  </div>

<?php elseif ($p === 'login'): ?>
  <h1>MGH Licences</h1>
  <div class="card" style="max-width:400px">
    <form method="post">
      <label>Password</label>
      <input type="password" name="pw" required autocomplete="current-password" autofocus>
      <div style="margin-top:14px"><button>Sign in</button></div>
    </form>
  </div>

<?php elseif ($p === 'install'): $row = ls_install((string)($_GET['id'] ?? '')); ?>
  <nav style="margin-bottom:14px"><a href="index.php">← All installations</a></nav>
  <?php if (!$row): ?><div class="msg bad">No such installation.</div><?php else:
    $st = ls_state($row); $d = ls_days_left($row); ?>
  <h1><?= ls_h($row['customer_name']) ?>
    <span class="pill <?= $st === 'ACTIVE' ? 'ok' : ($st === 'LAPSED' || $st === 'SUSPENDED' ? 'bad' : 'warn') ?>"><?= ls_h($st) ?></span>
  </h1>

  <div class="card">
    <table>
      <tr><th style="width:190px">Expires</th><td><?= ls_h($row['expires_on'] ?: '—') ?>
        <?= $d !== null ? '<span class="muted">(' . ($d >= 0 ? $d . ' days left' : abs($d) . ' days ago') . ')</span>' : '' ?></td></tr>
      <tr><th>Modules</th><td><?= ls_h($row['mods']) ?></td></tr>
      <tr><th>Seats</th><td><?= (int)$row['seats'] > 0 ? (int)$row['seats'] : 'unlimited' ?></td></tr>
      <tr><th>Last check-in</th><td>
        <?php if (trim((string)$row['last_seen_at']) === ''): ?>
          <span class="pill bad">never</span>
          <div class="hint">This installation has never asked for its licence. Almost always a cron job that was never
            set up — check that before anything else.</div>
        <?php else: ?>
          <?= ls_h(substr((string)$row['last_seen_at'], 0, 16)) ?> · <?= (int)$row['seen_count'] ?> times
        <?php endif; ?>
      </td></tr>
      <tr><th>Installation id</th><td><span class="mono"><?= ls_h($row['install_id']) ?></span>
        <div class="hint">Goes into that installation's <code>LICENCE_INSTALL</code> setting.</div></td></tr>
    </table>
  </div>

  <div class="row">
    <div class="card" style="flex:1;min-width:280px">
      <h2>Extend</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= ls_h(ls_csrf()) ?>">
        <input type="hidden" name="do" value="extend">
        <input type="hidden" name="install" value="<?= ls_h($row['install_id']) ?>">
        <div class="row">
          <div><label>Months</label><input type="number" name="months" value="12" min="1" max="60"></div>
          <div><label>Reference</label><input type="text" name="reference" placeholder="cheque / UTR"></div>
        </div>
        <div style="margin-top:12px"><button>Extend now</button></div>
        <div class="hint">Added to whichever is later — today or the current expiry — so paying early never shortens a subscription.</div>
      </form>
    </div>

    <div class="card" style="flex:1;min-width:280px">
      <h2>What they have</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= ls_h(ls_csrf()) ?>">
        <input type="hidden" name="do" value="edit">
        <input type="hidden" name="install" value="<?= ls_h($row['install_id']) ?>">
        <?php $has = explode(',', (string)$row['mods']); foreach ($MODS as $k => $lbl): ?>
          <label style="font-weight:400;display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="mods[]" value="<?= $k ?>" style="width:auto" <?= in_array($k, $has, true) ? 'checked' : '' ?>>
            <?= ls_h($lbl) ?></label>
        <?php endforeach; ?>
        <div class="row" style="margin-top:10px">
          <div><label>Seats</label><input type="number" name="seats" min="0" value="<?= (int)$row['seats'] ?>"></div>
          <div><label>Grace days</label><input type="number" name="grace" min="0" value="<?= (int)$row['grace_days'] ?>"></div>
        </div>
        <label>Label</label><input type="text" name="label" value="<?= ls_h($row['label']) ?>">
        <div style="margin-top:12px"><button class="sec">Save</button></div>
      </form>
      <form method="post" style="margin-top:10px">
        <input type="hidden" name="csrf" value="<?= ls_h(ls_csrf()) ?>">
        <input type="hidden" name="do" value="<?= $row['status'] === 'SUSPENDED' ? 'resume' : 'suspend' ?>">
        <input type="hidden" name="install" value="<?= ls_h($row['install_id']) ?>">
        <button class="sec"><?= $row['status'] === 'SUSPENDED' ? 'Resume' : 'Suspend' ?></button>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>Taking payment for this installation</h2>
    <p class="hint">When you create the Razorpay payment link or subscription, add these two <b>notes</b>. They are how a
      payment finds this customer without anybody matching it by hand. Everything else about the link — amount,
      description, expiry — is yours to set.</p>
    <table>
      <tr><th style="width:120px">Note name</th><th>Value</th></tr>
      <tr><td class="mono">install</td><td><span class="mono"><?= ls_h($row['install_id']) ?></span></td></tr>
      <tr><td class="mono">months</td><td><span class="mono">12</span> <span class="muted">— or 1, 3, 6, 24, whatever they are paying for</span></td></tr>
    </table>
    <p class="hint" style="margin-top:10px"><b>If you forget them</b> the payment is not lost — it is recorded on this
      server marked as needing to be matched by hand, and it appears in the list below with no customer against it.</p>
  </div>

  <div class="card">
    <h2>Every change, and why</h2>
    <table><tr><th>When</th><th>What</th><th>Amount</th><th>Moved to</th><th>Note</th></tr>
    <?php foreach (ls_ledger($row['install_id']) as $l): ?>
      <tr><td class="muted"><?= ls_h(substr((string)$l['at'], 0, 16)) ?></td>
          <td><span class="pill"><?= ls_h($l['kind']) ?></span> <?= $l['months'] ? '+' . (int)$l['months'] . 'm' : '' ?></td>
          <td><?= $l['amount'] > 0 ? '₹' . number_format((float)$l['amount'], 2) : '—' ?></td>
          <td><?= ls_h($l['now'] ?: '—') ?></td>
          <td class="muted"><?= ls_h($l['detail'] ?: $l['reference']) ?></td></tr>
    <?php endforeach; ?></table>
  </div>
  <?php endif; ?>

<?php elseif ($p === 'settings'): ?>
  <nav style="margin-bottom:14px"><a href="index.php">← All installations</a></nav>
  <h1>Server settings</h1>
  <div class="card">
    <h2>Razorpay webhook</h2>
    <p class="hint">In Razorpay → Settings → Webhooks, add
      <code><?= ls_h((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'id.mghaiapps.com')) ?>/api.php?action=razorpay_webhook</code>
      for the events <b>payment.captured</b> and <b>subscription.charged</b>, then paste the secret Razorpay shows you.</p>
    <p class="hint"><b>Important:</b> when you create the payment link or subscription, put the installation id in the
      payment <b>notes</b> as <code>install</code>, and the term as <code>months</code>. That is how a payment finds the
      right customer without anybody matching it by hand.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= ls_h(ls_csrf()) ?>">
      <input type="hidden" name="do" value="webhook_secret">
      <label>Webhook secret</label>
      <input type="password" name="secret" placeholder="<?= ls_webhook_key() !== '' ? 'stored — type to replace' : 'paste from Razorpay' ?>">
      <div style="margin-top:12px"><button>Save</button></div>
    </form>
  </div>
  <div class="card">
    <h2>Public signing key</h2>
    <p class="hint">Goes into each application so it can verify what you issue. Also at <code>api.php?action=pubkey</code>.</p>
    <pre class="mono" style="background:var(--soft);padding:12px;border-radius:6px;white-space:pre-wrap"><?= ls_h(ls_public_key() ?: 'No signing key on this server.') ?></pre>
  </div>

<?php else: $rows = ls_installs(); ?>
  <nav style="margin-bottom:14px">
    <a href="index.php"><b>Installations</b></a><a href="index.php?p=settings">Settings</a><a href="index.php?p=logout">Sign out</a>
  </nav>
  <h1>Installations</h1>

  <?php
    $lapsing = array_filter($rows, function ($r) { $d = ls_days_left($r); return $d !== null && $d <= 14; });
    if ($lapsing): ?>
    <div class="msg" style="background:#fff8e6;border:1px solid #f0c36d;color:var(--warn)">
      <b><?= count($lapsing) ?></b> subscription<?= count($lapsing) === 1 ? '' : 's' ?> ending within a fortnight or already past.
    </div>
  <?php endif; ?>

  <div class="card">
    <table>
      <tr><th>Customer</th><th>Modules</th><th>Seats</th><th>Expires</th><th>State</th><th>Last seen</th></tr>
      <?php foreach ($rows as $r): $st = ls_state($r); $d = ls_days_left($r); ?>
        <tr>
          <td><a href="index.php?p=install&amp;id=<?= urlencode($r['install_id']) ?>"><b><?= ls_h($r['customer_name']) ?></b></a>
              <?= $r['label'] ? '<div class="muted" style="font-size:12px">' . ls_h($r['label']) . '</div>' : '' ?></td>
          <td class="muted" style="font-size:12.5px"><?= ls_h($r['mods']) ?></td>
          <td><?= (int)$r['seats'] > 0 ? (int)$r['seats'] : '∞' ?></td>
          <td><?= ls_h($r['expires_on'] ?: '—') ?><?= $d !== null && $d <= 14 ? '<div class="muted" style="font-size:12px">' . ($d >= 0 ? $d . 'd left' : abs($d) . 'd ago') . '</div>' : '' ?></td>
          <td><span class="pill <?= $st === 'ACTIVE' ? 'ok' : ($st === 'LAPSED' || $st === 'SUSPENDED' ? 'bad' : 'warn') ?>"><?= ls_h($st) ?></span></td>
          <td class="muted" style="font-size:12.5px"><?= trim((string)$r['last_seen_at']) !== '' ? ls_h(substr((string)$r['last_seen_at'], 0, 10)) : '<span class="pill bad">never</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Nothing yet. Add the first customer below.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>New customer</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= ls_h(ls_csrf()) ?>">
      <input type="hidden" name="do" value="new_install">
      <div class="row">
        <div><label>Legal name</label><input type="text" name="customer" required placeholder="Vertex Steel Pvt Ltd"></div>
        <div><label>E-mail</label><input type="text" name="email"></div>
        <div><label>GSTIN</label><input type="text" name="gstin"></div>
      </div>
      <label style="margin-top:12px">What have they bought?</label>
      <?php foreach ($MODS as $k => $lbl): ?>
        <label style="font-weight:400;display:flex;gap:8px;align-items:center">
          <input type="checkbox" name="mods[]" value="<?= $k ?>" style="width:auto" <?= $k === 'sales' ? 'checked' : '' ?>>
          <?= ls_h($lbl) ?></label>
      <?php endforeach; ?>
      <div class="row" style="margin-top:12px">
        <div><label>Seats (0 = no limit)</label><input type="number" name="seats" value="10" min="0"></div>
        <div><label>Months</label><input type="number" name="months" value="12" min="1"></div>
        <div><label>Grace days</label><input type="number" name="grace" value="14" min="0"></div>
        <div><label>Label</label><input type="text" name="label" placeholder="production"></div>
      </div>
      <div style="margin-top:14px"><button>Create</button></div>
    </form>
  </div>
<?php endif; ?>

</div></body></html>
