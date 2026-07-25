<?php // Smart remarks & conclusions — proposals the inspector reviews before applying ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <a href="/document?id=<?= (int)$doc['id'] ?>"><?= e($doc['irn']) ?></a> › Suggested remarks</div>
<div class="master-head">
  <div><h1>Suggested remarks</h1>
    <p class="sub" style="margin:2px 0 0">Drafted from this report's own findings using the standard phrase library. <strong>You decide</strong> — edit anything before applying.</p></div>
  <a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">← Back to report</a>
</div>

<div class="panel" style="border:1px solid <?= $p['clean'] ? 'var(--ok)' : 'var(--warn,#c90)' ?>">
  <b><?= $p['clean'] ? '✓ No adverse findings detected' : '⚠ Adverse findings detected' ?></b> —
  <?= $p['clean'] ? 'the draft proposes acceptance and release.' : 'the draft proposes conditional acceptance with the observations carried forward.' ?>
</div>

<div class="dash-2col" style="align-items:start">
  <div>
    <form method="post" action="/document-smart?id=<?= (int)$doc['id'] ?>" class="panel">
      <input type="hidden" name="_do" value="apply">
      <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
      <h3 class="tab-sub" style="margin-top:0">Proposed remarks <span class="muted">— edit freely</span></h3>
      <textarea class="form-control" name="remarks" rows="16"><?= e($text) ?></textarea>
      <div class="form-grid" style="margin-top:10px">
        <div class="ff"><label>Inspection result</label>
          <select class="form-control" name="result"><?php foreach (lk_options_or('inspection_result', IDEMS_RESULTS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p['result']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Release status</label>
          <select class="form-control" name="release_status"><?php foreach (lk_options_or('release_status', IDEMS_RELEASE) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p['release']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      </div>
      <div style="margin-top:12px">
        <?php if (idems_can_edit_doc($doc)): ?><button class="btn" type="submit">Apply to report</button>
        <?php else: ?><span class="muted">This report is finalized — the draft above is read-only.</span><?php endif; ?>
        <a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">Cancel</a>
      </div>
    </form>
  </div>

  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">What was proposed</h3>
    <?php
      $blocks = ['summary'=>'Inspection summary','observations'=>'Observations','deviations'=>'Deviations','holds'=>'Hold points','witness'=>'Witness points','conclusion'=>'Conclusion','acceptance'=>'Acceptance status','recommendations'=>'Recommendations'];
      foreach ($blocks as $k=>$lbl): if (empty($p[$k])) continue; ?>
      <div style="margin-bottom:9px">
        <div style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand)"><?= e($lbl) ?></div>
        <div style="font-size:12px"><?= e($p[$k]) ?></div>
      </div>
    <?php endforeach; ?>
    <p class="muted" style="font-size:11px;margin-top:10px">Wording comes from your editable <a href="/phrase-library">phrase library</a>. Nothing is issued until you apply it and the report goes through approval.</p>
  </div>
</div>
