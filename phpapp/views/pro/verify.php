<?php
  // Connect #3 (pro side) — "Get verified". A professional submits identity and
  // credential checks; a moderator (or a verified provider) confirms them, moving
  // them up the trust ladder. Deterministic checks pre-screen the number; the tier
  // only moves on a real confirmation — stated honestly here.
  $me = $me ?? []; $msg = $msg ?? ''; $msgOk = $msgOk ?? true;
  $tierKey = $tierKey ?? 'registered'; $ladder = $ladder ?? []; $types = $types ?? []; $checks = $checks ?? [];
  $rank = 0; foreach ($ladder as $t) if ($t['key'] === $tierKey) $rank = (int)$t['rank'];
  // Only the checks a professional submits themselves.
  $proTypes = array_filter($types, fn($c) => in_array('professional', $c['for'] ?? [], true));
  $statusPill = function ($s) {
      $s = strtoupper((string)$s);
      $map = ['PENDING' => ['Pending', '#8a6d12', 'rgba(201,162,39,.14)'],
              'VERIFIED' => ['Verified', '#0b7a4a', 'rgba(16,122,74,.14)'],
              'REJECTED' => ['Rejected', '#b91c1c', 'rgba(185,28,28,.12)']];
      [$lbl, $c, $bg] = $map[$s] ?? $map['PENDING'];
      return '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;color:' . $c . ';background:' . $bg . '">' . e($lbl) . '</span>';
  };
?>
<h1>Get verified</h1>
<p class="muted" style="margin:0 0 16px">Verified professionals win more work — clients hire them with confidence.
  We check the number you enter, then a reviewer confirms it. Your tier only rises once a check is
  <strong>actually confirmed</strong> — a number that merely looks right is never enough.</p>

<?php if ($msg): ?>
  <div class="card" style="border-color:<?= $msgOk ? 'var(--ok)' : 'var(--bad)' ?>;background:<?= $msgOk ? 'var(--okbg)' : 'transparent' ?>">
    <strong><?= $msgOk ? '✓' : '⚠' ?></strong> <?= e($msg) ?>
  </div>
<?php endif; ?>

<!-- The ladder + where you are -->
<div class="card">
  <h2>Your trust level</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
    <?php foreach ($ladder as $t): $on = (int)$t['rank'] <= $rank; ?>
      <div style="flex:1 1 130px;padding:10px 12px;border-radius:12px;border:1px solid <?= $on ? 'var(--teal)' : 'var(--line)' ?>;background:<?= $on ? 'rgba(15,125,125,.08)' : 'transparent' ?>">
        <div style="font-weight:700;color:<?= $on ? 'var(--teal)' : 'var(--muted)' ?>"><?= $on ? '● ' : '○ ' ?><?= e($t['label']) ?></div>
        <div class="muted" style="font-size:12px;margin-top:2px"><?= e($t['blurb']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Submit a check -->
<div class="card">
  <h2>Submit a check</h2>
  <form method="post" action="/pro/verify">
    <label style="display:block;font-weight:600;margin:8px 0 4px">What are you verifying?</label>
    <select name="check_type" id="ct" onchange="var v=this.value;var needs=<?= json_encode(array_map(fn($c)=>!empty($c['value']), $proTypes)) ?>;document.getElementById('valwrap').style.display=needs[v]?'block':'none';">
      <?php foreach ($proTypes as $code => $c): ?>
        <option value="<?= e($code) ?>"><?= e($c['label']) ?><?= ($c['method'] ?? '')==='deterministic' ? ' (instant format check)' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <?php $firstNeedsValue = false; foreach ($proTypes as $c) { $firstNeedsValue = !empty($c['value']); break; } ?>
    <div id="valwrap" style="margin-top:10px;display:<?= $firstNeedsValue ? 'block' : 'none' ?>">
      <label style="display:block;font-weight:600;margin:0 0 4px">Number</label>
      <input type="text" name="value" placeholder="e.g. ABCDE1234F" autocomplete="off">
      <p class="muted" style="font-size:12px;margin:6px 0 0">We store only the last 4 digits, masked. Your full number is never kept.</p>
    </div>
    <div style="margin-top:10px">
      <label style="display:block;font-weight:600;margin:0 0 4px">Note / document reference (optional)</label>
      <input type="text" name="evidence" placeholder="e.g. 'uploaded PAN scan' or a reference">
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Submit for verification</button>
  </form>
</div>

<!-- History -->
<div class="card">
  <h2>Your checks</h2>
  <?php if (!$checks): ?>
    <p class="muted" style="margin:0">Nothing submitted yet. Start with your PAN or a photo ID above.</p>
  <?php else: ?>
    <?php foreach ($checks as $c): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line)">
        <div>
          <strong><?= e($types[$c['check_type']]['label'] ?? $c['check_type']) ?></strong>
          <?php if (!empty($c['ref_masked'])): ?><span class="muted" style="font-family:ui-monospace,monospace;font-size:13px"> · <?= e($c['ref_masked']) ?></span><?php endif; ?>
          <?php if (!empty($c['result_note'])): ?><div class="muted" style="font-size:12px"><?= e($c['result_note']) ?></div><?php endif; ?>
        </div>
        <div><?= $statusPill($c['status']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
