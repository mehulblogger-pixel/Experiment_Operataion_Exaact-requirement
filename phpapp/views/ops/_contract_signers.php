<?php
//  WHO GIVES EACH SIGNATURE ON A CONTRACT (owner, 13 Nov 2026: "This all approvals
//  shall be set by individual organisation … it shall be easy to access").
//  Small boxes — "First signature", the ones in between (three or more —
//  Q-08, any number from 26 Nov 2026) and "Final approval" — each a tick-list of
//  the company's roles plus, optionally, one named person. Only the boxes that
//  apply to the number of approvals chosen above are shown.
$CS = setupqa_contract_signers_data(); $rule = $CS['rule']; if (!$rule) return;
$csBox = function ($i, $title, $help) use ($CS, $rule) {
    $s = $rule['sig'][$i] ?? ['roles' => [], 'user' => 0, 'standard' => true]; $dis = $CS['can'] ? '' : ' disabled'; ?>
  <?php //  Shown when the company asks for at least this many: the first signature with two or more,
        //  the final approval with one or more, the box for slot $i (signature $i-1) with $i or more. ?>
  <fieldset class="csig" data-only-when="contract_open_signatures" data-only-value="<?= $i === 1 ? '2+' : ($i === 2 ? '1+' : $i . '+') ?>" style="border:1px solid var(--line,#ddd);border-radius:10px;padding:12px;margin:14px 0 0">
    <legend style="font-weight:600;font-size:15px;padding:0 6px"><?= e($title) ?></legend>
    <p class="muted" style="margin:0 0 8px;font-size:13px"><?= e($help) ?></p>
    <input type="hidden" name="contract_sig<?= $i ?>_sent" value="1">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:6px">
      <?php foreach ($CS['roles'] as $code => $label): ?>
        <label style="display:flex;gap:8px;align-items:center;padding:6px 10px;border:1px solid var(--line,#ddd);border-radius:8px;cursor:pointer;font-size:13.5px">
          <input type="checkbox" name="contract_sig<?= $i ?>_roles[]" value="<?= e($code) ?>" <?= in_array($code, $s['roles'], true) ? 'checked' : '' ?><?= $dis ?>> <span><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php if ($CS['can']): ?>
    <label style="display:block;margin-top:10px;font-size:13.5px">…and/or one named person
      <select class="form-control" name="contract_sig<?= $i ?>_user" style="max-width:320px;margin-top:4px">
        <option value="">— nobody by name —</option>
        <?php foreach ($CS['users'] as $u): $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: (string)$u['username']; ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$s['user'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($nm) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <p class="muted" style="margin:8px 0 0;font-size:12.5px">
      Can sign today: <b><?= e(contract_sig_names($i, null, 0, $rule)) ?></b>.
      <?php if ($s['standard'] && $i >= 3): ?> Nobody is chosen yet — until you choose, only the Master Admin can give it.<?php elseif ($s['standard']): ?> This is the standard rule<?= $i === 1 ? ' — it also lets anyone who manages a branch\'s users sign. Once you save your own choice, only the roles and person picked here can' : '' ?>.<?php else: ?> Untick everything and choose nobody by name to go back to the standard rule.<?php endif; ?>
    </p>
  </fieldset>
<?php };
$csBox(1, 'Who gives the first signature?', 'Usually the manager who confirms the contract is genuinely needed.');
//  The signatures in between, in order (slot 3 = signature 2, slot 4 = signature 3, …). Five boxes
//  are always there (24 Nov); with more signatures (owner, 26 Nov 2026: "as many as needed") a box
//  for each, plus a few spare ones that appear as soon as a larger number is typed above.
$csTop = min(defined('CONTRACT_SIG_HARD_LIMIT') ? CONTRACT_SIG_HARD_LIMIT : 50, max(CONTRACT_SIG_MAX, (int)$rule['signatures']) + 5);
for ($i = 3; $i <= $csTop; $i++)
    $csBox($i, 'Who gives signature ' . ($i - 1) . '?', 'With ' . $i . ' or more: after ' . ($i === 3 ? 'the first signature' : 'signature ' . ($i - 2)) . ', before the next one.');
$csBox(2, 'Who gives the final approval?', 'The person who agrees the company will carry the contract. With one approval, this is the only one.'); ?>
<p class="muted" style="margin:10px 0 0;font-size:12.5px">Need more signatures than there are boxes? Type the number above and press Save — a box for each appears.</p>
<?php
