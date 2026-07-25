<?php // IDEMS Document Register ?>
<div class="master-head">
  <div><h1><?= e(T_REG('report')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Every inspection report &amp; certificate, with its IRN and status. <?= (int)($counts['total'] ?? 0) ?> document(s).</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if (is_master() || can('idems.type.manage') || can('master.manage')): ?><a class="btn secondary" href="/report-types">Report types</a><?php endif; ?>
    <?php if (is_master() || can('idems.type.manage')): ?><a class="btn secondary" href="/irn-rules">IRN rules</a><?php endif; ?>
    <?php if (is_master() || can('idems.audit.view')): ?><a class="btn secondary" href="/audit-log">Audit log</a><?php endif; ?>
    <?php if (is_master() || can('mod.idems.edit')): ?><a class="btn" href="/document-new">+ New report</a><?php endif; ?>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Total</div><div class="k-val"><?= (int)($counts['total'] ?? 0) ?></div><div class="k-sub">in register</div></div>
  <div class="kpi"><div class="k-lab">Open / in progress</div><div class="k-val"><?= (int)($counts['open_n'] ?? 0) ?></div><div class="k-sub">draft · submitted · review</div></div>
  <div class="kpi"><div class="k-lab">Approved / issued</div><div class="k-val up"><?= (int)($counts['issued_n'] ?? 0) ?></div><div class="k-sub">finalized</div></div>
</div>

<form method="get" action="/documents" class="chip-row" style="margin:10px 0;gap:6px">
  <select class="form-control" name="type" onchange="this.form.submit()" style="max-width:200px"><option value="">All types</option>
    <?php foreach ($types as $t): ?><option value="<?= e($t['code']) ?>" <?= $ft===$t['code']?'selected':'' ?>><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?>
  </select>
  <select class="form-control" name="status" onchange="this.form.submit()" style="max-width:170px"><option value="">All statuses</option>
    <?php foreach (lk_options_or('report_status', IDEMS_STATUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fs===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search IRN / title / project" style="min-width:200px">
  <button class="btn small" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>IRN</th><th>Type</th><th>Title</th><th>Client</th><th>Inspector</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No reports yet. Click <strong>+ New report</strong> to create the first one.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr onclick="location.href='/document?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($r['irn']) ?></strong></td>
        <td><span class="pill p-info"><?= e($r['type_code']) ?></span></td>
        <td><?= e($r['title']) ?></td>
        <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
        <td><?= e($r['inspector_name'] ?: '—') ?></td>
        <td><?= e($r['inspection_date'] ?: '—') ?></td>
        <td><span class="pill <?= idems_status_pill($r['status']) ?>"><?= e(lk_options_or('report_status', IDEMS_STATUS)[$r['status']] ?? $r['status']) ?></span><?= $r['finalized'] ? ' 🔒' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
