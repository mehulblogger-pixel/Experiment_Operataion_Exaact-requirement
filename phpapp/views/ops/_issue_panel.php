<?php
  // Phase-8 panel on the NCR / issue detail. Included from ncr_detail.php.
  // $n (the nonconformity row), $issue (panel data), $issueTypes, $issueClasses,
  // $issueResp, $depKinds, $depStatuses, $disputeStatus, $issueCanEdit in scope.
  $nid = (int)$n['id'];
  $fmt = fn($s) => $s ? (function_exists('fdate') ? fdate(substr((string)$s,0,10)) : substr((string)$s,0,10)) : '—';
?>
<div class="panel" style="margin-top:16px" id="issue">
  <h3 style="margin-top:0">Issue classification
    <span class="pill p-mut" style="margin-left:6px"><?= e($issueTypes[$n['issue_type'] ?? 'NCR'] ?? ($n['issue_type'] ?: 'NCR')) ?></span></h3>
  <?php if ($issueCanEdit): ?>
    <form method="post" action="/issue-classify" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;max-width:640px">
      <input type="hidden" name="ncr_id" value="<?= $nid ?>">
      <label>Issue type <select name="issue_type"><?php foreach ($issueTypes as $c=>$l): ?><option value="<?= e($c) ?>" <?= ($n['issue_type']??'NCR')===$c?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label>Classification <select name="issue_class"><option value="">—</option><?php foreach ($issueClasses as $c=>$l): ?><option value="<?= e($c) ?>" <?= ($n['issue_class']??'')===$c?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label>Responsible party <select name="responsibility"><option value="">—</option><?php foreach ($issueResp as $c=>$l): ?><option value="<?= e($c) ?>" <?= ($n['responsibility']??'')===$c?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label>Visibility <select name="visibility"><?php foreach (($issueVis ?? []) as $c=>$l): ?><option value="<?= e($c) ?>" <?= ($n['visibility']??'INTERNAL')===$c?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <div style="grid-column:1/3"><button class="btn secondary" type="submit">Save classification</button></div>
    </form>
  <?php else: ?>
    <p class="muted" style="margin:0">Class: <?= e($issueClasses[$n['issue_class']??''] ?? '—') ?> · Responsible: <?= e($issueResp[$n['responsibility']??''] ?? '—') ?></p>
  <?php endif; ?>

  <?php // ---- Possible repeats ---- ?>
  <?php if (!empty($issue['repeats'])): ?>
    <div class="msg msg-warning" style="margin-top:12px"><strong>Possible repeat.</strong> Similar earlier issue(s) for this vendor / clause —
      review before treating as new:
      <?php foreach (array_slice($issue['repeats'],0,4) as $rp): ?>
        <a href="/ncr-item?id=<?= (int)$rp['id'] ?>"><?= e($rp['ref']) ?></a><?= ' ' ?>
      <?php endforeach; ?>
      <span class="muted">(a human confirms a repeat; nothing is auto-labelled)</span></div>
  <?php endif; ?>

  <?php // ---- Departures raised from this issue ---- ?>
  <div class="ctitle" style="margin-top:14px"><h3 style="font-size:14px">Deviations / concessions / waivers</h3></div>
  <?php if (!empty($issue['departures'])): ?>
    <table class="grid" style="margin-bottom:8px">
      <tr><th>Ref</th><th>Kind</th><th>Title</th><th>Status</th><th>Valid to</th></tr>
      <?php foreach ($issue['departures'] as $d): ?>
        <tr><td><b><?= e($d['ref']) ?></b></td><td><?= e($depKinds[$d['kind']] ?? $d['kind']) ?></td>
          <td><?= e($d['title']) ?></td>
          <td><span class="pill <?= $d['status']==='APPROVED'?'p-ok':($d['status']==='REJECTED'?'p-bad':'p-warn') ?>"><?= e($depStatuses[$d['status']] ?? $d['status']) ?></span></td>
          <td><?= e($fmt($d['valid_to'])) ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted">None raised from this issue.</p><?php endif; ?>
  <?php if ($issueCanEdit): ?>
    <details style="margin-bottom:10px"><summary>➕ Raise a controlled departure from this issue</summary>
      <form method="post" action="/departure-new" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;max-width:640px">
        <input type="hidden" name="ncr_id" value="<?= $nid ?>"><input type="hidden" name="client_id" value="<?= (int)($n['partner_id'] ?? 0) ?>">
        <label>Kind <select name="kind"><?php foreach ($depKinds as $kc=>$kl): ?><option value="<?= e($kc) ?>"><?= e($kl) ?></option><?php endforeach; ?></select></label>
        <label>Valid to <input type="date" name="valid_to"></label>
        <label style="grid-column:1/3">Title <input type="text" name="title" value="<?= e($n['title']) ?>" required></label>
        <label style="grid-column:1/3">Requirement <input type="text" name="requirement" value="<?= e($n['clause'] ?? '') ?>"></label>
        <label style="grid-column:1/3">Justification <textarea name="justification" rows="2"></textarea></label>
        <div style="grid-column:1/3"><button class="btn" type="submit">Create (Draft)</button></div>
      </form>
    </details>
  <?php endif; ?>

  <?php // ---- Dispute / response ---- ?>
  <div class="ctitle"><h3 style="font-size:14px">Dispute &amp; response</h3></div>
  <?php if (!empty($issue['disputes'])): ?>
    <table class="grid" style="margin-bottom:8px">
      <tr><th>By</th><th>Reason</th><th>Response</th><th>Decision</th></tr>
      <?php foreach ($issue['disputes'] as $d): ?>
        <tr><td><?= e($d['party']) ?></td><td><?= e($d['reason']) ?></td>
          <td><?= e($d['response']) ?: '—' ?></td>
          <td><?= $d['decision'] ? '<span class="pill '.($d['decision']==='UPHELD'?'p-ok':'p-mut').'">'.e($disputeStatus[$d['decision']] ?? $d['decision']).'</span>' : '<span class="muted">open</span>' ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted">No dispute raised. The original finding stands.</p><?php endif; ?>
  <?php if ($issueCanEdit): ?>
    <details style="margin-bottom:10px"><summary>➕ Record a dispute / response</summary>
      <form method="post" action="/dispute-new" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;max-width:640px">
        <input type="hidden" name="ncr_id" value="<?= $nid ?>">
        <label>Party <select name="party"><option value="VENDOR">Vendor</option><option value="CLIENT">Client</option></select></label>
        <label>Nature <select name="response_kind"><option value="DISAGREE">Disagreement</option><option value="PARTIAL">Partial acceptance</option><option value="CLARIFY">Clarification</option><option value="PROPOSE">Proposed action</option></select></label>
        <label style="grid-column:1/3">Reason / response <textarea name="reason" rows="2"></textarea></label>
        <div style="grid-column:1/3"><button class="btn secondary" type="submit">Record — the finding is retained</button></div>
      </form>
    </details>
  <?php endif; ?>

  <?php // ---- Due-date extension history ---- ?>
  <div class="ctitle"><h3 style="font-size:14px">Due-date extensions</h3></div>
  <?php if (!empty($issue['extensions'])): ?>
    <table class="grid" style="margin-bottom:8px">
      <tr><th>Original</th><th>New</th><th>Reason</th><th>Approved by</th><th>Status</th></tr>
      <?php foreach ($issue['extensions'] as $x): ?>
        <tr><td><?= e($fmt($x['original_due'])) ?></td><td><b><?= e($fmt($x['new_due'] ?: $x['requested_due'])) ?></b></td>
          <td><?= e($x['reason']) ?: '—' ?></td><td><?= e($x['approved_by']) ?: '—' ?></td>
          <td><span class="pill <?= $x['status']==='APPROVED'?'p-ok':'p-warn' ?>"><?= e($x['status']) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted">No extension requested. The original due date stands.</p><?php endif; ?>
  <?php if ($issueCanEdit && !$closed): ?>
    <details><summary>➕ Request a due-date extension</summary>
      <form method="post" action="/issue-extend" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:8px">
        <input type="hidden" name="ncr_id" value="<?= $nid ?>"><input type="hidden" name="entity" value="NCR"><input type="hidden" name="entity_id" value="<?= $nid ?>">
        <input type="hidden" name="original_due" value="<?= e($n['due_on'] ?? '') ?>">
        <label>New date <input type="date" name="requested_due" required></label>
        <label style="flex:1;min-width:180px">Reason <input type="text" name="reason"></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="approve"> approve now</label>
        <button class="btn secondary" type="submit">Request</button>
        <span class="muted" style="width:100%;font-size:12px">The original date is always kept in the history.</span>
      </form>
    </details>
  <?php endif; ?>
</div>
