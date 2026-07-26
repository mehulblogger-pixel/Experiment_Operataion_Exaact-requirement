<div class="crumbs"><a href="/">Home</a> › <a href="/clients"><?= e(T_REG('client')) ?></a> › Import from a spreadsheet</div>
<div class="master-head">
  <div><h1>Import <?= e(Tlp('client')) ?> &amp; <?= e(Tlp('vendor')) ?></h1>
    <p class="sub">Download the format, fill it in, upload it. You see exactly what will happen before anything is saved.</p></div>
  <div><a class="btn ghost" href="/clients">Back</a></div>
</div>

<?php if (!$rows): ?>

<div class="panel">
  <h3 class="tab-sub">Step 1 — get the format</h3>
  <p class="sub">The Excel file has the right columns already, with real dropdowns for Role, Type, Industry, Ownership and Status, so nothing has to be spelled the system's way by hand.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
    <a class="btn" href="/partner-template?blank=1&amp;fmt=xlsx">Empty format (Excel)</a>
    <a class="btn ghost" href="/partner-template?blank=1&amp;fmt=csv">Empty format (CSV)</a>
    <a class="btn ghost" href="/partner-template?fmt=xlsx">Everything already on file (Excel)</a>
  </div>
  <p class="muted" style="margin-top:10px">The third one downloads what is already in the system. Edit it, upload it back, and the changes are applied to the same companies — it is a round trip, not a second copy.</p>
</div>

<div class="panel">
  <h3 class="tab-sub">Step 2 — upload it</h3>
  <form method="post" action="/partner-import" enctype="multipart/form-data">
    <input type="hidden" name="do" value="preview">
    <div class="form-grid">
      <div class="ff-wide"><label>Your file (.xlsx, .csv or .txt)</label>
        <input class="form-control" type="file" name="sheet" accept=".xlsx,.csv,.txt,.tsv" required></div>
      <div class="ff"><label>If a row does not say what it is, treat it as</label>
        <select class="form-control" name="default_role">
          <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div style="margin-top:12px"><button class="btn" type="submit">Read the file</button></div>
  </form>
</div>

<div class="panel">
  <h3 class="tab-sub">What the columns mean</h3>
  <p class="sub">Only <strong>Legal name</strong> is required. Every other column can be left empty, and an empty cell never erases something already on file.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>Column</th><th>What to put in it</th></tr>
    <tr><td>Code</td><td>Leave blank and the system generates one. Fill it in and that code is used to find the company on a later upload.</td></tr>
    <tr><td>Legal name</td><td>The registered company name. This is the one column that must be filled.</td></tr>
    <tr><td>Role</td><td>Client, Vendor, Both, or Sub-contractor. A company already on file as a client that arrives on a vendor sheet becomes both — it is never overwritten.</td></tr>
    <tr><td>GSTIN</td><td>If given, the PAN and state are read from it automatically when those columns are blank.</td></tr>
    <tr><td>Contact person / e-mail / mobile</td><td>Creates the first contact for that company. A contact of the same name already on file is left alone.</td></tr>
    <tr><td>Address, city, pincode</td><td>Creates the registered address, but only if the company has no address yet.</td></tr>
  </table>
  </div>
</div>

<?php else: ?>

<?php
  $create = 0; $update = 0; $skip = 0; $warn = 0;
  foreach ($rows as $r) {
      if (!empty($r['skip'])) $skip++;
      elseif ($r['action'] === 'create') $create++;
      else $update++;
      if (!empty($r['notes'])) $warn++;
  }
?>
<div class="panel">
  <div class="master-head" style="margin-bottom:8px">
    <div><h3 class="tab-sub">Step 3 — check, then apply</h3>
      <p class="sub">Read from <strong><?= e($fileName) ?></strong>. Nothing has been saved yet.</p></div>
  </div>
  <div class="kpi-row">
    <div class="kpi"><span class="k-lab">New companies</span><span class="k-val"><?= $create ?></span></div>
    <div class="kpi"><span class="k-lab">Already on file, will be updated</span><span class="k-val"><?= $update ?></span></div>
    <div class="kpi"><span class="k-lab">Skipped</span><span class="k-val"><?= $skip ?></span></div>
    <div class="kpi"><span class="k-lab">Worth a look</span><span class="k-val"><?= $warn ?></span></div>
  </div>
</div>

<form method="post" action="/partner-import">
<input type="hidden" name="do" value="apply">
<div class="panel">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th style="width:60px">Skip</th><th style="width:50px">Line</th><th>Company</th><th>Role</th>
        <th>What will happen</th><th>GSTIN / PAN</th><th>Contact</th><th>City</th></tr>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><input type="checkbox" name="skip[<?= (int)$i ?>]" value="1" <?= !empty($r['skip']) ? 'checked' : '' ?>></td>
      <td class="muted"><?= (int)$r['line'] ?></td>
      <td><strong><?= e($r['legal_name']) ?></strong>
          <?php if ($r['code'] !== ''): ?><span class="muted"> · <?= e($r['code']) ?></span><?php endif; ?>
          <?php foreach ($r['notes'] as $n): ?><div class="muted"><?= e($n) ?></div><?php endforeach; ?></td>
      <td><select class="form-control" name="row_role[<?= (int)$i ?>]">
            <?php foreach ($roles as $ro): ?>
              <option value="<?= e($ro) ?>"<?= $r['role'] === $ro ? ' selected' : '' ?>><?= e($ro) ?></option>
            <?php endforeach; ?>
          </select></td>
      <td><?php if ($r['action'] === 'create'): ?>
            <span class="pill p-ok">New</span>
          <?php else: ?>
            <span class="pill p-info">Update</span>
            <div class="muted">matched on <?= e($r['match_by']) ?> — <?= e($r['match_name']) ?></div>
          <?php endif; ?></td>
      <td class="muted"><?= e($r['gstin']) ?><?= $r['pan'] !== '' ? '<br>' . e($r['pan']) : '' ?></td>
      <td class="muted"><?= e($r['contact_name']) ?><?= $r['contact_email'] !== '' ? '<br>' . e($r['contact_email']) : '' ?></td>
      <td class="muted"><?= e($r['city']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="submit">Apply <?= $create + $update - 0 ?> row(s)</button>
    <button class="btn ghost" type="submit" name="do" value="cancel" formnovalidate>Cancel and start again</button>
  </div>
</div>
</form>

<?php endif; ?>
