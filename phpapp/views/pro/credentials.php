<?php
  // Connect K0+ — structured certifications + project experience on the passport.
  $me = $me ?? []; $certs = $certs ?? []; $projects = $projects ?? [];
  $stPill = function ($s) {
    $m = ['VALID'=>['Valid','ok'],'EXPIRING'=>['Expiring','warn'],'EXPIRED'=>['Expired','err']];
    if (!isset($m[$s])) return '';
    [$l,$c] = $m[$s]; return '<span class="cpill '.$c.'">'.e($l).'</span>';
  };
?>
<style>
  .cpill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600}
  .cpill.ok{background:#e7f5ef;color:#0f7d5a}.cpill.warn{background:#fbf3d8;color:#8a6d0b}.cpill.err{background:#f6e6e6;color:#9a2a2a}.cpill.info{background:#e6f0fb;color:#1858a8}
  .crow{border:1px solid var(--line,#e3ebea);border-radius:11px;padding:11px 13px;margin-bottom:8px}
  .crow .h{display:flex;justify-content:space-between;gap:8px;align-items:start}
  .crow .m{color:var(--muted,#667);font-size:12.5px;margin-top:2px}
  .ac{position:relative}.ac-list{position:absolute;left:0;right:0;top:100%;z-index:20;background:var(--card,#fff);border:1px solid var(--line,#ddd);border-radius:10px;margin-top:2px;max-height:200px;overflow:auto;display:none}
  .ac-list.open{display:block}.ac-list div{padding:8px 11px;cursor:pointer;font-size:14px}.ac-list div:hover{background:rgba(15,125,125,.09)}
</style>

<p style="margin:0 0 8px"><a href="/pro/profile" class="muted" style="font-size:14px">← My passport</a></p>
<h1>Certifications &amp; experience</h1>
<p class="muted" style="margin:0 0 14px">Structured records clients can filter on. Expiry is tracked automatically; verification is confirmed by our team, not self-declared.</p>

<?php // ---- Certifications ---- ?>
<div class="card">
  <h2>Certifications</h2>
  <?php if (!$certs): ?><p class="muted" style="margin:0 0 12px">None added yet.</p>
  <?php else: foreach ($certs as $c): ?>
    <?php
      $vs = strtoupper((string)($c['verify_status'] ?? ''));
      $verified = (int)$c['verified'] === 1;
    ?>
    <div class="crow">
      <div class="h">
        <div><strong><?= e($c['name']) ?></strong> <?= $stPill($c['status']) ?>
          <?php if ($verified): ?> <span class="cpill info">✓ Verified</span>
          <?php elseif ($vs === 'PENDING'): ?> <span class="cpill warn">Verification pending</span>
          <?php elseif ($vs === 'REJECTED'): ?> <span class="cpill err">Not verified</span>
          <?php else: ?> <span class="cpill" style="background:#eef1f4;color:#556">Unverified</span><?php endif; ?>
          <div class="m">
            <?php if ($c['authority']): ?><?= e($c['authority']) ?> · <?php endif; ?>
            <?php if ($c['cert_number']): ?>No. <?= e($c['cert_number']) ?> · <?php endif; ?>
            <?php if ($c['level']): ?>Level <?= e($c['level']) ?> · <?php endif; ?>
            <?php if ($c['discipline']): ?><?= e($c['discipline']) ?> · <?php endif; ?>
            <?php if ($c['issue_date']): ?>issued <?= e(substr($c['issue_date'],0,10)) ?><?php endif; ?>
            <?php if ($c['expiry_date']): ?> · expires <?= e(substr($c['expiry_date'],0,10)) ?><?php endif; ?>
            <?php if ((int)$c['file_id']): ?> · <a href="/pro/file?id=<?= (int)$c['file_id'] ?>" target="_blank" rel="noopener">📎 document</a><?php endif; ?>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
          <?php if (!$verified && $vs !== 'PENDING'): ?>
            <?php if ((int)$c['file_id'] > 0): ?>
              <form method="post" action="/pro/credentials" style="margin:0"><input type="hidden" name="action" value="cert_verify"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn" type="submit" style="padding:4px 10px;font-size:12px" title="Our team will review your certificate">Request verification</button></form>
            <?php else: ?>
              <span class="muted" style="font-size:11.5px;max-width:150px;text-align:right">Attach the document (edit below) to request verification</span>
            <?php endif; ?>
          <?php endif; ?>
          <form method="post" action="/pro/credentials" style="margin:0"><input type="hidden" name="action" value="cert_del"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sec" type="submit" style="padding:4px 10px;font-size:12px">Remove</button></form>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <form method="post" action="/pro/credentials" enctype="multipart/form-data" style="border-top:1px solid var(--line,#eee);margin-top:8px;padding-top:12px">
    <input type="hidden" name="action" value="cert_save"><input type="hidden" name="node_id" id="certNode" value="">
    <label>Certification</label>
    <div class="ac"><input type="text" name="name" id="certName" autocomplete="off" required placeholder="e.g. CSWIP 3.1, ASNT NDT Level II, NEBOSH"><div class="ac-list" id="certList"></div></div>
    <div class="grid2">
      <div><label>Issuing authority</label><input name="authority" placeholder="e.g. TWI, ASNT, BINDT"></div>
      <div><label>Certificate number</label><input name="cert_number"></div>
      <div><label>Level (if any)</label><input name="level" placeholder="e.g. II, 3.1"></div>
      <div><label>Discipline</label><input name="discipline" placeholder="e.g. NDT, Welding"></div>
      <div><label>Issue date</label><input type="date" name="issue_date"></div>
      <div><label>Expiry date</label><input type="date" name="expiry_date"></div>
    </div>
    <label>Certificate document (optional)</label><input type="file" name="cert_file">
    <button class="btn" type="submit" style="margin-top:12px">Add certification</button>
  </form>
</div>

<?php // ---- Project experience ---- ?>
<div class="card">
  <h2>Project experience</h2>
  <p class="muted" style="margin:0 0 10px;font-size:13px">What you actually did — where, on what equipment, in which industry, for how long. This is often what wins the job.</p>
  <?php if (!$projects): ?><p class="muted" style="margin:0 0 12px">No projects added yet.</p>
  <?php else: foreach ($projects as $pr): ?>
    <div class="crow">
      <div class="h">
        <div><strong><?= e($pr['title']) ?></strong>
          <div class="m">
            <?php if ($pr['role']): ?><?= e($pr['role']) ?> · <?php endif; ?>
            <?php if ($pr['client']): ?><?= e($pr['client']) ?> · <?php endif; ?>
            <?php if ($pr['industry']): ?><?= e($pr['industry']) ?> · <?php endif; ?>
            <?php if ($pr['location']): ?><?= e($pr['location']) ?><?php endif; ?>
            <?php if ($pr['start_date'] || $pr['end_date']): ?> · <?= e(substr($pr['start_date'],0,10)) ?> – <?= e(substr($pr['end_date'],0,10) ?: 'now') ?><?php endif; ?>
          </div>
          <?php if ($pr['equipment']): ?><div class="m">Equipment: <?= e($pr['equipment']) ?></div><?php endif; ?>
          <?php if ($pr['scope']): ?><div class="m" style="margin-top:4px"><?= e($pr['scope']) ?></div><?php endif; ?>
        </div>
        <form method="post" action="/pro/credentials" style="margin:0"><input type="hidden" name="action" value="project_del"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>"><button class="btn sec" type="submit" style="padding:4px 10px;font-size:12px">Remove</button></form>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <form method="post" action="/pro/credentials" style="border-top:1px solid var(--line,#eee);margin-top:8px;padding-top:12px">
    <input type="hidden" name="action" value="project_save">
    <label>Project title</label><input name="title" required placeholder="e.g. Refinery shutdown 2025 — RT/UT inspection">
    <div class="grid2">
      <div><label>Your role</label><input name="role" placeholder="e.g. NDT Technician"></div>
      <div><label>Client / owner (optional)</label><input name="client"></div>
      <div><label>Industry</label><input name="industry" placeholder="e.g. Oil &amp; Gas"></div>
      <div><label>Location</label><input name="location" placeholder="e.g. Dahej, Gujarat"></div>
      <div><label>Start</label><input type="date" name="start_date"></div>
      <div><label>End</label><input type="date" name="end_date"></div>
    </div>
    <label>Equipment / systems</label><input name="equipment" placeholder="e.g. Pressure vessels, piping, storage tanks">
    <label>Scope of work</label><textarea name="scope" rows="2" placeholder="What you did on this project…"></textarea>
    <button class="btn" type="submit" style="margin-top:12px">Add project</button>
  </form>
</div>

<script>
(function(){
  var name=document.getElementById('certName'), list=document.getElementById('certList'), node=document.getElementById('certNode'), t;
  if(!name) return;
  name.addEventListener('input', function(){ node.value=''; clearTimeout(t); var q=name.value.trim(); if(q.length<2){list.classList.remove('open');list.innerHTML='';return;}
    t=setTimeout(function(){ fetch('/pro/tax-resolve?kinds=CERTIFICATION&q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(rows){
      list.innerHTML=''; (rows||[]).slice(0,10).forEach(function(r){ var d=document.createElement('div'); d.textContent=r.name; d.onclick=function(){ name.value=r.name; node.value=r.id; list.classList.remove('open'); }; list.appendChild(d); });
      list.classList.toggle('open',(rows||[]).length>0);
    }).catch(function(){}); },160);
  });
  document.addEventListener('click',function(e){ if(!list.contains(e.target)&&e.target!==name) list.classList.remove('open'); });
})();
</script>
