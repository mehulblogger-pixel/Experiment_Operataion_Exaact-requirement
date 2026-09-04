<?php
  // My reputation — the ratings clients gave me, and a way to report one I believe is
  // unfair. Genuine reviews build trust; a wrong one can be investigated, not silently left.
  $me = $me ?? []; $summary = $summary ?? []; $ratings = $ratings ?? []; $categories = $categories ?? [];
  $stars = fn($n) => str_repeat('★', max(0, min(5, (int)$n))) . str_repeat('☆', 5 - max(0, min(5, (int)$n)));
?>
<h1>My reputation</h1>
<p class="muted" style="margin:0 0 14px">How clients have rated your work. If a rating is unfair, factually wrong or retaliatory, report it — our moderation desk will investigate.</p>

<div class="card" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
  <div>
    <div style="font-size:30px;font-weight:800;color:var(--teal)"><?= $summary['avg_stars'] !== null ? number_format((float)$summary['avg_stars'], 1) : '—' ?></div>
    <div class="muted" style="font-size:12px"><?= (int)($summary['count'] ?? 0) ?> rating<?= (int)($summary['count'] ?? 0) === 1 ? '' : 's' ?></div>
  </div>
  <?php if (($summary['rehire_pct'] ?? null) !== null): ?>
    <div><div style="font-size:22px;font-weight:700"><?= (int)$summary['rehire_pct'] ?>%</div><div class="muted" style="font-size:12px">would hire again</div></div>
  <?php endif; ?>
</div>

<?php if (!$ratings): ?>
  <div class="card"><p class="muted" style="margin:0">No ratings yet. They appear here after a client rates a completed engagement.</p></div>
<?php else: ?>
  <?php foreach ($ratings as $r): ?>
    <div class="card" style="<?= !empty($r['hidden']) ? 'opacity:.6' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
        <div><span style="color:#e0a100;font-size:16px"><?= $stars($r['stars']) ?></span>
          <?php if (!empty($r['would_rehire'])): ?><span class="chip" style="font-size:11px;border-color:var(--teal);color:var(--teal)">would hire again</span><?php endif; ?>
          <?php if (!empty($r['hidden'])): ?><span class="chip" style="font-size:11px;border-color:#9a2a2a;color:#9a2a2a">removed from scores</span><?php endif; ?></div>
        <span class="muted" style="font-size:12px"><?= e(substr((string)$r['created_at'], 0, 10)) ?></span>
      </div>
      <?php if (!empty($r['comment'])): ?><p style="margin:8px 0 0;font-size:14px">“<?= e($r['comment']) ?>”</p><?php endif; ?>
      <?php if (!empty($r['moderation_note'])): ?><p class="muted" style="margin:6px 0 0;font-size:12.5px;color:var(--teal)">Moderator note: <?= e($r['moderation_note']) ?></p><?php endif; ?>
      <?php if (empty($r['hidden'])): ?>
        <?php if (!empty($r['_disputed'])): ?>
          <p class="muted" style="margin:8px 0 0;font-size:12px">⏳ You’ve reported this rating — under review.</p>
        <?php else: ?>
          <details style="margin-top:10px">
            <summary style="cursor:pointer;font-size:13px;color:var(--muted)">Report this rating</summary>
            <form method="post" action="/pro/reputation" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
              <input type="hidden" name="action" value="report_rating"><input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
              <div class="ff"><label style="font-size:12px">Why</label>
                <select name="category" class="form-control" style="width:auto">
                  <?php foreach ($categories as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
                </select></div>
              <div class="ff" style="flex:1;min-width:200px"><label style="font-size:12px">Details</label><input name="detail" class="form-control" placeholder="What’s wrong with it?"></div>
              <button class="btn sec" type="submit">Submit report</button>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
