<?php // Report templates — upload client-specific .docx formats and map fields with tokens. ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/templates">Document templates</a> › <?= e(T('report')) ?> formats</div>
<div class="master-head"><div><h1>Document templates</h1>
  <p class="sub" style="margin:2px 0 0">Upload a <?= e(Tl('client')) ?>'s approved Word format and place <code>{{tokens}}</code> where values go. Generated <?= e(Tlp('report')) ?> come out in exactly that format — fonts, headers, footers, logo and tables preserved. The most specific template (by <?= e(Tl('report')) ?> type, <?= e(Tl('client')) ?>, <?= e(T('office')) ?>) is used automatically.</p></div></div>
<?= template_tabs('/templates?kind=report') ?>

<?php if (empty($zipOk)): ?><div class="panel" style="border:1px solid var(--bad)"><b style="color:var(--bad)">Note:</b> the PHP <code>zip</code> extension isn't enabled here, so .docx generation won't run until it's turned on. You can still upload &amp; manage templates.</div><?php endif; ?>

<div class="panel" style="border-left:3px solid var(--brand);background:var(--soft)">
  <h3 class="tab-sub" style="margin-top:0">🪄 Easiest — build a form from your plain Word file <span class="pill p-ok">no codes</span></h3>
  <p class="sub" style="margin:0 0 10px">Don't want to insert <code>{{tokens}}</code>? Just upload your ordinary report format and the app finds the
    fields for you — every “Label:” line and blank table cell. You review them, then it's ready. The <code>{{token}}</code>
    method below is only for when you'd rather place fields by hand.</p>
  <?php if (!empty($types)): ?>
    <form method="get" action="/report-autoform" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <select class="form-control" name="type" required style="max-width:340px">
        <option value="">Which report is this format for?…</option>
        <?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Upload my Word file &amp; build the form →</button>
    </form>
  <?php else: ?>
    <p class="muted" style="margin:0">Add a report type first (under <a href="/report-types">Report types</a>), then come back to build its form from a file.</p>
  <?php endif; ?>
</div>

<div class="dash-2col" style="align-items:start">
  <div>
    <form method="post" action="/report-templates" enctype="multipart/form-data" class="panel">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <h3 class="tab-sub" style="margin-top:0"><?= $edit ? 'Edit template' : 'Add a template' ?></h3>
      <div class="ff"><label>Name</label><input class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Client A — Inspection Report format"></div>
      <div class="form-grid">
        <div class="ff"><label>Report type <span class="muted">(blank = any)</span></label>
          <select class="form-control searchable" name="report_type_id"><option value="">Any</option><?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= ($edit && (int)$edit['report_type_id']===(int)$t['id'])?'selected':'' ?>><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Client <span class="muted">(blank = any)</span></label>
          <select class="form-control searchable" name="client_id"><option value="">Any</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($edit && (int)$edit['client_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Office <span class="muted">(blank = any)</span></label>
          <select class="form-control searchable" name="office_id"><option value="">Any</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($edit && (int)$edit['office_id']===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
        <div class="ff ff-check"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>><label>Active</label></div>
      </div>
      <h4 style="margin:8px 0 4px">Document control numbers <span class="muted" style="font-weight:400">(become {{doc_number}}, {{format_number}}, {{doc_revision}})</span></h4>
      <div class="form-grid">
        <div class="ff"><label>Document number</label><input class="form-control" name="document_number" value="<?= e($edit['document_number'] ?? '') ?>"></div>
        <div class="ff"><label>Format number</label><input class="form-control" name="format_number" value="<?= e($edit['format_number'] ?? '') ?>"></div>
        <div class="ff"><label>Revision</label><input class="form-control" name="doc_revision" value="<?= e($edit['doc_revision'] ?? '') ?>"></div>
        <div class="ff"><label>Format issue date</label><input class="form-control" name="issue_date" value="<?= e($edit['issue_date'] ?? '') ?>" placeholder="e.g. 01-04-2026"></div>
      </div>
      <div class="ff"><label>Word template (.docx) <?= $edit && $edit['file_name'] ? '<span class="muted">— current: '.e($edit['file_name']).'</span>' : '' ?></label>
        <input class="form-control" type="file" name="tpl" accept=".docx"></div>
      <div class="ff ff-check"><input type="checkbox" name="is_default" <?= ($edit && $edit['is_default'])?'checked':'' ?>><label>Prefer this when several match</label></div>
      <div style="margin-top:10px"><button class="btn" type="submit"><?= $edit ? 'Save template' : 'Add template' ?></button><?php if ($edit): ?> <a class="btn secondary" href="/report-templates">Cancel</a><?php endif; ?></div>
    </form>
  </div>

  <div>
    <div class="panel">
      <h3 class="tab-sub" style="margin-top:0">Token reference</h3>
      <form method="get" action="/report-templates" style="margin-bottom:8px"><select class="form-control" name="tokens" onchange="this.form.submit()"><option value="">Pick a report type to see its field tokens…</option><?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$tokRefType===(int)$t['id']?'selected':'' ?>><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?></select></form>
      <p class="muted" style="margin:0 0 6px">Put these in your Word file, e.g. <code>{{irn}}</code>, <code>{{client}}</code>. Empty ones simply disappear.</p>
      <div><strong>Standard</strong><div class="tok-list"><?php foreach (($tokens['standard'] ?? ['irn','client','project','inspection_date','issue_date','inspector','approver','result','doc_number','format_number','doc_revision']) as $tk): ?><code>{{<?= e($tk) ?>}}</code> <?php endforeach; ?></div></div>
      <?php if (!empty($tokens['fields'])): ?><div style="margin-top:8px"><strong>This report's fields</strong><div class="tok-list"><?php foreach ($tokens['fields'] as $tk): ?><code>{{<?= e($tk) ?>}}</code> <?php endforeach; ?></div></div><?php endif; ?>
      <?php if (!empty($tokens['tables'])): ?><div style="margin-top:8px"><strong>Table rows</strong> <span class="muted">(put in one table row; it repeats per row)</span><div class="tok-list"><?php foreach ($tokens['tables'] as $tk): ?><code>{{<?= e($tk) ?>}}</code> <?php endforeach; ?></div></div><?php endif; ?>
    </div>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Name</th><th>Report type</th><th>Client</th><th>Office</th><th>File</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:14px">No templates yet. Reports fall back to the built-in PDF.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['name'] ?: '—') ?></strong><?= $r['is_default'] ? ' <span class="pill p-info" style="padding:0 5px">preferred</span>' : '' ?></td>
        <td><?= $r['type_code'] ? e($r['type_code']) : '<span class="muted">Any</span>' ?></td>
        <td><?= $r['client_name'] ? e($r['client_name']) : '<span class="muted">Any</span>' ?></td>
        <td><?= $r['office_name'] ? e($r['office_name']) : '<span class="muted">Any</span>' ?></td>
        <td><?= $r['file_name'] ? '<a href="/report-template-download?id='.(int)$r['id'].'">'.e($r['file_name']).'</a>' : '<span class="muted">none</span>' ?></td>
        <td><?= $r['active'] ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-mut">Off</span>' ?></td>
        <td style="white-space:nowrap">
          <?php if ($r['file_data'] && $r['report_type_id']): ?><a class="btn small" href="/report-form-from-template?id=<?= (int)$r['id'] ?>" title="Create the form fields from the tokens in this format">🪄 Build form</a><?php endif; ?>
          <a class="btn small secondary" href="/report-template-edit?id=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" action="/report-templates" style="display:inline" onsubmit="return confirm('Remove template?')"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary">✕</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<style>.tok-list code{display:inline-block;margin:2px 3px;font-size:11px}</style>
