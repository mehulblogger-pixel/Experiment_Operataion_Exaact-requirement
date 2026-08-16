<?php $stPill = ['OPEN'=>'p-warn','QUOTED'=>'p-ok','DROPPED'=>'p-mut']; ?>
<div class="master-head">
  <div><h1><?= e(T_REG('inquiry')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Received via email / phone — the front of the sales funnel. <?= count($rows) ?> shown.</p></div>
  <?php if (can('mod.inquiries.edit')): ?><a class="btn" href="/inquiry-new">+ New inquiry</a><?php endif; ?>
</div>

<form method="get" action="/inquiries" class="filter-bar" style="gap:8px;flex-wrap:wrap">
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search no. / subject / client" style="min-width:220px">
  <select class="form-control" name="st" onchange="this.form.submit()"><option value="">All statuses</option>
    <?php foreach (lk_options_or('inquiry_status', INQUIRY_STATUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <?= fy_select_html($fy ?? current_fy()) ?>
  <button class="btn small" type="submit">Search</button>
  <?php if ($q!==''||$st!==''||($fy ?? current_fy())!==current_fy()): ?><a class="btn small secondary" href="/inquiries">Reset</a><?php endif; ?>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Inquiry</th><th>Received</th><th>Client</th><th>Subject</th><th><?= e(T('sbu')) ?></th><th>Source</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['inquiry_no']) ?></b></td>
      <td><?= e($r['received_date'] ?: '—') ?></td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><?= e($r['subject'] ?: '—') ?></td>
      <td><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?: '—') ?></td>
      <td><?= e(lk_options_or('inquiry_source', INQUIRY_SOURCES)[$r['source']] ?? $r['source']) ?></td>
      <td><span class="pill <?= $stPill[$r['status']] ?? 'p-mut' ?>"><?= e(lk_options_or('inquiry_status', INQUIRY_STATUS)[$r['status']] ?? $r['status']) ?></span></td>
      <td class="num" style="white-space:nowrap">
        <?php if (can('mod.inquiries.edit')): ?><a class="btn small secondary" href="/inquiry-edit?id=<?= (int)$r['id'] ?>">Edit</a><?php endif; ?>
        <?php if (can('mod.quotes.edit') && $r['status']!=='DROPPED'): ?><a class="btn small" href="/quote-new?inquiry=<?= (int)$r['id'] ?>">Quote</a><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;padding:24px" class="muted">No inquiries yet<?= can('mod.inquiries.edit')?' — click "New inquiry" to log the first one':'' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
