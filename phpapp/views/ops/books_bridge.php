<div class="crumbs"><a href="/">Home</a> › <span>MGH Books connection</span></div>

<div class="master-head">
  <div><h1>📗 MGH Books connection</h1>
    <p class="sub" style="margin:2px 0 0">Connect Books as your finance system of record. Off by default — with it off,
      this app keeps its own invoicing and nothing changes.</p></div>
  <div><span class="pill <?= $connected ? 'p-ok' : 'p-mut' ?>"><?= $connected ? 'Connected' : 'Not connected' ?></span></div>
</div>

<?php if (!$licensed): ?>
<div class="panel" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
  <b>The Money / invoicing module is not licensed on this installation.</b>
  <div class="muted" style="margin-top:4px">Books connects the finance side, so it needs that module. Contact MGH to add it.</div>
</div>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>How it works</h3></div>
    <p class="muted" style="font-size:13.5px;line-height:1.65">
      When connected, an <b>issued invoice</b> is queued and pushed to Books as the billable event — the client, the line
      items and the tax split — and Books becomes the system of record. The push uses an <b>outbox</b>, so Books being
      unreachable never blocks you here: the queue drains on the next scheduled run, retries a failure a few times, then
      shows it plainly. Nothing is sent twice.
    </p>
    <p class="muted" style="font-size:13.5px;line-height:1.65">
      Turn it off and everything falls straight back to this app's own invoicing — no data is lost either way.
    </p>
  </div>

  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Connection</h3></div>
    <form method="post" action="/books-bridge-save" class="form-grid">
      <div class="ff ff-check"><label><input type="checkbox" name="books_connected" value="1" <?= $connected ? 'checked' : '' ?> <?= $licensed ? '' : 'disabled' ?>>
        Books is connected (it becomes the system of record for invoices)</label></div>
      <div class="ff"><label>Books API address <span class="muted">— server-to-server (where invoices are pushed)</span></label>
        <input class="form-control" name="books_api_url" value="<?= e($url) ?>" placeholder="https://books.yourcompany.com/api.php"></div>
      <div class="ff"><label>API token <?php if ($hasToken): ?><span class="muted">— saved; leave blank to keep</span><?php endif; ?></label>
        <input class="form-control" type="password" name="books_api_token" placeholder="<?= $hasToken ? '••••••••' : 'paste the token from Books' ?>"></div>
      <div class="ff"><label>Books web address <span class="muted">— what a person opens; leave blank to reuse the API address</span></label>
        <input class="form-control" name="books_app_url" value="<?= e($appUrl) ?>" placeholder="https://books.yourcompany.com">
        <span class="muted" style="font-size:12px;margin-top:4px;display:block">
          <?php if ($switchReady): ?>✓ The <b>📗 Accounts &amp; GST</b> link is live in the top nav — it opens Books already signed in via the shared login.
          <?php else: ?>Set this and connect to show an <b>Accounts &amp; GST</b> shortcut in the top nav that opens Books with the same login.<?php endif; ?>
        </span></div>
      <div class="ff ff-check"><label><input type="checkbox" name="books_dryrun" value="1" <?= $dryrun ? 'checked' : '' ?>>
        Dry run — queue and mark items delivered <em>without</em> contacting Books (for testing the wiring)</label></div>
      <div class="ff ff-check" style="align-items:end"><button class="btn" type="submit">Save connection</button></div>
    </form>
  </div>
</div>

<?php if ($connected): ?>
<div class="kpi-row" style="margin-top:16px">
  <div class="kpi"><span class="kic">📤</span><div class="k">Waiting to send</div><div class="v"><?= (int)$counts['PENDING'] ?></div><div class="d">in the outbox</div></div>
  <div class="kpi"><span class="kic">✓</span><div class="k">Sent to Books</div><div class="v"><?= (int)$counts['DONE'] ?></div><div class="d">delivered</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">Failed</div><div class="v"><?= $counts['FAILED'] ? '<span class="down">' . (int)$counts['FAILED'] . '</span>' : '0' ?></div><div class="d"><?= (int)$counts['stuck'] ?> given up on</div></div>
  <div class="kpi"><span class="kic">🔌</span><div class="k">Configured</div><div class="v" style="font-size:19px"><?= $configured ? 'Yes' : 'No' ?></div><div class="d"><?= $dryrun ? 'dry run' : ($configured ? 'live' : 'needs URL + token') ?></div></div>
</div>

<form method="post" action="/books-bridge-drain" style="margin-top:12px">
  <button class="btn secondary" type="submit">Send the queue now</button>
  <span class="muted" style="font-size:12.5px;margin-left:8px">Otherwise it sends automatically on the next scheduled run.</span>
</form>

<?php if ($pending || $failed): ?>
<div class="panel" style="padding:0;overflow:hidden;margin-top:16px">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Queue</h3></div>
  <div style="overflow-x:auto"><table class="dt" style="margin-top:8px">
    <thead><tr><th>Kind</th><th>Record</th><th>Status</th><th>Tries</th><th>Last error</th><th>Queued</th></tr></thead>
    <tbody>
      <?php foreach (array_merge($failed, $pending) as $r): ?>
      <tr>
        <td><b><?= e($r['kind']) ?></b></td>
        <td>#<?= (int)$r['local_id'] ?></td>
        <td><span class="pill <?= $r['status'] === 'FAILED' ? 'p-bad' : 'p-warn' ?>"><?= e($r['status']) ?></span></td>
        <td><?= (int)$r['attempts'] ?></td>
        <td class="muted" style="font-size:12px;max-width:280px"><?= e($r['last_error'] ?: '—') ?></td>
        <td class="muted" style="font-size:12px"><?= e(substr((string)$r['queued_at'], 0, 16)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php endif; ?>
