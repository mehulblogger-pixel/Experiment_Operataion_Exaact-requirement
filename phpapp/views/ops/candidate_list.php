<?php
  $stageBadge = ['RECEIVED'=>'AMBER','SUBMITTED'=>'AMBER','SHORTLISTED'=>'AMBER','INTERVIEW'=>'AMBER',
                'HOLD'=>'AMBER','REJECTED'=>'RED','ACCEPTED'=>'GREEN','WITHDRAWN'=>'RED'];
?>
<div class="master-head">
  <div><h1><?= e(T_REG('candidate')) ?></h1>
    <p class="sub">Candidate CVs for project deputation / resident engineers · <?= count($rows) ?> shown</p></div>
  <a class="btn" href="/candidate-new">+ Add candidate CV</a>
</div>

<div class="filter-bar" style="flex-wrap:wrap;gap:6px;margin-bottom:6px">
  <a class="btn small <?= $stage===''?'':'secondary' ?>" href="/candidates">All</a>
  <?php foreach (lk_options_or('candidate_stage', CAND_STAGES) as $k=>$v): ?>
    <a class="btn small <?= $stage===$k?'':'secondary' ?>" href="/candidates?stage=<?= e($k) ?>"><?= e($v) ?> (<?= (int)($counts[$k] ?? 0) ?>)</a>
  <?php endforeach; ?>
</div>
<form method="get" action="/candidates" class="filter-bar">
  <?php if ($stage): ?><input type="hidden" name="stage" value="<?= e($stage) ?>"><?php endif; ?>
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / code / site…">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q): ?><a class="btn secondary" href="/candidates<?= $stage?'?stage='.e($stage):'' ?>">Clear</a><?php endif; ?>
</form>

<table class="grid">
  <tr><th>CV</th><th>Candidate</th><th>Trade</th><th>Client</th><th>Proposed site</th><th>Exp (yrs)</th><th>Source</th><th>Stage</th><th>Actions</th></tr>
  <?php foreach ($rows as $c): ?>
  <tr>
    <td><a href="/candidate?id=<?= (int)$c['id'] ?>"><?= e($c['cand_code'] ?: ('#'.$c['id'])) ?></a></td>
    <td><strong><?= e(candidate_name($c)) ?></strong></td>
    <td><?= e($c['trade_label'] ?: '—') ?></td>
    <td><?= e($c['client_disp'] ?: $c['client_name'] ?: '—') ?></td>
    <td><?= e($c['proposed_site'] ?: '—') ?></td>
    <td><?= e(rtrim(rtrim((string)($c['experience_years'] ?? 0), '0'), '.') ?: '0') ?></td>
    <td><?= e(lk_options_or('candidate_source', CAND_SOURCES)[$c['source']] ?? $c['source']) ?></td>
    <td><span class="badge <?= $stageBadge[$c['stage']] ?? 'AMBER' ?>"><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$c['stage']] ?? $c['stage']) ?></span></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/candidate?id=<?= (int)$c['id'] ?>">Open</a>
      <a class="btn small" href="/candidate-edit?id=<?= (int)$c['id'] ?>">Edit</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9">No candidates<?= $stage?' in this stage':'' ?> yet. <a href="/candidate-new">Add the first CV</a>.</td></tr><?php endif; ?>
</table>
