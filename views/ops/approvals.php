<?php
// Two lists, and the split is the point: what is waiting on YOU, and what is
// waiting on somebody else. A single undifferentiated queue makes every person
// scan every row to find out whether any of it is theirs.
$act = $q['act']; $watch = $q['watch'];
$total = count($act) + count($watch);
$sumOf = function ($rows) { $t = 0.0; foreach ($rows as $r) $t += (float)$r['amount']; return $t; };
?>
<div class="crumbs"><a href="/">Home</a> › Approvals</div>
<div class="master-head">
  <div><h1>Approvals</h1>
  <p class="sub" style="margin:2px 0 0">Deals held at a stage until somebody with the authority agrees. A quotation of this size already needed an approver; the deal it belongs to did not, and the forecast is built from the deal.</p></div>
  <?php if ($can_manage): ?><a class="btn secondary" href="/stage-gates">Approval rules</a><?php endif; ?>
</div>

<div class="filter-bar" style="margin-top:12px">
  <?php foreach ($labels as $k => $l): ?>
    <a class="btn small <?= $status === $k ? '' : 'secondary' ?>" href="/approvals?f=<?= e($k) ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$total): ?>
  <div class="panel" style="margin-top:16px;text-align:center;padding:32px 16px">
    <div style="font-size:28px;line-height:1">✓</div>
    <p style="margin:8px 0 0"><b>Nothing <?= e(strtolower($labels[$status] ?? $status)) ?>.</b></p>
    <p class="muted" style="margin:6px 0 0">
      <?php if ($status === 'PENDING'): ?>
        No deal is being held. If you expected one to be, check the <a href="/stage-gates">approval rules</a> —
        a gate only applies where a rule matches the deal's value, business unit and destination stage.
      <?php else: ?>
        Nothing has been <?= e(strtolower($labels[$status] ?? $status)) ?> yet.
      <?php endif; ?>
    </p>
  </div>
<?php endif; ?>

<?php
$block = function ($rows, $title, $note, $showActions) use ($status) {
    if (!$rows) return;
    ?>
    <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
      <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
        <b style="font-size:13.5px"><?= e($title) ?></b>
        <span class="muted" style="font-size:12.5px"> — <?= count($rows) ?></span>
        <div class="muted" style="font-size:12.5px;margin-top:3px"><?= e($note) ?></div>
      </div>
      <div class="dt-scroll">
        <table class="dt">
          <caption class="sr-only"><?= e($title) ?></caption>
          <thead><tr>
            <th scope="col">Deal</th><th scope="col">Move</th>
            <th scope="col" style="text-align:right">Value</th>
            <th scope="col">Raised by</th><th scope="col">Waiting on</th><th scope="col"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <a href="/opportunity?id=<?= (int)$r['entity_id'] ?>"><b><?= e((string)($r['deal_name'] ?: 'Deal #' . (int)$r['entity_id'])) ?></b></a>
                <?php if (!empty($r['partner_name'])): ?><div class="muted" style="font-size:12px"><?= e((string)$r['partner_name']) ?></div><?php endif; ?>
              </td>
              <td>
                <span class="muted"><?= e((string)($r['from_name'] ?: '—')) ?></span> →
                <b><?= e((string)($r['to_name'] ?: '—')) ?></b>
                <?php if (($r['to_kind'] ?? '') === 'WON'): ?><span class="pill">Win</span><?php endif; ?>
                <?php if (($r['to_kind'] ?? '') === 'LOST'): ?><span class="pill warn">Loss</span><?php endif; ?>
                <?php if (trim((string)$r['note']) !== ''): ?>
                  <div class="muted" style="font-size:12px;margin-top:2px"><?= e((string)$r['note']) ?></div>
                <?php endif; ?>
              </td>
              <td style="text-align:right"><?= e(fmoney((float)$r['amount'])) ?></td>
              <td><?= e((string)$r['requested_by'] ?: '—') ?>
                  <div class="muted" style="font-size:12px"><?= trim((string)$r['requested_at']) !== '' ? e(fdate(substr((string)$r['requested_at'], 0, 10))) : '' ?></div></td>
              <td><?= e((string)$r['waiting_on']) ?></td>
              <td style="white-space:nowrap">
                <?php if ($status === 'PENDING' && $showActions): ?>
                  <form method="post" action="/approval-act" style="display:inline-flex;gap:6px;align-items:center">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="back" value="/approvals?f=<?= e($status) ?>">
                    <input class="form-control" style="width:180px" name="remarks" placeholder="Remark (required to send back)" maxlength="500">
                    <button class="btn small" name="decision" value="APPROVED">Approve</button>
                    <button class="btn small secondary" name="decision" value="REJECTED">Send back</button>
                  </form>
                <?php elseif ($status === 'PENDING' && !empty($r['is_mine'])): ?>
                  <form method="post" action="/approval-act" onsubmit="return confirm('Withdraw this request? The deal stays where it is.')">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="back" value="/approvals?f=<?= e($status) ?>">
                    <button class="btn small secondary" name="decision" value="CANCELLED">Withdraw</button>
                  </form>
                <?php elseif ($status !== 'PENDING'): ?>
                  <span class="muted" style="font-size:12.5px">
                    <?= e((string)$r['acted_by'] ?: '—') ?><?= trim((string)$r['remarks']) !== '' ? ' — ' . e((string)$r['remarks']) : '' ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
};

if ($status === 'PENDING') {
    $block($act, 'Waiting on you', 'Approving moves the deal immediately. Sending it back needs a reason — the person who raised it has to know what to change.', true);
    $block($watch, 'Waiting on somebody else', 'Shown so a deal that has stopped moving is never a mystery. You cannot approve your own request, which is what the rule exists for.', false);
} else {
    $block(array_merge($act, $watch), $labels[$status] ?? $status, 'What was decided, by whom, and why.', false);
}
?>

<?php if ($status === 'PENDING' && $act): ?>
  <p class="muted" style="margin-top:12px;font-size:12.5px">
    <?= count($act) ?> waiting on you, worth <?= e(fmoney($sumOf($act))) ?> in total.
  </p>
<?php endif; ?>
