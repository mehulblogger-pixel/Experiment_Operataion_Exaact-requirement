<?php // Expediting register + management KPIs (Register + Dashboard views) ?>
<?php
  $statusPill = function($st){
    switch($st){
      case 'ON TRACK': return ['p-ok','On track'];
      case 'COMPLETED': return ['p-ok','Completed'];
      case 'AT RISK': return ['p-warn','At risk'];
      case 'CRITICAL': return ['p-bad','Critical'];
      default: return ['p-bad','Delayed'];
    }
  };
  $progCol = function($s){ $s=(float)$s; if($s>=90) return '#15803d'; if($s>=50) return '#2563eb'; if($s>0) return '#b45309'; return 'var(--muted)'; };
  $riskPill = function($band){
    switch($band){
      case 'CRITICAL': return ['#dc2626','Critical'];
      case 'HIGH':     return ['#c2410c','High'];
      case 'MEDIUM':   return ['#b45309','Medium'];
      case 'LOW':      return ['#15803d','Low'];
      default:         return ['var(--muted)','—'];
    }
  };
?>
<div class="crumbs"><a href="/">Home</a> › Expediting</div>
<div class="master-head">
  <div><h1>Expediting register</h1>
    <p class="sub" style="margin:2px 0 0">Every expediting report with its live progress, status and the expeditor's delivery forecast. <?= (int)$counts['total'] ?> report(s).</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/documents">Reports</a>
    <?php if (is_master() || can('mod.idems.edit')): ?><a class="btn" href="/document-new">+ New expediting report</a><?php endif; ?>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Active reports</div><div class="k-val"><?= (int)$counts['total'] ?></div><div class="k-sub">expediting</div></div>
  <div class="kpi"><div class="k-lab">On track</div><div class="k-val up"><?= (int)$counts['on_track'] ?></div><div class="k-sub">incl. completed</div></div>
  <div class="kpi"><div class="k-lab">At risk</div><div class="k-val <?= (int)$counts['at_risk']>0?'':'' ?>" style="color:#b45309"><?= (int)$counts['at_risk'] ?></div><div class="k-sub">watch</div></div>
  <div class="kpi"><div class="k-lab">Delayed / critical</div><div class="k-val <?= (int)$counts['delayed']>0?'down':'' ?>"><?= (int)$counts['delayed'] ?></div><div class="k-sub">need action</div></div>
  <div class="kpi"><div class="k-lab">Forecast late</div><div class="k-val <?= (int)$counts['late_forecast']>0?'down':'' ?>"><?= (int)$counts['late_forecast'] ?></div><div class="k-sub">expeditor forecast &gt; required</div></div>
  <div class="kpi"><div class="k-lab">High delivery risk</div><div class="k-val <?= (int)($counts['high_risk']??0)>0?'down':'' ?>"><?= (int)($counts['high_risk']??0) ?></div><div class="k-sub">predictive · high/critical</div></div>
</div>

<form method="get" action="/expediting" class="chip-row" style="margin:10px 0;gap:6px;flex-wrap:wrap">
  <select class="form-control" name="status" onchange="this.form.submit()" style="max-width:170px"><option value="">All statuses</option>
    <?php foreach ($statusOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fs===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search ER No. / project" style="min-width:220px">
  <button class="btn small" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>ER No.</th><th>Vendor</th><th>PO / project</th><th>Progress</th><th>Status</th><th>Risk</th><th>Required</th><th>Forecast</th><th>Δ days</th><th>Date</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="10" class="muted" style="padding:16px">No expediting reports yet. Create one with type <strong>Expediting Report</strong> against a vendor and PO.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): [$pc,$pl] = $statusPill($r['status']); ?>
      <tr onclick="location.href='/document?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($r['irn']) ?></strong><?= $r['finalized'] ? ' 🔒' : '' ?></td>
        <td><?= e($r['vendor'] ?: '—') ?></td>
        <td><?php $poproj = trim(($r['po'] ? $r['po'] : '') . ($r['project'] ? ($r['po']?' · ':'').$r['project'] : '')); ?><?= e($poproj ?: '—') ?></td>
        <td><?php if ($r['progress']!==null): $c=$progCol($r['progress']); ?><span style="font-weight:700;color:<?= $c ?>;font-variant-numeric:tabular-nums"><?= e(rtrim(rtrim(number_format((float)$r['progress'],1),'0'),'.')) ?><span class="muted" style="font-size:11px">%</span></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><span class="pill <?= $pc ?>"><?= e($pl) ?></span></td>
        <td><?php [$rk,$rl]=$riskPill($r['risk']??null); ?><span title="Predictive delivery risk<?= $r['risk_score']!==null?' — score '.(int)$r['risk_score'].'/100':'' ?>" style="font-weight:700;color:<?= $rk ?>"><?= e($rl) ?></span><?php if(($r['risk_score']??null)!==null && ($r['risk']??'')!=='LOW'): ?> <span class="muted" style="font-size:10px;font-variant-numeric:tabular-nums"><?= (int)$r['risk_score'] ?></span><?php endif; ?></td>
        <td><?= e($r['required'] ?: '—') ?></td>
        <td><?= e($r['forecast'] ?: '—') ?></td>
        <td><?php $v=$r['variance']; if($v===null): ?>—<?php else: ?><span style="<?= $v>0?'color:var(--bad);font-weight:600':($v<0?'color:#15803d':'') ?>"><?= ($v>0?'+':'').(int)$v ?></span><?php endif; ?></td>
        <td><?= e($r['date'] ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
