<?php
  // Super-Admin — feature gates. Each monetisation feature rolls out OFF → TEST → LIVE.
  $catalog = $catalog ?? []; $states = $states ?? []; $gates = $gates ?? [];
  $badge = function ($s) {
      $c = ['OFF' => '#9a2a2a', 'TEST' => '#8a5a00', 'LIVE' => '#2f7a34'][$s] ?? '#667';
      return '<span style="display:inline-block;font-size:11px;font-weight:700;color:#fff;background:' . $c . ';border-radius:999px;padding:2px 9px">' . e($s) . '</span>';
  };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/super-admin">Super Admin</a> › Feature gates</div>
<div class="master-head">
  <div><h1>Feature gates</h1>
    <p class="sub">Roll out each money feature in three steps — <b>Off</b> (invisible), <b>Test</b> (staff only, no charging), <b>Live</b> (everyone). Everything defaults to Off; turning a feature on is always deliberate.</p></div>
  <a class="btn secondary" href="/super-admin">← Super Admin</a>
</div>

<form method="post" action="/feature-gates">
  <div class="panel" style="max-width:820px">
    <table class="grid" style="margin:0"><thead><tr><th>Feature</th><th>Now</th><th style="width:240px">Set to</th></tr></thead><tbody>
      <?php foreach ($catalog as $f => $lbl): $cur = $gates[$f] ?? 'OFF'; ?>
        <tr>
          <td><b><?= e($lbl) ?></b></td>
          <td><?= $badge($cur) ?></td>
          <td>
            <?php foreach ($states as $sk => $slbl): ?>
              <label style="margin-right:12px;font-size:13px;white-space:nowrap"><input type="radio" name="gate_<?= e($f) ?>" value="<?= e($sk) ?>" <?= $cur === $sk ? 'checked' : '' ?>> <?= e($slbl) ?></label>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <div style="margin-top:14px"><button class="btn" type="submit">Save gates</button></div>
  </div>
</form>
<p class="muted" style="font-size:12px;max-width:820px;margin-top:10px">These express your intended rollout. Specific enforcement switches (charging, escrow) still do their own job; new features check these gates as they ship.</p>
