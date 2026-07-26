<div class="crumbs"><a href="/">Home</a> › Settings</div>
<h1>System settings</h1>
<p class="sub">Company-wide options — financial year, branding and dashboards.</p>

<form method="post" action="/settings" enctype="multipart/form-data" class="panel" style="max-width:620px;">
  <h3 class="tab-sub" style="margin-top:0;">Branding</h3>
  <div class="form-grid">
    <div class="ff"><label>Application name</label><input class="form-control" name="app_name" value="<?= e(setting_get('app_name','')) ?>" placeholder="e.g. Exaact Inspection Ops"></div>
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
    <div class="ff"><label>Annual revenue target (<?= e(cur_sym()) ?>) <span class="muted">— shows on the leadership dashboard</span></label>
      <input class="form-control" type="number" min="0" step="1000" name="fy_revenue_target" value="<?= e(setting_get('fy_revenue_target', '')) ?>" placeholder="e.g. 50000000"></div>
    <div class="ff"><label>Report-overdue escalation (days) <span class="muted">— then e-mail the reporting manager</span></label>
      <input class="form-control" type="number" min="1" name="report_escalate_days" value="<?= e(setting_get('report_escalate_days', 3)) ?>"></div>
    <div class="ff"><label>Contract expiry warning (days) <span class="muted">— how far ahead to start warning</span></label>
      <input class="form-control" type="number" min="1" max="365" name="contract_warn_days" value="<?= e(setting_get('contract_warn_days', 30)) ?>">
      <small class="muted">Everyone on the order is e-mailed once when a contract comes inside this window. Past the end
        date, scheduling stops until the Super Admin grants an exception.</small></div>
  </div>

  <h3 class="tab-sub">Working norms &amp; limits</h3>
  <p class="sub" style="margin-bottom:10px">The ceiling and defaults the whole app enforces. Per-designation and per-<?= e(Tl('office')) ?> norms are set separately under <a href="/work-norms">Working norms</a>.</p>
  <div class="form-grid">
    <div class="ff"><label>Maximum hours a person may log in one day</label>
      <input class="form-control" type="number" step="0.25" min="1" max="24" name="daily_hours_cap" value="<?= e(hours_cap()) ?>">
      <small class="muted">Vouchers and timesheets are rejected above this.</small></div>
    <div class="ff"><label>Default weekly working days</label>
      <select class="form-control" name="default_weekly_days">
        <?php /* pairs, not an array key — PHP would truncate the 5.5 key to 5 */
              $dw = default_weekly_days(); foreach ([['6','6 days'], ['5.5','5.5 days'], ['5','5 days']] as [$k, $lbl]): ?>
          <option value="<?= e($k) ?>" <?= ((float)$dw === (float)$k) ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
      </select><small class="muted">Used when a person has no norm of their own. 5.5 means five full days plus one half day.</small></div>
    <div class="ff"><label>Hours on a half day</label>
      <input class="form-control" type="number" step="0.25" min="0.5" max="12" name="half_day_hours" value="<?= e(company_half_day_hours()) ?>">
      <small class="muted">A half day is its own length, not half of a full one — four hours by default, against a full day of
        <?= e(hours_cap()) ?> h. Anybody whose half day differs has their own figure on their person record.</small></div>
    <div class="ff"><label>Employee code prefix <span class="muted">(own staff)</span></label>
      <input class="form-control" name="emp_code_prefix" value="<?= e(setting_get('emp_code_prefix','')) ?>" placeholder="EMP">
      <small class="muted">Sub-contractors stay <code>SC-</code>, freelancers <code>FL-</code>.</small></div>
  </div>

  <h3 class="tab-sub">Display</h3>
  <div class="form-grid">
    <div class="ff"><label>Currency symbol</label>
      <input class="form-control" name="currency_symbol" value="<?= e(setting_get('currency_symbol','')) ?>" placeholder="<?= e(cur_sym()) ?>" maxlength="4"></div>
    <div class="ff"><label>Date format</label>
      <select class="form-control" name="date_format">
        <?php $cdf = date_fmt(); foreach (DATE_FORMATS as $k=>$sample): ?>
          <option value="<?= e($k) ?>" <?= $cdf===$k?'selected':'' ?>><?= e($sample) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Wording used across the app</label>
      <a class="btn small secondary" href="/terminology" style="margin-top:2px">Open terminology</a>
      <small class="muted">Rename <?= e(Tlp('client')) ?>, <?= e(Tlp('call')) ?>, <?= e(Tlp('job')) ?> and every other business word.</small></div>
  </div>

  <h3 class="tab-sub">Default terms &amp; conditions</h3>
  <p class="sub" style="margin-bottom:10px">Carried onto every new <?= e(Tl('quote')) ?>, where it can still be edited for that one <?= e(Tl('quote')) ?>. Changing it here does not alter <?= e(Tlp('quote')) ?> already written.</p>
  <textarea class="form-control" name="quote_terms" rows="10" style="font-family:inherit"><?= e(setting_get('quote_terms','') !== '' ? setting_get('quote_terms') : crm_default_terms()) ?></textarea>

  <h3 class="tab-sub">Reporting controls</h3>
  <div class="form-grid">
    <div class="ff ff-wide"><label>Source documents a complete inspection pack must contain</label>
      <div class="chip-row">
        <?php $esd = expected_source_docs(); foreach (lk_options_or('source_doc_type', SOURCE_DOC_TYPES) as $k=>$lbl): ?>
          <label class="ff-check"><input type="checkbox" name="expected_source_docs[]" value="<?= e($k) ?>" <?= in_array($k,$esd,true)?'checked':'' ?>> <?= e($lbl) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Missing ones are flagged on the report review screen.</small></div>
    <div class="ff ff-wide"><label>Actions a compliance reviewer should always see</label>
      <div class="chip-row">
        <?php $hr = audit_high_risk(); foreach (AUDIT_ACTIONS_ALL as $a): ?>
          <label class="ff-check"><input type="checkbox" name="audit_high_risk[]" value="<?= e($a) ?>" <?= in_array($a,$hr,true)?'checked':'' ?>> <?= e(audit_action_label($a)) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">These are highlighted in red on the audit trail and counted as "high-risk".</small></div>
  </div>

  <h3 class="tab-sub">Security</h3>
  <p class="sub" style="margin-bottom:10px">
    These choose how strict each guard is. None of them switches a guard off — a password is always
    scrambled before it is stored, every save is always checked for where it came from, and every
    sign-in is always recorded, whatever is set here.
  </p>
  <div class="form-grid">
    <div class="ff"><label>Shortest password allowed</label>
      <input class="form-control" type="number" min="8" max="64" name="pwd_min_len" value="<?= (int)pwd_min_len() ?>">
      <small class="muted">Applied the moment a password is chosen. A letter and a number are always required.</small></div>
    <div class="ff"><label>Force a new password after (days)</label>
      <input class="form-control" type="number" min="0" max="730" name="pwd_max_age_days" value="<?= (int)pwd_max_age_days() ?>" placeholder="0 = never">
      <small class="muted">0 means never. Set 90 or 180 only if a client contract asks for it — forced rotation tends to push people towards weaker passwords they can remember.</small></div>
    <div class="ff"><label>Sign out after idle (minutes)</label>
      <input class="form-control" type="number" min="5" max="1440" name="session_idle_min" value="<?= (int)session_idle_min() ?>">
      <small class="muted">A laptop left open at a client's plant is the real risk here.</small></div>
    <div class="ff"><label>Sign out after (hours) regardless</label>
      <input class="form-control" type="number" min="1" max="168" name="session_max_hours" value="<?= (int)session_max_hours() ?>">
      <small class="muted">Ends even a session somebody keeps awake.</small></div>
    <div class="ff"><label>Keep the audit trail for (days)</label>
      <input class="form-control" type="number" min="180" max="3650" name="audit_retain_days" value="<?= (int)audit_retain_days() ?>">
      <small class="muted">The CERT-In directions require at least 180 days, so that is the floor. 400 covers a full year plus an audit cycle.</small></div>
    <div class="ff ff-wide"><label>Roles that must use two-step sign-in</label>
      <div class="chip-row">
        <?php $tr = twofa_required_roles(); foreach (ORG_ROLES as $rk=>$rl): ?>
          <label class="ff-check"><input type="checkbox" name="twofa_roles[]" value="<?= e($rk) ?>" <?= in_array($rk,$tr,true)?'checked':'' ?>> <?= e($rl) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">People in these roles are asked to set it up at their next sign-in and cannot turn it off themselves. Start with the roles that can move money or change permissions.</small></div>
    <div class="ff"><label>Largest attachment allowed (MB)</label>
      <input class="form-control" type="number" min="1" max="64" name="upload_max_mb" value="<?= (int)upload_max_mb() ?>">
      <small class="muted">Applies everywhere a file can be attached.</small></div>
  </div>

  <h3 class="tab-sub">Compliance</h3>
  <p class="sub" style="margin-bottom:10px">
    None of this is written by the software, and leaving it blank is itself what
    <a href="/compliance">Where we stand</a> will report. The DPDP Act requires a named person to complain to and
    a notice saying what is collected and why; the CERT-In directions require an independent audit once a year.
  </p>
  <div class="form-grid">
    <div class="ff"><label>Grievance officer — name</label>
      <input class="form-control" name="grievance_name" value="<?= e(setting_get('grievance_name','')) ?>" placeholder="The person a complaint goes to"></div>
    <div class="ff"><label>Their e-mail</label>
      <input class="form-control" type="email" name="grievance_email" value="<?= e(setting_get('grievance_email','')) ?>" placeholder="privacy@yourcompany.com">
      <small class="muted">Use an address that is actually read. It is published on the privacy page.</small></div>
    <div class="ff"><label>Their phone</label>
      <input class="form-control" name="grievance_phone" value="<?= e(setting_get('grievance_phone','')) ?>"></div>
    <div class="ff"><label>Date of the last independent security audit</label>
      <input class="form-control" type="date" name="last_cert_audit" value="<?= e(setting_get('last_cert_audit','')) ?>">
      <small class="muted">By a CERT-In empanelled auditor. The list is published on cert-in.org.in.</small></div>
    <div class="ff ff-wide"><label>Privacy notice <span class="muted">— shown at /privacy</span></label>
      <?php // A draft rather than a blank box. A notice nobody can start writing
            // is a notice that never gets written, and the Act does not care why. ?>
      <textarea class="form-control" name="privacy_notice" rows="10" placeholder="<?= e(
"What we hold, and why\n\n" .
"We are an inspection company. To do the work you or your employer asked for, this system holds:\n" .
"  · the name, designation, e-mail and phone number of the people we deal with at client and vendor companies, so that inspections can be arranged and reports sent;\n" .
"  · for our own staff, the details needed to employ them and to schedule work;\n" .
"  · the inspection records themselves — dates, findings, photographs taken on site, and who approved what.\n\n" .
"Why we are allowed to hold it\n" .
"Most of it is needed to perform a contract. Inspection records are kept because our clients, and the standards we work to, require them to be kept.\n\n" .
"How long we keep it\n" .
"Contact details for as long as we work with that company. Inspection records and the trail of who changed what for the period our clients and the applicable standards require.\n\n" .
"Who else sees it\n" .
"Nobody outside this company, except where a client is entitled to the report of their own inspection, or where the law requires it. The system sends nothing to any other service.\n\n" .
"What you can ask for\n" .
"A copy of what we hold about you, a correction, deletion, or the withdrawal of consent you gave earlier. Write to the person named below and we will log it and answer.\n\n" .
"Where it is kept\n" .
"On our hosting account in India."
      ) ?>"><?= e(setting_get('privacy_notice','')) ?></textarea>
      <small class="muted">The grey text is a starting draft written for an inspection business — read it, change what is not true of you, and paste it in.</small></div>
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
  <h3 class="tab-sub" style="margin-top:0;">Roles &amp; access</h3>
  <p class="sub" style="margin-bottom:10px">Control which <strong>modules and features</strong> each role can view or edit — Calls, Jobs, Vouchers, Invoicing, Profitability, Masters, Users, Settings and more. Set defaults per role; fine-tune per person under Users.</p>
  <a class="btn" href="/access">Open Roles &amp; access</a>
</div>

<div class="panel" style="max-width:620px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0;">Terminology</h3>
  <p class="sub" style="margin-bottom:10px">Rename every business word the app uses — <?= e(Tl('client')) ?>, <?= e(Tl('vendor')) ?>, <?= e(Tl('quote')) ?>, <?= e(Tl('call')) ?>, <?= e(Tl('job')) ?>, <?= e(Tl('report')) ?>, <?= e(T('office')) ?>, <?= e(T('boss')) ?> and the rest. Change a word once and every heading, menu, button and e-mail follows.</p>
  <a class="btn" href="/terminology">Open terminology</a>
</div>

<div class="panel" style="max-width:620px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0;">AI providers &amp; models</h3>
  <p class="sub" style="margin-bottom:10px">Enter API keys for OpenAI, Claude, Gemini, Perplexity or GitHub Copilot / Models, refresh each provider's live model list (retired models drop off), and pick which models to use.</p>
  <a class="btn" href="/ai-settings">Open AI settings</a>
</div>

<div class="panel" style="max-width:620px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0;">Demo / sample data</h3>
  <?php if (demo_seeded()): ?>
    <p class="sub" style="margin:0 0 6px"><span class="pill p-ok">Loaded</span> The sample dataset is already in the system.</p>
    <p class="muted" style="margin:0 0 10px">Demo logins: <code>director</code>, <code>bmanager</code>, <code>account</code>, <code>coord.amd</code>, <code>insp.ravi</code> … — all with password <code>demo12345</code>.</p>
    <form method="post" action="/seed-demo-remove" onsubmit="return confirm('Remove ALL demo/sample data (offices left in place)? Your own real records are not touched. You can load the demo again later.')">
      <button class="btn danger" type="submit">🗑 Remove demo data</button>
    </form>
    <p class="muted" style="margin-top:8px;font-size:12px">Deletes only the seeded calls, jobs, vouchers, demo inspectors, clients/vendors, BOSS numbers and demo logins. The three demo offices are left in place (delete them under Masters if you want).</p>
  <?php else: ?>
    <p class="sub" style="margin-bottom:10px">One-click load of a complete example — <strong>offices, users of every role, inspectors, clients, BOSS numbers, calls, jobs, vouchers, invoicing &amp; credit</strong> — so every screen shows live figures. Safe to explore; you can delete records later. It runs only once.</p>
    <form method="post" action="/seed-demo" onsubmit="return confirm('Load the demo/sample dataset now? This adds example records across the whole app.')">
      <button class="btn" type="submit">Load demo data</button>
    </form>
    <p class="muted" style="margin-top:8px">Creates demo logins (director, sbuhead, bmanager, appmanager, opmanager, asstmgr, coord.amd, coord.pun, account, insp.ravi, insp.anil) — all password <code>demo12345</code>. Use a fresh/test install, not a database that already holds real data.</p>
  <?php endif; ?>
</div>

<div class="panel" style="max-width:620px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0;">Clear records</h3>
  <p class="sub" style="margin-bottom:10px">For setting up and testing: empty whole groups of records — day-to-day work, reports, costing figures, <?= e(Tlp('client')) ?> &amp; <?= e(Tlp('vendor')) ?>, people, master lists — and start again with a clean register. You see the count before anything happens, and your own login is never deleted.</p>
  <a class="btn danger" href="/reset-data">Open clear records</a>
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
