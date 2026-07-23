<div class="crumbs"><a href="/">Home</a> › Office finance (overheads)</div>
<div class="master-head">
  <div><h1>Office overheads &amp; contingency</h1>
    <p class="sub">Each office sets its own <strong>Overhead %</strong> and <strong>Contingency %</strong>, applied to that office's cost so profitability is accurate. Blank = use the global default.</p></div>
</div>

<?php if ($canGlobal): ?>
<div class="panel">
  <h3 class="tab-sub">Global default <span class="muted">(used when an office leaves its own blank)</span></h3>
  <form method="post" action="/office-finance" class="inline-add" style="align-items:flex-end">
    <input type="hidden" name="global_default" value="1">
    <div class="ff"><label>Overhead %</label><input class="form-control" style="width:110px" type="number" step="0.01" name="overhead_pct" value="<?= e($defOh) ?>"></div>
    <div class="ff"><label>Contingency %</label><input class="form-control" style="width:110px" type="number" step="0.01" name="contingency_pct" value="<?= e($defCg) ?>"></div>
    <button class="btn" type="submit">Save default</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">Per-office</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>Office</th><th>Overhead %</th><th>Contingency %</th><th></th></tr>
    <?php foreach ($offices as $o): $fid = 'of_'.(int)$o['id']; ?>
    <tr>
      <td><form id="<?= $fid ?>" method="post" action="/office-finance"><input type="hidden" name="office_id" value="<?= (int)$o['id'] ?>"></form>
        <strong><?= e($o['name']) ?></strong> <span class="muted">(<?= e($o['code']) ?>)</span></td>
      <td><input form="<?= $fid ?>" class="form-control" style="width:110px" type="number" step="0.01" name="overhead_pct" value="<?= $o['overhead_pct']===null?'':e($o['overhead_pct']) ?>" placeholder="<?= e($defOh) ?>"></td>
      <td><input form="<?= $fid ?>" class="form-control" style="width:110px" type="number" step="0.01" name="contingency_pct" value="<?= $o['contingency_pct']===null?'':e($o['contingency_pct']) ?>" placeholder="<?= e($defCg) ?>"></td>
      <td><button form="<?= $fid ?>" class="btn small" type="submit">Save</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$offices): ?><tr><td colspan="4">No office is linked to your account. Ask an admin to set your home office.</td></tr><?php endif; ?>
  </table>
  </div>
  <p class="muted" style="margin-top:8px">Loaded labour = (CTC ÷ 12 × (1 + Overhead %)) ÷ working days. Contingency % is added on top of (labour + expenses + sub-con) as a buffer, reducing margin. Both flow into the Profitability and Financial dashboards.</p>
</div>
