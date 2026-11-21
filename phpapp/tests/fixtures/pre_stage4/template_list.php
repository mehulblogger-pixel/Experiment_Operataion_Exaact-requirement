<div class="master-head">
  <div><h1>Document templates</h1>
    <p class="sub" style="margin:2px 0 0">Upload your Word <?= e(Tl('quote')) ?> format (for editing) and the signature that appears on the <strong><?= e(Tl('client')) ?> PDF</strong>. The generated <?= e(Tl('quote')) ?> stamps the document / format number from the format.</p></div>
  <a class="btn" href="/crm-template-new">+ Add template</a>
</div>
<?= template_tabs('/templates?kind=quote') ?>

<?php // What is saved, shown as it prints (owner, 9 Nov 2026).
  $savedLine = function ($v) { if ((string) $v === '') return ''; [$at, $by] = array_pad(explode('|', (string) $v, 2), 2, '');
      return 'Saved ' . (function_exists('fdate') ? fdate(substr($at, 0, 10)) : substr($at, 0, 10)) . ' ' . substr($at, 11, 5) . ($by !== '' ? ' by ' . $by : ''); };
  $img = fn($b64) => (string) $b64 !== '' ? 'data:image/jpeg;base64,' . preg_replace('~[^A-Za-z0-9+/=]~', '', (string) $b64) : '';
  $prev = 'margin-top:14px;border:1px dashed var(--line,#d7dde5);border-radius:10px;padding:14px 16px;background:var(--card,#fff)';
  $lhD = (array) ($_SESSION['crm_lh_draft'] ?? []); unset($_SESSION['crm_lh_draft']);
  $lhF = ['name' => $lhD['name'] ?? ($lhName ?? ''), 'address' => $lhD['address'] ?? ($lhAddress ?? ''), 'contact' => $lhD['contact'] ?? ($lhContact ?? ''), 'footer' => $lhD['footer'] ?? ($lhFooter ?? '')]; ?>
<div class="panel" id="letterhead" style="scroll-margin-top:70px">
  <h3 class="tab-sub" style="margin-top:0">Letterhead for client PDF <span class="muted">— per company</span></h3>
  <p class="sub">The <?= e(Tl('client')) ?> receives the <?= e(Tl('quote')) ?> as a PDF on this letterhead. Fully customisable to your company.</p>
  <form method="post" action="/crm-letterhead" enctype="multipart/form-data" novalidate>
    <div class="form-grid">
      <div class="ff"><label>Company logo (PNG / JPG) <?= !empty($lhLogo)?'<span class="pill p-ok">on file</span>':'' ?></label><input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg"></div>
      <div class="ff"><label>Company name *</label><input class="form-control" name="lh_name" value="<?= e($lhF['name']) ?>" placeholder="e.g. Your Company Pvt Ltd"></div>
      <div class="ff"><label>Contact line <span class="muted">(phone · email · web · GSTIN)</span></label><input class="form-control" name="lh_contact" value="<?= e($lhF['contact']) ?>" placeholder="+91 … · sales@… · www… · GSTIN …"></div>
    </div>
    <div class="ff ff-wide"><label>Address (one line each) *</label><textarea class="form-control" name="lh_address" rows="2" placeholder="Building / street&#10;City, State – PIN"><?= e($lhF['address']) ?></textarea></div>
    <div class="ff ff-wide"><label>Footer note (optional)</label><input class="form-control" name="lh_footer" value="<?= e($lhF['footer']) ?>" placeholder="e.g. This is a computer-generated <?= e(Tl('quote')) ?>."></div>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <button class="btn" type="submit">Save letterhead</button>
      <?php if (!empty($lhLogo)): ?><label class="chk"><input type="checkbox" name="clear_logo" value="1"> Remove current logo</label><?php endif; ?>
    </div>
  </form>
  <div style="<?= $prev ?>">
    <div class="muted" style="font-size:12px;margin-bottom:8px"><b>Saved — this is what prints</b><?= ($lhSaved ?? '') !== '' ? ' · ' . e($savedLine($lhSaved)) : '' ?></div>
    <?php if (($lhName ?? '') === '' && ($lhAddress ?? '') === '' && empty($lhLogo)): ?>
      <div class="muted" style="font-size:13px">Nothing saved yet — the PDF uses your company profile until you save a letterhead.</div>
    <?php else: ?>
      <div style="display:flex;gap:14px;align-items:flex-start;border-bottom:2px solid var(--ink,#111827);padding-bottom:10px">
        <?php if (($u = $img($lhLogoData ?? '')) !== ''): ?><img src="<?= e($u) ?>" alt="Logo" style="max-height:56px;max-width:160px;object-fit:contain"><?php endif; ?>
        <div><div style="font-weight:800;font-size:17px"><?= e($lhName ?? '') ?></div>
          <div style="font-size:12.5px;white-space:pre-line"><?= e($lhAddress ?? '') ?></div>
          <?php if (($lhContact ?? '') !== ''): ?><div class="muted" style="font-size:12px"><?= e($lhContact) ?></div><?php endif; ?></div>
      </div>
      <?php if (($lhFooter ?? '') !== ''): ?><div class="muted" style="font-size:11.5px;margin-top:6px">Footer: <?= e($lhFooter) ?></div><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub" id="signature" style="margin-top:0">Signature for client PDFs</h3>
  <p class="sub">The <?= e(Tl('client')) ?> receives the <?= e(Tl('quote')) ?> as a <strong>PDF</strong>; this signature image + name are stamped on it.</p>
  <form method="post" action="/crm-signature" enctype="multipart/form-data" novalidate>
    <?php $sd = (array) ($_SESSION['crm_sig_draft'] ?? []); unset($_SESSION['crm_sig_draft']);
          if (isset($sd['name'])) $sigName = $sd['name']; if (isset($sd['desig'])) $sigDesig = $sd['desig']; ?>
    <div class="form-grid">
      <div class="ff"><label>Signature image (PNG / JPG) *<?= !empty($sigSet)?' <span class="pill p-ok">on file</span>':'' ?></label><input class="form-control" type="file" name="sig" accept=".png,.jpg,.jpeg"<?= empty($sigSet) ? ' required' : '' ?>></div>
      <div class="ff"><label>Signatory name *</label><input class="form-control" name="sig_name" required value="<?= e($sigName ?? '') ?>" placeholder="e.g. R. Sharma"></div>
      <div class="ff"><label>Designation *</label><input class="form-control" name="sig_desig" required value="<?= e($sigDesig ?? '') ?>" placeholder="e.g. Marketing Manager"></div>
    </div>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <button class="btn" type="submit">Save signature</button>
      <?php if (!empty($sigSet)): ?><button class="btn ghost" type="submit" name="clear_sig" value="1" formnovalidate onclick="return confirm('Remove the company signature from quote PDFs?')">Remove the company signature</button><?php endif; ?>
    </div>
  </form>
  <div style="<?= $prev ?>">
    <div class="muted" style="font-size:12px;margin-bottom:8px"><b>Saved — this is what prints</b><?= ($sigSaved ?? '') !== '' ? ' · ' . e($savedLine($sigSaved)) : '' ?></div>
    <?php if (empty($sigSet)): ?>
      <div class="muted" style="font-size:13px">No company signature saved — each quote carries only the signature of the person who signs it.</div>
    <?php else: ?>
      <div style="display:inline-block;text-align:left">
        <?php if (($u = $img($sigImgData ?? '')) !== ''): ?><img src="<?= e($u) ?>" alt="Signature" style="max-height:60px;max-width:220px;object-fit:contain;display:block"><?php endif; ?>
        <div style="border-top:1px solid var(--ink,#111827);margin-top:4px;padding-top:4px;font-weight:700;font-size:14px"><?= e($sigName ?? '') ?></div>
        <div class="muted" style="font-size:12.5px"><?= e($sigDesig ?? '') ?></div>
      </div>
    <?php endif; ?>
  </div>
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
