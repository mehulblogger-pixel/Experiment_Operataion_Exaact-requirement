<?php
// Connect K0+ — Unified professional identity console. Confirm that a marketplace
// professional and an internal inspector are the SAME person (a link, never a
// merge), so the whole system treats them as one identity with many roles.
$links = $links ?? []; $suggestions = $suggestions ?? [];
?>
<style>
  .id-wrap{max-width:1000px}
  .id-card{background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:12px;padding:16px 18px;margin-bottom:16px}
  .id-card h2{margin:0 0 3px;font-size:17px}
  .id-card .sub{color:var(--muted,#667);font-size:13px;margin:0 0 12px}
  .id-row{display:flex;justify-content:space-between;gap:14px;align-items:center;border:1px solid var(--line,#eef1f0);border-radius:10px;padding:11px 13px;margin-bottom:8px;flex-wrap:wrap}
  .id-two{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
  .id-pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:700}
  .id-pill.pro{background:#e6f0fb;color:#1858a8}.id-pill.insp{background:#e7f5ef;color:#0f7d5a}.id-pill.basis{background:#f3eefb;color:#6a3fa8}
  .id-link{font-size:20px;color:var(--muted,#889)}
  .id-name{font-weight:600}
  .id-meta{color:var(--muted,#667);font-size:12px}
  .id-manual{display:flex;gap:10px;align-items:end;flex-wrap:wrap;border-top:1px solid var(--line,#eee);margin-top:10px;padding-top:12px}
  .id-manual label{display:block;font-size:12px;color:var(--muted,#667);margin:0 0 4px}
  .id-manual input{padding:8px 10px;border:1px solid var(--line,#ccc);border-radius:8px;font-size:14px;width:130px}
</style>

<div class="id-wrap">
<h1>Professional identity</h1>
<p class="muted" style="margin:0 0 16px;max-width:70ch">One person, one master identity. When someone on your internal staff (an <strong>inspector</strong>) is also a self-registered <strong>marketplace professional</strong>, link the two records here. Nothing is merged or moved — both keep working exactly as before; the system simply knows they are the same person, so matching won’t show them twice and their roles connect across Operations and the marketplace.</p>

<?php // ---- Suggested links (same e-mail / mobile, not yet linked) ---- ?>
<div class="id-card">
  <h2>Suggested links <?php if ($suggestions): ?><span class="id-pill basis" style="margin-left:4px"><?= count($suggestions) ?></span><?php endif; ?></h2>
  <p class="sub">A marketplace professional and an internal inspector that share an e-mail or mobile — almost certainly the same person. Confirm each one; nothing is linked automatically.</p>
  <?php if (!$suggestions): ?>
    <p class="muted" style="margin:0;font-size:13.5px">No suggestions right now.</p>
  <?php else: foreach ($suggestions as $s): ?>
    <div class="id-row">
      <div class="id-two">
        <div><span class="id-pill pro">Marketplace</span> <span class="id-name"><?= e($s['pro_name']) ?></span><div class="id-meta"><?= e($s['pro_email']) ?></div></div>
        <span class="id-link">↔</span>
        <div><span class="id-pill insp">Internal inspector</span> <span class="id-name"><?= e($s['insp_name']) ?></span><div class="id-meta"><?= e($s['emp_code'] ? $s['emp_code'].' · ' : '') ?><?= e($s['insp_email']) ?></div></div>
        <span class="id-pill basis">matched on <?= e($s['basis']) ?></span>
      </div>
      <form method="post" action="/connect-identity" style="margin:0">
        <input type="hidden" name="action" value="link">
        <input type="hidden" name="professional_id" value="<?= (int)$s['professional_id'] ?>">
        <input type="hidden" name="inspector_id" value="<?= (int)$s['inspector_id'] ?>">
        <input type="hidden" name="method" value="<?= e($s['basis']) ?>_match">
        <button class="btn" type="submit">Link as one person</button>
      </form>
    </div>
  <?php endforeach; endif; ?>

  <form method="post" action="/connect-identity" class="id-manual">
    <input type="hidden" name="action" value="link">
    <div><label>Professional ID</label><input name="professional_id" type="number" min="1" required></div>
    <span class="id-link" style="padding-bottom:6px">↔</span>
    <div><label>Inspector ID</label><input name="inspector_id" type="number" min="1" required></div>
    <button class="btn sec" type="submit">Link by ID</button>
  </form>
</div>

<?php // ---- Existing links ---- ?>
<div class="id-card">
  <h2>Linked identities <span class="id-pill insp" style="margin-left:4px"><?= count($links) ?></span></h2>
  <p class="sub">People the system treats as one identity across Operations and the marketplace.</p>
  <?php if (!$links): ?>
    <p class="muted" style="margin:0;font-size:13.5px">Nothing linked yet.</p>
  <?php else: foreach ($links as $l): ?>
    <div class="id-row">
      <div class="id-two">
        <div><span class="id-pill pro">Marketplace #<?= (int)$l['professional_id'] ?></span> <span class="id-name"><?= e($l['pro_name'] ?: '—') ?></span><div class="id-meta"><?= e($l['pro_email'] ?? '') ?></div></div>
        <span class="id-link">↔</span>
        <div><span class="id-pill insp">Inspector #<?= (int)$l['inspector_id'] ?></span> <span class="id-name"><?= e($l['insp_name'] ?: '—') ?></span><div class="id-meta"><?= e($l['emp_code'] ?? '') ?></div></div>
        <span class="id-meta">via <?= e($l['method']) ?><?= $l['linked_by'] ? ' · '.e($l['linked_by']) : '' ?></span>
      </div>
      <form method="post" action="/connect-identity" style="margin:0" onsubmit="return confirm('Unlink these two records? Both keep working; they just stop being treated as one person.');">
        <input type="hidden" name="action" value="unlink"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="btn sec" type="submit" style="padding:5px 12px;font-size:12.5px">Unlink</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
</div>
</div>
