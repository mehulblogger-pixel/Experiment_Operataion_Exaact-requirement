<?php // Manufacturer document endorsement register ?>
<div class="master-head">
  <div><h1><?= e(T_REG('endorsement')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Manufacturer / vendor quality records reviewed &amp; endorsed by the TPIA. The original is kept unaltered. <?= (int)($counts['total'] ?? 0) ?> record(s).</p></div>
  <?php if (is_master() || can('mod.idems.edit')): ?><a class="btn" href="/endorsement-new">+ New endorsement</a><?php endif; ?>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Total</div><div class="k-val"><?= (int)($counts['total'] ?? 0) ?></div><div class="k-sub">in register</div></div>
  <div class="kpi"><div class="k-lab">In progress</div><div class="k-val"><?= (int)($counts['open_n'] ?? 0) ?></div><div class="k-sub">uploaded / under review</div></div>
  <div class="kpi"><div class="k-lab">Endorsed</div><div class="k-val up"><?= (int)($counts['endorsed_n'] ?? 0) ?></div><div class="k-sub">approved</div></div>
  <div class="kpi"><div class="k-lab">Rejected</div><div class="k-val down"><?= (int)($counts['rejected_n'] ?? 0) ?></div><div class="k-sub">returned</div></div>
</div>

<form method="get" action="/endorsements" class="chip-row" style="margin:10px 0;gap:6px">
  <select class="form-control" name="type" onchange="this.form.submit()" style="max-width:210px"><option value="">All document types</option>
    <?php foreach (ENDORSE_DOC_TYPES as $k=>$v): ?><option value="<?= e($k) ?>" <?= $ft===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <select class="form-control" name="status" onchange="this.form.submit()" style="max-width:160px"><option value="">All statuses</option>
    <?php foreach (ENDORSE_STATUS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fs===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search no. / title / heat no." style="min-width:200px">
  <button class="btn small" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Endorsement no.</th><th>Type</th><th>Title</th><th>Vendor</th><th>Heat/Lot</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:16px">No endorsements yet. Click <strong>+ New endorsement</strong> to upload a manufacturer document for review.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr onclick="location.href='/endorsement?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($r['endorsement_no']) ?></strong></td>
        <td><span class="pill p-info"><?= e($r['doc_type']) ?></span></td>
        <td><?= e($r['title'] ?: '—') ?></td>
        <td><?= e($r['vendor_disp'] ?: $r['vendor_name'] ?: '—') ?></td>
        <td class="muted"><?= e($r['heat_no'] ?: '—') ?></td>
        <td><span class="pill <?= endorse_status_pill($r['status']) ?>"><?= e(ENDORSE_STATUS[$r['status']] ?? $r['status']) ?></span><?= $r['finalized'] ? ' 🔒' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
