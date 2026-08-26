<div class="crumbs"><a href="/">Home</a> › Settings</div>
<h1>System settings</h1>
<p class="sub">Company-wide options — financial year, branding and dashboards.
  <a href="/preflight">Server check &amp; version <?= e(APP_VERSION) ?> →</a></p>

<?php // Module 14 — every configuration change is now recorded on the sealed audit
      // chain (who, when, old → new). Secrets record the event, never the value. ?>
<?php if (is_master() || can('idems.audit.view')): ?>
<p class="muted" style="font-size:12px;margin:-4px 0 10px">🛡️ Changes here are recorded on the audit trail —
  <a href="/audit-log?action=SETTING_CHANGED">see who changed what →</a>
  &nbsp;·&nbsp; 📨 <a href="/notifications">Notification log</a> (what email went out)
  &nbsp;·&nbsp; 🔌 <a href="/integrations">Integration health</a> (are the syncs working)</p>
<?php endif; ?>

<?php // Which parts of the product this installation runs. Its own form, because
      // switching a module off changes what everybody can see and should not be
      // saved by accident alongside a logo upload. ?>
<?php if (is_master()): $mods = licence_summary(); $pinned = (bool)getenv('MODULES_OFF'); ?>
<form method="post" action="/settings" class="panel settings-form">
  <input type="hidden" name="modules_form" value="1">
  <h3 class="tab-sub" style="margin-top:0;">Modules</h3>
  <p class="sub" style="margin-top:0">What this installation has. Switching one off hides it from
    <strong>everybody, including you</strong> — the menu group disappears and its screens refuse.
    Nothing is deleted; switching it back on restores it exactly as it was.</p>
  <?php if ($pinned): ?>
    <div class="msg msg-warning">Modules are pinned by this server's configuration
      (<code>MODULES_OFF</code>) and cannot be changed from this screen.</div>
  <?php endif; ?>
  <div class="checkgrid" style="grid-template-columns:1fr">
    <?php foreach ($mods as $key => $m): ?>
      <label class="chk" style="align-items:flex-start;padding:7px 2px">
        <input type="checkbox" name="mod_on[<?= e($key) ?>]" value="1"
               <?= $m['on'] ? 'checked' : '' ?> <?= ($m['core'] || $pinned) ? 'disabled' : '' ?>>
        <span><strong><?= e($m['label']) ?></strong>
          <?php if ($m['core']): ?><span class="pill p-mut">always on</span><?php endif; ?>
          <br><span class="muted" style="font-size:12px"><?= e($m['desc']) ?></span></span>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="font-size:12px;margin:8px 2px">Operations and Administration cannot be switched off —
    a system without <?= e(Tlp('call')) ?>, <?= e(Tlp('job')) ?>, masters and users is not a smaller product.</p>
  <?php if (!$pinned): ?><button class="btn" type="submit">Save modules</button><?php endif; ?>
</form>
<?php endif; ?>

<form method="post" action="/settings" enctype="multipart/form-data" class="panel settings-form">
<div data-tabs data-tabs-key="prefs" data-tabs-order="Branding &amp; theme,Financial &amp; norms,Display &amp; terms,Numbering &amp; reports,Security">
<section class="fs-pane" data-tab="Branding &amp; theme">
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

</section>
<section class="fs-pane" data-tab="Financial &amp; norms">
  <h3 class="tab-sub" style="margin-top:0">Financial &amp; operations</h3>
  <div class="form-grid">
    <div class="ff"><label>Financial year starts in</label>
      <select class="form-control" name="fy_start_month">
        <?php $months=['1'=>'January','2'=>'February','3'=>'March','4'=>'April','5'=>'May','6'=>'June','7'=>'July','8'=>'August','9'=>'September','10'=>'October','11'=>'November','12'=>'December'];
        $cur=(string)fy_start_month(); foreach ($months as $k=>$v): ?><option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select><small class="muted">India = April. This year (from today) = <strong><?= e(current_fy()) ?></strong>.</small></div>
    <div class="ff"><label>Current financial year <span class="muted">— what every register opens on</span></label>
      <select class="form-control" name="fy_current">
        <?php $fyPin = strtoupper(trim((string)setting_get('fy_current', ''))); ?>
        <option value="AUTO" <?= ($fyPin === '' || $fyPin === 'AUTO') ? 'selected' : '' ?>>Automatic — follow today (<?= e(current_fy()) ?>)</option>
        <?php foreach (fy_options() as $o): ?><option value="<?= e($o) ?>" <?= $fyPin === strtoupper($o) ? 'selected' : '' ?>>FY <?= e($o) ?></option><?php endforeach; ?>
      </select><small class="muted">Calls, quotes, jobs and invoices open on this year. A per-screen year filter still overrides it. Opening on <strong>FY <?= e(active_fy()) ?></strong><?= fy_is_pinned() ? ' (pinned)' : '' ?>.</small></div>
    <div class="ff"><label>On-time TAT threshold (days)</label>
      <input class="form-control" type="number" min="0" name="tat_threshold_days" value="<?= e(setting_get('tat_threshold_days', 3)) ?>"></div>
    <div class="ff"><label>Annual revenue target (<?= e(cur_sym()) ?>) <span class="muted">— shows on the leadership dashboard</span></label>
      <input class="form-control" type="number" min="0" step="1000" name="fy_revenue_target" value="<?= e(setting_get('fy_revenue_target', '')) ?>" placeholder="e.g. 50000000"></div>
    <div class="ff"><label>Report-overdue escalation (days) <span class="muted">— then e-mail the reporting manager</span></label>
      <input class="form-control" type="number" min="1" name="report_escalate_days" value="<?= e(setting_get('report_escalate_days', 3)) ?>"></div>
    <?php // A man-month is a commercial term, not a fact about the calendar,
          // and clients mean different things by it. This is the company
          // default; a client's record can override it, and a single deputation
          // can override that. Most specific wins, and the allocate screen says
          // which of the three it took. ?>
    <div class="ff"><label>What a man-month means <span class="muted">— company default</span></label>
      <select class="form-control" name="manmonth_basis">
        <?php $curMm = setting_get('manmonth_basis', 'CALENDAR');
              foreach (MANMONTH_BASES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curMm === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Overridden on a <?= e(Tl('client') ) ?>'s record, and on a single <?= e(Tl('job')) ?>, when that client's contract says something different.</small></div>
    <div class="ff"><label>Minimum working days in a man-month</label>
      <input class="form-control" type="number" min="1" max="31" name="manmonth_min_days" value="<?= e(setting_get('manmonth_min_days', 26)) ?>">
      <small class="muted">Only used on the minimum-days basis. A month falling short of this is claimable pro-rata; a month exceeding it is still exactly one man-month.</small></div>
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
    <?php // The deadline for closing a job, and whether it bites at all. Two days
          // is what was asked for; a business that works differently changes it
          // here rather than asking for a code change. ?>
    <div class="ff"><label>Days to close a <?= e(Tl('job')) ?> after the inspection ends</label>
      <input class="form-control" type="number" step="1" min="0" max="365" name="job_close_grace_days" value="<?= e((string)job_close_grace_days()) ?>">
      <small class="muted">Miss it and the <?= e(Tl('job')) ?> locks itself: dates, man-days, expenses and credit
        are fixed as they stand, and the <?= e(Tl('engineer')) ?>, coordinator, branch manager and administrators are told.
        Reports and photographs can still be uploaded. A manager can reopen it with a reason.</small></div>
    <div class="ff ff-check"><label><input type="checkbox" name="job_lock_enabled" value="1" <?= job_lock_enabled() ? 'checked' : '' ?>>
      Lock <?= e(Tlp('job')) ?> that are not closed in time</label>
      <small class="muted">Untick to turn the deadline off entirely — nothing locks, and no alerts are sent.</small></div>
    <div class="ff"><label>Employee code prefix <span class="muted">(own staff)</span></label>
      <input class="form-control" name="emp_code_prefix" value="<?= e(setting_get('emp_code_prefix','')) ?>" placeholder="EMP">
      <small class="muted">Sub-contractors stay <code>SC-</code>, freelancers <code>FL-</code>.</small></div>
  </div>

</section>
<section class="fs-pane" data-tab="Display &amp; terms">
  <h3 class="tab-sub" style="margin-top:0">Display</h3>
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

  <?php if (function_exists('numbering_types')): ?>
</section>
<section class="fs-pane" data-tab="Numbering &amp; reports">
  <h3 class="tab-sub" style="margin-top:0">Document numbering</h3>
  <p class="sub" style="margin-bottom:10px">Set how each reference is built — the prefix, the separator, how many digits,
    whether the financial year is in it, and the number to start from. The example updates as you change it. Numbers already
    issued are never renumbered; new ones follow the scheme from the next one on.</p>
  <input type="hidden" name="numbering_form" value="1">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid" style="min-width:720px">
    <tr><th>Document</th><th>Prefix</th><th>Separator</th><th>Digits</th><th>Financial year</th><th>Start&nbsp;from</th><th>Example</th></tr>
    <?php foreach (numbering_types() as $nk => $nt): $sc = numbering_scheme($nk); $fromOffice = !empty($sc['prefix_from']); ?>
    <tr data-num="<?= e($nk) ?>">
      <td><b><?= e($nt['label']) ?></b><?php if (!empty($sc['money'])): ?><br><span class="muted" style="font-size:11px">resets each financial year</span><?php endif; ?></td>
      <td><?php if ($fromOffice): ?>
            <span class="muted" style="font-size:12px">from office code</span>
            <input type="hidden" data-f="prefix" name="num_<?= e($nk) ?>_prefix" value="<?= e($sc['prefix']) ?>">
          <?php else: ?>
            <input class="form-control num-in" data-f="prefix" style="width:90px" name="num_<?= e($nk) ?>_prefix" value="<?= e($sc['prefix']) ?>" maxlength="20">
          <?php endif; ?></td>
      <td><input class="form-control num-in" data-f="sep" style="width:56px;text-align:center" name="num_<?= e($nk) ?>_sep" value="<?= e($sc['sep']) ?>" maxlength="3"></td>
      <td><input class="form-control num-in" data-f="pad" type="number" min="1" max="12" style="width:64px" name="num_<?= e($nk) ?>_pad" value="<?= (int)$sc['pad'] ?>"></td>
      <td style="white-space:nowrap">
        <label class="ff-check" style="display:inline-flex;gap:5px"><input type="checkbox" class="num-in" data-f="fy" name="num_<?= e($nk) ?>_fy" value="1" <?= $sc['fy'] ? 'checked' : '' ?>> in number</label>
        <select class="form-control num-in" data-f="fy_style" name="num_<?= e($nk) ?>_fy_style" style="width:110px;margin-top:4px">
          <?php foreach (numbering_fy_styles() as $sv => $sample): ?>
            <option value="<?= e($sv) ?>" <?= $sc['fy_style'] === $sv ? 'selected' : '' ?>><?= e($sample) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input class="form-control num-in" data-f="start" type="number" min="0" style="width:80px" name="num_<?= e($nk) ?>_start" value="<?= (int)$sc['start'] ?>" placeholder="auto"></td>
      <td><code class="num-eg" style="font-size:14px"><?= e(numbering_preview($nk)) ?></code></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted" style="font-size:12.5px;margin-top:8px"><b><?= e(Tl('report')) ?> numbers (IRN)</b> use a richer token
    template (company, branch, year, client, type, serial) configured under
    <a href="/irn-rules">Reports → IRN numbering rules</a>. Contract numbers are entered by hand from the client's PO.</p>
  <script>
  (function(){
    var fyNow = <?= json_encode(array_map(fn($s)=>numbering_fy_string($s), array_combine(array_keys(numbering_fy_styles()), array_keys(numbering_fy_styles())))) ?>;
    document.querySelectorAll('tr[data-num]').forEach(function(row){
      function redraw(){
        var g=function(f){return row.querySelector('[data-f="'+f+'"]');};
        var prefix=(g('prefix').value||'').trim(), sep=g('sep').value||'', pad=Math.max(1,Math.min(12,parseInt(g('pad').value)||5));
        var fy=g('fy').checked, style=g('fy_style').value, start=parseInt(g('start').value)||42;
        var stem=prefix + (fy ? sep + (fyNow[style]||'') : '') + sep;
        var num=String(Math.max(1,start)).padStart(pad,'0');
        row.querySelector('.num-eg').textContent = stem + num;
      }
      row.querySelectorAll('.num-in').forEach(function(el){ el.addEventListener('input',redraw); el.addEventListener('change',redraw); });
    });
  })();
  </script>
  <?php endif; ?>

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
    <div class="ff ff-check"><label><input type="checkbox" name="notify_client_on_issue" value="1" <?= setting_get('notify_client_on_issue','1') !== '0' ? 'checked' : '' ?>>
      E-mail the <?= e(Tl('client')) ?> when a <?= e(Tl('report')) ?> is issued</label>
      <small class="muted">A short note that it is ready, with a link to sign in to the portal and the public
        verification code — never the <?= e(Tl('report')) ?> itself or any finding. Sent to the portal users who
        can see reports, or the primary contact if none.</small></div>
  </div>

</section>
<section class="fs-pane" data-tab="Security">
  <h3 class="tab-sub" style="margin-top:0">Security</h3>
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
      <div class="chip-row" id="twofaRoles">
        <?php $tr = twofa_required_roles(); foreach (ORG_ROLES as $rk=>$rl): ?>
          <label class="ff-check"><input type="checkbox" name="twofa_roles[]" value="<?= e($rk) ?>" <?= in_array($rk,$tr,true)?'checked':'' ?>> <?= e($rl) ?></label>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
        <?php $money = twofa_money_roles(); ?>
        <button type="button" class="btn secondary" style="font-size:12.5px;padding:5px 11px"
          onclick="twofaPick(<?= e(json_encode($money)) ?>)">🔒 Require it for the money roles</button>
        <button type="button" class="btn secondary" style="font-size:12.5px;padding:5px 11px"
          onclick="twofaPick([])">Clear all</button>
      </div>
      <script>
      function twofaPick(list){
        document.querySelectorAll('#twofaRoles input[name="twofa_roles[]"]').forEach(function(cb){
          cb.checked = list.indexOf(cb.value) !== -1;
        });
      }
      </script>
      <small class="muted">People in these roles are asked to set it up at their next sign-in and cannot turn it off themselves. The <b>money roles</b> button ticks the accounts that move money, change permissions or issue a report in the company's name — then press <b>Save</b>.</small></div>
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

</section>
</div><!-- /.prefs tabs -->
  <div style="margin-top:16px;"><button class="btn" type="submit">Save settings</button></div>
</form>

<?php if (is_master()): ?>
<div class="settings-cards">
<?php if (function_exists('lk_console_allowed') && lk_console_allowed()): ?>
<div class="panel settings-card" style="border-left:3px solid var(--brand)">
  <h3 class="tab-sub" style="margin-top:0;">Super Admin</h3>
  <p class="sub" style="margin-bottom:10px">Everything you run as the software provider — cloud workspaces, customer
    licences, per-user pricing and the one-click signing setup — in one place.</p>
  <a class="btn" href="/vendor">Open Super Admin</a>
</div>
<?php endif; ?>
<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Custom forms</h3>
  <p class="sub" style="margin-bottom:10px">Build a whole new register with no coding — its own fields and dropdowns — and it appears in the
    menu under the module you choose.</p>
  <a class="btn" href="/cforms">Build a form</a>
</div>
<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Your company</h3>
  <p class="sub" style="margin-bottom:10px">Your legal name, address, GSTIN and logo — entered once, shown on your quotations,
    invoices and records.</p>
  <a class="btn" href="/company-profile">Edit company profile</a>
</div>
<?php $curIndT = function_exists('industry_current') ? (string)industry_current() : ''; ?>
<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Your industry</h3>
  <p class="sub" style="margin-bottom:10px">One choice builds the whole thing for your trade — the sales pipeline &amp; funnel,
    the wording across the app (<?= e(Tl('call')) ?>, <?= e(Tl('report')) ?>, <?= e(Tl('client')) ?>…), and the
    inspection accreditation rules when the trade is inspection.</p>
  <p class="muted" style="margin:0 0 10px">Now: <strong><?= e($curIndT && defined('INDUSTRY_TEMPLATES') && isset(INDUSTRY_TEMPLATES[$curIndT]) ? INDUSTRY_TEMPLATES[$curIndT]['label'] : 'Not set') ?></strong>.
    Fine-tune individual words under <a href="/terminology">Terminology</a>.</p>
  <a class="btn" href="/industry">Choose your industry</a>
</div>

<?php if (function_exists('billing_can_manage') && billing_can_manage()): ?>
<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Users &amp; billing</h3>
  <p class="sub" style="margin-bottom:10px">Pay per person, monthly or yearly. Add or renew seats online — the seat count
    goes live the moment payment clears.<?= (function_exists('billing_configured') && billing_configured()) ? '' : ' <em>Set your price and Razorpay keys to switch it on.</em>' ?></p>
  <a class="btn" href="/billing">Open users &amp; billing</a>
</div>
<?php endif; ?>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Roles &amp; access</h3>
  <p class="sub" style="margin-bottom:10px">Control which <strong>modules and features</strong> each role can view or edit — Calls, Jobs, Vouchers, Invoicing, Profitability, Masters, Users, Settings and more. Set defaults per role; fine-tune per person under Users.</p>
  <a class="btn" href="/access">Open Roles &amp; access</a>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Terminology</h3>
  <p class="sub" style="margin-bottom:10px">Rename every business word the app uses — <?= e(Tl('client')) ?>, <?= e(Tl('vendor')) ?>, <?= e(Tl('quote')) ?>, <?= e(Tl('call')) ?>, <?= e(Tl('job')) ?>, <?= e(Tl('report')) ?>, <?= e(T('office')) ?>, <?= e(T('boss')) ?> and the rest. Change a word once and every heading, menu, button and e-mail follows.</p>
  <a class="btn" href="/terminology">Open terminology</a>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">AI providers &amp; models</h3>
  <p class="sub" style="margin-bottom:10px">Enter API keys for OpenAI, Claude, Gemini, Perplexity or GitHub Copilot / Models, refresh each provider's live model list (retired models drop off), and pick which models to use.</p>
  <a class="btn" href="/ai-settings">Open AI settings</a>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Industry packs</h3>
  <p class="sub" style="margin-bottom:10px">This application is general. A pack adds the rules of one industry —
    and a business that is not in that industry should not meet them. Switching a pack off leaves every register
    and every record in place; it only stops the gates firing.</p>
  <form method="post" action="/packs-save">
    <?php foreach (packs_available() as $k => $p): ?>
      <label style="display:flex;gap:9px;align-items:flex-start;margin:8px 0;font-size:14px">
        <input type="checkbox" name="packs[]" value="<?= e($k) ?>" <?= pack_on($k) ? 'checked' : '' ?>>
        <span><b><?= $p['label'] ?></b><br><span class="muted" style="font-size:12.5px"><?= $p['desc'] ?></span></span>
      </label>
    <?php endforeach; ?>
    <button class="btn" style="margin-top:10px">Save</button>
  </form>
  <p class="muted" style="margin-top:10px;font-size:12.5px">Inside a pack the rules are <b>not</b> negotiable — an
    inspection body cannot untick §8.7.3. Choosing packs is an installation decision; softening a standard is not
    a decision anybody gets to make.</p>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Demo / sample data</h3>
  <?php if (function_exists('demo_flag_is_stale') && demo_flag_is_stale()): ?>
    <?php // The flag says loaded and the records are not there — a load that was
          // cut off part-way, which on a shared host means the page hit its time
          // limit. This state used to be a dead end: load refused as "already
          // loaded", and "remove" had nothing to remove. ?>
    <p class="sub" style="margin:0 0 6px"><span class="pill p-bad">Half loaded</span>
      The system is marked as having demo data, but the records are not there — the last load was almost
      certainly cut short by this server's page time limit.</p>
    <p class="muted" style="margin:0 0 10px">Load it again below. If it stops short a second time, run it from
      the command line instead, where there is no time limit:
      <code>php tools/seed-demo.php --force</code> (cPanel → Terminal). <code>php tools/seed-demo.php --status</code>
      says what is currently there.</p>
    <form method="post" action="/seed-demo">
      <input type="hidden" name="force" value="1">
      <button class="btn" type="submit">Load demo data again</button>
    </form>
  <?php elseif (demo_seeded()): ?>
    <p class="sub" style="margin:0 0 6px"><span class="pill p-ok">Loaded</span> The sample dataset is already in the system.</p>
    <p class="muted" style="margin:0 0 10px">Demo logins: <code>director</code>, <code>bmanager</code>, <code>account</code>, <code>coord.amd</code>, <code>insp.ravi</code> … — all with password <code>demo12345</code>.</p>
    <form method="post" action="/seed-demo-remove" onsubmit="return confirm('Remove ALL demo/sample data (offices left in place)? Your own real records are not touched. You can load the demo again later.')">
      <button class="btn danger" type="submit">🗑 Remove demo data</button>
    </form>
    <p class="muted" style="margin-top:8px;font-size:12px">Deletes the seeded records across every module — <?= e(Tlp('call')) ?>, <?= e(Tlp('job')) ?>, vouchers, quotations, <?= e(Tlp('report')) ?> and their evidence, equipment, authorisations, complaints, corrective actions, audits, reviews, portal logins — plus the demo inspectors, clients/vendors, <?= e(Tlp("boss")) ?> and demo logins, and switches the client portal back off. The three demo offices are left in place (delete them under Masters if you want).</p>
  <?php else: ?>
    <p class="sub" style="margin-bottom:10px">One-click load of a complete example across <strong>every module</strong> — offices, users of every role, <?= e(Tlp('engineer')) ?>, clients, <?= e(Tlp("boss")) ?>, <?= e(Tlp('call')) ?>, <?= e(Tlp('job')) ?>, vouchers, invoicing &amp; credit, quotations, <?= e(Tlp('report')) ?> with evidence, equipment and calibration, authorisations, impartiality, complaints and appeals, corrective actions, internal audit, management review and the client portal. Roughly half the records are deliberately awkward — an expired calibration, a lapsed authorisation, a complaint nobody acknowledged in time, a corrective action that did not work — because a register of tidy rows cannot show you whether a rule is working. See <code>docs/DEMO-TEST-PACK.md</code> for what each one should do on screen.</p>
    <form method="post" action="/seed-demo" onsubmit="return confirm('Load the demo/sample dataset now? This adds example records across the whole app.')">
      <button class="btn" type="submit">Load demo data</button>
    </form>
    <p class="muted" style="margin-top:8px">Creates demo logins (director, sbuhead, bmanager, appmanager, opmanager, asstmgr, coord.amd, coord.pun, account, insp.ravi, insp.anil) — all password <code>demo12345</code>. Use a fresh/test install, not a database that already holds real data.</p>
    <p class="muted" style="margin-top:6px;font-size:12px">It writes a few thousand rows. If this server stops the page before it finishes, load it from the command line instead — <code>php tools/seed-demo.php</code> — where there is no time limit.</p>
  <?php endif; ?>

  <?php
  // What the demo actually reaches, checked against the database rather than
  // remembered. This module once fell two years behind — the seed filled
  // Operations and every register added afterwards loaded empty — and nothing
  // said so. Now it does, on the screen where somebody would notice.
  $cov = function_exists('demo_coverage') ? demo_coverage() : [];
  $gaps = array_filter($cov, fn($c) => $c['state'] !== 'ok');
  $seedFail = [];
  if (function_exists('setting_get')) { $sf = setting_get('demo_seed_last_fail', ''); if ($sf) { $seedFail = json_decode((string)$sf, true) ?: []; } }
  ?>
  <?php if ($gaps && $seedFail): ?>
  <div class="msg msg-error" style="margin-top:14px;font-size:12.5px;line-height:1.55">
    <b>Why some registers loaded empty</b> — the last “Load demo data” reported these errors. Send this text and it can be fixed precisely:
    <ul style="margin:6px 0 0;padding-left:18px">
      <?php foreach (array_slice($seedFail, 0, 12) as $f): ?><li style="font-family:ui-monospace,monospace;font-size:11.5px"><?= e($f) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <?php if ($cov): ?>
  <details style="margin-top:14px">
    <summary style="cursor:pointer;font-size:13px" class="muted">
      Module coverage —
      <?= $gaps
        ? '<span class="pill p-warn">' . count($gaps) . ' of ' . count($cov) . ' with no demo data</span>'
        : '<span class="pill p-ok">all ' . count($cov) . ' modules covered</span>' ?>
    </summary>
    <div style="margin-top:10px">
      <?php if ($gaps): ?>
        <p class="muted" style="font-size:12.5px;line-height:1.6;margin:0 0 8px">
          These registers hold nothing, so a demonstration shows them empty. If a module was added recently,
          its rows belong in <code>demo_seed_modules()</code> in <code>lib/seed_demo.php</code>.
        </p>
      <?php endif; ?>
      <div style="display:grid;gap:2px 14px;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));font-size:12.5px">
        <?php foreach ($cov as $label => $c): ?>
          <div style="display:flex;justify-content:space-between;gap:8px">
            <span><?= $c['state'] === 'ok' ? '✓' : '·' ?> <?= e($label) ?></span>
            <span class="muted"><?= $c['state'] === 'missing' ? 'not installed' : (int)$c['rows'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </details>
  <?php endif; ?>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Traceability check</h3>
  <p class="sub" style="margin-bottom:8px">Builds <strong>one</strong> record and follows it the whole way through — customer, lead, contact, deal, quotation, accept, contract number, work-order, job, site check-in, report, invoice, money-in — then reads the database back and shows you, place by place, that every link was saved and every figure is right.</p>
  <p class="muted" style="margin:0 0 10px">Safe to run on a live system: everything it writes is one demo customer (<code><?= e(defined('TRACE_CLIENT_CODE') ? TRACE_CLIENT_CODE : 'GT-CLIENT') ?></code>) and can be removed again in one click on the results page. Use it to prove the flow end-to-end, or after any change to check nothing broke.</p>
  <form method="post" action="/trace-thread">
    <button class="btn" type="submit">Build &amp; check the thread</button>
  </form>
  <p class="muted" style="margin-top:8px;font-size:12px">Command line, no time limit: <code>php tools/trace-thread.php</code> — add <code>--remove</code> to take it out, <code>--check</code> to re-verify.</p>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Audit &amp; compliance check</h3>
  <p class="sub" style="margin-bottom:8px">The inspection-pack twin of the check above. Builds one compliance chain — <strong>internal audit → finding → corrective action</strong> (with owner &amp; due date, verified and closed), a nonconformity and a complaint that each raise their own corrective action, and a management review — then <strong>fires the accreditation gates on purpose</strong> (a lapsed certificate, an out-of-calibration instrument, an open impartiality threat, the pack's own assign-hook) and confirms each one blocks.</p>
  <p class="muted" style="margin:0 0 10px">Safe on a live system; removable in one click on the results page. Proves the inspection &amp; audit pack holds together end to end.</p>
  <form method="post" action="/trace-audit">
    <button class="btn" type="submit">Build &amp; check the audit thread</button>
  </form>
  <p class="muted" style="margin-top:8px;font-size:12px">Command line: <code>php tools/trace-audit.php</code> — add <code>--remove</code> to take it out.</p>
</div>

<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Clear records</h3>
  <p class="sub" style="margin-bottom:10px">For setting up and testing: empty whole groups of records — day-to-day work, reports, costing figures, <?= e(Tlp('client')) ?> &amp; <?= e(Tlp('vendor')) ?>, people, master lists — and start again with a clean register. You see the count before anything happens, and your own login is never deleted.</p>
  <a class="btn danger" href="/reset-data">Open clear records</a>
</div>

<?php if (function_exists('can_manage_tenants') && can_manage_tenants()): ?>
<div class="panel settings-card">
  <h3 class="tab-sub" style="margin-top:0;">Cloud workspaces</h3>
  <p class="sub" style="margin-bottom:10px">Run one copy of the app as many separate businesses — each on its own subdomain
    and its own database, isolated from the rest. <?= saas_enabled()
      ? 'Cloud mode is <strong>on</strong>.' : 'Turn it on and add workspaces here.' ?></p>
  <a class="btn" href="/tenants">Manage cloud workspaces</a>
</div>
<?php endif; ?>
</div><?php // .settings-cards ?>
<?php endif; ?>

<style>
  /* Settings uses the full working width instead of a narrow left column.
     The two big forms span the page and let their fields flow into as many
     columns as fit; the small admin panels tile as cards. */
  .settings-form{max-width:none}
  .settings-form .form-grid{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
  /* Free-text blocks (terms, privacy notice, live preview) read badly at full
     width — hold them to a comfortable measure and let them span the row. */
  .settings-form textarea.form-control{max-width:920px}
  .settings-form .ff-wide{max-width:none}
  .settings-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
    gap:16px;margin-top:18px;align-items:start}
  .settings-cards .settings-card{margin:0}
  @media(max-width:720px){
    .settings-form .form-grid{grid-template-columns:1fr}
    .settings-cards{grid-template-columns:1fr}
  }
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
