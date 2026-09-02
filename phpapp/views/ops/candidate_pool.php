<?php
  // Revamp P11 — candidate pool convergence (read-only). One person can sit in both the
  // recruitment pool (candidates) and the marketplace pool (cx_professionals). This surfaces
  // every candidate that is also a known marketplace professional, matched by the same
  // mobile / e-mail / name keys the app already dedupes on. It merges nothing and moves no
  // figure — each pool keeps its own record.
  $summary = $summary ?? []; $rows = $rows ?? [];
  $overlap = (int)($summary['overlap'] ?? 0);
  $reasonLabel = ['mobile' => 'same mobile', 'email' => 'same e-mail', 'name' => 'same name'];
  $reasonTone  = ['mobile' => 'p-ok', 'email' => 'p-ok', 'name' => 'p-warn'];
  $tierTone = fn($t) => in_array(strtolower((string)$t), ['verified','id_verified','engaged'], true) ? 'p-ok' : 'p-mut';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/recruitment">Recruitment</a> › Candidate pool</div>
<div class="master-head">
  <div><h1>Candidate pool convergence</h1>
    <p class="sub" style="margin:2px 0 0">Where a recruitment candidate is also a known <strong>marketplace professional</strong>
      — the same human in both pools. Matched by mobile / e-mail / name. Read-only — it merges nothing and each pool keeps its
      own record; it just lets a recruiter see the person is already on the bench.</p></div>
  <a class="btn secondary" href="/recruitment">← Recruitment</a>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">🔗</span><div class="k">Overlapping people</div>
    <div class="v"><?= $overlap ?></div>
    <div class="d">candidate is also a professional</div></div>
  <div class="kpi"><span class="kic">📋</span><div class="k">Recruitment pool</div>
    <div class="v"><?= (int)($summary['candidates'] ?? 0) ?></div>
    <div class="d">candidate records</div></div>
  <div class="kpi"><span class="kic">🧑‍🔧</span><div class="k">Marketplace pool</div>
    <div class="v"><?= (int)($summary['professionals'] ?? 0) ?></div>
    <div class="d">active professionals</div></div>
  <div class="kpi"><span class="kic">🎯</span><div class="k">Matched by</div>
    <div class="v" style="font-size:15px"><?= (int)($summary['by_mobile'] ?? 0) ?> mob · <?= (int)($summary['by_email'] ?? 0) ?> mail · <?= (int)($summary['by_name'] ?? 0) ?> name</div></div>
</div>

<div class="panel" style="margin-top:12px">
  <?php if (!$rows): ?>
    <p class="msg msg-ok" style="margin:0">No candidate currently matches a marketplace professional. The two pools do not overlap.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px;font-size:12px">Each row is one person who exists in both pools. A <span class="pill p-warn" style="font-size:11px">same name</span>
      match is a soft signal — confirm before acting on it. Nothing here changes either record.</p>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Candidate</th><th>Against</th><th>Stage</th><th>Marketplace professional</th><th>Tier</th><th>Availability</th><th>Matched by</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="/candidate?id=<?= (int)$r['cand_id'] ?>"><?= e($r['cand_code']) ?></a>
              <?php if ($r['cand_name']): ?><span class="muted">· <?= e($r['cand_name']) ?></span><?php endif; ?></td>
            <td style="font-size:12px"><?= $r['req_code'] ? e($r['req_code']) : e($r['client_name'] ?: '—') ?></td>
            <td style="font-size:12px"><?= e($r['stage'] ?: '—') ?></td>
            <td><?= e($r['pro_name'] ?: ('#' . $r['pro_id'])) ?>
              <?php if ($r['match_count'] > 1): ?><span class="muted" style="font-size:11px"> +<?= (int)$r['match_count'] - 1 ?> more</span><?php endif; ?></td>
            <td><span class="pill <?= $tierTone($r['verification_tier']) ?>" style="font-size:11px"><?= e($r['verification_tier'] ?: '—') ?></span></td>
            <td style="font-size:12px"><?= e($r['availability'] ?: '—') ?></td>
            <td><span class="pill <?= $reasonTone[$r['reason']] ?? 'p-mut' ?>" style="font-size:11px"><?= e($reasonLabel[$r['reason']] ?? $r['reason']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
