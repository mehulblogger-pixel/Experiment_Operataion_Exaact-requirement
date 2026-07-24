<div class="crumbs"><a href="/">Home</a> › <a href="/quotes">Quotations</a> › Templates</div>
<div class="master-head">
  <div><h1>Quote &amp; e-mail templates</h1>
    <p class="sub" style="margin:2px 0 0">Upload your Word quotation format. The generated quote adopts it automatically and stamps the document / format number from the format.</p></div>
  <a class="btn" href="/crm-template-new">+ Add template</a>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Name</th><th>Kind</th><th>Document no.</th><th>Format no.</th><th>Rev</th><th>Issued</th><th>File</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['name'] ?: '—') ?></b> <?= $r['is_default']?'<span class="pill p-ok">default</span>':'' ?></td>
      <td><?= e(CRM_TEMPLATE_KINDS[$r['kind']] ?? $r['kind']) ?></td>
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
    <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;padding:24px" class="muted">No templates yet — click "Add template" and upload your Word quotation format.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
