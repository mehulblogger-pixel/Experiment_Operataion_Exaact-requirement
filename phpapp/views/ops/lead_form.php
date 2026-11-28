<?php
// New lead — the full form (owner, 21 Nov 2026: Sales redesign Stage 2, approved
// design s2-leadform). The same boxes, rules and save as before, in five short
// numbered steps, plus: the business unit (DEF-15) and the quick lead's duplicate
// check while typing and on save (backend item 1). Saved by /lead-new in
// lib/leads.php. The old page is kept in tests/fixtures/pre_stage2/lead_form.php.
//
// Prefill order: what was typed and bounced back beats what arrived in the URL.
// Somebody whose session timed out mid-form gets their twenty fields returned,
// not an empty screen and an apology.
require_once __DIR__ . '/_sxw_ui.php';
$p = $prefill ?? [];
$val = function ($k, $d = '') use ($p) { return form_old($k, $p[$k] ?? $d); };
$me  = current_user();
$dupes = $dupes ?? [];
$sbus = $sbus ?? [];
$step = function ($n, $title, $sub = '', $open = true) {
    echo '<details class="sxl-step" data-step="' . (int)$n . '"' . ($open ? ' open' : '') . '><summary><i>' . (int)$n . '</i><span class="x"><b>' . e($title) . '</b>'
       . ($sub !== '' ? '<small>' . e($sub) . '</small>' : '') . '</span>' . rec_icon('down', 's') . '</summary><div class="sxl-sb">';
};
$stepEnd = function () { echo '</div></details>'; };
$right = '<div class="sxw-ctl"><a class="sxw-bt" href="/sales-lead">' . rec_icon('bolt', 's') . ' Quick lead</a></div>';
?>
<div class="rec sxw has-bn sxl sxl-form">
  <?php sxw_nav('/leads'); ?>
  <?php sxw_head('New lead', 'Somebody worth chasing. Five short steps; only the company or person’s name is needed. If they become a ' . Tl('client') . ', everything here comes across — you will not type it again.', 'plus', $right); ?>

<?php if ($dup): ?>
  <div class="sxl-ban warn" role="status"><span class="ic"><?= rec_icon('alert') ?></span>
    <div class="tx"><b class="t">We may already know them.</b>
    <?php if ($dup['kind'] === 'partner'): ?>
      <a href="/client?id=<?= (int)$dup['row']['id'] ?>"><?= e($dup['row']['display_name'] ?? $dup['row']['legal_name']) ?></a>
      is already on the <?= e(Tl('client')) ?> master — matched on <?= e($dup['by']) ?>.
      Chasing them as a new lead builds a second relationship with the same company.
    <?php else: ?>
      There is already an open lead <a href="/lead?id=<?= (int)$dup['row']['id'] ?>"><?= e($dup['row']['ref']) ?></a>
      for <?= e($dup['row']['company_name']) ?>.
    <?php endif; ?></div>
  </div>
<?php endif; ?>

<?php if (form_is_retry()): ?>
  <div class="msg msg-warning" style="margin:0 0 12px">Nothing was lost — everything you typed is below. Press <b>Create it</b> again.</div>
<?php endif; ?>

<div class="sxl-fg">
<form method="post" action="/lead-new" enctype="multipart/form-data" id="lf-form" class="sxl-main">
  <?php $step(1, 'Who they are', 'The minimum that makes a lead chaseable.'); ?>
  <div class="form-grid">
    <?php // Company from the master, or a person's name typed in. Picking a company on
          // the master links the lead straight to that customer, so it is never re-typed
          // or mis-spelled — the same client picker the quotation screen uses. ?>
    <div class="ff ff-wide ff-req"><label for="lead-partner">Company or person <a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a></label>
      <div class="pair-2">
        <select class="form-control searchable" name="partner_id" id="lead-partner" data-cust-chip>
          <option value="">— pick a company on the master —</option>
          <?php foreach (($clients ?? []) as $cl): ?>
            <option value="<?= (int)$cl['id'] ?>" <?= $val('partner_id') === (string)$cl['id'] ? 'selected' : '' ?>>
              <?= e($cl['display_name'] ?: $cl['legal_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control" name="company_name" id="lead-company" maxlength="200"
               value="<?= e($val('company_name')) ?>" placeholder="…or type a new company / person's name"
               data-cust-match="#lead-partner" autocomplete="off">
      </div>
      <?php $pickList = $picks ?? []; include __DIR__ . '/_customer_picks.php'; ?>
      <div class="ff-help">Already a <?= e(Tl('client')) ?>? Pick them on the left — their main contact fills in. Someone new — a company or a person — type the name on the right.</div>
    </div>
    <div class="ff"><label for="lf-cn">Contact name</label>
      <input class="form-control" id="lf-cn" name="contact_name" maxlength="150" value="<?= e($val('contact_name')) ?>"></div>
    <div class="ff"><label for="lf-em">E-mail</label>
      <input class="form-control" id="lf-em" type="email" inputmode="email" name="contact_email" maxlength="200"
             value="<?= e($val('contact_email')) ?>"></div>
    <div class="ff"><label for="lf-ph">Mobile</label>
      <input class="form-control" id="lf-ph" type="tel" inputmode="tel" name="contact_phone" maxlength="60"
             value="<?= e($val('contact_phone')) ?>"></div>
    <div class="ff"><label for="lf-src">Where they came from</label>
      <select class="form-control" id="lf-src" name="source"><option value="">—</option>
        <?php foreach ($sources as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $val('source') === (string)$k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <?php //  Possible duplicates — while typing (filled in by the page) and after a save that found some. ?>
  <div class="sxl-ban warn" id="lf-dupes" role="status"<?= $dupes ? '' : ' hidden' ?>>
    <span class="ic"><?= rec_icon('alert') ?></span>
    <div class="tx"><b class="t">This looks like one we already have</b>
      <ul class="sxl-dl"><?php foreach ($dupes as $dp): ?><li><?= $dp['url'] !== '' ? '<a href="' . e($dp['url']) . '" target="_blank" rel="noopener">' . e($dp['text']) . '</a>' : e($dp['text']) ?></li><?php endforeach; ?></ul>
      <?php // Owner, 22 Nov 2026 (findings J2-04, 7g): the same "Save anyway" button as the quick
            // lead, in place of a tick box + Create it. It sends the same confirm=1 the box did. ?>
      <span class="sxl-hint">Open one of them instead — or, if this really is new, press <b>Save anyway</b>.</span>
      <div style="margin-top:8px"><button class="btn secondary" type="submit" name="confirm" value="1">Save anyway</button></div></div>
  </div>
  <?php $stepEnd(); ?>

  <?php $step(2, 'What they want'); ?>
  <div class="form-grid">
    <div class="ff ff-wide"><label for="lf-req">The requirement</label>
      <textarea class="form-control" id="lf-req" name="requirement" rows="3"
        placeholder="Scope, quantities, the standard — anything that decides whether this is worth chasing"><?= e($val('requirement')) ?></textarea></div>
    <div class="ff"><label for="lf-val">Value they are worth <span class="muted">(estimate, <?= e(cur_sym()) ?>)</span></label>
      <input class="form-control" id="lf-val" type="number" inputmode="decimal" step="0.01" min="0" name="value"
             value="<?= e($val('value')) ?>"></div>
    <div class="ff"><label for="lf-cl">Expected to close</label>
      <input class="form-control" id="lf-cl" type="date" name="expected_close" value="<?= e($val('expected_close')) ?>"></div>
    <?php // DEF-15 (owner, 21 Nov 2026): the business unit can be set on the lead; the
          // choices are the units this person's data scope covers. ?>
    <div class="ff"><label for="lf-sbu"><?= e(ucfirst(Tl('sbu'))) ?></label>
      <select class="form-control" id="lf-sbu" name="sbu"><option value="">— not decided yet —</option>
        <?php foreach ($sbus as $sk => $sv): ?><option value="<?= e($sk) ?>"<?= $val('sbu') === (string)$sk ? ' selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
      </select><div class="ff-help">The <?= e(Tl('inquiry')) ?> needs it when they become a <?= e(Tl('client')) ?>; setting it now saves asking later.</div></div>
  </div>
  <?php $stepEnd(); ?>

  <?php // Field #7 — manpower-deputation intake. Man-day needs no site; man-month places
        //   people on the customer's site, so the site details become required (here and
        //   on the server in lead_create / lead-edit). ?>
  <?php $step(3, 'Manpower deputation', 'Only if this lead is for deputing people.'); ?>
  <div class="form-grid">
    <div class="ff"><label for="dep-kind">Type of deputation</label>
      <select class="form-control" name="deputation_kind" id="dep-kind">
        <option value="">— not a manpower deputation —</option>
        <?php foreach (LEAD_DEPUTATION as $k => $lbl): ?>
          <option value="<?= e($k) ?>" <?= (string)$val('deputation_kind') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label for="lf-mc">How many people</label>
      <input class="form-control" id="lf-mc" type="number" inputmode="numeric" step="1" min="0" name="manpower_count"
             value="<?= e($val('manpower_count') ?: '') ?>" placeholder="e.g. 3"></div>
    <div class="ff ff-wide"><label for="lf-sk">Skills / qualifications needed</label>
      <textarea class="form-control" id="lf-sk" name="manpower_skills" rows="2"
        placeholder="Disciplines, certifications, experience — e.g. 2× NDT Level II (UT/RT), 1× welding inspector CSWIP"><?= e($val('manpower_skills')) ?></textarea></div>
    <div class="ff ff-wide" id="dep-site" style="display:none">
      <label for="dep-site-input">Site details <span class="muted">(where the people will be placed)</span> <span id="dep-site-req" class="msg-error" style="display:none">— required for a man-month deputation</span></label>
      <input class="form-control" name="site_location" id="dep-site-input" maxlength="300"
             value="<?= e($val('site_location')) ?>" placeholder="Plant / location, city — where the deputation is based"></div>
  </div>
  <?php $stepEnd(); ?>

  <?php $step(4, 'Who chases it, and what happens next', 'A lead nobody owns is a lead nobody rings.'); ?>
  <div class="form-grid">
    <?php // Allocating at creation — whoever takes the enquiry is very often not the person who will chase it. ?>
    <div class="ff"><label for="lf-own">Allocated to</label>
      <select class="form-control searchable" id="lf-own" name="owner_user_id">
        <?php $sel = $val('owner_user_id', (string)($me['id'] ?? '')); ?>
        <?php foreach (($users ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (string)$u['id'] === (string)$sel ? 'selected' : '' ?>>
            <?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) ?><?php
              if ((int)$u['id'] === (int)($me['id'] ?? 0)) echo ' — me'; ?></option>
        <?php endforeach; ?>
      </select>
      <div class="ff-help">Defaults to you. Change it if somebody else will chase this one.</div></div>

    <div class="ff"><label for="lf-off">Branch</label>
      <?php // Owner, 22 Nov 2026 (findings LF-03, 7d): "— mine —" said nothing. Blank means
            // the person's home branch (leads_create), so the option names it. ?>
      <?php $myOff = (int)(($me ?? (function_exists('current_user') ? current_user() : []))['home_office_id'] ?? 0); $myOffName = '';
            foreach ($offices as $o) if ((int)$o['id'] === $myOff) { $myOffName = (string)$o['name']; break; }
            if ($myOffName === '' && $myOff) $myOffName = (string)(ops_val("SELECT name FROM offices WHERE id=?", [$myOff]) ?: ''); ?>
      <select class="form-control" id="lf-off" name="office_id"><option value=""><?= e($myOffName !== '' ? 'My branch (' . $myOffName . ')' : 'My home branch') ?></option>
        <?php foreach ($offices as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= $val('office_id') === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select><div class="ff-help">Only the branches you work for.</div></div>

    <div class="ff"><label for="lf-pipe">Pipeline</label>
      <select class="form-control" id="lf-pipe" name="pipeline_id">
        <?php foreach ($pipelines as $pl): ?>
          <option value="<?= (int)$pl['id'] ?>" <?= $val('pipeline_id') === (string)$pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <?php // Pick from the list or type your own — a datalist is both countable and open. ?>
    <div class="ff"><label for="lf-na">Next thing to do</label>
      <input class="form-control" id="lf-na" name="next_action" maxlength="255" list="next-actions"
             value="<?= e($val('next_action')) ?>" placeholder="Pick one, or type your own">
      <datalist id="next-actions">
        <?php foreach (($nextActions ?: [
              'Send the capability statement','Call to qualify','Arrange a meeting','Send a quotation',
              'Ask for the specification','Site visit','Follow up on the quotation','Send credentials and approvals',
          ]) as $k => $v): ?>
          <option value="<?= e(is_string($k) && !is_int($k) ? $v : $v) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <div class="ff-help">Kept as a list under <a href="/masters" target="_blank">Masters</a>, so the same words are
        used by everybody and the follow-up report adds up.</div></div>

    <div class="ff"><label for="lf-non">By when</label>
      <input class="form-control" id="lf-non" type="date" name="next_action_on" value="<?= e($val('next_action_on')) ?>"></div>
  </div>
  <?php $stepEnd(); ?>

  <?php $step(5, 'Attach files and extra fields', 'Optional — up to ' . (int) round(LEAD_FILE_MAX / 1048576) . ' MB a file.'); ?>
  <p class="sxl-hint" style="margin:0 0 8px">The customer's enquiry, specifications, drawings or e-mails. They are kept under the lead's Documents and carried onto its quotation.</p>
  <div class="form-grid">
    <div class="ff ff-wide"><label for="lf-new-files">File(s)</label>
      <input id="lf-new-files" type="file" name="files[]" multiple data-max-bytes="<?= (int) LEAD_FILE_MAX ?>"></div>
    <div class="ff"><label for="lf-new-kind">What is it?</label>
      <select id="lf-new-kind" class="form-control" name="file_kind"><?php foreach (LEAD_FILE_KINDS as $fk => $fl): ?><option value="<?= e($fk) ?>"<?= $fk === 'REQUIREMENT' ? ' selected' : '' ?>><?= e($fl) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label for="lf-new-note">Note <span class="muted">(optional)</span></label>
      <input id="lf-new-note" class="form-control" name="file_note" maxlength="255" placeholder="e.g. Rev 2"></div>
  </div>
  <?php // Form Designer — any field this company added, with its label / order / hide / required overrides.
        if (function_exists('fd_extra_fields')) echo fd_extra_fields('lead', 0); ?>
  <?php $stepEnd(); ?>

  <div class="sxl-act">
    <span class="sp">A lead needs a mobile or an e-mail — one nobody can reach cannot be chased.</span>
    <a class="btn secondary" href="/leads">Cancel</a>
    <button class="btn" type="submit">Create it</button>
  </div>
</form>

<aside class="sxl-side">
  <?php rec_card_open('Where this lead goes', 'flow'); ?>
    <ol class="sxl-path"><li class="on">The first stage of its pipeline</li><li>Then through the stages as it is worked</li><li>Won — it becomes a <?= e(Tl('client')) ?> and an <?= e(Tl('inquiry')) ?> is raised</li><li>or Not pursued, with a reason</li></ol>
  <?php rec_card_close(); ?>
  <?php rec_card_open('After you press Create it', 'check'); ?>
    <ul class="sxl-ul"><li>It gets its number, like <b>L-<?= date('Y') ?>-0001</b></li><li>The lead page opens</li><li>Files go to its Documents</li><li>It shows on <b>Today</b> for the person chasing it</li></ul>
  <?php rec_card_close(); ?>
  <?php rec_card_open('Quick or full?', 'bolt'); ?>
    <p class="sxl-hint" style="margin:0"><a href="/sales-lead">Quick lead</a> is for the phone call in front of you: name, mobile, when to call. This form adds e-mail, deputation, pipeline, files and your own fields.</p>
  <?php rec_card_close(); ?>
</aside>
</div>
</div>
<?php sxw_bottom('/leads'); ?>

<script>
(function () {
  // The site box: shown and required only for a man-month deputation.
  var sel = document.getElementById('dep-kind'), site = document.getElementById('dep-site'),
      inp = document.getElementById('dep-site-input'), req = document.getElementById('dep-site-req');
  if (sel && site) {
    var sync = function () {
      var mm = sel.value === 'MANMONTH';
      site.style.display = mm ? '' : 'none';
      if (req) req.style.display = mm ? '' : 'none';
      if (inp) inp.required = mm;
    };
    sel.addEventListener('change', sync); sync();
  }
  var form = document.getElementById('lf-form');
  // On a phone the later steps start folded, unless something is already in them.
  if (form && window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
    form.querySelectorAll('details.sxl-step').forEach(function (d) {
      if (+d.getAttribute('data-step') < 3) return;
      var filled = Array.prototype.some.call(d.querySelectorAll('input:not([type=hidden]):not([type=file]),textarea,select'), function (el) {
        return el.id !== 'lf-new-kind' && el.name !== 'pipeline_id' && el.name !== 'owner_user_id' && String(el.value || '').trim() !== '';
      });
      if (!filled) d.open = false;
    });
  }
  // A box the browser refuses is opened, so it can be seen and fixed.
  if (form) form.addEventListener('invalid', function (ev) { var d = ev.target.closest('details'); if (d) d.open = true; }, true);
})();

// Company from the master, OR a typed name — the two are one decision, so they
// cannot both be filled. Picking a customer on the master takes over the name
// box (its own name is what gets saved); clearing the picker hands the box back.
(function () {
  var sel  = document.getElementById('lead-partner');
  var name = document.getElementById('lead-company');
  if (!sel || !name) return;
  function sync() {
    var picked = sel.value !== '';
    if (picked) {
      var opt = sel.options[sel.selectedIndex];
      name.value = opt ? opt.textContent.trim() : name.value;
      name.readOnly = true;
      name.classList.add('readonly-field');
      name.removeAttribute('required');
      name.placeholder = 'Using the customer picked on the left';
    } else {
      if (name.readOnly) name.value = '';   // only clear what WE filled
      name.readOnly = false;
      name.classList.remove('readonly-field');
      name.setAttribute('required', 'required');
      name.placeholder = '…or type a new company / person’s name';
    }
  }
  sel.addEventListener('change', sync);
  sync();   // honour a bounced-back partner_id on a retry

  // Picking a customer also flows their primary contact in from the client
  // master — the same person entered under the Directory, not re-typed here.
  var cf={name:document.querySelector('[name="contact_name"]'),
          email:document.querySelector('[name="contact_email"]'),
          phone:document.querySelector('[name="contact_phone"]')};
  Object.keys(cf).forEach(function(k){ if(cf[k]) cf[k].addEventListener('input',function(){ cf[k].dataset.af=''; }); });
  function put(el,v){ if(!el) return; v=v||''; if((el.value||'')!=='' && el.dataset.af!=='1') return; el.value=v; el.dataset.af=v!==''?'1':''; }
  function pull(){ var id=sel.value; if(!id) return;
    fetch('/partner-contact?id='+encodeURIComponent(id),{headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json();})
      .then(function(d){ var c=(d&&d.contact)||{}; put(cf.name,c.name); put(cf.email,c.email); put(cf.phone,c.mobile); check(); })
      .catch(function(){});
  }
  sel.addEventListener('change', pull);

  // While typing: the quick lead's duplicate check (same name, same mobile on an
  // open lead, a contact with that mobile), asked of /lead-new itself.
  var box = document.getElementById('lf-dupes'), list = box ? box.querySelector('.sxl-dl') : null, t = null, last = '';
  function check() {
    if (!box || !list || !window.fetch) return;
    var q = 'dupcheck=1&company_name=' + encodeURIComponent(name.readOnly ? '' : (name.value || '').trim())
          + '&contact_phone=' + encodeURIComponent(((cf.phone && cf.phone.value) || '').trim())
          + '&partner_id=' + encodeURIComponent(sel.value || '');
    if (q === last) return; last = q;
    if ((name.value || '').trim().length < 3 && !sel.value && ((cf.phone && cf.phone.value) || '').replace(/\D/g, '').length < 10) { return; }
    fetch('/lead-new?' + q, {headers: {'X-Requested-With': 'fetch'}, credentials: 'same-origin'})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var rows = (d && d.dupes) || [];
        list.innerHTML = '';
        rows.forEach(function (x) {
          var li = document.createElement('li');
          if (x.url) { var a = document.createElement('a'); a.href = x.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = x.text; li.appendChild(a); }
          else li.textContent = x.text;
          list.appendChild(li);
        });
        box.hidden = rows.length === 0;
      }).catch(function () {});
  }
  function later() { clearTimeout(t); t = setTimeout(check, 450); }
  name.addEventListener('input', later); sel.addEventListener('change', later);
  if (cf.phone) cf.phone.addEventListener('input', later);
})();
</script>
