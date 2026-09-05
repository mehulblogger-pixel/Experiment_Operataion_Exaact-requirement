<?php
// Billing / Licence — seat usage and applying a licence key.
require_can('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('do') === 'apply') {
        [$ok, $msg] = licence_apply(post('licence_key'));
        flash($msg, $ok ? 'ok' : 'err');
    } elseif (post('do') === 'clear') {
        setting_set('licence_key','');
        flash('Licence removed — back to the free tier.');
    }
    redirect('?p=billing');
}

$s = licence_status();
$pct = $s['limit'] ? min(100, round($s['used'] / max(1,$s['limit']) * 100)) : 0;
$barColor = $s['over'] ? 'var(--bad)' : ($pct >= 80 ? 'var(--warn)' : 'var(--ok)');

layout_top('Billing & licence');
?>
<?php if ($s['expired']): ?>
  <div class="flash err">Your licence expired on <?= e(fdate($s['exp'])) ?>. New users can't be added until it's renewed.</div>
<?php elseif ($s['over']): ?>
  <div class="flash err">You are over your seat limit (<?= $s['used'] ?>/<?= $s['limit'] ?>). Please add seats or disable some users.</div>
<?php endif; ?>

<div class="card">
  <h2 class="mt0">Your plan</h2>
  <div class="grid kpis" style="margin-bottom:8px">
    <div class="kpi b"><div class="n"><?= e($s['plan']) ?></div><div class="l">Current plan</div></div>
    <div class="kpi"><div class="n"><?= $s['used'] ?></div><div class="l">Seats in use</div></div>
    <div class="kpi"><div class="n"><?= $s['limit'] ? $s['limit'] : '∞' ?></div><div class="l">Seat limit</div></div>
    <div class="kpi"><div class="n"><?= $s['exp'] ? e(fdate($s['exp'])) : 'Never' ?></div><div class="l">Expires</div></div>
  </div>
  <?php if ($s['limit']): ?>
  <div style="background:#eef2f7;border-radius:20px;height:12px;overflow:hidden;margin-top:6px">
    <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>"></div>
  </div>
  <p class="muted" style="margin-top:6px"><?= $s['used'] ?> of <?= $s['limit'] ?> seats used<?= $s['remaining']!==null?' · '.$s['remaining'].' free':'' ?>.</p>
  <?php else: ?>
    <p class="muted">Unlimited seats on this licence.</p>
  <?php endif; ?>
  <?php if (!$s['licensed']): ?>
    <p class="muted">No licence applied — running on the free tier (<?= licence_free_seats() ?> seats). Paste a licence key below to unlock more.</p>
  <?php elseif ($s['customer']): ?>
    <p class="muted">Licensed to <b><?= e($s['customer']) ?></b>.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Apply a licence key</h2>
  <p class="muted mt0">Paste the key we sent you. Keys are signed — a limit can't be changed by hand.</p>
  <form method="post">
    <input type="hidden" name="do" value="apply"><?= csrf_field() ?>
    <textarea name="licence_key" rows="3" placeholder="paste licence key here"></textarea>
    <div style="margin-top:12px;display:flex;gap:8px">
      <button class="btn">Apply licence</button>
      <?php if ($s['licensed']): ?><button class="btn ghost" name="do" value="clear" onclick="return confirm('Remove the current licence?')">Remove licence</button><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>Seat types</h2>
  <p class="muted mt0">A seat is one active login. Every role counts as one seat; disable a user to free their seat. Your default seat prices are set by your sales team; this workspace only enforces the seat <em>count</em> on the licence.</p>
</div>
<?php layout_bottom();
