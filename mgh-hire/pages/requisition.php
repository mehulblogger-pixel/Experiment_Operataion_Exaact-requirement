<?php
// Single requisition — detail, approve/hold, and its candidates.
require_can('view');

$id = (int)get('id');
$s = db()->prepare("SELECT * FROM requisitions WHERE id=?");
$s->execute([$id]);
$r = $s->fetch();
if (!$r) { flash('Requisition not found.', 'err'); redirect('?p=requisitions'); }

// Actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post('do');
    if ($do === 'approve') {
        require_can('req.approve');
        $u = current_user();
        db()->prepare("UPDATE requisitions SET status='approved', approved_by=? WHERE id=?")
            ->execute([$u['name'], $id]);
        flash("Requisition {$r['code']} approved. You can now add candidates.");
    } elseif ($do === 'hold') {
        require_can('req.approve');
        db()->prepare("UPDATE requisitions SET status='on_hold' WHERE id=?")->execute([$id]);
        flash("Requisition {$r['code']} put on hold.");
    } elseif ($do === 'close') {
        require_can('req.manage');
        db()->prepare("UPDATE requisitions SET status='closed' WHERE id=?")->execute([$id]);
        flash("Requisition {$r['code']} closed.");
    } elseif ($do === 'addcand') {
        require_can('cand.manage');
        if ($r['status'] !== 'approved') { flash('Add candidates only after the requisition is approved.', 'err'); redirect('?p=requisition&id='.$id); }
        $first = first_stage();
        $code  = next_code('CAN', 'seq_cand');
        db()->prepare("INSERT INTO candidates
            (code,requisition_id,name,email,phone,source,stage_id,status,expected_ctc,notice_period,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$code,$id,post('name'),post('email'),post('phone'),post('source'),
                     $first['id']??0,'active',post('expected_ctc'),post('notice_period'),now()]);
        $cid = (int)db()->lastInsertId();
        cand_log($cid, 'created', 0, $first['id']??0, '', 'Added to '.$r['code']);
        flash("Candidate $code added.");
        redirect('?p=candidate&id='.$cid);
    }
    redirect('?p=requisition&id='.$id);
}

$cands = db()->prepare("SELECT * FROM candidates WHERE requisition_id=? ORDER BY id DESC");
$cands->execute([$id]);
$cands = $cands->fetchAll();

layout_top('Requisition ' . $r['code']);
?>
<div class="crumbs"><a href="?p=requisitions">Requisitions</a> › <?= e($r['code']) ?></div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div>
      <h2 class="mt0"><?= e($r['title']) ?> &nbsp;<?= req_pill($r['status']) ?></h2>
      <div class="muted"><?= e($r['department'] ?: '—') ?> · <?= e($r['location'] ?: '—') ?> · <?= (int)$r['positions'] ?> position(s) · <?= e($r['employment_type']) ?><?= $r['grade']?' · '.e($r['grade']):'' ?></div>
      <div class="muted" style="margin-top:6px">Organogram: <?= $r['org_verified']?'<span class="pill g">Verified</span>':'<span class="pill a">Not verified</span>' ?></div>
      <?php if ($r['justification']): ?><p style="margin:10px 0 0"><?= nl2br(e($r['justification'])) ?></p><?php endif; ?>
      <div class="muted" style="margin-top:8px">Raised by <?= e($r['raised_by']) ?> on <?= fdate($r['created_at']) ?><?= $r['approved_by']?' · Approved by '.e($r['approved_by']):'' ?></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($r['status']==='pending_approval' && can('req.approve')): ?>
        <form method="post"><input type="hidden" name="do" value="approve"><?= csrf_field() ?><button class="btn ok sm">Approve</button></form>
      <?php endif; ?>
      <?php if (in_array($r['status'],['approved','pending_approval']) && can('req.approve')): ?>
        <form method="post"><input type="hidden" name="do" value="hold"><?= csrf_field() ?><button class="btn warn sm">Hold</button></form>
      <?php endif; ?>
      <?php if ($r['status']!=='closed' && can('req.manage')): ?>
        <form method="post" onsubmit="return confirm('Close this requisition?')"><input type="hidden" name="do" value="close"><?= csrf_field() ?><button class="btn ghost sm">Close</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($r['status']==='approved' && can('cand.manage')): ?>
<div class="card">
  <h2>Add a candidate</h2>
  <form method="post">
    <input type="hidden" name="do" value="addcand"><?= csrf_field() ?>
    <div class="row3">
      <div><label>Name *</label><input name="name" required></div>
      <div><label>Email</label><input name="email" type="email"></div>
      <div><label>Phone</label><input name="phone"></div>
    </div>
    <div class="row3">
      <div><label>Source</label>
        <select name="source"><option value="">—</option><option>Job portal</option><option>Consultant</option><option>Referral</option><option>Direct application</option><option>Walk-in</option></select>
      </div>
      <div><label>Expected CTC</label><input name="expected_ctc" placeholder="optional"></div>
      <div><label>Notice period</label><input name="notice_period" placeholder="optional"></div>
    </div>
    <div style="margin-top:14px"><button class="btn">Add candidate</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Candidates (<?= count($cands) ?>)</h2>
  <?php if (!$cands): ?><p class="muted mt0">None yet.</p><?php else: ?>
  <table>
    <tr><th>Code</th><th>Name</th><th>Stage</th><th>Status</th><th>Source</th></tr>
    <?php foreach ($cands as $c): $st = stage($c['stage_id']); ?>
      <tr>
        <td><a href="?p=candidate&amp;id=<?= (int)$c['id'] ?>"><?= e($c['code']) ?></a></td>
        <td><?= e($c['name']) ?></td>
        <td><?= $st?e($st['name']):'—' ?></td>
        <td><?= cand_pill($c['status']) ?></td>
        <td class="muted"><?= e($c['source'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php layout_bottom();
