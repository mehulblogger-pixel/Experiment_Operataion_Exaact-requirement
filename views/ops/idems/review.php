<?php
  // Document review — source documents, rule-based conflict checks, optional AI review.
  $sevPill = ['high'=>'p-bad','medium'=>'p-warn','low'=>'p-mut'];
  $byType = [];
  foreach ($srcDocs as $s) $byType[$s['doc_type']][] = $s;
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <a href="/document?id=<?= (int)$doc['id'] ?>"><?= e($doc['irn']) ?></a> › Document review</div>
<div class="master-head">
  <div><h1><?= e(TH('report')) ?> review</h1>
    <p class="sub" style="margin:2px 0 0">Upload the PO, QAP, drawings, specifications and certificates for this report. The system cross-checks them automatically; AI adds a deeper review when enabled. <strong>You remain the approving authority.</strong></p></div>
  <a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">← Back to report</a>
</div>

<?php
  // ---- Where each expected document stands ---------------------------------
  // Uploading is only one of the answers. "Not applicable to this scope" and
  // "the vendor has not given it to us" are decisions the reviewer is entitled
  // to record, and the automatic checks stop nagging about anything marked
  // either way — which is what keeps the checks worth reading.
  $tone = ['RECEIVED'=>'p-ok','RECEIVED_OBS'=>'p-warn','NOT_APPLICABLE'=>'p-mut',
           'NOT_AVAILABLE'=>'p-bad','AWAITED'=>'p-warn','PENDING'=>'p-mut'];
  $rowTypes = $expected ?: array_keys($types);
  foreach (array_keys($byType) as $t) if (!in_array($t, $rowTypes, true)) $rowTypes[] = $t;
  $editable = idems_can_edit_doc($doc);
?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Where each document stands</h3></div>
  <p class="sub" style="margin:0 0 10px">Mark every expected document. Anything set to
    <b>not applicable</b> or <b>not available</b> is left out of the automatic checks.</p>
  <form method="post" action="/document-review?id=<?= (int)$doc['id'] ?>">
    <input type="hidden" name="_do" value="state">
    <div style="overflow-x:auto">
    <table class="dt">
      <thead><tr><th>Document</th><th style="min-width:210px">Status</th><th style="min-width:230px">Note</th><th>Files</th></tr></thead>
      <tbody>
      <?php foreach ($rowTypes as $t):
            $r = $review[$t] ?? null; $st = $r['state'] ?? (isset($byType[$t]) ? 'RECEIVED' : 'PENDING'); ?>
        <tr>
          <td><b><?= e(lk_options_or('source_doc_type', SOURCE_DOC_TYPES)[$t] ?? $t) ?></b>
              <?php if ($r && !empty($r['reviewed_by'])): ?>
                <div class="muted" style="font-size:11px"><?= e($r['reviewed_by']) ?><?= !empty($r['reviewed_at']) ? ' · ' . e(fdate(substr((string)$r['reviewed_at'],0,10))) : '' ?></div>
              <?php endif; ?></td>
          <td><?php if ($editable): ?>
                <select class="form-control" name="st[<?= e($t) ?>]">
                  <?php foreach ($reviewStates as $k=>$v): ?>
                    <option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <span class="pill <?= e($tone[$st] ?? 'p-mut') ?>"><?= e($reviewStates[$st] ?? $st) ?></span>
              <?php endif; ?></td>
          <td><?php if ($editable): ?>
                <input class="form-control" name="nt[<?= e($t) ?>]" value="<?= e($r['note'] ?? '') ?>" placeholder="why, or what is awaited">
              <?php else: ?><?= e($r['note'] ?? '—') ?><?php endif; ?></td>
          <td class="num"><?= isset($byType[$t]) ? '<span class="pill p-ok">' . count($byType[$t]) . '</span>' : '<span class="muted">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php if ($editable): ?><div style="margin-top:10px"><button class="btn small" type="submit">Save the review</button></div><?php endif; ?>
  </form>
</div>

<div class="dash-2col" style="align-items:start">
  <div>
    <!-- ===== conflict report ===== -->
    <div class="panel">
      <div class="ctitle" style="margin-top:0"><h3>Automatic checks <span class="muted">(<?= count($checks) ?>)</span></h3></div>
      <?php if (!$checks): ?>
        <p style="color:var(--ok)"><b>✓ No issues detected</b> — the required documents are attached and the header references are consistent.</p>
      <?php else: ?>
        <?php foreach ($checks as $c): ?>
          <div style="padding:7px 0;border-bottom:1px solid var(--line)">
            <span class="pill <?= $sevPill[$c['severity']] ?? 'p-mut' ?>"><?= e(ucfirst($c['severity'])) ?></span>
            <strong style="font-size:12px"><?= e($c['kind']) ?></strong>
            <div class="muted" style="font-size:12px"><?= e($c['detail']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <p class="muted" style="font-size:11px;margin-top:8px">These checks run without AI. They are advisory — confirm against the actual documents.</p>
    </div>

    <!-- ===== AI review ===== -->
    <div class="panel">
      <div class="ctitle" style="margin-top:0"><h3>AI-assisted review</h3>
        <?php if ($aiOn): ?>
          <form method="post" action="/document-review?id=<?= (int)$doc['id'] ?>" style="display:inline"><input type="hidden" name="_do" value="ai"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="btn small" type="submit">🤖 Run AI review</button></form>
        <?php endif; ?>
      </div>
      <?php if (!$aiOn): ?>
        <p class="muted">No AI provider is enabled. Add a key under <a href="/ai-settings">Settings → AI providers</a> to enable the deeper review. The automatic checks above work regardless.</p>
      <?php elseif (!$aiText): ?>
        <p class="muted">Click <strong>Run AI review</strong> to have the assistant read the attached documents and flag missing records, revision or specification conflicts, and traceability gaps — and suggest hold/witness points and draft remarks.</p>
      <?php else: ?>
        <?php foreach ($aiSections as $h=>$body): ?>
          <div style="margin-bottom:10px">
            <div style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand)"><?= e($h) ?></div>
            <div style="font-size:12px;white-space:pre-wrap"><?= e($body) ?></div>
          </div>
        <?php endforeach; ?>
        <p class="muted" style="font-size:11px;border-top:1px solid var(--line);padding-top:6px">AI suggestions are advisory only. Verify every point against the actual documents before acting — the inspector's technical judgement and approval are final.</p>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <!-- ===== upload ===== -->
    <?php if (idems_can_edit_doc($doc)): ?>
    <form method="post" action="/document-review?id=<?= (int)$doc['id'] ?>" enctype="multipart/form-data" class="panel">
      <input type="hidden" name="_do" value="upload"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
      <h3 class="tab-sub" style="margin-top:0">Add source documents</h3>
      <div class="ff"><label>Document type</label>
        <select class="form-control searchable" name="doc_type"><?php foreach ($types as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>File(s)</label><input class="form-control" type="file" name="src[]" multiple></div>
      <div class="ff"><label>Note (optional)</label><input class="form-control" name="note"></div>
      <div style="margin-top:10px"><button class="btn" type="submit">Upload</button></div>
    </form>
    <?php endif; ?>

    <!-- ===== attached documents ===== -->
    <div class="panel">
      <div class="ctitle" style="margin-top:0"><h3>Attached documents <span class="muted">(<?= count($srcDocs) ?>)</span></h3></div>
      <?php if (!$srcDocs): ?><p class="muted">Nothing attached yet.</p><?php endif; ?>
      <?php foreach ($types as $tk=>$tl): if (empty($byType[$tk])) continue; ?>
        <div style="margin-bottom:8px">
          <div style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand)"><?= e($tl) ?></div>
          <?php foreach ($byType[$tk] as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;padding:3px 0">
              <a class="pill p-info" href="/report-file?id=<?= (int)$s['id'] ?>" target="_blank">📎 <?= e($s['file_name']) ?></a>
              <?php if (idems_can_edit_doc($doc)): ?>
              <form method="post" action="/document-review?id=<?= (int)$doc['id'] ?>" style="margin:0" onsubmit="return confirm('Remove this document?')">
                <input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><input type="hidden" name="file_id" value="<?= (int)$s['id'] ?>">
                <button class="btn small secondary" type="submit">✕</button></form>
              <?php endif; ?>
            </div>
            <?php if ($s['note']): ?><div class="muted" style="font-size:11px;margin-left:4px"><?= e($s['note']) ?></div><?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
