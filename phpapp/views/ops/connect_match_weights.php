<?php
// Connect K0+ — admin-tunable matching weights (§23). Defaults equal the values
// the scorer has always used; changing them re-weights recommendations app-wide.
$weights = $weights ?? []; $defaults = $defaults ?? [];
$rows = [
  ['skills',      'Skills / discipline overlap', 'How much a match on the required skills is worth.'],
  ['reputation',  'Reputation (rating & history)', 'Weight of an inspector’s ratings and completed jobs.'],
  ['credentials', 'Verified credentials (cap)', 'Maximum points from verified live certificates.'],
  ['cred_each',   '— per verified credential', 'Points each verified certificate adds, up to the cap above.'],
  ['tax_bonus',   'Taxonomy / concept match (cap)', 'Bonus when the graph links a role to related skills/equipment.'],
  ['location',    'Location fit (cap)', 'Bonus for being at / near the job site (tiered by distance).'],
];
$elig = [
  ['elig_eligible','Eligible'],['elig_expiring','Expiring soon'],['elig_check','Needs a check'],
  ['elig_unverified','Unverified (marketplace)'],['elig_blocked','Blocked'],
];
?>
<style>
  .mw-wrap{max-width:720px}
  .mw-card{background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:12px;padding:16px 18px;margin-bottom:14px}
  .mw-row{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line,#eef1f0)}
  .mw-row:last-child{border-bottom:0}
  .mw-row .lab{font-weight:600;font-size:14.5px}
  .mw-row .desc{color:var(--muted,#667);font-size:12.5px;margin-top:1px}
  .mw-row input{width:82px;padding:8px 10px;border:1px solid var(--line,#ccc);border-radius:8px;font-size:15px;text-align:right;font-variant-numeric:tabular-nums}
  .mw-def{color:var(--muted,#889);font-size:11.5px;min-width:70px;text-align:right}
</style>

<div class="mw-wrap">
<h1>Matching weights</h1>
<p class="muted" style="margin:0 0 16px;max-width:66ch">These control how the desk ranks people for a requirement. Every value is capped at 100; the defaults are exactly what the platform has always used — change them only to reflect how <em>you</em> prioritise (e.g. weight certifications higher for regulated work). The reasons shown on each card are unaffected.</p>

<form method="post" action="/connect-match-weights">
  <div class="mw-card">
    <?php foreach ($rows as [$k,$lab,$desc]): ?>
      <div class="mw-row">
        <div><div class="lab"><?= e($lab) ?></div><div class="desc"><?= e($desc) ?></div></div>
        <div style="display:flex;align-items:center;gap:12px">
          <span class="mw-def">default <?= (int)($defaults[$k] ?? 0) ?></span>
          <input type="number" min="0" max="100" name="<?= e($k) ?>" value="<?= (int)($weights[$k] ?? 0) ?>">
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mw-card">
    <div style="font-weight:700;margin-bottom:4px">Eligibility points</div>
    <div class="desc" style="color:var(--muted);font-size:12.5px;margin-bottom:6px">Points for each competence-gate outcome (internal inspectors are gated; a marketplace professional shows “Unverified”).</div>
    <?php foreach ($elig as [$k,$lab]): ?>
      <div class="mw-row">
        <div class="lab"><?= e($lab) ?></div>
        <div style="display:flex;align-items:center;gap:12px">
          <span class="mw-def">default <?= (int)($defaults[$k] ?? 0) ?></span>
          <input type="number" min="0" max="100" name="<?= e($k) ?>" value="<?= (int)($weights[$k] ?? 0) ?>">
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:10px;align-items:center">
    <button class="btn" type="submit">Save weights</button>
    <button class="btn secondary" type="submit" name="action" value="reset" onclick="return confirm('Reset all matching weights to their defaults?');">Reset to defaults</button>
  </div>
</form>
</div>
