<div class="crumbs"><a href="/">Home</a> › <a href="/quotes"><?= e(TP('quote')) ?></a> › <a href="/crm-templates">Templates</a> › <?= $t ? 'Edit' : 'Add' ?></div>
<div class="master-head">
  <div><h1><?= $t ? 'Edit template — ' . e($t['name']) : 'Add quote / e-mail template' ?></h1></div>
  <a class="btn secondary" href="/crm-templates">← Back</a>
</div>

<form method="post" action="/<?= $t ? 'crm-template-edit?id=' . (int)$t['id'] : 'crm-template-new' ?>" class="panel" enctype="multipart/form-data">
  <div class="form-grid">
    <div class="ff"><label>Template name *</label><input class="form-control" name="name" required value="<?= e($t['name'] ?? '') ?>" placeholder="e.g. Standard <?= e(Tl('quote')) ?> format"></div>
    <div class="ff"><label>Kind</label>
      <select class="form-control" name="kind"><?php foreach ($kinds as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($t['kind'] ?? 'QUOTE_DOC')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff" style="align-self:end">
      <label class="chk"><input type="checkbox" name="is_default" value="1" <?= ($t['is_default'] ?? 0)?'checked':'' ?>> Default for this kind</label>
      <label class="chk" style="margin-top:6px"><input type="checkbox" name="active" value="1" <?= ($t['active'] ?? 1)?'checked':'' ?>> Active</label>
    </div>

    <div class="ff"><label>Document number <span class="muted">(from this format)</span></label><input class="form-control" name="document_number" value="<?= e($t['document_number'] ?? '') ?>" placeholder="e.g. QT/DOC-014"></div>
    <div class="ff"><label>Format number</label><input class="form-control" name="format_number" value="<?= e($t['format_number'] ?? '') ?>" placeholder="e.g. F-MKT-07"></div>
    <div class="ff"><label>Format revision</label><input class="form-control" name="doc_revision" value="<?= e($t['doc_revision'] ?? '') ?>" placeholder="e.g. Rev 03"></div>
    <div class="ff"><label>Issue / effective date</label><input class="form-control" type="date" name="issue_date" value="<?= e($t['issue_date'] ?? '') ?>"></div>
  </div>

  <div class="ff ff-wide" style="margin-top:6px">
    <label>Word format file (.docx) <?= ($t && $t['file_name']) ? '<span class="muted">— current: '.e($t['file_name']).' (leave blank to keep)</span>' : '' ?></label>
    <input class="form-control" type="file" name="file" accept=".docx">
    <small class="muted">Upload your controlled Word <?= e(Tl('quote')) ?> format. Put the tokens below where you want the data to appear — the system fills them in and stamps the document / format number above. Re-upload anytime to switch to a revised format.</small>
  </div>

  <div class="ff ff-wide" id="emailfields" style="margin-top:6px">
    <label>E-mail subject <span class="muted">(for e-mail templates)</span></label><input class="form-control" name="subject" value="<?= e($t['subject'] ?? '') ?>">
    <label style="margin-top:8px">E-mail body</label><textarea class="form-control" name="body" rows="4"><?= e($t['body'] ?? '') ?></textarea>
  </div>

  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $t ? 'Save template' : 'Add template' ?></button>
    <a class="btn secondary" href="/crm-templates">Cancel</a>
  </div>
</form>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Tokens you can put in the Word format</h3>
  <p class="sub">Type these exactly (with the double braces) anywhere in your .docx — including the header/footer. For the line-item table, put the <code>l_</code> tokens inside <strong>one</strong> table row; that row repeats for every line automatically.</p>
  <?php foreach (quote_doc_tokens() as $group=>$toks): ?>
    <div style="margin-bottom:10px">
      <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px"><?= e($group) ?></div>
      <div class="chip-row">
        <?php foreach ($toks as $tk): ?><span class="ct" style="font-family:monospace">{{<?= e($tk) ?>}}</span><?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
