<?php
// Import an organogram. Data: $preview (rows|null), $raw, $note, $error, $format,
// $summary (['positions','offices','designations','departments']|null), $rowsJson.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$preview = $preview ?? null; $raw = $raw ?? ''; $note = $note ?? ''; $error = $error ?? '';
$format = $format ?? ''; $summary = $summary ?? null; $rowsJson = $rowsJson ?? '';
$aiPool = function_exists('ai_pool_applies') && ai_pool_applies();
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/positions">Positions</a> › Import organogram</div>
<div class="master-head">
  <div><h1>Import organogram</h1>
    <p class="sub" style="margin:2px 0 0">Upload your organisation chart and the system creates the <b>offices</b>, <b>designations</b>, <b>positions</b>, their <b>codes</b> and the <b>reporting lines</b> — you approve a preview before anything is saved. Re-running is safe: a position with the same code or name is updated, never duplicated.</p></div>
  <div class="row-actions"><a class="btn secondary" href="/positions">← Positions</a></div>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">What you can upload</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;font-size:13px">
    <div style="border:1px solid var(--line,#e5e9f0);border-radius:10px;padding:10px 12px"><b>📊 Excel / CSV</b><br><span class="muted" style="font-size:12px">A table of roles. Most accurate. <a href="/positions-import?tpl=1">Download the template</a>.</span></div>
    <div style="border:1px solid var(--line,#e5e9f0);border-radius:10px;padding:10px 12px"><b>📝 Paste text</b><br><span class="muted" style="font-size:12px">Tab- or comma-separated lines in the box below.</span></div>
    <div style="border:1px solid var(--line,#e5e9f0);border-radius:10px;padding:10px 12px"><b>📽️ PowerPoint / Visio</b><br><span class="muted" style="font-size:12px">Titles are read; set reporting lines on the preview.</span></div>
    <div style="border:1px solid <?= $aiPool ? 'var(--brand,#3f4fce)' : 'var(--line,#e5e9f0)' ?>;border-radius:10px;padding:10px 12px"><b>🖼️ Picture / scanned PDF <span class="pill p-info" style="font-size:9.5px">AI</span></b><br><span class="muted" style="font-size:12px">A photo or scan is read by AI.
      <?php if ($aiPool): ?>Uses <b>1</b> of your monthly AI actions (<?= (int) ai_pool_remaining() ?> of <?= (int) ai_effective_cap() ?> left this month).<?php else: ?>Needs an AI provider under Settings → AI.<?php endif; ?></span></div>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Upload or paste</h3>
  <p class="muted" style="font-size:12.5px;margin:0 0 10px">Table columns (a header row is detected automatically):
    <b>Title</b>, Department, Office, Grade, <b>Reports to</b> (the manager's title), Sanctioned, Occupied.
    “Reports to” is what draws the chart — leave it blank for the top of the organisation. Codes are generated for you.</p>
  <form method="post" enctype="multipart/form-data">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
      <label class="muted" style="font-size:12.5px">Upload a file:
        <input type="file" name="file" accept=".csv,.txt,.xlsx,.pptx,.vsdx,.png,.jpg,.jpeg,.webp,.gif,.pdf" style="font-size:12.5px"></label>
      <span class="muted" style="font-size:12px">Excel · CSV · PowerPoint · Visio · image · PDF</span>
    </div>
    <textarea class="form-control" name="data" rows="7" style="font-family:ui-monospace,Menlo,monospace;font-size:13px"
      placeholder="Managing Director, Executive, Head Office, E1, , 1, 1&#10;HR Head, Human Resources, Head Office, M1, Managing Director, 1, 1&#10;Recruiter, Human Resources, Mumbai, O1, HR Head, 3, 1"><?= $e($raw) ?></textarea>
    <div style="margin-top:10px"><button class="btn secondary" name="do" value="preview">Read &amp; preview</button></div>
  </form>
</div>

<?php if ($error !== ''): ?>
<div class="panel" style="border-left:4px solid var(--fail,#c0342b);background:#fef4f3">
  <b style="color:#8a231c">Could not read that.</b> <span style="font-size:13.5px"><?= $e($error) ?></span>
</div>
<?php endif; ?>

<?php if ($preview !== null && $error === ''): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Preview
    <span class="muted" style="font-weight:400;font-size:12px">— <?= count($preview) ?> role<?= count($preview) === 1 ? '' : 's' ?> read<?= $format ? ' from ' . $e($format) : '' ?>. Nothing is saved until you confirm.</span></h3>
  <?php if ($note): ?><p class="muted" style="font-size:12.5px;margin:0 0 10px">ℹ️ <?= $e($note) ?></p><?php endif; ?>
  <?php if (!$preview): ?>
    <p class="muted">Nothing recognised — check the file/columns and try again.</p>
  <?php else: ?>
    <?php if ($summary): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <span class="pill p-info">Will add <?= (int) $summary['positions'] ?> position<?= $summary['positions'] === 1 ? '' : 's' ?></span>
      <span class="pill <?= $summary['offices'] ? 'p-ok' : 'p-mut' ?>"><?= (int) $summary['offices'] ?> new office<?= $summary['offices'] === 1 ? '' : 's' ?></span>
      <span class="pill <?= $summary['designations'] ? 'p-ok' : 'p-mut' ?>"><?= (int) $summary['designations'] ?> new designation<?= $summary['designations'] === 1 ? '' : 's' ?></span>
      <span class="pill <?= $summary['departments'] ? 'p-ok' : 'p-mut' ?>"><?= (int) $summary['departments'] ?> new department<?= $summary['departments'] === 1 ? '' : 's' ?></span>
    </div>
    <?php endif; ?>
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;min-width:720px;font-size:13px">
      <tr style="text-align:left;color:var(--muted,#64748b);font-size:10.5px;text-transform:uppercase">
        <th style="padding:6px 8px">Title / designation</th><th style="padding:6px 8px">Department</th><th style="padding:6px 8px">Office</th>
        <th style="padding:6px 8px">Grade</th><th style="padding:6px 8px">Reports to</th><th style="padding:6px 8px">Seats</th></tr>
      <?php foreach ($preview as $r): $r = function_exists('orga_norm_row') ? orga_norm_row($r) : $r; ?>
        <tr style="border-top:1px solid var(--line,#eef1f5)">
          <td style="padding:6px 8px;font-weight:600"><?= $e($r['name']) ?></td>
          <td style="padding:6px 8px"><?= $e($r['department']) ?: '<span class="muted">—</span>' ?></td>
          <td style="padding:6px 8px"><?= $e($r['office']) ?: '<span class="muted">—</span>' ?></td>
          <td style="padding:6px 8px"><?= $e($r['grade']) ?: '<span class="muted">—</span>' ?></td>
          <td style="padding:6px 8px"><?= $r['reports_to'] !== '' ? $e($r['reports_to']) : '<span class="muted">— top —</span>' ?></td>
          <td style="padding:6px 8px"><?= (int) $r['occupied'] ?> / <?= (int) ($r['sanctioned'] ?: 1) ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <form method="post" style="margin-top:14px">
      <input type="hidden" name="rows_json" value="<?= $e($rowsJson) ?>">
      <button class="btn" name="do" value="apply" onclick="return confirm('Create these offices, designations and positions now?')">✓ Confirm &amp; import <?= count($preview) ?> role<?= count($preview) === 1 ? '' : 's' ?></button>
      <span class="muted" style="font-size:12px;margin-left:8px">You can edit the file and preview again before confirming.</span>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
