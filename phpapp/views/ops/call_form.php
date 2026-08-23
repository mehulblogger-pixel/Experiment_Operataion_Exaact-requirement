<?php
  $act = activity_options_by_sbu();
  // Dates already on the call, padded so the coordinator always has a spare box.
  $curDates = call_dates_parse($call['inspection_dates'] ?? '');
  if (!$curDates && !empty($call['inspection_required_date'])) $curDates = [$call['inspection_required_date']];
  $curWd = array_filter(array_map('intval', explode(',', (string)($call['schedule_weekdays'] ?? ''))));
  $ex = credit_explainer($call['ibo_office_id'] ?? (current_user()['home_office_id'] ?? null), $call['executing_office_id'] ?? null);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/calls"><?= e(T_REG('call')) ?></a> › <?= $isEdit ? e($call['call_code']) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $isEdit ? 'Edit ' . e(Tl('call')) . ' ' . e($call['call_code']) : ucfirst(T_NEW('call')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Pick the <?= e(Tl('client')) ?> and the <?= e(Tl('quote')) ?> it is against — the commercial terms come across by themselves. Not in a list? Use <strong>+ Add new</strong> beside any dropdown.</p></div>
  <a class="btn secondary" href="/calls">← Back</a>
</div>
<?php if (!empty($error)): ?><div class="msg msg-error" id="call_err"><?= e($error) ?></div><?php endif; ?>
<?php // A message at the top of a form this long, with nothing marked on the form
      // itself, reads as "Save is not working". Ring the boxes that stopped it and
      // put the person in front of the first one. ?>
<?php if (!empty($errorFields)): ?>
<script>
// Deferred: this block sits at the TOP of the form, so the fields it has to
// mark do not exist yet when it runs.
document.addEventListener('DOMContentLoaded', function () {
  var names = <?= json_encode(array_values((array)$errorFields)) ?>;
  var first = null;
  names.forEach(function (n) {
    var el = document.querySelector('[name="' + n + '"]');
    if (!el) return;
    // the searchable dropdowns hide the real select, so mark what is on screen
    var box = el.parentNode && el.parentNode.querySelector('input.form-control');
    var mark = box || el;
    mark.classList.add('field-bad');
    var ff = mark.closest('.ff');
    if (ff) ff.classList.add('ff-bad');
    if (!first) first = mark;
    // A field inside a section the form keeps collapsed cannot be filled in
    // while it is hidden — open whatever it lives in.
    for (var n = mark; n && n !== document.body; n = n.parentNode)
      if (n.style && n.style.display === 'none') n.style.display = '';
    var clear = function () { mark.classList.remove('field-bad'); if (ff) ff.classList.remove('ff-bad'); };
    mark.addEventListener('input', clear);
    mark.addEventListener('change', clear);
    el.addEventListener('change', clear);
  });
  // If the first bad box sits on a tab that is not the open one, switch to it —
  // otherwise the person is looking at a page with no visible error.
  if (first) {
    var pane = first.closest('[data-tab]');
    if (pane) {
      var label = pane.getAttribute('data-tab');
      var bar = pane.closest('[data-tabs]');
      bar = bar ? bar.previousElementSibling : null;   // the tab bar the engine inserts
      if (bar && bar.classList.contains('tabbar')) {
        Array.prototype.forEach.call(bar.querySelectorAll('.tabbtn'), function (b) {
          var t = (b.textContent || '').replace(/\s+/g, ' ').trim();
          if (t === (label || '').replace(/\s+/g, ' ').trim()) b.click();
        });
      }
    }
  }
  var msg = document.getElementById('call_err');
  if (msg) msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
  if (first) setTimeout(function () { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }, 350);
});
</script>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? '/call-edit?id=' . (int)$call['id'] : '/call-new' ?>" class="panel">

<div data-tabs data-tabs-key="callform" class="form-tabs">
<section class="fs-pane" data-tab="<?= e(T('client')) ?> &amp; <?= e(Tl('quote')) ?>">
  <h3 class="tab-sub" style="margin-top:0">1. <?= e(T('client')) ?> &amp; <?= e(Tl('quote')) ?></h3>
  <?php // §b — a party added in a hurry has a name and nothing else. Say so here,
        // while the client is still on the phone, rather than three weeks later
        // when the engineer is at the gate with nobody to ring. ?>
  <div id="party_gaps" style="display:none;margin:0 0 12px"></div>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('client')) ?> <a href="#" class="addlink" data-qa="client">+ Add new</a></label>
      <select class="form-control searchable" id="client_sel" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($call && $call['client_id']==$c['id'])?'selected':'' ?>><?= e(pname($c)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(T('quote')) ?> <span class="muted">— what was sold</span></label>
      <select class="form-control searchable" id="quote_sel" name="quotation_id" data-cur="<?= (int)($call['quotation_id'] ?? 0) ?>">
        <option value="">— pick a <?= e(Tl('client')) ?> first —</option>
      </select>
      <small class="muted">Accepted, approved or sent <?= e(Tlp('quote')) ?> for this <?= e(Tl('client')) ?>.</small></div>
    <?php // Read-only ONLY while a quotation is driving it. On a direct call there
          // is no cascade to fill it, and a call drawing down against a live ARC
          // still has a contract number the client will quote back at you — a
          // locked, permanently blank box made that impossible to record. ?>
    <?php // One quotation → one contract → many calls. A call INHERITS its contract
          //  number and never creates a new one, so once it is carried from the
          //  contract the box is locked. It stays typeable only for a genuinely
          //  direct call / ARC draw-down that has no contract yet. ?>
    <?php $cnInherited = trim((string)($call['contract_number'] ?? '')) !== ''; ?>
    <div class="ff"><label>Contract number <span class="muted" id="cn_hint"><?= $cnInherited ? '— inherited from the contract' : '— from the ' . e(Tl('quote')) ?></span></label>
      <input class="form-control" id="contract_no" name="contract_number" value="<?= e($call['contract_number'] ?? '') ?>"
             <?= $cnInherited ? 'readonly style="background:var(--soft)"' : '' ?>
             placeholder="pick a <?= e(Tl('quote')) ?>, or type it for a direct <?= e(Tl('call')) ?>">
      <small class="muted" id="cn_note" style="display:none">Typed by hand — no <?= e(Tl('quote')) ?> is driving this <?= e(Tl('call')) ?>.</small>
      <?php if ($cnInherited): ?><small class="muted">A <?= e(Tl('call')) ?> inherits its contract number — it never creates a new one.</small><?php endif; ?></div>

    <div class="ff ff-wide"><label>Line item on the <?= e(Tl('quote')) ?> <span class="muted">— which part of the order this <?= e(Tl('call')) ?> draws on</span></label>
      <select class="form-control searchable" id="qline_sel" name="quote_line_id" data-cur="<?= (int)($call['quote_line_id'] ?? 0) ?>">
        <option value="">— whole <?= e(Tl('quote')) ?> —</option>
      </select></div>

    <?php // The site is very often the client's own premises — a manufacturer who
          // buys inspection of their own works. That partner is a client AND the
          // site, so it is offered here in one tick rather than making somebody
          // go and flag the client as a vendor first. ?>
    <div class="ff"><label><?= e(T('vendor')) ?> / <?= e(Tl('manufacturer')) ?> (site) <a href="#" class="addlink" data-qa="vendor">+ Add new</a></label>
      <select class="form-control searchable" id="vendor_sel" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= ($call && $call['vendor_id']==$v['id'])?'selected':'' ?>><?= e(pname($v)) ?></option><?php endforeach; ?>
      </select>
      <label class="chk" style="margin-top:5px"><input type="checkbox" id="site_is_client">
        Inspection is at the <?= e(Tl('client')) ?>'s own premises</label>
      <small class="muted" id="sic_note" style="display:none">The <?= e(Tl('client')) ?> is recorded as the site too.</small></div>
    <div class="ff ff-wide"><label>Shared folder / drive link <span class="muted">— the client's papers and our working files</span></label>
      <input class="form-control" type="url" name="folder_link" value="<?= e($call['folder_link'] ?? '') ?>" placeholder="https://…  (SharePoint, Google Drive, OneDrive)">
      <small class="muted">Travels with the <?= e(Tl('job')) ?>, so the <?= e(Tl('engineer')) ?> gets it too.</small></div>
  </div>

</section>
<section class="fs-pane" data-tab="What's inspected">
  <h3 class="tab-sub" style="margin-top:0">2. What is being inspected <span class="muted">— filled from the <?= e(Tl('quote')) ?>, change if this <?= e(Tl('call')) ?> differs</span></h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('sbu')) ?></label>
      <select class="form-control" id="sbu_sel" name="sbu"><option value="">—</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Activity code <a href="#" class="addlink" data-qa="activity">+ Add new</a></label>
      <select class="form-control" id="activity_sel" name="activity_id"><option value="">— pick <?= e(T('sbu')) ?> first —</option>
        <?php if ($call && ($call['activity_id']??null)) { $curAct = lk_value($call['activity_id']); if ($curAct) echo '<option value="'.(int)$curAct['id'].'" selected>'.e($curAct['label']).'</option>'; } ?>
      </select></div>
    <?php // §svc — the TPIA service line. It leads the form because it decides
          //  the report format the job will be given: pick "Vendor Audit" and the
          //  job is allocated the Vendor Audit Report format, and so on. Only the
          //  services switched on for this install appear.
          $svcFmt = [];
          foreach (svc_catalog() as $__s) {
              if (function_exists('svc_globally_active') && !svc_globally_active($__s['code'])) continue;
              $__pc = function_exists('svc_report_primary') ? svc_report_primary($__s['code']) : '';
              if ($__pc !== '') { $__nm = ops_val("SELECT name FROM report_types WHERE code=?", [$__pc]); $svcFmt[$__s['code']] = ['code' => $__pc, 'name' => $__nm ?: $__pc]; }
          } ?>
    <div class="ff"><label>Service line <span class="muted">(sets the report format)</span></label>
      <select class="form-control" id="service_sel" name="service_code"><option value="">—</option>
        <?php foreach (svc_catalog() as $__s): if (function_exists('svc_globally_active') && !svc_globally_active($__s['code'])) continue; ?>
          <option value="<?= e($__s['code']) ?>" <?= ($call && ($call['service_code']??'')===$__s['code'])?'selected':'' ?>><?= e($__s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="muted" id="svc_fmt_hint" style="margin:6px 0 0;font-size:12px"></p>
      <script>
        window.SVC_FMT = <?= json_encode($svcFmt) ?>;
        (function(){ var sel=document.getElementById('service_sel'), h=document.getElementById('svc_fmt_hint');
          function upd(){ var f=window.SVC_FMT[sel.value];
            h.innerHTML = f ? ('→ Report format: <strong>'+f.name+'</strong> ('+f.code+') — allocated automatically. You can still tick different reports below.') : ''; }
          if(sel){ sel.addEventListener('change',upd); upd(); }
        })();
      </script>
    </div>
    <div class="ff"><label>Type of inspection <span class="muted">(narrows to the <?= e(Tl('client')) ?>'s types)</span></label>
      <select class="form-control searchable" id="insp_sel" name="inspection_type"><option value="">—</option>
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['inspection_type']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        <option value="OTHER" <?= ($call && ($call['inspection_type']??'')==='OTHER')?'selected':'' ?>>Other (type below)…</option>
      </select>
      <input class="form-control" id="insp_other" name="inspection_type_other" value="<?= e($call['inspection_type_other'] ?? '') ?>" placeholder="Other inspection type" style="margin-top:6px;<?= ($call && ($call['inspection_type']??'')==='OTHER')?'':'display:none' ?>"></div>
    <div class="ff"><label>Product category <a href="#" class="addlink" data-qa="product">+ Add new</a></label>
      <select class="form-control searchable" id="product_sel" name="product_category"><option value="">—</option>
        <?php foreach (lk_options_or('product', PRODUCT_CATS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['product_category']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Product (if "Others")</label><input class="form-control" name="product_other" value="<?= e($call['product_other'] ?? '') ?>"></div>
    <div class="ff" id="site_ff" style="<?= ($call && ($call['inspection_type']??'')==='DEPUTATION')?'':'display:none' ?>"><label>Site (<?= e(Tl('client')) ?>'s site)</label>
      <select class="form-control searchable" id="site_sel" name="site_address_id"><option value="">—</option>
        <?php if ($call && ($call['site_address_id']??null)) { $sa=ops_one("SELECT id,label,city FROM partner_addresses WHERE id=?", [$call['site_address_id']]); if ($sa) echo '<option value="'.(int)$sa['id'].'" selected>'.e(($sa['label']?:'Site').' '.$sa['city']).'</option>'; } ?>
      </select></div>
  </div>

  <?php // §when — five shapes of engagement, and only the boxes that shape needs.
        // Showing all of them at once meant the coordinator had to know which to
        // ignore, and the register could not say what had actually been asked
        // for. The end date, the visit count and the working-day arithmetic are
        // worked out on the server — Sundays and the branch's own public
        // holidays are not working days, and nobody should be counting those by
        // hand against a wall calendar. ?>
</section>
<section class="fs-pane" data-tab="When">
  <h3 class="tab-sub" style="margin-top:0">3. When</h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(TH('call')) ?> received <span class="muted">— the day the contracting <?= e(Tl('office')) ?> got it</span></label>
      <input class="form-control" type="date" name="call_received_date" value="<?= e($call['call_received_date'] ?? date('Y-m-d')) ?>"></div>
    <div class="ff"><label><?= e(TH('client')) ?>'s required date <span class="muted">— when they want it</span></label>
      <input class="form-control" type="date" id="req_date" name="inspection_required_date" value="<?= e($call['inspection_required_date'] ?? '') ?>"></div>
    <div class="ff"><label>Shape of the engagement</label>
      <select class="form-control" id="eng_sel" name="engagement_type">
        <?php $curEng = ($call['engagement_type'] ?? '') ?: 'SINGLE';
              foreach (lk_options_or('engagement_type', engagement_types()) as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curEng === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted" id="eng_hint"></small></div>

    <?php // CONTINUOUS — the engineer is there day after day. Type how many. ?>
    <div class="ff eng-box" data-for="CONTINUOUS"><label>How many days, continuously?</label>
      <input class="form-control" type="number" min="1" max="365" id="days_count" name="days_count"
             value="<?= e(($call['days_count'] ?? '') ?: '') ?>" placeholder="e.g. 5, 10, 15">
      <small class="muted">Sundays and this <?= e(Tl('office')) ?>'s public holidays are stepped over, so the end date is a real one.</small></div>

    <?php // MONTHLY — a posting at the works, on a man-month basis. ?>
    <div class="ff eng-box" data-for="MONTHLY"><label>How many months on site?</label>
      <input class="form-control" type="number" min="1" max="36" id="months_count" name="months_count"
             value="<?= e(($call['months_count'] ?? '') ?: '') ?>" placeholder="e.g. 1, 3, 6">
      <small class="muted">The posting runs the 1st to the last day of the month, whatever day it starts.</small></div>
    <?php // What a man-month means here. Blank follows the client's record, and
          // that follows the company default in Settings. Only worth setting when
          // this one order differs from what the client normally agrees. ?>
    <div class="ff eng-box" data-for="MONTHLY"><label>Man-month basis <span class="muted">— blank follows the <?= e(Tl('client')) ?></span></label>
      <select class="form-control" name="manmonth_basis">
        <option value="">— as agreed with the <?= e(Tl('client')) ?> —</option>
        <?php $curMm = (string)($call['manmonth_basis'] ?? '');
              foreach (MANMONTH_BASES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curMm === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff eng-box" data-for="MONTHLY"><label>Minimum working days</label>
      <input class="form-control" type="number" min="1" max="31" name="manmonth_min_days"
             value="<?= e((($call['manmonth_min_days'] ?? 0) ?: '')) ?>" placeholder="e.g. 26">
      <small class="muted">Only used on the minimum-days basis.</small></div>

    <?php // PATTERN — how it repeats, and until when. ?>
    <div class="ff eng-box" data-for="PATTERN"><label>How does it repeat?</label>
      <select class="form-control" id="pattern_kind" name="pattern_kind">
        <?php $curPk = ($call['pattern_kind'] ?? '') ?: 'WEEKDAYS';
              foreach (lk_options_or('pattern_kind', PATTERN_KINDS) as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curPk === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff eng-box" data-for="PATTERN" id="pat_n_box"><label id="pat_n_label">How many</label>
      <input class="form-control" type="number" min="1" max="30" id="pattern_n" name="pattern_n"
             value="<?= e(($call['pattern_n'] ?? '') ?: '') ?>"></div>
    <div class="ff ff-wide eng-box" data-for="PATTERN" id="pat_wd_box"><label>On these days</label>
      <div class="chip-row pickbox" style="max-height:none">
        <?php foreach (WEEKDAY_NAMES as $n => $nm): ?>
          <label class="ff-check"><input type="checkbox" name="schedule_weekdays[]" value="<?= $n ?>" <?= in_array($n, $curWd, true) ? 'checked' : '' ?>> <?= e($nm) ?></label>
        <?php endforeach; ?>
      </div></div>
    <div class="ff eng-box" data-for="PATTERN"><label>Repeat until</label>
      <input class="form-control" type="date" id="schedule_end_date" name="schedule_end_date" value="<?= e($call['schedule_end_date'] ?? '') ?>"></div>

    <?php // Travel days belong to the inspection they are travelling for. ?>
    <div class="ff ff-wide ff-check">
      <label><input type="checkbox" name="is_outstation" value="1"
        <?= !empty($call['is_outstation']) ? 'checked' : '' ?>> Outstation — the <?= e(Tl('engineer')) ?> travels to reach this site</label>
      <small class="muted">Travel days either side are then costed to this <?= e(Tl('sbu')) ?> and activity code rather than counted as non-chargeable.</small></div>
  </div>

  <?php // MULTIPLE — the client names the days. Two lines to begin with, and a
        // button that adds one more; a wall of empty date boxes is what made
        // this screen unreadable. ?>
  <div class="panel eng-box" data-for="MULTIPLE" style="background:var(--soft);margin:8px 0">
    <b>The dates the <?= e(Tl('client')) ?> has asked for</b>
    <span class="muted">— it starts on the earliest and ends on the latest; the days in between are not inspection days.</span>
    <div id="datebox" style="margin-top:8px">
      <?php $slots = max(2, count($curDates));
            for ($i = 0; $i < $slots; $i++): ?>
        <div class="ff dateline" style="max-width:280px">
          <label>Date <?= $i + 1 ?></label>
          <input class="form-control" type="date" name="inspection_dates[]" value="<?= e($curDates[$i] ?? '') ?>"></div>
      <?php endfor; ?>
    </div>
    <button type="button" class="btn small secondary" id="adddate_call">+ Add another date</button>
  </div>

  <?php // Whatever shape it is, this is what it comes to. Worked out by the
        // server as the boxes are filled, so nobody presses Save wondering. ?>
  <div class="msg" id="sched_out" style="display:none;margin:8px 0"></div>

</section>
<section class="fs-pane" data-tab="Offices &amp; money">
  <h3 class="tab-sub" style="margin-top:0">4. Which <?= e(TP('office')) ?>, and the money between them</h3>
  <div class="form-grid">
    <div class="ff"><label>Contracting <?= e(T('office')) ?> <span class="muted">— who holds the order</span></label>
      <select class="form-control searchable" id="ibo_sel" name="ibo_office_id"><option value="">— my <?= e(T('office')) ?> —</option>
        <?php $iboCur = $call['ibo_office_id'] ?? (current_user()['home_office_id'] ?? null);
          foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($iboCur==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Executing <?= e(T('office')) ?> <span class="muted">— who does the work</span> <a href="#" class="addlink" data-qa="office">+ Add new</a></label>
      <select class="form-control searchable" id="exec_sel" name="executing_office_id"><option value="">— the same <?= e(T('office')) ?> —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($call && ($call['executing_office_id']??null)==$o['id'])?'selected':'' ?>><?= e($o['name']) ?><?= $o['coordinator_name']?' · '.e($o['coordinator_name']):'' ?></option><?php endforeach; ?>
      </select></div>
    <?php // Forward BY NAME. An office has several coordinators; handing the call
          // to "the office" is exactly how one lands with someone the raiser can no
          // longer find. The list narrows to the executing office's coordinators
          // (or this office's, when the work stays here). ?>
    <div class="ff" id="coord_wrap">
      <label>Forward to coordinator <span class="muted">— who owns this <?= e(Tl('call')) ?> in that <?= e(T('office')) ?></span></label>
      <select class="form-control searchable" id="coord_sel" name="coordinator_id">
        <option value="">— any coordinator in the <?= e(T('office')) ?> —</option>
      </select>
      <small class="muted">Leave blank to notify every coordinator in that <?= e(T('office')) ?>.</small>
    </div>
    <script>
    (function(){
      var COORDS = <?= json_encode($officeCoordinators ?? [], JSON_UNESCAPED_UNICODE) ?>;
      var HOME   = <?= (int)($homeOfficeId ?? 0) ?>;
      var CUR    = <?= (int)($call['coordinator_id'] ?? 0) ?>;
      var exec   = document.getElementById('exec_sel');
      var sel    = document.getElementById('coord_sel');
      var wrap   = document.getElementById('coord_wrap');
      if (!sel) return;
      function fill(){
        var oid = (exec && exec.value) ? parseInt(exec.value,10) : HOME;
        var list = (COORDS && COORDS[oid]) ? COORDS[oid] : [];
        var keep = CUR;
        sel.innerHTML = '<option value="">— any coordinator in the office —</option>';
        list.forEach(function(c){
          var o = document.createElement('option');
          o.value = c.id; o.textContent = c.name + (c.role ? ' · ' + c.role.replace(/_/g,' ').toLowerCase() : '');
          if (parseInt(c.id,10) === parseInt(keep,10)) o.selected = true;
          sel.appendChild(o);
        });
        // Nowhere to forward (office has no coordinators on file) — hide the row.
        if (wrap) wrap.style.display = list.length ? '' : 'none';
      }
      fill();
      if (exec) exec.addEventListener('change', function(){ CUR = 0; fill(); });
    })();
    </script>
    <?php if (!empty($showRegion)): ?>
    <div class="ff"><label>Region <span class="muted">— roll-up for <?= e(T('sbu')) ?> heads and the Business Director</span></label>
      <select class="form-control searchable" name="region"><option value="">—</option>
        <?php foreach (lk_options_or('region', OPS_REGIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['region']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <?php else: ?>
      <input type="hidden" name="region" value="<?= e($call['region'] ?? '') ?>">
    <?php endif; ?>
  </div>

  <div class="panel" id="moneybox" style="background:var(--soft);margin:8px 0">
    <div id="creditnote" class="muted" style="margin-bottom:8px"><?= e($ex['text']) ?></div>
    <?php // §13 — the rate is what was quoted, per unit. The quantity is how many
          // of those units this call is asking for, which for a day-priced order
          // is simply how many visit dates it has: one for a single day, the count
          // for several, and the expanded count for a repeating pattern. The value
          // is their product and is never typed, so 5,000 a man-day for two days
          // can only ever read 10,000. ?>
    <div class="form-grid" id="samebox">
      <div class="ff"><label>Unit rate — <strong>excluding GST</strong> (<?= e(cur_sym()) ?>) <span class="muted">— as quoted</span></label>
        <input class="form-control" type="number" step="0.01" id="billable_rate" name="billable_rate" value="<?= e($call['billable_rate'] ?? '') ?>"></div>
      <div class="ff"><label>Basis <span class="muted">— as quoted</span></label>
        <select class="form-control" id="billable_basis" name="billable_basis"><option value="">—</option>
          <?php foreach (lk_options_or('charge_unit', CHARGE_UNITS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['billable_basis']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Quantity <span class="muted" id="qty_hint">— counted from the visit dates</span></label>
        <input class="form-control" type="number" step="0.5" min="0" id="billable_qty" name="billable_qty" value="<?= e(($call['billable_qty'] ?? '') ?: '') ?>">
        <?php // Tells the server whether this figure was derived or typed. A repeating
              // pattern is expanded into dates on the server, so the browser cannot
              // know the count yet — when this is 1, the server recounts after
              // expanding and prices against that. ?>
        <input type="hidden" id="billable_qty_auto" name="billable_qty_auto" value="1">
        <small class="muted">Change it if this <?= e(Tl('call')) ?> is for a different number of units.</small></div>
      <div class="ff"><label>Value billable to the <?= e(Tl('client')) ?> (<?= e(cur_sym()) ?>, ex-GST) <span class="muted">— rate × quantity</span></label>
        <input class="form-control" type="number" step="0.01" id="billable_value" name="billable_value" value="<?= e($call['billable_value'] ?? '') ?>" readonly
               style="background:var(--soft);font-weight:700">
        <small class="muted" id="calc_note"></small></div>
    </div>
    <?php // §credit — the credit is agreed per man-day exactly as the client
          // charge is, so it is entered the same way and totalled the same way.
          // Only the total was ever stored, which is why a six-day deputation
          // could carry one day's credit with nothing on screen to show it. ?>
    <div class="form-grid" id="crossbox" style="display:none">
      <div class="ff"><label>Credit per man-day to the executing <?= e(T('office')) ?> (<?= e(cur_sym()) ?>)</label>
        <input class="form-control" type="number" step="0.01" id="credit_rate" name="credit_rate" value="<?= e(($call['credit_rate'] ?? '') ?: '') ?>">
        <small class="muted">What the executing branch is paid for each day.</small></div>
      <div class="ff"><label>Total credit (<?= e(cur_sym()) ?>) <span class="muted">— rate × quantity</span></label>
        <input class="form-control" type="number" step="0.01" id="credit_total" name="expected_credit" value="<?= e($call['expected_credit'] ?? '') ?>">
        <input type="hidden" id="credit_total_auto" name="expected_credit_auto" value="<?= ((float)($call['expected_credit'] ?? 0) > 0 && (float)($call['credit_rate'] ?? 0) <= 0) ? '0' : '1' ?>">
        <small class="muted" id="credit_note">Type over it if this one is credited differently.</small></div>
      <div class="ff"><label>Credit basis</label>
        <select class="form-control" name="credit_type"><option value="">—</option>
          <?php foreach (lk_options_or('credit_type', CREDIT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['credit_type']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Total invoice value to the <?= e(Tl('client')) ?> (<?= e(cur_sym()) ?>, ex-GST)</label>
        <input class="form-control" type="number" step="0.01" id="billable_value_x" name="billable_value_x" value="<?= e($call['billable_value'] ?? '') ?>" disabled
               title="Worked out above from the rate and the quantity"></div>
    </div>
    <?php // §revenue — what this branch actually keeps. Held behind its own
          // permission: a coordinator has to see the credit to do the job and
          // has no business seeing what the branch earns on it. ?>
    <?php if (can_see_revenue()): ?>
      <div class="msg" id="rev_box" style="margin:8px 0"></div>
    <?php else: ?>
      <p class="muted" style="margin:8px 2px">The revenue on this <?= e(Tl('call')) ?> is not shown to your role.</p>
    <?php endif; ?>
  </div>

</section>
<section class="fs-pane" data-tab="Purchase order">
  <h3 class="tab-sub" style="margin-top:0">5. Against the <?= e(Tl('client')) ?>'s purchase order <span class="muted">(optional)</span></h3>
  <div class="form-grid">
    <?php // When the client has not sent a formal PO, the work is still against a
          // line of OUR accepted quotation — offer those lines here so the call is
          // tied to what was quoted (quantity, rate) instead of being left loose. ?>
    <?php $qid = (int)($call['quotation_id'] ?? 0);
          $qLines = $qid ? ops_all("SELECT id, line_no, description, service_type, qty, unit, rate FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [$qid]) : []; ?>
    <?php if ($qLines): ?>
    <div class="ff"><label>Quotation line <span class="muted">— use when there is no client PO</span></label>
      <select class="form-control searchable" name="quote_line_id"><option value="">— none / whole quotation —</option>
        <?php foreach ($qLines as $ql):
          $lbl = ($ql['description'] ?: $ql['service_type']) . ' — ' . rtrim(rtrim(number_format((float)$ql['qty'], 2), '0'), '.') . ' ' . $ql['unit'] . ' @ ' . number_format((float)$ql['rate'], 2); ?>
          <option value="<?= (int)$ql['id'] ?>" <?= ((int)($call['quote_line_id'] ?? 0) === (int)$ql['id']) ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="ff"><label>Purchase order</label>
      <select class="form-control searchable" id="po_sel" name="po_id"><option value="">— open / none —</option>
        <?php if ($call && ($call['po_id']??null)) { $po=ops_one("SELECT id,po_number FROM partner_purchase_orders WHERE id=?", [$call['po_id']]); if ($po) echo '<option value="'.(int)$po['id'].'" selected>'.e($po['po_number']?:'Open order').'</option>'; } ?>
      </select>
      <small class="muted"><a id="po_add_link" href="#" target="_blank" style="display:none">＋ Record this <?= e(Tl('client')) ?>'s PO in the directory</a></small></div>
    <div class="ff"><label>PO line item <span class="muted">(tracks quantity)</span></label>
      <select class="form-control searchable" id="po_line_sel" name="po_line_item_id"><option value="">—</option>
        <?php if ($call && ($call['po_line_item_id']??null)) { $li=ops_one("SELECT id,description,rate,item_type,quantity,consumed FROM po_line_items WHERE id=?", [$call['po_line_item_id']]); if ($li) echo '<option value="'.(int)$li['id'].'" selected data-rate="'.e((string)$li['rate']).'" data-unit="'.e((string)$li['item_type']).'" data-balance="'.e((string)((float)$li['quantity'] - (float)$li['consumed'])).'">'.e($li['description']).'</option>'; } ?>
      </select>
      <?php // §l — what the order has left, against what this call is asking for. ?>
      <small class="muted" id="po_bal_note"></small></div>
    <div class="ff ff-check"><input type="checkbox" name="notify_manager" <?= ($call && !empty($call['notify_manager']))?'checked':'' ?>><label>Also e-mail the branch manager on forwarding</label></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($call['notes'] ?? '') ?>"></div>
    <?php render_custom_fields('call', $cfvals ?? []); ?>
  </div>

  <?php // §i — what the client is owed in the way of reporting is agreed when the
        // call is taken, not invented at allocation. Both fields are carried onto
        // every job raised from this call, so the engineer is asked for exactly
        // what was promised. ?>
</section>
<section class="fs-pane" data-tab="Reporting">
  <h3 class="tab-sub" style="margin-top:0">6. Reporting owed to the <?= e(Tl('client')) ?> <span class="muted">— carried onto the <?= e(TP('job')) ?></span></h3>
  <?php
    $callFreq  = $call['reporting_frequency'] ?? '';
    $callDays  = $call['report_custom_days'] ?? '';
    $callDeliv = array_values(array_filter(array_map('trim', explode(',', (string)($call['deliverables'] ?? '')))));
    $callChg   = chargeable_heads($call ?: []);
  ?>
  <div class="form-grid">
    <div class="ff"><label>Reporting frequency</label>
      <select class="form-control" id="call_freq_sel" name="reporting_frequency">
        <option value="">— decide at allocation —</option>
        <?php foreach (lk_options_or('reporting_frequency', REPORT_FREQ) as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $callFreq===$k?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">How often the <?= e(Tl('engineer')) ?> must send progress in — shown on the <?= e(T_REG('call')) ?>.</small></div>
    <div class="ff" id="call_days_wrap" style="<?= $callFreq==='CUSTOM'?'':'display:none' ?>"><label>…every how many days?</label>
      <input class="form-control" type="number" min="1" name="report_custom_days" value="<?= e($callDays) ?>" placeholder="e.g. 3"></div>

    <div class="ff ff-wide"><label>Types of <?= e(TP('report')) ?> required</label>
      <div class="checkgrid">
        <?php foreach (deliverable_options() as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="deliverables[]" value="<?= e($k) ?>" <?= in_array($k, $callDeliv, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Each format ticked is handed to the <?= e(Tl('engineer')) ?> on every <?= e(Tl('job')) ?> raised from this <?= e(Tl('call')) ?>, and is the only list they can report against. Maintained in the <a href="/report-types" target="_blank"><?= e(Tl('report')) ?> types</a> register.</small></div>

    <div class="ff ff-wide"><label>Expenses the <?= e(Tl('client')) ?> pays for <span class="muted">(tick any number)</span></label>
      <div class="checkgrid">
        <?php foreach (chargeable_head_options() as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="chargeable_heads[]" value="<?= e($k) ?>" <?= in_array($k, $callChg, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Travelling, lodging, boarding and the rest — tick whatever this <?= e(Tl('client')) ?> has agreed to reimburse on top of the fee. Anything ticked <strong>must have its bill uploaded</strong> before the <?= e(Tl('job')) ?> can be closed, and is then taken out of the cost when the profit is worked out. Headings come from the <a href="/lookup?key=expense_heading" target="_blank">expense headings</a> list.</small></div>
  </div>

</section>
</div><!-- /.form-tabs -->

  <div class="fs-actions" style="margin-top:16px;">
    <button class="btn" type="submit">Save <?= e(Tl('call')) ?></button>
    <a class="btn secondary" href="/calls">Cancel</a>
  </div>
</form>

<script>
(function(){
  // ---- §a.viii: say what the credit actually is, in the offices' own names ---
  var ibo=document.getElementById('ibo_sel'), ex=document.getElementById('exec_sel');
  var same=document.getElementById('samebox'), cross=document.getElementById('crossbox');
  var note=document.getElementById('creditnote');
  function offName(sel){ return sel.value ? sel.options[sel.selectedIndex].text.split(' · ')[0] : 'your office'; }
  function syncMoney(){
    var e=ex.value||'', m=ibo.value||'';
    var isSame = (e==='') || (e===m);
    same.style.display  = isSame ? '' : 'none';
    cross.style.display = isSame ? 'none' : '';
    note.textContent = isSame
      ? 'One office both holds the contract and does the work, so there is no inter-office credit — only the value billable to the client.'
      : (offName(ibo) + ' holds this contract and ' + offName(ex) + ' will do the work, so ' + offName(ibo)
         + ' gives ' + offName(ex) + ' a credit. Enter what ' + offName(ex) + ' is to receive — they can revert with the figure they need.');
  }
  ibo.addEventListener('change', function () { syncMoney(); if (window.__recalcCredit) window.__recalcCredit(); });
  ex.addEventListener('change', function () { syncMoney(); if (window.__recalcCredit) window.__recalcCredit(); });
  syncMoney();

  // (the old date-grid toggle lived here; the engagement shape now decides
  //  which boxes exist at all — see initEngagement in app.js)

  // ---- §13: value billable = unit rate × quantity, always ---------------------
  // The quantity follows the visit dates unless somebody has deliberately typed
  // one, because that is what "how many days are there in between" means once a
  // pattern has been expanded into actual dates. A lump-sum order has no
  // quantity to count, so it stays at one.
  var rateBox = document.getElementById('billable_rate'), qtyBox = document.getElementById('billable_qty'),
      valBox  = document.getElementById('billable_value'), basisSel = document.getElementById('billable_basis'),
      calcNote = document.getElementById('calc_note'), qtyHint = document.getElementById('qty_hint');
  var qtyTouched = false;
  var qtyAuto = document.getElementById('billable_qty_auto');
  function markTouched(v){ qtyTouched = v; if (qtyAuto) qtyAuto.value = v ? '0' : '1'; }
  if (qtyBox) qtyBox.addEventListener('input', function(){ markTouched(true); recalc(); });
  function lumpSum(){ var b = basisSel ? basisSel.value : ''; return b === 'LOT' || b === 'DOC' || b === 'OTHER'; }
  // How many units this call is asking for. It used to be counted by looking
  // for filled-in date boxes on the page — which stopped working the moment the
  // form only shows the boxes the chosen shape needs: a continuous run of six
  // days has no date boxes at all, so the count read zero and the value stopped
  // calculating. The number now comes from the schedule itself, which is the
  // only thing that actually knows: six days is six, a posting is however many
  // man-months are claimable, and a lump sum is one.
  function datesCount(){
    if (typeof window.__schedCount === 'number' && window.__schedCount > 0) return window.__schedCount;
    var n = 0;
    Array.prototype.forEach.call(document.querySelectorAll('input[name="inspection_dates[]"]'), function(i){ if (i.value) n++; });
    return n;
  }
  // What to call those units on screen, so the hint is not lying about where
  // the figure came from.
  function countWord(){ return window.__schedWord || 'the visit dates'; }
  function recalc(){
    if (!rateBox || !qtyBox || !valBox) return;
    if (!qtyTouched) {
      var auto = lumpSum() ? 1 : Math.max(1, datesCount());
      qtyBox.value = auto;
    }
    var r = parseFloat(rateBox.value || '0'), q = parseFloat(qtyBox.value || '0');
    if (!(r > 0)) { valBox.value = ''; if (calcNote) calcNote.textContent = 'Enter the unit rate.'; return; }
    if (!(q > 0)) q = 1;
    valBox.value = (Math.round(r * q * 100) / 100).toFixed(2);
    var unit = (basisSel && basisSel.value && basisSel.options[basisSel.selectedIndex])
             ? basisSel.options[basisSel.selectedIndex].text.toLowerCase() : 'unit';
    if (calcNote) calcNote.textContent = r.toLocaleString() + ' per ' + unit + ' × ' + q + ' = ' + valBox.value;
    if (qtyHint) qtyHint.textContent = qtyTouched ? '— entered by hand'
                : (lumpSum() ? '— a lump sum is one unit' : '— ' + countWord() + ' (' + datesCount() + ')');
    // The credit is priced off the same quantity, and the revenue off both.
    if (valueX) valueX.value = valBox.value;
    creditRecalc();
  }
  // ---- §credit / §revenue -------------------------------------------------
  //  The credit is agreed per man-day, exactly as the client charge is, and the
  //  quantity is the same quantity. So the same sum, and then the one figure
  //  that actually matters to a branch: what it keeps.
  var creditRate  = document.getElementById('credit_rate'),
      creditTotal = document.getElementById('credit_total'),
      creditAuto  = document.getElementById('credit_total_auto'),
      creditNote  = document.getElementById('credit_note'),
      valueX      = document.getElementById('billable_value_x'),
      revBox      = document.getElementById('rev_box');
  var creditTouched = creditAuto ? creditAuto.value === '0' : false;
  if (creditTotal) creditTotal.addEventListener('input', function () {
    creditTouched = true;
    if (creditAuto) creditAuto.value = '0';
    if (creditNote) creditNote.textContent = 'Typed by hand — saved exactly as entered.';
    showRevenue();
  });
  function creditRecalc() {
    if (!creditTotal) return;
    var q = parseFloat(qtyBox ? qtyBox.value || '0' : '0') || 1;
    var cr = parseFloat(creditRate ? creditRate.value || '0' : '0');
    if (!creditTouched) {
      if (cr > 0) {
        creditTotal.value = (Math.round(cr * q * 100) / 100).toFixed(2);
        if (creditNote) creditNote.textContent = cr.toLocaleString() + ' × ' + q + ' = ' + creditTotal.value
          + '. Type over it if this one is credited differently.';
      } else if (creditNote) {
        creditNote.textContent = 'Enter the credit per man-day, or type the total here.';
      }
    }
    showRevenue();
  }
  //  Invoice − credit. On a same-office call there is no credit, so the revenue
  //  is the whole invoice; the box only exists when it crosses offices.
  function showRevenue() {
    if (!revBox) return;
    var crossNow = cross && cross.style.display !== 'none';
    var inv = parseFloat(valBox ? valBox.value || '0' : '0');
    var cred = crossNow ? (parseFloat(creditTotal ? creditTotal.value || '0' : '0') || 0) : 0;
    if (!(inv > 0)) { revBox.style.display = 'none'; return; }
    var rev = Math.round((inv - cred) * 100) / 100;
    revBox.style.display = '';
    revBox.className = 'msg ' + (rev < 0 ? 'msg-error' : 'msg-success');
    var money = function (v) { return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    revBox.innerHTML = crossNow
      ? '<strong>Revenue to this ' + (window.__officeWord || 'office') + ': ' + money(rev) + '</strong>'
        + ' <span class="muted">— total invoice ' + money(inv) + ' less the ' + money(cred)
        + ' credited to the executing ' + (window.__officeWord || 'office') + '.'
        + ' They book the ' + money(cred) + '; the two add back to the invoice.</span>'
        + (rev < 0 ? '<div><strong>The credit is more than the invoice.</strong> Check both figures.</div>' : '')
      : '<strong>Revenue to this ' + (window.__officeWord || 'office') + ': ' + money(rev) + '</strong>'
        + ' <span class="muted">— one ' + (window.__officeWord || 'office') + ' holds the order and does the work,'
        + ' so the whole invoice value is its revenue.</span>';
  }
  if (creditRate) creditRate.addEventListener('input', creditRecalc);
  window.__recalcCredit = creditRecalc;

  if (rateBox) rateBox.addEventListener('input', recalc);
  if (basisSel) basisSel.addEventListener('change', recalc);
  // The date lines and every other box that shapes the schedule are watched
  // by initEngagement, which re-prices through __recalcBillable when the
  // server comes back with the day count.
  // An existing call already has a quantity recorded; leave it alone.
  if (qtyBox && parseFloat(qtyBox.value || '0') > 0) markTouched(true);
  recalc();
  window.__recalcBillable = recalc;

  // ---- §i: "every N days" only matters when the frequency is Custom ----------
  var freqSel = document.getElementById('call_freq_sel'), daysWrap = document.getElementById('call_days_wrap');
  if (freqSel && daysWrap) {
    freqSel.addEventListener('change', function(){
      daysWrap.style.display = freqSel.value === 'CUSTOM' ? '' : 'none';
    });
  }

  // ---- §a.i / §a.ii / §a.iv: the quote drives the commercial fields ---------
  var clientSel=document.getElementById('client_sel'), qSel=document.getElementById('quote_sel'),
      lineSel=document.getElementById('qline_sel'), contractBox=document.getElementById('contract_no');

  function fillQuotes(keep){
    var want = keep ? (qSel.value || qSel.dataset.cur || '') : '';
    qSel.innerHTML = '';
    var o0=document.createElement('option'); o0.value='';
    o0.textContent = clientSel.value ? '— none / direct call —' : '— pick a client first —';
    qSel.appendChild(o0);
    if (!clientSel.value) { fillLines(null); return; }
    fetch('/client-quotes?id=' + encodeURIComponent(clientSel.value))
      .then(function(r){ return r.json(); })
      .then(function(list){
        list.forEach(function(q){
          var el=document.createElement('option'); el.value=q.id; el.textContent=q.label;
          el.dataset.contract = q.contract_number || '';
          if (String(q.id) === String(want)) el.selected = true;
          qSel.appendChild(el);
        });
        if (qSel.value) loadQuote(true);
        else setContractMode(false);        // direct call — the box is the user's
      }).catch(function(){});
  }
  function fillLines(ctx){
    var want = lineSel.value || lineSel.dataset.cur || '';
    lineSel.innerHTML='';
    var o0=document.createElement('option'); o0.value=''; o0.textContent='— whole quote —'; lineSel.appendChild(o0);
    if (!ctx || !ctx.lines) return;
    ctx.lines.forEach(function(l){
      var el=document.createElement('option'); el.value=l.id; el.textContent=l.label;
      el.dataset.sbu=l.sbu||''; el.dataset.service=l.service_type||'';
      el.dataset.activity=l.activity_id||''; el.dataset.amount=l.amount||''; el.dataset.unit=l.unit||'';
      el.dataset.rate=l.rate||''; el.dataset.qty=l.qty||'';
      if (String(l.id) === String(want)) el.selected = true;
      lineSel.appendChild(el);
    });
  }
  // ---- §11: the client's own works is the inspection site -------------------
  // Ticking this copies the client into the site box, adding the option if the
  // partner has never been used as a site before. The server flags them as a
  // site partner on save, so next time they are simply in the list.
  var sicBox = document.getElementById('site_is_client'), sicNote = document.getElementById('sic_note');
  function clientOptionLabel(){
    var o = clientSel.options[clientSel.selectedIndex];
    return o && o.value ? o.textContent.trim() : '';
  }
  function applySameAsClient(){
    if (!sicBox.checked) { sicNote.style.display = 'none'; return; }
    var cid = clientSel.value, label = clientOptionLabel();
    if (!cid) { sicBox.checked = false; alert('Pick the client first.'); return; }
    var found = false;
    Array.prototype.forEach.call(vendorSel.options, function(o){ if (o.value === cid) found = true; });
    if (!found) {
      var op = document.createElement('option');
      op.value = cid; op.textContent = label + ' (client)';
      vendorSel.appendChild(op);
    }
    vendorSel.value = cid;
    vendorSel.dispatchEvent(new Event('change', {bubbles:true}));
    sicNote.style.display = '';
  }
  var vendorSel = document.getElementById('vendor_sel');
  sicBox.addEventListener('change', applySameAsClient);
  // Untick it the moment somebody chooses a different site by hand.
  vendorSel.addEventListener('change', function(){
    if (sicBox.checked && vendorSel.value !== clientSel.value) {
      sicBox.checked = false; sicNote.style.display = 'none';
    }
  });
  // Reflect an already-saved call where the two are the same.
  if (vendorSel.value && vendorSel.value === clientSel.value) {
    sicBox.checked = true; sicNote.style.display = '';
  }

  function setIfEmpty(el, v){ if (el && v && !el.value) el.value = v; }
  // The contract box is owned by the quotation when one is chosen, and by the
  // user when one is not. Switching to "direct call" must NOT wipe a number the
  // user typed themselves — that was how a direct call against a live ARC ended
  // up with no contract reference at all.
  var cnHint = document.getElementById('cn_hint'), cnNote = document.getElementById('cn_note');
  function setContractMode(fromQuote, value){
    if (fromQuote) {
      contractBox.value = value || '';
      contractBox.readOnly = true;
      contractBox.style.background = 'var(--soft)';
      if (cnHint) cnHint.textContent = '— from the quotation';
      if (cnNote) cnNote.style.display = 'none';
    } else {
      contractBox.readOnly = false;
      contractBox.style.background = '';
      if (cnHint) cnHint.textContent = '— type it if this draws on an existing contract';
      if (cnNote) cnNote.style.display = '';
    }
  }
  function loadQuote(initial){
    var opt = qSel.options[qSel.selectedIndex];
    if (!qSel.value) { setContractMode(false); fillLines(null); return; }
    setContractMode(true, opt && opt.dataset.contract ? opt.dataset.contract : '');
    fetch('/quote-context?id=' + encodeURIComponent(qSel.value))
      .then(function(r){ return r.json(); })
      .then(function(ctx){
        if (!ctx) return;
        if (ctx.contract_number) contractBox.value = ctx.contract_number;
        // remembered so clearing the line falls back to the quote's own types
        window.__quoteTypes = ctx.inspection_types || null;
        // The product category is a header field on the quotation — one order,
        // one category — so it narrows off the quote rather than off the line.
        window.__quoteProduct = ctx.product_category || null;
        narrowToLine(null, window.__quoteTypes);
        // Carry the commercial terms across. On a fresh call fill everything; on
        // an existing one only fill blanks, so a deliberate change is not undone.
        var sbu=document.getElementById('sbu_sel');
        if (sbu && ctx.sbu && (!initial || !sbu.value)) { sbu.value = ctx.sbu; sbu.dispatchEvent(new Event('change')); }
        var prod=document.getElementById('product_sel');
        if (prod && ctx.product_category) setIfEmpty(prod, ctx.product_category);
        var insp=document.getElementById('insp_sel');
        if (insp && ctx.inspection_types && ctx.inspection_types.length) setIfEmpty(insp, ctx.inspection_types[0]);
        // Deliberately NOT the quote total: that prices the whole order. A call
        // without a chosen line has no rate to inherit, so it is asked for.
        if (window.__recalcBillable) window.__recalcBillable();
        fillLines(ctx);
      }).catch(function(){});
  }
  // ---- §e: narrow the call's own dropdowns to what the order actually sold ---
  // The whole catalogue is the wrong list once a quotation line is chosen: that
  // line sold one service, at one product category. Offering everything is how
  // a call gets raised for work nobody quoted. The full list stays one click
  // away, because a client does occasionally ask for something extra.
  var INSP_ALL = null, PROD_ALL = null;
  function snapshot(sel){
    return Array.prototype.map.call(sel.options, function(o){
      return {v:o.value, t:o.textContent, s:o.selected};
    });
  }
  function rebuild(sel, all, allow, keep){
    if (!sel) return;
    var cur = keep || sel.value;
    sel.innerHTML = '';
    all.forEach(function(o){
      if (allow && o.v && allow.indexOf(o.v) === -1 && o.v !== cur) return;
      var op = document.createElement('option');
      op.value = o.v; op.textContent = o.t;
      if (o.v === cur) op.selected = true;
      sel.appendChild(op);
    });
    // The enhancer wraps the select in its own text input; rebuilding the
    // options behind it means that input has to be re-synced.
    var wrap = sel.closest('.ss-wrap');
    if (wrap) { var inp = wrap.querySelector('input'); if (inp) inp.value = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent.trim() : ''; }
  }
  function narrowNote(sel, on, label){
    var ff = sel ? sel.closest('.ff') : null; if (!ff) return;
    var note = ff.querySelector('.narrow-note');
    if (!on) { if (note) note.remove(); return; }
    if (note) return;
    note = document.createElement('small');
    note.className = 'muted narrow-note';
    note.innerHTML = 'Limited to what the ' + label + ' sold. <a href="#">Show every option</a>';
    note.querySelector('a').addEventListener('click', function(e){
      e.preventDefault();
      if (sel.id === 'insp_sel' && INSP_ALL) rebuild(sel, INSP_ALL, null);
      if (sel.id === 'product_sel' && PROD_ALL) rebuild(sel, PROD_ALL, null);
      note.remove();
    });
    ff.appendChild(note);
  }
  function narrowToLine(o, ctxTypes){
    var insp = document.getElementById('insp_sel'), prod = document.getElementById('product_sel');
    if (insp && !INSP_ALL) INSP_ALL = snapshot(insp);
    if (prod && !PROD_ALL) PROD_ALL = snapshot(prod);
    // A chosen line pins one service; otherwise allow everything the quote sold.
    var allow = (o && o.dataset.service) ? [o.dataset.service]
              : (ctxTypes && ctxTypes.length ? ctxTypes : null);
    if (insp && allow) { rebuild(insp, INSP_ALL, allow, allow.length === 1 ? allow[0] : insp.value); narrowNote(insp, true, 'quotation'); }
    else if (insp && INSP_ALL) { rebuild(insp, INSP_ALL, null); narrowNote(insp, false); }
    // The category the order was priced against, pinned the same way.
    var pcat = window.__quoteProduct || null;
    // A category typed freehand on the quote is not in the master list; narrowing
    // to it would leave the box empty, so leave the full list alone instead.
    if (pcat && PROD_ALL && !PROD_ALL.some(function(o){ return o.v === pcat; })) pcat = null;
    if (prod && pcat) { rebuild(prod, PROD_ALL, [pcat], pcat); narrowNote(prod, true, 'quotation'); }
    else if (prod && PROD_ALL) { rebuild(prod, PROD_ALL, null); narrowNote(prod, false); }
  }

  // Choosing one line narrows the call to that part of the order.
  lineSel.addEventListener('change', function(){
    var o = lineSel.options[lineSel.selectedIndex];
    if (!o || !o.value) { narrowToLine(null, window.__quoteTypes || null); return; }
    narrowToLine(o, null);
    var sbu=document.getElementById('sbu_sel');
    if (sbu && o.dataset.sbu) { sbu.value=o.dataset.sbu; sbu.dispatchEvent(new Event('change')); }
    var insp=document.getElementById('insp_sel');
    if (insp && o.dataset.service) insp.value=o.dataset.service;
    // §g / §13 — the unit rate and the basis come off the chosen line. The value
    // is then this call's own quantity times that rate, never the line's total,
    // which prices the whole order rather than this one visit.
    var br=document.getElementById('billable_rate');
    if (br && o.dataset.rate) br.value=o.dataset.rate;
    var bb=document.getElementById('billable_basis');
    if (bb && o.dataset.unit) { bb.value=o.dataset.unit; bb.dispatchEvent(new Event('change', {bubbles:true})); }
    if (window.__recalcBillable) window.__recalcBillable();
    // the activity list is filled by the business-unit cascade; select afterwards
    if (o.dataset.activity) setTimeout(function(){
      var a=document.getElementById('activity_sel'); if (a) a.value=o.dataset.activity;
    }, 250);
  });
  // ---- §b: a new client's master must actually be filled in -----------------
  // The details are not optional — an order cannot be invoiced without a tax
  // identity, and an inspection cannot be carried out without an address and
  // somebody to ring. The call is still saved, so nothing typed is lost, but it
  // cannot be forwarded to an executing office until the master is complete.
  var gapBox = document.getElementById('party_gaps'), vendorSel = document.getElementById('vendor_sel');
  function gapsFor(sel, role, label) {
    if (!sel || !sel.value) return Promise.resolve(null);
    return fetch('/partner-gaps?role=' + role + '&id=' + encodeURIComponent(sel.value))
      .then(function(r){ return r.json(); })
      .then(function(g){ return (g && g.missing && g.missing.length) ? {g:g, label:label} : null; })
      .catch(function(){ return null; });
  }
  function checkGaps() {
    if (!gapBox) return;
    Promise.all([
      gapsFor(clientSel, 'client', '<?= e(Tl('client')) ?>'),
      gapsFor(vendorSel, 'site',   'site')
    ]).then(function(res){
      var bad = res.filter(Boolean);
      if (!bad.length) { gapBox.style.display = 'none'; gapBox.innerHTML = ''; return; }
      var html = '<div class="panel" style="margin:0;border:1px solid var(--warn);'
               + 'background:color-mix(in srgb,var(--warn) 8%,transparent)">'
               + '<b>⚠ The master record is not complete</b>';
      bad.forEach(function(b){
        var m = b.g.missing;
        var list = m.length === 1 ? m[0] : m.slice(0, -1).join(', ') + ' and ' + m[m.length - 1];
        html += '<div style="margin-top:6px">' + esc(b.g.name) + ' <span class="muted">(' + b.label + ')</span>'
             +  ' is missing ' + esc(list) + '. '
             +  '<a class="btn small secondary" href="' + b.g.url + '" target="_blank" rel="noopener">Complete it</a></div>';
      });
      html += '<div class="muted" style="margin-top:8px">Save this ' + '<?= e(Tl('call')) ?>'
           +  ' by all means — nothing typed is lost. It cannot be forwarded to an executing '
           +  '<?= e(Tl('office')) ?> until the details are in, because that is the point at which '
           +  'somebody has to travel there and somebody has to be invoiced.'
           +  ' <a href="#" id="gaps_recheck">Re-check</a></div></div>';
      gapBox.innerHTML = html;
      gapBox.style.display = '';
      var rc = document.getElementById('gaps_recheck');
      if (rc) rc.addEventListener('click', function(e){ e.preventDefault(); checkGaps(); });
    });
  }
  function esc(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // ---- §PO: the client's purchase orders flow into the call -----------------
  // Picking the client loads that client's purchase orders straight from the
  // directory; picking an order loads its line items and shows the balance left
  // on the chosen line. The endpoints (/partner-pos, /po-lines) already exist —
  // this wires the form to them so PO detail is a single source that flows in
  // from the directory instead of being re-typed (or left blank) here.
  var poSel = document.getElementById('po_sel'),
      poLineSel = document.getElementById('po_line_sel'),
      poBal = document.getElementById('po_bal_note');
  if (poSel) { poSel.dataset.cur = poSel.value || ''; }
  if (poLineSel) { poLineSel.dataset.cur = poLineSel.value || ''; }
  function updatePOBal(){
    if (!poLineSel || !poBal) return;
    var opt = poLineSel.options[poLineSel.selectedIndex];
    if (opt && opt.value && opt.dataset.balance !== undefined && opt.dataset.balance !== '') {
      poBal.textContent = 'Balance left on this line: ' + opt.dataset.balance + ' ' + (opt.dataset.unit || '');
    } else if (poLineSel.value) {
      poBal.textContent = '';
    }
  }
  function fillPOLines(keep){
    if (!poLineSel) return;
    var want = keep ? (poLineSel.value || poLineSel.dataset.cur || '') : '';
    poLineSel.innerHTML = '';
    var o0 = document.createElement('option'); o0.value=''; o0.textContent='—'; poLineSel.appendChild(o0);
    if (poBal) poBal.textContent = '';
    if (!poSel || !poSel.value) return;
    fetch('/po-lines?id=' + encodeURIComponent(poSel.value))
      .then(function(r){ return r.json(); })
      .then(function(res){
        var lines = (res && res.lines) || [];
        lines.forEach(function(l){
          var el=document.createElement('option'); el.value=l.id; el.textContent=l.label;
          el.dataset.rate = (l.rate==null?'':l.rate); el.dataset.unit = l.unit||'';
          el.dataset.balance = (l.balance==null?'':l.balance);
          if (String(l.id) === String(want)) el.selected = true;
          poLineSel.appendChild(el);
        });
        if (!lines.length && res && res.hint && poBal) {
          poBal.innerHTML = esc(res.hint.text || 'This order has no line items yet.')
            + (res.hint.url ? ' <a href="'+esc(res.hint.url)+'" target="_blank">'+esc(res.hint.link||'Open the order')+' →</a>' : '');
        }
        updatePOBal();
      }).catch(function(){});
  }
  function fillPOs(keep){
    if (!poSel) return;
    var want = keep ? (poSel.value || poSel.dataset.cur || '') : '';
    poSel.innerHTML = '';
    var o0=document.createElement('option'); o0.value='';
    o0.textContent = clientSel.value ? '— open / none —' : '— pick a client first —';
    poSel.appendChild(o0);
    var addLink = document.getElementById('po_add_link');
    if (addLink) {
      if (clientSel.value) { addLink.href = '/partner?id=' + encodeURIComponent(clientSel.value) + '&tab=purchase_orders'; addLink.style.display = ''; }
      else { addLink.style.display = 'none'; }
    }
    if (!clientSel.value) { fillPOLines(false); return; }
    fetch('/partner-pos?id=' + encodeURIComponent(clientSel.value))
      .then(function(r){ return r.json(); })
      .then(function(list){
        (list||[]).forEach(function(o){
          var el=document.createElement('option'); el.value=o.id; el.textContent=o.label;
          if (String(o.id) === String(want)) el.selected = true;
          poSel.appendChild(el);
        });
        fillPOLines(true);
      }).catch(function(){});
  }
  if (poSel) poSel.addEventListener('change', function(){ fillPOLines(false); });
  if (poLineSel) poLineSel.addEventListener('change', updatePOBal);

  clientSel.addEventListener('change', function(){ fillQuotes(false); fillPOs(false); checkGaps(); });
  if (vendorSel) vendorSel.addEventListener('change', checkGaps);
  qSel.addEventListener('change', function(){ loadQuote(false); });
  fillQuotes(true);
  fillPOs(true);
  checkGaps();
  // Coming back from the master in the other tab should not need a reload.
  window.addEventListener('focus', checkGaps);
})();
</script>

<!-- Quick-add modal -->
<div class="modal-back" id="qa_back" style="display:none;">
  <div class="modal">
    <h3 id="qa_title">Add</h3>
    <div class="ff"><label>Name *</label><input class="form-control" id="qa_name" autocomplete="off"></div>
    <?php // §b — a name on its own is not a master record. Ask for what operations
          // and accounts cannot work without, here, while the coordinator still
          // has the client on the phone. Everything else can be filled in later
          // on the full master screen. ?>
    <div class="qa-field qa-cv">
      <div class="ff"><label>GSTIN <span class="muted" id="qa_gstin_hint">— auto PAN / state</span></label><input class="form-control" id="qa_gstin"></div>
      <div class="ff"><label>PAN <span class="muted">— if there is no GSTIN</span></label><input class="form-control" id="qa_pan"></div>
      <div class="ff"><label>Address <span id="qa_req_addr">*</span></label><input class="form-control" id="qa_line1" placeholder="works / office address"></div>
      <div class="ff"><label>City <span id="qa_req_city">*</span></label><input class="form-control" id="qa_qcity"></div>
      <?php // The same state list the master screen uses. A typed state does not
            // match anything downstream, and a GSTIN already knows the answer. ?>
      <div class="ff"><label>State</label>
        <select class="form-control searchable" id="qa_state"><option value="">—</option>
          <?php foreach (lk_options_or('gst_state', GST_STATES) as $sc => $sn): ?><option value="<?= e($sn) ?>"><?= e($sn) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Contact person <span>*</span></label><input class="form-control" id="qa_pname"></div>
      <div class="ff"><label>Mobile <span>*</span></label><input class="form-control" id="qa_pmob"></div>
      <div class="ff"><label>E-mail <span id="qa_req_mail">*</span></label><input class="form-control" id="qa_pmail"></div>
      <div class="ff ff-check"><input type="checkbox" id="qa_both"><label>Add to <strong>both</strong> <?= e(T('client')) ?> &amp; <?= e(T('vendor')) ?> lists</label></div>
      <p class="muted ff-wide" id="qa_cv_note" style="margin:2px 0 0"></p>
    </div>
    <div class="qa-field qa-office">
      <div class="ff"><label>Code</label><input class="form-control" id="qa_code"></div>
      <div class="ff"><label>City</label><input class="form-control" id="qa_city"></div>
      <div class="ff"><label>Coordinator name</label><input class="form-control" id="qa_cname"></div>
      <div class="ff"><label>Coordinator email</label><input class="form-control" id="qa_cemail"></div>
      <div class="ff"><label>Manager name</label><input class="form-control" id="qa_mname"></div>
      <div class="ff"><label>Manager email</label><input class="form-control" id="qa_memail"></div>
    </div>
    <div class="qa-field qa-activity"><p class="muted">Will be added under the <?= e(T('sbu')) ?> currently selected on the form.</p></div>
    <div id="qa_err" class="msg msg-error" style="display:none;"></div>
    <div style="margin-top:14px;display:flex;gap:8px;">
      <button class="btn" type="button" id="qa_save">Add &amp; select</button>
      <button class="btn secondary" type="button" id="qa_cancel">Cancel</button>
    </div>
  </div>
</div>
<script>window.ACTIVITY = <?= json_encode($act) ?>;
window.TERM_SBU = <?= json_encode(Tl('sbu')) ?>;
window.__callWord = <?= json_encode(Tl('call')) ?>;
window.INSPTYPES = <?= json_encode(lk_options_or('inspection_type', INSPECTION_TYPES)) ?>;</script>
