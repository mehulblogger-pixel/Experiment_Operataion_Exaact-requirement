<?php
// A customer's billing locations (owner, 21 Nov 2026: D1–D4) — the main one
// (the record's own GSTIN) and every further state it is registered in.
//   $p — the business_partners row
if (!function_exists('custid_locations') || empty($p) || empty($p['is_client'])) return;
custid_migrate();
$pp = ops_one("SELECT * FROM business_partners WHERE id=?", [(int) $p['id']]) ?: $p;
$locs = custid_locations((int) $pp['id']);
$canLoc = custid_can_edit($pp);
$stLabel = CUSTID_STATUS[strtoupper((string) ($pp['tax_status'] ?? ''))] ?? '';
$complete = custid_complete($pp);
$mv = !empty($pp['parent_id']) ? custid_move_preview((int) $pp['id']) : null;
// Which page this box sits on, so Add / Change / Stop land back there (owner,
// 25 Nov 2026: Q3 — the same box on the master record and the Sales customer page).
$blBack = (isset($blBack) && $blBack === 'customer') ? 'customer' : 'partner';
$blBackIn = '<input type="hidden" name="back" value="' . $blBack . '">';
?>
<section class="panel" id="billing-locations" style="margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Billing addresses <span class="muted" style="font-weight:400;font-size:12.5px">— one <?= e(Tl('client')) ?>, one PAN<?= trim((string) $pp['pan']) !== '' ? ' (' . e($pp['pan']) . ')' : '' ?>, billed at each GSTIN and office address it uses</span></h3>
  <?php if (!$complete): ?>
    <div class="msg msg-warning" style="margin:0 0 10px">GSTIN and PAN are not on file<?= $stLabel !== '' ? '' : ' (and no reason is recorded)' ?>. They are needed before this <?= e(Tl('client')) ?>'s next invoice can be issued.
      <?php if ($canLoc): ?><a href="/partner-edit?id=<?= (int) $pp['id'] ?>">Add them</a><?php endif; ?></div>
  <?php elseif ($stLabel !== '' && strtoupper((string) $pp['tax_status']) !== 'REGISTERED'): ?>
    <p class="muted" style="margin:0 0 8px;font-size:12.5px"><?= e($stLabel) ?><?= trim((string) $pp['tax_reason']) !== '' ? ' — ' . e($pp['tax_reason']) : '' ?><?= trim((string) $pp['country']) !== '' ? ' · ' . e($pp['country']) . ' · tax no. ' . e($pp['foreign_tax_id']) : '' ?></p>
  <?php endif; ?>
  <div class="dt-scroll"><table class="grid">
    <thead><tr><th scope="col">Location</th><th scope="col">State</th><th scope="col">GSTIN</th><th scope="col">Billing address</th><?php if ($canLoc): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?></tr></thead>
    <tbody>
    <?php $blEdits = [];
          foreach ($locs as $l): ?>
      <tr>
        <td><b><?= e($l['label']) ?></b><?= $l['is_main'] ? ' <span class="pill p-ok">main</span>' : '' ?></td>
        <td><?= e($l['state'] ?: '—') ?></td>
        <td><?= e($l['gstin'] ?: '—') ?></td>
        <td class="muted"><?php if ($l['address'] !== ''): ?><?= e($l['address']) ?><?php elseif ($l['is_main']): ?>
            <?php //  Owner test BL-03 (27 Nov 2026): the Main line never borrows another office's address. ?>
            <span class="bl-no-ho">Head-office address not on file<?php if ($canLoc && $blBack === 'partner'): ?> — <a href="/partner?id=<?= (int) $pp['id'] ?>&amp;tab=addresses">add it</a><?php elseif ($canLoc): ?> — <a href="/partner?id=<?= (int) $pp['id'] ?>&amp;tab=addresses">add it on the master record</a><?php endif; ?></span>
          <?php else: ?>—<?php endif; ?></td>
        <?php if ($canLoc): ?><td>
          <?php if (!$l['is_main']): $blEdits[] = $l; ?>
            <a class="btn small secondary" href="#bl-edit-<?= (int) $l['id'] ?>" onclick="var d=document.getElementById('bl-edit-<?= (int) $l['id'] ?>');if(d){d.open=true;}">✎ Change</a>
          <?php elseif ($l['addr']): ?>
            <a class="btn small secondary" href="/partner?id=<?= (int) $pp['id'] ?>&amp;tab=addresses" title="The head-office address is kept on the master record's Addresses tab">✎ Change head office</a>
          <?php endif; ?>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php // Change / Stop of each extra billing address — full width under the list, opened by
        // the "✎ Change" button on its row, so it is never squeezed into a table cell or off a
        // phone screen (owner test BL-03, 27 Nov 2026: "no option to edit"). The GSTIN stays. ?>
  <?php foreach ($blEdits as $l): ?>
    <details class="bl-edit" id="bl-edit-<?= (int) $l['id'] ?>" style="margin-top:8px"><summary class="btn small secondary">✎ Change <?= e($l['label']) ?> (<?= e($l['gstin']) ?>)</summary>
      <form method="post" action="/partner-billing-location?id=<?= (int) $pp['id'] ?>" class="inline-add" style="margin-top:6px">
        <input type="hidden" name="do" value="update"><?= $blBackIn ?><input type="hidden" name="loc" value="<?= (int) $l['id'] ?>">
        <div class="ff"><label>GSTIN <span class="muted">(stays — it is what this address is)</span></label><input class="form-control" value="<?= e($l['gstin']) ?> · <?= e($l['state']) ?>" readonly></div>
        <div class="ff"><label>Name</label><input class="form-control" name="label" value="<?= e($l['label']) ?>" maxlength="150"></div>
        <div class="ff"><label>Address line</label><input class="form-control" name="line1" value="<?= e($l['addr']['line1'] ?? '') ?>" maxlength="255"></div>
        <div class="ff"><label>City</label><input class="form-control" name="city" value="<?= e($l['addr']['city'] ?? '') ?>" maxlength="120"></div>
        <div class="ff"><label>PIN</label><input class="form-control" name="pincode" value="<?= e($l['addr']['pincode'] ?? '') ?>" maxlength="15"></div>
        <button class="btn small" type="submit">Save</button>
      </form>
      <form method="post" action="/partner-billing-location?id=<?= (int) $pp['id'] ?>" style="margin-top:6px" onsubmit="return confirm('Stop billing at this location? Bills already raised keep it.')">
        <input type="hidden" name="do" value="stop"><?= $blBackIn ?><input type="hidden" name="loc" value="<?= (int) $l['id'] ?>">
        <button class="btn small secondary" type="submit">Stop using for new bills</button>
      </form>
      <p class="muted" style="margin:4px 0 0;font-size:12px">Bills already issued keep the address they were made out to.</p>
    </details>
  <?php endforeach; ?>
  <?php if ($canLoc && trim((string) $pp['gstin']) !== ''): ?>
    <details style="margin-top:10px"><summary class="btn small">+ Add a billing address / GSTIN</summary>
      <?php $blFid = 'bl-' . $blBack; include __DIR__ . '/_billing_location_form.php'; ?>
    </details>
  <?php endif; ?>
  <?php if ($mv && empty($mv['err']) && $canLoc && (is_master() || can('mod.clients.edit'))): ?>
    <div class="msg msg-info" style="margin-top:12px">
      <b>This is a branch company of <?= e($mv['main']['code'] . ' — ' . $mv['main']['legal_name']) ?>, with the same PAN.</b>
      One PAN is one <?= e(Tl('client')) ?>. <a href="/partner-move-under?id=<?= (int) $pp['id'] ?>">See what moving it under the main <?= e(Tl('client')) ?> would do</a> — nothing changes until you press the button there.
    </div>
  <?php endif; ?>
</section>
