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
    <div class="ff"><label>BOSS number</label>
      <select class="form-control searchable" name="boss_id"><option value="">—</option>
        <?php foreach ($boss as $bn): ?><option value="<?= (int)$bn['id'] ?>" <?= ($job && $job['boss_id']==$bn['id'])?'selected':'' ?>><?= e($bn['boss_number']) ?> (<?= e($bn['status']) ?>)</option><?php endforeach; ?>
      </select>
      <?php if (!$boss): ?><small class="muted">No BOSS numbers for this client yet — add under <a href="/m/boss/new">BOSS numbers</a>.</small><?php endif; ?></div>

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

    <div class="ff"><label>Scheduled date</label><input class="form-control" type="date" name="scheduled_date" value="<?= e($job['scheduled_date'] ?? '') ?>"></div>
    <div class="ff"><label>Inspection start</label><input class="form-control" type="date" name="inspection_start_date" value="<?= e($job['inspection_start_date'] ?? '') ?>"></div>
    <div class="ff"><label>Inspection end</label><input class="form-control" type="date" name="inspection_end_date" value="<?= e($job['inspection_end_date'] ?? '') ?>"></div>
    <div class="ff"><label>Man-days (0 = auto from dates)</label><input class="form-control" type="number" step="0.5" name="mandays" value="<?= e($job['mandays'] ?? '0') ?>"></div>

    <div class="ff ff-wide">
      <label>Inspection dates <span class="muted">— every day the <?= e(Tl('engineer')) ?> attends; up to 20</span></label>
      <div class="form-grid" id="jdates" style="margin-top:4px">
        <?php for ($i = 0; $i < $dateSlots; $i++): ?>
          <div class="ff" style="margin:0"><input class="form-control" type="date" name="inspection_dates[]" value="<?= e($curDates[$i] ?? '') ?>"></div>
        <?php endfor; ?>
      </div>
      <div style="margin-top:6px"><button type="button" class="btn small secondary" id="adddate">+ Add another date</button>
        <span class="muted" style="margin-left:8px"><?= count($curDates) ?> set. Carried from the <?= e(Tl('call')) ?>; edit freely.</span></div>
    </div>

    <div class="ff"><label>Expected credit (<?= e(cur_sym()) ?>) *</label><input class="form-control" type="number" step="0.01" name="expected_credit" value="<?= e($job['expected_credit'] ?? $call['expected_credit'] ?? '') ?>" required></div>
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
