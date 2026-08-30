<?php
  // Connect K0+ — the professional's own privacy states + a live client-preview.
  $me = $me ?? []; $settings = $settings ?? []; $preview = $preview ?? []; $labels = $labels ?? [];
  $cur = ['contact' => $settings['contact'] ?? 'on_request', 'rate' => $settings['rate'] ?? 'band', 'identity' => $settings['identity'] ?? 'full'];
  $listed = !array_key_exists('listed', $settings) || (int)$settings['listed'] === 1;
?>
<style>
  .pv-card{border:1px solid var(--line,#e3ebea);border-radius:12px;padding:14px 15px;margin-bottom:14px;background:var(--card,#fff)}
  .pv-opt{display:block;border:1px solid var(--line,#e3ebea);border-radius:11px;padding:10px 12px;margin-bottom:8px;cursor:pointer;transition:border-color .12s,background .12s}
  .pv-opt:hover{border-color:#9fc7c0}
  .pv-opt.sel{border-color:#0f7d5a;background:#f1faf6}
  .pv-opt input{margin-right:8px}
  .pv-opt .t{font-weight:600;font-size:14.5px}
  .pv-opt .d{color:var(--muted,#667);font-size:12.5px;margin-top:2px;margin-left:22px}
  .pv-preview{background:#0f2b28;color:#e8f3f0;border-radius:12px;padding:14px 16px}
  .pv-preview .nm{font-size:18px;font-weight:700}
  .pv-preview .rowk{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.10);font-size:13.5px}
  .pv-preview .rowk:last-child{border-bottom:0}
  .pv-preview .lock{color:#9fe3cf}
  .grp-h{font-weight:700;margin:0 0 3px;font-size:15px}
  .grp-s{color:var(--muted,#667);font-size:12.5px;margin:0 0 9px}
</style>

<p style="margin:0 0 8px"><a href="/pro/profile" class="muted" style="font-size:14px">← My passport</a></p>
<h1>Privacy &amp; who sees what</h1>
<p class="muted" style="margin:0 0 14px">You decide what a client sees <strong>before</strong> a job. Your skills and experience are always discoverable — your phone, e-mail, exact rate and full name follow the rules you set here.</p>

<?php // ---- Incoming contact requests from clients (approve / decline) ---- ?>
<?php $requests = $requests ?? []; if ($requests): ?>
<div class="pv-card" style="border-color:#0f7d5a">
  <div class="grp-h">📨 Contact requests <span class="cpill warn" style="margin-left:4px"><?= count($requests) ?></span></div>
  <div class="grp-s">A client wants your phone and e-mail. You decide — nothing is shared until you approve.</div>
  <?php foreach ($requests as $rq): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;border:1px solid var(--line,#e3ebea);border-radius:11px;padding:10px 12px;margin-bottom:8px">
      <div><strong><?= e($rq['client_name'] ?: 'A client') ?></strong>
        <div class="muted" style="font-size:12px">Requested <?= e(substr((string)$rq['requested_at'],0,10)) ?></div>
      </div>
      <div style="display:flex;gap:6px">
        <form method="post" action="/pro/privacy" style="margin:0"><input type="hidden" name="action" value="reveal_approve"><input type="hidden" name="client_party_id" value="<?= (int)$rq['client_party_id'] ?>"><button class="btn" type="submit" style="padding:5px 12px;font-size:12.5px">Approve</button></form>
        <form method="post" action="/pro/privacy" style="margin:0"><input type="hidden" name="action" value="reveal_decline"><input type="hidden" name="client_party_id" value="<?= (int)$rq['client_party_id'] ?>"><button class="btn sec" type="submit" style="padding:5px 12px;font-size:12.5px">Decline</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php // ---- Live preview of a client's search card ---- ?>
<div class="pv-preview" id="pvPreview">
  <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#9fe3cf;margin-bottom:6px">What a new client sees</div>
  <div class="nm" data-pv="name"><?= e($preview['display_name'] ?? ($me['name'] ?? 'You')) ?></div>
  <div style="color:#bfe0d8;font-size:13px;margin:2px 0 10px"><?= e($me['headline'] ?? 'Technical professional') ?></div>
  <div class="rowk"><span>Phone / e-mail</span><span class="lock" data-pv="contact">🔒 Unlocks when engaged</span></div>
  <div class="rowk"><span>Day rate</span><span data-pv="rate">Range shown</span></div>
  <div class="rowk"><span>Full name</span><span data-pv="fullname">Hidden until engaged</span></div>
</div>

<form method="post" action="/pro/privacy">
  <?php
    $group = function ($key, $current) use ($labels) {
      $opts = $labels[$key] ?? [];
      echo '<div class="pv-card">';
      $titles = ['contact' => ['📞 Contact details', 'Your phone number and e-mail.'],
                 'rate' => ['💰 Day rate', 'What you charge per day.'],
                 'identity' => ['🪪 Your name in search', 'How your name appears before a client engages you.']];
      [$h, $s] = $titles[$key] ?? [$key, ''];
      echo '<div class="grp-h">' . e($h) . '</div><div class="grp-s">' . e($s) . '</div>';
      foreach ($opts as $val => $meta) {
        [$lbl, $desc] = $meta;
        $sel = ($current === $val) ? ' sel' : '';
        $chk = ($current === $val) ? ' checked' : '';
        echo '<label class="pv-opt' . $sel . '" data-key="' . e($key) . '" data-val="' . e($val) . '">'
           . '<span class="t"><input type="radio" name="' . e($key) . '" value="' . e($val) . '"' . $chk . '>' . e($lbl) . '</span>'
           . '<div class="d">' . e($desc) . '</div></label>';
      }
      echo '</div>';
    };
    $group('contact', $cur['contact']);
    $group('rate', $cur['rate']);
    $group('identity', $cur['identity']);
  ?>

  <div class="pv-card">
    <label class="pv-opt<?= $listed ? ' sel' : '' ?>" style="margin-bottom:0" data-key="listed" data-val="1">
      <span class="t"><input type="checkbox" name="listed" value="1" id="pvListed"<?= $listed ? ' checked' : '' ?>>List me in client search</span>
      <div class="d">Turn this off to pause all discovery — you stay in your saved jobs and messages, but new clients won't find you.</div>
    </label>
  </div>

  <button class="btn" type="submit" style="margin-top:4px">Save privacy settings</button>
</form>

<script>
(function(){
  function upd(){
    var g=function(n){var el=document.querySelector('input[name="'+n+'"]:checked');return el?el.value:'';};
    var contact=g('contact'), rate=g('rate'), identity=g('identity');
    var nm=document.querySelector('[data-pv="name"]'), full=document.querySelector('[data-pv="fullname"]'),
        ct=document.querySelector('[data-pv="contact"]'), rt=document.querySelector('[data-pv="rate"]');
    var realName=<?= json_encode((string)($me['name'] ?? 'You')) ?>;
    function initials(s){var p=s.trim().split(/\s+/);if(p.length<2)return s;return p[0]+' '+p.slice(1).map(function(x){return x[0].toUpperCase()+'.';}).join('');}
    if(identity==='first_initial'){nm.textContent=initials(realName);full.textContent='Hidden until engaged';}
    else{nm.textContent=realName;full.textContent='Shown';}
    if(contact==='public'){ct.textContent='Shown to signed-in clients';ct.classList.remove('lock');}
    else if(contact==='hidden'){ct.textContent='🔒 Platform messages only';ct.classList.add('lock');}
    else{ct.textContent='🔒 Unlocks when engaged';ct.classList.add('lock');}
    rt.textContent = rate==='public' ? 'Exact rate shown' : (rate==='hidden' ? 'Quote on enquiry' : 'Range shown');
    document.querySelectorAll('.pv-opt[data-key]').forEach(function(l){
      var k=l.getAttribute('data-key'), v=l.getAttribute('data-val'); if(k==='listed')return;
      l.classList.toggle('sel', g(k)===v);
    });
  }
  document.querySelectorAll('input[type=radio]').forEach(function(r){r.addEventListener('change',upd);});
  upd();
})();
</script>
