<?php
//  ADMIN → APPROVALS → EXPENSE VOUCHERS (branch batch R5, owner 16 Nov 2026).
//  Which levels approve an expense voucher and the total above which each one is
//  needed. The order is fixed — the reporting line first, N+1 upwards, Finance
//  last (Q1) — so the screen is one row per level rather than a list to sort.
//  Data: $cfg (null = default), $types, $noLine, $finAll, $strip.
$cfg = $cfg ?? null;
$def = $cfg === null;
$cur = function_exists('cur_sym') ? cur_sym() : '₹';
$vw  = function_exists('Tl') ? Tl('voucher') : 'voucher';      // the workspace's own word (ADR-002)
$vws = function_exists('Tlp') ? Tlp('voucher') : 'vouchers';
$sayAbove = fn($a) => $a === null ? 'every ' . $vw : $vws . ' above ' . (function_exists('fmoney') ? fmoney($a) : $cur . $a);
?>
<div class="master-head">
  <div>
    <h1><?= e(ucfirst($vw)) ?> approvals</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px">Who approves an expense <?= e($vw) ?> before it can be paid. It goes up the claimant's reporting line ("Reports to"), one person after another, and Finance approves last.</p>
  </div>
</div>

<?= $strip ?? '' ?>

<div class="panel" style="max-width:860px;margin-top:12px">
  <h3 class="tab-sub" style="margin-top:0">The rule in force</h3>
  <?php if ($def): ?>
    <p style="margin:4px 0">Nothing has been set yet, so every voucher goes to <strong>the person the claimant reports to (N+1)</strong>, then <strong>Finance</strong> — the Finance people who cover the voucher's office, or, if nobody does, those who cover all offices.</p>
  <?php else: ?>
    <ol style="margin:4px 0 0 18px;padding:0;line-height:1.7">
      <?php foreach ($types as $t => $label): if (!array_key_exists($t, $cfg)) continue; ?>
        <li><strong><?= e($label) ?></strong> — <?= e($sayAbove($cfg[$t])) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
  <p class="muted" style="margin:8px 0 0;font-size:12.5px">Nobody approves their own voucher or one they submitted, and one person approves one level. Any approver can send a voucher back with a reason; it then goes back to the claimant as a draft and starts again from the first level when resubmitted.</p>
</div>

<?php if (!empty($noLine)): ?>
  <div class="panel" style="max-width:860px;border-left:3px solid var(--warn,#b45309)">
    <strong>⚠ <?= count($noLine) ?> team member<?= count($noLine) == 1 ? ' has' : 's have' ?> vouchers but no "Reports to":</strong>
    <?= e(implode(', ', array_slice(array_map(fn($r) => (string)$r['name'], $noLine), 0, 8))) ?><?= count($noLine) > 8 ? ' …' : '' ?>.
    <span class="muted">Their vouchers wait for an administrator. Fill in "Reports to" on each team-member record.</span>
  </div>
<?php endif; ?>
<?php if (empty($finAll)): ?>
  <div class="panel" style="max-width:860px;border-left:3px solid var(--warn,#b45309)">
    <strong>⚠ No Finance person covers all offices.</strong>
    <span class="muted">A "Central Finance" level, or the default when no Finance person covers a voucher's office, will wait for an administrator.</span>
  </div>
<?php endif; ?>

<form method="post" action="/voucher-approvals" class="panel" style="max-width:860px">
  <h3 class="tab-sub" style="margin-top:0">Change the levels</h3>
  <p class="muted" style="margin:2px 0 10px;font-size:13px">Tick the levels you use. Leave the amount blank for "always". A higher reporting level includes the ones below it: when N+3 is needed, N+1, N+2 and N+3 each approve, in that order. If the claimant's line is shorter, its highest person approves and the line ends there.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Order</th><th>Who approves</th><th>Use</th><th>Needed when the voucher total is above (<?= e($cur) ?>)</th></tr></thead>
    <tbody>
    <?php $n = 0; foreach ($types as $t => $label): $n++;
          $on = $def ? in_array($t, ['N1', 'OFFICE_FIN'], true) : array_key_exists($t, $cfg);
          $val = (!$def && array_key_exists($t, $cfg) && $cfg[$t] !== null) ? rtrim(rtrim(number_format($cfg[$t], 2, '.', ''), '0'), '.') : ''; ?>
      <tr>
        <td><?= $n ?></td>
        <td><strong><?= e($label) ?></strong><br><span class="muted" style="font-size:12px"><?php
          if ($t === 'N1') echo 'the person the claimant reports to';
          elseif ($t === 'OFFICE_FIN') echo 'Finance people whose offices include the voucher\'s office';
          elseif ($t === 'CENTRAL_FIN') echo 'Finance people who cover all offices';
          else echo 'that person\'s own "Reports to", one step further up';
        ?></span></td>
        <td><label class="chk" style="margin:0"><input type="checkbox" name="use[<?= e($t) ?>]" value="1" <?= $on ? 'checked' : '' ?> aria-label="Use <?= e($label) ?>"></label></td>
        <td><input class="form-control" style="max-width:160px" type="number" min="0" step="1" name="above[<?= e($t) ?>]" value="<?= e($val) ?>" placeholder="always" aria-label="<?= e($label) ?>: needed above this total"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="submit">Save the levels</button>
    <?php if (!$def): ?><button class="btn secondary" type="submit" name="reset" value="1" onclick="return confirm('Go back to the default: N+1, then Finance?')">Back to the default</button><?php endif; ?>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:12.5px">A change applies from the next approval step of each voucher; levels already signed stay signed.</p>
</form>

<div class="panel" style="max-width:860px">
  <h3 class="tab-sub" style="margin-top:0">Someone is away?</h3>
  <p style="margin:4px 0">Set who approves in their place, for a stated period: <a href="/voucher-delegations">who covers for a voucher approver</a>. A company-wide delegation (Approvals → Delegation) covers vouchers too.</p>
</div>
