<div class="crumbs"><a href="/">Home</a> › <a href="/identity">Identity documents</a> › What sites require</div>
<div class="master-head"><div>
  <h1>What each site requires before the gate opens</h1>
  <p class="sub" style="margin:2px 0 0">A refinery that wants a medical and a police verification says so here, once. Deputing somebody who does not hold them is then refused at the allocation screen rather than at the barrier.</p></div></div>

<?php if ($expiring): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--warn)">
  <h3 style="margin:0 0 8px">Running out in the next 45 days <span class="muted">(<?= count($expiring) ?>)</span></h3>
  <table class="dt">
    <thead><tr><th>Engineer</th><th>Document</th><th>Expires</th></tr></thead>
    <tbody>
    <?php foreach ($expiring as $x): ?>
      <tr><td><?= e($x['inspector_name'] ?: '—') ?></td>
          <td><?= e($kinds[$x['doc_kind']] ?? $x['doc_kind']) ?></td>
          <td><?= e(fdate($x['expires_on'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="margin:8px 0 0;font-size:12.5px">A visa takes weeks to renew, so this looks further ahead than the certificate reminder does.</p>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Requirements <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <?php if (!$rows): ?>
    <p class="muted" style="padding:18px">Nothing recorded, so nothing is checked. Add the first one below.</p>
  <?php else: ?>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Client</th><th>Site</th><th>Document</th><th>Enforcement</th><th>Validity wanted</th><th>Note</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['display_name'] ?: $r['legal_name'] ?: '—') ?></td>
        <td><?= $r['site_address_id'] ? e(trim(($r['site_label'] ?: '') . ' ' . ($r['site_city'] ?: ''))) : '<span class="muted">every site</span>' ?></td>
        <td><?= e($kinds[$r['doc_kind']] ?? $r['doc_kind']) ?></td>
        <td><?= !empty($r['is_mandatory'])
              ? '<span class="pill p-bad">Blocks allocation</span>'
              : '<span class="pill p-warn">Warns only</span>' ?></td>
        <td><?= (int)$r['valid_days_after'] > 0
              ? 'valid ' . (int)$r['valid_days_after'] . ' days past the visit'
              : '<span class="muted">valid on the day</span>' ?></td>
        <td><?= e($r['note'] ?: '—') ?></td>
        <?php if ($canEdit): ?>
        <td><form method="post" action="/site-docs-delete" onsubmit="return confirm('Remove this requirement?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn small secondary">Remove</button></form></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<form method="post" action="/site-docs-add" class="panel" style="margin-top:16px;max-width:820px">
  <h3 style="margin-top:0">Add a requirement</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Client</label>
      <select class="form-control" name="partner_id" required>
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Site <span class="muted">(leave blank for every site of that client)</span></label>
      <input class="form-control" name="site_address_id" placeholder="address id, or blank"></div>
    <div><label>Document</label>
      <select class="form-control" name="doc_kind">
        <?php foreach ($kinds as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Still valid this many days after the visit</label>
      <input class="form-control" type="number" name="valid_days_after" min="0" value="0" placeholder="0 = valid on the day is enough"></div>
    <div class="ff-wide">
      <label style="display:flex;gap:8px;align-items:flex-start;font-weight:400">
        <input type="checkbox" name="is_mandatory" value="1" checked>
        <span>This one <b>blocks</b> the allocation. A manager can still send somebody, but only by stating why — and that reason stays on the <?= e(Tl('job')) ?>. Untick to warn only.</span></label></div>
    <div class="ff-wide"><label>Note <span class="muted">(what the site actually asks for)</span></label>
      <input class="form-control" name="note" maxlength="400" placeholder="e.g. Gate pass must be applied for 7 days ahead through their security office"></div>
  </div>
  <button class="btn" style="margin-top:12px">Add it</button>
</form>
<?php endif; ?>
