<?php
// Careers admin — enable the public page, write the intro, choose which
// openings are advertised. Data: $enabled,$intro,$reqs,$live.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$reqs = $reqs ?? []; $enabled = $enabled ?? false; $intro = $intro ?? ''; $live = (int)($live ?? 0);
$title = function ($r) use ($e) { return $e(trim((string)($r['designation'] ?? '')) ?: ('Opening ' . ($r['req_code'] ?? ''))); };
?>
<div class="crumbs"><a href="/">Home</a> › Careers page</div>
<div class="master-head">
  <div><h1>Careers page</h1>
    <p class="sub" style="margin:2px 0 0">Publish selected open requirements to a public page where candidates apply directly. Every application creates a candidate on the recruiter's desk automatically.</p></div>
  <div class="row-actions">
    <a class="btn secondary" href="/careers" target="_blank" rel="noopener">Open public page ↗</a>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub">Page settings</h3>
  <form method="post">
    <input type="hidden" name="do" value="settings">
    <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
      <input type="checkbox" name="careers_enabled" value="1" <?= $enabled?'checked':'' ?> style="width:auto">
      Careers page is live
      <?php if ($enabled): ?><span class="pill p-ok" style="font-size:11px"><?= $live ?> opening<?= $live===1?'':'s' ?> visible</span>
      <?php else: ?><span class="pill p-mut" style="font-size:11px">off — visitors are sent to staff login</span><?php endif; ?>
    </label>
    <div class="ff" style="margin-top:12px"><label style="font-weight:600">Intro shown at the top of the page</label>
      <textarea class="form-control" name="careers_intro" rows="3" placeholder="e.g. We're growing — join a team that values craft and ownership."><?= $e($intro) ?></textarea></div>
    <div style="margin-top:10px;display:flex;gap:10px;align-items:center">
      <button class="btn">Save settings</button>
      <span class="muted" style="font-size:12.5px">Public address: <code>/careers</code></span>
    </div>
  </form>
</div>

<?php if (!empty($jdcfg)): ?>
<div class="panel">
  <h3 class="tab-sub">Posting defaults <span class="muted" style="font-weight:400;font-size:12px">— the boilerplate every generated posting inherits</span></h3>
  <form method="post">
    <input type="hidden" name="do" value="jd_settings">
    <div class="ff"><label style="font-size:12px;font-weight:600">Intro / about us <span class="muted" style="font-weight:400">— tokens: {company} {role} {department} {location}</span></label>
      <textarea class="form-control" name="about" rows="2"><?= $e($jdcfg['about'] ?? '') ?></textarea></div>
    <div class="ff" style="margin-top:10px"><label style="font-size:12px;font-weight:600">What we offer</label>
      <textarea class="form-control" name="offer" rows="2"><?= $e($jdcfg['offer'] ?? '') ?></textarea></div>
    <div class="ff" style="margin-top:10px"><label style="font-size:12px;font-weight:600">How to apply (footer)</label>
      <textarea class="form-control" name="apply" rows="2"><?= $e($jdcfg['apply'] ?? '') ?></textarea></div>
    <div class="ff" style="margin-top:10px;max-width:240px"><label style="font-size:12px;font-weight:600">Tone</label>
      <select class="form-control" name="tone">
        <?php foreach (['professional'=>'Professional & warm','friendly'=>'Friendly & conversational','concise'=>'Concise & direct'] as $tk=>$tl): ?>
          <option value="<?= $tk ?>" <?= ($jdcfg['tone'] ?? '')===$tk?'selected':'' ?>><?= $e($tl) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
      <button class="btn">Save posting defaults</button>
      <span class="muted" style="font-size:12px"><?= !empty($ai_on) ? 'AI is on — postings are written in fluent prose.' : 'AI is off — postings are assembled from a clear template (turn on AI under Settings → AI providers for richer copy).' ?></span>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">Openings</h3>
  <p class="muted" style="font-size:12.5px;margin:0 0 12px">Tick “Advertise” and add a short public description for each role you want on the careers page. Roles that are closed, filled or on hold never appear.</p>
  <?php if (!$reqs): ?>
    <p class="muted">No open requirements to advertise yet. Create a requirement first.</p>
  <?php else: foreach ($reqs as $r): $pub = (int)($r['careers_published'] ?? 0) === 1; ?>
    <form method="post" style="border:1px solid var(--line,#e5e9f0);border-radius:12px;padding:14px 15px;margin-bottom:12px;<?= $pub?'border-left:4px solid var(--brand,#1e40af)':'' ?>">
      <input type="hidden" name="do" value="publish">
      <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
        <div>
          <div style="font-weight:700;font-size:15px"><?= $title($r) ?>
            <?php if ($pub): ?><span class="pill p-ok" style="font-size:10.5px">Live</span><?php endif; ?></div>
          <div class="muted" style="font-size:12px">
            <?= $e($r['req_code'] ?? '') ?>
            <?= $r['department'] ? ' · ' . $e($r['department']) : '' ?>
            <?= $r['grade'] ? ' · ' . $e($r['grade']) : '' ?>
            <?= ($r['project_site'] ?? '') ? ' · 📍 ' . $e($r['project_site']) : '' ?>
            · Status <?= $e($r['status'] ?? '') ?>
          </div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13.5px;white-space:nowrap;cursor:pointer">
          <input type="checkbox" name="publish" value="1" <?= $pub?'checked':'' ?> style="width:auto"> Advertise
        </label>
      </div>
      <div class="ff" style="margin-top:10px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
          <label style="font-size:12px;font-weight:600;margin:0">Public description (what applicants see)</label>
          <span style="display:flex;gap:8px;align-items:center">
            <span class="jd-msg muted" style="font-size:11.5px"></span>
            <button type="button" class="btn secondary jd-gen" data-req="<?= (int)$r['id'] ?>" data-target="sum_<?= (int)$r['id'] ?>" style="padding:5px 12px;font-size:12.5px">✨ Generate</button>
          </span>
        </div>
        <textarea class="form-control" id="sum_<?= (int)$r['id'] ?>" name="careers_summary" rows="4" placeholder="Describe the role, key responsibilities and what you're looking for — or click Generate."><?= $e($r['careers_summary'] ?? '') ?></textarea></div>
      <div style="margin-top:8px"><button class="btn secondary" style="padding:6px 14px;font-size:13px">Save</button></div>
    </form>
  <?php endforeach; endif; ?>
</div>
<script>
(function(){
  var CSRF = '<?= function_exists('csrf_token') ? e(csrf_token()) : '' ?>';
  document.querySelectorAll('.jd-gen').forEach(function(btn){
    btn.addEventListener('click', function(){
      var wrap = btn.closest('.ff'), msg = wrap.querySelector('.jd-msg'),
          ta = document.getElementById(btn.dataset.target);
      btn.disabled = true; msg.textContent = 'Writing…';
      var fd = new FormData(); fd.append('_csrf', CSRF); fd.append('req_id', btn.dataset.req);
      fetch('/jd-generate', {method:'POST', body:fd, headers:{'X-Requested-With':'fetch'}})
        .then(function(r){ return r.json(); })
        .then(function(d){
          btn.disabled = false;
          if(!d || !d.ok){ msg.textContent = (d && d.error) || 'Could not generate — write it manually.'; return; }
          ta.value = d.text || ''; ta.dispatchEvent(new Event('input'));
          var src = d.source === 'ai' ? 'AI' : 'template';
          var miss = (d.missing && d.missing.length) ? ' · add ' + d.missing.join(', ') + ' for a fuller posting' : '';
          msg.innerHTML = '<b style="color:var(--brand,#1e40af)">Drafted (' + src + ')</b> — review &amp; Save' + miss;
        })
        .catch(function(){ btn.disabled = false; msg.textContent = 'Network error — try again.'; });
    });
  });
})();
</script>
<style>.ff label{display:block;margin-bottom:4px}</style>
