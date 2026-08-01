<?php
// The licence screen. It has one job beyond showing the state: it must accept a
// new key in EVERY state, including the broken ones, because it is the screen
// somebody reaches when nothing else works.
$tone = ['OPEN' => 'info', 'TRIAL' => 'info', 'VALID' => 'success',
         'GRACE' => 'warning', 'READONLY' => 'error', 'INVALID' => 'error', 'MISSING' => 'error'];
$t = $tone[$s['state']] ?? 'info';
?>
<div class="crumbs"><a href="/">Home</a> › Licence</div>
<div class="master-head"><div>
  <h1>Licence</h1>
  <p class="sub" style="margin:2px 0 0">What this installation is entitled to, and until when.</p>
</div>
<?php if (function_exists('lk_console_allowed') && lk_console_allowed()): ?>
  <a class="btn secondary" href="/issue-licence">Licence console →</a>
<?php endif; ?>
</div>

<div class="msg msg-<?= e($t) ?>" style="margin-top:12px">
  <b><?= e($s['label']) ?><?= $s['customer'] !== '' ? ' — ' . e($s['customer']) : '' ?></b>
  <?php if ($s['state'] === 'OPEN'): ?>
    <div style="margin-top:4px">No licence is being enforced on this installation, so every module is available.
      This is the right state for your own systems and for development. A customer installation sets
      <code>LICENCE_ENFORCE=1</code> in its server environment, and then a key is required.</div>
  <?php elseif ($s['state'] === 'TRIAL'): ?>
    <div style="margin-top:4px"><b><?= (int)$s['days_left'] ?> <?= (int)$s['days_left'] === 1 ? 'day' : 'days' ?> left.</b>
      Everything is switched on during the trial. Paste a licence key below before it ends and nothing will change for
      the people using it.</div>
  <?php elseif ($s['state'] === 'VALID'): ?>
    <div style="margin-top:4px">Valid to <b><?= e(fdate($s['expires'])) ?></b><?php
      if ($s['days_left'] !== null && $s['days_left'] <= 30): ?> — <b><?= (int)$s['days_left'] ?> days from now</b><?php
      endif; ?>.</div>
  <?php elseif ($s['state'] === 'GRACE'): ?>
    <div style="margin-top:4px">The subscription ended on <b><?= e(fdate($s['expires'])) ?></b>. Everything still works
      during the grace period, and nothing has been switched off. Renew and paste the new key to avoid the system
      becoming read-only.</div>
  <?php else: ?>
    <div style="margin-top:4px"><?= e($s['err']) ?>
      <b>Nothing has been deleted and nothing is hidden.</b> Every screen, export and PDF still works — only saving new
      records is refused. Paste a current key below and the system carries on exactly where it left off.</div>
  <?php endif; ?>
</div>

<div class="panel-split" style="margin-top:16px;flex-wrap:wrap">
  <div class="panel">
    <h3 style="margin-top:0">Renewal</h3>
    <?php if (!empty($sync['on'])): ?>
      <p style="margin:0 0 10px">This installation renews itself. When a payment clears, the subscription is extended
        and this system collects the new licence on its own — daily normally, and every fifteen minutes once an expiry
        is close.</p>
      <?php if ($canManage): ?>
        <form method="post" action="/licence-check">
          <button class="btn" type="submit">Just paid? Check now</button>
        </form>
      <?php endif; ?>
      <div class="kv-grid" style="margin-top:12px">
        <div><span class="k">Last checked</span><span><?= trim((string)$sync['last']) !== ''
            ? e(fdate(substr((string)$sync['last'], 0, 10))) . ' ' . e(substr((string)$sync['last'], 11, 5))
            : '<span class="muted">never</span>' ?></span></div>
      </div>
      <?php if (trim((string)$sync['error']) !== ''): ?>
        <div class="msg msg-warning" style="margin-top:10px">
          Last check did not succeed: <?= e($sync['error']) ?>
          <div style="margin-top:4px">Nothing has been lost — this system carries on with the licence it already holds
            until that licence's own expiry.</div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p style="margin:0" class="muted">Automatic renewal is not switched on for this installation, so a key is pasted
        by hand below. That is the right setting for a system with no route to the internet.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 style="margin-top:0">The key</h3>
    <?php if ($s['from_env']): ?>
      <p class="muted" style="margin:0">This installation's key is fixed in the server environment
        (<code>LICENCE_KEY</code>), so it cannot be changed from this screen. That is deliberate on a managed
        installation — ask MGH to update it.</p>
    <?php elseif (!$canManage): ?>
      <p class="muted" style="margin:0">You can see the licence but not change it.</p>
    <?php else: ?>
      <form method="post" action="/licence-save">
        <label class="form-label">Paste the licence key MGH sent you</label>
        <textarea class="form-control" name="key" rows="4" style="font-family:ui-monospace,Menlo,monospace;font-size:12px"
                  placeholder="eyJjdXN0IjoiLi4uIn0.MEUCIQ..."></textarea>
        <div class="muted" style="font-size:12px;margin-top:4px">
          It is one long line. If your e-mail program has broken it across several lines that is fine — paste it as it
          is and the spaces are removed for you.
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
          <button class="btn" type="submit">Apply the key</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <details class="panel">
    <summary style="cursor:pointer;font-weight:600">Provider key <span class="muted" style="font-weight:400">— only if your provider asked you to paste one</span></summary>
    <p class="sub" style="margin:10px 0">If you run this on your <strong>own</strong> server and your provider gave you a
      “public key” to paste, put it here — it lets your copy check the licences they issue you. Leave it blank if your
      copy already came set up.</p>
    <form method="post" action="/licence-pubkey">
      <textarea class="form-control" name="pubkey" rows="4" style="font-family:ui-monospace,Menlo,monospace;font-size:12px" placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"><?= e(setting_get('licence_pubkey','')) ?></textarea>
      <button class="btn small" type="submit" style="margin-top:8px">Save provider key</button>
    </form>
  </details>

  <div class="panel">
    <h3 style="margin-top:0">This subscription</h3>
    <div class="kv-grid">
      <div><span class="k">Customer</span><span><?= e($s['customer'] ?: '—') ?></span></div>
      <div><span class="k">Reference</span><span><?= e($s['ref'] ?: '—') ?></span></div>
      <div><span class="k">Expires</span><span><?= $s['expires'] !== '' ? e(fdate($s['expires'])) : '—' ?></span></div>
      <div><span class="k">People covered</span><span>
        <?php if ($s['seats'] > 0): ?>
          <?= (int)$s['used'] ?> of <?= (int)$s['seats'] ?> in use
          <?php if ($s['used'] >= $s['seats']): ?><span class="pill bad">full</span><?php endif; ?>
        <?php else: ?>
          <?= (int)$s['used'] ?> active <span class="muted">(no limit)</span>
        <?php endif; ?>
      </span></div>
    </div>
    <?php if ($s['seats'] > 0 && $s['used'] >= $s['seats']): ?>
      <p class="muted" style="margin:10px 0 0;font-size:12.5px">
        Every seat is in use. To add somebody, first deactivate a person who has left — or ask MGH for more seats.
        Deactivating keeps all their history; it only stops them signing in.
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
    <b style="font-size:13.5px">What is switched on</b>
    <div class="muted" style="font-size:12.5px;margin-top:3px">
      A module that was not bought is not merely hidden — its screens refuse to open, its permissions are withdrawn,
      and even an administrator cannot reach it.
    </div>
  </div>
  <div class="dt-scroll">
    <table class="dt">
      <caption class="sr-only">Licensed modules</caption>
      <thead><tr><th scope="col">Module</th><th scope="col">What it contains</th><th scope="col">State</th></tr></thead>
      <tbody>
      <?php foreach ($s['modules'] as $key => $m): ?>
        <tr<?= $m['on'] ? '' : ' style="opacity:.55"' ?>>
          <td><b><?= e($m['label']) ?></b><?php if ($m['core']): ?> <span class="pill">always</span><?php endif; ?></td>
          <td class="muted"><?= e($m['desc']) ?></td>
          <td>
            <?php if ($m['on']): ?>
              <span class="pill">on</span>
              <?php if ($m['tier'] === 'starter'): ?><span class="muted" style="font-size:12px">Starter</span><?php endif; ?>
            <?php else: ?>
              <span class="muted">not bought</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="muted" style="margin-top:16px;font-size:12.5px;max-width:760px">
  The key is checked here on this server by arithmetic, not by asking MGH. Nothing is sent anywhere, so an installation
  on a factory network behind a corporate firewall works exactly like one on the internet, and an outage at MGH can
  never stop you invoicing. It also means the key cannot be forged: it is signed with a key only MGH holds.
</p>
