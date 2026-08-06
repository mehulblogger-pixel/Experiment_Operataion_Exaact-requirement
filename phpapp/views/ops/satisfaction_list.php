<?php
$sbadge = ['REQUESTED' => 'AMBER', 'RECEIVED' => 'GREEN', 'DECLINED' => 'GREY'];
// Score band for the little coloured pill next to a score.
$scoreBadge = function ($score) use ($scale) {
    if ($score === null || $score === '') return 'GREY';
    $frac = $score / max(1, $scale);
    return $frac >= 0.8 ? 'GREEN' : ($frac >= 0.5 ? 'AMBER' : 'RED');
};
?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Customer satisfaction</div>
<div class="master-head">
  <div><h1>Customer satisfaction</h1>
    <p class="sub">How customers rate the work after it closes · ISO 9001 §9.1.2</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/satisfaction-new">+ Request feedback</a><?php endif; ?>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">⭐</span><div class="k">Average score</div>
    <div class="v"><?= $sum['avg'] === null ? '—' : e($sum['avg']) . '<span style="font-size:14px;color:#889">/' . (int)$sum['scale'] . '</span>' ?></div>
    <div class="d"><?= (int)$sum['received'] ?> response<?= (int)$sum['received'] === 1 ? '' : 's' ?></div></div>
  <div class="kpi"><span class="kic">📨</span><div class="k">Response rate</div>
    <div class="v"><?= $sum['response_rate'] === null ? '—' : (int)$sum['response_rate'] . '%' ?></div>
    <div class="d"><?= (int)$sum['requested'] ?> awaiting a reply</div></div>
  <div class="kpi"><span class="kic">👍</span><div class="k">Would recommend</div>
    <div class="v"><?= $sum['recommend_rate'] === null ? '—' : (int)$sum['recommend_rate'] . '%' ?></div>
    <div class="d"><?= (int)$sum['recommend'] ?> yes · <?= (int)$sum['not_recommend'] ?> no</div></div>
  <div class="kpi"><span class="kic">📞</span><div class="k">Follow-ups open</div>
    <div class="v"><?= $sum['followup'] ? '<span class="down">' . (int)$sum['followup'] . '</span>' : '0' ?></div>
    <div class="d">low scores to act on</div></div>
</div>

<form method="get" action="/satisfaction" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / about / respondent / comment…">
  <button class="btn secondary" type="submit">Search</button>
  <a class="btn secondary<?= $show === 'all' ? ' active' : '' ?>" href="/satisfaction">All</a>
  <a class="btn secondary<?= $show === 'open' ? ' active' : '' ?>" href="/satisfaction?show=open">Awaiting reply</a>
  <a class="btn secondary<?= $show === 'followup' ? ' active' : '' ?>" href="/satisfaction?show=followup">Needs follow-up</a>
</form>

<table class="grid">
  <tr><th>Code</th><th><?= e(T('client')) ?></th><th>About</th><th>Score</th><th>Recommend</th><th>Respondent</th><th>Status</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="/satisfaction-survey?id=<?= (int)$r['id'] ?>"><strong><?= e($r['survey_code']) ?></strong></a>
      <?php if ((int)$r['followup_needed'] === 1 && (int)$r['followup_done'] === 0): ?><span class="badge RED" title="Low score — follow up">follow up</span><?php endif; ?></td>
    <td><?= e($r['client_name'] ?: '—') ?></td>
    <td><?= e($r['about'] ?: ($r['report_ref'] ?: '—')) ?></td>
    <td><?php if ($r['status'] === 'RECEIVED' && $r['score'] !== null): ?>
      <span class="badge <?= $scoreBadge($r['score']) ?>"><?= (int)$r['score'] ?>/<?= (int)$scale ?></span>
    <?php else: ?>—<?php endif; ?></td>
    <td><?= $r['recommend'] === 'Y' ? '✅' : ($r['recommend'] === 'N' ? '❌' : '—') ?></td>
    <td><?= e($r['respondent'] ?: '—') ?></td>
    <td><span class="badge <?= $sbadge[$r['status']] ?? 'GREY' ?>"><?= e(ucfirst(strtolower($r['status']))) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No feedback recorded yet.<?php if ($canEdit): ?> <a href="/satisfaction-new">Request some</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
