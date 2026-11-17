<?php
//  ADMIN → APPROVALS → EXPENSE VOUCHERS (branch batch R5, owner 16 Nov 2026;
//  batch 2, owner 17 Nov 2026 — plan 29 Change 2).
//  Which levels approve an expense voucher, the total above which each one is
//  needed, and their order. Levels are the reporting line (N+1 … N+4), any role
//  (Business Director, Business Unit Head, Branch Manager, a custom role …) and
//  Finance. The Master Admin orders them; reporting-line levels go upwards and
//  Finance always stays last.
//  Data: $cfg (ordered list, or null = default), $types, $roles, $noLine,
//  $noHolder, $finAll, $strip.
$cfg = $cfg ?? null;
$def = $cfg === null;
$roles = $roles ?? [];
$cur = function_exists('cur_sym') ? cur_sym() : '₹';
$vw  = function_exists('Tl') ? Tl('voucher') : 'voucher';      // the workspace's own word (ADR-002)
$vws = function_exists('Tlp') ? Tlp('voucher') : 'vouchers';
$sayAbove = fn($a) => $a === null ? 'every ' . $vw : $vws . ' above ' . (function_exists('fmoney') ? fmoney($a) : $cur . $a);
$amt = fn($a) => $a === null ? '' : rtrim(rtrim(number_format((float) $a, 2, '.', ''), '0'), '.');
$depth = ['N1' => 1, 'N2' => 2, 'N3' => 3, 'N4' => 4];

// The rows the form shows, in order: the saved levels, with the reporting-line
// levels not in use slotted in where they would go, then Finance at the bottom.
$main = []; $fin = [];
if ($def) {
    foreach (['N1', 'N2', 'N3', 'N4'] as $t) $main[] = ['type' => $t, 'role' => '', 'above' => null, 'on' => $t === 'N1'];
    $fin = [['type' => 'OFFICE_FIN', 'role' => '', 'above' => null, 'on' => true], ['type' => 'CENTRAL_FIN', 'role' => '', 'above' => null, 'on' => false]];
} else {
    $have = [];
    foreach ($cfg as $l) {
        if (in_array($l['type'], ['OFFICE_FIN', 'CENTRAL_FIN'], true)) continue;
        $main[] = $l + ['on' => true]; $have[$l['type']] = true;
    }
    foreach (['N1', 'N2', 'N3', 'N4'] as $t) {
        if (isset($have[$t])) continue;
        $pos = count($main);
        foreach ($main as $i => $r) if (isset($depth[$r['type']]) && $depth[$r['type']] > $depth[$t]) { $pos = $i; break; }
        array_splice($main, $pos, 0, [['type' => $t, 'role' => '', 'above' => null, 'on' => false]]);
    }
    foreach (['OFFICE_FIN', 'CENTRAL_FIN'] as $t) {
        $hit = null; foreach ($cfg as $l) if ($l['type'] === $t) $hit = $l;
        $fin[] = $hit ? $hit + ['on' => true] : ['type' => $t, 'role' => '', 'above' => null, 'on' => false];
    }
}
$used = [];
foreach ($main as $r) if ($r['type'] === 'ROLE') $used[$r['role']] = true;
$name = fn($r) => $r['type'] === 'ROLE' ? ($roles[$r['role']] ?? (function_exists('role_name') ? role_name($r['role']) : $r['role'])) : ($types[$r['type']] ?? $r['type']);
$help = function ($r) {
    switch ($r['type']) {
        case 'N1': return 'the person the claimant reports to';
        case 'ROLE': return 'anyone holding this role (or a role built on it) whose offices include the voucher\'s office — the first to approve signs';
        case 'OFFICE_FIN': return 'Finance people whose offices include the voucher\'s office';
        case 'CENTRAL_FIN': return 'Finance people who cover all offices';
        default: return 'that person\'s own "Reports to", one step further up';
    }
};
?>
<div class="master-head">
  <div>
    <h1><?= e(ucfirst($vw)) ?> approvals</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px">Who approves an expense <?= e($vw) ?> before it can be paid: the claimant's reporting line ("Reports to"), any role you add — a Business Director above a certain amount, for example — and Finance last. Each level has its own amount.
      <a href="/voucher-rules">Claim period, deadlines, expense types and bands →</a></p>
  </div>
</div>

<?= $strip ?? '' ?>

<div class="panel" style="max-width:900px;margin-top:12px">
  <h3 class="tab-sub" style="margin-top:0">The rule in force</h3>
  <?php if ($def): ?>
    <p style="margin:4px 0">Nothing has been set yet, so every voucher goes to <strong>the person the claimant reports to (N+1)</strong>, then <strong>Finance</strong> — the Finance people who cover the voucher's office, or, if nobody does, those who cover all offices.</p>
  <?php else: ?>
    <ol style="margin:4px 0 0 18px;padding:0;line-height:1.7">
      <?php foreach ($cfg as $l): ?>
        <li><strong><?= e($name($l)) ?></strong> — <?= e($sayAbove($l['above'])) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
  <p class="muted" style="margin:8px 0 0;font-size:12.5px">Nobody approves their own voucher or one they submitted. <strong>Nobody signs twice:</strong> if the person at a level has already signed an earlier level of the same voucher (a Director who is also the claimant's N+2, say), that level counts as signed and History says so. Finance is the paying check, so another Finance person signs it. Any approver can send a voucher back with a reason; it then goes back to the claimant as a draft and starts again from the first level when resubmitted.</p>
</div>

<?php if (!empty($noLine)): ?>
  <div class="panel" style="max-width:900px;border-left:3px solid var(--warn,#b45309)">
    <strong>⚠ <?= count($noLine) ?> team member<?= count($noLine) == 1 ? ' has' : 's have' ?> vouchers but no "Reports to":</strong>
    <?= e(implode(', ', array_slice(array_map(fn($r) => (string)$r['name'], $noLine), 0, 8))) ?><?= count($noLine) > 8 ? ' …' : '' ?>.
    <span class="muted">Their vouchers wait for an administrator. Fill in "Reports to" on each team-member record.</span>
  </div>
<?php endif; ?>
<?php if (!empty($noHolder)): ?>
  <div class="panel" style="max-width:900px;border-left:3px solid var(--warn,#b45309)">
    <strong>⚠ Nobody active holds <?= e(implode(', ', $noHolder)) ?>.</strong>
    <span class="muted">A voucher that needs that level waits for an administrator. Give the role to the right person, or remove the level.</span>
  </div>
<?php endif; ?>
<?php if (empty($finAll)): ?>
  <div class="panel" style="max-width:900px;border-left:3px solid var(--warn,#b45309)">
    <strong>⚠ No Finance person covers all offices.</strong>
    <span class="muted">A "Central Finance" level, or the default when no Finance person covers a voucher's office, will wait for an administrator.</span>
  </div>
<?php endif; ?>

<form method="post" action="/voucher-approvals" class="panel" style="max-width:900px" id="vappr-form">
  <h3 class="tab-sub" style="margin-top:0">Change the levels</h3>
  <p class="muted" style="margin:2px 0 10px;font-size:13px">Tick the levels you use and put them in the order they sign (use ↑ ↓). Leave the amount blank for "always". The reporting line goes upwards — N+1 before N+2 — and a higher level includes the ones below it: when N+3 is needed, N+1, N+2 and N+3 each approve. If the claimant's line is shorter, its highest person approves and the line ends there. Finance always signs last.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Order</th><th>Who approves</th><th>Use</th><th>Needed when the voucher total is above (<?= e($cur) ?>)</th></tr></thead>
    <tbody id="vappr-main">
    <?php $n = 0; foreach ($main as $r): $n++; $lbl = $name($r); ?>
      <tr data-vappr-row>
        <td style="white-space:nowrap">
          <input type="hidden" name="lv[<?= $n ?>][type]" value="<?= e($r['type']) ?>">
          <input type="hidden" name="lv[<?= $n ?>][role]" value="<?= e($r['role']) ?>">
          <input class="form-control" data-vappr-order style="width:64px;display:inline-block" type="number" min="1" step="1" name="lv[<?= $n ?>][order]" value="<?= $n ?>" aria-label="Order of <?= e($lbl) ?>">
          <button class="btn small secondary" type="button" data-vappr-move="-1" aria-label="Move <?= e($lbl) ?> up">↑</button>
          <button class="btn small secondary" type="button" data-vappr-move="1" aria-label="Move <?= e($lbl) ?> down">↓</button>
        </td>
        <td><strong><?= e($lbl) ?></strong><?= $r['type'] === 'ROLE' ? ' <span class="pill p-info" style="font-size:11px">role</span>' : '' ?><br><span class="muted" style="font-size:12px"><?= e($help($r)) ?></span></td>
        <td><label class="chk" style="margin:0"><input type="checkbox" name="lv[<?= $n ?>][use]" value="1" <?= !empty($r['on']) ? 'checked' : '' ?> aria-label="Use <?= e($lbl) ?>"></label></td>
        <td><input class="form-control" style="max-width:160px" type="number" min="0" step="1" name="lv[<?= $n ?>][above]" value="<?= e($amt($r['above'])) ?>" placeholder="always" aria-label="<?= e($lbl) ?>: needed above this total"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tbody>
    <?php foreach ($fin as $r): $n++; $lbl = $name($r); ?>
      <tr>
        <td class="muted">last<input type="hidden" name="lv[<?= $n ?>][type]" value="<?= e($r['type']) ?>"><input type="hidden" name="lv[<?= $n ?>][order]" value="<?= 1000 + $n ?>"></td>
        <td><strong><?= e($lbl) ?></strong><br><span class="muted" style="font-size:12px"><?= e($help($r)) ?></span></td>
        <td><label class="chk" style="margin:0"><input type="checkbox" name="lv[<?= $n ?>][use]" value="1" <?= !empty($r['on']) ? 'checked' : '' ?> aria-label="Use <?= e($lbl) ?>"></label></td>
        <td><input class="form-control" style="max-width:160px" type="number" min="0" step="1" name="lv[<?= $n ?>][above]" value="<?= e($amt($r['above'])) ?>" placeholder="always" aria-label="<?= e($lbl) ?>: needed above this total"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div class="inline-add" style="margin-top:12px;align-items:flex-end;flex-wrap:wrap;gap:8px">
    <div class="ff"><label>Add a level by role <span class="muted">— optional</span></label>
      <select class="form-control" name="add_role" style="min-width:220px"><option value="">— pick a role —</option>
        <?php foreach ($roles as $k => $label): if (isset($used[$k])) continue; ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Needed above (<?= e($cur) ?>)</label><input class="form-control" style="width:140px" type="number" min="0" step="1" name="add_above" placeholder="always"></div>
    <span class="muted" style="font-size:12.5px;align-self:center">It is added after the other levels, before Finance; move it with ↑ ↓ after saving. Untick a role level to remove it.</span>
  </div>

  <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="submit">Save the levels</button>
    <?php if (!$def): ?><button class="btn secondary" type="submit" name="reset" value="1" onclick="return confirm('Go back to the default: N+1, then Finance?')">Back to the default</button><?php endif; ?>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:12.5px">A change applies from the next approval step of each voucher; levels already signed stay signed. No amount is filled in for you.</p>
</form>

<div class="panel" style="max-width:900px">
  <h3 class="tab-sub" style="margin-top:0">Someone is away?</h3>
  <p style="margin:4px 0">Set who approves in their place, for a stated period: <a href="/voucher-delegations">who covers for a voucher approver</a>. A company-wide delegation (Approvals → Delegation) covers vouchers too.</p>
</div>
<script>
  (function(){
    var body = document.getElementById('vappr-main'); if (!body) return;
    function renumber(){ var i = 0; body.querySelectorAll('[data-vappr-order]').forEach(function(inp){ inp.value = ++i; }); }
    body.addEventListener('click', function(ev){
      var b = ev.target.closest('[data-vappr-move]'); if (!b) return;
      var tr = b.closest('tr'), dir = parseInt(b.getAttribute('data-vappr-move'), 10);
      if (dir < 0 && tr.previousElementSibling) body.insertBefore(tr, tr.previousElementSibling);
      else if (dir > 0 && tr.nextElementSibling) body.insertBefore(tr.nextElementSibling, tr);
      renumber(); b.focus();
    });
  })();
</script>
