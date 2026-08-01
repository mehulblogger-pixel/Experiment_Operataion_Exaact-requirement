<div class="crumbs"><a href="/">Home</a> › Management reviews</div>
<div class="master-head">
  <div><h1>Management review</h1>
    <p class="sub" style="margin:2px 0 0"><?= e(accreditation_ref('review')) ?>. Fifteen required inputs — and most of them are
      questions this system already knows the answer to, so they are counted for you.</p></div>
  <a class="btn secondary" href="/internal-audits">← Internal audits</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">📅</span><div class="k">Last one held</div>
    <div class="v" style="font-size:18px"><?= $ready['last'] ? e(fdate($ready['last']['held_on'])) : 'never' ?></div>
    <div class="d"><?= $ready['days_since'] === null ? 'nothing on file' : (int)$ready['days_since'] . ' days ago' ?></div></div>
  <div class="kpi"><span class="kic">⏳</span><div class="k">Overdue?</div>
    <div class="v" style="font-size:18px"><?= $ready['overdue'] ? '<span class="down">yes</span>' : 'no' ?></div>
    <div class="d">a year is the usual expectation</div></div>
  <div class="kpi"><span class="kic">📌</span><div class="k">Decisions still open</div>
    <div class="v"><?= $ready['open_actions'] ? '<span class="down">' . (int)$ready['open_actions'] . '</span>' : '0' ?></div>
    <div class="d">across every review</div></div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Reviews <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Held</th><th>Period</th><th>Chair</th><th>Decisions</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $acts = review_actions((int)$r['id']);
      $openA = count(array_filter($acts, fn($x) => $x['status'] !== 'DONE')); ?>
      <tr>
        <td><a href="/management-review?id=<?= (int)$r['id'] ?>"><strong><?= e($r['ref']) ?></strong></a></td>
        <td><?= e($r['held_on'] ? fdate($r['held_on']) : '—') ?></td>
        <td class="muted"><?= e(fdate($r['period_from'])) ?> – <?= e(fdate($r['period_to'])) ?></td>
        <td><?= e($r['chair'] ?: '—') ?></td>
        <td><?= count($acts) ?><?= $openA ? ' <span class="pill p-warn">' . $openA . ' open</span>' : '' ?></td>
        <td><?= $r['status'] === 'COMPLETE' ? '<span class="pill p-ok">complete</span>' : '<span class="pill p-warn">draft</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6">None held. This is the clause most often written the night before an assessment — start one and it will fill itself in.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($canEdit): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Hold a review</h3>
  <p class="sub" style="margin-top:0">Choose the period and the fifteen required inputs are laid out, with the
    figures this system can count already counted. You write what they mean.</p>
  <form method="post" action="/management-review-new" class="inline-add">
    <div class="ff"><label>Held on</label><input class="form-control" type="date" name="held_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Period from</label><input class="form-control" type="date" name="period_from" value="<?= e(date('Y-m-d', strtotime('-1 year'))) ?>"></div>
    <div class="ff"><label>to</label><input class="form-control" type="date" name="period_to" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Chaired by</label><input class="form-control" name="chair" value="<?= e(user_name(current_user())) ?>"></div>
    <div class="ff ff-wide"><label>Who was there</label><input class="form-control" name="attendees" placeholder="names and roles"></div>
    <button class="btn small" type="submit">Open it</button>
  </form>
</div>
<?php endif; ?>
