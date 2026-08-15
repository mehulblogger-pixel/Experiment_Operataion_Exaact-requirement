<?php
// Project costing — the builder / cost sheet.
$roll = $roll ?? null; if (!$roll) { echo '<p>Not found.</p>'; return; }
$h = $roll['header']; $lines = $roll['lines']; $T = $roll['totals']; $byHead = $roll['by_head'];
$heads = $heads ?? []; $clients = $clients ?? []; $offices = $offices ?? [];
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$sym = function_exists('cur_sym') ? cur_sym() : '₹';
$locked = function_exists('pc_locked') && pc_locked($h);
$canEdit = function_exists('pc_can_edit') && pc_can_edit() && !$locked;
$canApprove = function_exists('pc_can_approve') && pc_can_approve();
$basis = (string)$h['basis'];
$qtyLabel = $basis === 'DAILY' ? 'Man-days' : ($basis === 'FIXED' ? 'Units' : 'Man-months');
$rateLabel = $basis === 'DAILY' ? '/man-day' : ($basis === 'FIXED' ? '/lump' : '/man-month');
$stTone = ['DRAFT'=>'p-mut','SUBMITTED'=>'p-warn','APPROVED'=>'p-ok','REJECTED'=>'p-bad'];
// Only show head COLUMNS that are actually used by a role, so a full catalogue
// (18 heads) does not make the table unreadably wide. The add-role form below
// still offers every head.
$shownHeads = array_filter($heads, fn($lbl, $hk) => isset($byHead[$hk]) && (float)$byHead[$hk] != 0, ARRAY_FILTER_USE_BOTH);
if (!$shownHeads) $shownHeads = [];
$nCols = count($shownHeads) + ($canEdit ? 9 : 8);
?>
<style>
  .pc .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:12px 0}
  .pc .kpi{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--brand);border-radius:12px;padding:12px 14px}
  .pc .kpi .l{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700}
  .pc .kpi .v{font-size:22px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:3px}
  .pc .kpi.ok{border-left-color:var(--ok)} .pc .kpi.ok .v{color:var(--ok)}
  .pc .kpi.bad{border-left-color:var(--bad)} .pc .kpi.bad .v{color:var(--bad)}
  .pc table.grid{font-size:12.5px} .pc table.grid th{white-space:nowrap}
  .pc .num{text-align:right;font-variant-numeric:tabular-nums}
  .pc .hh input{width:100%;padding:6px 7px;border:1px solid var(--line);border-radius:7px;font-size:12.5px;box-sizing:border-box}
  .pc .addrow td{background:var(--soft)} .pc .grp{font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.4px}
  .pc .bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0}
  .pc .lockmsg{background:var(--warn-soft,#fdf1e3);border:1px solid var(--line);border-radius:9px;padding:8px 12px;font-size:12.5px;margin:10px 0}
  @media(max-width:1000px){.pc .kpis{grid-template-columns:repeat(2,1fr)} .pc .wide-scroll{overflow-x:auto}}
</style>

<div class="pc">
<div class="crumbs"><a href="/">Home</a> › <a href="/project-costings">Project costing</a> › <?= $e($h['code']) ?></div>
<div class="master-head">
  <div><h1 style="margin:0"><?= $e($h['title']) ?> <span class="pill <?= $stTone[$h['status']] ?? 'p-mut' ?>"><?= $e(PC_STATUS[$h['status']] ?? $h['status']) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><?= $e($h['code']) ?> · <?= $e(PC_BASES[$basis] ?? $basis) ?><?= !empty($h['site']) ? ' · '.$e($h['site']) : '' ?></p></div>
  <div class="row-actions"><a class="btn secondary" href="/project-costing-print?id=<?= (int)$h['id'] ?>" target="_blank" rel="noopener">🖨 Print / PDF</a></div>
</div>

<?php if ($locked): ?><div class="lockmsg">🔒 This costing is <b><?= $e(PC_STATUS[$h['status']] ?? $h['status']) ?></b> and read-only.<?php if ($h['status']==='APPROVED' && !empty($h['approved_by'])): ?> Approved by <?= $e($h['approved_by']) ?><?= !empty($h['approval_ref']) ? ' · ref '.$e($h['approval_ref']) : '' ?>.<?php endif; ?><?php if ($canEdit===false && (pc_can_edit())): ?> <form method="post" action="/project-costing-reopen" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-ghost" style="padding:4px 10px">Reopen as draft</button></form><?php endif; ?></div><?php endif; ?>
<?php if ($h['status']==='REJECTED' && !empty($h['reject_note'])): ?><div class="lockmsg" style="background:var(--bad-soft,#fdecec)">↩ Sent back: <?= $e($h['reject_note']) ?></div><?php endif; ?>

<!-- Project rollup -->
<div class="kpis">
  <div class="kpi"><div class="l">Project revenue</div><div class="v"><?= fmoney_short($T['revenue']) ?></div></div>
  <div class="kpi"><div class="l">Project cost</div><div class="v"><?= fmoney_short($T['cost']) ?></div></div>
  <div class="kpi <?= $T['profit']>=0?'ok':'bad' ?>"><div class="l">Project profit</div><div class="v"><?= fmoney_short($T['profit']) ?></div></div>
  <div class="kpi <?= $T['margin']>=0?'ok':'bad' ?>"><div class="l">Margin</div><div class="v"><?= number_format($T['margin'],1) ?>%</div></div>
</div>

<!-- Roles table -->
<div class="panel wide-scroll">
  <h3 class="tab-sub" style="margin-top:0">Team roles <span class="muted">— <?= count($lines) ?> role(s), each built up from the cost heads</span></h3>
  <table class="grid">
    <thead><tr><th>Role</th>
      <?php foreach ($shownHeads as $hk=>$hl): ?><th class="num" title="<?= $e($hl) ?>"><?= $e(mb_strimwidth($hl,0,10,'…')) ?></th><?php endforeach; ?>
      <th class="num">Direct/mo</th><th class="num">Loaded/mo</th><th class="num">Rate<?= $e($rateLabel) ?></th><th class="num"><?= $e($qtyLabel) ?></th><th class="num">Revenue</th><th class="num">Profit</th><th class="num">Margin</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php $lastGrp = null; foreach ($lines as $ln): $c = pc_line_calc($ln, $h); $lh = $c['heads'];
      if (($ln['group_label'] ?? '') !== $lastGrp && ($ln['group_label'] ?? '') !== '') { $lastGrp = $ln['group_label']; echo '<tr><td colspan="'.$nCols.'" class="grp">'.$e($lastGrp).'</td></tr>'; } ?>
      <tr>
        <td><b><?= $e($ln['role_label']) ?></b></td>
        <?php foreach ($shownHeads as $hk=>$hl): ?><td class="num"><?= isset($lh[$hk]) ? number_format((float)$lh[$hk]) : '<span class="muted">—</span>' ?></td><?php endforeach; ?>
        <td class="num"><?= number_format($c['direct']) ?></td>
        <td class="num"><?= number_format($c['loaded']) ?></td>
        <td class="num"><b><?= number_format($c['proposed']) ?></b><?= (float)$ln['target_margin']>0 && (float)$ln['rate_manual']<=0 ? '<br><span class="muted" style="font-size:10px">@'.rtrim(rtrim(number_format((float)$ln['target_margin'],1),'0'),'.').'% marg</span>' : '' ?></td>
        <td class="num"><?= $basis==='FIXED' ? '1' : number_format((float)$ln['boq_qty'],($basis==='DAILY'?0:1)) ?><?= (float)$ln['oneoff']>0 ? '<br><span class="muted" style="font-size:10px">+'.fmoney_short($ln['oneoff']).' 1-off</span>' : '' ?></td>
        <td class="num"><?= fmoney_short($c['revenue']) ?></td>
        <td class="num" style="color:var(--<?= $c['profit']>=0?'ok':'bad' ?>)"><?= fmoney_short($c['profit']) ?></td>
        <td class="num"><?= number_format($c['margin'],1) ?>%</td>
        <?php if ($canEdit): ?><td class="num"><form method="post" action="/project-costing-line-delete" onsubmit="return confirm('Remove this role?')" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="line_id" value="<?= (int)$ln['id'] ?>"><button class="btn btn-ghost" style="padding:2px 8px" title="Remove">✕</button></form></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($lines)): ?><tr><td colspan="<?= $nCols ?>" class="muted" style="padding:10px">No roles yet.<?= $canEdit ? ' Add the first below.' : '' ?></td></tr><?php endif; ?>
    </tbody>
    <?php if (!empty($lines)): ?>
    <tfoot><tr style="border-top:2px solid var(--line);font-weight:700">
      <td>Totals</td>
      <?php foreach ($shownHeads as $hk=>$hl): ?><td class="num"><?= isset($byHead[$hk]) ? number_format((float)$byHead[$hk]) : '' ?></td><?php endforeach; ?>
      <td class="num"><?= number_format($T['direct']) ?></td><td></td><td></td><td></td>
      <td class="num"><?= fmoney_short($T['revenue']) ?></td>
      <td class="num" style="color:var(--<?= $T['profit']>=0?'ok':'bad' ?>)"><?= fmoney_short($T['profit']) ?></td>
      <td class="num"><?= number_format($T['margin'],1) ?>%</td><?php if ($canEdit): ?><td></td><?php endif; ?>
    </tr></tfoot>
    <?php endif; ?>
  </table>
</div>

<?php // ---- Use this costing: quotation + requirements --------------------------
  $canQuote = function_exists('can') && can('mod.quotes.edit');
  $canHire  = function_exists('can') && can('mod.hiring.edit');
  if (!empty($lines) && ($canQuote || $canHire)): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Use this costing</h3>
  <div class="bar" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
    <?php if ($canQuote): ?>
      <?php if (!empty($h['quote_id'])): ?>
        <a class="btn secondary" href="/quote?id=<?= (int)$h['quote_id'] ?>">📝 View linked quotation</a>
        <span class="muted" style="font-size:12px">A quotation was generated from this costing.</span>
      <?php else: ?>
        <form method="post" action="/project-costing-to-quote"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn primary">📝 Create quotation</button></form>
        <span class="muted" style="font-size:12px">One quote line per role, priced at the proposed rate × <?= $e(strtolower($qtyLabel)) ?>.</span>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($h['opportunity_id'])): ?><a class="btn secondary" href="/opportunity?id=<?= (int)$h['opportunity_id'] ?>">💡 Linked deal</a><?php endif; ?>
  </div>
  <?php if ($canHire): ?>
  <div class="scroll"><table class="grid" style="font-size:12.5px">
    <thead><tr><th>Role</th><th class="num">Rate<?= $e($rateLabel) ?></th><th class="num">Loaded/mo</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lines as $ln): $c = pc_line_calc($ln, $h); ?>
      <tr>
        <td><?= !empty($ln['group_label']) ? '<span class="muted">'.$e($ln['group_label']).' / </span>' : '' ?><b><?= $e($ln['role_label']) ?></b></td>
        <td class="num"><?= number_format($c['proposed']) ?></td>
        <td class="num"><?= number_format($c['loaded']) ?></td>
        <td class="num"><form method="post" action="/project-costing-to-req" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="line_id" value="<?= (int)$ln['id'] ?>"><button class="btn btn-ghost" style="padding:3px 10px" title="Create a recruitment requirement from this role">→ Requirement</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub" style="margin-top:6px">Each requirement carries this role's per-person economics (loaded cost, proposed rate, target margin); set the headcount &amp; duration on the requirement.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<!-- Add / edit a role -->
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">＋ Add a role</h3>
  <form method="post" action="/project-costing-line-save" id="lineForm">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label>Group <span class="muted">optional</span></label><input class="form-control" name="group_label" placeholder="e.g. Module / Cell"></div>
      <div class="ff ff-wide"><label>Role</label><input class="form-control" name="role_label" required placeholder="e.g. Shift Engineer"></div>
    </div>
    <p class="sub" style="margin:12px 0 4px">Cost heads — <?= $sym ?> per person per month<?= $basis==='DAILY' ? ' (day rates are per man-day; enter the monthly-equivalent build-up)' : '' ?></p>
    <div class="form-grid">
      <?php foreach ($heads as $hk=>$hl): ?>
        <div class="ff"><label><?= $e($hl) ?></label><input class="form-control pc-head" type="number" step="0.01" name="head[<?= $e($hk) ?>]" data-head="<?= $e($hk) ?>" value=""></div>
      <?php endforeach; ?>
    </div>
    <div class="form-grid" style="margin-top:6px">
      <div class="ff"><label><?= $e($qtyLabel) ?> (BOQ)</label><input class="form-control" type="number" step="0.01" id="pc_boq" name="boq_qty" value="<?= $basis==='FIXED'?'1':'' ?>"></div>
      <div class="ff"><label>Target margin (%) <span class="muted">sets the rate</span></label><input class="form-control" type="number" step="0.1" id="pc_tm" name="target_margin" placeholder="e.g. 22"></div>
      <div class="ff"><label>…or fixed rate<?= $e($rateLabel) ?> <span class="muted">override</span></label><input class="form-control" type="number" step="0.01" id="pc_rate" name="rate_manual" placeholder="overrides margin"></div>
      <div class="ff"><label>One-time / person <span class="muted">mobilisation</span></label><input class="form-control" type="number" step="0.01" id="pc_oneoff" name="oneoff" value=""></div>
    </div>
    <div class="rq-calc" style="background:var(--soft);border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin-top:10px;font-size:13px">
      <span>Direct <b id="pv_direct">—</b></span> · <span>Loaded <b id="pv_loaded">—</b></span> · <span>Rate <b id="pv_rate">—</b></span> · <span>Revenue <b id="pv_rev">—</b></span> · <span>Margin <b id="pv_marg">—</b></span>
    </div>
    <div style="margin-top:10px"><button class="btn" type="submit">Add role</button></div>
  </form>
</div>
<?php endif; ?>

<!-- Settings + notes + approval -->
<div class="g2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Loadings, basis &amp; details</h3>
    <?php if ($canEdit): ?>
    <form method="post" action="/project-costing-save">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
      <div class="form-grid">
        <div class="ff ff-wide"><label>Title</label><input class="form-control" name="title" value="<?= $e($h['title']) ?>"></div>
        <div class="ff"><label>Client</label><select class="form-control" name="client_id"><option value="">—</option>
          <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$h['client_id']===(int)$c['id']?'selected':'' ?>><?= $e($c['display_name'] ?? $c['legal_name'] ?? ('#'.$c['id'])) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Office</label><select class="form-control" name="office_id"><option value="">—</option>
          <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$h['office_id']===(int)$o['id']?'selected':'' ?>><?= $e($o['name']) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Site</label><input class="form-control" name="site" value="<?= $e($h['site']) ?>"></div>
        <div class="ff"><label>Basis</label><select class="form-control" name="basis"><?php foreach (PC_BASES as $k=>$v): ?><option value="<?= $e($k) ?>" <?= $basis===$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Loadings applied on</label><select class="form-control" name="load_on"><option value="COST" <?= strtoupper((string)$h['load_on'])!=='RATE'?'selected':'' ?>>Direct cost (standard)</option><option value="RATE" <?= strtoupper((string)$h['load_on'])==='RATE'?'selected':'' ?>>Sell rate (Excel-style)</option></select></div>
        <div class="ff"><label>Overhead %</label><input class="form-control" type="number" step="0.01" name="overhead_pct" value="<?= $e($h['overhead_pct']) ?>"></div>
        <div class="ff"><label>Insurance %</label><input class="form-control" type="number" step="0.01" name="insurance_pct" value="<?= $e($h['insurance_pct']) ?>"></div>
        <div class="ff"><label>Bad debt %</label><input class="form-control" type="number" step="0.01" name="baddebt_pct" value="<?= $e($h['baddebt_pct']) ?>"></div>
        <div class="ff"><label>Contingency %</label><input class="form-control" type="number" step="0.01" name="contingency_pct" value="<?= $e($h['contingency_pct']) ?>"></div>
        <div class="ff"><label>Negotiation %</label><input class="form-control" type="number" step="0.01" name="negotiation_pct" value="<?= $e($h['negotiation_pct']) ?>"></div>
      </div>
      <div class="form-grid" style="margin-top:6px">
        <div class="ff ff-wide"><label>Inclusions</label><textarea class="form-control" name="inclusions" rows="2"><?= $e($h['inclusions']) ?></textarea></div>
        <div class="ff ff-wide"><label>Exclusions <span class="muted">— "Not included…"</span></label><textarea class="form-control" name="exclusions" rows="2"><?= $e($h['exclusions']) ?></textarea></div>
        <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= $e($h['notes']) ?>"></div>
      </div>
      <div style="margin-top:8px"><button class="btn" type="submit">Save settings</button></div>
    </form>
    <?php else: ?>
      <div class="kv-grid" style="font-size:13px">
        <div><span class="muted">Loadings</span> OH <?= $e($h['overhead_pct']) ?>% · Ins <?= $e($h['insurance_pct']) ?>% · BD <?= $e($h['baddebt_pct']) ?>% · Cont <?= $e($h['contingency_pct']) ?>% · Neg <?= $e($h['negotiation_pct']) ?>%</div>
        <?php if (!empty($h['inclusions'])): ?><div style="margin-top:6px"><span class="muted">Inclusions:</span> <?= nl2br($e($h['inclusions'])) ?></div><?php endif; ?>
        <?php if (!empty($h['exclusions'])): ?><div style="margin-top:6px"><span class="muted">Exclusions:</span> <?= nl2br($e($h['exclusions'])) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Approval</h3>
    <div class="bar">
      <?php if (in_array($h['status'],['DRAFT','REJECTED'],true) && function_exists('pc_can_edit') && pc_can_edit() && !empty($lines)): ?>
        <form method="post" action="/project-costing-submit"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn primary">Submit for approval</button></form>
      <?php endif; ?>
      <?php if ($h['status']==='SUBMITTED' && $canApprove): ?>
        <form method="post" action="/project-costing-approve" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input class="form-control" name="approval_ref" placeholder="Approval ref (optional)" style="width:170px"><button class="btn primary">Approve</button></form>
        <form method="post" action="/project-costing-reject" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input class="form-control" name="reject_note" placeholder="Reason" style="width:150px"><button class="btn btn-ghost">Send back</button></form>
      <?php endif; ?>
      <?php if (!in_array($h['status'],['DRAFT'],true) && function_exists('pc_can_edit') && pc_can_edit() && $h['status']!=='SUBMITTED'): ?>
        <form method="post" action="/project-costing-reopen"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-ghost">Reopen as draft</button></form>
      <?php endif; ?>
      <?php if ($h['status']==='DRAFT' && empty($lines)): ?><span class="muted" style="font-size:12.5px">Add at least one role before submitting.</span><?php endif; ?>
    </div>
    <p class="sub" style="margin-top:6px">Draft → Submitted → Approved. Submitting locks the sheet; an approver approves or sends it back with a note. Approval is optional — you can keep it in Draft and still use the numbers.</p>
  </div>
</div>
</div>

<?php if ($canEdit): ?>
<script>(function(){
  var f=document.getElementById('lineForm'); if(!f) return;
  var sym='<?= $e($sym) ?>', basis='<?= $e($basis) ?>';
  var OH=<?= (float)$h['overhead_pct'] ?>, INS=<?= (float)$h['insurance_pct'] ?>, BD=<?= (float)$h['baddebt_pct'] ?>, CN=<?= (float)$h['contingency_pct'] ?>, NEG=<?= (float)$h['negotiation_pct'] ?>;
  var ONRATE=<?= strtoupper((string)$h['load_on'])==='RATE' ? 'true':'false' ?>, L=OH+INS+BD+CN;
  function n(el){ return el?(parseFloat(el.value)||0):0; }
  function fmt(v){ return sym+' '+Math.round(v).toLocaleString('en-IN'); }
  function calc(){
    var direct=0; f.querySelectorAll('.pc-head').forEach(function(el){ direct+=n(el); });
    var tm=n(document.getElementById('pc_tm')), man=n(document.getElementById('pc_rate'));
    var rate, loaded;
    if(ONRATE){
      if(man>0){ rate=man; } else { var d=1-tm/100-L/100; rate=(tm>0&&d>0)?direct/d:direct; }
      loaded=direct+rate*L/100;
    } else {
      loaded=direct*(1+L/100);
      rate=man>0?man:((tm>0&&tm<100)?loaded/(1-tm/100):loaded);
    }
    var proposed=rate*(1+NEG/100);
    var boq=n(document.getElementById('pc_boq')), oneoff=n(document.getElementById('pc_oneoff'));
    var rev, cost;
    if(basis==='FIXED'){ rev=proposed; cost=loaded+oneoff; }
    else { rev=proposed*boq; cost=loaded*boq+oneoff; }
    var pf=rev-cost, mg=rev>0?pf/rev*100:0;
    document.getElementById('pv_direct').textContent=direct?fmt(direct):'—';
    document.getElementById('pv_loaded').textContent=loaded?fmt(loaded):'—';
    document.getElementById('pv_rate').textContent=proposed?fmt(proposed):'—';
    document.getElementById('pv_rev').textContent=rev?fmt(rev):'—';
    document.getElementById('pv_marg').textContent=rev?mg.toFixed(1)+'%':'—';
  }
  f.querySelectorAll('input').forEach(function(el){ el.addEventListener('input',calc); });
  calc();
})();</script>
<?php endif; ?>
