<?php
  $seeSal = can_see_salary();
  $outCost   = $outgoing ? ((float)$outgoing['salary_ctc']/12 + (float)$outgoing['agency_cost']) : 0;
  $hiredCost = $hired    ? ((float)$hired['salary_ctc']/12 + (float)$hired['agency_cost']) : 0;
  $stCls = ['OPEN'=>'p-warn','PROPOSED'=>'p-info','OFFERED'=>'p-info','HIRED'=>'p-ok','CLOSED'=>'p-ok','CANCELLED'=>'p-mut'][$req['status']] ?? 'p-mut';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/requisitions"><?= e(TP('requisition')) ?></a> › <?= e($req['req_code']) ?></div>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('requisition', $req['req_code'])) ?> <span class="pill <?= $stCls ?>" style="vertical-align:middle;font-size:12px"><?= e(lk_options_or('requisition_status', REQ_STATUS)[$req['status']] ?? $req['status']) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><?= e(DESIGNATIONS[$req['designation']] ?? ($req['designation'] ?: 'Position')) ?><?php if ((int)($req['quantity'] ?? 1) > 1): ?> <span class="pill p-info" style="font-size:11px">× <?= (int)$req['quantity'] ?></span><?php endif; ?> · <?= e(lk_options_or('requisition_type', REQ_TYPES)[$req['req_type']] ?? '') ?> · <?= e($req['office_name'] ?: '—') ?><?php if (!empty($req['client_name']) || !empty($req['client_legal'])): ?> · <?= e($req['client_name'] ?: $req['client_legal']) ?><?php endif; ?></p></div>
  <?php if (is_coordinator_level()): ?><a class="btn secondary" href="/requisition-edit?id=<?= (int)$req['id'] ?>">Edit</a><?php endif; ?>
</div>

<?php // §18 — Requirement Health: is this vacancy on track to fill in time?
$h = $health ?? null; if ($h): [$hband, $htone] = $h['band']; ?>
<div class="panel" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
  <div style="text-align:center;min-width:96px">
    <div style="font-size:34px;font-weight:800;line-height:1;color:var(--<?= $h['score']>=75?'ok':($h['score']>=45?'warn':'bad') ?>,#333)"><?= (int)$h['score'] ?></div>
    <div class="pill <?= e($htone) ?>" style="margin-top:5px"><?= e($hband) ?></div>
    <div class="muted" style="font-size:11px;margin-top:4px">Requirement health</div>
  </div>
  <div style="flex:1;min-width:220px">
    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px;margin-bottom:6px">
      <span><b><?= (int)$h['filled'] ?></b> / <?= (int)$h['quantity'] ?> filled</span>
      <span><b><?= (int)$h['pipeline'] ?></b> in pipeline</span>
      <?php if ($h['days_to_start'] !== null): ?><span>mobilises in <b><?= (int)$h['days_to_start'] ?></b> day(s)</span><?php endif; ?>
    </div>
    <?php if (!empty($h['reasons'])): ?><div class="muted" style="font-size:12.5px"><?= e(implode(' · ', $h['reasons'])) ?></div><?php endif; ?>
    <?php if (!empty($h['actions'])): ?><div style="margin-top:6px;font-size:12.5px"><b>Do next:</b> <?= e(implode(' · ', $h['actions'])) ?></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel"><div class="kv-grid">
  <div><span class="k">Type</span><?= e(lk_options_or('requisition_type', REQ_TYPES)[$req['req_type']] ?? '') ?></div>
  <div><span class="k">Project / site</span><?= e($req['project_site'] ?: '—') ?></div>
  <div><span class="k"><?= e(T("sbu")) ?></span><?= e(OPS_SBUS[$req['sbu']] ?? ($req['sbu'] ?: '—')) ?></div>
  <div><span class="k">Approval</span><?= e($req['approval_ref'] ?: '—') ?><?= $req['approval_date'] ? ' · '.e($req['approval_date']) : '' ?><?= $req['approved_by'] ? ' · by '.e($req['approved_by']) : '' ?></div>
  <?php if ($seeSal): ?><div><span class="k">Budgeted monthly cost</span><?= fmoney($req['budgeted_cost']) ?></div><?php endif; ?>
  <?php if ($req['notes']): ?><div class="kv-wide"><span class="k">Notes</span><?= e($req['notes']) ?></div><?php endif; ?>
  <?php if (function_exists('custom_display')) foreach (custom_display('requisition', $req['id']) as $cf): ?>
    <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value'] ?: '—') ?></div>
  <?php endforeach; ?>
</div></div>

<?php
// ---- Phase 2 enrichment panels: only render what has been filled in --------
$has = fn($k) => isset($req[$k]) && trim((string)$req[$k]) !== '' && (string)$req[$k] !== '0';
$yes = fn($k) => !empty($req[$k]);
$WM = defined('PHP_INT_MAX') && function_exists('recruit_home_can') ? (REQ_WORK_MODELS[$req['work_model'] ?? ''] ?? ($req['work_model'] ?? '')) : ($req['work_model'] ?? '');
$SH = REQ_SHIFTS[$req['shift'] ?? ''] ?? ($req['shift'] ?? '');
$position = $has('discipline') || $has('category') || $has('qualification') || $has('skills') || $has('experience_min') || $has('relevant_experience') || (int)($req['quantity'] ?? 1) > 1;
$deploy = $has('work_model') || $has('deploy_location') || $has('start_date') || $has('end_date') || $has('duration_months') || $has('duty_hours') || $has('shift') || $yes('prov_travel') || $yes('prov_accommodation') || $yes('prov_food') || $has('other_allowances');
$selc = $yes('sel_client_interview') || $yes('sel_tech_interview') || $yes('sel_hr_interview') || $yes('client_approval_req') || $yes('training_req') || $yes('cmp_medical') || $yes('cmp_pcc') || $yes('cmp_gate_pass') || $yes('cmp_safety') || $yes('cmp_certification') || $has('documents_note');
$commer = $has('billing_rate') || (float)($req['expected_revenue'] ?? 0) != 0 || $has('target_margin') || $has('negotiation_floor');
?>

<?php if ($position || $deploy): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Position &amp; deployment</h3><div class="kv-grid">
  <?php if ((int)($req['quantity'] ?? 1) > 0): ?><div><span class="k">Headcount</span><?= (int)($req['quantity'] ?? 1) ?></div><?php endif; ?>
  <?php if ($has('discipline')): ?><div><span class="k">Discipline</span><?= e($req['discipline']) ?></div><?php endif; ?>
  <?php if ($has('category')): ?><div><span class="k">Category</span><?= e($req['category']) ?></div><?php endif; ?>
  <?php if ($has('qualification')): ?><div><span class="k">Qualification</span><?= e($req['qualification']) ?></div><?php endif; ?>
  <?php if ($has('skills')): ?><div><span class="k">Skills / certifications</span><?= e($req['skills']) ?></div><?php endif; ?>
  <?php if ($has('experience_min')): ?><div><span class="k">Min experience</span><?= e($req['experience_min']) ?> yrs<?= $has('relevant_experience') ? ' · '.e($req['relevant_experience']).' relevant' : '' ?></div><?php endif; ?>
  <?php if ($has('work_model')): ?><div><span class="k">Work model</span><?= e($WM) ?></div><?php endif; ?>
  <?php if ($has('deploy_location')): ?><div><span class="k">Location</span><?= e($req['deploy_location']) ?></div><?php endif; ?>
  <?php if ($has('start_date') || $has('end_date')): ?><div><span class="k">Period</span><?= e($req['start_date'] ?: '—') ?> → <?= e($req['end_date'] ?: '—') ?><?= (float)($req['duration_months'] ?? 0) > 0 ? ' · '.rtrim(rtrim(number_format((float)$req['duration_months'],2),'0'),'.').' mo' : '' ?></div><?php endif; ?>
  <?php if ($has('duty_hours')): ?><div><span class="k">Duty hours</span><?= e($req['duty_hours']) ?></div><?php endif; ?>
  <?php if ($has('shift')): ?><div><span class="k">Shift</span><?= e($SH) ?></div><?php endif; ?>
  <?php $prov = array_filter(['Travel'=>$yes('prov_travel'),'Accommodation'=>$yes('prov_accommodation'),'Food'=>$yes('prov_food')]);
        if ($prov || $has('other_allowances')): ?><div><span class="k">Provided</span><?= e(implode(' · ', array_keys($prov)) ?: '—') ?><?= $has('other_allowances') ? ' · '.e($req['other_allowances']) : '' ?></div><?php endif; ?>
  <?php if (!empty($req['contact_name'])): ?><div><span class="k">Client contact</span><?= e($req['contact_name']) ?><?= !empty($req['contact_phone']) ? ' · '.e($req['contact_phone']) : '' ?></div><?php endif; ?>
  <?php if (!empty($req['contract_ref'])): ?><div><span class="k">Contract / PO</span><?= e($req['contract_ref']) ?></div><?php endif; ?>
</div></div>
<?php endif; ?>

<?php if ($selc): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Selection &amp; compliance</h3>
  <?php
  $chips = array_filter([
    $yes('sel_client_interview') ? 'Client interview' : '', $yes('sel_tech_interview') ? 'Technical interview' : '',
    $yes('sel_hr_interview') ? 'HR interview' : '', $yes('client_approval_req') ? 'Client approval' : '', $yes('training_req') ? 'Training' : '',
    $yes('cmp_medical') ? 'Medical' : '', $yes('cmp_pcc') ? 'Police verification' : '', $yes('cmp_gate_pass') ? 'Gate pass' : '',
    $yes('cmp_safety') ? 'Safety induction' : '', $yes('cmp_certification') ? 'Certification' : '',
  ]);
  foreach ($chips as $c) echo '<span class="pill p-info" style="margin:2px 4px 2px 0">' . e($c) . '</span>';
  if ($has('documents_note')) echo '<p class="sub" style="margin:8px 0 0">' . e($req['documents_note']) . '</p>';
  ?>
</div>
<?php endif; ?>

<?php if ($commer && $seeSal):
  $rev = (float)($req['expected_revenue'] ?? 0); $prof = (float)($req['expected_profit'] ?? 0);
  $cost = $rev - $prof; $marg = $rev > 0 ? $prof / $rev * 100 : 0; ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Commercial (expected)</h3>
  <div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="kic">💰</span><div class="k">Revenue</div><div class="v"><?= fmoney_short($rev) ?></div><div class="d"><?= e($req['quantity'] ?? 1) ?> × <?= fmoney_short($req['billing_rate'] ?? 0) ?> <?= e(REQ_RATE_BASIS[$req['rate_basis'] ?? 'MONTHLY'] ?? '') ?></div></div>
    <div class="kpi"><span class="kic">🧾</span><div class="k">Cost</div><div class="v"><?= fmoney_short($cost) ?></div><div class="d">est.</div></div>
    <div class="kpi"><span class="kic">📈</span><div class="k">Profit</div><div class="v <?= $prof>=0?'up':'down' ?>"><?= fmoney_short($prof) ?></div><div class="d"><?= $marg>=0?'':'−' ?><?= number_format(abs($marg),1) ?>% margin</div></div>
    <div class="kpi"><span class="kic">🎯</span><div class="k">Target / floor</div><div class="v"><?= $has('target_margin') ? number_format((float)$req['target_margin'],1).'%' : '—' ?></div><div class="d"><?= $has('negotiation_floor') ? 'floor '.fmoney_short($req['negotiation_floor']) : 'no floor set' ?></div></div>
  </div>
</div>
<?php endif; ?>

<?php // Cost build-up — how the per-person cost is made up, per the sourcing
      // model, and the project-level rollup (recurring + one-off).
if ($seeSal && function_exists('req_cost_buildup')
    && (!empty($req['sourcing_model']) || (float)($req['cost_wage'] ?? 0) > 0)):
  $bu = req_cost_buildup($req); $cm = req_commercials($req);
  $smLabel = (defined('REQ_SOURCING_MODELS') ? (REQ_SOURCING_MODELS[$req['sourcing_model'] ?? ''] ?? '') : '');
  $qty = max(1,(int)($req['quantity'] ?? 1)); ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Cost build-up
  <?php if ($smLabel): ?><span class="pill p-info" style="font-size:11px;margin-left:4px"><?= e($smLabel) ?></span><?php endif; ?>
  <span class="muted" style="font-weight:400">— how the per-person cost is made up</span></h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
    <div>
      <table class="grid" style="width:100%">
        <tbody>
        <?php foreach ($bu['lines'] as $ln): ?>
          <tr><td style="color:var(--muted)"><?= e($ln['k']) ?></td><td style="text-align:right"><?= fmoney($ln['v']) ?></td></tr>
        <?php endforeach; ?>
          <tr style="border-top:2px solid var(--line)"><td><b>Cost / person / month</b></td><td style="text-align:right"><b><?= fmoney($bu['monthly']) ?></b></td></tr>
          <?php if ($bu['oneoff'] > 0): ?><tr><td style="color:var(--muted)">One-time / person <span class="muted">(medical/PCC/training/mobilisation)</span></td><td style="text-align:right"><?= fmoney($bu['oneoff']) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="kpi-row" style="grid-template-columns:1fr 1fr">
      <div class="kpi"><span class="kic">👥</span><div class="k">Project cost</div><div class="v"><?= fmoney_short($cm['cost']) ?></div><div class="d"><?= $qty ?> person(s) · <?= number_format((float)$cm['months'],1) ?> mo</div></div>
      <div class="kpi"><span class="kic">🔁</span><div class="k">Recurring</div><div class="v"><?= fmoney_short($cm['recurring_cost'] ?? 0) ?></div><div class="d"><?= fmoney_short($bu['monthly']) ?>/person·mo</div></div>
      <?php if (($cm['oneoff_total'] ?? 0) > 0): ?><div class="kpi"><span class="kic">📦</span><div class="k">One-off total</div><div class="v"><?= fmoney_short($cm['oneoff_total']) ?></div><div class="d"><?= $qty ?> × <?= fmoney_short($bu['oneoff']) ?></div></div><?php endif; ?>
      <div class="kpi"><span class="kic">📈</span><div class="k">Project profit</div><div class="v <?= ($cm['profit']??0)>=0?'up':'down' ?>"><?= fmoney_short($cm['profit'] ?? 0) ?></div><div class="d"><?= ($cm['revenue']??0)>0 ? number_format(($cm['profit']/$cm['revenue'])*100,1).'% margin' : 'set billing rate' ?></div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php // Phase 5 — commercial rollup across the placements made on this requirement.
$rollup = $rollup ?? null;
if ($seeSal && !empty($rollup) && (int)($rollup['n'] ?? 0) > 0): $R = $rollup; ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Placements — planned vs approved vs actual
  <span class="muted">— <?= (int)$R['n'] ?> hired · <?= (int)$R['approved'] ?> approved<?= (int)$R['n_act']>0 ? ' · '.(int)$R['n_act'].' with actuals' : '' ?></span></h3>
  <div class="kpi-row" style="grid-template-columns:repeat(3,1fr)">
    <?php
    $rtier = function($label, $rev, $prof, $marg, $sub) {
      $mc = $prof >= 0 ? 'up' : 'down';
      return '<div class="kpi"><div class="k">'.$label.'</div><div class="v">'.fmoney_short($rev).'</div>'
        .'<div class="d"><b class="'.$mc.'">'.fmoney_short($prof).'</b> · '.($marg>=0?'':'−').number_format(abs($marg),1).'% · '.$sub.'</div></div>';
    };
    echo $rtier('Planned <span class="muted" style="font-weight:400">(requirement)</span>', $R['plan_rev'], $R['plan_profit'], $R['plan_margin'], 'as budgeted');
    echo $rtier('Approved <span class="muted" style="font-weight:400">(locked hires)</span>', $R['appr_rev'], $R['appr_profit'], $R['appr_margin'], 'this many hires');
    if ((int)$R['n_act'] > 0) echo $rtier('Actual <span class="muted" style="font-weight:400">(billed &amp; paid)</span>', $R['act_rev'], $R['act_profit'], $R['act_margin'], (int)$R['n_act'].' recorded');
    else echo '<div class="kpi"><div class="k">Actual <span class="muted" style="font-weight:400">(billed &amp; paid)</span></div><div class="v" style="color:var(--muted)">—</div><div class="d">no actuals recorded yet</div></div>';
    ?>
  </div>
  <?php $dv = $R['appr_profit'] - $R['plan_profit']; if (abs($dv) >= 1): ?>
  <p class="muted" style="font-size:12px;margin:8px 2px 0">Approved profit is running <span class="pill <?= $dv>=0?'p-ok':'p-bad' ?>" style="font-size:11px"><?= $dv>=0?'+':'−' ?><?= fmoney_short(abs($dv)) ?></span> against the requirement plan.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($seeSal && ($outgoing || $hired)): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Cost comparison (monthly)</h3>
  <div class="kpi-row" style="grid-template-columns:repeat(3,1fr)">
    <?php if ($outgoing): ?><div class="kpi"><span class="kic">↩️</span><div class="k">Outgoing — <?= e($outgoing['name']) ?></div><div class="v"><?= fmoney_short($outCost) ?></div><div class="d">previous holder</div></div><?php endif; ?>
    <div class="kpi"><span class="kic">🎯</span><div class="k">Budgeted</div><div class="v"><?= fmoney_short($req['budgeted_cost']) ?></div><div class="d">approved</div></div>
    <?php if ($hired): ?><div class="kpi" style="border-color:color-mix(in srgb,var(--<?= $hiredCost<=(float)$req['budgeted_cost']?'ok':'bad' ?>) 45%,var(--line))"><span class="kic">✅</span><div class="k">Hired — <?= e($hired['name']) ?></div><div class="v <?= ($req['budgeted_cost']>0 && $hiredCost<=(float)$req['budgeted_cost'])?'up':'down' ?>"><?= fmoney_short($hiredCost) ?></div><div class="d"><?= $req['budgeted_cost']>0 ? ($hiredCost<=(float)$req['budgeted_cost']?'within budget':'over budget') : 'actual' ?></div></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel"><h3 class="tab-sub" style="margin-top:0">Candidates against this requisition <span class="muted">(<?= count($cands) ?>)</span></h3>
  <?php if ($cands): ?>
  <table class="dt"><thead><tr><th>Candidate</th><th>Source</th><th>Stage</th><th>Fit</th><th></th></tr></thead><tbody>
    <?php foreach ($cands as $cd): ?><tr>
      <td><b><?= e(candidate_name($cd)) ?></b></td>
      <td><?= e(lk_options_or('candidate_source', CAND_SOURCES)[$cd['source']] ?? $cd['source']) ?></td>
      <td><span class="pill <?= in_array($cd['stage'],['ACCEPTED'],true)?'p-ok':(in_array($cd['stage'],['REJECTED','WITHDRAWN','OFFER_DECLINED'],true)?'p-bad':'p-info') ?>"><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$cd['stage']] ?? $cd['stage']) ?></span></td>
      <td><?php $ft = $cd['fit'] ?? null; if ($ft) { [$fl, $ftone] = recruit_fit_band($ft['score']);
            $tip = implode(' · ', array_map(fn($x) => $x['label'] . ': ' . $x['note'], array_filter($ft['factors'], fn($x) => $x['state'] !== 'part'))); ?>
        <span class="pill <?= e($ftone) ?>" title="<?= e($tip) ?>"><?= (int)$ft['score'] ?>% <?= e($fl) ?></span><?php } else { echo '—'; } ?></td>
      <td><a class="btn small secondary" href="/candidate?id=<?= (int)$cd['id'] ?>">Open</a></td>
    </tr><?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="muted">No candidates yet. Add a CV against this requisition from <a href="/candidate-new?req=<?= (int)$req['id'] ?>">Hiring</a>.</p><?php endif; ?>
</div>

<?php // §16/§19 — before recruiting outside, who in the existing pool fits?
if (!empty($pool)): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">💡 Best matches from the pool <span class="muted">— existing people who fit, not yet on this requirement</span></h3>
  <table class="dt"><thead><tr><th>Candidate</th><th>Designation</th><th>Fit</th><th>Why</th><th></th></tr></thead><tbody>
    <?php foreach ($pool as $pc): [$fl, $ftone] = recruit_fit_band($pc['fit']['score']);
      $why = implode(' · ', array_map(fn($x) => $x['label'], array_filter($pc['fit']['factors'], fn($x) => $x['state'] === 'ok'))); ?>
    <tr>
      <td><b><?= e(candidate_name($pc)) ?></b> <span class="muted"><?= e($pc['cand_code']) ?></span></td>
      <td><?= e(lk_options_or('designation', DESIGNATIONS)[$pc['designation']] ?? ($pc['designation'] ?: '—')) ?></td>
      <td><span class="pill <?= e($ftone) ?>"><?= (int)$pc['fit']['score'] ?>% <?= e($fl) ?></span></td>
      <td class="muted" style="font-size:12px"><?= e($why ?: '—') ?></td>
      <td><a class="btn small secondary" href="/candidate?id=<?= (int)$pc['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <p class="muted" style="font-size:12px;margin:6px 2px 0">Scored on discipline, skills, experience, designation, business unit, location and cost — reuse a fit person before sourcing externally.</p>
</div>
<?php endif; ?>
