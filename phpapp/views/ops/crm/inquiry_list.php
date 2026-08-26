<?php $stPill = ['OPEN'=>'p-warn','QUOTED'=>'p-ok','DROPPED'=>'p-mut']; ?>
<div class="master-head">
  <div><h1><?= e(T_REG('inquiry')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Received via email / phone — the front of the sales funnel. <?= count($rows) ?> shown.</p></div>
  <?php if (can('mod.inquiries.edit')): ?><a class="btn" href="/inquiry-new">+ New inquiry</a><?php endif; ?>
</div>

<?php // Module 19 — inquiries left open past the response service level, un-quoted.
$dueCount = $dueCount ?? 0; $sla = $sla ?? 7; ?>
<?php if (!empty($dueCount)): ?>
<div class="panel" style="border-left:3px solid var(--bad);margin-top:12px">
  <b>🔔 <?= (int)$dueCount ?> inquiry<?= $dueCount==1?'':'s' ?> waiting for a quotation</b>
  <span class="muted" style="font-size:12.5px">— open more than <?= (int)$sla ?> days with no quote raised. A customer who
  asked for a price and heard nothing is the fastest lost sale. See the ranked list on the
  <a href="/advisor">business advisor</a>, or the ⏳ rows below.</span>
</div>
<?php endif; ?>

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
    <thead><tr><th>Inquiry</th><th>Received</th><th>Client</th><th>Subject</th><th><?= e(T('sbu')) ?></th><th>Owner</th><th>Source</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['inquiry_no']) ?></b>
        <?php if (!empty($r['origin_ref'])): ?><div class="muted" style="font-size:12px">from lead <?= e($r['origin_ref']) ?></div><?php endif; ?></td>
      <td><?= e($r['received_date'] ?: '—') ?></td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><?= e($r['subject'] ?: '—') ?></td>
      <td><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?: '—') ?></td>
      <td class="muted"><?= e(trim((string)($r['owner_first'] ?? '') . ' ' . (string)($r['owner_last'] ?? '')) ?: ($r['owner_username'] ?? '—')) ?></td>
      <td><?= e(lk_options_or('inquiry_source', INQUIRY_SOURCES)[$r['source']] ?? $r['source']) ?></td>
      <td><span class="pill <?= $stPill[$r['status']] ?? 'p-mut' ?>"><?= e(lk_options_or('inquiry_status', INQUIRY_STATUS)[$r['status']] ?? $r['status']) ?></span><?php
        // Module 19 — an OPEN inquiry past the response service level with no quote.
        if ($r['status']==='OPEN') { $base = trim((string)($r['received_date'] ?? '')) ?: substr((string)($r['created_at'] ?? ''),0,10);
          if ($base!=='') { $age=(int)floor((strtotime(date('Y-m-d'))-strtotime($base))/86400); if ($age > (int)$sla) echo ' <span class="pill p-bad" title="Open '.$age.' days, no quotation">⏳ '.$age.'d</span>'; } } ?></td>
      <td class="num" style="white-space:nowrap">
        <?php if (can('mod.inquiries.edit')): ?><a class="btn small secondary" href="/inquiry-edit?id=<?= (int)$r['id'] ?>">Edit</a><?php endif; ?>
        <?php if (can('mod.quotes.edit') && $r['status']!=='DROPPED'): ?><a class="btn small" href="/quote-new?inquiry=<?= (int)$r['id'] ?>">Quote</a><?php endif; ?>
        <?php if ((is_admin_level() || is_master()) && $r['status']!=='QUOTED'): ?>
          <form method="post" action="/inquiry-delete" style="display:inline" onsubmit="return confirm('Delete inquiry <?= e($r['inquiry_no']) ?>? This cannot be undone.')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn small danger" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;padding:24px" class="muted">No inquiries yet<?= can('mod.inquiries.edit')?' — click "New inquiry" to log the first one':'' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
