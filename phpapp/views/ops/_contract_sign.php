<?php
//  The signature buttons for opening a contract, or for an amendment — ONE piece,
//  used on the quotation, the contract page and the "Contract openings" queue, so
//  the three can never say different things (owner, 13 Nov 2026: signed in as
//  Branch Manager, pressed "Endorse", and was told it was "now with the branch
//  manager").
//
//  The rule is each company's own (lib/contracts.php → contract_open_rule()).
//  A viewer is shown ONE main button — the signature that is theirs to give now —
//  and, in words, who it is waiting for when it is not them.
//
//  Needs: $sg (contract_open_stage() / contract_amend_stage()), $sgId (contract id),
//         $sgKind 'open' | 'amend'. Optional: $sgCoordinators, $sgOfficeName, $sgCompact.
$sgKind = $sgKind ?? 'open'; $sgAmend = $sgKind === 'amend';
$sgDo = fn($a) => $sgAmend ? 'amend-' . $a : $a;
$sgCoordinators = $sgCoordinators ?? []; $sgCompact = !empty($sgCompact);
$sgEndorseForm = function ($primary) use ($sgId, $sgDo, $sgAmend, $sgCoordinators, $sgOfficeName, $sgCompact) { ?>
  <form method="post" action="/contract-open" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?= (int)$sgId ?>"><input type="hidden" name="do" value="<?= e($sgDo('endorse')) ?>">
    <?php if (!$sgAmend && $sgCoordinators): ?>
      <select name="coordinator_id" class="form-control" style="min-width:150px;font-size:12px" title="Forward to a coordinator in <?= e(($sgOfficeName ?? '') ?: T('office')) ?>" aria-label="Forward to a coordinator (optional)">
        <option value="">— forward to (optional) —</option>
        <?php foreach ($sgCoordinators as $co): ?>
          <option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?><?= !empty($co['role']) ? ' · ' . e(strtolower(str_replace('_',' ',$co['role']))) : '' ?></option>
        <?php endforeach; ?>
      </select>
    <?php elseif (!$sgAmend && !$sgCompact): ?>
      <input class="form-control" name="note" placeholder="note (optional)" aria-label="Note (optional)" style="width:180px">
    <?php endif; ?>
    <button class="btn small<?= $primary ? '' : ' secondary' ?>" type="submit"><?= $primary ? 'Give the first signature' : 'Give the first signature instead' ?></button>
  </form>
<?php };
?>
<div class="csign">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center<?= $sgCompact ? ';justify-content:flex-end' : '' ?>">
    <?php if ($sg['primary'] === 'endorse') $sgEndorseForm(true); ?>
    <?php if ($sg['primary'] === 'approve'): ?>
      <form method="post" action="/contract-open" style="display:inline">
        <input type="hidden" name="id" value="<?= (int)$sgId ?>"><input type="hidden" name="do" value="<?= e($sgDo('approve')) ?>">
        <button class="btn small" type="submit"><?= $sgAmend ? 'Approve the amendment' : 'Approve &amp; open the contract' ?></button>
      </form>
    <?php endif; ?>
    <?php if ($sg['alt'] === 'endorse') $sgEndorseForm(false); ?>
    <?php if (!empty($sg['reject'])): ?>
      <?php if ($sgAmend): ?>
        <form method="post" action="/contract-open" style="display:flex;gap:6px"><input type="hidden" name="id" value="<?= (int)$sgId ?>"><input type="hidden" name="do" value="amend-reject">
          <input class="form-control" name="note" required placeholder="Why it is rejected" aria-label="Why it is rejected" style="width:200px"><button class="btn small secondary">Reject</button></form>
      <?php else: ?>
        <form method="post" action="/contract-open" style="display:inline" onsubmit="return confirm('Reject opening this contract number?')">
          <input type="hidden" name="id" value="<?= (int)$sgId ?>"><input type="hidden" name="do" value="reject">
          <button class="btn small danger" type="submit">Reject</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if (!$sgCompact || $sg['primary'] === ''): ?><p class="sub" style="margin:8px 0 0<?= $sgCompact ? ';font-size:12px;text-align:right' : '' ?>"><?= e($sg['waiting']) ?></p><?php endif; ?>
  <?php if ($sg['hint'] !== ''): ?><p class="sub" style="margin:4px 0 0<?= $sgCompact ? ';font-size:12px;text-align:right' : '' ?>"><b><?= e($sg['hint']) ?></b></p><?php endif; ?>
</div>
