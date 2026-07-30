<div class="crumbs"><a href="/">Home</a> › <a href="/opportunities">Opportunities</a> › New</div>
<div class="master-head"><div><h1>New opportunity</h1>
  <p class="sub" style="margin:2px 0 0">A piece of business you are trying to win. It can exist long before there is anything to quote.</p></div></div>

<form method="post" action="/opportunity-new" class="panel" style="margin-top:16px;max-width:900px">
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>What is the opportunity? *</label>
      <input class="form-control" name="name" required maxlength="255" value="<?= e($prefill['name'] ?? '') ?>"
             placeholder="e.g. Annual vessel inspection contract — 2027 renewal">
      <span class="muted" style="font-size:12px">Name it the way you would say it out loud in a review.</span></div>
    <div><label>Customer</label>
      <select name="partner_id" class="searchable">
        <option value="">— not on the master yet —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($prefill['partner_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>…or who it is for</label>
      <input class="form-control" name="partner_name" maxlength="200" value="<?= e($prefill['partner_name'] ?? '') ?>"
             placeholder="Company name, if they are not a customer yet"></div>
    <div><label>Pipeline</label>
      <select class="form-control" name="pipeline_id">
        <?php foreach ($pipelines as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Estimated value</label><input class="form-control" name="value" type="number" step="0.01" value="<?= e($prefill['value'] ?? '') ?>" placeholder="0.00">
      <span class="muted" style="font-size:12px">A working figure. It moves as you learn more — that is the point of it not being a quotation.</span></div>
    <div><label>Expected close</label><input class="form-control" type="date" name="expected_close" value="<?= e($prefill['expected_close'] ?? '') ?>"></div>
    <div><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if ($sources): ?>
      <div><label>Where it came from</label>
        <select class="form-control" name="source"><option value="">—</option>
          <?php foreach ($sources as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div><label>Competing against</label><input class="form-control" name="competitor" maxlength="200" placeholder="Who else is bidding, if you know"></div>
    <div><label>Contact</label><input class="form-control" name="contact_name" maxlength="150"></div>
    <div><label>Contact e-mail</label><input class="form-control" name="contact_email" type="email" maxlength="200"></div>
    <div><label>Contact phone</label><input class="form-control" name="contact_phone" maxlength="60"></div>
    <div><label>Next action</label><input class="form-control" name="next_action" maxlength="255" placeholder="e.g. Send the scope for review"></div>
    <div><label>By when</label><input class="form-control" type="date" name="next_action_on"></div>
    <div class="ff-wide"><label>What they need</label><textarea class="form-control" name="requirement" rows="3"></textarea></div>
  </div>
  <button class="btn" style="margin-top:14px">Open the opportunity</button>
  <a class="btn secondary" href="/opportunities" style="margin-left:8px">Cancel</a>
</form>
