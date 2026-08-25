<?php
  $statusBadge = ['DRAFT'=>'AMBER','SUBMITTED'=>'AMBER','APPROVED'=>'GREEN','PAID'=>'GREEN'];
  $pillMap = ['DRAFT'=>['p-warn','Draft'],'SUBMITTED'=>['p-info','Submitted'],'APPROVED'=>['p-ok','Approved'],'PAID'=>['p-ok','Paid']];
  $vpill = $pillMap[$v['status']] ?? ['p-warn', ucfirst(strtolower($v['status']))];
  // group entries by date for per-day hour subtotals
  $byDate = [];
  foreach ($entries as $e) $byDate[$e['entry_date']][] = $e;
  ksort($byDate);
  $monthHours = 0;
  // headline figures for the KPI strip
  $vGrand = (float)($sum['grand'] ?? 0);
  $vBal = $vGrand - (float)$v['advance'] - (float)$v['office_incurred'];
  $vHours = 0; foreach ($entries as $e) $vHours += (float)$e['hours'];
  $vHoursFmt = rtrim(rtrim(number_format($vHours, 1, '.', ''), '0'), '.') ?: '0';
  $vDays = count($byDate);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/vouchers"><?= e(TP('voucher')) ?></a> › <?= e($v['month']) ?></div>
<div class="master-head">
  <div><h1>Statement of travelling expenses
      <span class="pill <?= $vpill[0] ?>" style="vertical-align:middle;font-size:12px"><?= e($vpill[1]) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><strong><?= e($v['inspector_name']) ?></strong><?= $v['emp_code']?' · '.e($v['emp_code']):'' ?> · Month <?= e($v['month']) ?> · <?= e(T("sbu")) ?> <?= e(lk_options_or('sbu',OPS_SBUS)[$v['sbu']] ?? $v['sbu'] ?: '—') ?></p></div>
  <a class="btn secondary" href="/vouchers">← Back</a>
</div>

<div class="kpi-row" style="margin:16px 0">
  <div class="kpi"><span class="kic"><?= e(cur_sym()) ?></span><div class="k">Grand total</div><div class="v"><?= fmoney_short($vGrand) ?></div><div class="d"><?= $vDays ?> day(s) · <?= e($vHoursFmt) ?> hrs</div></div>
  <div class="kpi"><span class="kic">💳</span><div class="k">Balance <?= $vBal < 0 ? '(recover)' : 'to pay' ?></div><div class="v"><?= fmoney_short(abs($vBal)) ?></div><div class="d">after advance &amp; office</div></div>
  <div class="kpi"><span class="kic">🧾</span><div class="k">Travel</div><div class="v"><?= fmoney_short($sum['travel']) ?></div><div class="d">km × your rate</div></div>
  <div class="kpi"><span class="kic">📌</span><div class="k">Status</div><div class="v" style="font-size:17px;margin-top:8px"><span class="pill <?= $vpill[0] ?>"><?= e($vpill[1]) ?></span></div><div class="d"><?= $v['approved_by'] ? 'by '.e($v['approved_by']) : 'not yet approved' ?></div></div>
</div>

<div data-tabs data-tabs-key="voucher" data-tabs-order="Entries,Summary">
<?php if ($canEdit): ?>
<?php // ---- Module 30: quick add one expense (amount + type + optional job + receipt) ---- ?>
<div class="panel" data-tab="Entries" id="quick-add" style="border-left:3px solid var(--accent,#2b6cff)">
  <h3 class="tab-sub" style="margin-top:0">Add an expense</h3>
  <p class="muted" style="margin:0 0 10px">Amount, what it was for, and a photo of the receipt — the date (<?= e(date('d M Y')) ?>), your name and the currency are filled in for you.</p>
  <form method="post" action="/voucher-quick-add" enctype="multipart/form-data" class="inline-add" style="align-items:flex-end">
    <input type="hidden" name="inspector_id" value="<?= (int)$v['inspector_id'] ?>">
    <input type="hidden" name="month" value="<?= e($v['month']) ?>">
    <div class="ff"><label>Amount (<?= e(cur_sym()) ?>) *</label><input class="form-control" style="width:120px" type="number" step="0.01" min="0" name="amount" required></div>
    <div class="ff"><label>What for *</label>
      <select class="form-control" name="head"><?php foreach ($heads as $h): ?><option value="<?= e($h['code']) ?>"><?= e($h['label'] ?? $h['code']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Date</label><input class="form-control" type="date" name="entry_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Against a <?= e(Tl('job')) ?> <span class="muted">— optional</span></label>
      <select class="form-control searchable" name="job_id"><option value="">— none —</option>
        <?php foreach (($qaJobs ?? []) as $jb): ?><option value="<?= (int)$jb['id'] ?>" <?= (int)($addJob ?? 0) === (int)$jb['id'] ? 'selected' : '' ?>><?= e($jb['job_code']) ?><?= $jb['client_name'] ? ' · ' . e($jb['client_name']) : '' ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Receipt photo <span class="muted">— optional</span></label><input class="form-control" type="file" name="receipt" accept="image/*,.pdf" capture="environment"></div>
    <div class="ff ff-wide"><label>Note <span class="muted">— optional</span></label><input class="form-control" name="note" placeholder="e.g. taxi from station to plant"></div>
    <button class="btn" type="submit">Add expense</button>
  </form>
</div>
<div class="panel" data-tab="Entries" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
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
    <div class="ff"><label>Hours</label><input class="form-control" style="width:90px" type="number" step="0.25" name="hours" value="0"></div>
    <button class="btn small" type="submit">Add day</button>
  </form>
</div>
<?php endif; ?>

<?php
  $fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.') ?: '0';
  // running column totals
  $tTravel = 0; $tHead = []; foreach ($heads as $h) $tHead[$h['code']] = 0; $grand = 0;
  $ncol = 8 + count($heads) + ($canEdit ? 2 : 1); // Date,Site,File,Line,Hrs,Mode,KM,Travel + heads + Row(+✕)
?>
<?php if ($canEdit): ?><form method="post" action="/voucher-save?id=<?= (int)$v['id'] ?>" id="vform"><?php endif; ?>
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
      <td data-label="Hours"><input form="vform" class="form-control v-hours" style="width:64px" type="number" step="0.25" name="<?= $P ?>[hours]" value="<?= e($fmt($e['hours'])) ?>"></td>
      <td data-label="Mode"><select form="vform" class="form-control v-mode" style="width:110px" name="<?= $P ?>[mode]"><option value="">—</option>
        <?php foreach ($modes as $m): ?><option value="<?= e($m['code']) ?>" <?= $modeVal===$m['code']?'selected':'' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select></td>
      <td data-label="KM"><input form="vform" class="form-control v-km" style="width:70px" type="number" step="0.1" name="<?= $P ?>[km]" value="<?= e($kmVal!==''?$fmt($kmVal):'') ?>"></td>
      <td class="v-travel" data-label="Travel <?= e(cur_sym()) ?>" data-eid="<?= $eid ?>"><?= e(cur_sym()) ?><?= $fmt($e['travel_amount']) ?></td>
      <?php foreach ($heads as $h): ?>
        <td data-label="<?= e($h['label']) ?>"><input form="vform" class="form-control v-amt" data-code="<?= e($h['code']) ?>" style="width:80px" type="number" step="0.01" name="<?= $P ?>[amt][<?= e($h['code']) ?>]" value="<?= e(isset($amt[$h['code']])?$fmt($amt[$h['code']]):'') ?>" <?= $h['head_type']==='BILL'?'title="actual bill"':'' ?>></td>
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

<div class="panel-split" data-tab="Summary" style="margin-top:16px">
  <div class="panel">
    <h3 class="tab-sub">Summary — particulars</h3>
    <table class="grid">
      <tr><th>Particular</th><th style="text-align:right">Amount</th></tr>
      <tr><td>Travel charges (KM × rate)</td><td style="text-align:right"><?= e(cur_sym()) ?><?= number_format($sum['travel'],0) ?></td></tr>
      <?php foreach ($sum['heads'] as $code=>$amt): if ($amt==0) continue; ?>
        <tr><td><?= e($headLabels[$code] ?? $code) ?></td><td style="text-align:right"><?= e(cur_sym()) ?><?= number_format($amt,0) ?></td></tr>
      <?php endforeach; ?>
      <tr style="background:var(--soft)"><td><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= e(cur_sym()) ?><?= number_format($sum['grand'],0) ?></strong></td></tr>
      <tr><td>Less: Advance</td><td style="text-align:right"><?= e(cur_sym()) ?><?= number_format((float)$v['advance'],0) ?></td></tr>
      <tr><td>Less: Expenses incurred by Office</td><td style="text-align:right"><?= e(cur_sym()) ?><?= number_format((float)$v['office_incurred'],0) ?></td></tr>
      <?php $bal = $sum['grand'] - (float)$v['advance'] - (float)$v['office_incurred']; ?>
      <tr style="background:var(--soft)"><td><strong>Balance to be paid / (recovered)</strong></td><td style="text-align:right"><strong><?= e(cur_sym()) ?><?= number_format($bal,0) ?></strong></td></tr>
    </table>
    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn secondary" href="/voucher-print?id=<?= (int)$v['id'] ?>" target="_blank">🖨 Print / Save PDF</a>
      <a class="btn secondary" href="/voucher-csv?id=<?= (int)$v['id'] ?>">⬇ Download (Excel/CSV)</a>
    </div>
    <p class="muted" style="margin:6px 2px 0;font-size:12px">Print → "Save as PDF" for the signed statement; Download for the spreadsheet accounts can file.</p>
  </div>

  <div class="panel">
    <h3 class="tab-sub">Voucher details &amp; approval</h3>
    <?php if ($canEdit): ?>
    <form method="post" action="/voucher-header?id=<?= (int)$v['id'] ?>" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="ff"><label>Nature of spend</label>
          <select class="form-control" name="nature"><option value="">—</option><?php foreach ($natureOpts as $k=>$vv): ?><option value="<?= e($k) ?>" <?= $v['nature']===$k?'selected':'' ?>><?= e($vv) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Less Advance (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="advance" value="<?= e($v['advance']) ?>"></div>
        <div class="ff"><label>Less Office-incurred (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="office_incurred" value="<?= e($v['office_incurred']) ?>"></div>
        <div class="ff ff-wide"><label>Supporting file — one file for all bills (PDF/JPG, max 6 MB)</label><input class="form-control" type="file" name="support"></div>
      </div>
      <div style="margin-top:8px"><button class="btn small" type="submit">Save details / upload</button></div>
    </form>
    <?php else: ?>
      <div class="kv-grid">
        <div><span class="k">Nature</span><span class="v"><?= e($natureOpts[$v['nature']] ?? $v['nature'] ?: '—') ?></span></div>
        <div><span class="k">Advance</span><span class="v"><?= e(cur_sym()) ?><?= number_format((float)$v['advance'],0) ?></span></div>
        <div><span class="k">Office-incurred</span><span class="v"><?= e(cur_sym()) ?><?= number_format((float)$v['office_incurred'],0) ?></span></div>
      </div>
    <?php endif; ?>
    <?php if ($v['supporting_file']): ?><p style="margin-top:8px">📎 Supporting: <a href="/voucher-file?id=<?= (int)$v['id'] ?>" target="_blank"><?= e($v['supporting_name'] ?: 'file') ?></a></p><?php endif; ?>

    <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
    <p><strong>Status:</strong> <span class="badge <?= $statusBadge[$v['status']] ?? 'AMBER' ?>"><?= e($v['status']) ?></span>
      <?php if ($v['approved_by']): ?><span class="muted"> · approved by <?= e($v['approved_by']) ?></span><?php endif; ?></p>
    <div class="row-actions" style="flex-wrap:wrap;gap:6px">
      <?php if ($v['status']==='DRAFT' && ($canApprove || voucher_owner_is_me($v))): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="submit"><button class="btn" type="submit">Submit for approval</button></form>
      <?php endif; ?>
      <?php if ($v['status']==='SUBMITTED' && ($canApproveThis ?? $canApprove)): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
          <input type="hidden" name="action" value="approve">
          <div class="ff"><label>Checked by</label><input class="form-control" name="checked_by"></div>
          <div class="ff"><label>Approved by</label><input class="form-control" name="approved_by" value="<?= e(user_name(current_user())) ?>"></div>
          <div class="ff"><label>Authorized by</label><input class="form-control" name="authorized_by"></div>
          <button class="btn" type="submit">Approve</button>
        </form>
      <?php elseif ($v['status']==='SUBMITTED' && $canApprove && !($canApproveThis ?? true)): ?>
        <p class="muted" style="margin:0;font-size:12.5px">Awaiting approval — it must be approved by someone other than
          <?= voucher_owner_is_me($v) ? 'you (the claimant)' : 'the person who submitted it' ?> (maker&nbsp;≠&nbsp;checker).</p>
      <?php endif; ?>
      <?php if ($v['status']==='APPROVED' && $canApprove): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="paid"><button class="btn" type="submit">Mark paid</button></form>
      <?php endif; ?>
      <?php if ($v['status']!=='DRAFT' && $canApprove): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" onsubmit="return confirm('Reopen for editing?')"><input type="hidden" name="action" value="reopen"><button class="btn secondary" type="submit">Reopen</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
</div><!-- /data-tabs (voucher) -->

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
