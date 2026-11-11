<?php
// Owner, 11 Nov 2026 — when applications close, and keeping a job open longer.
// Shared by the staff desk and the client portal. Needs $req and $cxPostTo.
$cxSt = strtoupper((string)($req['status'] ?? ''));
if (!in_array($cxSt, ['OPEN', 'SHORTLISTING'], true) || trim((string)($req['closes_on'] ?? '')) === '' || !function_exists('cx_req_days_left')) return;
$cxLeft = cx_req_days_left($req);
$cxWhen = function_exists('fdate') ? fdate($req['closes_on'], $req['closes_on']) : $req['closes_on'];
$cxSoon = $cxLeft <= (function_exists('cx_req_remind_days') ? cx_req_remind_days() : 3);
$cxText = $cxLeft < 0 ? ($cxSt === 'SHORTLISTING' ? 'Applications closed on ' . $cxWhen . ' — award it, or keep it open longer.' : 'Applications closed on ' . $cxWhen . '.')
        : ($cxLeft === 0 ? 'Applications close <strong>today</strong>.' : 'Applications close on <strong>' . e($cxWhen) . '</strong> — in ' . $cxLeft . ' day' . ($cxLeft === 1 ? '' : 's') . '.');
$cxNote = $cxSt === 'OPEN' ? 'If nobody is shortlisted by then, it closes by itself and applicants are told.' : 'You are shortlisting, so it will not close by itself.';
?>
<div style="margin-top:10px;padding:10px 12px;border-radius:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;<?= $cxSoon ? 'background:#fff7ea;border:1px solid #efd9a3' : 'background:#f4f7f7;border:1px solid #e3e9e8' ?>">
  <div style="flex:1;min-width:220px;font-size:13.5px">🗓 <?= strpos($cxText, '<strong>') !== false ? $cxText : e($cxText) ?>
    <div style="font-size:12px;color:#5b6b6a;margin-top:2px"><?= e($cxNote) ?></div></div>
  <form method="post" action="<?= e($cxPostTo) ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0">
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="req_extend">
    <?php foreach ([7, 15, 30] as $cxD): ?><button class="btn secondary" type="submit" name="days" value="<?= $cxD ?>" style="padding:6px 10px;font-size:12.5px">+<?= $cxD ?> days</button><?php endforeach; ?>
  </form>
  <form method="post" action="<?= e($cxPostTo) ?>" style="display:flex;gap:6px;align-items:center;margin:0">
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="req_extend">
    <input type="date" name="closes_on" min="<?= date('Y-m-d') ?>" value="<?= e(max(date('Y-m-d'), (string)$req['closes_on'])) ?>" aria-label="New closing date" style="padding:6px 8px;border:1px solid #d5dedd;border-radius:8px;font-size:12.5px">
    <button class="btn secondary" type="submit" style="padding:6px 10px;font-size:12.5px">Set date</button>
  </form>
</div>
