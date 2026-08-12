<?php // Vendor qualification register — every vendor with its assessment score & approval status ?>
<?php
  $statusPill = function($st){
    switch($st){
      case 'APPROVED': return ['p-ok','Approved'];
      case 'CONDITIONAL': return ['p-warn','Approved w/ conditions'];
      case 'EXPIRED': return ['p-bad','Approval expired'];
      case 'SUSPENDED': return ['p-bad','Suspended'];
      case 'BLACKLISTED': return ['p-bad','Blacklisted'];
      case 'UNDER_ASSESSMENT': return ['p-info','Under assessment'];
      default: return ['p-mut','Prospect'];
    }
  };
  $scoreCol = function($s){ $s=(float)$s; if($s>=90) return '#15803d'; if($s>=75) return '#2563eb'; if($s>=60) return '#b45309'; if($s>=40) return '#c2410c'; return 'var(--bad)'; };
?>
<div class="crumbs"><a href="/">Home</a> › Vendors</div>
<div class="master-head">
  <div><h1>Vendor register</h1>
    <p class="sub" style="margin:2px 0 0">Approved-vendor list with the latest assessment score, approval status and re-assessment due date. <?= (int)$counts['total'] ?> vendor(s).</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/documents">Reports</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Total vendors</div><div class="k-val"><?= (int)$counts['total'] ?></div><div class="k-sub">on record</div></div>
  <div class="kpi"><div class="k-lab">Approved</div><div class="k-val up"><?= (int)$counts['approved'] ?></div><div class="k-sub">+ <?= (int)$counts['conditional'] ?> conditional</div></div>
  <div class="kpi"><div class="k-lab">Not yet assessed</div><div class="k-val"><?= (int)$counts['pending'] ?></div><div class="k-sub">prospect · under assessment</div></div>
  <div class="kpi"><div class="k-lab">Re-assessment due</div><div class="k-val <?= (int)$counts['due']>0?'down':'' ?>"><?= (int)$counts['due'] ?></div><div class="k-sub">past the due date</div></div>
</div>

<form method="get" action="/vendors" class="chip-row" style="margin:10px 0;gap:6px;flex-wrap:wrap">
  <select class="form-control" name="status" onchange="this.form.submit()" style="max-width:190px"><option value="">All statuses</option>
    <?php foreach ($statusOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fs===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <select class="form-control" name="risk" onchange="this.form.submit()" style="max-width:150px"><option value="">All risk</option>
    <?php foreach ($riskOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fr===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <select class="form-control" name="vtype" onchange="this.form.submit()" style="max-width:170px"><option value="">All types</option>
    <?php foreach ($typeOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $ftp===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name / code" style="min-width:200px">
  <button class="btn small" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Vendor</th><th>Type</th><th>Category</th><th>Risk</th><th>Score</th><th>Status</th><th>Assessed</th><th>Re-assess by</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted" style="padding:16px">No vendors on record. A partner marked as a vendor in the directory appears here; issue a Vendor Assessment to score and approve it.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): [$pc,$pl] = $statusPill($r['approval_status']); ?>
      <tr onclick="location.href='/vendor-profile?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($r['display_name'] ?: $r['legal_name']) ?></strong><?php if ($r['code']): ?> <span class="muted" style="font-size:11px"><?= e($r['code']) ?></span><?php endif; ?></td>
        <td><?= e($typeOpts[$r['vendor_type']] ?? ($r['vendor_type'] ?: '—')) ?></td>
        <td><?= e($r['product_category'] ? (lk_options_or('vendor_product_category',[])[$r['product_category']] ?? $r['product_category']) : '—') ?></td>
        <td><?php $rk=$r['risk_class']; ?><?= $rk ? '<span class="pill '.($rk==='CRITICAL'||$rk==='HIGH'?'p-bad':($rk==='MEDIUM'?'p-warn':'p-mut')).'">'.e($riskOpts[$rk] ?? $rk).'</span>' : '—' ?></td>
        <td><?php if ($r['last_score']!==null && $r['last_score']!==''): $c=$scoreCol($r['last_score']); ?><span style="font-weight:700;color:<?= $c ?>;font-variant-numeric:tabular-nums"><?= e(rtrim(rtrim(number_format((float)$r['last_score'],1),'0'),'.')) ?></span><span class="muted" style="font-size:11px">/100</span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><span class="pill <?= $pc ?>"><?= e($pl) ?></span></td>
        <td><?= e($r['last_assessed_on'] ?: '—') ?></td>
        <td><?php $re=$r['reassess_on']; $over = $re && $re < $today && in_array($r['approval_status'],['APPROVED','CONDITIONAL'],true); ?>
          <?= $re ? '<span'.($over?' style="color:var(--bad);font-weight:600"':'').'>'.e($re).($over?' ⚠':'').'</span>' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
