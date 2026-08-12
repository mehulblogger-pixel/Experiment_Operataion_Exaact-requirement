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
