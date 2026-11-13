<?php
//  WHO GIVES EACH SIGNATURE ON A CONTRACT (owner, 13 Nov 2026: "This all approvals
//  shall be set by individual organisation … it shall be easy to access").
//  Two small boxes — "First signature" and "Final approval" — each a tick-list of
//  the company's roles plus, optionally, one named person. Only the boxes that
//  apply to the number of approvals chosen above are shown.
$CS = setupqa_contract_signers_data(); $rule = $CS['rule']; if (!$rule) return;
$csBox = function ($i, $title, $help) use ($CS, $rule) {
    $s = $rule['sig'][$i]; $dis = $CS['can'] ? '' : ' disabled'; ?>
  <fieldset class="csig" data-only-when="contract_open_signatures" data-only-value="<?= $i === 1 ? '2' : '2|1' ?>" style="border:1px solid var(--line,#ddd);border-radius:10px;padding:12px;margin:14px 0 0">
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
      <?php if ($s['standard']): ?> This is the standard rule<?= $i === 1 ? ' — it also lets anyone who manages a branch\'s users sign. Once you save your own choice, only the roles and person picked here can' : '' ?>.<?php else: ?> Untick everything and choose nobody by name to go back to the standard rule.<?php endif; ?>
    </p>
  </fieldset>
<?php };
$csBox(1, 'Who gives the first signature?', 'Usually the manager who confirms the contract is genuinely needed.');
$csBox(2, 'Who gives the final approval?', 'The person who agrees the company will carry the contract. With one approval, this is the only one.');
