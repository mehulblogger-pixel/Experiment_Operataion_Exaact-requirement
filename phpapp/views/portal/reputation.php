<?php
  // Our reputation as a client — how professionals rated being paid and treated, and a
  // way to report a rating we believe is unfair. This is what a freelancer sees before
  // accepting our jobs, so it matters.
  $summary = $summary ?? []; $ratings = $ratings ?? []; $payLabels = $payLabels ?? []; $categories = $categories ?? [];
  $pay = $summary['pay'] ?? []; $stars = fn($n) => str_repeat('★', max(0, min(5, (int)$n))) . str_repeat('☆', 5 - max(0, min(5, (int)$n)));
?>
<h2 class="ptitle">Our reputation</h2>
<p class="plead" style="margin:0 0 12px">Professionals rate their experience of working for you — including how you paid. This is visible to freelancers deciding whether to take your jobs, so keeping it strong helps you hire.</p>

<div class="pcard" style="max-width:760px;display:flex;gap:26px;flex-wrap:wrap;align-items:center">
  <div><div style="font-size:30px;font-weight:800;color:#0a5c5c"><?= $summary['avg_stars'] !== null ? number_format((float)$summary['avg_stars'], 1) : '—' ?></div>
    <div class="muted" style="font-size:12px"><?= (int)($summary['count'] ?? 0) ?> rating<?= (int)($summary['count'] ?? 0) === 1 ? '' : 's' ?></div></div>
  <?php if (($summary['paid_fair_pct'] ?? null) !== null): ?>
    <div><div style="font-size:22px;font-weight:700"><?= (int)$summary['paid_fair_pct'] ?>%</div><div class="muted" style="font-size:12px">paid on time</div></div>
  <?php endif; ?>
  <?php if (($summary['rehire_pct'] ?? null) !== null): ?>
    <div><div style="font-size:22px;font-weight:700"><?= (int)$summary['rehire_pct'] ?>%</div><div class="muted" style="font-size:12px">would work again</div></div>
  <?php endif; ?>
  <?php if (array_sum($pay) > 0): ?>
    <div style="font-size:12.5px;color:var(--muted)">
      <?php foreach ($payLabels as $k => $lbl): if ((int)($pay[$k] ?? 0) > 0): ?><div><?= e($lbl) ?>: <b style="color:var(--ink)"><?= (int)$pay[$k] ?></b></div><?php endif; endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$ratings): ?>
  <div class="pcard" style="max-width:760px"><p class="muted" style="margin:0">No ratings yet. Professionals rate you after a completed engagement.</p></div>
<?php else: ?>
  <?php foreach ($ratings as $r): ?>
    <div class="pcard" style="max-width:760px;<?= !empty($r['hidden']) ? 'opacity:.6' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
        <div><span style="color:#e0a100;font-size:16px"><?= $stars($r['stars']) ?></span>
          <?php if (!empty($r['payment_status'])): ?><span class="ppill" style="font-size:11px"><?= e($payLabels[$r['payment_status']] ?? $r['payment_status']) ?></span><?php endif; ?>
          <?php if (!empty($r['hidden'])): ?><span class="ppill" style="font-size:11px;color:#9a2a2a">removed from scores</span><?php endif; ?></div>
        <span class="muted" style="font-size:12px"><?= e(substr((string)$r['created_at'], 0, 10)) ?></span>
      </div>
      <?php if (!empty($r['comment'])): ?><p style="margin:8px 0 0;font-size:14px">“<?= e($r['comment']) ?>”</p><?php endif; ?>
      <?php if (!empty($r['moderation_note'])): ?><p class="muted" style="margin:6px 0 0;font-size:12.5px;color:#0a5c5c">Moderator note: <?= e($r['moderation_note']) ?></p><?php endif; ?>
      <?php if (empty($r['hidden'])): ?>
        <?php if (!empty($r['_disputed'])): ?>
          <p class="muted" style="margin:8px 0 0;font-size:12px">⏳ You’ve reported this rating — under review.</p>
        <?php else: ?>
          <details style="margin-top:10px">
            <summary style="cursor:pointer;font-size:13px;color:var(--muted)">Report this rating</summary>
            <form method="post" action="/portal/reputation" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
              <input type="hidden" name="action" value="report_rating"><input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
              <select name="category" class="form-control" style="width:auto"><?php foreach ($categories as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select>
              <input name="detail" class="form-control" placeholder="What’s wrong with it?" style="flex:1;min-width:200px">
              <button class="btn sec" type="submit">Submit report</button>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
