<?php
// Recruitment Command Centre (Phase 7) — mirrors the client's Excel command
// centre, plus the ownership & money layer. Read-only; data from rcc_data().
$d = $d ?? []; $f = $d['f'] ?? [];
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$sym = function_exists('cur_sym') ? cur_sym() : '₹';
$m = function ($v) use ($sym) { return function_exists('fmoney_short') ? fmoney_short($v) : ($sym . number_format((float)$v)); };
$pct = fn($v) => rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . '%';
$K = $d['kpi'] ?? []; $C = $d['conv'] ?? []; $PL = $d['pl'] ?? [];
// query-string builder that preserves other filters
$qs = function ($over = []) use ($f) {
    $q = array_filter(['fy' => $f['fy'] ?? '', 'month' => $f['month'] ?? '', 'dept' => $f['dept'] ?? '', 'source' => $f['source'] ?? '', 'manager' => $f['manager'] ?? '']);
    foreach ($over as $k => $v) { if ($v === null) unset($q[$k]); else $q[$k] = $v; }
    return $q ? '?' . http_build_query($q) : '';
};
// donut geometry
$C_ = 2 * M_PI * 54; $tot = array_sum(array_map(fn($s) => $s['n'], $d['status'] ?? [])); $tot = max(1, $tot);
$cvar = ['1'=>'--c1','2'=>'--c2','3'=>'--c3','4'=>'--c4','5'=>'--c5','7'=>'--c7','8'=>'--c8'];
?>
<style>
  .rcc{--c1:#3b6fb0;--c2:#e08a3c;--c3:#4a9d5b;--c4:#7d5ba6;--c5:#3aa6a6;--c6:#b07a3c;--c7:#b0413c;--c8:#c98a94;--band:var(--soft,#f1f5f9);--grid:var(--line,#e6e9ef)}
  .rcc .tnum{font-variant-numeric:tabular-nums}
  .rcc h1{margin:0;font-size:22px;font-weight:800;letter-spacing:.3px}
  .rcc .sub{color:var(--muted);font-size:12.5px;margin:3px 0 0}
  .rcc .refreshed{font-size:12px;color:var(--brand);font-weight:600;margin-top:2px}
  .rcc .rfilters{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:14px 0 4px}
  .rcc .rfilters .ff label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700;margin-bottom:3px}
  .rcc .rfilters select{width:100%;padding:7px 9px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink,inherit);font-size:13px}
  .rcc .rfacts{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .rcc .band{background:var(--band);border:1px solid var(--line);border-radius:9px;padding:7px 13px;margin:20px 0 12px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
  .rcc .band h2{margin:0;font-size:13px;font-weight:800;letter-spacing:.4px;text-transform:uppercase}
  .rcc .band .bd{font-size:12px;color:var(--muted)}
  .rcc .add{font-size:9.5px;font-weight:800;letter-spacing:.4px;color:var(--ok);background:color-mix(in srgb,var(--ok) 14%,transparent);border-radius:20px;padding:1px 8px;text-transform:uppercase}
  .rcc .kpis{display:grid;gap:12px}.rcc .k4{grid-template-columns:repeat(4,1fr)}.rcc .k3{grid-template-columns:repeat(3,1fr)}
  .rcc .kpi{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--brand);border-radius:12px;padding:12px 13px;text-decoration:none;color:inherit;display:block}
  .rcc .kpi .l{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700}
  .rcc .kpi .v{font-size:25px;font-weight:800;line-height:1.05;margin-top:5px}
  .rcc .kpi .dd{font-size:11px;color:var(--muted);margin-top:3px}
  .rcc .kpi.good{border-left-color:var(--ok)}.rcc .kpi.good .v{color:var(--ok)}
  .rcc .kpi.bad{border-left-color:var(--bad)}.rcc .kpi.bad .v{color:var(--bad)}
  .rcc .g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
  .rcc .g2.wide{grid-template-columns:1.35fr 1fr}
  .rcc .panel{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .rcc .panel .ph{display:flex;align-items:baseline;justify-content:space-between;gap:10px;padding:13px 16px 6px}
  .rcc .panel h3{margin:0;font-size:14.5px;font-weight:700}
  .rcc .panel .note{font-size:11.5px;color:var(--muted)}
  .rcc .panel .pb{padding:8px 16px 16px}
  .rcc .hb{display:flex;flex-direction:column;gap:8px}
  .rcc .hbrow{display:grid;grid-template-columns:150px 1fr 40px;align-items:center;gap:9px;font-size:12.5px}
  .rcc .hbrow .hl{text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rcc .hbrow a.hl:hover{color:var(--brand)}
  .rcc .hbtrack{height:15px;background:var(--soft);border-radius:5px;overflow:hidden}
  .rcc .hbtrack>i{display:block;height:100%;border-radius:5px;background:var(--brand)}
  .rcc .hb.red .hbtrack>i{background:var(--bad)}
  .rcc .hbrow .hv{font-variant-numeric:tabular-nums;color:var(--muted);text-align:right}
  .rcc .dept{display:flex;flex-direction:column;gap:10px}
  .rcc .drow{display:grid;grid-template-columns:130px 1fr;gap:9px;align-items:center;font-size:12px}
  .rcc .dbar{height:9px;border-radius:4px;background:var(--soft);overflow:hidden;margin-bottom:3px}
  .rcc .dbar>i{display:block;height:100%;border-radius:4px}
  .rcc .dbar.cand>i{background:var(--brand)}.rcc .dbar.off>i{background:var(--warn)}
  .rcc .hist{display:flex;align-items:flex-end;gap:16px;height:150px;padding:6px 6px 0}
  .rcc .hcol{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%}
  .rcc .hbar{width:100%;max-width:70px;background:var(--brand);border-radius:6px 6px 0 0;margin-top:auto;min-height:2px}
  .rcc .hcap{font-size:11px;color:var(--muted);text-align:center}.rcc .hval{font-size:12px;font-weight:700}
  .rcc svg .axis{stroke:var(--grid);stroke-width:1}.rcc svg .axl{fill:var(--muted);font-size:10px}.rcc svg .gl{fill:var(--ink,#333);font-size:11px;font-weight:600}
  .rcc .clegend{display:flex;gap:14px;flex-wrap:wrap;font-size:11.5px;color:var(--muted);padding:6px 2px 0}
  .rcc .clegend b{color:var(--ink,inherit);font-weight:600}
  .rcc .sw{width:11px;height:11px;border-radius:3px;display:inline-block;margin-right:5px;vertical-align:-1px}
  .rcc .swl{width:16px;border-top:2.5px solid var(--bad);display:inline-block;margin-right:5px;vertical-align:4px}
  .rcc .donutwrap{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
  .rcc .dleg{display:flex;flex-direction:column;gap:5px;font-size:12px;min-width:150px}
  .rcc .dleg .di{display:flex;align-items:center;gap:7px}.rcc .dleg .dn{margin-left:auto;color:var(--muted);font-variant-numeric:tabular-nums}
  .rcc table{width:100%;border-collapse:collapse;font-size:13px}.rcc .scroll{overflow-x:auto}
  .rcc th{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700;text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap;background:var(--card)}
  .rcc td{padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:middle;white-space:nowrap}
  .rcc tr:last-child td{border-bottom:none}.rcc td.num,.rcc th.num{text-align:right;font-variant-numeric:tabular-nums}
  .rcc .who{display:inline-flex;align-items:center;gap:6px}
  .rcc .av{width:22px;height:22px;border-radius:50%;background:color-mix(in srgb,var(--brand) 14%,transparent);color:var(--brand);font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center}
  .rcc .sub2{color:var(--muted);font-size:11.5px}
  .rcc .money-pos{color:var(--ok);font-weight:650}.rcc .money-neg{color:var(--bad);font-weight:650}
  .rcc .fillb{display:flex;align-items:center;gap:7px}.rcc .fbar{width:54px;height:7px;border-radius:5px;background:var(--soft);overflow:hidden}
  .rcc .fbar>i{display:block;height:100%;border-radius:5px}
  .rcc .proj{display:flex;flex-direction:column;gap:11px}
  .rcc .ptop{display:flex;justify-content:space-between;align-items:baseline;font-size:12.5px;margin-bottom:3px}
  .rcc .ptop .pn{font-weight:600}.rcc .ptop .pc{color:var(--muted);font-size:11.5px}
  .rcc .ptrack{height:16px;border-radius:6px;background:var(--soft);position:relative;overflow:hidden}
  .rcc .ptrack>.ord{position:absolute;inset:0;background:color-mix(in srgb,var(--brand) 16%,transparent)}
  .rcc .ptrack>.wrk{position:absolute;inset:0;background:var(--ok);opacity:.85}
  .rcc .ptrack>.lab{position:absolute;right:7px;top:0;line-height:16px;font-size:10.5px;color:var(--muted);font-variant-numeric:tabular-nums}
  .rcc .foot{margin-top:22px;font-size:11.5px;color:var(--muted);font-style:italic}
  .rcc .empty{padding:14px;color:var(--muted);font-size:12.5px}
  @media (max-width:1000px){.rcc .rfilters{grid-template-columns:repeat(2,1fr)}.rcc .k4,.rcc .k3{grid-template-columns:repeat(2,1fr)}.rcc .g2,.rcc .g2.wide{grid-template-columns:1fr}}
</style>

<div class="rcc">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
    <div>
      <h1>Recruitment Command Centre</h1>
      <p class="sub">Live hiring pipeline, funnel health and open demand — every number below responds to the filters.</p>
      <div class="refreshed"><?= $e($f['dept'] ? ($f['opts']['dept'][$f['dept']] ?? $f['dept']) : 'All departments') ?> · <?= $e($f['fy'] ?: '') ?> · Refreshed <?= date('d-M-Y') ?></div>
    </div>
    <div class="rfacts" style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn primary" href="/requisition-new">＋ New requirement</a>
      <a class="btn secondary" href="/candidate-new">＋ Add candidate</a>
      <a class="btn secondary" href="/requisitions">Requirements</a>
      <a class="btn secondary" href="/candidates">Candidates</a>
      <a class="btn secondary" href="/recruitment">Action view</a>
    </div>
  </div>

  <!-- CONFIGURABLE FILTERS -->
  <form method="get" action="/recruitment-cc" id="rccFilters">
    <div class="rfilters">
      <div class="ff"><label>Financial year</label>
        <select name="fy" onchange="this.form.submit()"><?php foreach (($f['opts']['fy'] ?: [$f['fy']]) as $v): ?><option value="<?= $e($v) ?>"<?= $f['fy']===$v?' selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Month (CV shared)</label>
        <select name="month" onchange="this.form.submit()"><option value="">All months</option><?php foreach ($f['opts']['month'] as $k=>$v): ?><option value="<?= $e($k) ?>"<?= $f['month']===$k?' selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Department</label>
        <select name="dept" onchange="this.form.submit()"><option value="">All departments</option><?php foreach ($f['opts']['dept'] as $k=>$v): ?><option value="<?= $e($k) ?>"<?= $f['dept']===$k?' selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Source type</label>
        <select name="source" onchange="this.form.submit()"><option value="">All sources</option><?php foreach ($f['opts']['source'] as $k=>$v): ?><option value="<?= $e($k) ?>"<?= $f['source']===$k?' selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Hiring manager / recruiter</label>
        <select name="manager" onchange="this.form.submit()"><option value="">Everyone</option><?php foreach ($f['opts']['manager'] as $k=>$v): ?><option value="<?= $e($k) ?>"<?= (string)$f['manager']===(string)$k?' selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
    </div>
  </form>
  <p class="sub" style="margin-top:6px">Every dropdown is driven by Masters &amp; Settings — an admin edits Department, Source, Stages and FY there with no code.</p>

  <!-- 1. HIRING DEMAND & PIPELINE VOLUME -->
  <div class="band"><h2>Hiring demand &amp; pipeline volume</h2><span class="bd">— where the workload sits right now</span></div>
  <div class="kpis k4">
    <a class="kpi" href="/requisitions"><div class="l">Open positions</div><div class="v tnum"><?= (int)($K['open_positions'] ?? 0) ?></div><div class="dd">vacancies still to fill ›</div></a>
    <a class="kpi" href="/candidates"><div class="l">Candidates in pipeline</div><div class="v tnum"><?= (int)($K['pipeline'] ?? 0) ?></div><div class="dd">total on record ›</div></a>
    <a class="kpi" href="/candidates"><div class="l">Active now</div><div class="v tnum"><?= (int)($K['active_now'] ?? 0) ?></div><div class="dd">still live in the process ›</div></a>
    <a class="kpi" href="/candidates?stage=OFFERED"><div class="l">Offers issued</div><div class="v tnum"><?= (int)($K['offers_issued'] ?? 0) ?></div><div class="dd">reached offer stage ›</div></a>
  </div>
  <!-- Manpower P&L (added) -->
  <div class="band" style="margin-top:12px"><h2>Manpower P&amp;L</h2><span class="bd">— money made when seats are filled, and lost while they stay open</span><span class="add">added</span></div>
  <div class="kpis k3">
    <a class="kpi good" href="/requisitions"><div class="l">Profit earned · deployed</div><div class="v tnum"><?= $m($PL['earned'] ?? 0) ?></div><div class="dd"><?= (int)($K['filled'] ?? 0) ?> of <?= (int)($K['ordered'] ?? 0) ?> seats filled ›</div></a>
    <a class="kpi bad" href="/requisitions"><div class="l">Profit lost · vacant</div><div class="v tnum"><?= $m($PL['lost'] ?? 0) ?></div><div class="dd"><?= (int)($K['open_positions'] ?? 0) ?> seats unfilled ›</div></a>
    <a class="kpi" href="/availability"><div class="l">Working on site now</div><div class="v tnum"><?= (int)($PL['working'] ?? 0) ?></div><div class="dd">deployed across live projects ›</div></a>
  </div>

  <!-- 2. CONVERSION, SPEED & COST -->
  <div class="band"><h2>Conversion, speed &amp; cost</h2><span class="bd">— how well and how fast the funnel works</span></div>
  <div class="kpis k4">
    <div class="kpi"><div class="l">Shortlist rate</div><div class="v tnum"><?= $pct($C['shortlist'] ?? 0) ?></div><div class="dd">CVs that got shortlisted</div></div>
    <div class="kpi"><div class="l">Interview → offer</div><div class="v tnum"><?= $pct($C['int_offer'] ?? 0) ?></div><div class="dd">interviewed → offered</div></div>
    <div class="kpi"><div class="l">Offer acceptance</div><div class="v tnum"><?= $pct($C['accept'] ?? 0) ?></div><div class="dd">offers accepted vs issued</div></div>
    <div class="kpi"><div class="l">Avg time to hire</div><div class="v tnum"><?= (int)($C['tth'] ?? 0) ?><span style="font-size:14px">d</span></div><div class="dd">CV → accepted (n=<?= (int)($C['tth_n'] ?? 0) ?>)</div></div>
  </div>

  <!-- 3. FUNNEL + STATUS DONUT -->
  <div class="band"><h2>Funnel, outcomes &amp; source performance</h2></div>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>Recruitment funnel</h3><span class="note">stage by stage · click to see who’s there</span></div>
      <div class="pb"><?php $fmax = max(1, ($d['funnel'][0]['n'] ?? 1)); if (array_sum(array_map(fn($x)=>$x['n'],$d['funnel']))===0): ?><div class="empty">No candidates match these filters.</div><?php else: ?>
        <div class="hb"><?php $i=0; foreach ($d['funnel'] as $s): $i++; ?>
          <div class="hbrow"><a class="hl" href="/candidates?stage=<?= $e($s['key']) ?>"><?= $i ?>. <?= $e($s['label']) ?></a><span class="hbtrack"><i style="width:<?= round($s['n']/$fmax*100) ?>%"></i></span><span class="hv"><?= (int)$s['n'] ?></span></div>
        <?php endforeach; ?></div>
      <?php endif; ?></div>
    </div>
    <div class="panel"><div class="ph"><h3>Where every candidate is</h3><span class="note">current status · <?= (int)$tot ?> on record</span></div>
      <div class="pb donutwrap">
        <svg width="180" height="180" viewBox="0 0 180 180" role="img" aria-label="Candidate status donut">
          <g transform="rotate(-90 90 90)" fill="none" stroke-width="26"><?php $off=0; foreach ($d['status'] as $s): if ($s['n']<=0) continue; $seg=$s['n']/$tot*$C_; ?>
            <circle cx="90" cy="90" r="54" stroke="var(<?= $cvar[(string)$s['c']] ?? '--c1' ?>)" stroke-dasharray="<?= round($seg,1) ?> <?= round($C_-$seg,1) ?>" stroke-dashoffset="<?= round(-$off,1) ?>"></circle>
          <?php $off+=$seg; endforeach; ?></g>
          <text x="90" y="86" text-anchor="middle" font-size="26" font-weight="800" fill="var(--ink,#333)"><?= (int)$tot ?></text>
          <text x="90" y="104" text-anchor="middle" font-size="10" fill="var(--muted)">candidates</text>
        </svg>
        <div class="dleg"><?php foreach ($d['status'] as $s): ?>
          <div class="di"><span class="sw" style="background:var(<?= $cvar[(string)$s['c']] ?? '--c1' ?>)"></span><?= $e($s['label']) ?><span class="dn"><?= (int)$s['n'] ?></span></div>
        <?php endforeach; ?></div>
      </div>
    </div>
  </div>

  <!-- 4. TREND + DEPARTMENT LOAD -->
  <div class="band"><h2>Trend, department load &amp; hiring-manager activity</h2></div>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>Monthly trend</h3><span class="note">CVs shared · offers · joined</span></div>
      <div class="pb"><?php $tr=$d['trend']; $n=count($tr); if ($n===0): ?><div class="empty">No monthly data yet.</div><?php else:
        $tmax=max(1,max(array_map(fn($x)=>max($x['cv'],$x['off'],$x['join']),$tr)));
        $W=460;$H=200;$pl=42;$pb=24;$pt=12;$plot=$H-$pb-$pt;$stepx=$n>1?($W-$pl-10)/($n-1):0;
        $xy=fn($i,$v)=>[$pl+$i*$stepx, $pt+$plot-($v/$tmax*$plot)];
        $line=function($key,$col) use($tr,$xy){ $pts=[]; foreach($tr as $i=>$r){ [$x,$y]=$xy($i,$r[$key]); $pts[]=round($x).','.round($y);} return '<polyline fill="none" stroke="'.$col.'" stroke-width="2.5" points="'.implode(' ',$pts).'"></polyline>'; };
      ?>
        <svg width="100%" viewBox="0 0 <?= $W ?> <?= $H+18 ?>" role="img" aria-label="Monthly trend">
          <line class="axis" x1="<?= $pl ?>" y1="<?= $pt+$plot ?>" x2="<?= $W ?>" y2="<?= $pt+$plot ?>"></line>
          <line class="axis" x1="<?= $pl ?>" y1="<?= $pt+$plot/2 ?>" x2="<?= $W ?>" y2="<?= $pt+$plot/2 ?>"></line>
          <text class="axl" x="<?= $pl-6 ?>" y="<?= $pt+$plot+3 ?>" text-anchor="end">0</text>
          <text class="axl" x="<?= $pl-6 ?>" y="<?= $pt+$plot/2+3 ?>" text-anchor="end"><?= round($tmax/2) ?></text>
          <text class="axl" x="<?= $pl-6 ?>" y="<?= $pt+6 ?>" text-anchor="end"><?= $tmax ?></text>
          <?= $line('cv','var(--brand)') ?><?= $line('off','var(--warn)') ?><?= $line('join','var(--ok)') ?>
          <?php foreach ($tr as $i=>$r): [$x,]=$xy($i,0); ?><text class="gl" x="<?= round($x) ?>" y="<?= $H+8 ?>" text-anchor="middle"><?= $e(substr($r['label'],0,3)) ?></text><?php endforeach; ?>
        </svg>
        <div class="clegend"><span><span class="sw" style="background:var(--brand)"></span>CVs shared</span><span><span class="sw" style="background:var(--warn)"></span>Offers</span><span><span class="sw" style="background:var(--ok)"></span>Joined</span></div>
      <?php endif; ?></div>
    </div>
    <div class="panel"><div class="ph"><h3>Department load</h3><span class="note">candidates vs reached-offer</span></div>
      <div class="pb"><?php if (!$d['dept']): ?><div class="empty">No department data — tag departments on requirements/candidates.</div><?php else: $dmax=max(1,max(array_map(fn($x)=>$x['cand'],$d['dept']))); ?>
        <div class="dept"><?php foreach ($d['dept'] as $x): ?>
          <div class="drow"><span class="hl" style="text-align:right;overflow:hidden;text-overflow:ellipsis"><?= $e($x['label']) ?></span><div>
            <div class="dbar cand"><i style="width:<?= round($x['cand']/$dmax*100) ?>%"></i></div>
            <div class="dbar off"><i style="width:<?= round($x['off']/$dmax*100) ?>%"></i></div></div></div>
        <?php endforeach; ?></div>
        <div class="clegend"><span><span class="sw" style="background:var(--brand)"></span>candidates</span><span><span class="sw" style="background:var(--warn)"></span>reached offer</span></div>
      <?php endif; ?></div>
    </div>
  </div>

  <!-- Recruiter performance (added) -->
  <div class="panel" style="margin-top:16px"><div class="ph"><h3>Recruiter performance <span class="add" style="margin-left:6px">added</span></h3><span class="note">Responsible 1 · posted vs working now, with profit lost while vacant</span></div>
    <div class="pb"><?php $R=$d['recruiters']; if (!$R): ?><div class="empty">Assign a Recruiter (Responsible 1) on requirements to see this chart.</div><?php else:
      $rmax=max(1,max(array_map(fn($x)=>max($x['posted'],$x['working']),$R)));
      $lmax=max(1,max(array_map(fn($x)=>$x['lost'],$R)));
      $n=count($R);$W=520;$H=250;$pl=46;$pr=40;$pb=40;$pt=18;$plot=$H-$pb-$pt;$gw=($W-$pl-$pr)/max(1,$n);
      $barW=min(26,$gw*0.3);
    ?>
      <div class="g2 wide"><div>
        <svg width="100%" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Recruiter combo chart">
          <line class="axis" x1="<?= $pl ?>" y1="<?= $pt+$plot ?>" x2="<?= $W-$pr ?>" y2="<?= $pt+$plot ?>"></line>
          <line class="axis" x1="<?= $pl ?>" y1="<?= $pt+$plot/2 ?>" x2="<?= $W-$pr ?>" y2="<?= $pt+$plot/2 ?>"></line>
          <text class="axl" x="<?= $pl-6 ?>" y="<?= $pt+$plot+3 ?>" text-anchor="end">0</text>
          <text class="axl" x="<?= $pl-6 ?>" y="<?= $pt+6 ?>" text-anchor="end"><?= $rmax ?></text>
          <?php $pts=[]; foreach ($R as $i=>$x){ $cx=$pl+$gw*$i+$gw/2;
            $hP=$x['posted']/$rmax*$plot; $hW=$x['working']/$rmax*$plot;
            $yL=$pt+$plot-($x['lost']/$lmax*$plot); $pts[]=round($cx).','.round($yL);
            echo '<rect x="'.round($cx-$barW-2).'" y="'.round($pt+$plot-$hP).'" width="'.round($barW).'" height="'.round($hP).'" rx="3" fill="var(--brand)"></rect>';
            echo '<rect x="'.round($cx+2).'" y="'.round($pt+$plot-$hW).'" width="'.round($barW).'" height="'.round($hW).'" rx="3" fill="var(--ok)"></rect>';
            echo '<text class="gl" x="'.round($cx).'" y="'.($H-8).'" text-anchor="middle">'.$e(explode(' ',$x['name'])[0]).'</text>';
          } ?>
          <polyline fill="none" stroke="var(--bad)" stroke-width="2.5" points="<?= implode(' ',$pts) ?>"></polyline>
          <?php foreach ($R as $i=>$x){ $cx=$pl+$gw*$i+$gw/2; $yL=$pt+$plot-($x['lost']/$lmax*$plot); echo '<circle cx="'.round($cx).'" cy="'.round($yL).'" r="4" fill="var(--bad)"></circle>'; } ?>
        </svg>
        <div class="clegend"><span><span class="sw" style="background:var(--brand)"></span><b>Posted</b> ordered</span><span><span class="sw" style="background:var(--ok)"></span><b>Working</b> now</span><span><span class="swl"></span><b>Profit lost</b> vacant</span></div>
      </div>
      <div class="scroll"><table>
        <thead><tr><th>Recruiter</th><th class="num">Posted</th><th class="num">Working</th><th class="num">Recruited</th><th class="num">Earned</th><th class="num">Lost</th></tr></thead>
        <tbody><?php foreach ($R as $x): $ini=strtoupper(substr($x['name'],0,1).(strpos($x['name'],' ')!==false?substr(strstr($x['name'],' '),1,1):'')); ?>
          <tr><td><span class="who"><span class="av"><?= $e($ini) ?></span> <?= $e($x['name']) ?></span></td><td class="num"><?= (int)$x['posted'] ?></td><td class="num"><?= (int)$x['working'] ?></td><td class="num"><?= (int)$x['recruited'] ?></td><td class="num money-pos"><?= $m($x['earned']) ?></td><td class="num money-neg"><?= $m($x['lost']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div></div>
    <?php endif; ?></div>
  </div>

  <!-- 5. DROP REASONS + AGEING -->
  <div class="band"><h2>Why we lose candidates &amp; how long they wait</h2></div>
  <?php $dp = $d['droppoints'] ?? []; $dpTot = array_sum(array_map(fn($x)=>$x['n'],$dp)); if ($dpTot > 0):
    $dpTone = ['INITIAL'=>'var(--c2)','IN_BETWEEN'=>'var(--warn)','SALARY'=>'var(--c4)','ACCEPTED_NO_JOIN'=>'var(--bad)']; $dpMax = max(1,max(array_map(fn($x)=>$x['n'],$dp))); ?>
  <div class="panel" style="margin-bottom:16px"><div class="ph"><h3>Where they drop off</h3><span class="note">by point in the pipeline · <?= (int)$dpTot ?> tracked</span></div>
    <div class="pb"><div class="hb">
      <?php foreach ($dp as $x): $tone = $dpTone[$x['key']] ?? 'var(--bad)'; ?>
        <div class="hbrow"><span class="hl" title="<?= $e($x['label']) ?>"><?= $e($x['label']) ?></span><span class="hbtrack"><i style="width:<?= round($x['n']/$dpMax*100) ?>%;background:<?= $tone ?>"></i></span><span class="hv"><?= (int)$x['n'] ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="sub" style="margin:8px 2px 0;font-size:11.5px">Drop points are configurable in Masters. <b><?= (int)($d['accepted_no_join'] ?? 0) ?></b> candidates accepted an offer but did not join.</p>
    </div>
  </div>
  <?php endif; ?>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>Drop / hold reasons</h3><span class="note">candidates lost, by reason</span></div>
      <div class="pb"><?php if (!$d['drops']): ?><div class="empty">No dropped candidates in this window.</div><?php else: $dr=max(1,max(array_map(fn($x)=>$x['n'],$d['drops']))); ?>
        <div class="hb red"><?php foreach ($d['drops'] as $x): ?>
          <div class="hbrow"><span class="hl"><?= $e($x['label']) ?></span><span class="hbtrack"><i style="width:<?= round($x['n']/$dr*100) ?>%"></i></span><span class="hv"><?= (int)$x['n'] ?></span></div>
        <?php endforeach; ?></div>
      <?php endif; ?></div>
    </div>
    <div class="panel"><div class="ph"><h3>How long active</h3><span class="note">days a live candidate has been waiting</span></div>
      <div class="pb"><?php $ag=$d['ageing']; $amax=max(1,max(array_values($ag))); $tones=['0–15 days'=>'var(--brand)','16–30 days'=>'var(--brand)','31–45 days'=>'var(--warn)','Over 45 days'=>'var(--bad)']; ?>
        <div class="hist"><?php foreach ($ag as $lab=>$v): ?>
          <div class="hcol"><span class="hval"><?= (int)$v ?></span><div class="hbar" style="height:<?= round($v/$amax*100) ?>%;background:<?= $tones[$lab] ?>"></div><span class="hcap"><?= $e($lab) ?></span></div>
        <?php endforeach; ?></div>
      </div>
    </div>
  </div>

  <!-- 6. NEEDS ATTENTION -->
  <div class="band"><h2>Needs attention today</h2><span class="bd">— longest-waiting candidates and the biggest open demand</span></div>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>Longest-waiting candidates</h3><span class="note">chase these first</span></div>
      <div class="pb scroll"><?php if (!$d['waiting']): ?><div class="empty">Nobody is waiting — pipeline is clear.</div><?php else: ?>
        <table><thead><tr><th>Candidate</th><th>Department</th><th>Owner</th><th class="num">Days</th><th></th></tr></thead>
        <tbody><?php foreach ($d['waiting'] as $w): ?>
          <tr><td><?= $e($w['nm']) ?></td><td><?= $e($w['dept']) ?></td><td><?= $e($w['mgr']) ?></td><td class="num"><?= (int)$w['days'] ?></td><td><a href="/candidate?id=<?= (int)$w['id'] ?>#ct=Timeline">Timeline ›</a></td></tr>
        <?php endforeach; ?></tbody></table>
      <?php endif; ?></div>
    </div>
    <div class="panel"><div class="ph"><h3>Biggest open demand</h3><span class="note">most vacancies still open</span></div>
      <div class="pb scroll"><?php if (!$d['demand']): ?><div class="empty">No open demand — every seat is filled.</div><?php else: ?>
        <table><thead><tr><th>Department</th><th>Role / grade</th><th class="num">Ordered</th><th class="num">Open</th><th></th></tr></thead>
        <tbody><?php foreach ($d['demand'] as $x): ?>
          <tr><td><?= $e($x['dept']) ?></td><td><?= $e($x['role']) ?></td><td class="num"><?= (int)$x['vac'] ?></td><td class="num"><?= (int)$x['open'] ?></td><td><a href="/requisition?id=<?= (int)$x['id'] ?>">Open ›</a></td></tr>
        <?php endforeach; ?></tbody></table>
      <?php endif; ?></div>
    </div>
  </div>

  <!-- 7. TRACKER + PROJECTS (added) -->
  <div class="band"><h2>Ownership, deployment &amp; the requirement tracker</h2><span class="add">added</span></div>
  <div class="g2 wide">
    <div class="panel"><div class="ph"><h3>Requirement tracker</h3><span class="note">Resp 1 = Recruiter · Resp 2 = Reporting manager</span></div>
      <div class="pb scroll"><?php if (!$d['tracker']): ?><div class="empty">No open requirements for these filters.</div><?php else: ?>
        <table><thead><tr><th>Req #</th><th>Posted</th><th>Resp 1</th><th>Resp 2</th><th>Working</th><th>Status</th><th class="num">Earned</th><th class="num">Lost</th><th></th></tr></thead>
        <tbody><?php foreach ($d['tracker'] as $t): $tone=$t['filled']>=$t['qty']?'var(--ok)':($t['filled']>0?'var(--warn)':'var(--bad)'); ?>
          <tr>
            <td><a href="/requisition?id=<?= (int)$t['id'] ?>"><?= $e($t['req']) ?></a></td>
            <td><?= $e($t['posted']) ?> <span class="sub2">×<?= (int)$t['qty'] ?></span></td>
            <td><?= $t['recruiter']!=='' ? $e($t['recruiter']) : '<span class="sub2">—</span>' ?></td>
            <td><?= $t['manager']!=='' ? $e($t['manager']) : '<span class="sub2">—</span>' ?></td>
            <td><span class="fillb"><span class="fbar"><i style="width:<?= round($t['filled']/max(1,$t['qty'])*100) ?>%;background:<?= $tone ?>"></i></span><span class="sub2"><?= (int)$t['filled'] ?>/<?= (int)$t['qty'] ?></span></span></td>
            <td><span class="sub2"><?= $e(REQ_STATUS[$t['status']] ?? $t['status']) ?></span></td>
            <td class="num money-pos"><?= $m($t['earned']) ?></td><td class="num money-neg"><?= $t['lost']>0?$m($t['lost']):'—' ?></td>
            <td><a href="/requisition?id=<?= (int)$t['id'] ?>">Open ›</a></td>
          </tr>
        <?php endforeach; ?></tbody></table>
      <?php endif; ?></div>
    </div>
    <div class="panel"><div class="ph"><h3>People working per project</h3><span class="note">deployed now vs ordered</span></div>
      <div class="pb"><?php if (!$d['projects']): ?><div class="empty">No projects with deployed manpower yet.</div><?php else: ?>
        <div class="proj"><?php foreach ($d['projects'] as $p): $w2=$p['ordered']>0?round($p['working']/$p['ordered']*100):0; ?>
          <div><div class="ptop"><span class="pn"><?= $e($p['site']) ?></span><span class="pc"><?= (int)$p['working'] ?> / <?= (int)$p['ordered'] ?></span></div>
            <div class="ptrack"><span class="ord" style="width:100%"></span><span class="wrk" style="width:<?= $w2 ?>%"></span><span class="lab"><?= (int)$p['working'] ?> working</span></div></div>
        <?php endforeach; ?></div>
      <?php endif; ?></div>
    </div>
  </div>

  <p class="foot">Source of every number: the Candidates, Requirements and Placement records inside this app — nothing external is linked. Cards, bars and rows open the live detail screen. Money figures reuse the approved placement commercials.</p>
</div>
