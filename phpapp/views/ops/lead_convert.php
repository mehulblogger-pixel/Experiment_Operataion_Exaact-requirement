<div class="crumbs"><a href="/">Home</a> › <a href="/leads">Leads</a> › <a href="/lead?id=<?= (int)$l['id'] ?>"><?= e($l['ref']) ?></a> › Convert</div>
<div class="master-head"><div><h1>Convert <?= e($l['ref']) ?></h1>
  <p class="sub" style="margin:2px 0 0">Winning a lead is not a tick in a column. It becomes a customer on the master and an inquiry in the funnel — everything below comes from the lead, so nothing is retyped.</p></div></div>

<?php if ($dup && $dup['kind'] === 'partner'): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--warn)">
  <b>They may already be on the master.</b>
  <p style="margin:6px 0 0"><?= e($dup['row']['display_name'] ?? $dup['row']['legal_name']) ?>
    matched on <?= e($dup['by']) ?>. Attach the lead to them below rather than creating a second record.</p>
</div>
<?php endif; ?>

<form method="post" action="/lead-convert" class="panel" style="margin-top:16px;max-width:820px">
  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
  <input type="hidden" name="stage_id" value="<?= (int)$stage_id ?>">

  <h3 style="margin-top:0">The customer</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>Attach to a customer we already have</label>
      <select class="form-control" name="partner_id">
        <option value="">— create a new one —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"<?= (int)$l['partner_id'] === (int)$c['id'] ? ' selected' : '' ?>>
            <?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff-wide"><label>…or the name for the new one</label>
      <input class="form-control" name="company_name" value="<?= e($l['company_name']) ?>" maxlength="200"></div>
    <div><label>State <span class="muted">(for tax on the invoice side)</span></label><input class="form-control" name="state" maxlength="60"></div>
  </div>

  <h3>What comes across</h3>
  <table class="grid">
    <tr><th>Contact</th><td><?= e($l['contact_name'] ?: '—') ?></td>
        <th>E-mail</th><td><?= e($l['contact_email'] ?: '—') ?></td></tr>
    <tr><th>Telephone</th><td><?= e($l['contact_phone'] ?: '—') ?></td>
        <th>Source</th><td><?= e($l['source'] ?: '—') ?></td></tr>
    <tr><th>Value</th><td><?= $l['value'] ? fmoney($l['value']) : '—' ?></td>
        <th>Owner</th><td><?= e($l['owner_name'] ?: '—') ?></td></tr>
    <tr><th>What they want</th><td colspan="3" style="white-space:pre-wrap"><?= e($l['requirement'] ?: '—') ?></td></tr>
  </table>
  <p class="muted" style="margin-top:10px;font-size:12.5px">The contact becomes the customer's primary contact, and the requirement becomes the inquiry's service requirement. From there the existing quotation flow takes over.</p>

  <div style="margin-top:14px;display:flex;gap:10px">
    <button class="btn">Convert it</button>
    <a class="btn secondary" href="/lead?id=<?= (int)$l['id'] ?>">Not yet</a>
  </div>
</form>
