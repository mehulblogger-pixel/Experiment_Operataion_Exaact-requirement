<?php
// Import an existing org chart / organogram. Data: $preview (parsed rows or null), $raw.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$preview = $preview ?? null; $raw = $raw ?? '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/positions">Positions</a> › Import org chart</div>
<div class="master-head">
  <div><h1>Import organisation chart</h1>
    <p class="sub" style="margin:2px 0 0">Already have your structure in a spreadsheet? Paste it below (or upload a CSV) and we'll create the positions and draw the reporting chart for you. Import is safe to re-run — a position with the same code or name is updated, not duplicated.</p></div>
  <div class="row-actions"><a class="btn secondary" href="/positions">← Positions</a></div>
</div>

<div class="panel">
  <h3 class="tab-sub">Paste or upload</h3>
  <p class="muted" style="font-size:12.5px;margin:0 0 10px">Columns, in this order (a header row is detected automatically if present):
    <b>Name</b>, Code, Department, Grade, <b>Reports to</b> (a code or name from this list), Sanctioned, Occupied.
    “Reports to” is what builds the tree — leave it blank for the top of the organisation.</p>
  <form method="post" enctype="multipart/form-data">
    <textarea class="form-control" name="data" rows="9" style="font-family:ui-monospace,Menlo,monospace;font-size:13px"
      placeholder="Chief Executive Officer, P1, Executive, E1, , 1, 1&#10;HR Director, P2, Human Resources, M4, P1, 1, 1&#10;HR Manager, P4, Human Resources, M2, P2, 2, 1&#10;Recruiter, P5, Human Resources, M1, P2, 3, 2"><?= $e($raw) ?></textarea>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px">
      <label class="muted" style="font-size:12.5px">…or upload a CSV: <input type="file" name="file" accept=".csv,.txt" style="font-size:12.5px"></label>
      <div style="margin-left:auto;display:flex;gap:8px">
        <button class="btn secondary" name="do" value="preview">Preview</button>
        <?php if ($preview): ?><button class="btn" name="do" value="apply">Import <?= count($preview) ?> position<?= count($preview)===1?'':'s' ?></button><?php endif; ?>
      </div>
    </div>
  </form>
</div>

<?php if ($preview !== null): ?>
<div class="panel">
  <h3 class="tab-sub">Preview <span class="muted" style="font-weight:400;font-size:12px">— <?= count($preview) ?> row<?= count($preview)===1?'':'s' ?> parsed. Nothing is saved until you click Import.</span></h3>
  <?php if (!$preview): ?>
    <p class="muted">Nothing recognised — check the columns and try again.</p>
  <?php else: ?>
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;min-width:680px;font-size:13px">
      <tr style="text-align:left;color:var(--muted,#64748b);font-size:10.5px;text-transform:uppercase">
        <th style="padding:6px 8px">Name</th><th style="padding:6px 8px">Code</th><th style="padding:6px 8px">Department</th>
        <th style="padding:6px 8px">Grade</th><th style="padding:6px 8px">Reports to</th><th style="padding:6px 8px">Sanctioned</th><th style="padding:6px 8px">Occupied</th></tr>
      <?php foreach ($preview as $r): ?>
        <tr style="border-top:1px solid var(--line,#eef1f5)">
          <td style="padding:6px 8px;font-weight:600"><?= $e($r['name']) ?></td>
          <td style="padding:6px 8px"><?= $e($r['code']) ?></td>
          <td style="padding:6px 8px"><?= $e($r['department']) ?></td>
          <td style="padding:6px 8px"><?= $e($r['grade']) ?></td>
          <td style="padding:6px 8px"><?= $r['reports_to'] !== '' ? $e($r['reports_to']) : '<span class="muted">— top —</span>' ?></td>
          <td style="padding:6px 8px"><?= (int)$r['sanctioned'] ?></td>
          <td style="padding:6px 8px"><?= (int)$r['occupied'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
