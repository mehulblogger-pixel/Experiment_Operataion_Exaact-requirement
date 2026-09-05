<?php
// Dashboard — headline counts + attention list.
require_can('view');

$openReq   = (int)db()->query("SELECT COUNT(*) c FROM requisitions WHERE status IN ('approved','pending_approval')")->fetch()['c'];
$pendReq   = (int)db()->query("SELECT COUNT(*) c FROM requisitions WHERE status='pending_approval'")->fetch()['c'];
$activeCand= (int)db()->query("SELECT COUNT(*) c FROM candidates WHERE status='active'")->fetch()['c'];
$atOffer   = (int)db()->query("SELECT COUNT(*) c FROM candidates WHERE status IN ('offer')")->fetch()['c'];
$hired     = (int)db()->query("SELECT COUNT(*) c FROM candidates WHERE status='hired'")->fetch()['c'];

// Candidates by stage (for a quick funnel).
$byStage = [];
foreach (db()->query("SELECT stage_id, COUNT(*) c FROM candidates WHERE status='active' GROUP BY stage_id")->fetchAll() as $r)
    $byStage[(int)$r['stage_id']] = (int)$r['c'];

// Recent candidates.
$recent = db()->query("SELECT c.*, r.title req FROM candidates c
                       LEFT JOIN requisitions r ON r.id=c.requisition_id
                       ORDER BY c.id DESC LIMIT 8")->fetchAll();

layout_top('Dashboard');
?>
<div class="grid kpis" style="margin-bottom:18px">
  <div class="kpi b"><div class="n"><?= $openReq ?></div><div class="l">Open requisitions</div></div>
  <div class="kpi"><div class="n"><?= $pendReq ?></div><div class="l">Awaiting approval</div></div>
  <div class="kpi"><div class="n"><?= $activeCand ?></div><div class="l">Candidates in process</div></div>
  <div class="kpi"><div class="n"><?= $atOffer ?></div><div class="l">At offer</div></div>
  <div class="kpi"><div class="n"><?= $hired ?></div><div class="l">Hired</div></div>
</div>

<div class="card">
  <h2>Pipeline at a glance</h2>
  <div class="steps">
    <?php foreach (stages_all() as $s): $n = $byStage[(int)$s['id']] ?? 0; ?>
      <span class="st <?= $n>0?'now':'' ?>"><?= e($s['name']) ?><?= $n>0?' · '.$n:'' ?></span>
    <?php endforeach; ?>
  </div>
  <p class="muted mt0" style="margin-top:10px">Each candidate flows left → right. Numbers show how many people sit at each step right now.</p>
</div>

<div class="card">
  <h2>Recent candidates</h2>
  <?php if (!$recent): ?>
    <p class="muted mt0">No candidates yet. Start by raising a <a href="?p=requisitions">requisition</a>.</p>
  <?php else: ?>
  <table>
    <tr><th>Code</th><th>Name</th><th>Requisition</th><th>Stage</th><th>Status</th></tr>
    <?php foreach ($recent as $c): $st = stage($c['stage_id']); ?>
      <tr>
        <td><a href="?p=candidate&amp;id=<?= (int)$c['id'] ?>"><?= e($c['code']) ?></a></td>
        <td><?= e($c['name']) ?></td>
        <td class="muted"><?= e($c['req'] ?: '—') ?></td>
        <td><?= $st ? e($st['name']) : '—' ?></td>
        <td><?= cand_pill($c['status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php layout_bottom();
