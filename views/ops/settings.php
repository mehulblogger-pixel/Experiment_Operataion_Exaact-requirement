<div class="crumbs"><a href="/">Home</a> › Settings</div>
<h1>System settings</h1>
<p class="sub">Company-wide options — financial year, branding and dashboards.</p>

<form method="post" action="/settings" enctype="multipart/form-data" class="panel" style="max-width:620px;">
  <h3 class="tab-sub" style="margin-top:0;">Branding</h3>
  <div class="form-grid">
    <div class="ff"><label>Application name</label><input class="form-control" name="app_name" value="<?= e(setting_get('app_name','')) ?>" placeholder="e.g. SGS Ahmedabad Ops"></div>
    <div class="ff"><label>Theme colour</label><input class="form-control" type="color" name="brand_color" value="<?= e(setting_get('brand_color','#1e40af')) ?>" style="height:42px;padding:4px"><small class="muted">Top bar &amp; buttons. Text stays legible automatically.</small></div>
    <div class="ff"><label>Logo (PNG/JPG/SVG, ≤600 KB)</label><input class="form-control" type="file" name="logo" accept="image/*"></div>
    <div class="ff">
      <?php if (setting_get('logo_data','')): ?>
        <label>Current logo</label><div style="background:#1e40af;padding:8px;border-radius:8px;display:inline-block"><?= logo_html() ?></div>
        <label class="chk" style="margin-top:6px"><input type="checkbox" name="clear_logo" value="1"> Remove logo</label>
      <?php else: ?><label class="muted">No logo uploaded yet.</label><?php endif; ?>
    </div>
  </div>

  <h3 class="tab-sub">Financial &amp; operations</h3>
  <div class="form-grid">
    <div class="ff"><label>Financial year starts in</label>
      <select class="form-control" name="fy_start_month">
        <?php $months=['1'=>'January','2'=>'February','3'=>'March','4'=>'April','5'=>'May','6'=>'June','7'=>'July','8'=>'August','9'=>'September','10'=>'October','11'=>'November','12'=>'December'];
        $cur=(string)fy_start_month(); foreach ($months as $k=>$v): ?><option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select><small class="muted">India = April. Current FY = <strong><?= e(current_fy()) ?></strong>.</small></div>
    <div class="ff"><label>On-time TAT threshold (days)</label>
      <input class="form-control" type="number" min="0" name="tat_threshold_days" value="<?= e(setting_get('tat_threshold_days', 3)) ?>"></div>
  </div>
  <div style="margin-top:16px;"><button class="btn" type="submit">Save settings</button></div>
</form>
