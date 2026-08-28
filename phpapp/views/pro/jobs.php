<?php $rows = $rows ?? []; $applied = $applied ?? []; ?>
<h1>Open jobs</h1>
<p class="muted" style="margin:0 0 14px">Requirements posted right now. Apply to the ones that fit — you apply once per job.</p>
<?php if (!$rows): ?>
  <div class="card"><p class="muted" style="margin:0">No open jobs at the moment. Complete your <a href="/pro/profile">profile</a> so the right ones find you.</p></div>
<?php else: foreach ($rows as $r): $done = !empty($applied[(int)$r['id']]); ?>
  <div class="card">
    <div style="font-weight:600;font-size:17px"><?= e($r['title']) ?></div>
    <div class="muted" style="font-size:13px;margin:4px 0 8px">
      <?= e($r['ref_code']) ?>
      <?php if (!empty($r['location'])): ?> · <?= e($r['location']) ?><?php endif; ?>
      · <?= (int)$r['positions'] ?> position<?= (int)$r['positions']===1?'':'s' ?>
      <?php if (!empty($r['discipline_code'])): ?> · <?= e($r['discipline_code']) ?><?php endif; ?>
      <?php if (!empty($r['work_type'])): ?> · <?= e(str_replace('_',' ',$r['work_type'])) ?><?php endif; ?>
    </div>
    <?php if (($r['rate_min'] ?? 0) || ($r['rate_max'] ?? 0)): ?><div style="font-weight:600">₹<?= (int)$r['rate_min'] ?>–<?= (int)$r['rate_max'] ?> <?= e($r['rate_unit']) ?></div><?php endif; ?>
    <?php if (!empty($r['description'])): ?><p class="muted" style="font-size:13.5px;margin:8px 0 0;white-space:pre-line"><?= e($r['description']) ?></p><?php endif; ?>
    <?php if ($done): ?>
      <p style="color:var(--ok);font-weight:600;margin:10px 0 0">✓ You have applied</p>
    <?php else: ?>
      <form method="post" action="/pro/jobs" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
        <input type="hidden" name="requirement_id" value="<?= (int)$r['id'] ?>">
        <input type="number" name="proposed_rate" placeholder="Your rate ₹" style="width:140px">
        <button class="btn" type="submit">Apply</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; endif; ?>
