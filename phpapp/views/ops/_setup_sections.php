<?php
//  The sections of a "set up in plain questions" page (lib/setupqa.php) — shared
//  by Set up Sales (every section) and Admin → Approvals → Sales (one tab's
//  sections), so a rule reads and saves the same way wherever it is opened.
//  Needs: $sections. Optional: $levelsBack (where the level editor returns to).
$n = $n ?? 0;
foreach ($sections as $sec):
  $secCan = !isset($sec['can']) || ($sec['can'])(); ?>
    <section class="panel" style="margin-top:14px" aria-labelledby="sq-<?= ++$n ?>">
      <h2 class="tab-sub" id="sq-<?= $n ?>" style="margin-top:0"><?= e($sec['title']) ?></h2>
      <?php if (!empty($sec['facts'])): ?>
        <ul style="margin:6px 0 12px;padding-left:18px;line-height:1.6">
          <?php foreach ($sec['facts'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (!$secCan && (!empty($sec['questions']) || !empty($sec['editor']))): ?>
        <p class="muted" style="margin:0 0 4px;font-size:13px">🔒 This rule is changed by someone who may change the company settings (an administrator). It is shown here so you know what applies.</p>
      <?php endif; ?>
      <?php if (($sec['editor'] ?? '') === 'quote_levels') { $mode = 'rows'; include __DIR__ . '/crm/approval_levels_inline.php'; } ?>
      <?php foreach ($sec['questions'] as $q): $cur = setupqa_value($q); ?>
        <fieldset style="border:0;padding:0;margin:14px 0 0"<?= !empty($q['only_when']) ? ' data-only-when="' . e($q['only_when'][0]) . '" data-only-value="' . e($q['only_when'][1]) . '"' : '' ?>>
          <legend style="font-weight:600;font-size:15px;margin-bottom:4px"><?= e($q['ask']) ?></legend>
          <?php if (!empty($q['why'])): ?><p class="muted" style="margin:0 0 8px;font-size:13px"><?= e($q['why']) ?></p><?php endif; ?>
          <div style="display:grid;gap:6px">
            <?php foreach ($q['options'] as $val => $label): ?>
              <label style="display:flex;gap:8px;align-items:flex-start;padding:8px 10px;border:1px solid var(--line,#ddd);border-radius:8px;cursor:pointer">
                <input type="radio" name="<?= e($q['key']) ?>" value="<?= e((string) $val) ?>" <?= (string) $val === $cur ? 'checked' : '' ?> <?= $secCan ? '' : 'disabled' ?> style="margin-top:3px">
                <span><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <?php if (($sec['editor'] ?? '') === 'contract_signers') include __DIR__ . '/_contract_signers.php'; ?>
      <?php foreach (['link', 'link2'] as $lk) if (!empty($sec[$lk])): ?>
        <p style="margin:12px 0 0"><a href="<?= e($sec[$lk][0]) ?>"><?= e($sec[$lk][1]) ?> →</a></p>
      <?php endif; ?>
    </section>
<?php endforeach; ?>
<script>
//  A question that only makes sense for one answer of another (e.g. "may one person
//  give both?" only when two approvals are asked for) is shown only then.
(function(){
  function sync(){
    [].forEach.call(document.querySelectorAll('[data-only-when]'),function(el){
      var on=document.querySelector('input[name="'+el.getAttribute('data-only-when')+'"]:checked');
      var vals=(el.getAttribute('data-only-value')||'').split('|');
      el.hidden=!(on && vals.indexOf(on.value)>=0);
    });
  }
  document.addEventListener('change',function(ev){ if(ev.target && ev.target.type==='radio') sync(); });
  sync();
})();
</script>
