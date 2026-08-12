<?php // Release Note register — Release Notes only, with the linked inspection report ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › Release Notes</div>
<div class="master-head">
  <div><h1>Release Notes</h1>
    <p class="sub" style="margin:2px 0 0">Every Release Note, with the inspection report it was raised against and its release status. <?= (int)($counts['total'] ?? 0) ?> Release Note(s).</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/documents"><?= e(T_REG('report')) ?></a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Total</div><div class="k-val"><?= (int)($counts['total'] ?? 0) ?></div><div class="k-sub">Release Notes</div></div>
  <div class="kpi"><div class="k-lab">Open / in progress</div><div class="k-val"><?= (int)($counts['open_n'] ?? 0) ?></div><div class="k-sub">draft · submitted · review</div></div>
  <div class="kpi"><div class="k-lab">Approved / issued</div><div class="k-val up"><?= (int)($counts['issued_n'] ?? 0) ?></div><div class="k-sub">finalized</div></div>
</div>

<form method="get" action="/release-notes" class="chip-row" style="margin:10px 0;gap:6px">
  <select class="form-control" name="status" onchange="this.form.submit()" style="max-width:170px"><option value="">All statuses</option>
    <?php foreach (lk_options_or('report_status', IDEMS_STATUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($fs ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search RN No. / title / project" style="min-width:220px">
  <button class="btn small" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Release Note No.</th><th>Against inspection report</th><th>Client</th><th>Vendor / Mfr</th><th>Date</th><th>Release</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No Release Notes yet. Open an approved inspection report and click <strong>Draft Release Note</strong> to raise the first one.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr onclick="location.href='/document?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($r['irn']) ?></strong></td>
        <td onclick="event.stopPropagation()">
          <?php if (!empty($r['source_report_id'])): ?>
            <a href="/document?id=<?= (int)$r['source_report_id'] ?>"><?= e($r['source_irn'] ?: '—') ?></a>
          <?php else: ?>
            <?= e($r['source_irn'] ?: '—') ?>
          <?php endif; ?>
        </td>
        <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
        <td><?= e($r['vendor_disp'] ?: $r['vendor_name'] ?: '—') ?></td>
        <td><?= e($r['inspection_date'] ?: '—') ?></td>
        <td><?php $rs = $r['release_status'] ?? ''; $rl = lk_options_or('release_status', IDEMS_RELEASE)[$rs] ?? $rs; ?>
          <?= $rl ? '<span class="pill '.($rs==='RELEASED'?'p-ok':($rs==='NOT_RELEASED'?'p-bad':'p-mut')).'">'.e($rl).'</span>' : '—' ?></td>
        <td><span class="pill <?= idems_status_pill($r['status']) ?>"><?= e(lk_options_or('report_status', IDEMS_STATUS)[$r['status']] ?? $r['status']) ?></span><?= $r['finalized'] ? ' 🔒' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
