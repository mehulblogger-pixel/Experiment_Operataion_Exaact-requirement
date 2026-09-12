<?php
// Cockpit → Forms hub. Lists the designable forms with a Configure link into the
// canonical Form Designer (/form-designer). It builds NO form storage of its own.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<style>
  .ck{max-width:680px}
  .ck .frm{display:flex;gap:12px;align-items:center;border:1px solid var(--line,#e5e7eb);border-radius:14px;
    padding:14px 16px;margin-bottom:10px;background:var(--card,#fff)}
  .ck .frm .ic{font-size:22px;flex:none}
  .ck .frm .bd{flex:1}
  .ck .frm h3{margin:0;font-size:16px}
  .ck .frm p{margin:2px 0 0;color:#6b7280;font-size:13px}
  .ck .cnt{font-size:12px;color:#8494a8}
</style>

<div class="ck">
  <div class="crumbs"><a href="/">Home</a> › <a href="/workspace/setup">Workspace setup</a> › Forms</div>
  <h1 style="margin:.2em 0">Forms</h1>
  <p class="muted" style="margin-top:0">These are the forms your team fills in. Open one to rename fields, reorder them, add your own field, or build a dropdown — all in the Form Designer.</p>

  <?php if (!$forms): ?>
    <p class="muted">No designable forms for your current features. Turn on the features you need under <a href="/workspace/setup/modules">Features</a>.</p>
  <?php else: ?>
    <?php foreach ($forms as $fk => $f): $n = (int) ($customCounts[$fk] ?? 0); ?>
      <div class="frm">
        <span class="ic"><?= $e($f['icon'] ?? '📝') ?></span>
        <div class="bd">
          <h3><?= $e($f['label'] ?? $fk) ?></h3>
          <p><?= $e($f['help'] ?? 'Design this form') ?><?php if ($n > 0): ?> · <span class="cnt"><?= $n ?> field<?= $n === 1 ? '' : 's' ?> you added</span><?php endif; ?></p>
        </div>
        <a class="btn" href="/form-designer?form=<?= $e($fk) ?>">Configure</a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <p class="muted" style="font-size:13px;margin-top:16px">Need a whole new kind of form? Use <a href="/cforms">Custom forms</a>. Need to change a dropdown’s options? Open the field in the Form Designer, or manage lists under <a href="/masters">Dropdown lists</a>.</p>
</div>
