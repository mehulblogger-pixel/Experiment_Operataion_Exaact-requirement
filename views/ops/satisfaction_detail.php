<?php
$sbadge = ['REQUESTED' => 'AMBER', 'RECEIVED' => 'GREEN', 'DECLINED' => 'GREY'][$s['status']] ?? 'GREY';
$ee = fn($x) => e((string)$x);
$row = fn($lbl, $val) => $val !== '' && $val !== null ? '<tr><th style="text-align:left;width:180px;color:#697787;font-weight:600">' . e($lbl) . '</th><td>' . $val . '</td></tr>' : '';
$received = $s['status'] === 'RECEIVED';
$scoreFrac = ($s['score'] !== null && $s['score'] !== '') ? $s['score'] / max(1, $scale) : null;
$scoreBadge = $scoreFrac === null ? 'GREY' : ($scoreFrac >= 0.8 ? 'GREEN' : ($scoreFrac >= 0.5 ? 'AMBER' : 'RED'));
$needFollow = (int)$s['followup_needed'] === 1 && (int)$s['followup_done'] === 0;
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/satisfaction">Customer satisfaction</a> › <?= e($s['survey_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($s['survey_code']) ?>
    <span class="badge <?= $sbadge ?>"><?= e(ucfirst(strtolower($s['status']))) ?></span>
    <?php if ($received && $s['score'] !== null): ?><span class="badge <?= $scoreBadge ?>"><?= (int)$s['score'] ?>/<?= (int)$scale ?></span><?php endif; ?>
    <?php if ($needFollow): ?><span class="badge RED">follow-up open</span><?php endif; ?></h1>
    <p class="sub"><?= e($s['client_name'] ?: '—') ?><?= $s['about'] ? ' · ' . e($s['about']) : '' ?></p></div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="grid" style="margin:0">
    <?= $row(T('client'), $ee($s['client_name'])) ?>
    <?= $row('About', $ee($s['about'])) ?>
    <?= $row('Report / job ref', $ee($s['report_ref'])) ?>
    <?= $row('Requested', $ee(trim(($s['requested_on'] ?? '') . ' by ' . ($s['requested_by'] ?? ''), ' by'))) ?>
    <?php if ($received): ?>
      <?= $row('Received', $ee($s['received_on'])) ?>
      <?= $row('Respondent', $ee(trim(($s['respondent'] ?? '') . ($s['respondent_role'] ? ' (' . $s['respondent_role'] . ')' : '')))) ?>
      <?= $row('Overall score', $s['score'] !== null ? $ee($s['score'] . ' / ' . $scale) : '—') ?>
      <?= $row('Would recommend', $s['recommend'] === 'Y' ? 'Yes' : ($s['recommend'] === 'N' ? 'No' : '')) ?>
      <?php $given = $given ?? []; if ($given): $parts = [];
        foreach ($aspects as $code => $label) if (isset($given[$code])) $parts[] = e($label) . ': <strong>' . (int)$given[$code] . '/' . (int)$scale . '</strong>';
        echo $row('By aspect', implode(' &nbsp;·&nbsp; ', $parts)); ?>
      <?php endif; ?>
      <?= $row('Comments', nl2br($ee($s['comments']))) ?>
    <?php elseif ($s['status'] === 'DECLINED'): ?>
      <?= $row('Declined', $ee($s['received_on'])) ?>
      <?= $row('Note', nl2br($ee($s['comments']))) ?>
    <?php endif; ?>
    <?php if ((int)$s['followup_done'] === 1): ?>
      <?= $row('Follow-up', 'Closed — ' . nl2br($ee($s['followup_note']))) ?>
    <?php endif; ?>
    <?php foreach (($cvals ?? []) as $lbl => $val): ?><?= $row($lbl, $ee($val)) ?><?php endforeach; ?>
  </table>
</div>

<?php if ($canEdit && $s['status'] === 'REQUESTED'): ?>
<div class="panel-split" style="margin-top:20px">
  <div class="card">
    <h4 style="margin-top:0">Record the response</h4>
    <form method="post" action="/satisfaction-record">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <div class="form-grid">
        <div class="ff"><label>Respondent</label><input class="form-control" name="respondent" placeholder="Who gave the feedback"></div>
        <div class="ff"><label>Their role</label><input class="form-control" name="respondent_role" placeholder="e.g. QA Manager"></div>
        <div class="ff"><label>Overall score (1–<?= (int)$scale ?>)</label>
          <select class="form-control" name="score"><option value="">—</option>
            <?php for ($i = $scale; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> / <?= (int)$scale ?></option><?php endfor; ?>
          </select></div>
        <div class="ff"><label>Would they recommend us?</label>
          <select class="form-control" name="recommend"><option value="">—</option>
            <option value="Y">Yes</option><option value="N">No</option></select></div>
      </div>

      <?php if ($aspects): ?>
      <div style="margin-top:12px">
        <label style="font-weight:600;font-size:13px;color:#697787">By aspect (optional)</label>
        <div class="form-grid" style="margin-top:6px">
          <?php foreach ($aspects as $code => $label): ?>
            <div class="ff"><label><?= e($label) ?></label>
              <select class="form-control" name="aspect[<?= e($code) ?>]"><option value="">—</option>
                <?php for ($i = $scale; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
              </select></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="ff" style="margin-top:12px"><label>Comments</label>
        <textarea class="form-control" name="comments" rows="3" placeholder="What the customer said in their own words"></textarea></div>
      <button class="btn" type="submit" style="margin-top:10px">Save response</button>
      <p class="muted" style="font-size:12px;margin-top:8px">A score at or below <?= (int)sat_followup_threshold() ?>/<?= (int)$scale ?> raises a follow-up so it is not missed.</p>
    </form>
  </div>

  <div class="card">
    <h4 style="margin-top:0">They will not answer</h4>
    <p class="muted" style="font-size:13px">If the customer declines or does not respond, record it — a request left open for ever hides the real response rate.</p>
    <form method="post" action="/satisfaction-decline">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <input class="form-control" name="note" placeholder="Optional note (e.g. no reply after two asks)">
      <button class="btn secondary" type="submit" style="margin-top:10px">Mark as declined / no reply</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($canEdit && $needFollow): ?>
<div class="card" style="margin-top:20px;max-width:520px;border-left:3px solid #b3402f">
  <h4 style="margin-top:0">Close the follow-up</h4>
  <p class="muted" style="font-size:13px">This response scored low. Record what was done about it — a call back, a correction, a nonconformity raised.</p>
  <form method="post" action="/satisfaction-followup">
    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    <textarea class="form-control" name="note" rows="2" placeholder="What was done to address it" required></textarea>
    <button class="btn small" type="submit" style="margin-top:10px">Mark follow-up done</button>
  </form>
</div>
<?php endif; ?>
