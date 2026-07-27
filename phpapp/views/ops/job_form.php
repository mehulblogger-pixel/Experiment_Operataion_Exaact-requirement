<?php
  $act = activity_options_by_sbu();
  // Prefill from the call for a new job; use the job's own values when editing.
  $curSbu   = $job ? ($job['sbu'] ?? '')             : ($call['sbu'] ?? '');
  $curActId = $job ? ($job['activity_id'] ?? '')     : ($call['activity_id'] ?? '');
  $curInsp  = $job ? ($job['inspection_type'] ?? '') : ($call['inspection_type'] ?? '');
  // §i — the reporting rhythm and the report formats are agreed on the call, so
  // they arrive here already answered. The coordinator can still change them for
  // this one deputation, but nobody has to remember what was promised.
  $curFreq  = $job ? ($job['reporting_frequency'] ?? 'NOREPORT') : (($call['reporting_frequency'] ?? '') ?: 'NOREPORT');
  $curDays  = $job ? ($job['report_custom_days'] ?? '') : ($call['report_custom_days'] ?? '');
  $curDeliv = $job
      ? (trim((string)($job['deliverables'] ?? '')) !== '' ? explode(',', $job['deliverables']) : [])
      : array_values(array_filter(array_map('trim', explode(',', (string)($call['deliverables'] ?? '')))));
  $curActRow = $curActId ? lk_value($curActId) : null;
  // §b.i / §b.iii — everything already settled on the call flows through, so the
  // coordinator allocates rather than re-types.
  $curFolder   = $job ? ($job['folder_link'] ?? '')   : ($call['folder_link'] ?? '');
  $curContract = $job ? ($job['contract_number'] ?? ''): ($call['contract_number'] ?? '');
  $curQuoteId  = $job ? ($job['quotation_id'] ?? '')  : ($call['quotation_id'] ?? '');
  // §b.vi — the call's visit dates become the deputation's, and more can be added.
  $curDates = call_dates_parse($job ? ($job['inspection_dates'] ?? '') : ($call['inspection_dates'] ?? ''));
  if (!$curDates && $job && !empty($job['scheduled_date'])) $curDates = [$job['scheduled_date']];
  // Older jobs kept three loose "random" dates — fold them in so nothing is lost.
  if ($job) $curDates = call_dates_parse(implode(',', array_merge($curDates,
      array_filter([$job['random_date1'] ?? '', $job['random_date2'] ?? '', $job['random_date3'] ?? '']))));
  $dateSlots = max(5, min(20, count($curDates) + 3));
  // §b.iv — cross-office means the contracting office GIVES credit to the executor.
  $mng  = $call['ibo_office_id'] ?? null; $exe = $call['executing_office_id'] ?? null;
  $cross = $exe && (!$mng || (int)$mng !== (int)$exe);
  $curDir = $job ? ($job['credit_direction'] ?? '') : ($cross ? 'GIVEN' : '');
  $ex = credit_explainer($mng, $exe);
  // §b.vii — our own employee, or somebody else's.
  $curKind = '';
  if ($job && !empty($job['subcon_id'])) $curKind = 'SUBCON';
  elseif ($job && !empty($job['inspector_id'])) {
      $ik = ops_val("SELECT staff_kind FROM inspectors WHERE id=?", [(int)$job['inspector_id']]);
      $curKind = ($ik === 'FREELANCER') ? 'FREELANCER' : (($ik === 'SUBCON') ? 'SUBCON' : 'ASSET');
  }
  function contact_block($info, $role) {
    if (!$info) { echo '<div class="muted">No ' . e($role) . ' selected on the call.</div>'; return; }
    $p = $info['p'];
    echo '<div class="info-party"><strong>' . e($p['display_name'] ?: $p['legal_name']) . '</strong> <span class="muted">' . e($p['code']) . '</span>';
    if ($p['gstin']) echo '<div class="muted">GSTIN ' . e($p['gstin']) . '</div>';
    $addr = $info['addresses'][0] ?? null;
    if ($addr) echo '<div class="muted">' . e(trim(($addr['line1'] ?? '') . ' ' . ($addr['city'] ?? '') . ' ' . ($addr['state'] ?? ''))) . '</div>';
    if ($info['contacts']) {
      echo '<table class="mini"><tr><th>Contact</th><th>Designation</th><th>Mobile</th><th>Email</th></tr>';
      foreach ($info['contacts'] as $c) echo '<tr><td>' . e($c['name']) . '</td><td>' . e($c['designation'] ?: '—') . '</td><td>' . e($c['mobile'] ?: $c['phone'] ?: '—') . '</td><td>' . e($c['email'] ?: '—') . '</td></tr>';
      echo '</table>';
    } else echo '<div class="muted">No contact persons recorded.</div>';
    echo '</div>';
  }
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/calls"><?= e(T_REG('call')) ?></a> › <a href="/call?id=<?= (int)$call['id'] ?>"><?= e($call['call_code']) ?></a> › <?= $job ? e($job['job_code']) : 'Allocate' ?></div>
<div class="master-head">
  <div><h1><?= $job ? 'Edit ' . e(Tl('job')) . ' ' . e($job['job_code']) : 'Allocate ' . e(Tl('call')) . ' ' . e($call['call_code']) ?></h1>
    <p class="sub" style="margin:2px 0 0">Everything agreed on the <?= e(Tl('call')) ?> is already filled in below. Pick who does it and when — the <?= e(Tl('engineer')) ?> is e-mailed once a date is set.</p></div>
  <a class="btn secondary" href="/call?id=<?= (int)$call['id'] ?>">← Back</a>
</div>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<?php // The contract position, stated before the form is filled in. A blocked
      // allocation is refused on submit as well, but being told up front is the
      // difference between a warning and a wasted five minutes. ?>
<?php if (!empty($gate) && !$gate['allowed']): ?>
<div class="panel" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 7%,transparent)">
  <b style="color:var(--bad)">⛔ This order cannot be allocated against</b>
  <div class="muted" style="margin-top:4px"><?= e($gate['reason']) ?>
    <?php if (!empty($gate['pending'])): ?>
      An exception is <?= e(strtolower(override_status_text($gate['pending']))) ?>.
    <?php else: ?>
      Ask for an exception from the <?= e(Tl('call')) ?>.
      <?= e(override_flow_text($gate['state'] === 'EXPIRED' ? 'EXPIRED' : 'EXHAUSTED')) ?>
    <?php endif; ?>
  </div>
  <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn small secondary" href="/call?id=<?= (int)$call['id'] ?>">Back to the <?= e(Tl('call')) ?></a>
    <a class="btn small secondary" href="/contract-overrides">Contract exceptions</a>
  </div>
</div>
<?php elseif (!empty($gate) && !empty($gate['override'])): ?>
<div class="panel" style="border:1px solid var(--ok)">
  <b style="color:var(--ok)">Exception in force</b>
  <span class="muted">Granted by <?= e($gate['override']['decided_by']) ?> —
    <?= (int)$gate['override']['uses_taken'] ?> of <?= (int)$gate['override']['uses_allowed'] ?> allocation(s) used.
    Allocating here uses one of them.</span>
</div>
<?php endif; ?>

<div class="panel info-panel">
  <h3 class="tab-sub">Client &amp; vendor details (from the call)</h3>
  <div class="panel-split">
    <div><div class="info-role">Client</div><?php contact_block($clientInfo ?? null, 'client'); ?></div>
    <div><div class="info-role">Vendor / Site</div><?php contact_block($vendorInfo ?? null, 'vendor'); ?></div>
  </div>
</div>

<?php if ($curFolder || $curContract || $curQuoteId): ?>
<div class="panel" style="background:var(--soft)">
  <b>From the <?= e(Tl('call')) ?></b>
  <div style="margin-top:6px;display:flex;gap:18px;flex-wrap:wrap">
    <?php if ($curContract): ?><span>Contract <b><?= e($curContract) ?></b></span><?php endif; ?>
    <?php if ($curFolder): ?><span>📁 <a href="<?= e($curFolder) ?>" target="_blank" rel="noopener">Shared folder</a></span><?php endif; ?>
    <?php if (!empty($call['inspection_dates'])): ?><span><?= count(call_dates_parse($call['inspection_dates'])) ?> date(s) requested by the <?= e(Tl('client')) ?></span><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<form method="post" action="<?= $job ? '/job-edit?id=' . (int)$job['id'] : '/job-new?call=' . (int)$call['id'] ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Executing office</label>
      <select class="form-control searchable" id="jexec_sel" name="executing_office_id" data-contracting="<?= (int)($call['ibo_office_id'] ?? 0) ?>">
        <?php foreach ($offices as $o): $sel = $job ? $job['executing_office_id']==$o['id'] : (($call['executing_office_id']??null)? $call['executing_office_id']==$o['id'] : $o['code']==='AHM'); ?>
          <option value="<?= (int)$o['id'] ?>" <?= $sel?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Stage</label>
      <select class="form-control" name="stage"><?php foreach (lk_options_or('job_stage', JOB_STAGES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($job['stage'] ?? 'ALLOCATED')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>How it is worked</label>
      <select class="form-control" name="job_type"><?php foreach (lk_options_or('job_type', JOB_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($job['job_type'] ?? 'INSPECTION')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      <small class="muted">A resident posting runs over a period; set the start and completion dates.</small></div>
    <div class="ff ff-check">
      <label><input type="checkbox" name="is_outstation" value="1"
        <?= !empty($job['is_outstation'] ?? ($call['is_outstation'] ?? 0)) ? 'checked' : '' ?>>
        Outstation — the <?= e(Tl('engineer')) ?> travels to reach this site</label>
      <small class="muted">Carried from the <?= e(Tl('call')) ?>. Travel days either side are costed to this <?= e(Tl('sbu')) ?> and activity code.</small></div>
    <div class="ff"><label>Type of inspection <span class="muted">(from the <?= e(Tl('call')) ?>, narrowed to the <?= e(Tl('client')) ?>'s types)</span></label>
      <select class="form-control searchable" id="insp_sel" name="inspection_type"><option value="">—</option>
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $curInsp===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>SBU <span class="muted">(from call)</span></label>
      <select class="form-control" id="sbu_sel" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $curSbu===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Activity code <span class="muted">(from call)</span></label>
      <select class="form-control" id="activity_sel" name="activity_id"><option value="">— pick SBU first —</option>
        <?php if ($curActRow) echo '<option value="'.(int)$curActRow['id'].'" selected>'.e($curActRow['label']).'</option>'; ?>
      </select></div>

    <div class="ff"><label>Who does it</label>
      <select class="form-control" id="kind_sel" name="staff_kind_pick">
        <option value="ASSET"      <?= $curKind==='ASSET'?'selected':'' ?>>Our own employee</option>
        <option value="NONASSET"   <?= in_array($curKind,['FREELANCER','SUBCON'],true)?'selected':'' ?>>Not our employee</option>
      </select></div>
    <div class="ff" id="nonasset_ff" style="<?= in_array($curKind,['FREELANCER','SUBCON'],true)?'':'display:none' ?>"><label>…which kind</label>
      <select class="form-control" id="nonkind_sel" name="non_asset_kind">
        <option value="FREELANCER" <?= $curKind==='FREELANCER'?'selected':'' ?>>Freelancer</option>
        <option value="SUBCON"     <?= $curKind==='SUBCON'?'selected':'' ?>>Sub-contractor</option>
      </select></div>
    <div class="ff"><label><?= e(T('engineer')) ?></label>
      <select class="form-control searchable" id="insp_pick" name="inspector_id"><option value="">—</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" data-kind="<?= e($i['staff_kind'] ?? 'ASSET') ?>" <?= ($job && $job['inspector_id']==$i['id'])?'selected':'' ?>><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?>
      </select>
      <small class="muted" id="insp_hint"></small></div>
    <div class="ff" id="subcon_ff" style="<?= $curKind==='SUBCON'?'':'display:none' ?>"><label>Sub-contracting agency</label>
      <select class="form-control searchable" name="subcon_id"><option value="">—</option>
        <?php foreach ($subcons as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ($job && $job['subcon_id']==$s['id'])?'selected':'' ?>><?= e($s['agency']) ?><?= $s['inspector_name']?' — '.e($s['inspector_name']):'' ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Sub-con cost (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="subcon_cost" value="<?= e($job['subcon_cost'] ?? '') ?>"></div>
    <?php // The contract number is agreed once, on the quotation, and carried
          // from there — quotation → inspection call → deputation. It is shown
          // here rather than chosen, because choosing it again from a register
          // is how a month's profit ends up booked against somebody else's
          // contract. The register entry is created on saving if it is not
          // already there, so the profitability screens have it without anybody
          // maintaining a second list by hand. ?>
    <div class="ff"><label><?= e(T('boss')) ?> <span class="muted">— carried from the <?= e(Tl('quote')) ?> / <?= e(Tl('call')) ?></span></label>
      <?php if ($curContract !== ''): ?>
        <input class="form-control" value="<?= e($curContract) ?>" readonly style="background:var(--soft);font-weight:700">
        <small class="muted">Profit on this <?= e(Tl('job')) ?> is booked against this number. It is filed under
          <a href="/m/boss"><?= e(Tlp('boss')) ?></a> automatically when you save.</small>
      <?php else: ?>
        <input class="form-control" value="" readonly placeholder="— none yet —" style="background:var(--soft)">
        <small class="muted"><span class="pill p-warn">not agreed yet</span>
          No <?= e(Tl('boss')) ?> has come through on the <?= e(Tl('quote')) ?> or the <?= e(Tl('call')) ?>.
          The <?= e(Tl('job')) ?> can still be allocated — record the number on the <?= e(Tl('quote')) ?> when the
          client confirms it, and everything raised against it lines up from then on.</small>
      <?php endif; ?>
      <?php // Kept so an existing deputation does not lose the register row it
            // was already pointing at when the number is still blank. ?>
      <input type="hidden" name="boss_id" value="<?= e((string)($job['boss_id'] ?? '')) ?>"></div>

    <?php // §viii — one heading, and it is the quotation. The contract number is
          // a property of the order, not a second thing to choose here: it is
          // shown beneath, read-only, exactly as it stands on the call. ?>
    <div class="ff"><label><?= e(T('quote')) ?></label>
      <select class="form-control searchable" name="quotation_id"><option value="">— none —</option>
        <?php foreach (($quotes ?? []) as $qq): ?><option value="<?= (int)$qq['id'] ?>" <?= ((string)$curQuoteId===(string)$qq['id'])?'selected':'' ?>><?= e($qq['quote_no']) ?><?= (int)$qq['rev']>0?' R'.$qq['rev']:'' ?> · <?= e(cur_sym()) ?><?= number_format((float)$qq['total_amount'],0) ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Carried from the <?= e(Tl('call')) ?>. Links this <?= e(Tl('job')) ?> to the order — advance / payment-hold rules and deliverables carry over, and revenue is tracked against it.
        <?php if ($curContract !== ''): ?><br>Contract <b><?= e($curContract) ?></b>.
        <?php else: ?><br><span class="pill p-warn">contract number pending</span><?php endif; ?></small></div>

    <?php // §when — the three dates mean three different things and were being
          // typed as though they were interchangeable:
          //   received  — the day the contracting branch got the call
          //   required  — the day the client asked for
          //   scheduled — the day we are actually going
          // The first two are settled on the call and are shown here, not
          // re-typed. Only the actual date is chosen here, and everything else
          // — the end date, the visit count, the working-day arithmetic —
          // follows from it. ?>
    <div class="ff"><label><?= e(Tl('call')) ?> received <span class="muted">— from the <?= e(Tl('call')) ?></span></label>
      <input class="form-control" type="date" value="<?= e($call['call_received_date'] ?? '') ?>" readonly style="background:var(--soft)"></div>
    <div class="ff"><label><?= e(Tl('client')) ?>'s required date <span class="muted">— from the <?= e(Tl('call')) ?></span></label>
      <input class="form-control" type="date" value="<?= e($call['inspection_required_date'] ?? '') ?>" readonly style="background:var(--soft)"></div>
    <div class="ff"><label>Actual scheduled date <span class="muted">— when we are going</span></label>
      <input class="form-control" type="date" id="req_date" name="scheduled_date"
             value="<?= e(($job['scheduled_date'] ?? '') ?: ($call['inspection_required_date'] ?? '')) ?>">
      <small class="muted">Move this and the end date moves with it.</small></div>

    <?php // The shape is settled on the call; it is shown here and can be
          // corrected, and the boxes that shape needs appear with it. ?>
    <div class="ff"><label>Shape of the engagement</label>
      <select class="form-control" id="eng_sel" name="engagement_type">
        <?php $curEng = ($job['engagement_type'] ?? '') ?: (($call['engagement_type'] ?? '') ?: 'SINGLE');
              foreach (lk_options_or('engagement_type', ENGAGEMENT_TYPES) as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curEng === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted" id="eng_hint"></small></div>

    <div class="ff eng-box" data-for="CONTINUOUS"><label>How many days, continuously?</label>
      <input class="form-control" type="number" min="1" max="365" id="days_count" name="days_count"
             value="<?= e((($job['days_count'] ?? '') ?: ($call['days_count'] ?? '')) ?: '') ?>">
      <small class="muted">Working days — Sundays and this <?= e(Tl('office')) ?>'s public holidays are stepped over.</small></div>

    <div class="ff eng-box" data-for="MONTHLY"><label>How many months on site?</label>
      <input class="form-control" type="number" min="1" max="36" id="months_count" name="months_count"
             value="<?= e((($job['months_count'] ?? '') ?: ($call['months_count'] ?? '')) ?: '') ?>">
      <small class="muted">1st to the last day of the month, whatever day it starts.</small></div>
    <div class="ff eng-box" data-for="MONTHLY"><label>Man-month basis <span class="muted">— blank follows the <?= e(Tl('client')) ?></span></label>
      <select class="form-control" name="manmonth_basis">
        <option value="">— as agreed with the <?= e(Tl('client')) ?> —</option>
        <?php $curMm = (string)(($job['manmonth_basis'] ?? '') ?: ($call['manmonth_basis'] ?? ''));
              foreach (MANMONTH_BASES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curMm === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff eng-box" data-for="MONTHLY"><label>Minimum working days</label>
      <input class="form-control" type="number" min="1" max="31" name="manmonth_min_days"
             value="<?= e(((($job['manmonth_min_days'] ?? 0) ?: ($call['manmonth_min_days'] ?? 0))) ?: '') ?>" placeholder="e.g. 26"></div>

    <div class="ff eng-box" data-for="PATTERN"><label>How does it repeat?</label>
      <select class="form-control" id="pattern_kind" name="pattern_kind">
        <?php $curPk = (($job['pattern_kind'] ?? '') ?: ($call['pattern_kind'] ?? '')) ?: 'WEEKDAYS';
              foreach (lk_options_or('pattern_kind', PATTERN_KINDS) as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curPk === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff eng-box" data-for="PATTERN" id="pat_n_box"><label id="pat_n_label">How many</label>
      <input class="form-control" type="number" min="1" max="30" id="pattern_n" name="pattern_n"
             value="<?= e((($job['pattern_n'] ?? '') ?: ($call['pattern_n'] ?? '')) ?: '') ?>"></div>
    <div class="ff ff-wide eng-box" data-for="PATTERN" id="pat_wd_box"><label>On these days</label>
      <div class="chip-row pickbox" style="max-height:none">
        <?php $jwd = array_filter(array_map('intval', explode(',', (string)(($job['schedule_weekdays'] ?? '') ?: ($call['schedule_weekdays'] ?? '')))));
              foreach (WEEKDAY_NAMES as $n => $nm): ?>
          <label class="ff-check"><input type="checkbox" name="schedule_weekdays[]" value="<?= $n ?>" <?= in_array($n, $jwd, true) ? 'checked' : '' ?>> <?= e($nm) ?></label>
        <?php endforeach; ?>
      </div></div>
    <div class="ff eng-box" data-for="PATTERN"><label>Repeat until</label>
      <input class="form-control" type="date" id="schedule_end_date" name="schedule_end_date"
             value="<?= e(($job['schedule_end_date'] ?? '') ?: ($call['schedule_end_date'] ?? '')) ?>"></div>

    <?php // §price — the man-days ARE the quantity. Entering 6 here and watching
          // the money stay at one day's rate is the commonest way a deputation
          // gets invoiced short: the value was carried from the call once and
          // then never followed the days. It follows them now. ?>
    <div class="ff"><label>Man-days <span class="muted">— 0 counts them from the dates</span></label>
      <input class="form-control" type="number" step="0.5" id="j_mandays" name="mandays" value="<?= e($job['mandays'] ?? '0') ?>">
      <small class="muted" id="md_note"></small></div>

    <?php // The client named these days; they are not ours to invent. Two lines
          // to begin with and one more on request, rather than twenty empty boxes. ?>
    <div class="ff ff-wide eng-box" data-for="MULTIPLE">
      <label>The dates the <?= e(Tl('client')) ?> asked for</label>
      <div id="datebox" style="margin-top:4px;display:flex;gap:8px;flex-wrap:wrap">
        <?php $slots = max(2, count($curDates));
              for ($i = 0; $i < $slots; $i++): ?>
          <div class="ff dateline" style="margin:0;max-width:200px">
            <input class="form-control" type="date" name="inspection_dates[]" value="<?= e($curDates[$i] ?? '') ?>"></div>
        <?php endfor; ?>
      </div>
      <div style="margin-top:6px"><button type="button" class="btn small secondary" id="adddate">+ Add another date</button></div>
    </div>

    <?php // Whether the engineer can actually be there. Every date, checked
          // against what they are already booked on and against days marked off
          // — and where there is a clash, who else in the branch is free. ?>
    <div class="ff ff-wide">
      <div class="msg" id="sched_out" style="display:none;margin:0"></div>
    </div>
    <?php // A run of named dates may need more than one engineer. Drawn only
          // when the shape has several visits, so a single-day allocation is not
          // handed a table with one row in it. ?>
    <div class="ff ff-wide" id="visit_rows" style="display:none;margin-top:4px"></div>
    <input type="hidden" name="_job_id" value="<?= (int)($job['id'] ?? 0) ?>">
    <input type="hidden" name="inspection_start_date" id="insp_start"
           value="<?= e($job['inspection_start_date'] ?? '') ?>">
    <input type="hidden" name="inspection_end_date" id="insp_end"
           value="<?= e($job['inspection_end_date'] ?? '') ?>">

    <?php // Two different numbers, and confusing them is how a branch's profit
          // stops meaning anything. The invoice value is what the client is
          // charged. The credit is what one branch passes another for doing the
          // work, and it exists only when those are two different branches.
          // Revenue is the invoice less any credit given away — so added across
          // the branches it comes back to the invoice value exactly. ?>
    <?php $unitRate = (float)($call['billable_rate'] ?? 0);
          $unitBasis = (string)($call['billable_basis'] ?? '');
          $unitLabel = $unitBasis ? (lk_options_or('charge_unit', CHARGE_UNITS)[$unitBasis] ?? $unitBasis) : 'unit'; ?>
    <div class="ff"><label>Unit rate <span class="muted">— as quoted, from the <?= e(Tl('call')) ?></span></label>
      <input class="form-control" type="number" step="0.01" id="j_rate" name="billable_rate_display"
             value="<?= e($unitRate ?: '') ?>" readonly style="background:var(--soft)"
             data-basis="<?= e($unitLabel) ?>">
      <small class="muted"><?= $unitRate > 0
        ? 'Per ' . e(strtolower($unitLabel)) . ', taken from the order line on the ' . e(Tl('call')) . '.'
        : 'No rate came through on the ' . e(Tl('call')) . ' — type the invoice value below by hand.' ?></small></div>
    <div class="ff"><label>Invoice value to the <?= e(Tl('client')) ?> (<?= e(cur_sym()) ?>, ex-GST)</label>
      <input class="form-control" type="number" step="0.01" id="j_invoice" name="invoice_value"
             value="<?= e($job['invoice_value'] ?? $call['billable_value'] ?? '') ?>">
      <input type="hidden" id="j_invoice_auto" name="invoice_value_auto" value="<?= ($job && (float)($job['invoice_value'] ?? 0) > 0) ? '0' : '1' ?>">
      <small class="muted" id="inv_note">Rate × man-days. Type over it if this one is billed differently.</small></div>
    <?php // §revenue — the one figure a branch is actually judged on, behind its
          // own permission. A coordinator needs the credit to do the job and has
          // no business seeing what the branch earns on it. ?>
    <?php if (can_see_revenue()): ?>
      <div class="ff ff-wide"><div class="msg" id="j_rev" style="display:none;margin:0"></div>
        <input type="hidden" id="j_is_cross" value="<?= $cross ? '1' : '0' ?>"></div>
    <?php else: ?>
      <div class="ff ff-wide"><p class="muted" style="margin:0">The revenue on this <?= e(Tl('job')) ?> is not shown to your role.</p></div>
    <?php endif; ?>
    <?php // Marking the credit required on a same-office deputation meant the
          // browser refused to submit a form over a box that should not have
          // been there at all, and the button simply did nothing. ?>
    <?php // §credit — priced per man-day, exactly as the client charge is, and
          // off the same man-days. Only the total used to be entered, so a
          // six-day deputation could carry one day's credit and the branch on
          // the other end was short without anybody seeing why. ?>
    <div class="ff eng-cross"><label>Credit per man-day to the executing <?= e(Tl('office')) ?> (<?= e(cur_sym()) ?>)</label>
      <input class="form-control" type="number" step="0.01" id="j_credit_rate" name="credit_rate"
             value="<?= e($cross ? ((($job['credit_rate'] ?? 0) ?: ($call['credit_rate'] ?? 0)) ?: '') : '') ?>"
             <?= $cross ? '' : 'readonly placeholder="— not applicable —" style="background:var(--soft)"' ?>>
      <small class="muted"><?= $cross
        ? 'What the executing ' . e(Tl('office')) . ' is paid for each day.'
        : 'One ' . e(Tl('office')) . ' both holds the order and does the work, so nothing passes between offices.' ?></small></div>
    <div class="ff eng-cross"><label>Total credit (<?= e(cur_sym()) ?>)<?= $cross ? ' *' : '' ?> <span class="muted">— rate × man-days</span></label>
      <input class="form-control" type="number" step="0.01" id="j_credit" name="expected_credit"
             value="<?= e($cross ? ($job['expected_credit'] ?? $call['expected_credit'] ?? '') : '') ?>"
             <?= $cross ? 'required' : 'readonly placeholder="— not applicable —" style="background:var(--soft)"' ?>>
      <input type="hidden" id="j_credit_auto" name="expected_credit_auto"
             value="<?= ($job && (float)($job['expected_credit'] ?? 0) > 0 && (float)($job['credit_rate'] ?? 0) <= 0) ? '0' : '1' ?>">
      <small class="muted" id="j_credit_note"><?= $cross
        ? 'The holding ' . e(Tl('office')) . ' books the invoice less this figure; the executing one books this figure.'
        : '' ?></small></div>
    <div class="ff"><label>Credit type</label>
      <select class="form-control searchable" name="credit_type"><?php foreach (lk_options_or('credit_type', CREDIT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($job['credit_type'] ?? $call['credit_type'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <?php // §iv — when the contracting office and the executing office are not the
          // same, the contracting office GIVES the credit. That is not a choice
          // to be made afresh on every allocation, so it is selected already and
          // follows the executing office if that is changed here. ?>
    <div class="ff"><label>Credit direction</label>
      <select class="form-control searchable" id="dir_sel" name="credit_direction" data-new="<?= $job ? '0' : '1' ?>">
        <?php // A same-office job has no inter-office credit at all, and needs to
              // be able to say so rather than being forced to pick a direction. ?>
        <option value="" <?= $curDir===''?'selected':'' ?>>— none (one office does both) —</option>
        <?php foreach (lk_options_or('credit_direction', CREDIT_DIRECTIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($curDir===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <small class="muted" id="dir_note"><?= e($ex['text']) ?></small></div>

    <div class="ff"><label>Reporting frequency</label>
      <select class="form-control" id="freq_sel" name="reporting_frequency"><?php foreach (lk_options_or('reporting_frequency', REPORT_FREQ) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $curFreq===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff" id="custom_days_wrap" style="<?= $curFreq==='CUSTOM'?'':'display:none' ?>"><label>…every how many days?</label>
      <input class="form-control" type="number" min="1" name="report_custom_days" value="<?= e($curDays) ?>" placeholder="e.g. 3"></div>

    <div class="ff ff-wide"><label>Shared folder / drive link <span class="muted">— carried from the <?= e(Tl('call')) ?></span></label>
      <input class="form-control" type="url" name="folder_link" value="<?= e($curFolder) ?>" placeholder="https://…">
      <input type="hidden" name="contract_number" value="<?= e($curContract) ?>"></div>

    <div class="ff ff-wide"><label>Deliverables / reports required after completion</label>
      <div class="checkgrid">
        <?php foreach (deliverable_options() as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="deliverables[]" value="<?= e($k) ?>" <?= in_array($k, $curDeliv, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Each format ticked here is handed to the <?= e(Tl('engineer')) ?> as a report to produce — they appear on the <?= e(Tl('report')) ?> screen ready to fill, using the <?= e(Tl('client')) ?>'s own format where one is on file. The list is the <a href="/report-types"><?= e(Tl('report')) ?> types</a> register.</small></div>

    <?php render_custom_fields('job', $cfvals ?? []); ?>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $job ? 'Save job' : 'Allocate & send email' ?></button>
    <a class="btn secondary" href="/call?id=<?= (int)$call['id'] ?>">Cancel</a>
  </div>
</form>
<script>
window.ACTIVITY = <?= json_encode($act) ?>;
(function(){
  // §b.vi — up to 20 visit dates.
  var box=document.getElementById('jdates'), add=document.getElementById('adddate');
  if (box && add) add.addEventListener('click', function(){
    if (box.querySelectorAll('input[type=date]').length >= 20) { add.disabled = true; return; }
    var d=document.createElement('div'); d.className='ff'; d.style.margin='0';
    d.innerHTML='<input class="form-control" type="date" name="inspection_dates[]">';
    box.appendChild(d);
  });
  // §b.vii — our own employee, or a freelancer / sub-contractor.
  var kind=document.getElementById('kind_sel'), non=document.getElementById('nonkind_sel'),
      nonFF=document.getElementById('nonasset_ff'), subFF=document.getElementById('subcon_ff'),
      pick=document.getElementById('insp_pick'), hint=document.getElementById('insp_hint');
  function wanted(){ return kind.value === 'ASSET' ? 'ASSET' : non.value; }
  function syncKind(){
    nonFF.style.display = kind.value === 'ASSET' ? 'none' : '';
    subFF.style.display = (kind.value !== 'ASSET' && non.value === 'SUBCON') ? '' : 'none';
    var want = wanted(), shown = 0;
    Array.prototype.forEach.call(pick.options, function(o){
      if (!o.value) return;
      var ok = (o.dataset.kind || 'ASSET') === want;
      o.hidden = !ok; o.disabled = !ok;
      if (ok) shown++;
    });
    if (pick.selectedIndex > 0 && pick.options[pick.selectedIndex].disabled) pick.value = '';
    hint.textContent = shown + ' ' + (want === 'ASSET' ? 'own employee' : (want === 'SUBCON' ? 'sub-contract' : 'freelance')) + ' engineer(s) on file.';
  }
  if (kind && non && pick) { kind.addEventListener('change', syncKind); non.addEventListener('change', syncKind); syncKind(); }

  // §iv — change the executing office and the credit direction follows it. A
  // cross-office job means the contracting office gives; a same-office job has
  // no inter-office credit at all.
  var jexec = document.getElementById('jexec_sel'), dir = document.getElementById('dir_sel'),
      dirNote = document.getElementById('dir_note');
  if (jexec && dir) {
    var contracting = jexec.dataset.contracting || '';
    function has(v){ return Array.prototype.some.call(dir.options, function(o){ return o.value === v; }); }
    function syncDir(){
      var crossNow = jexec.value !== '' && contracting !== '' && jexec.value !== contracting;
      if (crossNow) { if (has('GIVEN')) dir.value = 'GIVEN'; }
      else if (dir.value === 'GIVEN' && has('')) dir.value = '';
      if (dirNote) dirNote.textContent = crossNow
        ? 'The contracting office holds this order and another office does the work, so the contracting office gives the credit. Selected for you.'
        : 'One office both holds the order and does the work — there is no inter-office credit.';
    }
    jexec.addEventListener('change', syncDir);
    // On a fresh allocation set it from the offices. On a saved job leave what
    // was recorded alone — but still follow along if the office is changed here.
    if (dir.dataset.new === '1' || dir.value === '') syncDir();
  }
})();
</script>
