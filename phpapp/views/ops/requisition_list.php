<div class="crumbs"><a href="/">Home</a> › <a href="/recruitment">Recruitment</a> › <?= e(TP('requisition')) ?></div>
<div class="master-head">
  <div><h1><?= e(T_REG('requisition')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Management-approved positions (new or replacement). Every hire must be raised against one of these.</p></div>
  <?php if (is_coordinator_level()): ?><a class="btn" href="/requisition-new">➕ New requisition</a><?php endif; ?>
</div>
<form method="get" action="/requisitions" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="🔍 Search code / designation / site…">
  <button class="btn secondary" type="submit">Search</button>
</form>
<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">📋</div>
      <p class="muted" style="margin:8px 0 0">No requisitions yet. <?php if (is_coordinator_level()): ?><a href="/requisition-new">Raise the first</a>.<?php endif; ?></p></div>
  <?php else: ?>
  <table class="dt">
    <thead><tr><th>Req</th><th>Position</th><th>Type</th><th>Office</th><th class="num">Budget</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $stCls = ['OPEN'=>'p-warn','PROPOSED'=>'p-info','OFFERED'=>'p-info','HIRED'=>'p-ok','CLOSED'=>'p-ok','CANCELLED'=>'p-mut'][$r['status']] ?? 'p-mut'; ?>
      <tr>
        <td><a href="/requisition?id=<?= (int)$r['id'] ?>"><b><?= e($r['req_code']) ?></b></a></td>
        <td><?= e(DESIGNATIONS[$r['designation']] ?? ($r['designation'] ?: '—')) ?><?= $r['project_site'] ? '<br><span class="muted" style="font-size:12px">'.e($r['project_site']).'</span>' : '' ?></td>
        <td><?= $r['req_type']==='REPLACEMENT' ? '<span class="pill p-warn">Replacement</span>' : '<span class="pill p-info">New</span>' ?><?= $r['outgoing_name'] ? '<br><span class="muted" style="font-size:12px">↳ '.e($r['outgoing_name']).'</span>' : '' ?></td>
        <td><?= e($r['office_name'] ?: '—') ?></td>
        <td class="num"><?= $r['budgeted_cost']>0 ? fmoney($r['budgeted_cost']) : '<span class="muted">—</span>' ?></td>
        <td><span class="pill <?= $stCls ?>"><?= e(lk_options_or('requisition_status', REQ_STATUS)[$r['status']] ?? $r['status']) ?></span><?= $r['hired_name'] ? '<br><span class="muted" style="font-size:12px">'.e($r['hired_name']).'</span>' : '' ?></td>
        <td><a class="btn small secondary" href="/requisition?id=<?= (int)$r['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
