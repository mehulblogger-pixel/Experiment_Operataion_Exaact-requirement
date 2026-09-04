<?php $rows = $rows ?? []; $applied = $applied ?? []; $q = $q ?? ''; $showAll = !empty($showAll); ?>
<h1><?= $showAll ? 'All open jobs' : 'Jobs matched to you' ?></h1>
<p class="muted" style="margin:0 0 12px">
  <?php if ($showAll): ?>Every open requirement right now.<?php else: ?>Automatically filtered to your discipline &amp; skills — the work that fits your profile.<?php endif; ?>
  Apply to the ones that fit — you apply once per job.
</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
  <a class="btn <?= $showAll ? 'sec' : '' ?>" href="/pro/jobs" style="text-decoration:none">🎯 Matched to me</a>
  <a class="btn <?= $showAll ? '' : 'sec' ?>" href="/pro/jobs?all=1" style="text-decoration:none">All open jobs</a>
</div>
<form method="get" action="/pro/jobs" style="display:flex;gap:8px;margin-bottom:14px">
  <?php if ($showAll): ?><input type="hidden" name="all" value="1"><?php endif; ?>
  <input name="q" value="<?= e($q) ?>" placeholder="Search title, skill, discipline or location…" style="flex:1">
  <button class="btn" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn sec" href="/pro/jobs<?= $showAll ? '?all=1' : '' ?>" style="display:inline-flex;align-items:center">Clear</a><?php endif; ?>
</form>
<?php if ($q !== ''): ?><p class="muted" style="margin:-6px 0 12px;font-size:13px"><?= count($rows) ?> result<?= count($rows)===1?'':'s' ?> for "<?= e($q) ?>"</p><?php endif; ?>
<?php if (!$rows): ?>
  <div class="card"><p class="muted" style="margin:0">
    <?php if (!$showAll && $q === ''): ?>Nothing matches your profile right now. <a href="/pro/jobs?all=1">See all open jobs →</a> or complete your <a href="/pro/profile">profile</a> so the right ones find you.
    <?php else: ?>No open jobs at the moment. Complete your <a href="/pro/profile">profile</a> so the right ones find you.<?php endif; ?>
  </p></div>
<?php else: foreach ($rows as $r): $done = !empty($applied[(int)$r['id']]); ?>
  <div class="card">
    <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
      <div style="font-weight:600;font-size:17px;flex:1"><?= e($r['title']) ?></div>
      <?php if (!$showAll && !empty($r['_reason'])): ?><span class="chip" style="border-color:var(--teal);color:var(--teal);font-size:11.5px">🎯 <?= e($r['_reason']) ?></span><?php endif; ?>
    </div>
    <div class="muted" style="font-size:13px;margin:4px 0 8px">
      <?= e($r['ref_code']) ?>
      <?php if (!empty($r['location'])): ?> · <?= e($r['location']) ?><?php endif; ?>
      · <?= (int)$r['positions'] ?> position<?= (int)$r['positions']===1?'':'s' ?>
      <?php if (!empty($r['discipline_code'])): ?> · <?= e($r['discipline_code']) ?><?php endif; ?>
      <?php if (!empty($r['work_type'])): ?> · <?= e(str_replace('_',' ',$r['work_type'])) ?><?php endif; ?>
    </div>
    <?php if (($r['rate_min'] ?? 0) || ($r['rate_max'] ?? 0)): ?><div style="font-weight:600">₹<?= (int)$r['rate_min'] ?>–<?= (int)$r['rate_max'] ?> <?= e($r['rate_unit']) ?></div><?php endif; ?>
    <?php // Client payer reputation — so you know how this client pays before you apply.
      if (!empty($r['poster_party_id']) && function_exists('cx_rating_summary_for_client')):
        $crep = cx_rating_summary_for_client((int)$r['poster_party_id']);
        if (($crep['count'] ?? 0) > 0): ?>
      <div style="margin-top:6px;font-size:12.5px;color:var(--muted)">Client:
        <b style="color:var(--ink)"><?= number_format((float)$crep['avg_stars'], 1) ?>★</b>
        <?php if (($crep['paid_fair_pct'] ?? null) !== null): ?> · <b style="color:<?= (int)$crep['paid_fair_pct'] >= 70 ? 'var(--teal)' : '#8a5a00' ?>"><?= (int)$crep['paid_fair_pct'] ?>% pay on time</b><?php endif; ?>
        <span class="muted">(<?= (int)$crep['count'] ?> review<?= (int)$crep['count']===1?'':'s' ?>)</span>
      </div>
    <?php endif; endif; ?>
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
