<?php
  // Connect K14 / backlog #3 — the verification & moderation desk. Coordinators
  // and admins confirm or reject pending identity / credential checks; a decision
  // elevates the professional's verification tier. Zero-Training: the queue is the
  // whole screen, each row is one decision, plain words, big tap targets.
  $pending = $pending ?? []; $types = $types ?? []; $ladder = $ladder ?? []; $tierCounts = $tierCounts ?? [];
  $typeLabel = fn($t) => e($types[$t]['label'] ?? $t);
  $statusPill = function ($s) {
      $s = strtoupper((string)$s);
      $cls = ['PENDING' => 'p', 'VERIFIED' => 'v', 'REJECTED' => 'r'][$s] ?? 'p';
      return '<span class="vp vp-' . $cls . '">' . e(ucfirst(strtolower($s))) . '</span>';
  };
?>
<div class="crumbs"><a href="/">Home</a> › Verification desk</div>
<div class="master-head">
  <div><h1>Verification desk</h1>
    <p class="sub" style="margin:2px 0 0">Confirm or reject the identity and credential checks people submit.
      Approving one moves a professional up the trust ladder — <strong>Registered → ID-verified →
      Credential-verified → Proven</strong>. A number that only <em>looks</em> right is never enough:
      the tier moves only when you (or a verified provider) confirm it.</p></div>
</div>

<style>
  .vk-row{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0}
  .vk{flex:1 1 150px;padding:12px 14px;border:1px solid var(--line,#e5e7eb);border-radius:12px;background:var(--card,#fff)}
  .vk .k{font-size:12px;color:var(--muted,#777);text-transform:uppercase;letter-spacing:.03em}
  .vk .v{font-size:24px;font-weight:700;margin-top:2px}
  .vk .b{font-size:12px;color:var(--muted,#888)}
  .vp{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700}
  .vp-p{background:rgba(201,162,39,.14);color:#8a6d12}
  .vp-v{background:rgba(16,122,74,.14);color:#0b7a4a}
  .vp-r{background:rgba(185,28,28,.12);color:#b91c1c}
  .vq{border:1px solid var(--line,#e5e7eb);border-radius:12px;overflow:hidden;margin-top:10px}
  .vq-row{display:grid;grid-template-columns:1.4fr 1fr 1fr auto;gap:12px;align-items:center;padding:14px;border-bottom:1px solid var(--line,#eee)}
  .vq-row:last-child{border-bottom:0}
  .vq-name{font-weight:700}
  .vq-sub{font-size:12px;color:var(--muted,#777)}
  .vq-ref{font-family:ui-monospace,Menlo,monospace;font-size:13px}
  .vq-act{display:flex;gap:8px;flex-wrap:wrap}
  .vbtn{padding:8px 14px;border-radius:9px;border:1px solid var(--line,#ddd);background:var(--card,#fff);cursor:pointer;font-weight:600;font-size:14px}
  .vbtn.ok{background:#0b7a4a;border-color:#0b7a4a;color:#fff}
  .vbtn.no{color:#b91c1c;border-color:rgba(185,28,28,.35)}
  .vmeth{font-size:11px;color:var(--muted,#999)}
  .empty{padding:26px;text-align:center;color:var(--muted,#777)}
  @media(max-width:720px){.vq-row{grid-template-columns:1fr 1fr;}.vq-act{grid-column:1/-1}}
</style>

<!-- Where the whole pool sits on the ladder -->
<div class="vk-row">
  <?php foreach ($ladder as $t): $c = (int)($tierCounts[$t['key']] ?? 0); ?>
    <div class="vk">
      <div class="k"><?= e($t['label']) ?></div>
      <div class="v"><?= $c ?></div>
      <div class="b"><?= e($t['blurb']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<h2 style="margin:18px 0 4px;font-size:18px">Awaiting your decision <span class="vp vp-p"><?= count($pending) ?></span></h2>

<?php if (!$pending): ?>
  <div class="vq"><div class="empty">Nothing waiting — the queue is clear. 🎉</div></div>
<?php else: ?>
  <div class="vq">
    <?php foreach ($pending as $row): ?>
      <div class="vq-row">
        <div>
          <div class="vq-name"><?= e($row['subject_name'] ?: ('#' . (int)$row['subject_id'])) ?></div>
          <div class="vq-sub"><?= e(ucfirst((string)$row['subject_kind'])) ?> · submitted <?= e(substr((string)$row['created_at'], 0, 10)) ?></div>
        </div>
        <div>
          <div><strong><?= $typeLabel($row['check_type']) ?></strong></div>
          <div class="vmeth"><?= e(ucfirst((string)$row['method'])) ?><?= $row['provider'] ? ' · ' . e($row['provider']) : '' ?></div>
        </div>
        <div>
          <?php if (!empty($row['ref_masked'])): ?><div class="vq-ref"><?= e($row['ref_masked']) ?></div><?php endif; ?>
          <?php if (!empty($row['result_note'])): ?><div class="vq-sub"><?= e($row['result_note']) ?></div><?php endif; ?>
          <?php if (!empty($row['evidence'])): ?><div class="vq-sub">📎 <?= e($row['evidence']) ?></div><?php endif; ?>
        </div>
        <div class="vq-act">
          <form method="post" action="/connect-verify">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="vbtn ok" name="decision" value="VERIFIED" type="submit">✓ Verify</button>
            <button class="vbtn no" name="decision" value="REJECTED" type="submit">✕ Reject</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
