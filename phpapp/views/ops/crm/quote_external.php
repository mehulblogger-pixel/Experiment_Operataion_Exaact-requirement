<div class="crumbs"><a href="/">Home</a> › <a href="/quotes"><?= e(TP('quote')) ?></a> › Register an external <?= e(Tl('quote')) ?></div>
<div class="master-head">
  <div><h1>Register an external <?= e(Tl('quote')) ?></h1>
    <p class="sub" style="margin:2px 0 0">For an offer submitted straight into the <?= e(Tl('client')) ?>'s portal, a tender portal or by e-mail, where our own format was never used. It still belongs in the register, so win/loss and follow-up numbers stay honest.</p></div>
  <a class="btn secondary" href="/quotes">← Back</a>
</div>

<form method="post" action="/quote-external" class="panel">
  <h3 class="tab-sub" style="margin-top:0">Where it went</h3>
  <div class="form-grid">
    <div class="ff"><label>Submitted through *</label>
      <select class="form-control" name="origin" required>
        <?php foreach ($origins as $k=>$v): if ($k==='OWN') continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Portal / platform</label><input class="form-control" name="origin_portal" placeholder="e.g. GeM, client SRM portal"></div>
    <div class="ff"><label>Their reference / bid no.</label><input class="form-control" name="origin_ref"></div>
    <div class="ff"><label>Our reference <span class="muted">— blank to generate one</span></label><input class="form-control" name="quote_no" placeholder="auto"></div>
    <div class="ff"><label>Submitted on</label><input class="form-control" type="date" name="submitted_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Status</label>
      <select class="form-control" name="status">
        <?php foreach (['SENT','ACCEPTED','LOST','REJECTED'] as $sk): ?>
          <option value="<?= e($sk) ?>" <?= $sk==='SENT'?'selected':'' ?>><?= e($statuses[$sk] ?? $sk) ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Marking it sent schedules the usual follow-ups.</small></div>
  </div>

  <h3 class="tab-sub"><?= e(T('client')) ?> &amp; scope</h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('client')) ?></label>
      <select class="form-control searchable" name="client_id"><option value="">— not on file, type the name —</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>"><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or <?= e(Tl('client')) ?> name</label><input class="form-control" name="client_name"></div>
    <div class="ff"><label><?= e(T('sbu')) ?></label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Contact person</label><input class="form-control" name="contact_name"></div>
    <div class="ff"><label>Contact e-mail</label><input class="form-control" type="email" name="contact_email"></div>
    <div class="ff"><label>Contact mobile</label><input class="form-control" name="contact_mobile"></div>
    <div class="ff"><label>Executing <?= e(T('office')) ?></label>
      <select class="form-control searchable" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Product category</label>
      <input class="form-control" name="product_category" list="prodcats2" placeholder="e.g. Transformers">
      <datalist id="prodcats2"><?php foreach ($prodCats as $pc): ?><option value="<?= e($pc) ?>"><?php endforeach; ?></datalist></div>
    <div class="ff ff-wide"><label>Subject / scope title</label><input class="form-control" name="subject"></div>
    <div class="ff ff-wide"><label>Types of inspection</label>
      <div class="chip-row pickbox">
        <?php foreach ($svcOpts as $k=>$v): ?>
          <label class="ff-check"><input type="checkbox" name="inspection_types[]" value="<?= e($k) ?>"> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div></div>
  </div>

  <h3 class="tab-sub">Value</h3>
  <div class="form-grid">
    <div class="ff"><label>Total quoted (<?= e(cur_sym()) ?>) *</label><input class="form-control" type="number" step="0.01" name="total_amount" required></div>
    <div class="ff"><label>Payment terms</label>
      <select class="form-control searchable" name="payment_terms"><option value="">—</option>
        <?php foreach ($payTerms as $k=>$v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
  </div>
  <p class="muted" style="margin-top:6px">No line items are needed — this records the offer, not our working. Attach the file you actually submitted from the <?= e(Tl('quote')) ?> page once it is created.</p>

  <div style="margin-top:16px">
    <button class="btn" type="submit">Register it</button>
    <a class="btn secondary" href="/quotes">Cancel</a>
  </div>
</form>
