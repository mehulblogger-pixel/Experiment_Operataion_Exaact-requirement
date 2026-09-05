<?php
// Requisitions (Staff Requisition Form) — list + create.
require_can('view');

// Create a new SRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'create') {
    require_can('req.manage');
    $u = current_user();
    $code = next_code('SRF', 'seq_req');
    db()->prepare("INSERT INTO requisitions
        (code,title,department,location,positions,employment_type,grade,org_verified,justification,status,raised_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([
          $code, post('title'), post('department'), post('location'),
          max(1,(int)post('positions','1')), post('employment_type','Permanent'), post('grade'),
          post('org_verified')?1:0, post('justification'),
          'pending_approval', $u['name'], now(),
      ]);
    flash("Requisition $code raised and sent for approval.");
    redirect('?p=requisitions');
}

$rows = db()->query("SELECT r.*,
    (SELECT COUNT(*) FROM candidates c WHERE c.requisition_id=r.id) AS cand
    FROM requisitions r ORDER BY r.id DESC")->fetchAll();

layout_top('Requisitions');
?>
<?php if (can('req.manage')): ?>
<div class="card">
  <h2>Raise a Staff Requisition (SRF)</h2>
  <form method="post">
    <input type="hidden" name="do" value="create"><?= csrf_field() ?>
    <div class="row2">
      <div><label>Job title *</label><input name="title" required placeholder="e.g. Accounts Executive"></div>
      <div><label>Department</label><input name="department" placeholder="e.g. Finance"></div>
    </div>
    <div class="row3">
      <div><label>Location</label><input name="location" placeholder="e.g. Ahmedabad"></div>
      <div><label>No. of positions</label><input name="positions" type="number" min="1" value="1"></div>
      <div><label>Employment type</label>
        <select name="employment_type">
          <option>Permanent</option><option>Contract</option><option>Temporary</option><option>Internship</option>
        </select>
      </div>
    </div>
    <div class="row2">
      <div><label>Grade / band</label><input name="grade" placeholder="optional"></div>
      <div><label style="margin-top:12px"><input type="checkbox" name="org_verified" style="width:auto;margin-right:6px">Verified against approved organogram / manpower plan</label></div>
    </div>
    <label>Justification (new / replacement, business reason)</label>
    <textarea name="justification" rows="2"></textarea>
    <div style="margin-top:14px"><button class="btn">Raise requisition</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>All requisitions</h2>
  <?php if (!$rows): ?><p class="muted mt0">None yet.</p><?php else: ?>
  <table>
    <tr><th>Code</th><th>Title</th><th>Dept</th><th>Positions</th><th>Candidates</th><th>Status</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="?p=requisition&amp;id=<?= (int)$r['id'] ?>"><?= e($r['code']) ?></a></td>
        <td><?= e($r['title']) ?></td>
        <td class="muted"><?= e($r['department'] ?: '—') ?></td>
        <td><?= (int)$r['positions'] ?></td>
        <td><?= (int)$r['cand'] ?></td>
        <td><?= req_pill($r['status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php layout_bottom();
