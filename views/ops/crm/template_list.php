<div class="crumbs"><a href="/">Home</a> › <a href="/templates">Document templates</a> › <?= e(T('quote')) ?> &amp; e-mail</div>
<div class="master-head">
  <div><h1>Document templates</h1>
    <p class="sub" style="margin:2px 0 0">Upload your Word <?= e(Tl('quote')) ?> format (for editing) and the signature that appears on the <strong><?= e(Tl('client')) ?> PDF</strong>. The generated <?= e(Tl('quote')) ?> stamps the document / format number from the format.</p></div>
  <a class="btn" href="/crm-template-new">+ Add template</a>
</div>
<?= template_tabs('/templates?kind=quote') ?>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Letterhead for client PDF <span class="muted">— per company</span></h3>
  <p class="sub">The <?= e(Tl('client')) ?> receives the <?= e(Tl('quote')) ?> as a PDF on this letterhead. Fully customisable to your company.</p>
  <form method="post" action="/crm-letterhead" enctype="multipart/form-data">
    <div class="form-grid">
      <div class="ff"><label>Company logo (PNG / JPG) <?= !empty($lhLogo)?'<span class="pill p-ok">on file</span>':'' ?></label><input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg"></div>
      <div class="ff"><label>Company name</label><input class="form-control" name="lh_name" value="<?= e($lhName ?? '') ?>" placeholder="e.g. Your Company Pvt Ltd"></div>
      <div class="ff"><label>Contact line <span class="muted">(phone · email · web · GSTIN)</span></label><input class="form-control" name="lh_contact" value="<?= e($lhContact ?? '') ?>" placeholder="+91 … · sales@… · www… · GSTIN …"></div>
    </div>
    <div class="ff ff-wide"><label>Address (one line each)</label><textarea class="form-control" name="lh_address" rows="2" placeholder="Building / street&#10;City, State – PIN"><?= e($lhAddress ?? '') ?></textarea></div>
    <div class="ff ff-wide"><label>Footer note (optional)</label><input class="form-control" name="lh_footer" value="<?= e($lhFooter ?? '') ?>" placeholder="e.g. This is a computer-generated <?= e(Tl('quote')) ?>."></div>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <button class="btn" type="submit">Save letterhead</button>
      <?php if (!empty($lhLogo)): ?><label class="chk"><input type="checkbox" name="clear_logo" value="1"> Remove current logo</label><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Signature for client PDFs</h3>
  <p class="sub">The <?= e(Tl('client')) ?> receives the <?= e(Tl('quote')) ?> as a <strong>PDF</strong>; this signature image + name are stamped on it.</p>
  <form method="post" action="/crm-signature" enctype="multipart/form-data">
    <div class="form-grid">
      <div class="ff"><label>Signature image (PNG / JPG) <?= !empty($sigSet)?'<span class="pill p-ok">on file</span>':'' ?></label><input class="form-control" type="file" name="sig" accept=".png,.jpg,.jpeg"></div>
      <div class="ff"><label>Signatory name</label><input class="form-control" name="sig_name" value="<?= e($sigName ?? '') ?>" placeholder="e.g. R. Sharma"></div>
      <div class="ff"><label>Designation</label><input class="form-control" name="sig_desig" value="<?= e($sigDesig ?? '') ?>" placeholder="e.g. Marketing Manager"></div>
    </div>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <button class="btn" type="submit">Save signature</button>
      <?php if (!empty($sigSet)): ?><label class="chk"><input type="checkbox" name="clear_sig" value="1"> Remove current image</label><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Name</th><th>Kind</th><th>Document no.</th><th>Format no.</th><th>Rev</th><th>Issued</th><th>File</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['name'] ?: '—') ?></b> <?= $r['is_default']?'<span class="pill p-ok">default</span>':'' ?></td>
      <td><?= e(lk_options_or('template_kind', CRM_TEMPLATE_KINDS)[$r['kind']] ?? $r['kind']) ?></td>
      <td><?= e($r['document_number'] ?: '—') ?></td>
      <td><?= e($r['format_number'] ?: '—') ?></td>
      <td><?= e($r['doc_revision'] ?: '—') ?></td>
      <td><?= e($r['issue_date'] ?: '—') ?></td>
      <td><?= $r['file_name'] ? '<a href="/crm-template-download?id='.(int)$r['id'].'">⬇ '.e($r['file_name']).'</a>' : '<span class="muted">—</span>' ?></td>
      <td><span class="pill <?= $r['active']?'p-ok':'p-mut' ?>"><?= $r['active']?'Active':'Off' ?></span></td>
      <td class="num" style="white-space:nowrap">
        <a class="btn small secondary" href="/crm-template-edit?id=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" action="/crm-template-delete?id=<?= (int)$r['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this template?')"><button class="btn small danger" type="submit">✕</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;padding:24px" class="muted">No templates yet — click "Add template" and upload your Word <?= e(Tl('quote')) ?> format.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
