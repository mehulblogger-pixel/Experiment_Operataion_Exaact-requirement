<?php
// Connect K4 — the guided requirement builder. One question at a time, a clear
// progress meter, and a final Confirm panel (Confirm, not create). State is
// carried in hidden fields so Back/Next never loses an answer.
$step = $step ?? 1; $steps = $steps ?? []; $data = $data ?? [];
$disciplines = $disciplines ?? []; $sectors = $sectors ?? []; $certs = $certs ?? [];
$total = count($steps);
// Every field, so each step forwards the full state.
$hidden = function ($except = []) use ($data) {
    $h = '';
    foreach ($data as $k => $v) if (!in_array($k, $except, true)) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h;
};
$discName = '';
foreach ($disciplines as $d) if (($d['code'] ?? '') === $data['discipline_code']) $discName = $d['name'];
$secName = '';
foreach ($sectors as $s) if (($s['code'] ?? '') === $data['sector_code']) $secName = $s['name'];
?>
<style>
  .cwrap{max-width:620px}
  .cbar{display:flex;gap:6px;margin:14px 0 18px}
  .cbar div{flex:1;height:6px;border-radius:6px;background:var(--line,#e3ebea)}
  .cbar div.on{background:var(--teal,#0f7d7d)}
  .cq{font-size:22px;font-weight:600;letter-spacing:-.01em;margin:0 0 4px}
  .csub{color:var(--muted);margin:0 0 16px}
  .cchips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}
  .cchip{padding:9px 14px;border-radius:999px;border:1px solid var(--line,#dde3e2);cursor:pointer;font-size:15px;background:var(--card,#fff)}
  .cchip input{display:none}
  .cchip.sel,.cchip:has(input:checked){background:rgba(15,125,125,.1);border-color:var(--teal,#0f7d7d);font-weight:600}
  .cfield{width:100%;padding:12px;border:1px solid var(--line,#dde3e2);border-radius:11px;font-size:16px;margin-bottom:12px;background:var(--card,#fff);color:inherit}
  .cnav{display:flex;justify-content:space-between;gap:10px;margin-top:20px}
  .csummary{border:1px solid var(--line,#e3ebea);border-radius:14px;padding:16px;margin-bottom:14px}
  .csummary .row{display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid var(--line,#eee)}
  .csummary .row:last-child{border-bottom:0}
  .clab{color:var(--muted);font-size:13px}
  .ccerts .chip{display:inline-block;margin:3px;padding:6px 11px;border-radius:999px;background:rgba(201,162,39,.14);border:1px solid rgba(201,162,39,.4);font-size:13.5px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:520px){.grid2{grid-template-columns:1fr}}
</style>

<div class="crumbs"><a href="/">Home</a> › <a href="/connect-requirements">Marketplace</a> › Guided post</div>
<div class="cwrap">
  <div class="cbar"><?php for ($i = 1; $i <= $total; $i++): ?><div class="<?= $i <= $step ? 'on' : '' ?>"></div><?php endfor; ?></div>
  <div class="csub">Step <?= (int)$step ?> of <?= (int)$total ?></div>

  <form method="post" action="/connect-concierge">
    <input type="hidden" name="step" value="<?= (int)$step ?>">

    <?php if ($step === 1): ?>
      <?= $hidden(['title','discipline_code']) ?>
      <p class="cq">What do you need?</p>
      <p class="csub">One line — like you'd text a colleague.</p>
      <input class="cfield" name="title" value="<?= e($data['title']) ?>" autofocus placeholder="e.g. Welding inspector for a pressure-vessel FAT at Dahej">
      <p class="clab">Discipline</p>
      <div class="cchips">
        <?php foreach ($disciplines as $d): $sel = ($d['code'] ?? '') === $data['discipline_code']; ?>
          <label class="cchip <?= $sel ? 'sel' : '' ?>"><input type="radio" name="discipline_code" value="<?= e($d['code']) ?>" <?= $sel ? 'checked' : '' ?>><?= e($d['name']) ?></label>
        <?php endforeach; ?>
      </div>

    <?php elseif ($step === 2): ?>
      <?= $hidden(['sector_code','location','start_date','work_type']) ?>
      <p class="cq">Where and when?</p>
      <p class="clab">Sector</p>
      <div class="cchips">
        <?php foreach ($sectors as $s): $sel = ($s['code'] ?? '') === $data['sector_code']; ?>
          <label class="cchip <?= $sel ? 'sel' : '' ?>"><input type="radio" name="sector_code" value="<?= e($s['code']) ?>" <?= $sel ? 'checked' : '' ?>><?= e($s['name']) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="grid2" style="margin-top:12px">
        <div><p class="clab">Location</p><input class="cfield" name="location" value="<?= e($data['location']) ?>" placeholder="e.g. Dahej, Gujarat"></div>
        <div><p class="clab">Start date</p><input class="cfield" type="date" name="start_date" value="<?= e($data['start_date']) ?>"></div>
      </div>
      <p class="clab">Work type</p>
      <div class="cchips">
        <?php foreach (['per_visit'=>'Per visit','day_rate'=>'Day rate','short_project'=>'Short project','long_deployment'=>'Long deployment','shutdown'=>'Shutdown'] as $k=>$v): $sel=$data['work_type']===$k; ?>
          <label class="cchip <?= $sel?'sel':'' ?>"><input type="radio" name="work_type" value="<?= $k ?>" <?= $sel?'checked':'' ?>><?= e($v) ?></label>
        <?php endforeach; ?>
      </div>

    <?php elseif ($step === 3): ?>
      <?= $hidden(['positions','rate_min','rate_max']) ?>
      <p class="cq">How many, and budget?</p>
      <div class="grid2">
        <div><p class="clab">Positions</p><input class="cfield" type="number" min="1" name="positions" value="<?= (int)$data['positions'] ?>"></div>
        <div></div>
        <div><p class="clab">Rate from (₹)</p><input class="cfield" type="number" min="0" name="rate_min" value="<?= $data['rate_min']?e((int)$data['rate_min']):'' ?>"></div>
        <div><p class="clab">Rate to (₹)</p><input class="cfield" type="number" min="0" name="rate_max" value="<?= $data['rate_max']?e((int)$data['rate_max']):'' ?>"></div>
      </div>

    <?php elseif ($step === 4): ?>
      <?= $hidden(['description']) ?>
      <p class="cq">Anything else?</p>
      <p class="csub">Standards, stage, material, site access — whatever helps a professional judge the fit.</p>
      <textarea class="cfield" name="description" rows="4" placeholder="e.g. Final inspection + hydro witness on 3 SA 516 Gr 70 vessels; WPS/PQR review; PWHT chart check."><?= e($data['description']) ?></textarea>

    <?php else: /* step 5 — confirm */ ?>
      <?= $hidden() ?>
      <p class="cq">Confirm and post</p>
      <p class="csub">Here's what we'll post. Change anything with Back.</p>
      <div class="csummary">
        <div class="row"><span class="clab">What</span><strong><?= e($data['title'] ?: '—') ?></strong></div>
        <?php if ($discName): ?><div class="row"><span class="clab">Discipline</span><span><?= e($discName) ?></span></div><?php endif; ?>
        <?php if ($secName): ?><div class="row"><span class="clab">Sector</span><span><?= e($secName) ?></span></div><?php endif; ?>
        <?php if ($data['location']): ?><div class="row"><span class="clab">Location</span><span><?= e($data['location']) ?></span></div><?php endif; ?>
        <?php if ($data['start_date']): ?><div class="row"><span class="clab">Start</span><span><?= e($data['start_date']) ?></span></div><?php endif; ?>
        <div class="row"><span class="clab">Positions</span><span><?= (int)$data['positions'] ?></span></div>
        <?php if ($data['rate_min'] || $data['rate_max']): ?><div class="row"><span class="clab">Rate</span><span>₹<?= (int)$data['rate_min'] ?>–<?= (int)$data['rate_max'] ?></span></div><?php endif; ?>
      </div>
      <?php if ($certs): ?>
      <div class="csummary ccerts">
        <div class="clab" style="margin-bottom:6px">Certifications this usually needs — we'll note them on the post</div>
        <?php foreach ($certs as $c): ?><span class="chip"><?= e($c) ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="cnav">
      <?php if ($step > 1): ?><button class="btn secondary" type="submit" name="nav" value="back">← Back</button><?php else: ?><a class="btn secondary" href="/connect-requirements">Cancel</a><?php endif; ?>
      <?php if ($step < $total): ?>
        <button class="btn" type="submit" name="nav" value="next">Next →</button>
      <?php else: ?>
        <button class="btn" type="submit" name="nav" value="post">Post it</button>
      <?php endif; ?>
    </div>
  </form>
</div>
