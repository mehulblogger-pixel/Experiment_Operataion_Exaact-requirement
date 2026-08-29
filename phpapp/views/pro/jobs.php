<?php $rows = $rows ?? []; $applied = $applied ?? []; $q = $q ?? ''; ?>
<h1>Open jobs</h1>
<p class="muted" style="margin:0 0 12px">Requirements posted right now. Apply to the ones that fit — you apply once per job.</p>
<form method="get" action="/pro/jobs" style="display:flex;gap:8px;margin-bottom:14px">
  <input name="q" value="<?= e($q) ?>" placeholder="Search title, skill, discipline or location…" style="flex:1">
  <button class="btn" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn sec" href="/pro/jobs" style="display:inline-flex;align-items:center">Clear</a><?php endif; ?>
</form>
<?php if ($q !== ''): ?><p class="muted" style="margin:-6px 0 12px;font-size:13px"><?= count($rows) ?> result<?= count($rows)===1?'':'s' ?> for "<?= e($q) ?>"</p><?php endif; ?>
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
      <form method="post" action="/pro/jobs" style="margin-top:10px">
        <input type="hidden" name="requirement_id" value="<?= (int)$r['id'] ?>">
        <textarea name="cover_note" placeholder="Add a short note — why you're a fit (optional)" style="width:100%;min-height:54px;resize:vertical;margin-bottom:8px"></textarea>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="number" name="proposed_rate" placeholder="Your rate ₹" style="width:140px">
          <button class="btn" type="submit">Apply</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; endif; ?>
