<?php
//  Setup in plain questions — one screen per module (lib/setupqa.php).
//  Each question is a set of choices; the current answer is the one selected.
$set = $set ?? ['title' => '', 'intro' => '', 'route' => '', 'sections' => []];
$n = 0;
?>
<div class="master-head">
  <div>
    <h1><?= e($set['title']) ?> — set up in a few questions</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px"><?= e($set['intro']) ?></p>
  </div>
</div>

<form method="post" action="/<?= e($set['route']) ?>" style="max-width:820px">
  <?php foreach ($set['sections'] as $sec): ?>
    <section class="panel" style="margin-top:14px" aria-labelledby="sq-<?= ++$n ?>">
      <h2 class="tab-sub" id="sq-<?= $n ?>" style="margin-top:0"><?= e($sec['title']) ?></h2>
      <?php if (!empty($sec['facts'])): ?>
        <ul style="margin:6px 0 12px;padding-left:18px;line-height:1.6">
          <?php foreach ($sec['facts'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php foreach ($sec['questions'] as $q): $cur = setupqa_value($q); ?>
        <fieldset style="border:0;padding:0;margin:14px 0 0">
          <legend style="font-weight:600;font-size:15px;margin-bottom:4px"><?= e($q['ask']) ?></legend>
          <?php if (!empty($q['why'])): ?><p class="muted" style="margin:0 0 8px;font-size:13px"><?= e($q['why']) ?></p><?php endif; ?>
          <div style="display:grid;gap:6px">
            <?php foreach ($q['options'] as $val => $label): ?>
              <label style="display:flex;gap:8px;align-items:flex-start;padding:8px 10px;border:1px solid var(--line,#ddd);border-radius:8px;cursor:pointer">
                <input type="radio" name="<?= e($q['key']) ?>" value="<?= e((string) $val) ?>" <?= (string) $val === $cur ? 'checked' : '' ?> style="margin-top:3px">
                <span><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <?php if (!empty($sec['link'])): ?>
        <p style="margin:12px 0 0"><a href="<?= e($sec['link'][0]) ?>"><?= e($sec['link'][1]) ?> →</a></p>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="submit">Save these answers</button>
    <a class="btn secondary" href="<?= e($set['home'] ?? '/') ?>">Back to <?= e($set['title']) ?></a>
  </div>
</form>
