<?php
  // Inspector daily availability board (Coordinator / Operation Manager / Branch Manager).
  $opts = avail_status_options();
  $tonePill = ['ok'=>'p-ok','warn'=>'p-warn','bad'=>'p-bad','info'=>'p-info','mut'=>'p-mut'];
  $sum = ['total'=>count($rows),'AVAILABLE'=>0,'ON_JOB'=>0,'LEAVE'=>0,'OTHER'=>0];
  foreach ($rows as $r) { $s=$r['eff_status']; if($s==='AVAILABLE')$sum['AVAILABLE']++; elseif($s==='ON_JOB')$sum['ON_JOB']++; elseif($s==='LEAVE')$sum['LEAVE']++; else $sum['OTHER']++; }
  $offName = function($id){ return $id ? (ops_val("SELECT name FROM offices WHERE id=?", [$id]) ?: '—') : 'Unassigned'; };
  $isToday = ($day === date('Y-m-d'));
?>
<div class="master-head">
  <div><h1><?= e(TH('engineer')) ?> availability</h1>
    <p class="sub" style="margin:2px 0 0"><?= $isToday ? 'Today' : e(date('l, d M Y', strtotime($day))) ?> · <?= (int)$sum['total'] ?> inspector(s) in your scope</p></div>
  <form method="get" action="/availability" style="display:flex;gap:6px;align-items:center">
    <a class="btn small secondary" href="/availability?day=<?= e(date('Y-m-d', strtotime($day.' -1 day'))) ?>">‹</a>
    <input class="form-control" type="date" name="day" value="<?= e($day) ?>" onchange="this.form.submit()">
    <a class="btn small secondary" href="/availability?day=<?= e(date('Y-m-d', strtotime($day.' +1 day'))) ?>">›</a>
    <?php if (!$isToday): ?><a class="btn small" href="/availability">Today</a><?php endif; ?>
  </form>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Available (free)</div><div class="k-val up"><?= (int)$sum['AVAILABLE'] ?></div><div class="k-sub">ready to allocate</div></div>
  <div class="kpi"><div class="k-lab">On job</div><div class="k-val"><?= (int)$sum['ON_JOB'] ?></div><div class="k-sub">allocated today</div></div>
  <div class="kpi"><div class="k-lab">On leave</div><div class="k-val down"><?= (int)$sum['LEAVE'] ?></div><div class="k-sub">not available</div></div>
  <div class="kpi"><div class="k-lab">Training / other</div><div class="k-val"><?= (int)$sum['OTHER'] ?></div><div class="k-sub">office / travel / WFH</div></div>
</div>

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
      <thead><tr><th>Inspector</th><th>SBU</th><th>Today</th><th style="min-width:190px">Set status</th><th>Note / job</th></tr></thead>
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
