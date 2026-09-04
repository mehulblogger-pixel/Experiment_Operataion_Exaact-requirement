<?php
  // Rating-integrity moderation desk — investigate a reported rating and decide:
  // the rating stands, gets a public note, or is removed from scores (never deleted).
  $rows = $rows ?? []; $categories = $categories ?? []; $outcomes = $outcomes ?? [];
  $badge = function ($s) {
      $s = strtoupper((string)$s);
      $c = ['OPEN' => '#8a5a00', 'UNDER_REVIEW' => '#0a5c5c', 'RESOLVED' => '#2f7a34', 'WITHDRAWN' => '#667'][$s] ?? '#667';
      return '<span style="display:inline-block;font-size:11px;font-weight:700;color:#fff;background:' . $c . ';border-radius:999px;padding:2px 9px">' . e(str_replace('_', ' ', $s)) . '</span>';
  };
  $stars = fn($n) => str_repeat('★', max(0, min(5, (int)$n))) . str_repeat('☆', 5 - max(0, min(5, (int)$n)));
?>
<div class="crumbs"><a href="/">Home</a> › Rating-integrity desk</div>
<div class="master-head">
  <div><h1>Rating-integrity desk</h1>
    <p class="sub">Someone believes a rating about them is wrong. Investigate, then decide: the rating <b>stands</b>, gets a <b>public note</b>, or is <b>removed from scores</b>. Removed ratings are hidden, never deleted — the record stays.</p></div>
</div>

<?php if (!$rows): ?>
  <div class="panel"><p class="muted" style="margin:0">No rating disputes have been raised. When a client or professional reports a rating, it appears here to investigate.</p></div>
<?php else: ?>
  <div class="panel" style="max-width:1000px">
    <div style="overflow-x:auto"><table class="grid" style="margin:0"><thead><tr>
      <th>#</th><th>The rating</th><th>Reported</th><th>Status</th><th>Action</th>
    </tr></thead><tbody>
      <?php foreach ($rows as $r): $st = strtoupper((string)$r['status']); $rt = $r['_rating'] ?? null; ?>
        <tr>
          <td class="muted" style="font-size:12px"><?= e($r['ref_code']) ?></td>
          <td style="font-size:13px;max-width:280px">
            <?php if ($rt): ?>
              <span style="color:#e0a100"><?= $stars($rt['stars']) ?></span>
              <span class="muted">(<?= e($rt['direction'] === 'PRO_TO_CLIENT' ? 'pro → client' : 'client → pro') ?>)</span>
              <?php if (!empty($rt['comment'])): ?><br><span class="muted" style="font-size:12px">“<?= e(mb_strimwidth((string)$rt['comment'], 0, 90, '…')) ?>”</span><?php endif; ?>
              <?php if (!empty($rt['hidden'])): ?><br><span style="color:#9a2a2a;font-size:11px;font-weight:700">REMOVED from scores</span><?php endif; ?>
              <?php if (!empty($rt['moderation_note'])): ?><br><span style="color:#0a5c5c;font-size:11px">Note: <?= e($rt['moderation_note']) ?></span><?php endif; ?>
            <?php else: ?><span class="muted">rating #<?= (int)$r['rating_id'] ?></span><?php endif; ?>
          </td>
          <td style="font-size:12.5px"><b><?= e($categories[$r['category']] ?? $r['category']) ?></b><br>
            <span class="muted">by <?= e($r['raised_by_name'] ?: strtolower((string)$r['raised_by_kind'])) ?></span>
            <?php if (!empty($r['detail'])): ?><br><span class="muted" style="font-size:12px">“<?= e(mb_strimwidth((string)$r['detail'], 0, 90, '…')) ?>”</span><?php endif; ?>
            <?php if ($st === 'RESOLVED' && !empty($r['outcome'])): ?><br><span style="color:#2f7a34;font-size:11px;font-weight:700">→ <?= e($outcomes[$r['outcome']] ?? $r['outcome']) ?></span><?php endif; ?>
          </td>
          <td><?= $badge($st) ?></td>
          <td style="white-space:nowrap;min-width:230px">
            <?php if ($st === 'OPEN'): ?>
              <form method="post" action="/rating-disputes" style="display:inline"><input type="hidden" name="action" value="investigate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small" type="submit">Investigate</button></form>
              <form method="post" action="/rating-disputes" style="display:inline" onsubmit="return confirm('Withdraw this report?')"><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" type="submit">Withdraw</button></form>
            <?php elseif ($st === 'UNDER_REVIEW'): ?>
              <form method="post" action="/rating-disputes" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <select name="outcome" class="form-control" style="width:auto;font-size:12.5px" required>
                  <option value="">— outcome —</option>
                  <?php foreach ($outcomes as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
                </select>
                <input name="resolution" placeholder="Note (shown if annotated)" style="padding:6px 8px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:12.5px">
                <button class="btn small" type="submit">Resolve</button>
              </form>
            <?php else: ?>
              <span class="muted" style="font-size:12px"><?= $st === 'RESOLVED' ? 'by ' . e($r['resolved_by']) . ' · ' . e(substr((string)$r['resolved_at'],0,10)) : 'closed' ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
<?php endif; ?>
<p class="muted" style="font-size:12px;max-width:1000px;margin-top:10px">Genuine reviews are the platform’s trust — every rating here comes from a real completed engagement. Removing a rating only hides it from public scores; the underlying record is preserved for audit.</p>
