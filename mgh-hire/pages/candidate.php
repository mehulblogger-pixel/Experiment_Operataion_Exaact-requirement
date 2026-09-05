<?php
// Candidate — the working screen: stage progress, actions, interviews,
// documents, offer, and the full audit timeline.
require_can('view');

$id = (int)get('id');
$s = db()->prepare("SELECT c.*, r.title req, r.code reqcode, r.id rid
                    FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id WHERE c.id=?");
$s->execute([$id]);
$c = $s->fetch();
if (!$c) { flash('Candidate not found.', 'err'); redirect('?p=candidates'); }

// ---- Actions -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post('do');

    if ($do === 'advance') {
        require_can('cand.move');
        $nx = next_stage($c['stage_id']);
        if (!$nx) { flash('Already at the final stage.', 'err'); }
        else {
            $prevStatus = $c['status'];
            cand_move($c, $nx['id'], 'passed', post('remarks'));
            // Entering the offer stage flips status to 'offer'.
            if ($nx['kind'] === 'offer') db()->prepare("UPDATE candidates SET status='offer' WHERE id=?")->execute([$id]);
            if ($nx['kind'] === 'terminal') flash("{$c['name']} marked Hired — ready for onboarding.");
            else flash("Moved to “{$nx['name']}”.");
        }
    } elseif ($do === 'reject') {
        require_can('gate.decide');
        db()->prepare("UPDATE candidates SET status='rejected' WHERE id=?")->execute([$id]);
        cand_log($id, 'reject', (int)$c['stage_id'], (int)$c['stage_id'], 'rejected', post('remarks'));
        if (post('notify')) notify_rejected($c);
        flash("{$c['name']} marked rejected.");
    } elseif ($do === 'hold') {
        require_can('cand.move');
        $new = $c['status']==='on_hold' ? 'active' : 'on_hold';
        db()->prepare("UPDATE candidates SET status=? WHERE id=?")->execute([$new,$id]);
        cand_log($id, $new==='on_hold'?'hold':'resume', (int)$c['stage_id'], (int)$c['stage_id'], '', post('remarks'));
        flash($new==='on_hold' ? 'Candidate put on hold.' : 'Candidate resumed.');
    } elseif ($do === 'interview') {
        require_can('interview.log');
        db()->prepare("INSERT INTO interviews (candidate_id,round,scheduled_at,panel,mode,created_at)
                       VALUES (?,?,?,?,?,?)")
            ->execute([$id, post('round','L1'), post('scheduled_at'), post('panel'), post('mode','In person'), now()]);
        cand_log($id, 'interview_scheduled', (int)$c['stage_id'], (int)$c['stage_id'], '', post('round').' — '.post('scheduled_at'));
        notify_interview($c, post('round','L1'), post('scheduled_at'));
        flash('Interview scheduled.');
    } elseif ($do === 'interview_result') {
        require_can('interview.log');
        $iid = (int)post('iid');
        db()->prepare("UPDATE interviews SET result=?, rating=?, feedback=? WHERE id=? AND candidate_id=?")
            ->execute([post('result'), (int)post('rating','0'), post('feedback'), $iid, $id]);
        cand_log($id, 'interview_result', (int)$c['stage_id'], (int)$c['stage_id'], post('result'), post('feedback'));
        flash('Interview outcome recorded.');
    } elseif ($do === 'upload') {
        require_can('cand.manage');
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $bytes = file_get_contents($_FILES['file']['tmp_name']);
            if (strlen($bytes) > 12*1024*1024) { flash('File too large (max 12 MB).', 'err'); }
            else {
                $data = 'data:'.($_FILES['file']['type']?:'application/octet-stream').';base64,'.base64_encode($bytes);
                db()->prepare("INSERT INTO documents (candidate_id,doc_type,file_name,file_data,uploaded_by,created_at)
                               VALUES (?,?,?,?,?,?)")
                    ->execute([$id, post('doc_type','Document'), basename($_FILES['file']['name']), $data, current_user()['name'], now()]);
                cand_log($id, 'document', (int)$c['stage_id'], (int)$c['stage_id'], '', post('doc_type').': '.basename($_FILES['file']['name']));
                flash('Document collected.');
            }
        } else flash('Please choose a file.', 'err');
    } elseif ($do === 'offer') {
        require_can('offer.manage');
        if (in_array($c['status'], ['hired','rejected','withdrawn'], true)) {
            flash('This candidate is already closed — no new offer can be issued.', 'err');
            redirect('?p=candidate&id='.$id);
        }
        $ex = db()->prepare("SELECT id FROM offers WHERE candidate_id=? ORDER BY id DESC LIMIT 1");
        $ex->execute([$id]); $ofid = $ex->fetchColumn();
        if ($ofid) {
            db()->prepare("UPDATE offers SET ctc=?,components=?,joining_date=?,status='issued',issued_at=? WHERE id=?")
                ->execute([post('ctc'),post('components'),post('joining_date'),now(),$ofid]);
        } else {
            db()->prepare("INSERT INTO offers (candidate_id,ctc,components,joining_date,status,issued_at,created_at)
                           VALUES (?,?,?,?, 'issued', ?, ?)")
                ->execute([$id,post('ctc'),post('components'),post('joining_date'),now(),now()]);
        }
        db()->prepare("UPDATE candidates SET status='offer' WHERE id=?")->execute([$id]);
        cand_log($id, 'offer_issued', (int)$c['stage_id'], (int)$c['stage_id'], '', 'CTC '.post('ctc').' · joining '.post('joining_date'));
        notify_offer($c, ['ctc'=>post('ctc'),'joining_date'=>post('joining_date')]);
        flash('Offer issued.');
    } elseif ($do === 'offer_accept') {
        require_can('offer.manage');
        db()->prepare("UPDATE offers SET status='accepted', accepted_at=? WHERE candidate_id=?")->execute([now(),$id]);
        cand_log($id, 'offer_accepted', (int)$c['stage_id'], (int)$c['stage_id'], 'accepted', '');
        flash('Offer marked accepted.');
    }
    redirect('?p=candidate&id='.$id);
}

// Reload after possible change.
$s->execute([$id]); $c = $s->fetch();
$curStage = stage($c['stage_id']);
$allStages = stages_all();
$curSeq = $curStage['seq'] ?? -1;

$ints = db()->prepare("SELECT * FROM interviews WHERE candidate_id=? ORDER BY id DESC"); $ints->execute([$id]); $ints = $ints->fetchAll();
$docs = db()->prepare("SELECT * FROM documents WHERE candidate_id=? ORDER BY id DESC"); $docs->execute([$id]); $docs = $docs->fetchAll();
$offer = db()->prepare("SELECT * FROM offers WHERE candidate_id=? ORDER BY id DESC LIMIT 1"); $offer->execute([$id]); $offer = $offer->fetch();
$events = cand_events($id);
$terminal = in_array($c['status'], ['hired','rejected','withdrawn'], true);

layout_top('Candidate ' . $c['code']);
?>
<div class="crumbs"><a href="?p=candidates">Candidates</a> › <?php if ($c['reqcode']): ?><a href="?p=requisition&amp;id=<?= (int)$c['rid'] ?>"><?= e($c['reqcode']) ?></a> › <?php endif; ?><?= e($c['code']) ?></div>

<div class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <h2 class="mt0"><?= e($c['name']) ?> &nbsp;<?= cand_pill($c['status']) ?></h2>
      <div class="muted"><?= e($c['req'] ?: 'No requisition') ?><?= $c['source']?' · '.e($c['source']):'' ?></div>
      <div class="muted" style="margin-top:4px">
        <?= $c['email']?e($c['email']).' · ':'' ?><?= $c['phone']?e($c['phone']):'' ?>
        <?= $c['expected_ctc']?' · Expected '.e($c['expected_ctc']):'' ?><?= $c['notice_period']?' · Notice '.e($c['notice_period']):'' ?>
      </div>
    </div>
  </div>
  <!-- Stage progress -->
  <div class="steps" style="margin-top:14px">
    <?php foreach ($allStages as $st):
      $cls = $st['seq'] < $curSeq ? 'done' : ($st['seq']==$curSeq ? 'now' : ''); ?>
      <span class="st <?= $cls ?>"><?= e($st['name']) ?></span>
    <?php endforeach; ?>
  </div>

  <?php if (!$terminal): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
    <?php $nx = next_stage($c['stage_id']); ?>
    <?php if ($nx && can('cand.move')): ?>
      <form method="post" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="do" value="advance"><?= csrf_field() ?>
        <input name="remarks" placeholder="remark (optional)" style="width:220px">
        <button class="btn ok sm">Advance → <?= e($nx['name']) ?></button>
      </form>
    <?php endif; ?>
    <?php if (can('gate.decide')): ?>
      <form method="post" onsubmit="return confirm('Reject this candidate?')" style="display:flex;align-items:center;gap:6px">
        <input type="hidden" name="do" value="reject"><?= csrf_field() ?>
        <button class="btn bad sm">Reject</button>
        <label style="margin:0;font-weight:500;font-size:12px;color:#64748b"><input type="checkbox" name="notify" value="1" style="width:auto;margin-right:4px">email candidate</label>
      </form>
    <?php endif; ?>
    <?php if (can('cand.move')): ?>
      <form method="post"><input type="hidden" name="do" value="hold"><?= csrf_field() ?>
        <button class="btn ghost sm"><?= $c['status']==='on_hold'?'Resume':'Hold' ?></button></form>
    <?php endif; ?>
  </div>
  <?php elseif ($c['status']==='hired'): ?>
    <p style="margin-top:12px"><span class="pill g">Hired</span> — ready for onboarding hand-off.</p>
  <?php endif; ?>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr;gap:18px">
  <!-- Interviews -->
  <div class="card">
    <h2>Interviews</h2>
    <?php if (can('interview.log') && !$terminal): ?>
    <form method="post" style="margin-bottom:12px">
      <input type="hidden" name="do" value="interview"><?= csrf_field() ?>
      <div class="row2">
        <div><label>Round</label><select name="round"><option>L1</option><option>L2</option><option>HR</option><option>Final</option></select></div>
        <div><label>When</label><input name="scheduled_at" type="datetime-local"></div>
      </div>
      <div class="row2">
        <div><label>Panel</label><input name="panel" placeholder="names"></div>
        <div><label>Mode</label><select name="mode"><option>In person</option><option>Video</option><option>Phone</option></select></div>
      </div>
      <div style="margin-top:10px"><button class="btn sm">Schedule interview</button></div>
    </form>
    <?php endif; ?>
    <?php if (!$ints): ?><p class="muted mt0">None scheduled.</p><?php else: ?>
      <?php foreach ($ints as $iv): ?>
        <div style="border:1px solid var(--line);border-radius:9px;padding:10px 12px;margin-bottom:8px">
          <b><?= e($iv['round']) ?></b> · <?= fdate($iv['scheduled_at'], true) ?> · <span class="muted"><?= e($iv['mode']) ?></span>
          <?php if ($iv['panel']): ?><div class="muted">Panel: <?= e($iv['panel']) ?></div><?php endif; ?>
          <?php if ($iv['result']): ?>
            <div style="margin-top:4px"><span class="pill <?= $iv['result']==='Selected'?'g':($iv['result']==='Rejected'?'r':'a') ?>"><?= e($iv['result']) ?></span> <?= $iv['rating']?str_repeat('★',(int)$iv['rating']):'' ?></div>
            <?php if ($iv['feedback']): ?><div class="muted" style="margin-top:3px"><?= nl2br(e($iv['feedback'])) ?></div><?php endif; ?>
          <?php elseif (can('interview.log') && !$terminal): ?>
            <form method="post" style="margin-top:8px;border-top:1px dashed var(--line);padding-top:8px">
              <input type="hidden" name="do" value="interview_result"><input type="hidden" name="iid" value="<?= (int)$iv['id'] ?>"><?= csrf_field() ?>
              <div class="row2">
                <div><label>Outcome</label><select name="result"><option>Selected</option><option>Hold</option><option>Rejected</option></select></div>
                <div><label>Rating (1–5)</label><input name="rating" type="number" min="0" max="5"></div>
              </div>
              <label>Feedback</label><textarea name="feedback" rows="2"></textarea>
              <div style="margin-top:8px"><button class="btn sm">Save outcome</button></div>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Documents -->
  <div class="card">
    <h2>Documents</h2>
    <?php if (can('cand.manage') && !$terminal): ?>
    <form method="post" enctype="multipart/form-data" style="margin-bottom:12px">
      <input type="hidden" name="do" value="upload"><?= csrf_field() ?>
      <div class="row2">
        <div><label>Type</label>
          <select name="doc_type"><option>Resume / CV</option><option>Education</option><option>Experience</option><option>Identity (KYC)</option><option>Salary slip</option><option>Medical report</option><option>Reference</option><option>Other</option></select>
        </div>
        <div><label>File (max 12 MB)</label><input type="file" name="file"></div>
      </div>
      <div style="margin-top:10px"><button class="btn sm">Upload</button></div>
    </form>
    <?php endif; ?>
    <?php if (!$docs): ?><p class="muted mt0">None collected.</p><?php else: ?>
      <table>
        <?php foreach ($docs as $d): ?>
          <tr><td><b><?= e($d['doc_type']) ?></b><br><a href="<?= e($d['file_data']) ?>" download="<?= e($d['file_name']) ?>"><?= e($d['file_name']) ?></a></td>
              <td class="right muted"><?= fdate($d['created_at']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Offer -->
<div class="card">
  <h2>Offer &amp; salary structure</h2>
  <?php if ($offer): ?>
    <p class="mt0">
      <span class="pill <?= $offer['status']==='accepted'?'g':'a' ?>"><?= e(ucfirst($offer['status'])) ?></span>
      CTC <b><?= e(currency().$offer['ctc']) ?></b> · Joining <?= fdate($offer['joining_date']) ?>
      <?= $offer['issued_at']?' · Issued '.fdate($offer['issued_at']):'' ?><?= $offer['accepted_at']?' · Accepted '.fdate($offer['accepted_at']):'' ?>
    </p>
    <?php if ($offer['components']): ?><div class="muted" style="white-space:pre-wrap;margin-bottom:10px"><?= e($offer['components']) ?></div><?php endif; ?>
    <?php if ($offer['status']!=='accepted' && can('offer.manage')): ?>
      <form method="post"><input type="hidden" name="do" value="offer_accept"><?= csrf_field() ?><button class="btn ok sm">Mark offer accepted</button></form>
    <?php endif; ?>
  <?php elseif (can('offer.manage') && !$terminal): ?>
    <form method="post">
      <input type="hidden" name="do" value="offer"><?= csrf_field() ?>
      <div class="row2">
        <div><label>Total CTC (<?= e(currency()) ?>)</label><input name="ctc" placeholder="e.g. 8,50,000"></div>
        <div><label>Joining date</label><input name="joining_date" type="date"></div>
      </div>
      <label>Salary structure / components</label>
      <textarea name="components" rows="3" placeholder="Basic, HRA, allowances, bonus…"></textarea>
      <div style="margin-top:12px"><button class="btn">Issue offer</button></div>
    </form>
  <?php else: ?>
    <p class="muted mt0">No offer yet.</p>
  <?php endif; ?>
</div>

<!-- Timeline -->
<div class="card">
  <h2>History</h2>
  <ul class="timeline">
    <?php foreach ($events as $ev): $to = stage($ev['to_stage']); ?>
      <li>
        <div><b><?= e(ucwords(str_replace('_',' ',$ev['action']))) ?></b>
          <?= $ev['decision']?' · <span class="pill '.($ev['decision']==='passed'||$ev['decision']==='accepted'?'g':($ev['decision']==='rejected'?'r':'s')).'">'.e($ev['decision']).'</span>':'' ?>
          <?= $to && $ev['action']==='move' ? ' → '.e($to['name']) : '' ?>
        </div>
        <?php if ($ev['remarks']): ?><div class="muted"><?= nl2br(e($ev['remarks'])) ?></div><?php endif; ?>
        <div class="tm"><?= e($ev['actor']) ?> · <?= fdate($ev['created_at'], true) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php layout_bottom();
