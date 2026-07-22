<div class="crumbs"><a href="/">Home</a> › Settings</div>
<h1>System settings</h1>
<p class="sub">Company-wide options. These apply to every dashboard and report.</p>
<form method="post" action="/settings" class="panel" style="max-width:520px;">
  <div class="ff"><label>Financial year starts in</label>
    <select class="form-control" name="fy_start_month">
      <?php $months=['1'=>'January','2'=>'February','3'=>'March','4'=>'April','5'=>'May','6'=>'June','7'=>'July','8'=>'August','9'=>'September','10'=>'October','11'=>'November','12'=>'December'];
      $cur=(string)fy_start_month(); foreach ($months as $k=>$v): ?><option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <small class="muted">India = April. Current FY resolves to <strong><?= e(current_fy()) ?></strong>.</small></div>
  <div class="ff"><label>On-time TAT threshold (days)</label>
    <input class="form-control" type="number" min="0" name="tat_threshold_days" value="<?= e(setting_get('tat_threshold_days', 3)) ?>">
    <small class="muted">Jobs closed within this many days count as "on time".</small></div>
  <div style="margin-top:16px;"><button class="btn" type="submit">Save settings</button></div>
</form>
