<?php
// Phase 3 §35 — attendance review queue. Only the anomalous self-marks; the reviewer sends back,
// clears, or escalates. Advisory — the attendance already counts; this catches and corrects errors.
?>
<div class="crumbs"><a href="/">Home</a> › Attendance review</div>
<div class="master-head">
  <div>
    <h1>Attendance review</h1>
    <p class="sub" style="margin:2px 0 0">Self-marked entries that look off — a wrong location, a missing check-out, a late mark. Everything else is left alone.</p>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="panel" style="margin-top:16px;text-align:center;padding:30px">
    <div style="font-size:30px">✓</div>
    <h3 style="margin:8px 0 2px">Nothing to review</h3>
    <p class="muted" style="margin:0">No self-marked attendance looks wrong right now.</p>
  </div>
<?php else: ?>
  <div class="panel" style="margin-top:16px">
    <p class="muted" style="margin:0 0 10px"><?= count($rows) ?> entr<?= count($rows) === 1 ? 'y' : 'ies' ?> flagged. Send back to the inspector to re-mark, clear if it's actually fine, or escalate to the reporting manager.</p>
    <div style="display:flex;flex-direction:column;gap:0">
      <?php foreach ($rows as $r): ?>
        <div style="padding:11px 0;border-top:1px solid var(--line,#e5e7eb)">
          <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:baseline">
            <strong style="font-size:14.5px"><?= e($r['inspector_name'] ?: ('Inspector #' . (int)$r['inspector_id'])) ?></strong>
            <span class="mono muted" style="font-size:12.5px"><?= e($r['att_date']) ?></span>
            <span class="pill p-mut" style="font-size:10px"><?= e($r['status']) ?></span>
            <?php if (($r['review_status'] ?? '') === 'ESCALATED'): ?><span class="pill p-bad" style="font-size:10px">escalated</span><?php endif; ?>
          </div>
          <div style="color:var(--bad,#b91c1c);font-size:13px;margin-top:2px">⚑ <?= e($r['flag_reason']) ?></div>
          <form method="post" action="/attendance-review" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:7px">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input name="note" placeholder="Note to the inspector (optional)…" style="flex:1;min-width:200px">
            <button class="btn small" name="do" value="return" type="submit" title="Inspector must re-mark">↩ Send back</button>
            <button class="btn small secondary" name="do" value="clear" type="submit" title="It's fine — keep it">✓ Clear</button>
            <button class="btn small secondary" name="do" value="escalate" type="submit" title="To the reporting manager">⇧ Escalate</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
