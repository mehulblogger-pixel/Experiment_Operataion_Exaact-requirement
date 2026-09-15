<?php
// Approval delegation — who may act for whom, and when. Data: $rows, $people,
// $offices, $entities. Gated hiring_admin_can() in the handler; CSRF auto-stamped.
//
// One sentence the administrator should be able to read off this screen:
//   "While <delegator> is away, <delegate> may approve for them, until <date>."
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$rows = $rows ?? []; $people = $people ?? []; $offices = $offices ?? []; $entities = $entities ?? [];
$csrf = fn() => function_exists('csrf_field') ? csrf_field() : '';
$today = date('Y-m-d');
$name = function ($r, $p) use ($e) {
    $n = trim((string) ($r[$p . 'fn'] ?? '') . ' ' . (string) ($r[$p . 'ln'] ?? ''));
    return $e($n !== '' ? $n : ((string) ($r[$p . 'un'] ?? '—')));
};
$who = function ($u) use ($e) {
    $n = trim((string) ($u['first_name'] ?? '') . ' ' . (string) ($u['last_name'] ?? ''));
    return $e($n !== '' ? $n : (string) ($u['username'] ?? ''));
};
// Live / scheduled / expired — the state an administrator actually cares about.
$state = function ($r) use ($today) {
    if ((int) $r['active'] !== 1) return ['Revoked', 'p-mut'];
    $f = substr((string) $r['effective_from'], 0, 10); $t = substr((string) $r['effective_to'], 0, 10);
    if ($f !== '' && $today < $f) return ['Scheduled', 'p-info'];
    if ($t !== '' && $today > $t) return ['Expired', 'p-mut'];
    return ['Live', 'p-ok'];
};
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/recruit-approvals">Approval rules</a> › Delegation</div>
<div class="master-head">
  <div><h1>Approval delegation</h1>
    <p class="sub" style="margin:2px 0 0">While somebody is away, another person may approve in their place — for a stated period, and only for the authority they actually hold.</p></div>
</div>

<div class="panel" style="padding:0">
  <div style="padding:12px 15px;border-bottom:1px solid var(--line,#e5e7eb);font-weight:700;font-size:14px">Delegations (<?= count($rows) ?>)</div>
  <table class="table" style="font-size:13px">
    <tr><th>Who is away</th><th>Acting for them</th><th>Applies to</th><th>Branch</th><th>From</th><th>To</th><th>State</th><th>Reason</th><th></th></tr>
    <?php foreach ($rows as $r): [$lbl, $cls] = $state($r); ?>
      <tr>
        <td><?= $name($r, 'd') ?></td>
        <td><strong><?= $name($r, 'e') ?></strong></td>
        <td><?= $e(trim((string) $r['entity']) === '' ? 'Everything they approve' : ($entities[$r['entity']] ?? $r['entity'])) ?></td>
        <td><?php $o = (int) ($r['office_id'] ?? 0); $on = '—';
             foreach ($offices as $of) if ((int) $of['id'] === $o) $on = $of['name'];
             echo $e($o ? $on : 'Any branch'); ?></td>
        <td><?= $e(substr((string) $r['effective_from'], 0, 10) ?: '—') ?></td>
        <td><?= $e(substr((string) $r['effective_to'], 0, 10) ?: '—') ?></td>
        <td><span class="pill <?= $cls ?>" style="font-size:11px"><?= $e($lbl) ?></span></td>
        <td class="muted"><?= $e($r['reason'] ?: '—') ?></td>
        <td style="white-space:nowrap">
          <?php if ((int) $r['active'] === 1): ?>
            <form method="post" style="display:inline"><?= $csrf() ?>
              <input type="hidden" name="do" value="revoke"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn secondary" style="padding:4px 9px;font-size:12px" onclick="return confirm('Revoke this delegation?')">Revoke</button>
            </form>
          <?php else: ?><span class="muted" style="font-size:11.5px"><?= $e($r['revoked_by'] ?: '') ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted">No delegations. Approvals go to their configured approvers.</td></tr><?php endif; ?>
  </table>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Add a delegation</h3>
  <form method="post">
    <?= $csrf() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="0">
    <div class="ff-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
      <div class="ff"><label>Who is away *</label>
        <select class="form-control searchable" name="delegator_user_id" required>
          <option value="">—</option>
          <?php foreach ($people as $u): ?><option value="<?= (int) $u['id'] ?>"><?= $who($u) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Who acts for them *</label>
        <select class="form-control searchable" name="delegate_user_id" required>
          <option value="">—</option>
          <?php foreach ($people as $u): ?><option value="<?= (int) $u['id'] ?>"><?= $who($u) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Applies to</label>
        <select class="form-control" name="entity">
          <option value="">Everything they approve</option>
          <?php foreach ($entities as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Branch</label>
        <select class="form-control" name="office_id">
          <option value="0">Any branch they cover</option>
          <?php foreach ($offices as $o): ?><option value="<?= (int) $o['id'] ?>"><?= $e($o['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>From</label><input class="form-control" type="date" name="effective_from" value="<?= $e($today) ?>"></div>
      <div class="ff"><label>To</label><input class="form-control" type="date" name="effective_to"></div>
      <div class="ff" style="grid-column:1/-1"><label>Reason</label><input class="form-control" name="reason" placeholder="e.g. annual leave"></div>
    </div>
    <p class="muted" style="margin:8px 2px 0;font-size:12px">A delegation never grants more than the person delegating already holds, and it can never let somebody approve their own request.</p>
    <div style="margin-top:12px"><button class="btn">Add delegation</button></div>
  </form>
</div>
<style>.ff label{display:block;font-size:12px;font-weight:600;color:var(--ink,#28313f);margin-bottom:4px}
@media(max-width:820px){.ff-grid{grid-template-columns:1fr !important}}</style>
