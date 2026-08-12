<?php // Expediting — project consolidation (many PO-level ERs → one project delivery tree) ?>
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
  $riskCol = function($b){ switch($b){ case 'CRITICAL': return '#dc2626'; case 'HIGH': return '#c2410c'; case 'MEDIUM': return '#b45309'; case 'LOW': return '#15803d'; default: return 'var(--muted)'; } };
  $num = function($v){ return $v===null ? '—' : rtrim(rtrim(number_format((float)$v,1),'0'),'.'); };
  $fmtD = function($s){ $s=trim((string)$s); if($s==='')return '—'; $t=strtotime($s); return $t?date('d-M-Y',$t):$s; };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/expediting">Expediting</a> › Projects</div>
<div class="master-head">
  <div><h1>Project delivery consolidation</h1>
    <p class="sub" style="margin:2px 0 0">Every project rolled up from its purchase orders — one project, many POs, one delivery view. <?= (int)$counts['projects'] ?> project(s) · <?= (int)$counts['pos'] ?> PO(s).</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/expediting">PO register</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Projects</div><div class="k-val"><?= (int)$counts['projects'] ?></div><div class="k-sub">with expediting</div></div>
  <div class="kpi"><div class="k-lab">Purchase orders</div><div class="k-val"><?= (int)$counts['pos'] ?></div><div class="k-sub">tracked</div></div>
  <div class="kpi"><div class="k-lab">Projects at risk</div><div class="k-val <?= (int)$counts['at_risk_projects']>0?'down':'' ?>"><?= (int)$counts['at_risk_projects'] ?></div><div class="k-sub">at-risk / delayed / critical</div></div>
  <div class="kpi"><div class="k-lab">High delivery risk</div><div class="k-val <?= (int)$counts['high_risk_projects']>0?'down':'' ?>"><?= (int)$counts['high_risk_projects'] ?></div><div class="k-sub">predictive · high/critical</div></div>
  <div class="kpi"><div class="k-lab">POs forecast late</div><div class="k-val <?= (int)$counts['late_pos']>0?'down':'' ?>"><?= (int)$counts['late_pos'] ?></div><div class="k-sub">across all projects</div></div>
</div>

<form method="get" action="/expediting-projects" class="chip-row" style="margin:10px 0;gap:6px;flex-wrap:wrap">
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search project / ER No." style="min-width:240px">
  <button class="btn small" type="submit">Search</button>
</form>

<?php if (!$projects): ?>
  <div class="panel" style="padding:16px"><p class="muted" style="margin:0">No expediting reports yet. Create one with type <strong>Expediting Report</strong> against a vendor and PO; it will roll up here by project.</p></div>
<?php endif; ?>

<?php foreach ($projects as $p): [$pc,$pl] = $statusPill($p['status']); $rc = $riskCol($p['risk']['band']); ?>
<details class="panel" style="margin:0 0 12px;padding:0;overflow:hidden;border-left:4px solid <?= $rc ?>" <?= in_array($p['status'],['DELAYED','CRITICAL'],true)?'open':'' ?>>
  <summary style="list-style:none;cursor:pointer;padding:12px 14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="min-width:210px;flex:1">
      <div style="font-size:15px;font-weight:700"><?= e($p['project']) ?><?= $p['code']!==''?' <span class="muted" style="font-size:12px;font-weight:500">'.e($p['code']).'</span>':'' ?></div>
      <div class="muted" style="font-size:12px"><?= e($p['client']?:'—') ?> · <?= (int)$p['total_pos'] ?> PO<?= (int)$p['total_pos']===1?'':'s' ?><?= (int)$p['open_pos']!==(int)$p['total_pos']?' ('.(int)$p['open_pos'].' open)':'' ?></div>
    </div>
    <div style="text-align:center;min-width:74px">
      <div class="muted" style="font-size:10px">PROGRESS</div>
      <div style="font-weight:800;font-size:16px;color:<?= $progCol($p['progress']) ?>;font-variant-numeric:tabular-nums"><?= $num($p['progress']) ?><?= $p['progress']!==null?'<span class="muted" style="font-size:11px">%</span>':'' ?></div>
    </div>
    <div style="text-align:center;min-width:84px">
      <div class="muted" style="font-size:10px">STATUS</div>
      <span class="pill <?= $pc ?>"><?= e($pl) ?></span>
    </div>
    <div style="text-align:center;min-width:78px">
      <div class="muted" style="font-size:10px">RISK</div>
      <div style="font-weight:800;color:<?= $rc ?>"><?= e(ucfirst(strtolower($p['risk']['band']))) ?> <span class="muted" style="font-size:11px;font-weight:600"><?= (int)$p['risk']['score'] ?></span></div>
    </div>
    <div style="text-align:center;min-width:150px">
      <div class="muted" style="font-size:10px">BINDING DELIVERY</div>
      <div style="font-size:12.5px;font-variant-numeric:tabular-nums"><?= $fmtD($p['forecast']) ?><?php if ($p['variance']!==null && $p['variance']>0): ?> <span style="color:var(--bad);font-weight:700">+<?= (int)$p['variance'] ?>d</span><?php elseif ($p['variance']!==null): ?> <span style="color:#15803d;font-weight:600">on time</span><?php endif; ?></div>
      <?php if ($p['binding_po']!==''): ?><div class="muted" style="font-size:10px">via <?= e($p['binding_po']) ?></div><?php endif; ?>
    </div>
  </summary>
  <div class="tbl-scroll" style="overflow-x:auto;border-top:1px solid var(--line)">
    <table class="dt">
      <thead><tr><th>ER No.</th><th>PO / package</th><th>Vendor</th><th>Progress</th><th>Status</th><th>Risk</th><th>Required</th><th>Forecast</th><th>Δ days</th></tr></thead>
      <tbody>
        <?php foreach ($p['pos'] as $c): [$cc,$cl] = $statusPill($c['status']); $crc = $riskCol($c['risk']); ?>
        <tr onclick="location.href='/document?id=<?= (int)$c['id'] ?>'" style="cursor:pointer<?= $c['open']?'':';opacity:.62' ?>">
          <td><strong><?= e($c['irn']) ?></strong></td>
          <td><?php $pp = trim(($c['po']?:'').($c['package']?($c['po']?' · ':'').$c['package']:'')); ?><?= e($pp ?: '—') ?></td>
          <td><?= e($c['vendor'] ?: '—') ?></td>
          <td><?php if($c['progress']!==null): ?><span style="font-weight:700;color:<?= $progCol($c['progress']) ?>;font-variant-numeric:tabular-nums"><?= $num($c['progress']) ?><span class="muted" style="font-size:11px">%</span></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td><span class="pill <?= $cc ?>"><?= e($cl) ?></span></td>
          <td><?php if($c['risk']!==null): ?><span style="font-weight:700;color:<?= $crc ?>"><?= e(ucfirst(strtolower($c['risk']))) ?></span><?php if(($c['risk_score']??null)!==null && $c['risk']!=='LOW'): ?> <span class="muted" style="font-size:10px;font-variant-numeric:tabular-nums"><?= (int)$c['risk_score'] ?></span><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td style="font-variant-numeric:tabular-nums"><?= $fmtD($c['required']) ?></td>
          <td style="font-variant-numeric:tabular-nums"><?= $fmtD($c['forecast']) ?></td>
          <td><?php $v=$c['variance']; if($v===null): ?>—<?php else: ?><span style="<?= $v>0?'color:var(--bad);font-weight:700':($v<0?'color:#15803d':'') ?>"><?= ($v>0?'+':'').(int)$v ?></span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</details>
<?php endforeach; ?>
