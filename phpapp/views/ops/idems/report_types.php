<?php // Report-type registry — admin adds unlimited types ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › Report types</div>
<div class="master-head"><div><h1><?= e(TH('report')) ?> types</h1>
  <p class="sub" style="margin:2px 0 0">The catalogue of report types. Built-in TPIA types are seeded; add your own with no coding.</p></div></div>

<form method="post" action="/report-types" class="panel">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <h3 class="tab-sub" style="margin-top:0"><?= $edit ? 'Edit type' : 'Add a report type' ?></h3>
  <div class="form-grid">
    <div class="ff"><label>Code *</label><input class="form-control" name="code" value="<?= e($edit['code'] ?? '') ?>" placeholder="e.g. IR" maxlength="16" required></div>
    <div class="ff"><label>Name *</label><input class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Inspection Report" required></div>
    <div class="ff"><label>Category</label>
      <select class="form-control" name="category"><?php foreach (lk_options_or('report_category', IDEMS_CATEGORIES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['category'] ?? 'TPIA_REPORT')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff ff-check"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>><label>Active</label></div>
  </div>
  <p class="muted" style="margin:10px 0 4px;font-size:12px">Document control — printed on the report header (your own format, no Word file needed):</p>
  <div class="form-grid">
    <div class="ff"><label>Format No.</label><input class="form-control" name="format_number" value="<?= e($edit['format_number'] ?? '') ?>" placeholder="e.g. RE-IN-I&amp;E-INS-011"></div>
    <div class="ff"><label>Document No.</label><input class="form-control" name="doc_control_no" value="<?= e($edit['doc_control_no'] ?? '') ?>" placeholder="e.g. QF-07"></div>
    <div class="ff"><label>Revision</label><input class="form-control" name="revision" value="<?= e($edit['revision'] ?? '') ?>" placeholder="e.g. 0"></div>
  </div>
  <div style="margin-top:12px"><button class="btn" type="submit"><?= $edit ? 'Save' : 'Add type' ?></button><?php if ($edit): ?> <a class="btn secondary" href="/report-types">Cancel</a><?php endif; ?></div>
</form>

<?php if (!$edit): ?>
<div class="panel" style="margin-top:14px;border:1px solid var(--brand)">
  <h3 class="tab-sub" style="margin-top:0">⭐ Ready-made inspection report</h3>
  <p class="muted" style="margin:0 0 10px;font-size:12.5px">A complete inspection report with every section already wired — autofilled header, reference documents, PO items, ITP scope, master-linked instruments, hold-point status, observations &amp; conclusion, photographs, disclaimer and a Prepared / Reviewed / Approved sign-off. Nothing to design; just start writing reports (or fine-tune it).</p>
  <form method="post" action="/report-types" style="margin:0"><input type="hidden" name="_do" value="build_mgh">
    <button class="btn" type="submit">Set up the ready inspection report</button>
  </form>
</div>

<div class="panel" style="margin-top:12px">
  <h3 class="tab-sub" style="margin-top:0">🔧 Repair split table rows</h3>
  <p class="muted" style="margin:0 0 10px;font-size:12.5px">If a report saved earlier shows each table value on its own line (a “staircase”), this stitches those split rows back into whole rows. Safe — it only touches tables where every row has a single value, and never changes an issued report.</p>
  <form method="post" action="/report-types" style="margin:0" onsubmit="return confirm('Repair split table rows across all draft reports now?')"><input type="hidden" name="_do" value="repair_tables">
    <button class="btn secondary" type="submit">Repair split table rows</button>
  </form>
</div>
<?php endif; ?>

<?php
  $fieldCounts = $fieldCounts ?? []; $docCounts = $docCounts ?? [];
  $readyN = 0; $emptyN = 0;
  foreach ($rows as $r) { if (($fieldCounts[(int)$r['id']] ?? 0) > 0) $readyN++; elseif ($r['active']) $emptyN++; }
?>
<div class="panel" style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
  <span class="pill p-ok" style="font-size:12.5px"><?= $readyN ?> ready to fill</span>
  <?php if ($emptyN): ?><span class="pill p-warn" style="font-size:12.5px"><?= $emptyN ?> active but no form yet</span><?php endif; ?>
  <span class="muted" style="font-size:12.5px">“Ready to fill” means the form is designed — opening it goes straight to writing. “No form yet” opens a blank screen until you design it.</span>
</div>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Form</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $nf = $fieldCounts[(int)$r['id']] ?? 0; $nd = $docCounts[(int)$r['id']] ?? 0; $ready = $nf > 0; ?>
      <tr<?= (!$ready && $r['active']) ? ' style="background:rgba(214,158,46,.06)"' : '' ?>>
        <td><strong><?= e($r['code']) ?></strong></td>
        <td><?= e($r['name']) ?><?= $r['is_system'] ? ' <span class="muted" style="font-size:11px">(built-in)</span>' : '' ?>
          <?php if ($nd): ?><br><span class="muted" style="font-size:11px"><?= $nd ?> report<?= $nd==1?'':'s' ?> filed</span><?php endif; ?></td>
        <td class="muted"><?= e(lk_options_or('report_category', IDEMS_CATEGORIES)[$r['category']] ?? $r['category']) ?></td>
        <td>
          <?php if ($ready): ?><span class="pill p-ok" title="This report has a designed form">✓ Ready · <?= (int)$nf ?> field<?= $nf==1?'':'s' ?></span>
          <?php else: ?><span class="pill p-warn" title="No form designed — opens a blank screen">⚠ No form yet</span><?php endif; ?>
        </td>
        <td><?= $r['active'] ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-mut">Inactive</span>' ?></td>
        <td style="white-space:nowrap">
          <?php if ($ready): ?>
            <a class="btn small secondary" href="/report-builder?type=<?= (int)$r['id'] ?>">Edit form</a>
            <a class="btn small secondary" href="/report-type-preview?type=<?= (int)$r['id'] ?>" target="_blank">Preview</a>
          <?php else: ?>
            <a class="btn small" href="/report-builder?type=<?= (int)$r['id'] ?>">Design form</a>
          <?php endif; ?>
          <a class="btn small secondary" href="/report-type-edit?id=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" action="/report-types" style="display:inline" onsubmit="return confirm('<?= $r['is_system']?'Deactivate this built-in type?':'Remove this type?' ?>')"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" type="submit"><?= $r['is_system']?'Deactivate':'Remove' ?></button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
