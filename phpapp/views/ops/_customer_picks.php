<?php
// Owner, 28 Nov 2026 — two or more customers on file have the typed name: which one is it?
// Asked ONCE, on the form being saved (lead, quick lead, deal, enquiry), never later.
// "Use this customer" sends use_partner=<id>; "It is a new company" sends new_company=1
// (the typed name is kept). Included with $pickList = [ {id, code, name, city}, … ].
$pickList = $pickList ?? [];
if (!$pickList) return;
?>
<div class="cm-picks msg msg-warning" role="alert" style="margin:8px 0 0">
  <b>More than one customer on file has this name — which one is it?</b>
  <ul style="margin:6px 0 0;padding-left:18px">
    <?php foreach ($pickList as $pk): ?>
      <li style="margin:4px 0"><?= e(trim($pk['code'] . ' · ' . $pk['name'])) ?><?= $pk['city'] !== '' ? ' <span class="muted">(' . e($pk['city']) . ')</span>' : '' ?>
        <button class="btn small" type="submit" name="use_partner" value="<?= (int) $pk['id'] ?>" formnovalidate>Use this customer</button></li>
    <?php endforeach; ?>
  </ul>
  <button class="btn small secondary" type="submit" name="new_company" value="1" style="margin-top:6px">It is a new company</button>
</div>
