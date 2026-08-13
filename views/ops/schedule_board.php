<?php
  // Scheduling board — inspectors × days, capacity vs demand, and a "who's free"
  // suggestion. All read from the existing availability engine.
  $matrix = $board['matrix']; $days = $board['days']; $people = $board['people'];
  $tot = $capacity['totals']; $perFree = $capacity['per_day_free'];
  $fmtD = fn($s) => date('d M Y', strtotime($s));
  $isWeekend = fn($d) => (int)date('N', strtotime($d)) >= 6;
  $cell = function($st) {
      if ($st === 'ON_JOB')    return ['bg'=>'var(--accent,#2563eb)','fg'=>'#fff','lbl'=>''];
      if ($st === 'AVAILABLE') return ['bg'=>'rgba(22,163,74,.14)','fg'=>'inherit','lbl'=>'free'];
      if ($st === 'LEAVE')     return ['bg'=>'rgba(217,119,6,.18)','fg'=>'inherit','lbl'=>'leave'];
      return ['bg'=>'rgba(120,120,120,.15)','fg'=>'inherit','lbl'=>strtolower((string)$st)];
  };
  // Shift the visible window by a fortnight.
  $shift = fn($n) => '/schedule?from=' . date('Y-m-d', strtotime($from . ' ' . $n . ' days')) . '&to=' . date('Y-m-d', strtotime($to . ' ' . $n . ' days'));
  $spanDays = max(1, (strtotime($to) - strtotime($from)) / 86400 + 1);
?>
<div class="crumbs"><a href="/">Home</a> › Scheduling board</div>
<div class="master-head">
  <div><h1>Scheduling board</h1>
    <p class="sub" style="margin:2px 0 0">Who is where, who is free, and what still needs a person — for the fortnight.
      Read from the live availability engine; allocate as usual from the <?= e(function_exists('Tlp')?Tlp('call'):'call') ?>.</p></div>
  <div style="display:flex;gap:8px;align-items:center">
    <a class="btn secondary" href="<?= e($shift(-(int)$spanDays)) ?>">‹ Earlier</a>
    <a class="btn secondary" href="/schedule">Today</a>
    <a class="btn secondary" href="<?= e($shift((int)$spanDays)) ?>">Later ›</a>
  </div>
</div>

<!-- Capacity band -->
<div class="kpi-row" style="margin-top:18px">
  <div class="kpi"><span class="kic">👷</span><div class="k">People</div><div class="v"><?= (int)$capacity['people'] ?></div><div class="d"><?= e($fmtD($from)) ?> – <?= e($fmtD($to)) ?></div></div>
  <div class="kpi"><span class="kic">🟢</span><div class="k">Free inspector-days</div><div class="v"><?= (int)$tot['free'] ?></div><div class="d">of <?= (int)$tot['working'] ?> working days</div></div>
  <div class="kpi"><span class="kic">🔵</span><div class="k">On job</div><div class="v"><?= (int)$tot['on_job'] ?></div><div class="d">allocated days</div></div>
  <div class="kpi"><span class="kic">📊</span><div class="k">Utilisation</div><div class="v"><?= (int)$tot['utilisation'] ?>%</div><div class="d"><?= (int)count($unalloc) ? '<span class="down">'.count($unalloc).' unallocated '.e(function_exists('Tlp')?Tlp('call'):'calls').'</span>' : 'Nothing waiting' ?></div></div>
</div>

<!-- The board grid -->
<section class="card" style="margin-top:16px">
  <h2 style="margin:0 0 10px">Who is where</h2>
  <?php if (!$people): ?>
    <p class="muted" style="margin:0">No inspectors in your scope.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="tbl" style="min-width:640px;border-collapse:separate;border-spacing:0">
        <thead>
          <tr>
            <th style="position:sticky;left:0;background:var(--card,#fff);z-index:2;text-align:left;min-width:150px">Inspector</th>
            <?php foreach ($days as $d): ?>
              <th style="text-align:center;font-weight:600;padding:4px 6px;<?= $isWeekend($d)?'background:rgba(120,120,120,.08)':'' ?>">
                <div style="font-size:11px;color:var(--muted,#888)"><?= e(date('D', strtotime($d))) ?></div>
                <div style="font-variant-numeric:tabular-nums"><?= e(date('j', strtotime($d))) ?></div>
                <div style="font-size:10px;color:var(--muted,#888)"><?= e(date('M', strtotime($d))) ?></div>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($people as $p): $id = (int)$p['id']; ?>
          <tr>
            <td style="position:sticky;left:0;background:var(--card,#fff);z-index:1;white-space:nowrap">
              <b><?= e($p['name']) ?></b><?= !empty($p['emp_code']) ? ' <span class="muted" style="font-size:11px">'.e($p['emp_code']).'</span>' : '' ?>
            </td>
            <?php foreach ($days as $d): $st = $matrix[$id][$d] ?? 'AVAILABLE'; $c = $cell($st); $job = $board['busy'][$id][$d] ?? ''; ?>
              <td title="<?= e($p['name'].' · '.$fmtD($d).' · '.($job ?: strtolower($st))) ?>"
                  style="text-align:center;padding:3px 4px;background:<?= $c['bg'] ?>;color:<?= $c['fg'] ?>;font-size:10px;<?= $isWeekend($d)&&$st==='AVAILABLE'?'opacity:.55':'' ?>">
                <?php if ($job): ?><a href="/jobs" style="color:inherit;text-decoration:none;font-weight:600"><?= e($job) ?></a>
                <?php else: ?><?= e($c['lbl']) ?><?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td style="position:sticky;left:0;background:var(--card,#fff);font-size:11px;color:var(--muted,#888)">Free that day</td>
            <?php foreach ($days as $d): ?>
              <td style="text-align:center;font-size:11px;font-variant-numeric:tabular-nums;color:var(--muted,#888);<?= $isWeekend($d)?'background:rgba(120,120,120,.08)':'' ?>"><?= (int)($perFree[$d] ?? 0) ?></td>
            <?php endforeach; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="muted" style="font-size:12px;margin:8px 0 0">
      <span style="display:inline-block;width:10px;height:10px;background:rgba(22,163,74,.4);border-radius:2px"></span> free
      &nbsp;<span style="display:inline-block;width:10px;height:10px;background:var(--accent,#2563eb);border-radius:2px"></span> on job
      &nbsp;<span style="display:inline-block;width:10px;height:10px;background:rgba(217,119,6,.4);border-radius:2px"></span> leave / override
    </p>
  <?php endif; ?>
</section>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px" class="sched-two">
  <!-- Who's free (suggestion) -->
  <section class="card">
    <h2 style="margin:0 0 10px">Who’s free?</h2>
    <form method="get" action="/schedule" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
      <input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="to" value="<?= e($to) ?>">
      <?php if ($s_call): ?><input type="hidden" name="s_call" value="<?= (int)$s_call ?>"><?php endif; ?>
      <label>From <input type="date" name="s_from" value="<?= e($s_from) ?>" required></label>
      <label>To <input type="date" name="s_to" value="<?= e($s_to) ?>"></label>
      <?php if ($sbus): ?>
        <label><?= e(function_exists('Tl')?ucfirst(Tl('sbu')):'Business unit') ?>
          <select name="s_sbu"><option value="">Any</option>
            <?php foreach ($sbus as $k=>$v): ?><option value="<?= e($k) ?>" <?= $s_sbu===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></label>
      <?php endif; ?>
      <button class="btn" type="submit">Find</button>
    </form>
    <?php if ($suggest === null): ?>
      <p class="muted" style="margin:0">Pick a date (and optionally a discipline) to see who is free for the whole period, ranked by how long they stay free.</p>
    <?php elseif (!$suggest): ?>
      <p class="muted" style="margin:0">Nobody in scope is free for every day of that period<?= $s_sbu!==''?' in that discipline':'' ?>. Try a shorter run or a different date.</p>
    <?php else: ?>
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Inspector</th><th>Free run</th><th>Then</th><?php if ($canAllocate): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($suggest as $s): ?>
          <tr>
            <td><b><?= e($s['name']) ?></b><?= $s['sbu']?' <span class="muted" style="font-size:11px">'.e($s['sbu']).'</span>':'' ?></td>
            <td><span class="pill p-ok"><?= (int)$s['run'] ?> day<?= $s['run']==1?'':'s' ?></span><?= $s['until']?' <span class="muted" style="font-size:11px">to '.e($fmtD($s['until'])).'</span>':'' ?></td>
            <td class="muted" style="font-size:12px"><?= $s['next_busy'] ? 'busy '.e($fmtD($s['next_busy'])) : ($s['load']?'':'clear') ?></td>
            <?php if ($canAllocate): ?>
              <td><a class="btn sm secondary" href="<?= $s_call ? '/job-new?call_id='.(int)$s_call : '/calls' ?>">Allocate</a></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <!-- Unallocated demand -->
  <section class="card">
    <h2 style="margin:0 0 10px">Needs a person</h2>
    <?php if (!$unalloc): ?>
      <p class="muted" style="margin:0">Nothing waiting — every open <?= e(function_exists('Tlp')?Tlp('call'):'call') ?> has someone on it.</p>
    <?php else: ?>
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th><?= e(function_exists('THP')?THP('call'):'Call') ?></th><th>Client</th><th>Required</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($unalloc as $c): $req = $c['inspection_required_date'] ?? ''; ?>
          <tr>
            <td><a href="/call?id=<?= (int)$c['id'] ?>"><b><?= e($c['call_code']) ?></b></a><?= $c['sbu']?' <span class="muted" style="font-size:11px">'.e($c['sbu']).'</span>':'' ?></td>
            <td><?= e(($c['display_name'] ?? '')!==''?$c['display_name']:($c['legal_name'] ?? '')) ?: '—' ?></td>
            <td style="white-space:nowrap"><?= $req?e($fmtD($req)):'<span class="muted">—</span>' ?></td>
            <td><a class="btn sm" href="/schedule?from=<?= e($from) ?>&to=<?= e($to) ?>&s_from=<?= e($req?:date('Y-m-d')) ?>&s_to=<?= e($req?:'') ?>&s_sbu=<?= e($c['sbu']?:'') ?>&s_call=<?= (int)$c['id'] ?>#free">Who’s free?</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
<style>@media (max-width:820px){.sched-two{grid-template-columns:1fr!important}}</style>
