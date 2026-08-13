<div class="crumbs"><a href="/">Home</a> › <a href="/calls">Calls</a> › Recurring services</div>
<div class="master-head">
  <div><h1>Recurring services</h1>
    <p class="sub" style="margin:2px 0 0">A resident inspection, a weekly surveillance, a monthly audit — configure it
      once and the system raises the next call automatically. Generated calls are tagged as generated (source =
      Recurring) so they are never confused with a manually-raised request.</p></div>
  <div class="row-actions">
    <form method="post" action="/recurring" style="margin:0"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="run"><button class="btn secondary" type="submit">Generate due now</button></form>
  </div>
</div>

<section class="card" style="margin-bottom:16px">
  <h2 style="margin:0 0 10px">New recurring schedule</h2>
  <form method="post" action="/recurring">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-grid">
      <div class="ff"><label>Title</label><input class="form-control" name="title" placeholder="e.g. Weekly site surveillance — Unit 4" required></div>
      <div class="ff"><label>Client</label><select class="form-control" name="client_id"><option value="0">—</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?? $c['legal_name'] ?? ('#'.$c['id'])) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Service type</label><select class="form-control" name="inspection_type"><?php foreach ($itypes as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Frequency</label><select class="form-control" name="frequency"><?php foreach ($freq as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Every N (for daily/custom)</label><input class="form-control" type="number" name="interval_n" min="1" value="1" style="width:110px"></div>
      <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>"></div>
      <div class="ff"><label>End date (optional)</label><input class="form-control" type="date" name="end_date"></div>
      <div class="ff"><label>Deliverable(s)</label><input class="form-control" name="deliverables" placeholder="e.g. IR"></div>
    </div>
    <div style="margin-top:10px"><button class="btn" type="submit">Create schedule</button></div>
  </form>
</section>

<section class="card">
  <h2 style="margin:0 0 10px">Schedules</h2>
  <table class="grid">
    <tr><th>Title</th><th>Client</th><th>Service</th><th>Frequency</th><th>Next run</th><th>Generated</th><th>State</th><th></th></tr>
    <?php foreach ($rows as $r): $tpl = json_decode((string)$r['template'], true) ?: []; ?>
    <tr>
      <td><?= e($r['title']) ?></td>
      <td><?= e($r['client_name'] ?: '—') ?></td>
      <td><?= e($itypes[$tpl['inspection_type'] ?? ''] ?? ($tpl['inspection_type'] ?? '—')) ?></td>
      <td><?= e($freq[$r['frequency']] ?? $r['frequency']) ?><?= (int)$r['interval_n'] > 1 ? ' ×'.(int)$r['interval_n'] : '' ?></td>
      <td><?= e($r['next_run'] ?: '—') ?></td>
      <td><?= (int)$r['generated_count'] ?><?= $r['last_generated'] ? ' (last '.e($r['last_generated']).')' : '' ?></td>
      <td><span class="badge <?= $r['active'] ? 'GREEN' : 'AMBER' ?>"><?= $r['active'] ? 'Active' : 'Paused' ?></span></td>
      <td><form method="post" action="/recurring" style="margin:0"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" type="submit"><?= $r['active'] ? 'Pause' : 'Resume' ?></button></form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8">No recurring schedules yet.</td></tr><?php endif; ?>
  </table>
</section>
