<?php
// Side-by-side vetting: the actual report (left) and the vetting checklist (right).
$vetPill = ['VETTED'=>'p-ok','RETURNED'=>'p-bad','DEBRIEFED'=>'p-info'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <a href="/document?id=<?= (int)$doc['id'] ?>"><?= e($doc['irn']) ?></a> › Vet side by side</div>
<div class="master-head">
  <div><h1>Vet: <?= e($doc['irn']) ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= e($doc['type_name'] ?: $doc['type_code']) ?> · <?= e($doc['client_disp'] ?: $doc['client_name'] ?: '—') ?>
      <?php if (!empty($doc['vet_status']) && isset(IDEMS_VET_STATUS[$doc['vet_status']])): ?>
        · <span class="pill <?= $vetPill[$doc['vet_status']] ?? 'p-mut' ?>"><?= e(IDEMS_VET_STATUS[$doc['vet_status']]) ?></span>
      <?php endif; ?></p></div>
  <div class="row-actions"><a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">← Back to the report</a></div>
</div>

<div class="vet-split" style="display:grid;grid-template-columns:1.5fr 1fr;gap:16px;align-items:start">
  <!-- LEFT: the actual report -->
  <div class="panel" style="padding:0;overflow:hidden;min-height:70vh">
    <div style="padding:8px 12px;border-bottom:1px solid var(--line,#e5e7eb);display:flex;justify-content:space-between;align-items:center">
      <b style="font-size:13px">The report</b>
      <a class="btn small secondary" href="/document-pdf?id=<?= (int)$doc['id'] ?>" target="_blank">Open PDF in a new tab ↗</a>
    </div>
    <iframe src="/document-pdf?id=<?= (int)$doc['id'] ?>#view=FitH" title="Report PDF" style="width:100%;height:78vh;border:0;display:block"></iframe>
  </div>

  <!-- RIGHT: the checklist + vetting actions (sticky, so it stays beside the report) -->
  <div style="position:sticky;top:12px">
    <?php if ($finalized): ?>
      <div class="panel"><b style="color:var(--ok)">✓ Issued.</b> This report is finalised — vetting is closed.</div>
    <?php else: ?>
    <form method="post" action="/document-vet" class="panel" style="margin:0">
      <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
      <h3 class="tab-sub" style="margin-top:0">Vetting</h3>
      <?php if ($checklistOn && $checklistItems): ?>
        <div style="font-weight:600;font-size:12.5px;margin-bottom:7px">Checklist<?= $requireAll ? ' <span class="muted" style="font-weight:400">— all points required to clear</span>' : '' ?></div>
        <?php foreach ($checklistItems as $it): $k = idems_vetting_item_key($it); ?>
          <label class="chk" style="display:flex;align-items:flex-start;gap:8px;margin-bottom:7px;font-size:13px">
            <input type="checkbox" name="vc[<?= e($k) ?>]" value="1" <?= !empty($checklistState[$k]) ? 'checked' : '' ?> style="margin-top:2px">
            <span><?= e($it) ?></span>
          </label>
        <?php endforeach; ?>
        <hr>
      <?php elseif ($checklistOn): ?>
        <p class="muted" style="font-size:12px">No checklist points are configured yet. <?php if (is_master() || can('idems.type.manage')): ?><a href="/vetting-checklist">Set them up →</a><?php endif; ?></p>
      <?php endif; ?>
      <textarea class="form-control" name="note" rows="3" placeholder="Note (required to return for correction)" style="margin-bottom:8px;font-family:inherit"></textarea>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn" type="submit" name="vet_action" value="VETTED">✓ Vet (cleared)</button>
        <button class="btn secondary" type="submit" name="vet_action" value="RETURNED">↩ Return for correction</button>
        <button class="btn secondary" type="submit" name="vet_action" value="DEBRIEFED">🗣 Record debrief</button>
      </div>
    </form>
    <?php endif; ?>

    <?php if (!empty($vetting)): ?>
    <div class="panel" style="margin-top:12px">
      <div style="font-weight:600;font-size:12.5px;margin-bottom:6px">History</div>
      <?php foreach ($vetting as $v): ?>
        <div style="margin-bottom:7px">
          <span class="pill <?= $vetPill[$v['action']] ?? 'p-mut' ?>"><?= e(($v['stage']==='DEBRIEF'?'Debrief · ':'') . (IDEMS_VET_STATUS[$v['action']] ?? $v['action'])) ?></span>
          <span class="muted" style="font-size:12px">— <?= e($v['acted_by']) ?><?= $v['acted_at']?' · '.e(date('d M H:i', strtotime($v['acted_at']))):'' ?></span>
          <?php if ($v['note']): ?><div class="muted" style="font-size:12px">“<?= e($v['note']) ?>”</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
@media (max-width: 820px){ .vet-split{ grid-template-columns:1fr !important; } .vet-split iframe{ height:60vh !important; } }
</style>
