<?php
  // Connect K0+ — CV-assisted prefill. Paste (or scan the uploaded CV), review
  // what we detected against the taxonomy, and confirm what to add. Nothing is
  // saved until you confirm; suggestions never overwrite what you already have.
  $me = $me ?? []; $scan = $scan ?? null; $text = $text ?? ''; $relations = $relations ?? [];
  $relLabel = ['PRIMARY_ROLE'=>'Primary role','ADDITIONAL_ROLE'=>'Additional role','SPECIALIZATION'=>'Specialisation','SKILL'=>'Skill','EQUIPMENT'=>'Equipment','INDUSTRY'=>'Industry','CERTIFICATION'=>'Certification'];
?>
<p style="margin:0 0 8px"><a href="/pro/profile" class="muted" style="font-size:14px">← My passport</a></p>
<h1>Prefill from your CV</h1>
<p class="muted" style="margin:0 0 14px">Paste your CV text (or we'll read your uploaded CV). We match it to our technical taxonomy and show you what we found — you confirm what to add. We never overwrite what you already have.</p>

<form method="post" action="/pro/cv" class="card">
  <input type="hidden" name="action" value="scan">
  <label>Paste your CV (optional — leave blank to scan your uploaded CV)</label>
  <textarea name="cv_text" rows="8" placeholder="Paste your résumé text here…"><?= e($text) ?></textarea>
  <button class="btn" type="submit" style="margin-top:12px">Scan &amp; suggest</button>
  <p class="muted" style="margin:10px 0 0;font-size:13px">No CV uploaded yet? <a href="/pro/documents">Upload one →</a></p>
</form>

<?php if ($scan !== null): ?>
  <?php $exp = $scan['expertise'] ?? []; $base = $scan['base_place'] ?? null; ?>
  <div class="card">
    <h2>What we detected</h2>
    <?php if (!$exp && !$base): ?>
      <p class="muted" style="margin:0">Nothing matched our taxonomy in that text. Try pasting more of your CV, or add your expertise manually on your <a href="/pro/profile">passport</a>.</p>
    <?php else: ?>
    <form method="post" action="/pro/cv">
      <input type="hidden" name="action" value="apply">
      <?php if ($base): ?>
        <label style="display:flex;gap:8px;align-items:center;margin:0 0 12px">
          <input type="checkbox" name="base_place_id" value="<?= (int)$base['id'] ?>" checked>
          <span>📍 Set base city to <strong><?= e($base['name']) ?></strong><?= $base['state_name']?', '.e($base['state_name']):'' ?></span>
        </label>
      <?php endif; ?>
      <?php if ($exp): ?>
      <p class="muted" style="margin:0 0 8px;font-size:13px">Tick what applies and choose how each fits. Untick anything that isn't you.</p>
      <table style="width:100%;border-collapse:collapse;font-size:14px">
        <?php foreach ($exp as $x): ?>
          <tr style="border-bottom:1px solid var(--line,#eee)">
            <td style="padding:8px 6px;width:26px"><input type="checkbox" name="node[]" value="<?= (int)$x['node_id'] ?>" checked></td>
            <td style="padding:8px 6px"><strong><?= e($x['name']) ?></strong> <span class="muted" style="font-size:12px">(<?= e(strtolower($x['kind'])) ?>)</span></td>
            <td style="padding:8px 6px;text-align:right">
              <select name="rel_<?= (int)$x['node_id'] ?>" style="padding:6px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:13px">
                <?php foreach ($relations as $r): ?><option value="<?= e($r) ?>" <?= $r===$x['relation']?'selected':'' ?>><?= e($relLabel[$r] ?? $r) ?></option><?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <button class="btn" type="submit" style="width:100%;margin-top:14px">Add the ticked items to my passport</button>
    </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
