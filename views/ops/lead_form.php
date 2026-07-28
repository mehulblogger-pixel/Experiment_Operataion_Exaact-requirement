<?php $p = $prefill ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/leads">Leads</a> › New</div>
<div class="master-head"><div><h1>New lead</h1>
  <p class="sub" style="margin:2px 0 0">Somebody worth chasing. If they become a customer, everything here comes across — you will not type it again.</p></div></div>

<?php if ($dup): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--warn)">
  <b>We may already know them.</b>
  <p style="margin:6px 0 0">
    <?php if ($dup['kind'] === 'partner'): ?>
      <a href="/client?id=<?= (int)$dup['row']['id'] ?>"><?= e($dup['row']['display_name'] ?? $dup['row']['legal_name']) ?></a>
      is already on the customer master — matched on <?= e($dup['by']) ?>.
      Chasing them as a new lead builds a second relationship with the same company.
    <?php else: ?>
      There is already an open lead <a href="/lead?id=<?= (int)$dup['row']['id'] ?>"><?= e($dup['row']['ref']) ?></a>
      for <?= e($dup['row']['company_name']) ?>.
    <?php endif; ?>
  </p>
</div>
<?php endif; ?>

<form method="post" action="/lead-new" class="panel" style="margin-top:16px;max-width:860px">
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>Company or person *</label>
      <input name="company_name" required maxlength="200" value="<?= e($p['company_name'] ?? '') ?>"
             placeholder="Who are we chasing?"></div>
    <div><label>Pipeline</label><select name="pipeline_id">
      <?php foreach ($pipelines as $pl): ?><option value="<?= (int)$pl['id'] ?>"><?= e($pl['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div><label>Where they came from</label><select name="source">
      <option value="">—</option>
      <?php foreach ($sources as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
    </select></div>
    <div><label>Contact name</label><input name="contact_name" maxlength="150" value="<?= e($p['contact_name'] ?? '') ?>"></div>
    <div><label>Branch</label><select name="office_id"><option value="">— mine —</option>
      <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div><label>E-mail</label><input type="email" name="contact_email" maxlength="200" value="<?= e($p['contact_email'] ?? '') ?>"></div>
    <div><label>Telephone</label><input name="contact_phone" maxlength="60" value="<?= e($p['contact_phone'] ?? '') ?>"></div>
    <div class="ff-wide"><label>What do they want?</label>
      <textarea name="requirement" rows="3" placeholder="Scope, quantities, the standard, anything that decides whether this is worth chasing"><?= e($p['requirement'] ?? '') ?></textarea></div>
    <div><label>Value they are worth <span class="muted">(estimate)</span></label><input type="number" step="0.01" min="0" name="value" value="<?= e($p['value'] ?? '') ?>"></div>
    <div><label>Expected to close</label><input type="date" name="expected_close" value="<?= e($p['expected_close'] ?? '') ?>"></div>
    <div><label>Next thing to do</label><input name="next_action" maxlength="255" placeholder="e.g. send the capability statement"></div>
    <div><label>By when</label><input type="date" name="next_action_on"></div>
  </div>
  <p class="muted" style="margin-top:10px;font-size:12.5px">A lead with no e-mail and no telephone number scores badly on purpose — it is a lead nobody can chase.</p>
  <div style="margin-top:12px;display:flex;gap:10px">
    <button class="btn">Create it</button><a class="btn secondary" href="/leads">Cancel</a>
  </div>
</form>
