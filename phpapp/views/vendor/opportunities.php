<?php
// Connect K2b — the supply side: an agency/vendor browses open manpower
// requirements and applies. One application per requirement (deduped on the
// applying vendor). Zero-Training: each card is one requirement, one Apply.
$rows = $rows ?? []; $applied = $applied ?? [];
?>
<style>
  .oppgrid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));margin-top:8px}
  .opp{border:1px solid var(--line,#e3ebea);border-radius:14px;padding:16px;background:var(--card,#fff)}
  .opp h3{margin:0 0 4px;font-size:16px}
  .opp .meta{color:var(--muted);font-size:13px;margin-bottom:10px}
  .opp .rate{font-weight:600}
  .opp form{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}
  .opp input{padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px;font-size:14px}
  .applied{color:#0f7d5a;font-weight:600;font-size:14px}
</style>
<h2 class="ptitle">Open manpower requirements</h2>
<p class="plead">Requirements posted on the marketplace right now. Apply to the ones your people fit —
  you apply once per requirement, and the client sees you on their shortlist.</p>

<?php if (!$rows): ?>
  <p class="pempty">No open requirements at the moment. Check back soon.</p>
<?php else: ?>
  <div class="oppgrid">
    <?php foreach ($rows as $r): $done = !empty($applied[(int)$r['id']]); ?>
      <div class="opp">
        <h3><?= e($r['title']) ?></h3>
        <div class="meta">
          <?= e($r['ref_code']) ?>
          <?php if (!empty($r['location'])): ?> · <?= e($r['location']) ?><?php endif; ?>
          · <?= (int)$r['positions'] ?> position<?= (int)$r['positions']===1?'':'s' ?>
          <?php if (!empty($r['discipline_code'])): ?> · <?= e($r['discipline_code']) ?><?php endif; ?>
        </div>
        <?php if (($r['rate_min'] ?? 0) || ($r['rate_max'] ?? 0)): ?><div class="rate">₹<?= (int)$r['rate_min'] ?>–<?= (int)$r['rate_max'] ?> <?= e($r['rate_unit']) ?></div><?php endif; ?>
        <?php if (!empty($r['description'])): ?><p style="font-size:13.5px;color:var(--muted);margin:8px 0 0;white-space:pre-line"><?= e($r['description']) ?></p><?php endif; ?>
        <?php if ($done): ?>
          <p class="applied">✓ You have applied</p>
        <?php else: ?>
          <form method="post" action="/vendor/opportunities">
            <input type="hidden" name="requirement_id" value="<?= (int)$r['id'] ?>">
            <input type="number" name="proposed_rate" placeholder="Your rate ₹" min="0" style="width:130px">
            <button class="btn" type="submit">Apply</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
