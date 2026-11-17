<?php
// The older month sheet (one row per day, a column per expense head), kept for
// vouchers made before claim periods existed — the claimant can still correct
// them while they are a DRAFT (Expenses Phase 1: "keep the function for legacy
// months"). Included by voucher_detail.php only for an editable legacy month,
// so the live-total script below never runs on a read-only voucher (the fault
// that showed Rs 0 totals on a submitted claim, 17 Nov 2026).
?>
<div class="panel" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
  <form method="post" action="/voucher-generate?id=<?= (int)$v['id'] ?>">
    <button class="btn" type="submit">↻ Pull working days from jobs</button>
    <p class="muted" style="margin:6px 2px 0;max-width:280px">Auto-fills each inspection day (date, site, File No / <?= e(Tl("boss")) ?>, <?= e(Tl("sbu")) ?>) from the jobs allotted to this inspector. Safe to click again — it won't duplicate.</p>
  </form>
  <form method="post" action="/voucher-entry?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
    <input type="hidden" name="_do" value="add">
    <div class="ff"><label>Add a non-inspection day</label><input class="form-control" type="date" name="entry_date" required></div>
    <div class="ff"><label>Type</label>
      <select class="form-control" name="day_type" id="add_daytype">
        <option value="OFFICE">In office</option><option value="LEAVE">Leave</option>
        <option value="HOLIDAY">Holiday</option><option value="WEEKOFF">Week-off</option>
      </select></div>
    <div class="ff" id="add_office"><label>Office code</label>
      <select class="form-control" name="office_code"><option value="">—</option><?php foreach ($dayOpts as $k=>$vv): ?><option value="<?= e($k) ?>"><?= e($vv) ?></option><?php endforeach; ?></select></div>
    <div class="ff" id="add_leave" style="display:none"><label>Leave code</label>
      <select class="form-control" name="leave_code"><option value="">—</option><?php foreach ($leaveOpts as $k=>$vv): ?><option value="<?= e($k) ?>"><?= e($vv) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Hours</label><input class="form-control" style="width:90px" type="number" step="0.25" min="0" max="<?= e(hours_cap()) ?>" name="hours" value="0"></div>
    <button class="btn small" type="submit">Add day</button>
  </form>
</div>

<?php
  $fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.') ?: '0';
  // running column totals
  $tTravel = 0; $tHead = []; foreach ($heads as $h) $tHead[$h['code']] = 0; $grand = 0;
  $ncol = 8 + count($heads) + ($canEdit ? 2 : 1); // Date,Site,File,Line,Hrs,Mode,KM,Travel + heads + Row(+✕)
?>
<?php // Module 47 — draft-protect the expense lines an inspector types in the field. ?>
<?php if ($canEdit): ?><form method="post" action="/voucher-save?id=<?= (int)$v['id'] ?>" id="vform" data-autosave="voucher-<?= (int)$v['id'] ?>"><?php endif; ?>
<style>
  <?php /* Sticky header: it must fully COVER the rows scrolling under it. var(--soft)
           was not opaque, so the first rows bled through and read as an overlap.
           Use the solid card background, lift the z-index above the inputs, and add
           a divider so the header sits cleanly above the grid. */ ?>
  #vgrid th{position:sticky;top:56px;background:var(--card);z-index:6;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);box-shadow:inset 0 -1px 0 var(--line),0 2px 4px rgba(0,0,0,.06)}
  #vgrid td,#vgrid th{padding:7px 8px;white-space:nowrap}
  #vgrid .form-control{padding:6px 8px;font-size:13px;background:var(--card)}
  #vgrid .v-travel,#vgrid .v-rowtotal{font-variant-numeric:tabular-nums;text-align:right}
  #vgrid tr[data-eid]:hover td{background:color-mix(in srgb,var(--brand) 4%,transparent)}
  .tbl-scroll{border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm)}

  <?php /* ---- Phone: the 12-column spreadsheet is unusable behind a sideways
           scroll on a thumb, so below 720px the SAME grid (same inputs, same
           names, same recalc JS — nothing about the data changes) reflows into
           one card per day. Each cell becomes a "Label: value" line, the column
           header row is dropped, and inputs go full-width. Desktop is untouched:
           every rule here lives inside the media query. */ ?>
  @media (max-width: 720px){
    .tbl-scroll{overflow-x:visible;border:none;box-shadow:none;border-radius:0}
    #vgrid, #vgrid tbody, #vgrid tr, #vgrid td{display:block;width:auto}
    #vgrid .vgrid-head{display:none}                       /* column headers make no sense stacked */
    #vgrid td{white-space:normal}
    /* each entry is a card */
    #vgrid tr[data-eid]:not(.v-noterow){border:1px solid var(--line);border-radius:var(--radius);
      background:var(--card);box-shadow:var(--shadow-sm);margin:0 0 12px;padding:4px 0 6px;overflow:hidden}
    #vgrid tr[data-eid]:hover td{background:transparent}   /* no hover paint on touch cards */
    /* the Date cell is the card's title bar */
    #vgrid td.v-date{background:var(--soft);font-weight:700;padding:9px 12px;margin-bottom:4px;
      border-bottom:1px solid var(--line)}
    /* every other cell: label left, value/input right. The label is emitted only
       when data-label is present AND non-empty, so action/spacer cells get no
       stray empty label column. */
    #vgrid tr[data-eid] td:not(.v-date){display:flex;justify-content:space-between;align-items:center;
      gap:12px;padding:6px 12px;border:none}
    #vgrid tr[data-eid] td[data-label]:not([data-label=""])::before{content:attr(data-label);font-size:11px;
      font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);flex:0 0 40%}
    #vgrid td:empty{display:none}                          /* drop the spacer cells */
    #vgrid tr[data-eid] .form-control{width:100% !important;flex:1 1 auto;max-width:60%}
    #vgrid td.v-travel,#vgrid td.v-rowtotal{justify-content:space-between}
    #vgrid td.v-rowtotal{background:var(--soft);border-top:1px solid var(--line);padding-top:8px;padding-bottom:8px}
    #vgrid td.row-actions{justify-content:flex-end}
    /* the per-day note: sits attached under its card */
    #vgrid tr.v-noterow{border:1px solid var(--line);border-top:none;border-radius:0 0 var(--radius) var(--radius);
      background:var(--card);margin:-12px 0 12px;padding:6px 12px 10px}
    #vgrid tr.v-noterow td{display:block;padding:2px 0;border:none;white-space:normal}
    #vgrid tr.v-noterow td::before{content:none}
    #vgrid tr[data-eid]:not(.v-noterow):has(+ .v-noterow){margin-bottom:0;border-bottom:none;
      border-radius:var(--radius) var(--radius) 0 0}
    /* running per-day + grand totals read as plain lines, not a broken table row */
    #vgrid tr.v-daytot,#vgrid tr.v-totalrow,#vgrid tr.v-grandrow{background:transparent}
    #vgrid tr.v-totalrow,#vgrid tr.v-grandrow{border:1px solid var(--line);border-radius:var(--radius);
      background:var(--soft);margin:0 0 10px;padding:4px 0}
    #vgrid tr.v-totalrow td,#vgrid tr.v-grandrow td{display:flex;justify-content:space-between;
      gap:12px;padding:6px 12px;text-align:left !important;border:none}
    #vgrid tr.v-totalrow td[data-label]:not([data-label=""])::before,
    #vgrid tr.v-grandrow td[data-label]:not([data-label=""])::before{content:attr(data-label);
      font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);flex:0 0 40%}
    #vgrid tr.v-daytot td{padding:6px 4px}
    .inline-add{flex-wrap:wrap}
  }
</style>
<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid" id="vgrid">
  <tr class="vgrid-head">
    <th>Date</th><th>Attendance / Site</th><th>File No (<?= e(T("boss")) ?>)</th><th>Line No</th><th>Hrs</th>
    <th>Mode</th><th>KM</th><th>Travel <?= e(cur_sym()) ?></th>
    <?php foreach ($heads as $h): ?><th title="<?= e($h['code']) ?>"><?= e($h['label']) ?></th><?php endforeach; ?>
    <th>Row <?= e(cur_sym()) ?></th><?php if ($canEdit): ?><th></th><?php endif; ?>
  </tr>
  <?php foreach ($byDate as $date => $rows): $dayHours = 0; ?>
    <?php foreach ($rows as $e):
      $dayHours += (float)$e['hours']; $monthHours += (float)$e['hours'];
      $isWork = $e['day_type']==='WORK';
      $att = $isWork ? ($e['site_label'] ?: '(site)') : ($e['day_type']==='LEAVE' ? ('Leave'.($e['leave_code']?' · '.($leaveOpts[$e['leave_code']]??$e['leave_code']):'')) : ($e['day_type']==='OFFICE' ? ('Office'.($e['office_code']?' · '.($dayOpts[$e['office_code']]??$e['office_code']):'')) : ucfirst(strtolower($e['day_type']))));
      $amt = expense_extra_decode($e['amounts'] ?? '');
      // km / mode prefill from vendor memory when not yet filled
      $mem = ((float)$e['km']>0 || !$isWork) ? null : vendor_km_lookup($v['inspector_id'], $e['vendor_id']);
      $kmVal = (float)$e['km']>0 ? $e['km'] : ($mem['km'] ?? '');
      $modeVal = $e['mode_code'] ?: ($mem['mode_code'] ?? '');
      $tTravel += (float)$e['travel_amount']; $grand += (float)$e['row_total'];
      foreach ($heads as $h) $tHead[$h['code']] += (float)($amt[$h['code']] ?? 0);
      $eid = (int)$e['id']; $P = "rows[$eid]";
    ?>
    <tr data-eid="<?= $eid ?>">
      <td class="v-date"><?= e(date('d-M', strtotime($date))) ?><?= $e['is_auto']?' <span class="badge GREEN" style="font-size:10px">auto</span>':'' ?><?= $mem?' <span class="muted" title="km remembered for this vendor" style="font-size:11px">↺</span>':'' ?></td>
      <td data-label="Attendance / Site"><?= e($att) ?><?php if (!empty($e['receipt_data'])): ?> <a href="/voucher-line-receipt?id=<?= $eid ?>" target="_blank" rel="noopener" title="View the receipt" style="text-decoration:none">📎</a><?php endif; ?></td>
      <?php if ($canEdit): ?>
      <td data-label="File No (<?= e(T('boss')) ?>)"><input form="vform" class="form-control" style="width:110px" name="<?= $P ?>[file_no]" value="<?= e($e['file_no']) ?>" <?= $isWork?'':'readonly' ?>></td>
      <td data-label="Line No"><input form="vform" class="form-control" style="width:80px" name="<?= $P ?>[line_no]" value="<?= e($e['line_no']) ?>" placeholder="acct"></td>
      <td data-label="Hours"><input form="vform" class="form-control v-hours" style="width:64px" type="number" step="0.25" min="0" max="<?= e(hours_cap()) ?>" name="<?= $P ?>[hours]" value="<?= e($fmt($e['hours'])) ?>"></td>
      <td data-label="Mode"><select form="vform" class="form-control v-mode" style="width:110px" name="<?= $P ?>[mode]"><option value="">—</option>
        <?php foreach ($modes as $m): ?><option value="<?= e($m['code']) ?>" <?= $modeVal===$m['code']?'selected':'' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select></td>
      <td data-label="KM"><input form="vform" class="form-control v-km" style="width:70px" type="number" step="0.1" min="0" name="<?= $P ?>[km]" value="<?= e($kmVal!==''?$fmt($kmVal):'') ?>"></td>
      <td class="v-travel" data-label="Travel <?= e(cur_sym()) ?>" data-eid="<?= $eid ?>"><?= e(cur_sym()) ?><?= $fmt($e['travel_amount']) ?></td>
      <?php foreach ($heads as $h): ?>
        <td data-label="<?= e($h['label']) ?>"><input form="vform" class="form-control v-amt" data-code="<?= e($h['code']) ?>" style="width:80px" type="number" step="0.01" min="0" name="<?= $P ?>[amt][<?= e($h['code']) ?>]" value="<?= e(isset($amt[$h['code']])?$fmt($amt[$h['code']]):'') ?>" <?= $h['head_type']==='BILL'?'title="actual bill"':'' ?>></td>
      <?php endforeach; ?>
      <td class="v-rowtotal" data-label="Row total <?= e(cur_sym()) ?>" data-eid="<?= $eid ?>"><strong><?= e(cur_sym()) ?><?= $fmt($e['row_total']) ?></strong></td>
      <td class="row-actions" data-label=""><button form="del_<?= $eid ?>" class="btn small danger" type="submit" onclick="return confirm('Remove this row?')">✕</button></td>
      <?php else: ?>
      <td data-label="File No (<?= e(T('boss')) ?>)"><?= e($e['file_no'] ?: '—') ?></td>
      <td data-label="Line No"><?= e($e['line_no'] ?: '—') ?></td>
      <td data-label="Hours"><?= e($fmt($e['hours'])) ?></td>
      <td data-label="Mode"><?= e($e['mode_code'] ?: '—') ?></td>
      <td data-label="KM"><?= (float)$e['km']>0 ? e($fmt($e['km'])) : '—' ?></td>
      <td data-label="Travel <?= e(cur_sym()) ?>"><?= e(cur_sym()) ?><?= $fmt($e['travel_amount']) ?></td>
      <?php foreach ($heads as $h): ?><td data-label="<?= e($h['label']) ?>"><?= isset($amt[$h['code']]) ? cur_sym().$fmt($amt[$h['code']]) : '—' ?></td><?php endforeach; ?>
      <?php // Row total in the read-only view carries NO v-rowtotal class on purpose:
            //  the recalc script (which runs on every load) must not find and zero it. ?>
      <td data-label="Row total <?= e(cur_sym()) ?>" style="text-align:right"><strong><?= e(cur_sym()) ?><?= $fmt($e['row_total']) ?></strong></td>
      <?php endif; ?>
    </tr>
    <?php // A per-line note / remark — so an incidental or "Others (specify)" expense
          //  can say what it was for. Kept as a light sub-row rather than another
          //  column, so the wide grid stays readable. ?>
    <?php if ($canEdit): ?>
    <tr class="v-noterow" data-eid="<?= $eid ?>">
      <td class="muted" style="text-align:right;font-size:11px" colspan="2">Note / remark</td>
      <td colspan="<?= $ncol - 2 ?>"><input form="vform" class="form-control" style="width:100%" name="<?= $P ?>[note]"
          value="<?= e($e['notes'] ?? '') ?>" placeholder="what the expense was for — e.g. incidental / conveyance / other"></td>
    </tr>
    <?php elseif (!empty($e['notes'])): ?>
    <tr class="muted" style="font-size:11px"><td></td><td colspan="<?= $ncol - 1 ?>">↳ <?= e($e['notes']) ?></td></tr>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if (count($rows) > 1): ?><tr class="v-daytot muted" style="font-size:12px"><td colspan="<?= $ncol ?>">↳ <?= e($date) ?> — day total hours: <strong><?= e($fmt($dayHours)) ?></strong></td></tr><?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$entries): ?><tr><td colspan="<?= $ncol ?>">No days yet. <?= $canEdit?'Click “Pull working days from jobs”.':'' ?></td></tr><?php endif; ?>
  <?php if ($entries): ?>
  <tr class="v-totalrow" style="background:var(--soft)">
    <td colspan="4" style="text-align:right"><strong>TOTAL</strong></td>
    <td data-label="Total hours"><strong class="tot-hours"><?= e($fmt($monthHours)) ?></strong></td>
    <td></td><td></td>
    <td id="tot-travel" data-label="Travel <?= e(cur_sym()) ?>"><strong><?= e(cur_sym()) ?><?= $fmt($tTravel) ?></strong></td>
    <?php foreach ($heads as $h): ?><td id="tot-amt-<?= e($h['code']) ?>" data-label="<?= e($h['label']) ?>"><strong><?= e(cur_sym()) ?><?= $fmt($tHead[$h['code']]) ?></strong></td><?php endforeach; ?>
    <td id="tot-grand" data-label="Total <?= e(cur_sym()) ?>"><strong><?= e(cur_sym()) ?><?= $fmt($grand) ?></strong></td>
    <?php if ($canEdit): ?><td></td><?php endif; ?>
  </tr>
  <tr class="v-grandrow"><td colspan="<?= $ncol - 1 ?>" style="text-align:right"><strong>Grand Total</strong></td><td id="tot-grand2" data-label="Grand total <?= e(cur_sym()) ?>"><strong><?= e(cur_sym()) ?><?= $fmt($grand) ?></strong></td></tr>
  <?php endif; ?>
</table>
</div>
<?php if ($canEdit): ?>
  <div style="margin-top:12px"><button class="btn" type="submit">💾 Save all rows &amp; totals</button></div>
  </form>
  <?php foreach ($entries as $e): ?>
    <form id="del_<?= (int)$e['id'] ?>" method="post" action="/voucher-entry?id=<?= (int)$v['id'] ?>"><input type="hidden" name="_do" value="del"><input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>"></form>
  <?php endforeach; ?>
  <p class="muted" style="margin-top:8px">KM auto-fills from what you last entered for that vendor (↺) and stays editable. Travel <?= e(cur_sym()) ?> = KM × your rate; the bottom row totals every column. Only the heads &amp; modes you're entitled to appear.</p>
<?php endif; ?>

<script>
  (function(){
    var t=document.getElementById('add_daytype'), o=document.getElementById('add_office'), l=document.getElementById('add_leave');
    if(t){ function sync(){ var v=t.value; o.style.display=(v==='OFFICE')?'':'none'; l.style.display=(v==='LEAVE')?'':'none'; } t.addEventListener('change', sync); sync(); }

    var RATES = <?= json_encode($rates) ?>;
    var grid = document.getElementById('vgrid');
    if (!grid) return;
    function money(n){ return '<?= e(cur_sym()) ?>' + (Math.round(n*100)/100).toLocaleString('en-IN'); }
    function recalc(){
      var totTravel=0, totGrand=0, totHead={};
      grid.querySelectorAll('tr[data-eid]').forEach(function(tr){
        var km = parseFloat(tr.querySelector('.v-km') ? tr.querySelector('.v-km').value : 0) || 0;
        var mode = tr.querySelector('.v-mode') ? tr.querySelector('.v-mode').value : '';
        var travel = km * (RATES[mode] || 0);
        var row = travel;
        tr.querySelectorAll('.v-amt').forEach(function(inp){
          var val = parseFloat(inp.value)||0; row += val;
          totHead[inp.dataset.code] = (totHead[inp.dataset.code]||0) + val;
        });
        var tCell = tr.querySelector('.v-travel'); if (tCell) tCell.innerHTML = money(travel);
        var rCell = tr.querySelector('.v-rowtotal'); if (rCell) rCell.innerHTML = '<strong>'+money(row)+'</strong>';
        totTravel += travel; totGrand += row;
      });
      var tt=document.getElementById('tot-travel'); if(tt) tt.innerHTML='<strong>'+money(totTravel)+'</strong>';
      Object.keys(totHead).forEach(function(c){ var el=document.getElementById('tot-amt-'+c); if(el) el.innerHTML='<strong>'+money(totHead[c])+'</strong>'; });
      var g=document.getElementById('tot-grand'); if(g) g.innerHTML='<strong>'+money(totGrand)+'</strong>';
      var g2=document.getElementById('tot-grand2'); if(g2) g2.innerHTML='<strong>'+money(totGrand)+'</strong>';
    }
    grid.addEventListener('input', recalc); grid.addEventListener('change', recalc); recalc();
  })();
</script>
