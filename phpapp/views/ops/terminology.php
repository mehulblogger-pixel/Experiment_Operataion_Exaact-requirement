<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Terminology</div>
<div class="master-head"><div>
  <h1>Terminology</h1>
  <p class="sub" style="margin:2px 0 0">Every screen in the app reads its wording from here. Change a word once and it changes everywhere — headings, menus, buttons, labels and e-mails. The standard wording follows ISO/IEC 17020 and normal third-party-inspection usage.</p>
</div></div>

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
  <p class="sub" style="margin-bottom:10px">Puts every word back to the shipped ISO/IEC 17020 wording. Your data is not touched — only the labels.</p>
  <button class="btn danger" type="submit">Reset to standard wording</button>
</form>
