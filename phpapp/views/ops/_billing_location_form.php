<?php
// THE one form that adds a billing address (a GSTIN + its office address) to a
// customer. It is shown in the Billing addresses box (master record and Sales
// customer page) AND on the master record's Addresses tab when the type is
// "Billing Address" — one form, one save path (custid_add_location), so there are
// never two screens for the same thing (owner, 27 Nov 2026: A3).
//   $pp     — the business_partners row
//   $blBack — 'partner' | 'customer' | 'addresses': where Add lands back
//   $blFid  — a prefix for the box ids, so the form can sit twice on one page
if (empty($pp)) return;
$blBack = in_array($blBack ?? '', ['partner', 'customer', 'addresses'], true) ? $blBack : 'partner';
$blFid = preg_replace('/[^a-z0-9-]/', '', (string) ($blFid ?? 'bl')) ?: 'bl';
$blPan = strtoupper(trim((string) ($pp['pan'] ?? '')));
?>
<p class="muted" style="margin:8px 0 0;font-size:12.5px"><b>Office in another state</b> — type that state's own GSTIN. <b>Another office in the same state</b> — type the same GSTIN (<?= e($pp['gstin']) ?>) and that office's address.</p>
<form method="post" action="/partner-billing-location?id=<?= (int) $pp['id'] ?>" class="inline-add" style="margin-top:8px">
  <input type="hidden" name="do" value="add"><input type="hidden" name="back" value="<?= e($blBack) ?>">
  <div class="ff"><label for="<?= $blFid ?>-gstin">GSTIN</label>
    <input class="form-control" id="<?= $blFid ?>-gstin" name="gstin" maxlength="20" required data-gstin-check autocomplete="off"
           data-gstin-who="<?= (int) $pp['id'] ?>" data-gstin-pan="<?= e($blPan) ?>"
           placeholder="e.g. <?= e($pp['gstin']) ?>" title="Copy it from that state's GST certificate (state code + <?= e($blPan ?: 'the PAN') ?> + 3 characters) — the last character is a check, so a made-up number is refused" style="text-transform:uppercase">
    <span class="ff-help gstin-state" aria-live="polite"></span>
    <span class="ff-help gstin-hint" aria-live="polite"></span>
    <span class="ff-help gstin-who" aria-live="polite" style="color:var(--danger,#b42318)"></span></div>
  <div class="ff"><label for="<?= $blFid ?>-label">Name</label><input class="form-control" id="<?= $blFid ?>-label" name="label" maxlength="150" placeholder="e.g. Mumbai plant"></div>
  <div class="ff"><label for="<?= $blFid ?>-line1">Billing address</label><input class="form-control" id="<?= $blFid ?>-line1" name="line1" maxlength="255"></div>
  <div class="ff"><label for="<?= $blFid ?>-city">City</label><input class="form-control" id="<?= $blFid ?>-city" name="city" maxlength="120"></div>
  <div class="ff"><label for="<?= $blFid ?>-pin">PIN</label><input class="form-control" id="<?= $blFid ?>-pin" name="pincode" maxlength="15"></div>
  <button class="btn small" type="submit">Add billing address</button>
</form>
<p class="muted" style="margin:6px 0 0;font-size:12.5px">The GSTIN must carry this <?= e(Tl('client')) ?>'s PAN (<?= e($blPan ?: '—') ?>). Its state is read from the GSTIN. With the same GSTIN as one already listed, the address and city are needed so the two offices can be told apart. <?= e(Tlp('quote')) ?>, contracts and invoices then choose which address they are billed to.</p>
