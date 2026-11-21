<?php
// New deal (owner, 21 Nov 2026: Sales redesign Stage 3, approved design
// s3-dealform). The same boxes, names and save as before, in three numbered
// steps, plus: the business unit (DEF-15), the lead form's own checks on the value
// and the dates (backend item 6), the company's own extra fields ABOVE the buttons
// (they used to sit after them), the stages of the pipeline beside the form, and
// the help text corrected (DEF-16): a deal with only a typed name CAN be quoted
// (since 13 Nov 2026). The win % is not typed — it follows the stage. Saved by
// /opportunity-new in lib/opportunities.php. The old page is kept in
// tests/fixtures/pre_stage3/opportunity_form.php.
require_once __DIR__ . '/_sxw_ui.php';
$sbus = $sbus ?? [];
$stagesByPipe = $stagesByPipe ?? [];
$step = function ($n, $title, $sub = '') {
    echo '<details class="sxl-step" data-step="' . (int)$n . '" open><summary><i>' . (int)$n . '</i><span class="x"><b>' . e($title) . '</b>'
       . ($sub !== '' ? '<small>' . e($sub) . '</small>' : '') . '</span>' . rec_icon('down', 's') . '</summary><div class="sxl-sb">';
};
$stepEnd = function () { echo '</div></details>'; };
$me = current_user();
$selPipe = (int)($prefill['pipeline_id'] ?? 0) ?: (int)($pipelines[0]['id'] ?? 0);
?>
<div class="rec sxw has-bn sxl sxl-form sxd">
  <?php sxw_nav('/opportunities'); ?>
  <?php sxw_head('New ' . Tl('opportunity'), 'A piece of business you are trying to win. It can exist long before there is anything to quote. It starts at the first stage of the pipeline you pick; its win % follows the stage.', 'brief'); ?>

<?php if (!empty($form_err)): ?>
  <div class="sxl-ban bad" role="alert"><span class="ic"><?= rec_icon('alert') ?></span>
    <div class="tx"><b class="t">Couldn’t open it yet.</b> <?= e($form_err) ?>
      <div class="muted" style="font-size:12px;margin-top:2px">Nothing you typed was lost — fix the note above and try again.</div></div></div>
<?php endif; ?>

<div class="sxl-fg">
<form method="post" action="/opportunity-new" class="sxl-main" id="of-form">
  <?php $step(1, 'The ' . Tl('opportunity'), 'Only the name and who it is for are needed.'); ?>
  <div class="form-grid">
    <div class="ff ff-wide"><label>What is the <?= e(Tl('opportunity')) ?>? *</label>
      <input class="form-control" name="name" required maxlength="255" value="<?= e($prefill['name'] ?? '') ?>"
             placeholder="e.g. Annual vessel inspection contract — 2027 renewal">
      <span class="ff-help">Name it the way you would say it out loud in a review.</span></div>
    <div class="ff"><label>Pipeline</label>
      <select class="form-control" name="pipeline_id" id="of-pipe">
        <?php foreach ($pipelines as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)($prefill['pipeline_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Customer <span class="muted" style="font-size:12px">— on the master</span> <?php if (!function_exists('partner_can_add') || partner_can_add()): ?><a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a><?php endif; ?></label>
      <select name="partner_id" class="form-control searchable">
        <option value="">— not on the master yet —</option>
        <?php foreach ($clients as $cl): ?>
          <option value="<?= (int)$cl['id'] ?>" <?= ((int)($prefill['partner_id'] ?? 0) === (int)$cl['id']) ? 'selected' : '' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or who it is for <span class="muted" style="font-size:12px">— a name only</span></label>
      <input class="form-control" name="partner_name" maxlength="200" value="<?= e($prefill['partner_name'] ?? '') ?>"
             placeholder="Company name, if they are not a customer yet">
      <?php //  DEF-16 (owner, 21 Nov 2026): this said a name-only deal cannot be quoted — it can, since 13 Nov 2026. ?>
      <span class="ff-help">A deal with only a typed name can still be quoted; the <?= e(Tl('quote')) ?> goes out in their name, and they are added to the master when they accept it. Link the customer when they confirm.</span></div>
    <?php //  DEF-15 — the business unit: the units this person's data scope covers. ?>
    <div class="ff"><label for="of-sbu"><?= e(ucfirst(Tl('sbu'))) ?></label>
      <select class="form-control" id="of-sbu" name="sbu"><option value="">— not decided yet —</option>
        <?php foreach ($sbus as $sk => $sv): ?><option value="<?= e($sk) ?>"<?= (string)($prefill['sbu'] ?? '') === (string)$sk ? ' selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Estimated value <span class="muted">(<?= e(cur_sym()) ?>)</span></label><input class="form-control" name="value" type="number" step="0.01" min="0" value="<?= e($prefill['value'] ?? '') ?>" placeholder="0.00">
      <span class="ff-help">0 or more. A working figure — it moves as you learn more; that is the point of it not being a <?= e(Tl('quote')) ?>.</span></div>
    <div class="ff"><label>Expected close</label><input class="form-control" type="date" name="expected_close" value="<?= e($prefill['expected_close'] ?? '') ?>"></div>
    <div class="ff"><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $of): ?><option value="<?= (int)$of['id'] ?>" <?= ((int)($prefill['office_id'] ?? 0) === (int)$of['id']) ? 'selected' : '' ?>><?= e($of['name']) ?></option><?php endforeach; ?>
      </select>
      <span class="ff-help">Your own branches only.</span></div>
  </div>
  <?php $stepEnd(); ?>

  <?php $step(2, 'Contact and competition', 'Picking a customer fills the contact; what you typed is never overwritten.'); ?>
  <div class="form-grid">
    <div class="ff"><label>Contact</label><input class="form-control" name="contact_name" maxlength="150" value="<?= e($prefill['contact_name'] ?? '') ?>"></div>
    <div class="ff"><label>Contact e-mail</label><input class="form-control" name="contact_email" type="email" maxlength="200" value="<?= e($prefill['contact_email'] ?? '') ?>"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" type="tel" inputmode="tel" name="contact_phone" maxlength="60" value="<?= e($prefill['contact_phone'] ?? '') ?>"></div>
    <?php if ($sources): ?>
      <div class="ff"><label>Where it came from</label>
        <select class="form-control" name="source"><option value="">—</option>
          <?php foreach ($sources as $k => $v): ?><option value="<?= e($k) ?>" <?= ((string)($prefill['source'] ?? '') === (string)$k) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="ff ff-wide"><label>Competing against</label><input class="form-control" name="competitor" maxlength="200" placeholder="Who else is bidding, if you know" value="<?= e($prefill['competitor'] ?? '') ?>"></div>
  </div>
  <?php $stepEnd(); ?>

  <?php $step(3, 'Next step and requirement', ''); ?>
  <div class="form-grid">
    <div class="ff"><label>Next action</label><input class="form-control" name="next_action" maxlength="255" placeholder="e.g. Send the scope for review" value="<?= e($prefill['next_action'] ?? '') ?>"></div>
    <div class="ff"><label>By when</label><input class="form-control" type="date" name="next_action_on" value="<?= e($prefill['next_action_on'] ?? '') ?>"></div>
    <div class="ff"><label>Handled by</label><div class="form-control" style="background:var(--soft);display:flex;align-items:center" aria-readonly="true">You<?= $me ? ' (' . e(user_name($me)) . ')' : '' ?></div>
      <span class="ff-help">Hand it to someone else from the deal page.</span></div>
    <div class="ff ff-wide"><label>What they need</label><textarea class="form-control" name="requirement" rows="3"><?= e($prefill['requirement'] ?? '') ?></textarea></div>
  </div>
  <?php // Form Designer — render any field this company added, and apply their
        // label / order / hide / required overrides to the built-in ones. Now above the buttons. ?>
  <?php if (function_exists('fd_extra_fields')) echo fd_extra_fields('opportunity', 0); ?>
  <?php $stepEnd(); ?>

  <div class="sxl-act">
    <span class="sp">Win % is not typed — it follows the stage. It starts at the first stage of the pipeline.</span>
    <a class="btn secondary" href="/opportunities">Cancel</a>
    <button class="btn" type="submit">Open the <?= e(Tl('opportunity')) ?></button>
  </div>
</form>

<aside class="sxl-side">
  <?php rec_card_open('Stages of this pipeline', 'flow'); ?>
    <?php foreach ($pipelines as $p): $sts = $stagesByPipe[(int)$p['id']] ?? []; ?>
      <ol class="sxl-path" data-pipe="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === $selPipe ? '' : ' hidden' ?>>
        <?php $first = true; foreach ($sts as $s): if (!($s['active'] ?? 1)) continue; ?>
          <li<?= $first && ($s['kind'] ?? 'OPEN') === 'OPEN' ? ' class="on"' : '' ?>><?= e($s['name']) ?><?= (int)($s['probability'] ?? 0) ? ' · ' . (int)$s['probability'] . '%' : '' ?></li>
        <?php if (($s['kind'] ?? 'OPEN') === 'OPEN') $first = false; endforeach; ?>
      </ol>
    <?php endforeach; ?>
    <p class="sxl-hint" style="margin:6px 0 0">Win % per stage is set in Pipelines. A new <?= e(Tl('opportunity')) ?> opens on the first open stage.</p>
  <?php rec_card_close(); ?>
  <?php rec_card_open('After you press Open', 'check'); ?>
    <ul class="sxl-ul"><li>It gets its number</li><li>The <?= e(Tl('opportunity')) ?> page opens</li><li>It shows on the <?= e(Tlp('opportunity')) ?> board, in its first stage</li></ul>
  <?php rec_card_close(); ?>
</aside>
</div>
</div>
<?php sxw_bottom('/opportunities'); ?>
<?php // Pick a customer and their contact flows in from the client master — the
      // same details entered under the Directory, so nobody re-types them here. ?>
<script>(function(){
  var sel=document.querySelector('select[name="partner_id"]');
  var pipe=document.getElementById('of-pipe');
  if(pipe) pipe.addEventListener('change',function(){ document.querySelectorAll('.sxl-path[data-pipe]').forEach(function(ol){ ol.hidden = ol.getAttribute('data-pipe')!==pipe.value; }); });
  if(!sel) return;
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
