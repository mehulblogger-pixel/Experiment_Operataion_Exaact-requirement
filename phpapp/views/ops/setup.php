<?php
  $u = current_user();
  $months = ['1'=>'January','2'=>'February','3'=>'March','4'=>'April','5'=>'May','6'=>'June',
             '7'=>'July','8'=>'August','9'=>'September','10'=>'October','11'=>'November','12'=>'December'];
  $curFy = (string)(function_exists('fy_start_month') ? fy_start_month() : 4);
  $curInd = function_exists('industry_current') ? (string)industry_current() : '';
  $industries = defined('INDUSTRY_TEMPLATES') ? INDUSTRY_TEMPLATES : [];
?>
<div class="crumbs"><a href="/">Home</a> › Set up</div>
<div class="master-head">
  <div><h1>Welcome — let’s set up your system</h1>
    <p class="sub" style="margin:2px 0 0">Five quick things only you can decide. Every one of them can be changed later
      under <strong>Settings</strong> — this just gets you started instead of leaving a new install looking half-built.</p></div>
</div>

<form method="post" action="/setup-save" class="panel settings-form" style="max-width:820px">

  <h3 class="tab-sub" style="margin-top:0">1 · Your admin password</h3>
  <p class="sub" style="margin:0 0 8px">You are signed in as <strong><?= e($u['username'] ?? 'admin') ?></strong>.
    If you are still on the password from <code>config.php</code>, set your own now.</p>
  <div class="form-grid">
    <div class="ff"><label>New password <span class="muted">— leave blank to keep the current one</span></label>
      <input class="form-control" type="password" name="admin_pass" autocomplete="new-password" placeholder="at least <?= (int)(function_exists('pwd_min_len') ? pwd_min_len() : 8) ?> characters, a letter and a number"></div>
  </div>

  <h3 class="tab-sub">2 · What does your company do?</h3>
  <p class="sub" style="margin:0 0 8px">One choice sets three things: the sales pipeline &amp; funnel built for your trade,
    the wording across the whole app (what a job, a report and a client are called), and — if you pick an accredited
    trade (inspection or a testing laboratory) — the matching accreditation rules.</p>
  <div class="form-grid">
    <div class="ff"><label>Company / brand name</label>
      <input class="form-control" name="app_name" value="<?= e(setting_get('app_name','')) ?>" placeholder="e.g. Exaact Inspection Services"></div>
    <div class="ff"><label>Your industry</label>
      <select class="form-control" name="industry">
        <?php foreach ($industries as $k=>$p): ?>
          <option value="<?= e($k) ?>" <?= $curInd===$k?'selected':'' ?>><?= e($p['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">An accredited trade — inspection (ISO/IEC 17020) or a testing &amp; calibration laboratory (ISO/IEC 17025) — turns on equipment, competence, impartiality and the matching ISO gates. Any other choice keeps them off.</small></div>
  </div>

  <h3 class="tab-sub">3 · Money &amp; calendar</h3>
  <div class="form-grid">
    <div class="ff"><label>Financial year starts in</label>
      <select class="form-control" name="fy_start_month">
        <?php foreach ($months as $k=>$v): ?><option value="<?= $k ?>" <?= $curFy===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select><small class="muted">India = April.</small></div>
    <div class="ff"><label>Currency symbol</label>
      <input class="form-control" name="currency_symbol" value="<?= e(setting_get('currency_symbol','')) ?>" placeholder="<?= e(function_exists('cur_sym') ? cur_sym() : '₹') ?>" maxlength="4"></div>
    <div class="ff"><label>Date format</label>
      <select class="form-control" name="date_format">
        <?php $cdf = function_exists('date_fmt') ? date_fmt() : ''; foreach (DATE_FORMATS as $k=>$sample): ?>
          <option value="<?= e($k) ?>" <?= $cdf===$k?'selected':'' ?>><?= e($sample) ?></option><?php endforeach; ?>
      </select></div>
  </div>

  <h3 class="tab-sub">4 · Data-protection contact</h3>
  <p class="sub" style="margin:0 0 8px">The DPDP Act needs a named person a data complaint can go to. You can fill this in later, but it is on the checklist under “Where we stand”.</p>
  <div class="form-grid">
    <div class="ff"><label>Grievance officer — name</label>
      <input class="form-control" name="grievance_name" value="<?= e(setting_get('grievance_name','')) ?>" placeholder="the person a complaint goes to"></div>
    <div class="ff"><label>Their e-mail</label>
      <input class="form-control" type="email" name="grievance_email" value="<?= e(setting_get('grievance_email','')) ?>" placeholder="privacy@yourcompany.com"></div>
  </div>

  <div style="margin-top:18px;display:flex;gap:10px;align-items:center">
    <button class="btn" type="submit">Finish setup →</button>
    <span class="muted" style="font-size:13px">You can skip anything blank and set it later under Settings.</span>
  </div>
</form>
