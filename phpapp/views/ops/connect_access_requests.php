<?php
// ============================================================================
//  Access requests — somebody signed up for a company we already work with.
//
//  Nothing on this screen grants access. It is a list of people who asked, so a
//  human can decide and then invite them the ordinary way from the client
//  record. That separation is the point: a public form must never be able to
//  hand an organisation to whoever types its name (Q26).
// ============================================================================
$rows   = $rows   ?? [];
$closed = $closed ?? [];
?>
<div class="page-head">
  <h1>Access requests</h1>
  <p class="sub">People who signed up for a company we already have on file. Nothing here gives anybody
     access — decide, then invite them from the client record as usual.</p>
</div>

<div class="card">
  <h2 style="margin-top:0">Waiting <span class="badge <?= $rows ? 'AMBER' : 'GREEN' ?>"><?= count($rows) ?></span></h2>
  <?php if (!$rows): ?>
    <p class="muted">Nothing waiting. When someone signs up for a company we already work with,
       they appear here instead of getting an account.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Who</th><th>Company they typed</th><th>We think it is</th><th>Matched on</th><th>When</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['contact_name'] ?: '—') ?></strong><br>
            <span class="muted"><?= e($r['email']) ?><?= $r['mobile'] ? ' · ' . e($r['mobile']) : '' ?></span></td>
        <td><?= e($r['org_name']) ?></td>
        <td><?= $r['partner_name'] ? e($r['partner_code'] . ' — ' . $r['partner_name']) : '<span class="muted">—</span>' ?></td>
        <td><span class="badge <?= ($r['confidence'] ?? '') === 'EXACT' ? 'RED' : 'AMBER' ?>">
              <?= e(($r['confidence'] ?: '—') . ' · ' . ($r['matched_by'] ?: '—')) ?></span></td>
        <td class="muted" style="white-space:nowrap"><?= e(substr((string)$r['created_at'], 0, 10)) ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/access-requests" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="APPROVED">
            <button class="btn btn-sm" type="submit">Genuine — I'll invite them</button>
          </form>
          <form method="post" action="/access-requests" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="REJECTED">
            <button class="btn btn-sm btn-ghost" type="submit">Not genuine</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($closed): ?>
<div class="card">
  <h2 style="margin-top:0">Recently accepted</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Who</th><th>Company</th><th>Handled by</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($closed, 0, 20) as $r): ?>
      <tr><td><?= e($r['contact_name'] ?: $r['email']) ?></td>
          <td><?= e($r['partner_name'] ?: $r['org_name']) ?></td>
          <td><?= e($r['handled_by'] ?: '—') ?></td>
          <td class="muted" style="white-space:nowrap"><?= e(substr((string)$r['handled_at'], 0, 10)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>
