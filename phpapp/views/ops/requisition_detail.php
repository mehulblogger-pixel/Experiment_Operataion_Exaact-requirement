<?php
  $seeSal = can_see_salary();
  $outCost   = $outgoing ? ((float)$outgoing['salary_ctc']/12 + (float)$outgoing['agency_cost']) : 0;
  $hiredCost = $hired    ? ((float)$hired['salary_ctc']/12 + (float)$hired['agency_cost']) : 0;
  $stCls = ['OPEN'=>'p-warn','PROPOSED'=>'p-info','OFFERED'=>'p-info','HIRED'=>'p-ok','CLOSED'=>'p-ok','CANCELLED'=>'p-mut'][$req['status']] ?? 'p-mut';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/requisitions"><?= e(TP('requisition')) ?></a> › <?= e($req['req_code']) ?></div>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('requisition', $req['req_code'])) ?> <span class="pill <?= $stCls ?>" style="vertical-align:middle;font-size:12px"><?= e(lk_options_or('requisition_status', REQ_STATUS)[$req['status']] ?? $req['status']) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><?= e(DESIGNATIONS[$req['designation']] ?? ($req['designation'] ?: 'Position')) ?> · <?= e(lk_options_or('requisition_type', REQ_TYPES)[$req['req_type']] ?? '') ?> · <?= e($req['office_name'] ?: '—') ?></p></div>
  <?php if (is_coordinator_level()): ?><a class="btn secondary" href="/requisition-edit?id=<?= (int)$req['id'] ?>">Edit</a><?php endif; ?>
</div>

<div class="panel"><div class="kv-grid">
  <div><span class="k">Type</span><?= e(lk_options_or('requisition_type', REQ_TYPES)[$req['req_type']] ?? '') ?></div>
  <div><span class="k">Project / site</span><?= e($req['project_site'] ?: '—') ?></div>
  <div><span class="k">SBU</span><?= e(OPS_SBUS[$req['sbu']] ?? ($req['sbu'] ?: '—')) ?></div>
  <div><span class="k">Approval</span><?= e($req['approval_ref'] ?: '—') ?><?= $req['approval_date'] ? ' · '.e($req['approval_date']) : '' ?><?= $req['approved_by'] ? ' · by '.e($req['approved_by']) : '' ?></div>
  <?php if ($seeSal): ?><div><span class="k">Budgeted monthly cost</span><?= fmoney($req['budgeted_cost']) ?></div><?php endif; ?>
  <?php if ($req['notes']): ?><div class="kv-wide"><span class="k">Notes</span><?= e($req['notes']) ?></div><?php endif; ?>
</div></div>

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
  <table class="dt"><thead><tr><th>Candidate</th><th>Source</th><th>Stage</th><th></th></tr></thead><tbody>
    <?php foreach ($cands as $cd): ?><tr>
      <td><b><?= e(candidate_name($cd)) ?></b></td>
      <td><?= e(lk_options_or('candidate_source', CAND_SOURCES)[$cd['source']] ?? $cd['source']) ?></td>
      <td><span class="pill <?= in_array($cd['stage'],['ACCEPTED'],true)?'p-ok':(in_array($cd['stage'],['REJECTED','WITHDRAWN','OFFER_DECLINED'],true)?'p-bad':'p-info') ?>"><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$cd['stage']] ?? $cd['stage']) ?></span></td>
      <td><a class="btn small secondary" href="/candidate?id=<?= (int)$cd['id'] ?>">Open</a></td>
    </tr><?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="muted">No candidates yet. Add a CV against this requisition from <a href="/candidate-new?req=<?= (int)$req['id'] ?>">Hiring</a>.</p><?php endif; ?>
</div>
