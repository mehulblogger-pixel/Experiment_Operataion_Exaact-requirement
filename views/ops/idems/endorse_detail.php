<?php // Endorsement detail — original (unaltered) + supporting + review/decision + certificate + audit ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/endorsements"><?= e(TP('endorsement')) ?></a> › <?= e($e['endorsement_no']) ?></div>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('endorsement', $e['endorsement_no'])) ?> <?= $e['finalized'] ? '🔒' : '' ?></h1>
    <p class="sub" style="margin:2px 0 0"><span class="pill p-info"><?= e($e['doc_type']) ?></span> <?= e(ENDORSE_DOC_TYPES[$e['doc_type']] ?? '') ?> · <span class="pill <?= endorse_status_pill($e['status']) ?>"><?= e(ENDORSE_STATUS[$e['status']] ?? $e['status']) ?></span></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if (endorse_can_edit($e)): ?><a class="btn secondary" href="/endorsement-edit?id=<?= (int)$e['id'] ?>">Edit</a><?php endif; ?>
    <?php if (endorse_can_edit($e) && $e['status']==='UPLOADED'): ?>
      <form method="post" action="/endorsement-submit" style="display:inline"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn" type="submit">Submit for endorsement</button></form>
    <?php endif; ?>
    <?php if ($e['status']==='ENDORSED'): ?><a class="btn" href="/endorsement-cert?id=<?= (int)$e['id'] ?>" target="_blank">📄 Endorsement certificate</a><?php endif; ?>
  </div>
</div>

<?php if ($e['status']==='ENDORSED'): ?>
<div class="panel" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 7%,transparent)">
  <b style="color:var(--ok)">✓ Endorsed</b> — <?= e(strip_tags(ENDORSE_DECISION[$e['decision']] ?? 'Reviewed & Endorsed')) ?> by <?= e($e['endorsed_by']) ?><?= $e['endorsed_at']?' on '.e(date('d M Y H:i', strtotime($e['endorsed_at']))):'' ?>. <?= $e['decision_remarks'] ? '— '.e($e['decision_remarks']) : '' ?>
</div>
<?php elseif ($e['status']==='REJECTED'): ?>
<div class="panel" style="border:1px solid var(--bad)"><b style="color:var(--bad)">✕ Rejected</b> — <?= e($e['decision_remarks']) ?></div>
<?php endif; ?>

<?php if (!empty($canAct)): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Review decision</h3></div>
  <p class="muted" style="margin:0 0 8px">Open the original and any supporting evidence below, then endorse or reject. Your and the inspector's signatures are added automatically on endorsement.</p>
  <form method="post" action="/endorsement-approve">
    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
    <textarea class="form-control" name="remarks" rows="2" placeholder="Remarks (mandatory to reject; optional to endorse)" style="margin-bottom:8px"></textarea>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="btn" type="submit" name="decision" value="endorse">✓ Endorse</button>
      <button class="btn secondary" type="submit" name="decision" value="endorse_cond">Endorse with comments</button>
      <button class="btn secondary" type="submit" name="decision" value="reject">✕ Reject</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <div class="kv-grid">
    <div><span class="k">Endorsement no.</span><strong><?= e($e['endorsement_no']) ?></strong></div>
    <div><span class="k">Document type</span><?= e(ENDORSE_DOC_TYPES[$e['doc_type']] ?? $e['doc_type']) ?></div>
    <div><span class="k">Title</span><?= e($e['title'] ?: '—') ?></div>
    <div><span class="k">Manufacturer / vendor</span><?= e($e['vendor_disp'] ?: $e['vendor_name'] ?: '—') ?></div>
    <div><span class="k">Client</span><?= e($e['client_disp'] ?: $e['client_name'] ?: '—') ?></div>
    <div><span class="k">Heat / lot</span><?= e($e['heat_no'] ?: '—') ?></div>
    <div><span class="k">Item</span><?= e($e['item_desc'] ?: '—') ?></div>
    <div><span class="k">Version</span><?= e($e['doc_version'] ?: '—') ?></div>
    <div><span class="k">PO</span><?= e($e['po_ref'] ?: '—') ?></div>
    <div><span class="k">Drawing</span><?= e(trim(($e['drawing_no'] ?? '').' '.($e['drawing_rev']?'Rev '.$e['drawing_rev']:'')) ?: '—') ?></div>
    <div><span class="k">QAP rev</span><?= e($e['qap_rev'] ?: '—') ?></div>
    <div><span class="k">Inspector</span><?= e($e['inspector_name'] ?: '—') ?></div>
    <div><span class="k">Approver</span><?= $approver ? e(trim(($approver['first_name']??'').' '.($approver['last_name']??'')) ?: $approver['username']) : '—' ?></div>
    <div><span class="k">Linked report</span><?= $e['linked_irn'] ? '<a href="/document?id='.(int)$e['report_doc_id'].'">'.e($e['linked_irn']).'</a>' : '—' ?></div>
  </div>
  <?php if ($e['review_remarks']): ?><div class="kv-wide" style="margin-top:8px"><span class="k">Reviewer notes</span><?= nl2br(e($e['review_remarks'])) ?></div><?php endif; ?>
</div>

<div class="dash-2col" style="align-items:start">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Manufacturer document (original — unaltered)</h3></div>
    <?php if ($orig): foreach ($orig as $f): ?>
      <div style="margin-bottom:6px"><a class="pill p-info" href="/endorsement-file?id=<?= (int)$f['id'] ?>" target="_blank">📎 <?= e($f['file_name']) ?></a> <span class="muted"><?= e($f['created_at']?date('d M Y', strtotime($f['created_at'])):'') ?></span></div>
      <?php if (strpos($f['mime'],'image/')===0): ?><a href="/endorsement-file?id=<?= (int)$f['id'] ?>" target="_blank"><img src="/endorsement-file?id=<?= (int)$f['id'] ?>" style="max-width:100%;border:1px solid var(--line);border-radius:8px"></a><?php endif; ?>
    <?php endforeach; else: ?><p class="muted">No original uploaded yet. <?= endorse_can_edit($e)?'Edit to upload it.':'' ?></p><?php endif; ?>
  </div>
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Supporting evidence</h3></div>
    <?php if ($support): foreach ($support as $f): ?>
      <div style="margin-bottom:6px"><a class="pill p-mut" href="/endorsement-file?id=<?= (int)$f['id'] ?>" target="_blank">📎 <?= e($f['file_name']) ?></a></div>
    <?php endforeach; else: ?><p class="muted">None attached.</p><?php endif; ?>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:10px 14px 0"><h3>Endorsement audit trail</h3></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>When</th><th>Action</th><th>By</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($audit as $a): ?>
      <tr><td class="muted"><?= e($a['created_at']?date('d M Y H:i', strtotime($a['created_at'])):'—') ?></td>
        <td><span class="pill p-mut"><?= e($a['action']) ?></span></td><td><?= e($a['username']) ?></td>
        <td class="muted"><?= e(trim(($a['field']?$a['field'].': ':'').($a['new_value'] ?? '')) ?: ($a['reason'] ?? '')) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
