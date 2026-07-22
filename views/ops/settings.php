<div class="crumbs"><a href="/">Home</a> › Settings</div>
<h1>System settings</h1>
<p class="sub">Company-wide options. These apply to every dashboard and report.</p>
<form method="post" action="/settings" class="panel" enctype="multipart/form-data" style="max-width:520px;">
  <div class="ff"><label>Financial year starts in</label>
    <select class="form-control" name="fy_start_month">
      <?php $months=['1'=>'January','2'=>'February','3'=>'March','4'=>'April','5'=>'May','6'=>'June','7'=>'July','8'=>'August','9'=>'September','10'=>'October','11'=>'November','12'=>'December'];
      $cur=(string)fy_start_month(); foreach ($months as $k=>$v): ?><option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <small class="muted">India = April. Current FY resolves to <strong><?= e(current_fy()) ?></strong>.</small></div>
  <div class="ff"><label>On-time TAT threshold (days)</label>
    <input class="form-control" type="number" min="0" name="tat_threshold_days" value="<?= e(setting_get('tat_threshold_days', 3)) ?>">
    <small class="muted">Jobs closed within this many days count as "on time".</small></div>
  <h3 class="tab-sub" style="margin-top:22px;">Branding &amp; theme</h3>
  <div class="ff"><label>Agency name (shown in the top bar)</label>
    <input class="form-control" name="brand_name" value="<?= e(setting_get('brand_name', '')) ?>" placeholder="e.g. SGS Ahmedabad"></div>
  <div class="ff"><label>Theme colour</label>
    <?php $bc = valid_hex_color(setting_get('brand_color', '#1e40af')); ?>
    <input type="color" name="brand_color" value="<?= e($bc) ?>" style="width:64px;height:38px;padding:2px;vertical-align:middle;">
    <span class="muted">Text stays automatically legible (dark or white) whatever colour you pick.</span></div>
  <div class="ff"><label>Agency logo <span class="muted">(PNG/JPG/SVG, under 2 MB)</span></label>
    <?php $logo = setting_get('brand_logo', ''); if ($logo): ?>
      <div style="margin-bottom:6px;"><img src="<?= e($logo) ?>" alt="current logo" style="height:40px;background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px;"></div>
      <label class="chk"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
    <?php endif; ?>
    <input class="form-control" type="file" name="logo" accept="image/*"></div>
  <div style="margin-top:16px;"><button class="btn" type="submit">Save settings</button></div>
</form>
