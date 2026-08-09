<?php
$ed = $edit;
$sbadge = ['REQUESTED' => 'AMBER', 'CONSENTED' => 'GREEN', 'DECLINED' => 'RED', 'WITHDRAWN' => 'GREY'];
?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Public-disclosure consent</div>
<div class="master-head"><div><h1>Public-disclosure consent</h1>
  <p class="sub">Where the body intends to make a client's information public, the client's advance consent is recorded here (ISO §4.2)</p></div></div>

<form method="get" action="/disclosure" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search client / subject…">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q): ?><a class="btn secondary" href="/disclosure">Clear</a><?php endif; ?>
</form>

<table class="grid">
  <tr><th>Client</th><th>Information to be disclosed</th><th>Where / to whom</th><th>Status</th><th>Consented</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><strong><?= e($r['partner_name'] ?: '—') ?></strong></td>
    <td><?= e($r['subject']) ?><?php if ($r['note']): ?><br><span class="muted" style="font-size:12px"><?= e($r['note']) ?></span><?php endif; ?></td>
    <td><?= e($r['scope'] ?: '—') ?></td>
    <td><span class="badge <?= $sbadge[$r['status']] ?? 'GREY' ?>"><?= e(ucfirst(strtolower($r['status']))) ?></span></td>
    <td><?= e($r['status'] === 'CONSENTED' ? trim($r['consent_on'] . ' ' . $r['consent_by']) : '—') ?></td>
    <?php if ($canEdit): ?><td class="row-actions">
      <a class="btn small secondary" href="/disclosure?edit=<?= (int)$r['id'] ?>">Edit</a>
      <form method="post" action="/disclosure-del" style="display:inline" onsubmit="return confirm('Remove this entry?')">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small danger" type="submit">Remove</button>
      </form>
    </td><?php endif; ?>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= $canEdit ? 6 : 5 ?>" class="muted" style="padding:16px">No disclosures recorded.</td></tr><?php endif; ?>
</table>

<?php if ($canEdit): ?>
<div class="panel" style="margin-top:22px;padding:18px">
  <h3 style="margin-top:0"><?= $ed ? 'Edit entry' : 'Record a disclosure &amp; its consent' ?></h3>
  <form method="post" action="<?= $ed ? '/disclosure-edit?id=' . (int)$ed['id'] : '/disclosure-new' ?>" class="settings-form">
    <div class="form-grid">
      <div class="ff"><label>Client <a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a></label><select class="form-control" name="partner_id">
        <option value="">— select —</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)($ed['partner_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['nm']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="ff"><label>Status</label><select class="form-control" name="status">
        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= ($ed['status'] ?? 'REQUESTED') === $s ? ' selected' : '' ?>><?= e(ucfirst(strtolower($s))) ?></option><?php endforeach; ?>
      </select></div>
      <div class="ff" style="grid-column:1/-1"><label>Information to be disclosed *</label>
        <input class="form-control" name="subject" value="<?= e($ed['subject'] ?? '') ?>" required placeholder="e.g. Company name on the public certificate register"></div>
      <div class="ff" style="grid-column:1/-1"><label>Where / to whom</label>
        <input class="form-control" name="scope" value="<?= e($ed['scope'] ?? '') ?>" placeholder="e.g. Accreditation body public directory / our website"></div>
      <div class="ff"><label>Consent obtained on</label>
        <input class="form-control" type="date" name="consent_on" value="<?= e($ed['consent_on'] ?? '') ?>"></div>
      <div class="ff"><label>Consent given by (name)</label>
        <input class="form-control" name="consent_by" value="<?= e($ed['consent_by'] ?? '') ?>"></div>
      <div class="ff" style="grid-column:1/-1"><label>Note</label>
        <input class="form-control" name="note" value="<?= e($ed['note'] ?? '') ?>"></div>
    </div>
    <div style="margin-top:12px"><button class="btn" type="submit"><?= $ed ? 'Save' : 'Add entry' ?></button>
      <?php if ($ed): ?><a class="btn secondary" href="/disclosure">Cancel</a><?php endif; ?></div>
  </form>
</div>
<?php endif; ?>
