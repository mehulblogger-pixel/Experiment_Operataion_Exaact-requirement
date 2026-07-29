<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Terminology</div>
<div class="master-head"><div>
  <h1>Terminology</h1>
  <p class="sub" style="margin:2px 0 0">Every screen in the app reads its wording from here. Change a word once and it changes everywhere — headings, menus, buttons, labels and e-mails. Nothing about your data changes; only what things are called.</p>
</div></div>

<div class="panel" style="margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Start from your trade</h3>
  <p class="sub" style="margin:0 0 12px">Pick the one closest to what you do and every word below is filled in at once. Nothing is locked
    afterwards — change any single word you disagree with, and that is what the screens will say.</p>
  <div class="pack-grid">
    <?php foreach ($packs as $pk => $p): ?>
      <form method="post" action="/terminology" class="pack-card<?= $pack === $pk ? ' on' : '' ?>"
            onsubmit="return confirm('Change the wording to <?= e(addslashes($p['label'])) ?>? Any words you have typed yourself will be replaced.')">
        <input type="hidden" name="pack" value="<?= e($pk) ?>">
        <div class="pack-name"><?= e($p['label']) ?><?= $pack === $pk ? ' <span class="pill p-info">in use</span>' : '' ?></div>
        <div class="pack-note muted"><?= e($p['note']) ?></div>
        <?php if (!empty($p['terms'])): ?>
          <div class="pack-eg muted"><?= e($p['terms']['call'][0] ?? '') ?><?php
            if (!empty($p['terms']['job'][0])): ?> · <?= e($p['terms']['job'][0]) ?><?php endif; ?><?php
            if (!empty($p['terms']['engineer'][0])): ?> · <?= e($p['terms']['engineer'][0]) ?><?php endif; ?></div>
        <?php else: ?>
          <div class="pack-eg muted">Work Order · Job · Team Member</div>
        <?php endif; ?>
        <button class="btn secondary" type="submit"><?= $pack === $pk ? 'Apply again' : 'Use this wording' ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<style>
 .pack-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px}
 .pack-card{border:1px solid var(--line);border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:6px}
 .pack-card.on{border-color:var(--brand);background:var(--soft)}
 .pack-name{font-weight:600;font-size:13.5px}
 .pack-note{font-size:12px;line-height:1.4}
 .pack-eg{font-size:12px;font-style:italic;margin-top:auto}
 .pack-card .btn{margin-top:6px}
</style>

<form method="post" action="/terminology" class="panel">
  <p class="sub" style="margin:0 0 14px">Leave a box blank to keep the standard word. Anything you change is shown with a <span class="pill p-info">changed</span> tag.</p>

  <?php foreach ($groups as $gname => $items): ?>
    <h3 class="tab-sub"><?= e($gname) ?></h3>
    <table class="dt" style="margin-bottom:14px">
      <thead><tr><th style="width:26%">Standard wording</th><th style="width:22%">Singular</th><th style="width:22%">Plural</th><th>What it means</th></tr></thead>
      <tbody>
      <?php foreach ($items as $key => $d):
        $curS = $ov[$key][0] ?? ''; $curP = $ov[$key][1] ?? ''; ?>
        <tr>
          <td><strong><?= e($d[0]) ?></strong> <span class="muted">/ <?= e($d[1]) ?></span>
              <?= ($curS !== '' || $curP !== '') ? ' <span class="pill p-info">changed</span>' : '' ?></td>
          <td><input class="form-control" name="t_<?= e($key) ?>_s" value="<?= e($curS) ?>" placeholder="<?= e($d[0]) ?>"></td>
          <td><input class="form-control" name="t_<?= e($key) ?>_p" value="<?= e($curP) ?>" placeholder="<?= e($d[1]) ?>"></td>
          <td class="muted"><?= e($d[3]) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
    <button class="btn" type="submit">Save terminology</button>
    <a class="btn secondary" href="/settings">Back to settings</a>
  </div>
</form>

<form method="post" action="/terminology" class="panel" style="margin-top:14px"
      onsubmit="return confirm('Reset every word back to the standard wording?')">
  <input type="hidden" name="reset" value="1">
  <h3 class="tab-sub" style="margin-top:0">Reset</h3>
  <p class="sub" style="margin-bottom:10px">Puts every word back to the plain shipped wording, and forgets the trade you picked. Your data is not touched — only the labels.</p>
  <button class="btn danger" type="submit">Reset to standard wording</button>
</form>
