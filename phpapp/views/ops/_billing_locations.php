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
    <?php foreach ($locs as $l): ?>
      <tr>
        <td><b><?= e($l['label']) ?></b><?= $l['is_main'] ? ' <span class="pill p-ok">main</span>' : '' ?></td>
        <td><?= e($l['state'] ?: '—') ?></td>
        <td><?= e($l['gstin'] ?: '—') ?></td>
        <td class="muted"><?= e($l['address'] ?: '—') ?></td>
        <?php if ($canLoc): ?><td>
          <?php if (!$l['is_main']): ?>
            <details><summary class="btn small secondary" style="display:inline-flex">Change</summary>
              <form method="post" action="/partner-billing-location?id=<?= (int) $pp['id'] ?>" class="inline-add" style="margin-top:6px">
                <input type="hidden" name="do" value="update"><?= $blBackIn ?><input type="hidden" name="loc" value="<?= (int) $l['id'] ?>">
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
            </details>
          <?php endif; ?>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($canLoc && trim((string) $pp['gstin']) !== ''): ?>
    <details style="margin-top:10px"><summary class="btn small">+ Add a billing address / GSTIN</summary>
      <p class="muted" style="margin:8px 0 0;font-size:12.5px"><b>Office in another state</b> — type that state's own GSTIN. <b>Another office in the same state</b> — type the same GSTIN (<?= e($pp['gstin']) ?>) and that office's address.</p>
      <form method="post" action="/partner-billing-location?id=<?= (int) $pp['id'] ?>" class="inline-add" style="margin-top:8px">
        <input type="hidden" name="do" value="add"><?= $blBackIn ?>
        <div class="ff"><label for="bl-gstin">GSTIN</label><input class="form-control" id="bl-gstin" name="gstin" maxlength="20" required data-gstin-check autocomplete="off" placeholder="e.g. <?= e($pp['gstin']) ?>" title="Copy it from that state's GST certificate (state code + <?= e($pp['pan'] ?: 'the PAN') ?> + 3 characters) — the last character is a check, so a made-up number is refused" style="text-transform:uppercase"><span class="ff-help gstin-hint" aria-live="polite"></span></div>
        <div class="ff"><label for="bl-label">Name</label><input class="form-control" id="bl-label" name="label" maxlength="150" placeholder="e.g. Mumbai plant"></div>
        <div class="ff"><label for="bl-line1">Billing address</label><input class="form-control" id="bl-line1" name="line1" maxlength="255"></div>
        <div class="ff"><label for="bl-city">City</label><input class="form-control" id="bl-city" name="city" maxlength="120"></div>
        <div class="ff"><label for="bl-pin">PIN</label><input class="form-control" id="bl-pin" name="pincode" maxlength="15"></div>
        <button class="btn small" type="submit">Add billing address</button>
      </form>
      <p class="muted" style="margin:6px 0 0;font-size:12.5px">The GSTIN must carry this <?= e(Tl('client')) ?>'s PAN (<?= e($pp['pan'] ?: '—') ?>). Its state is read from the GSTIN. With the same GSTIN as one already listed, the address and city are needed so the two offices can be told apart. <?= e(Tlp('quote')) ?>, contracts and invoices then choose which address they are billed to.</p>
    </details>
  <?php endif; ?>
  <?php if ($mv && empty($mv['err']) && $canLoc && (is_master() || can('mod.clients.edit'))): ?>
    <div class="msg msg-info" style="margin-top:12px">
      <b>This is a branch company of <?= e($mv['main']['code'] . ' — ' . $mv['main']['legal_name']) ?>, with the same PAN.</b>
      One PAN is one <?= e(Tl('client')) ?>. <a href="/partner-move-under?id=<?= (int) $pp['id'] ?>">See what moving it under the main <?= e(Tl('client')) ?> would do</a> — nothing changes until you press the button there.
    </div>
  <?php endif; ?>
</section>
