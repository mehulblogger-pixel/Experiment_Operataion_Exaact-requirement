<?php
  // Inspector daily availability board (Coordinator / Operation Manager / Branch Manager).
  $opts = avail_status_options();
  $tonePill = ['ok'=>'p-ok','warn'=>'p-warn','bad'=>'p-bad','info'=>'p-info','mut'=>'p-mut'];
  // §a — the six numbers a coordinator actually reads off this board.
  $sum = ['total'=>count($rows),'AVAILABLE'=>0,'ON_JOB'=>0,'LEAVE'=>0,'OFFICE'=>0,'TRAINING'=>0,'OTHER'=>0];
  foreach ($rows as $r) {
    $st = $r['eff_status'];
    if      ($st==='AVAILABLE') $sum['AVAILABLE']++;
    elseif  ($st==='ON_JOB')    $sum['ON_JOB']++;
    elseif  ($st==='LEAVE')     $sum['LEAVE']++;
    elseif  ($st==='OFFICE')    $sum['OFFICE']++;
    elseif  ($st==='TRAINING')  $sum['TRAINING']++;
    else                        $sum['OTHER']++;   // WFH / travel / half day
  }
  $offName = function($id){ return $id ? (ops_val("SELECT name FROM offices WHERE id=?", [$id]) ?: '—') : 'Unassigned'; };
  $isToday = ($day === date('Y-m-d'));
?>
<div class="master-head">
  <div><h1><?= e(TH('engineer')) ?> availability</h1>
    <p class="sub" style="margin:2px 0 0"><?= $isToday ? 'Today' : e(date('l, d M Y', strtotime($day))) ?> · <?= (int)$sum['total'] ?> <?= e(Tlp('engineer')) ?> shown</p></div>
  <form method="get" action="/availability" style="display:flex;gap:6px;align-items:center">
    <a class="btn small secondary" href="/availability?day=<?= e(date('Y-m-d', strtotime($day.' -1 day'))) ?>">‹</a>
    <input class="form-control" type="date" name="day" value="<?= e($day) ?>" onchange="this.form.submit()">
    <a class="btn small secondary" href="/availability?day=<?= e(date('Y-m-d', strtotime($day.' +1 day'))) ?>">›</a>
    <?php if (!$isToday): ?><a class="btn small" href="/availability">Today</a><?php endif; ?>
  </form>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Total resources</div><div class="k-val"><?= (int)$sum['total'] ?></div><div class="k-sub">active, in your scope</div></div>
  <div class="kpi"><div class="k-lab">Available</div><div class="k-val up"><?= (int)$sum['AVAILABLE'] ?></div><div class="k-sub">free to allocate</div></div>
  <div class="kpi"><div class="k-lab">On job</div><div class="k-val"><?= (int)$sum['ON_JOB'] ?></div><div class="k-sub">allocated</div></div>
  <div class="kpi"><div class="k-lab">On leave</div><div class="k-val down"><?= (int)$sum['LEAVE'] ?></div><div class="k-sub">not available</div></div>
  <div class="kpi"><div class="k-lab">In office</div><div class="k-val"><?= (int)$sum['OFFICE'] ?></div><div class="k-sub">at the desk</div></div>
  <div class="kpi"><div class="k-lab">Training / other</div><div class="k-val"><?= (int)$sum['TRAINING'] + (int)$sum['OTHER'] ?></div><div class="k-sub">training · travel · WFH · half day</div></div>
</div>

<form method="get" action="/availability" class="filter-bar">
  <input type="hidden" name="day" value="<?= e($day) ?>">
  <input class="form-control" type="text" name="q" value="<?= e($fq ?? '') ?>" placeholder="🔍 Name or code…" style="max-width:220px">
  <select class="form-control searchable" name="office" onchange="this.form.submit()">
    <option value="">Any <?= e(T('office')) ?></option>
    <?php foreach (($officeList ?? []) as $o): ?>
      <option value="<?= (int)$o['id'] ?>" <?= ((int)($fOffice ?? 0)===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control searchable" name="sbu" onchange="this.form.submit()">
    <option value="">Any <?= e(T('sbu')) ?></option>
    <?php foreach (($sbuOpts ?? []) as $k=>$v): ?>
      <option value="<?= e($k) ?>" <?= (($fSbu ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">Any status</option>
    <?php foreach ($opts as $k=>$v): ?>
      <option value="<?= e($k) ?>" <?= (($fStatus ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <input class="form-control" type="month" name="month" value="<?= e($month ?? '') ?>" title="Show the whole month" style="max-width:170px">
  <button class="btn secondary" type="submit">Apply</button>
  <?php if (($fq ?? '')!=='' || !empty($fOffice) || ($fSbu ?? '')!=='' || ($fStatus ?? '')!=='' || ($month ?? '')!==''): ?>
    <a class="btn secondary" href="/availability?day=<?= e($day) ?>">Clear</a>
  <?php endif; ?>
</form>

<div class="panel-split" style="align-items:start">
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Free to allocate <span class="muted">— free today <em>and</em> tomorrow (<?= e(fdate($tomorrow)) ?>)</span></h3>
    <?php if (!$freeBoth): ?>
      <p class="muted">Nobody is free across both days. Widen the filters, or use the date check on the right to find the next opening.</p>
    <?php else: ?>
      <table class="dt">
        <thead><tr><th><?= e(T('engineer')) ?></th><th><?= e(T('sbu')) ?></th><th><?= e(T('office')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($freeBoth as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong><?= $r['emp_code'] ? ' <span class="muted">'.e($r['emp_code']).'</span>' : '' ?></td>
            <td class="muted"><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?? '—') ?></td>
            <td class="muted"><?= e($offName((int)($r['home_office_id'] ?? 0))) ?></td>
            <td class="num"><span class="pill p-ok">free 2 days</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="margin-top:6px"><?= count($freeBoth) ?> of <?= (int)$sum['total'] ?> can take work starting today or tomorrow.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Who is free on a date <span class="muted">— and for how long</span></h3>
    <form method="get" action="/availability" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="day" value="<?= e($day) ?>">
      <?php foreach (['q'=>$fq ?? '','office'=>$fOffice ?? '','sbu'=>$fSbu ?? ''] as $k=>$v): ?>
        <?php if ($v !== '' && $v !== 0): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <div class="ff" style="margin:0"><label>From</label>
        <input class="form-control" type="date" name="check_from" value="<?= e($chkFrom ?: $day) ?>"></div>
      <div class="ff" style="margin:0"><label>Days needed</label>
        <input class="form-control" type="number" min="1" max="30" name="check_days" value="<?= (int)($chkDays ?: 1) ?>" style="width:110px"></div>
      <button class="btn small" type="submit">Check</button>
    </form>

    <?php if ($check): ?>
      <p class="sub" style="margin:10px 0 6px">
        <b><?= (int)$check['fit'] ?></b> can cover <b><?= (int)$check['days'] ?> day(s)</b> from <b><?= e(fdate($check['from'])) ?></b>.
      </p>
      <table class="dt">
        <thead><tr><th><?= e(T('engineer')) ?></th><th>From <?= e(fdate($check['from'])) ?></th><th>Free until</th><th>Then</th></tr></thead>
        <tbody>
        <?php foreach ($check['rows'] as $r): $w = $r['win']; ?>
          <tr<?= $r['covers'] ? ' style="background:color-mix(in srgb,var(--ok) 7%,transparent)"' : '' ?>>
            <td><strong><?= e($r['name']) ?></strong><?= $r['emp_code'] ? ' <span class="muted">'.e($r['emp_code']).'</span>' : '' ?></td>
            <td>
              <?php if (!$w['free']): ?><span class="pill p-bad"><?= e(avail_label($w['next_reason'] ?: 'ON_JOB')) ?></span>
              <?php else: ?><span class="pill <?= $r['covers'] ? 'p-ok' : 'p-warn' ?>"><?= (int)$w['run'] ?> day(s) free</span><?php endif; ?>
            </td>
            <td class="muted"><?= $w['free'] ? e(fdate($w['until'])) : '—' ?></td>
            <td class="muted"><?= $w['next_busy'] ? e(fdate($w['next_busy'])) . ' · ' . e(avail_label($w['next_reason'])) : '<span class="muted">nothing booked</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="margin-top:6px">Green rows are free for the whole period asked for. Looks ahead 45 days.</p>
    <?php else: ?>
      <p class="muted" style="margin-top:8px">Pick a date and how many days the <?= e(Tl('call')) ?> needs. You will get everyone who is free from that date, how long each stays free, and what they are on next.</p>
    <?php endif; ?>
  </div>
</div>

<?php if ($grid && $grid['people']): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:10px 14px 0"><h3><?= e(date('F Y', strtotime($grid['month'] . '-01'))) ?> at a glance</h3></div>
  <div class="tbl-scroll" style="overflow-x:auto">
    <table class="dt" style="font-size:12px">
      <thead><tr><th style="position:sticky;left:0;background:var(--card);min-width:170px"><?= e(T('engineer')) ?></th>
        <?php foreach ($grid['days'] as $d): $wd=(int)date('N', strtotime($d)); ?>
          <th class="num" style="padding:6px 4px<?= $wd>=6?';color:var(--muted)':'' ?>"><?= (int)date('j', strtotime($d)) ?><br><span style="font-weight:400"><?= e(substr(date('D', strtotime($d)),0,1)) ?></span></th>
        <?php endforeach; ?>
        <th class="num">Free</th></tr></thead>
      <tbody>
      <?php foreach ($grid['people'] as $p): $pid=(int)$p['id']; $freeN=0; ?>
        <tr>
          <td style="position:sticky;left:0;background:var(--card)"><strong><?= e($p['name']) ?></strong></td>
          <?php foreach ($grid['days'] as $d):
            $st = $grid['matrix'][$pid][$d] ?? 'AVAILABLE';
            if ($st === 'AVAILABLE') $freeN++;
            $bg = $st === 'AVAILABLE' ? 'var(--ok)' : ($st === 'ON_JOB' ? 'var(--brand)' : ($st === 'LEAVE' ? 'var(--bad)' : 'var(--warn)'));
            $op = $st === 'AVAILABLE' ? '18%' : '55%';
          ?>
            <td class="num" title="<?= e(fdate($d)) ?> · <?= e(avail_label($st)) ?>"
                style="padding:4px 3px;background:color-mix(in srgb,<?= $bg ?> <?= $op ?>,transparent)"></td>
          <?php endforeach; ?>
          <td class="num"><b><?= $freeN ?></b></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted" style="padding:8px 14px">Green = free · blue = on a <?= e(Tl('job')) ?> · red = leave · amber = office, training, travel or WFH. Hover any cell for the date and status.</p>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="panel"><p class="muted">No active inspectors are posted to your office(s) yet. Set an inspector's <strong>posted office</strong> on the Inspector master so they appear here.</p></div>
<?php else: ?>
  <?php
    // group by office
    $byOff = [];
    foreach ($rows as $r) $byOff[(int)($r['home_office_id'] ?? 0)][] = $r;
    ksort($byOff);
  ?>
  <?php foreach ($byOff as $oid => $list): ?>
  <div class="panel" style="padding:0;overflow:hidden;margin-bottom:14px">
    <div class="ctitle" style="padding:10px 14px 0"><h3><?= e($offName($oid)) ?> <span class="muted">(<?= count($list) ?>)</span></h3></div>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="dt av-tbl">
      <thead><tr><th><?= e(T('engineer')) ?></th><th><?= e(T('sbu')) ?></th><th><?= $isToday ? 'Today' : e(fdate($day)) ?></th><th style="min-width:190px">Set status</th><th>Note / <?= e(Tl('job')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($list as $r): $id=(int)$r['id']; $eff=$r['eff_status']; $tone=avail_tone($eff); ?>
        <tr id="av-row-<?= $id ?>">
          <td><strong><?= e($r['name']) ?></strong><?= $r['emp_code'] ? ' <span class="muted">'.e($r['emp_code']).'</span>' : '' ?></td>
          <td class="muted"><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?? '') ?></td>
          <td><span class="pill <?= $tonePill[$tone] ?? 'p-mut' ?>" id="av-pill-<?= $id ?>"><?= e(avail_label($eff)) ?></span></td>
          <td>
            <select class="form-control av-sel" data-id="<?= $id ?>" <?= $eff==='ON_JOB' && !$r['manual'] ? 'title="Auto: on a job today"' : '' ?>>
              <?php foreach ($opts as $k=>$lbl): if ($k==='ON_JOB' && !$r['manual']) continue; ?>
                <option value="<?= e($k) ?>" <?= $eff===$k?'selected':'' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="muted">
            <?php if ($r['job_codes']): ?><span class="pill p-info">📋 <?= e(implode(', ', array_slice($r['job_codes'],0,3))) ?></span> <?php endif; ?>
            <span id="av-note-<?= $id ?>"><?= e($r['note']) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<form id="av-post" method="post" action="/availability?day=<?= e($day) ?>" style="display:none">
  <input type="hidden" name="inspector_id" id="av-post-id">
  <input type="hidden" name="status" id="av-post-status">
  <input type="hidden" name="note" id="av-post-note">
</form>

<script>
(function(){
  var day = <?= json_encode($day) ?>;
  var labels = <?= json_encode($opts) ?>;
  var tones = <?= json_encode(['AVAILABLE'=>'p-ok','ON_JOB'=>'p-info','LEAVE'=>'p-bad','HALF_DAY'=>'p-warn','TRAINING'=>'p-warn','WFH'=>'p-warn','TRAVEL'=>'p-warn','OFFICE'=>'p-warn']) ?>;
  document.querySelectorAll('.av-sel').forEach(function(sel){
    sel.addEventListener('change', function(){
      var id = this.getAttribute('data-id'), status = this.value;
      var note = '';
      if (['LEAVE','TRAINING','WFH','TRAVEL','HALF_DAY','OFFICE'].indexOf(status) >= 0) {
        note = prompt('Note for this status (optional):', document.getElementById('av-note-'+id).textContent.trim()) || '';
      }
      var body = new URLSearchParams({inspector_id:id, status:status, note:note, ajax:'1', day:day});
      fetch('/availability?day='+encodeURIComponent(day), {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString()})
        .then(function(r){ return r.json(); })
        .then(function(d){
          var pill = document.getElementById('av-pill-'+id);
          pill.textContent = d.label || labels[status] || status;
          pill.className = 'pill ' + (tones[status] || 'p-mut');
          document.getElementById('av-note-'+id).textContent = note;
        })
        .catch(function(){ /* fall back to full submit */
          document.getElementById('av-post-id').value = id;
          document.getElementById('av-post-status').value = status;
          document.getElementById('av-post-note').value = note;
          document.getElementById('av-post').submit();
        });
    });
  });
})();
</script>
