<?php $isClient = $roleField === 'is_client'; $salesView = !empty($salesView); $salesOk = !empty($salesOk);
      //  The Sales view keeps every filter; the switch only changes the columns.
      $vq = $salesView ? '&view=sales' : ''; ?>
<div class="master-head">
  <div><h1><?= e($title) ?></h1>
    <p class="sub"><?= e($subtitle) ?> · <?= (int)$total ?> record<?= $total == 1 ? '' : 's' ?></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn" href="/partner-new?role=<?= e($roleField) ?>">+ Add <?= $isClient ? 'Client' : 'Vendor' ?></a>
    <?php if (is_admin_level() || can('mod.clients.edit') || can('mod.vendors.edit')): ?>
      <a class="btn ghost" href="/partner-import">⬆ Import from a spreadsheet</a>
      <a class="btn ghost" href="/partner-template?kind=<?= $isClient ? 'clients' : 'vendors' ?>&amp;fmt=xlsx">⬇ Export</a>
      <a class="btn ghost" href="/duplicates">🔍 Possible duplicates</a>
    <?php endif; ?>
  </div>
</div>

<form method="get" action="/<?= $isClient ? 'clients' : 'vendors' ?>" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / code / GSTIN / PAN…">
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">Any status</option>
    <?php foreach (lk_options_or('partner_status', STATUSES) as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <?php if ($salesView): ?><input type="hidden" name="view" value="sales"><?php endif; ?>
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q || $status): ?><a class="btn secondary" href="/<?= $isClient ? 'clients' : 'vendors' ?><?= $salesView ? '?view=sales' : '' ?>">Clear</a><?php endif; ?>
  <?php if ($salesOk): $qs = '&q=' . rawurlencode($q) . '&status=' . rawurlencode($status); ?>
    <nav class="seg" aria-label="Columns" style="margin-left:auto;display:inline-flex;gap:4px">
      <a class="btn small<?= $salesView ? ' secondary' : '' ?>" href="/clients?<?= e(ltrim($qs, '&')) ?>"<?= $salesView ? '' : ' aria-current="true"' ?>>Standard</a>
      <a class="btn small<?= $salesView ? '' : ' secondary' ?>" href="/clients?view=sales<?= e($qs) ?>"<?= $salesView ? ' aria-current="true"' : '' ?>>💼 Sales view</a>
    </nav>
  <?php endif; ?>
</form>
<?php if ($salesView): ?><p class="muted" style="margin:-4px 0 10px">The same <?= e(Tl('client')) ?> list as Directory, with what Sales needs: open deals, won this year, amount owed and last contact. Open a name for its full record.</p><?php endif; ?>

<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid">
  <?php if ($salesView): $owedOn = false; foreach ($salesCols as $sc) if ($sc['owed'] !== null) { $owedOn = true; break; } ?>
  <tr><th>Name</th><th>State</th><th>GSTIN</th><th class="n">Open deals</th><th class="n">Open pipeline</th><th class="n">Won this FY</th><?php if ($owedOn): ?><th class="n">Owed to us</th><?php endif; ?><th>Last contact</th><th>Contact</th><th></th></tr>
  <?php foreach ($rows as $p): $sc = $salesCols[(int) $p['id']] ?? ['deals' => 0, 'pipe' => 0, 'won' => 0, 'owed' => null, 'touch' => '', 'contact' => '', 'phone' => ''];
        $g = strtoupper(trim((string) ($p['gstin'] ?? ''))); $gOk = $g === '' ? null : (bool) preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g);
        $idle = $sc['touch'] !== '' ? sx_days_since($sc['touch']) : null; ?>
  <tr>
    <td><a href="/partner?id=<?= (int)$p['id'] ?>"><b><?= e(partner_name($p)) ?></b></a><br><span class="muted" style="font-size:12px"><?= e($p['code']) ?> · <?= e(roles_label($p)) ?></span></td>
    <td><?= e(($p['state'] ?? '') ?: '—') ?></td>
    <td><?= $gOk === null ? '<span class="muted">—</span>' : ($gOk ? '<span class="badge GREEN" title="' . e($g) . '">✓ valid</span>' : '<span class="badge RED" title="' . e($g) . '">check</span>') ?></td>
    <td class="n"><?= $sc['deals'] ? '<a href="/customer?id=' . (int) $p['id'] . '" title="Open the customer 360">' . (int) $sc['deals'] . '</a>' : '—' ?></td>
    <td class="n"><?= $sc['pipe'] > 0 ? e(sx_short($sc['pipe'])) : '—' ?></td>
    <td class="n"><?= $sc['won'] > 0 ? '<span class="badge GREEN">' . e(sx_short($sc['won'])) . '</span>' : '—' ?></td>
    <?php if ($owedOn): ?><td class="n"><?= $sc['owed'] !== null && $sc['owed'] > 0.005 ? '<a href="/customer?id=' . (int) $p['id'] . '" title="Open the customer 360">' . e(sx_money($sc['owed'])) . '</a>' : '—' ?></td><?php endif; ?>
    <td><?= $idle === null ? '<span class="badge AMBER">never</span>' : ($idle > sx_stale_days() ? '<span class="badge AMBER">' . (int) $idle . ' days ago</span>' : e($idle === 0 ? 'today' : $idle . ' days ago')) ?></td>
    <td><?= e($sc['contact'] ?: '—') ?><?php if ($sc['phone'] !== '' && function_exists('sx_contact_btns_plain')): ?> <span style="white-space:nowrap"><?= sx_contact_btns_plain($sc['phone'], $sc['contact'] ?: partner_name($p)) ?></span><?php endif; ?></td>
    <td><a class="btn small secondary" href="/customer?id=<?= (int)$p['id'] ?>" title="Everything about this <?= e(Tl('client')) ?> on one page">360</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="10">No records found. <a href="/partner-new?role=is_client">Add one</a>.</td></tr><?php endif; ?>
  <?php else: ?>
  <tr><th>Code</th><th>Name</th><th>Roles</th><th>Industry</th><th>GSTIN</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $p): ?>
  <tr>
    <td><a href="/partner?id=<?= (int)$p['id'] ?>"><?= e($p['code']) ?></a></td>
    <td><a href="/partner?id=<?= (int)$p['id'] ?>"><?= e(partner_name($p)) ?></a></td>
    <td><?= e(roles_label($p)) ?></td>
    <td><?= e(INDUSTRIES[$p['industry']] ?? '—') ?></td>
    <td><?= e($p['gstin'] ?: '—') ?></td>
    <td><span class="badge <?= $p['status']==='ACTIVE'?'GREEN':($p['status']==='BLACKLISTED'?'RED':'AMBER') ?>"><?= e(lk_options_or('partner_status', STATUSES)[$p['status']] ?? $p['status']) ?></span></td>
    <td class="row-actions">
      <a class="btn small" href="/customer?id=<?= (int)$p['id'] ?>">360</a>
      <a class="btn small secondary" href="/partner?id=<?= (int)$p['id'] ?>">Open</a>
      <a class="btn small" href="/partner-edit?id=<?= (int)$p['id'] ?>">Edit</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">No records found. <a href="/partner-new?role=<?= e($roleField) ?>">Add one</a>.</td></tr><?php endif; ?>
  <?php endif; ?>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pill-row" style="margin-top:14px;">
  <?php $base = "/" . ($isClient ? 'clients' : 'vendors') . "?q=" . urlencode($q) . "&status=" . urlencode($status) . $vq; ?>
  <?php if ($page > 1): ?><a class="btn small secondary" href="<?= $base ?>&page=<?= $page-1 ?>">← Prev</a><?php endif; ?>
  <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn small secondary" href="<?= $base ?>&page=<?= $page+1 ?>">Next →</a><?php endif; ?>
</div>
<?php endif; ?>
