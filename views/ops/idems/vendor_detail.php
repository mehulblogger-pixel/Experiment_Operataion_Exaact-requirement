<?php // Vendor qualification profile — attributes (editable) + assessment history ?>
<?php
  $p = $profile ?: [];
  $st = $p['approval_status'] ?? 'PROSPECT';
  $statusPill = function($st){
    switch($st){
      case 'APPROVED': return ['p-ok','Approved'];
      case 'CONDITIONAL': return ['p-warn','Approved with conditions'];
      case 'EXPIRED': return ['p-bad','Approval expired'];
      case 'SUSPENDED': return ['p-bad','Suspended'];
      case 'BLACKLISTED': return ['p-bad','Blacklisted'];
      case 'UNDER_ASSESSMENT': return ['p-info','Under assessment'];
      default: return ['p-mut','Prospect'];
    }
  };
  [$pc,$pl] = $statusPill($st);
  $scoreCol = function($s){ $s=(float)$s; if($s>=90) return '#15803d'; if($s>=75) return '#2563eb'; if($s>=60) return '#b45309'; if($s>=40) return '#c2410c'; return 'var(--bad)'; };
  $today = date('Y-m-d');
  $vname = $partner['display_name'] ?: $partner['legal_name'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/vendors">Vendors</a> › <?= e($vname) ?></div>
<div class="master-head">
  <div><h1><?= e($vname) ?> <span class="pill <?= $pc ?>" style="font-size:12px;vertical-align:middle"><?= e($pl) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><?php if ($partner['code']): ?><?= e($partner['code']) ?> · <?php endif; ?>Vendor qualification profile</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap"><a class="btn secondary" href="/vendors">← Register</a></div>
</div>

<div class="two-col" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start">

  <?php // ---- Latest assessment result ---- ?>
  <div class="panel">
    <h3 style="margin:0 0 8px">Latest assessment</h3>
    <?php if (($p['last_score'] ?? null) !== null && ($p['last_score'] ?? '') !== ''): $c=$scoreCol($p['last_score']); ?>
      <div style="display:flex;align-items:center;gap:16px">
        <div style="text-align:center"><div style="font-size:30px;font-weight:800;line-height:1;color:<?= $c ?>;font-variant-numeric:tabular-nums"><?= e(rtrim(rtrim(number_format((float)$p['last_score'],1),'0'),'.')) ?><span style="font-size:14px;color:var(--muted)">/100</span></div>
          <div style="font-size:11.5px;font-weight:700;color:<?= $c ?>;margin-top:2px"><?= e(strtoupper($p['last_band'] ?? '')) ?></div></div>
        <div style="font-size:12.5px;line-height:1.7">
          <div><span class="muted">Assessed:</span> <?= e($p['last_assessed_on'] ?: '—') ?></div>
          <div><span class="muted">Approved on:</span> <?= e($p['approved_on'] ?: '—') ?></div>
          <div><span class="muted">Valid until:</span> <?= e($p['valid_until'] ?: '—') ?></div>
          <div><span class="muted">Re-assess by:</span> <?php $re=$p['reassess_on']??''; $over=$re && $re<$today; ?><span<?= $over?' style="color:var(--bad);font-weight:600"':'' ?>><?= e($re ?: '—') ?><?= $over?' ⚠ overdue':'' ?></span></div>
        </div>
      </div>
    <?php else: ?>
      <p class="muted" style="margin:0">No assessment issued yet. Raise a <strong>Vendor Assessment Report</strong> against this vendor and issue it — its score and approval status will appear here automatically.</p>
    <?php endif; ?>
  </div>

  <?php // ---- Editable attributes ---- ?>
  <div class="panel">
    <h3 style="margin:0 0 8px">Classification</h3>
    <?php if ($canEdit): ?>
    <form method="post" action="/vendor-profile-save">
      <input type="hidden" name="partner_id" value="<?= (int)$partner['id'] ?>">
      <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:8px">
        <div class="ff"><label>Vendor type</label>
          <select class="form-control" name="vendor_type"><option value="">—</option>
            <?php foreach ($typeOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p['vendor_type']??'')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Product / service category</label>
          <select class="form-control" name="product_category"><option value="">—</option>
            <?php foreach ($catOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p['product_category']??'')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Risk class</label>
          <select class="form-control" name="risk_class"><option value="">—</option>
            <?php foreach ($riskOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p['risk_class']??'')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Approval status <span class="muted">(usually set by assessment)</span></label>
          <select class="form-control" name="approval_status">
            <?php foreach ($statusOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="ff" style="margin-top:8px"><label>Reason for status change <span class="muted">(recorded on the timeline)</span></label><input class="form-control" name="status_reason" placeholder="e.g. Suspended pending CAPA closure"></div>
      <div class="ff" style="margin-top:8px"><label>Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($p['notes'] ?? '') ?></textarea></div>
      <div style="margin-top:8px"><button class="btn" type="submit">Save profile</button></div>
    </form>
    <?php else: ?>
      <div style="font-size:12.5px;line-height:1.9">
        <div><span class="muted">Type:</span> <?= e($typeOpts[$p['vendor_type']??''] ?? '—') ?></div>
        <div><span class="muted">Category:</span> <?= e($catOpts[$p['product_category']??''] ?? '—') ?></div>
        <div><span class="muted">Risk class:</span> <?= e($riskOpts[$p['risk_class']??''] ?? '—') ?></div>
        <?php if (trim((string)($p['notes']??''))!==''): ?><div style="margin-top:4px"><span class="muted">Notes:</span> <?= e($p['notes']) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php // ---- Vendor 360 — live performance from operational data ---- ?>
<?php if (!empty($perf)): $pcol = $scoreCol($perf['score']);
  $resPill = ['ACCEPTED'=>['p-ok','Accepted'],'ACCEPTED_COND'=>['p-ok','Accepted (obs.)'],'REJECTED'=>['p-bad','Rejected'],'HOLD'=>['p-warn','Hold'],'NA'=>['p-mut','—']];
?>
<div class="panel" style="margin-top:14px;border:1px solid <?= $pcol ?>;background:color-mix(in srgb,<?= $pcol ?> 4%,transparent)">
  <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div style="text-align:center;min-width:104px">
      <div style="font-size:13px;font-weight:700;letter-spacing:.03em;color:var(--muted)">PERFORMANCE</div>
      <div style="font-size:34px;font-weight:800;line-height:1.1;color:<?= $pcol ?>;font-variant-numeric:tabular-nums"><?= e(rtrim(rtrim(number_format((float)$perf['score'],1),'0'),'.')) ?><span style="font-size:14px;color:var(--muted)">/100</span></div>
      <div style="font-size:12px;font-weight:700;color:<?= $pcol ?>"><?= e(strtoupper($perf['band'])) ?></div>
    </div>
    <div style="flex:1;min-width:280px;display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
      <div><div class="muted" style="font-size:11px">Acceptance rate</div><div style="font-size:18px;font-weight:700"><?= $perf['reports']['acceptance_rate']===null?'—':e(rtrim(rtrim(number_format((float)$perf['reports']['acceptance_rate'],1),'0'),'.')).'%' ?></div><div class="muted" style="font-size:11px"><?= (int)$perf['reports']['accepted'] ?> ok · <?= (int)$perf['reports']['rejected'] ?> rej of <?= (int)$perf['reports']['graded'] ?></div></div>
      <div><div class="muted" style="font-size:11px">Open NCRs</div><div style="font-size:18px;font-weight:700;color:<?= $perf['ncr']['open']>0?'var(--bad)':'inherit' ?>"><?= (int)$perf['ncr']['open'] ?></div><div class="muted" style="font-size:11px"><?= (int)$perf['ncr']['overdue'] ?> overdue · <?= (int)$perf['ncr']['major_open'] ?> major</div></div>
      <div><div class="muted" style="font-size:11px">Open complaints</div><div style="font-size:18px;font-weight:700;color:<?= $perf['complaints']['open']>0?'var(--bad)':'inherit' ?>"><?= (int)$perf['complaints']['open'] ?></div><div class="muted" style="font-size:11px">of <?= (int)$perf['complaints']['total'] ?> total</div></div>
      <div><div class="muted" style="font-size:11px">Last assessment</div><div style="font-size:18px;font-weight:700"><?= $perf['assessment']['score']===null?'—':e(rtrim(rtrim(number_format((float)$perf['assessment']['score'],1),'0'),'.')) ?></div><div class="muted" style="font-size:11px"><?= e($perf['assessment']['on'] ?: 'not assessed') ?></div></div>
    </div>
  </div>
  <?php if (!empty($perf['penalties'])): ?>
    <div class="muted" style="font-size:11.5px;margin-top:10px">Base <?= e(rtrim(rtrim(number_format((float)$perf['base'],1),'0'),'.')) ?> − penalties (<?php $ps=[]; foreach($perf['penalties'] as $k=>$v){$ps[]=e($k).' −'.e(rtrim(rtrim(number_format((float)$v,1),'0'),'.'));} echo implode(', ',$ps); ?>) = <?= e(rtrim(rtrim(number_format((float)$perf['score'],1),'0'),'.')) ?>. Live score from operational data; open quality issues reduce it.</div>
  <?php else: ?>
    <div class="muted" style="font-size:11.5px;margin-top:10px">Live score from assessment and inspection results. No open quality issues against this vendor.</div>
  <?php endif; ?>
</div>

<div class="two-col" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:start;margin-top:14px">
  <div class="panel">
    <h3 style="margin:0 0 8px;font-size:14px">Recent reports</h3>
    <?php if (empty($v360['reports'])): ?><p class="muted" style="margin:0;font-size:12.5px">None yet.</p><?php else: foreach ($v360['reports'] as $r): [$rc,$rl] = $resPill[$r['result']] ?? ['p-mut','—']; ?>
      <div style="display:flex;justify-content:space-between;gap:8px;padding:5px 0;border-bottom:1px solid var(--line);font-size:12.5px">
        <a href="/document?id=<?= (int)$r['id'] ?>" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($r['irn']) ?></a>
        <span><?php if (($r['result']??'')!=='' && $r['result']!=='NA'): ?><span class="pill <?= $rc ?>"><?= e($rl) ?></span><?php else: ?><span class="muted"><?= e($r['type_code']) ?></span><?php endif; ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="panel">
    <h3 style="margin:0 0 8px;font-size:14px">Nonconformities</h3>
    <?php if (empty($v360['ncrs'])): ?><p class="muted" style="margin:0;font-size:12.5px">None raised.</p><?php else: foreach ($v360['ncrs'] as $n): ?>
      <div style="padding:5px 0;border-bottom:1px solid var(--line);font-size:12.5px">
        <div style="display:flex;justify-content:space-between;gap:8px"><strong><?= e($n['ref']) ?></strong>
          <span class="pill <?= $n['severity']==='MAJOR'?'p-bad':($n['severity']==='MINOR'?'p-warn':'p-mut') ?>"><?= e(ucfirst(strtolower($n['severity']))) ?></span></div>
        <div class="muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($n['title']) ?></div>
        <div class="muted" style="font-size:11px"><?= e($n['status']) ?><?= (($n['status']??'')!=='CLOSED' && !empty($n['due_on']) && $n['due_on']<date('Y-m-d'))?' · <span style="color:var(--bad)">overdue</span>':'' ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="panel">
    <h3 style="margin:0 0 8px;font-size:14px">Complaints</h3>
    <?php if (empty($v360['complaints'])): ?><p class="muted" style="margin:0;font-size:12.5px">None recorded.</p><?php else: foreach ($v360['complaints'] as $c): ?>
      <div style="padding:5px 0;border-bottom:1px solid var(--line);font-size:12.5px">
        <div style="display:flex;justify-content:space-between;gap:8px"><strong><?= e($c['ref']) ?></strong><span class="pill <?= ($c['status']??'')!=='CLOSED'?'p-warn':'p-mut' ?>"><?= e($c['status']) ?></span></div>
        <div class="muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($c['subject'] ?: $c['kind']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; // perf ?>

<?php // ---- Expediting performance (commitment & forecast reliability) ---- ?>
<?php if (!empty($xperf) && ($xperf['commitments']['total'] > 0 || $xperf['forecast']['compared'] > 0)):
  $rel = $xperf['commitments']['reliability_pct']; $relCol = $rel===null?'var(--muted)':($rel>=85?'#15803d':($rel>=60?'#b45309':'var(--bad)'));
  $opt = $xperf['forecast']['optimism_pct'];
?>
<div class="panel" style="margin-top:14px">
  <div style="display:flex;justify-content:space-between;align-items:baseline"><h3 style="margin:0">Expediting performance</h3><span class="muted" style="font-size:11.5px">across <?= (int)$xperf['reports'] ?> expediting report(s)</span></div>
  <div style="margin-top:8px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
    <div><div class="muted" style="font-size:11px">Commitment reliability</div><div style="font-size:20px;font-weight:800;color:<?= $relCol ?>"><?= $rel===null?'—':e(rtrim(rtrim(number_format((float)$rel,1),'0'),'.')).'%' ?></div><div class="muted" style="font-size:11px"><?= (int)$xperf['commitments']['on_time'] ?> on time · <?= (int)$xperf['commitments']['late'] ?> late</div></div>
    <div><div class="muted" style="font-size:11px">Commitments tracked</div><div style="font-size:20px;font-weight:800"><?= (int)$xperf['commitments']['total'] ?></div><div class="muted" style="font-size:11px"><?= (int)$xperf['commitments']['open'] ?> open · <?= (int)$xperf['commitments']['revised'] ?> revised</div></div>
    <div><div class="muted" style="font-size:11px">Forecast optimism</div><div style="font-size:20px;font-weight:800;color:<?= ($opt!==null && $opt>=50)?'var(--bad)':'inherit' ?>"><?= $opt===null?'—':e(rtrim(rtrim(number_format((float)$opt,1),'0'),'.')).'%' ?></div><div class="muted" style="font-size:11px">of <?= (int)$xperf['forecast']['compared'] ?> forecasts ran late vs the expeditor</div></div>
    <div><div class="muted" style="font-size:11px">Avg optimism</div><div style="font-size:20px;font-weight:800"><?= (float)$xperf['forecast']['avg_optimism_days']>0 ? e(rtrim(rtrim(number_format((float)$xperf['forecast']['avg_optimism_days'],1),'0'),'.')).' d' : '—' ?></div><div class="muted" style="font-size:11px">expeditor later than vendor</div></div>
  </div>
  <?php // ---- Predictive delivery risk (Phase 6) across open POs ---- ?>
  <?php if (!empty($xrisk) && (int)($xrisk['open_pos']??0) > 0):
    $vb = $xrisk['band']; $vc = $vb==='CRITICAL'?'#dc2626':($vb==='HIGH'?'#c2410c':($vb==='MEDIUM'?'#b45309':'#15803d'));
    $w = $xrisk['worst'] ?? null;
  ?>
  <div style="margin-top:12px;border-top:1px dashed var(--line);padding-top:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:16px">📡</span>
    <div><div class="muted" style="font-size:11px">Predictive delivery risk</div>
      <div style="font-size:16px;font-weight:800;color:<?= $vc ?>"><?= e(ucfirst(strtolower($vb))) ?> <span class="muted" style="font-size:12px;font-weight:600">· <?= (int)$xrisk['score'] ?>/100</span></div></div>
    <div class="muted" style="font-size:12px">across <?= (int)$xrisk['open_pos'] ?> open PO<?= (int)$xrisk['open_pos']===1?'':'s' ?></div>
    <?php if ($w && ($w['band']??'')!=='LOW'): ?>
      <div style="flex:1;text-align:right"><span class="muted" style="font-size:11.5px">Worst:</span>
        <a href="/document?id=<?= (int)$w['id'] ?>" style="font-weight:700;color:<?= $vc ?>"><?= e($w['irn'] ?: ($w['po'] ?: 'report')) ?></a>
        <span class="muted" style="font-size:11.5px">(<?= (int)$w['score'] ?>/100)</span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <p class="muted" style="font-size:11px;margin:10px 0 0">Commitment reliability = commitments met on time ÷ commitments completed. Forecast optimism = how often the vendor's forecast ran ahead of the expeditor's evidence-based forecast. Delivery risk is predicted across the vendor's open POs. Computed from this vendor's expediting reports.</p>
</div>
<?php endif; ?>

<?php // ---- Assessment history ---- ?>
<div class="panel" style="margin-top:14px;padding:0;overflow:hidden">
  <div style="padding:10px 14px;border-bottom:1px solid var(--line)"><h3 style="margin:0">Assessment history</h3></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Report No.</th><th>Type</th><th>Date</th><th>Score</th><th>Recommendation</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$history): ?><tr><td colspan="6" class="muted" style="padding:14px">No Vendor Assessment or Audit reports raised against this vendor yet.</td></tr><?php endif; ?>
      <?php foreach ($history as $h): ?>
      <tr onclick="location.href='/document?id=<?= (int)$h['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($h['irn']) ?></strong></td>
        <td><span class="pill p-mut"><?= e($h['kind'] ?? 'Assessment') ?></span></td>
        <td><?= e($h['issue_date'] ?: '—') ?></td>
        <td><?php if ($h['score']!==null): $c=$scoreCol($h['score']); ?><span style="font-weight:700;color:<?= $c ?>"><?= e(rtrim(rtrim(number_format((float)$h['score'],1),'0'),'.')) ?></span><span class="muted" style="font-size:11px">/100 <?= e($h['band']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td><?= e($h['recommendation'] ?: '—') ?></td>
        <td><span class="pill <?= idems_status_pill($h['status']) ?>"><?= e(lk_options_or('report_status', IDEMS_STATUS)[$h['status']] ?? $h['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php // ---- Qualification status timeline ---- ?>
<?php if (!empty($events)):
  $srcLab = ['ASSESSMENT'=>'Assessment','AUDIT'=>'Audit','EXPIRY'=>'Auto-expiry','MANUAL'=>'Manual'];
?>
<div class="panel" style="margin-top:14px">
  <h3 style="margin:0 0 8px">Qualification history</h3>
  <div style="display:flex;flex-direction:column;gap:0">
    <?php foreach ($events as $ev): [$nc,$nl] = $statusPill($ev['new_status']); $when = $ev['at'] ? date('d M Y H:i', strtotime($ev['at'])) : ''; ?>
      <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line)">
        <div style="flex:0 0 130px;font-size:11.5px;color:var(--muted)"><?= e($when) ?></div>
        <div style="flex:1">
          <div style="font-size:13px">
            <?php if (($ev['old_status'] ?? '') !== '' && $ev['old_status'] !== $ev['new_status']): ?>
              <span class="muted"><?= e($statusOpts[$ev['old_status']] ?? $ev['old_status']) ?></span> → <?php endif; ?>
            <span class="pill <?= $nc ?>"><?= e($nl) ?></span>
            <span class="muted" style="font-size:11px">· <?= e($srcLab[$ev['source']] ?? $ev['source']) ?></span>
            <?php if ($ev['score'] !== null && $ev['score'] !== ''): ?><span class="muted" style="font-size:11px">· score <?= e(rtrim(rtrim(number_format((float)$ev['score'],1),'0'),'.')) ?></span><?php endif; ?>
          </div>
          <?php if (trim((string)($ev['reason'] ?? '')) !== ''): ?><div class="muted" style="font-size:12px;margin-top:2px"><?= e($ev['reason']) ?></div><?php endif; ?>
          <?php if (trim((string)($ev['actor'] ?? '')) !== ''): ?><div class="muted" style="font-size:11px">by <?= e($ev['actor']) ?></div><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
