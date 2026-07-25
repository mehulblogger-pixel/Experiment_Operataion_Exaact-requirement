<?php
  $tone = ['PENDING'=>'p-warn','ENDORSED'=>'p-info','APPROVED'=>'p-ok','REFUSED'=>'p-bad'];
  $open = array_values(array_filter($rows, fn($r) => in_array($r['status'], ['PENDING','ENDORSED'], true)));
  $done = array_values(array_filter($rows, fn($r) => !in_array($r['status'], ['PENDING','ENDORSED'], true)));
  $waitingMe = array_values(array_filter($open, fn($r) =>
      ($r['status'] === 'PENDING' && $canEndorse) || ($r['status'] === 'ENDORSED' && $canGrant)));
?>
<div class="crumbs"><a href="/">Home</a> › Contract exceptions</div>
<div class="master-head">
  <div><h1>Contract exceptions</h1>
    <p class="sub" style="margin:2px 0 0">Requests to schedule work against an order whose contract has expired,
      or whose quantity is used up. Two signatures: a Branch Manager endorses that it is genuinely needed, then the
      Super Admin decides whether the company carries it.</p></div>
  <a class="btn secondary" href="/calls">← <?= e(THP('call')) ?></a>
</div>

<div class="kpi-row">
  <div class="kpi tone-warn"><span class="kic">✋</span><span class="k-lab">Waiting on you</span>
    <span class="k-val"><?= count($waitingMe) ?></span><span class="k-sub">to endorse or grant</span></div>
  <div class="kpi tone-info"><span class="kic">◷</span><span class="k-lab">Open</span>
    <span class="k-val"><?= count($open) ?></span><span class="k-sub">somewhere in the chain</span></div>
  <div class="kpi tone-ok"><span class="kic">✔</span><span class="k-lab">Granted</span>
    <span class="k-val"><?= count(array_filter($rows, fn($r) => $r['status'] === 'APPROVED')) ?></span><span class="k-sub">exceptions in force</span></div>
  <div class="kpi tone-bad"><span class="kic">✕</span><span class="k-lab">Refused</span>
    <span class="k-val"><?= count(array_filter($rows, fn($r) => $r['status'] === 'REFUSED')) ?></span><span class="k-sub">turned down</span></div>
</div>

<?php if (!$open): ?>
  <div class="panel"><p class="muted">Nothing outstanding. An exception appears here when a coordinator is stopped from
    allocating against an expired or exhausted order and asks for one.</p></div>
<?php else: ?>
  <?php foreach ($open as $r): ?>
  <div class="panel" style="border:1px solid var(--<?= $r['status']==='PENDING' ? 'warn' : 'info' ?>)">
    <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap">
      <b><?= e(OVERRIDE_KINDS[$r['kind']] ?? $r['kind']) ?></b>
      <span class="pill <?= e($tone[$r['status']] ?? 'p-mut') ?>"><?= e(OVERRIDE_STATUS[$r['status']] ?? $r['status']) ?></span>
      <span class="muted">
        <?php if ($r['quotation_id']): ?><a href="/quote?id=<?= (int)$r['quotation_id'] ?>"><?= e(quote_label($r)) ?></a> · <?php endif; ?>
        <?= e($r['client_name'] ?: '—') ?><?= $r['contract_number'] ? ' · contract ' . e($r['contract_number']) : '' ?>
      </span>
    </div>
    <div style="margin-top:6px"><b>Asked by</b> <?= e($r['requested_by']) ?>
      <span class="muted"><?= $r['requested_at'] ? 'on ' . e(fdate(substr((string)$r['requested_at'],0,10))) : '' ?></span></div>
    <div class="panel" style="margin:8px 0 0;background:var(--soft)"><?= e($r['reason']) ?></div>
    <?php if ($r['endorsed_by']): ?>
      <div class="muted" style="margin-top:6px">Endorsed by <?= e($r['endorsed_by']) ?>
        <?= $r['endorsed_at'] ? 'on ' . e(fdate(substr((string)$r['endorsed_at'],0,10))) : '' ?>
        <?= $r['endorse_note'] ? '— “' . e($r['endorse_note']) . '”' : '' ?></div>
    <?php endif; ?>

    <?php // Each step is offered only to the person whose step it is. ?>
    <?php if ($r['status'] === 'PENDING' && $canEndorse): ?>
      <form method="post" action="/contract-override" style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <div class="ff" style="margin:0;flex:1;min-width:240px"><label>Your note to the Super Admin</label>
          <input class="form-control" name="note" placeholder="e.g. client has confirmed the renewal in writing"></div>
        <button class="btn small" name="do" value="endorse" type="submit">Endorse</button>
        <button class="btn small danger" name="do" value="refuse" type="submit">Refuse</button>
      </form>
    <?php elseif ($r['status'] === 'ENDORSED' && $canGrant): ?>
      <form method="post" action="/contract-override" style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <div class="ff" style="margin:0"><label>Allocations allowed</label>
          <input class="form-control" type="number" name="uses_allowed" value="<?= (int)$r['uses_allowed'] ?>" min="1" max="99" style="width:110px"></div>
        <div class="ff" style="margin:0"><label>Valid until <span class="muted">— optional</span></label>
          <input class="form-control" type="date" name="valid_until"></div>
        <div class="ff" style="margin:0;flex:1;min-width:200px"><label>Note</label><input class="form-control" name="note"></div>
        <button class="btn small" name="do" value="grant" type="submit">Grant</button>
        <button class="btn small danger" name="do" value="refuse" type="submit">Refuse</button>
      </form>
    <?php else: ?>
      <p class="muted" style="margin:10px 0 0">
        <?= $r['status'] === 'PENDING' ? 'Waiting for a Branch Manager to endorse it.' : 'Waiting for the Super Admin to decide.' ?>
      </p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($done): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:14px 18px 0"><h3 class="tab-sub" style="margin:0">Decided</h3></div>
  <div style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Order</th><th>Reason for the block</th><th>Asked by</th><th>Outcome</th><th>Used</th><th>Decided by</th></tr></thead>
    <tbody>
    <?php foreach ($done as $r): ?>
      <tr>
        <td><?php if ($r['quotation_id']): ?><a href="/quote?id=<?= (int)$r['quotation_id'] ?>"><?= e(quote_label($r)) ?></a><?php else: ?>—<?php endif; ?>
            <div class="muted" style="font-size:11.5px"><?= e($r['client_name'] ?: '') ?></div></td>
        <td><?= e(OVERRIDE_KINDS[$r['kind']] ?? $r['kind']) ?>
            <div class="muted" style="font-size:11.5px"><?= e($r['reason']) ?></div></td>
        <td><?= e($r['requested_by']) ?></td>
        <td><span class="pill <?= e($tone[$r['status']] ?? 'p-mut') ?>"><?= e(OVERRIDE_STATUS[$r['status']] ?? $r['status']) ?></span></td>
        <td class="num"><?= $r['status'] === 'APPROVED' ? (int)$r['uses_taken'] . ' / ' . (int)$r['uses_allowed'] : '—' ?>
            <?= ($r['valid_until'] ?? '') !== '' ? '<div class="muted" style="font-size:11.5px">to ' . e(fdate($r['valid_until'])) . '</div>' : '' ?></td>
        <td><?= e($r['decided_by'] ?: '—') ?>
            <?= $r['decide_note'] ? '<div class="muted" style="font-size:11.5px">“' . e($r['decide_note']) . '”</div>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>
