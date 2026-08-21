<div class="crumbs"><a href="/">Home</a> › <a href="/opportunities">Opportunities</a> › New</div>
<div class="master-head"><div><h1>New opportunity</h1>
  <p class="sub" style="margin:2px 0 0">A piece of business you are trying to win. It can exist long before there is anything to quote.</p></div></div>

<?php if (!empty($form_err)): ?>
  <div class="panel" style="margin-top:12px;max-width:900px;border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 7%,var(--card))">
    <b style="color:var(--bad)">Couldn’t open it yet.</b> <?= e($form_err) ?>
    <div class="muted" style="font-size:12px;margin-top:2px">Nothing you typed was lost — fix the note above and try again.</div>
  </div>
<?php endif; ?>
<form method="post" action="/opportunity-new" class="panel" style="margin-top:16px;max-width:900px">
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>What is the opportunity? *</label>
      <input class="form-control" name="name" required maxlength="255" value="<?= e($prefill['name'] ?? '') ?>"
             placeholder="e.g. Annual vessel inspection contract — 2027 renewal">
      <span class="muted" style="font-size:12px">Name it the way you would say it out loud in a review.</span></div>
    <div><label>Customer <span class="muted" style="font-size:12px">— pick one to be able to quote</span> <a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a></label>
      <select name="partner_id" class="form-control searchable">
        <option value="">— not on the master yet —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($prefill['partner_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>…or who it is for <span class="muted" style="font-size:12px">— a name only</span></label>
      <input class="form-control" name="partner_name" maxlength="200" value="<?= e($prefill['partner_name'] ?? '') ?>"
             placeholder="Company name, if they are not a customer yet">
      <span class="muted" style="font-size:12px">A name-only deal can't be quoted until you link a real customer — pick one above when you can.</span></div>
    <div><label>Pipeline</label>
      <select class="form-control" name="pipeline_id">
        <?php foreach ($pipelines as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)($prefill['pipeline_id'] ?? 0)===(int)$p['id'])?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Estimated value</label><input class="form-control" name="value" type="number" step="0.01" value="<?= e($prefill['value'] ?? '') ?>" placeholder="0.00">
      <span class="muted" style="font-size:12px">A working figure. It moves as you learn more — that is the point of it not being a quotation.</span></div>
    <div><label>Expected close</label><input class="form-control" type="date" name="expected_close" value="<?= e($prefill['expected_close'] ?? '') ?>"></div>
    <div><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ((int)($prefill['office_id'] ?? 0)===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if ($sources): ?>
      <div><label>Where it came from</label>
        <select class="form-control" name="source"><option value="">—</option>
          <?php foreach ($sources as $k=>$v): ?><option value="<?= e($k) ?>" <?= ((string)($prefill['source'] ?? '')===(string)$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div><label>Competing against</label><input class="form-control" name="competitor" maxlength="200" placeholder="Who else is bidding, if you know" value="<?= e($prefill['competitor'] ?? '') ?>"></div>
    <div><label>Contact</label><input class="form-control" name="contact_name" maxlength="150" value="<?= e($prefill['contact_name'] ?? '') ?>"></div>
    <div><label>Contact e-mail</label><input class="form-control" name="contact_email" type="email" maxlength="200" value="<?= e($prefill['contact_email'] ?? '') ?>"></div>
    <div><label>Contact phone</label><input class="form-control" name="contact_phone" maxlength="60" value="<?= e($prefill['contact_phone'] ?? '') ?>"></div>
    <div><label>Next action</label><input class="form-control" name="next_action" maxlength="255" placeholder="e.g. Send the scope for review" value="<?= e($prefill['next_action'] ?? '') ?>"></div>
    <div><label>By when</label><input class="form-control" type="date" name="next_action_on" value="<?= e($prefill['next_action_on'] ?? '') ?>"></div>
    <div class="ff-wide"><label>What they need</label><textarea class="form-control" name="requirement" rows="3"><?= e($prefill['requirement'] ?? '') ?></textarea></div>
  </div>
  <button class="btn" style="margin-top:14px">Open the opportunity</button>
  <a class="btn secondary" href="/opportunities" style="margin-left:8px">Cancel</a>
</form>
<?php // Pick a customer and their contact flows in from the client master — the
      // same details entered under the Directory, so nobody re-types them here. ?>
<script>(function(){
  var sel=document.querySelector('select[name="partner_id"]'); if(!sel) return;
  var f={name:document.querySelector('[name="contact_name"]'),
         email:document.querySelector('[name="contact_email"]'),
         phone:document.querySelector('[name="contact_phone"]')};
  Object.keys(f).forEach(function(k){ if(f[k]) f[k].addEventListener('input',function(){ f[k].dataset.af=''; }); });
  function set(el,v){ if(!el) return; v=v||''; if((el.value||'')!=='' && el.dataset.af!=='1') return; el.value=v; el.dataset.af=v!==''?'1':''; }
  function load(){ var id=sel.value; if(!id) return;
    fetch('/partner-contact?id='+encodeURIComponent(id),{headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json();})
      .then(function(d){ var c=(d&&d.contact)||{}; set(f.name,c.name); set(f.email,c.email); set(f.phone,c.mobile); })
      .catch(function(){});
  }
  sel.addEventListener('change', load);
})();</script>
