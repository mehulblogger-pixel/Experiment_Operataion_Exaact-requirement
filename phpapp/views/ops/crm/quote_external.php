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

  <h3 class="tab-sub">What was quoted <span class="muted">— the line items</span></h3>
  <p class="muted" style="margin:0 0 8px">Enter what you actually offered. These quantities are what an
    <?= e(Tl('call')) ?> later draws down against, so an open order run out of quantity can be spotted before it is
    over-served. Leave the rows blank if only a lump-sum figure was submitted.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt qlines" id="xlines">
    <thead><tr>
      <th style="min-width:200px">Description</th>
      <th style="min-width:150px">Type of inspection</th>
      <th style="width:90px">Qty</th>
      <th style="min-width:130px">Unit</th>
      <th style="width:120px">Rate</th>
      <th style="width:120px">Amount</th>
      <th style="width:40px"></th>
    </tr></thead>
    <tbody>
      <?php for ($i = 0; $i < 3; $i++): ?>
      <tr class="xlrow">
        <td><input class="form-control" name="l_desc[]" placeholder="e.g. Stage inspection at works"></td>
        <td><select class="form-control" name="l_service[]"><option value="">—</option>
            <?php foreach ($svcOpts as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select></td>
        <td><input class="form-control l-qty" type="number" step="0.01" name="l_qty[]" placeholder="0"></td>
        <td><select class="form-control" name="l_unit[]">
            <?php foreach ($unitOpts as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select></td>
        <td><input class="form-control l-rate" type="number" step="0.01" name="l_rate[]" placeholder="0"></td>
        <td><input class="form-control l-amt" type="number" step="0.01" name="l_amount[]" placeholder="0" readonly style="background:var(--soft)"></td>
        <td><button type="button" class="btn small danger xlrm" title="Clear this line">✕</button></td>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>
  </div>
  <div style="margin:8px 0"><button type="button" class="btn small secondary" id="xaddrow">+ Add line</button></div>

  <h3 class="tab-sub">Value</h3>
  <div class="form-grid">
    <div class="ff"><label>Total quoted (<?= e(cur_sym()) ?>) *</label>
      <input class="form-control" type="number" step="0.01" name="total_amount" id="x_total" required>
      <small class="muted">Filled from the lines above; override it if the portal figure differs.</small></div>
    <div class="ff"><label>Payment terms</label>
      <select class="form-control searchable" name="payment_terms"><option value="">—</option>
        <?php foreach ($payTerms as $k=>$v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
  </div>
  <p class="muted" style="margin-top:6px">Attach the file you actually submitted from the <?= e(Tl('quote')) ?> page once it is created.</p>

<script>
(function () {
  // Line amount = qty x rate, and the header total follows the lines. Typing
  // straight into the total still wins — a portal figure can include rounding
  // or a rebate that the lines do not show.
  var tbody = document.querySelector('#xlines tbody');
  var total = document.getElementById('x_total');
  var totalTouched = false;
  total.addEventListener('input', function () { totalTouched = true; });

  function recalc() {
    var sum = 0;
    tbody.querySelectorAll('.xlrow').forEach(function (tr) {
      var q = parseFloat(tr.querySelector('.l-qty').value) || 0;
      var r = parseFloat(tr.querySelector('.l-rate').value) || 0;
      var a = q * r;
      tr.querySelector('.l-amt').value = a ? a.toFixed(2) : '';
      sum += a;
    });
    if (!totalTouched && sum) total.value = sum.toFixed(2);
  }
  tbody.addEventListener('input', function (e) {
    if (e.target.classList.contains('l-qty') || e.target.classList.contains('l-rate')) recalc();
  });
  tbody.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.xlrm') : null;
    if (!btn) return;
    var tr = btn.closest('tr');
    tr.querySelectorAll('input, select').forEach(function (f) { f.value = ''; });
    if (tbody.querySelectorAll('.xlrow').length > 1) tr.remove();
    recalc();
  });
  document.getElementById('xaddrow').addEventListener('click', function () {
    var tr = tbody.querySelector('.xlrow').cloneNode(true);
    tr.querySelectorAll('input').forEach(function (f) { f.value = ''; });
    tr.querySelectorAll('select').forEach(function (f) { f.selectedIndex = 0; });
    tbody.appendChild(tr);
  });
})();
</script>

  <div style="margin-top:16px">
    <button class="btn" type="submit">Register it</button>
    <a class="btn secondary" href="/quotes">Cancel</a>
  </div>
</form>
