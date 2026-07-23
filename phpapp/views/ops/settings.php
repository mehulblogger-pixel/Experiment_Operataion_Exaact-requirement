<div class="crumbs"><a href="/">Home</a> › Settings</div>
<h1>System settings</h1>
<p class="sub">Company-wide options — financial year, branding and dashboards.</p>

<form method="post" action="/settings" enctype="multipart/form-data" class="panel" style="max-width:620px;">
  <h3 class="tab-sub" style="margin-top:0;">Branding</h3>
  <div class="form-grid">
    <div class="ff"><label>Application name</label><input class="form-control" name="app_name" value="<?= e(setting_get('app_name','')) ?>" placeholder="e.g. SGS Ahmedabad Ops"></div>
    <div class="ff"><label>Logo (PNG/JPG/SVG, ≤600 KB)</label><input class="form-control" type="file" name="logo" accept="image/*"></div>
    <div class="ff">
      <?php if (setting_get('logo_data','')): ?>
        <label>Current logo</label><div style="background:var(--brand);padding:8px;border-radius:8px;display:inline-block"><?= logo_html() ?></div>
        <label class="chk" style="margin-top:6px"><input type="checkbox" name="clear_logo" value="1"> Remove logo</label>
      <?php else: ?><label class="muted">No logo uploaded yet.</label><?php endif; ?>
    </div>
  </div>

  <h3 class="tab-sub">Theme builder</h3>
  <p class="sub" style="margin-bottom:10px">Pick a ready-made theme, or set your own colours. Changes apply everywhere after you save.</p>
  <?php
    $cp = setting_get('c_primary','') ?: setting_get('brand_color','#1e40af');
    $ca = setting_get('c_accent','#0ea5e9'); $cbg = setting_get('c_bg','#f4f6f9');
    $cs = setting_get('c_surface','#ffffff'); $ct = setting_get('c_text','#1f2937');
    $curFs = (int)(setting_get('font_size','') ?: 14); $curPreset = setting_get('theme_preset','');
  ?>
  <div class="form-grid">
    <div class="ff ff-wide"><label>Built-in themes</label>
      <div class="theme-swatches" id="theme_presets">
        <?php foreach (THEME_PRESETS as $key=>$t): ?>
          <button type="button" class="theme-sw <?= $curPreset===$key?'sel':'' ?>" data-key="<?= e($key) ?>"
            data-primary="<?= e($t['primary']) ?>" data-accent="<?= e($t['accent']) ?>" data-bg="<?= e($t['bg']) ?>" data-surface="<?= e($t['surface']) ?>" data-text="<?= e($t['text']) ?>"
            title="<?= e($t['label']) ?>" style="background:<?= e($t['bg']) ?>">
            <span style="background:<?= e($t['primary']) ?>"></span><span style="background:<?= e($t['accent']) ?>"></span>
            <span style="background:<?= e($t['surface']) ?>;border:1px solid #ccc"></span>
            <em><?= e($t['label']) ?></em>
          </button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="theme_preset" id="theme_preset" value="<?= e($curPreset) ?>">
    </div>
    <div class="ff"><label>① Primary (top bar, buttons)</label><input class="form-control tc" type="color" id="c_primary" name="c_primary" value="<?= e($cp) ?>" style="height:42px;padding:4px"></div>
    <div class="ff"><label>② Accent (charts, highlights)</label><input class="form-control tc" type="color" id="c_accent" name="c_accent" value="<?= e($ca) ?>" style="height:42px;padding:4px"></div>
    <div class="ff"><label>③ Page background</label><input class="form-control tc" type="color" id="c_bg" name="c_bg" value="<?= e($cbg) ?>" style="height:42px;padding:4px"></div>
    <div class="ff"><label>④ Panels / cards</label><input class="form-control tc" type="color" id="c_surface" name="c_surface" value="<?= e($cs) ?>" style="height:42px;padding:4px"></div>
    <div class="ff"><label>Font colour</label><input class="form-control tc" type="color" id="c_text" name="c_text" value="<?= e($ct) ?>" style="height:42px;padding:4px"></div>
    <div class="ff"><label>Base font size</label>
      <select class="form-control" name="font_size" id="font_size">
        <?php foreach ([12=>'Small (12px)',13=>'13px',14=>'Default (14px)',15=>'15px',16=>'Comfortable (16px)',18=>'Large (18px)'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $curFs===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Live preview</label>
      <div id="theme_prev" style="border:1px solid var(--line);border-radius:10px;overflow:hidden">
        <div id="pv_bar" style="padding:8px 12px;color:#fff;font-weight:700">Top bar</div>
        <div id="pv_body" style="padding:12px">
          <div id="pv_card" style="border:1px solid #ddd;border-radius:8px;padding:10px;margin-bottom:8px">Panel / card text
            <div style="margin-top:6px"><span id="pv_btn" style="color:#fff;padding:4px 10px;border-radius:6px;font-size:13px">Button</span>
            <span id="pv_ac" style="color:#fff;padding:4px 10px;border-radius:6px;font-size:13px">Accent</span></div></div>
        </div>
      </div>
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

  <h3 class="tab-sub">Email — automatic sending (Office 365 SMTP)</h3>
  <p class="sub" style="margin-bottom:10px">Fill these to send assignment / closure / reminder emails <strong>automatically</strong> from your mailbox. Leave blank to keep the current behaviour (emails are logged and opened in Outlook to send by hand).</p>
  <div class="form-grid">
    <div class="ff"><label>SMTP host</label><input class="form-control" name="smtp_host" value="<?= e(setting_get('smtp_host','')) ?>" placeholder="smtp.office365.com"></div>
    <div class="ff"><label>Port</label><input class="form-control" type="number" name="smtp_port" value="<?= e(setting_get('smtp_port', 587)) ?>" placeholder="587"></div>
    <div class="ff"><label>Username (mailbox)</label><input class="form-control" name="smtp_user" value="<?= e(setting_get('smtp_user','')) ?>" placeholder="ops@yourcompany.com" autocomplete="off"></div>
    <div class="ff"><label>Password / app password</label><input class="form-control" type="password" name="smtp_pass" value="" placeholder="<?= setting_get('smtp_pass','') ? '•••••••• (leave blank to keep)' : 'enter to enable' ?>" autocomplete="new-password"></div>
    <div class="ff ff-wide"><label>From address <span class="muted">(usually same as the mailbox)</span></label><input class="form-control" name="smtp_from" value="<?= e(setting_get('smtp_from','')) ?>" placeholder="ops@yourcompany.com"></div>
  </div>
  <p class="muted" style="margin:6px 2px">Office 365: host <code>smtp.office365.com</code>, port <code>587</code>. Use an app password if MFA is on. <?= smtp_config() ? '<strong style="color:#15803d">✓ SMTP is configured — emails will auto-send.</strong>' : 'Not configured yet — emails are logged only.' ?></p>

  <div style="margin-top:16px;"><button class="btn" type="submit">Save settings</button></div>
</form>

<?php if (is_master()): ?>
<div class="panel" style="max-width:620px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0;">Demo / sample data</h3>
  <?php if (demo_seeded()): ?>
    <p class="sub" style="margin:0 0 6px"><span class="pill p-ok">Loaded</span> The sample dataset is already in the system.</p>
    <p class="muted" style="margin:0">Demo logins: <code>director</code>, <code>bmanager</code>, <code>account</code>, <code>coord.amd</code>, <code>insp.ravi</code> … — all with password <code>demo12345</code>.</p>
  <?php else: ?>
    <p class="sub" style="margin-bottom:10px">One-click load of a complete example — <strong>offices, users of every role, inspectors, clients, BOSS numbers, calls, jobs, vouchers, invoicing &amp; credit</strong> — so every screen shows live figures. Safe to explore; you can delete records later. It runs only once.</p>
    <form method="post" action="/seed-demo" onsubmit="return confirm('Load the demo/sample dataset now? This adds example records across the whole app.')">
      <button class="btn" type="submit">Load demo data</button>
    </form>
    <p class="muted" style="margin-top:8px">Creates demo logins (director, sbuhead, bmanager, appmanager, opmanager, asstmgr, coord.amd, coord.pun, account, insp.ravi, insp.anil) — all password <code>demo12345</code>. Use a fresh/test install, not a database that already holds real data.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<style>
  .theme-swatches{display:flex;flex-wrap:wrap;gap:10px}
  .theme-sw{cursor:pointer;border:2px solid var(--line);border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:5px;align-items:center;min-width:90px}
  .theme-sw.sel{border-color:var(--brand);box-shadow:0 0 0 2px rgba(0,0,0,.06)}
  .theme-sw span{display:inline-block;width:20px;height:20px;border-radius:5px;margin:0 2px}
  .theme-sw > span{display:inline-block}
  .theme-sw em{font-size:11px;font-style:normal;color:var(--muted)}
  .theme-sw div{display:flex}
</style>
<script>
(function(){
  var ids=['c_primary','c_accent','c_bg','c_surface','c_text'];
  var map={primary:'c_primary',accent:'c_accent',bg:'c_bg',surface:'c_surface',text:'c_text'};
  function el(id){return document.getElementById(id);}
  function preview(){
    var p=el('c_primary').value,a=el('c_accent').value,bg=el('c_bg').value,s=el('c_surface').value,t=el('c_text').value;
    el('pv_bar').style.background=p; el('pv_body').style.background=bg; el('pv_body').style.color=t;
    el('pv_card').style.background=s; el('pv_card').style.color=t;
    el('pv_btn').style.background=p; el('pv_ac').style.background=a;
  }
  ids.forEach(function(id){ el(id).addEventListener('input', preview); });
  document.querySelectorAll('.theme-sw').forEach(function(b){
    b.addEventListener('click', function(){
      Object.keys(map).forEach(function(k){ el(map[k]).value=b.dataset[k]; });
      el('theme_preset').value=b.dataset.key;
      document.querySelectorAll('.theme-sw').forEach(function(x){x.classList.remove('sel');});
      b.classList.add('sel'); preview();
    });
  });
  preview();
})();
</script>
