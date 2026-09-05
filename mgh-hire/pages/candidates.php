<?php
// Candidates — global list with light filtering.
require_can('view');

$stageF = (int)get('stage');
$statusF = preg_replace('/[^a-z_]/','', get('status'));

$sql = "SELECT c.*, r.title req, r.code reqcode FROM candidates c
        LEFT JOIN requisitions r ON r.id=c.requisition_id WHERE 1=1";
$args = [];
if ($stageF)  { $sql .= " AND c.stage_id=?"; $args[] = $stageF; }
if ($statusF) { $sql .= " AND c.status=?";   $args[] = $statusF; }
$sql .= " ORDER BY c.id DESC LIMIT 300";
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

layout_top('Candidates');
?>
<div class="card">
  <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="p" value="candidates">
    <div style="min-width:200px"><label>Stage</label>
      <select name="stage" onchange="this.form.submit()">
        <option value="0">All stages</option>
        <?php foreach (stages_all() as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $stageF===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:180px"><label>Status</label>
      <select name="status" onchange="this.form.submit()">
        <?php foreach ([''=>'All','active'=>'In process','offer'=>'At offer','hired'=>'Hired','rejected'=>'Rejected','withdrawn'=>'Withdrawn','on_hold'=>'On hold'] as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $statusF===$k?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><a class="btn ghost sm" href="?p=candidates">Reset</a></div>
  </form>
</div>

<div class="card">
  <h2><?= count($rows) ?> candidate(s)</h2>
  <?php if (!$rows): ?><p class="muted mt0">No candidates match.</p><?php else: ?>
  <table>
    <tr><th>Code</th><th>Name</th><th>Requisition</th><th>Stage</th><th>Status</th><th>Added</th></tr>
    <?php foreach ($rows as $c): $s = stage($c['stage_id']); ?>
      <tr>
        <td><a href="?p=candidate&amp;id=<?= (int)$c['id'] ?>"><?= e($c['code']) ?></a></td>
        <td><?= e($c['name']) ?></td>
        <td class="muted"><?= e($c['reqcode'] ?: '—') ?></td>
        <td><?= $s?e($s['name']):'—' ?></td>
        <td><?= cand_pill($c['status']) ?></td>
        <td class="muted"><?= fdate($c['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php layout_bottom();
