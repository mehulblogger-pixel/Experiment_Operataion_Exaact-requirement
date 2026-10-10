<div class="master-head">
<?php //  D4 (Gate 6) — the headline must not count people who have not arrived as
      //  though they were working here. The rows were already labelled correctly;
      //  the total was not. Counted over the rows ACTUALLY DISPLAYED, so the
      //  figure stays true when the D3 filter narrows the list. A blank status
      //  counts as on the team, consistent with every other reader. ?>
<?php $nTeam = 0; $nJoin = 0;
      foreach ($rows as $__r) {
          if (strtoupper(trim((string) ($__r['status'] ?? ''))) === WF_ST_JOINING) $nJoin++;
          elseif (wf_is_active($__r['status'] ?? '')) $nTeam++;
      } ?>
  <div><h1><?= e(T_REG('engineer')) ?></h1><p class="sub"><?= (int) $nTeam ?> on the team<?php
        if ($nJoin > 0): ?> · <strong><?= (int) $nJoin ?> joining pending</strong><?php endif; ?></p>
<?php //  B10-CL-3 — the definition where the word is. B4 wrote these into the
      //  terminology registry and put one at point of use (the hiring-request
      //  screen); the rest were reachable only from /terminology. Same helper,
      //  same registry, one line on the screen the term is the subject of —
      //  not on every row and not where the word is already obvious. ?>
    <?= function_exists('T_NOTE') ? T_NOTE('inspector') : '' ?></div>
  <a class="btn" href="/m/inspectors/new">+ Add <?= e(Tl('engineer')) ?></a>
</div>
<form method="get" action="/m/inspectors" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / code / skill…">
  <?php //  D3 — the one vocabulary, so this list can answer "who have we hired
        //  that has not started yet?". Everyone by default: this is the team
        //  register, not an allocation list. ?>
  <select class="form-control" name="status" style="max-width:210px">
    <option value="">Everyone</option>
    <?php foreach (wf_statuses() as $k => $v): ?>
      <option value="<?= e($k) ?>" <?= (($fStatus ?? '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q !== '' || ($fStatus ?? '') !== ''): ?><a class="btn secondary" href="/m/inspectors">Clear</a><?php endif; ?>
</form>
<table class="grid">
  <tr><th>Name</th><th>Emp code</th><th>Team</th><th>Trade</th><th><?= e(TP('sbu')) ?></th><th>Skills</th><th>Status</th><th>Actions</th></tr>
  <?php $teamLabels = ['FIELD'=>'Field', 'COORD'=>'Coordinator', 'OFFICE'=>'Back office'];
        foreach ($rows as $r): ?>
  <tr>
    <td><a href="/m/inspectors/edit?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name'] ?: '—') ?></strong></a></td>
    <td><?= e($r['emp_code'] ?: '—') ?></td>
    <td><?= e($teamLabels[$r['team_role'] ?? 'FIELD'] ?? 'Field') ?></td>
    <td><?= e(trade_label($r['trade_id'] ?? null)) ?></td>
    <td><?= e(sbu_labels($r['sbus'] ?? '')) ?></td>
    <td class="muted" style="font-size:13px;"><?= e(skill_labels($r['skill_ids'] ?? '')) ?></td>
    <td><span class="badge <?= wf_is_active($r['status'] ?? '')?'GREEN':'AMBER' ?>"><?= e(wf_status_label($r['status'] ?? '')) ?></span></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/inspector-profile?id=<?= (int)$r['id'] ?>">Profile</a>
      <a class="btn small" href="/m/inspectors/edit?id=<?= (int)$r['id'] ?>">Edit</a>
      <form method="post" action="/m/inspectors/delete?id=<?= (int)$r['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this <?= e(Tl('engineer')) ?>?')">
        <button class="btn small danger" type="submit">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8">No inspectors yet. <a href="/m/inspectors/new">Add one</a>.</td></tr><?php endif; ?>
</table>
