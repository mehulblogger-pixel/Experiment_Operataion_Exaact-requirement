<?php
// Connect B0 — organisation accounts. Master-only. Register an organisation with
// its type; the type maps to a module bundle from the existing product packages.
$orgs = $orgs ?? []; $types = $types ?? [];
$modLabel = ['operations'=>'Operations','admin'=>'Admin','sales'=>'Sales/CRM','reporting'=>'Reporting','money'=>'Money','hr'=>'People/Hiring','connect'=>'Marketplace','pro'=>'Self-service'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/marketplace">Marketplace</a> › Organisations</div>
<div class="master-head">
  <div><h1>Organisations</h1>
    <p class="sub" style="margin:2px 0 0">Each organisation on the platform carries a type, and the type sets which modules it gets — a TPIA gets the full operations platform, a manpower agency the marketplace. Everyone shares the professional pool; private data stays private (Phase B design).
      Organisations can self-register at <a href="/join" target="_blank" rel="noopener">/join</a> — those applications appear here as <em>Pending</em> for you to approve.</p>
    <p class="sub" style="margin:6px 0 0">A company is not one fixed type — set the full mix of what it delivers on the <a href="/connect-capabilities"><strong>Company capabilities</strong></a> screen (TPIA, manpower, freelance supply, recruitment, project services).</p></div>
</div>

<details class="panel" style="margin-top:12px" open>
  <summary style="cursor:pointer;font-weight:600;font-size:16px">➕ Register an organisation</summary>
  <form method="post" action="/connect-orgs" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
    <input type="hidden" name="action" value="add">
    <input type="text" name="name" placeholder="Organisation name" required style="flex:2;min-width:220px;padding:11px;border:1px solid var(--line,#dde3e2);border-radius:10px">
    <select name="org_type" style="padding:11px;border:1px solid var(--line,#dde3e2);border-radius:10px">
      <?php foreach ($types as $k=>$t): ?><option value="<?= e($k) ?>"><?= e($t['label']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Register</button>
  </form>
</details>

<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 8px">Registered organisations</h3>
  <?php if (!$orgs): ?>
    <p class="muted" style="margin:0">None yet. Register the first above.</p>
  <?php else: ?>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Organisation</th><th>Status</th><th>Type</th><th>Package</th><th>Modules (entitlement)</th></tr></thead>
      <tbody>
      <?php foreach ($orgs as $o):
        $mods = function_exists('connect_org_type_modules') ? connect_org_type_modules($o['org_type']) : [];
        $st = strtoupper((string)($o['status'] ?? 'ACTIVE'));
      ?>
        <tr>
          <td><strong><?= e($o['name']) ?></strong>
            <?php if (!empty($o['contact_email'])): ?><div class="muted" style="font-size:12px"><?= e($o['contact_name']) ?> · <?= e($o['contact_email']) ?></div><?php endif; ?></td>
          <td>
            <?php if ($st === 'PENDING'): ?>
              <span class="cxpill" style="display:inline-block;padding:3px 9px;border-radius:999px;font-size:11.5px;background:#fbf3d8;color:#8a6d0b">Pending</span>
              <form method="post" action="/connect-orgs" style="display:inline;margin-left:4px">
                <input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <button class="btn" type="submit" style="font-size:12px;padding:5px 10px">Approve</button></form>
            <?php else: ?>
              <span class="cxpill" style="display:inline-block;padding:3px 9px;border-radius:999px;font-size:11.5px;background:#e7f5ef;color:#0f7d5a">Active</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="/connect-orgs" style="display:inline">
              <input type="hidden" name="action" value="set_type"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <select name="org_type" onchange="this.form.submit()" style="padding:6px;border:1px solid var(--line,#dde3e2);border-radius:8px">
                <?php foreach ($types as $k=>$t): ?><option value="<?= e($k) ?>" <?= $o['org_type']===$k?'selected':'' ?>><?= e($t['label']) ?></option><?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?= e($o['package_key'] ?: '—') ?></td>
          <td><?php foreach ($mods as $m) echo '<span class="cxpill" style="display:inline-block;padding:3px 9px;border-radius:999px;font-size:11.5px;margin:2px;background:rgba(15,125,125,.08);border:1px solid rgba(15,125,125,.2)">' . e($modLabel[$m] ?? $m) . '</span>'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
