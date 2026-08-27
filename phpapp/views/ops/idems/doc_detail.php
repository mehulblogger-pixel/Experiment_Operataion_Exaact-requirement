<?php // Report instance detail — Phase 1 (header, references, lifecycle actions, audit) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <?= e($doc['irn']) ?></div>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('report', $doc['irn'])) ?> <?= $doc['finalized'] ? '🔒' : '' ?></h1>
    <p class="sub" style="margin:2px 0 0"><span class="pill p-info"><?= e($doc['type_code']) ?></span> <?= e($doc['type_name'] ?: $doc['title']) ?> · <span class="pill <?= idems_status_pill($doc['status']) ?>"><?= e(lk_options_or('report_status', IDEMS_STATUS)[$doc['status']] ?? $doc['status']) ?></span></p></div>
  <?php $canSubmit = idems_can_edit_doc($doc) && in_array($doc['status'],['DRAFT','REJECTED'],true);
        $comp = $canSubmit ? idems_completeness_check($doc) : null; ?>
  <?php
    // Work out the workflow state up front so the bar can show ONE clear next step.
    $appr = $approvals ?? [];
    $anyApproved = false; $anyPending = false;
    foreach ($appr as $a) {
      if (($a['status'] ?? '') === 'APPROVED') $anyApproved = true;
      if (in_array($a['status'] ?? '', ['PENDING', 'SENTBACK'], true)) $anyPending = true;
    }
    $canFinalize = $anyApproved && !$anyPending;
    $whyNot = !$appr ? 'No approver has been asked yet — submit it for approval first.'
            : ($anyPending ? 'Still waiting on an approver.' : 'Not approved.');
    $yourFmt = function_exists('idems_pick_template') ? idems_pick_template($doc) : null;
    $fmtLabel = $yourFmt ? ((int)($yourFmt['client_id'] ?? 0) ? 'client format' : 'company format') : '';
  ?>
  <style>
    .doc-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .more-menu{position:relative}
    .more-menu>summary{list-style:none;cursor:pointer}
    .more-menu>summary::-webkit-details-marker{display:none}
    .more-pop{position:absolute;right:0;top:calc(100% + 4px);z-index:30;background:var(--card,#fff);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:6px;min-width:210px;display:flex;flex-direction:column;gap:2px}
    .more-pop a,.more-pop button{display:block;width:100%;text-align:left;background:none;border:0;padding:8px 10px;border-radius:7px;font:inherit;color:var(--ink);cursor:pointer;text-decoration:none}
    .more-pop a:hover,.more-pop button:hover{background:color-mix(in srgb,var(--accent,#2b6cff) 8%,transparent)}
    .more-pop hr{border:0;border-top:1px solid var(--line);margin:4px 2px}
    .more-pop .mp-head{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;padding:6px 10px 2px}
  </style>
  <div class="doc-actions">
    <?php // ---- PRIMARY: the single next step ---- ?>
    <?php if (idems_can_edit_doc($doc) && !empty($hasSchema)): ?><a class="btn" href="/document-fill?id=<?= (int)$doc['id'] ?>">✍ Fill report</a><?php endif; ?>
    <?php if (idems_can_edit_doc($doc)): ?><a class="btn secondary" href="/document-edit?id=<?= (int)$doc['id'] ?>" title="Client, vendor, PO, applicable standards, result & release status">✎ Edit details</a><?php endif; ?>
    <?php if ((is_master() || can('idems.type.manage')) && empty($hasSchema)): ?><a class="btn secondary" href="/report-builder?type=<?= (int)$doc['report_type_id'] ?>">Design this form</a><?php endif; ?>
    <?php if ($canSubmit && $comp['ok']): ?>
      <form method="post" action="/document-submit?id=<?= (int)$doc['id'] ?>" style="display:inline"><button class="btn" type="submit">✓ Submit for review</button></form>
    <?php elseif ($canSubmit): ?>
      <a class="btn secondary" href="#completeness" title="Some completeness checks are failing" style="border-color:var(--bad);color:var(--bad)">⚠ Completeness <?= (int)$comp['passed'] ?>/<?= (int)$comp['applicable'] ?></a>
    <?php endif; ?>
    <?php if (!$doc['finalized'] && (is_master() || can('idems.finalize'))): ?>
      <?php if ($canFinalize): ?>
      <form method="post" action="/document-finalize?id=<?= (int)$doc['id'] ?>" style="display:inline" onsubmit="return confirm('Finalize &amp; issue this report? It becomes permanently locked (immutable).')"><button class="btn" type="submit">Finalize &amp; issue</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <?php // ---- ONE download button. Before a report is issued this IS the draft
          //   copy (printed watermarked DRAFT), so the inspector can pull it, read it
          //   over, and only then submit for review — Field #16. After issue it is the
          //   final copy. Labelled so pre-submit it plainly says "draft". ?>
    <?php $isDraftDl = empty($doc['finalized']);
          $dlTitle = $isDraftDl ? 'Download a draft copy to read over before submitting it for review' : 'Download the issued report'; ?>
    <?php if ($yourFmt): ?>
      <a class="btn secondary" href="/document-docx?id=<?= (int)$doc['id'] ?>" title="<?= e($dlTitle) ?>">📄 Download <?= $isDraftDl ? 'draft' : 'report' ?></a>
    <?php else: ?>
      <a class="btn secondary" href="/document-pdf?id=<?= (int)$doc['id'] ?>" target="_blank" title="<?= e($dlTitle) ?>">📄 Download <?= $isDraftDl ? 'draft PDF' : 'PDF' ?></a>
    <?php endif; ?>
    <?php // ---- Everything else lives here ---- ?>
    <details class="more-menu">
      <summary class="btn secondary">More ▾</summary>
      <div class="more-pop">
        <?php if (!$doc['finalized'] && (is_master() || can('idems.finalize')) && !$canFinalize): ?>
          <span class="mp-head" title="<?= e($whyNot) ?>">Finalize: <?= e($whyNot) ?></span>
        <?php endif; ?>
        <div class="mp-head">Downloads</div>
        <?php if ($yourFmt): ?>
          <a href="/document-docx?id=<?= (int)$doc['id'] ?>">📄 Your format (<?= e($fmtLabel) ?>)</a>
          <a href="/document-pdf?id=<?= (int)$doc['id'] ?>" target="_blank">📄 Plain PDF</a>
        <?php else: ?>
          <a href="/document-docx?id=<?= (int)$doc['id'] ?>">📝 Word (.docx)</a>
          <a href="/document-pdf?id=<?= (int)$doc['id'] ?>" target="_blank">📄 PDF<?= empty($doc['finalized']) ? ' (draft)' : '' ?></a>
        <?php endif; ?>
        <?php if (!empty($doc['finalized']) && !$yourFmt): ?>
          <a href="/document-pdf?id=<?= (int)$doc['id'] ?>&copy=duplicate" target="_blank">📄 Duplicate copy</a>
          <a href="/document-pdf?id=<?= (int)$doc['id'] ?>&copy=triplicate" target="_blank">📄 Triplicate copy</a>
        <?php endif; ?>
        <hr>
        <div class="mp-head">Tools</div>
        <a href="/document-evidence?id=<?= (int)$doc['id'] ?>">🖼 Evidence &amp; photos</a>
        <a href="/document-review?id=<?= (int)$doc['id'] ?>">🔍 Document review</a>
        <?php if (!empty($hasSchema)): ?><a href="/document-smart?id=<?= (int)$doc['id'] ?>">💡 Suggested remarks</a><?php endif; ?>
        <?php if (in_array($doc['status'], ['APPROVED','ISSUED'], true) && $doc['type_code'] !== 'RN' && (is_master() || can('mod.idems.edit'))): ?>
          <form method="post" action="/document-release-note"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button type="submit">📋 Draft Release Note</button></form>
        <?php endif; ?>
        <?php if (!empty($doc['finalized']) && empty($doc['revised_by_id']) && (is_master() || can('mod.idems.edit'))): ?>
          <hr>
          <form method="post" action="/document-revise" onsubmit="return confirm('Create Rev <?= (int)($doc['rev'] ?? 0) + 1 ?> of this report? The original stays on file, unchanged.')"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button type="submit">♻️ Reissue as new revision</button></form>
        <?php endif; ?>
      </div>
    </details>
  </div>
</div>

<?php // Guided path from a blank report to an issued, signed PDF — one clear
      // "do this now" step, with the automatic signature made a visible step. ?>
<?php if (function_exists('idems_render_report_playbook')) idems_render_report_playbook($doc, $approvals ?? [], $hasSchema ?? false); ?>

<?php
  // ---- Release Note decision: the inspector marks whether an IRN / Release Note
  // is to be issued for this report. Until ticked, no Release Note can be raised. ----
  $isReleaseType = in_array(strtoupper((string)($doc['type_code'] ?? '')), ['RN','IRN'], true);
  if (!$isReleaseType && (is_master() || can('mod.idems.edit'))):
    $rnOn = !empty($doc['rn_to_issue']);
?>
<div class="panel" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;border:1px solid <?= $rnOn ? 'var(--ok)' : 'var(--line,#e5e7eb)' ?>">
  <form method="post" action="/document-rn-flag" style="margin:0;display:flex;gap:9px;align-items:center">
    <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
    <input type="hidden" name="rn_to_issue" value="<?= $rnOn ? '0' : '1' ?>">
    <button class="btn small <?= $rnOn ? 'secondary' : '' ?>" type="submit"><?= $rnOn ? '☑ Release Note: to be issued (click to unmark)' : '☐ Mark: Release Note to be issued' ?></button>
  </form>
  <span class="muted" style="font-size:12px"><?= $rnOn
    ? 'A Release Note will be raised from this report once it is issued and all hold / deviation points and client acceptance are cleared.'
    : 'Tick this if a Release Note / IRN is to be issued for this report. Until it is ticked, a Release Note cannot be generated.' ?></span>
</div>
<?php endif; ?>

<div data-tabs data-tabs-key="report" data-tabs-order="Report,Checks,Approvals,Audit">
<?php // ---- Scorecard — only for scored report types (vendor assessment, audit) ---- ?>
<?php if (!empty($scorecard) && $scorecard['overall'] !== null):
  $ov = (float)$scorecard['overall'];
  $bandCol = function($s){ $s=(float)$s; if($s>=90) return '#15803d'; if($s>=75) return '#2563eb'; if($s>=60) return '#b45309'; if($s>=40) return '#c2410c'; return 'var(--bad)'; };
  $oc = $bandCol($ov);
?>
<div class="panel" data-tab="Report" style="margin:0 0 12px;border:1px solid <?= $oc ?>;background:color-mix(in srgb,<?= $oc ?> 5%,transparent)">
  <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
    <div style="display:flex;flex-direction:column;align-items:center;min-width:96px">
      <div style="font-size:34px;font-weight:800;line-height:1;color:<?= $oc ?>;font-variant-numeric:tabular-nums"><?= rtrim(rtrim(number_format($ov,1),'0'),'.') ?><span style="font-size:15px;font-weight:600;color:var(--muted)">/100</span></div>
      <div style="font-size:12px;font-weight:700;letter-spacing:.03em;color:<?= $oc ?>;margin-top:3px"><?= e(strtoupper($scorecard['band'])) ?></div>
    </div>
    <div style="flex:1;min-width:240px">
      <div style="display:flex;justify-content:space-between;align-items:baseline"><b style="font-size:13.5px">Assessment scorecard</b><span class="muted" style="font-size:11.5px"><?= (int)$scorecard['answered'] ?>/<?= (int)$scorecard['total'] ?> criteria scored</span></div>
      <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
        <?php foreach ($scorecard['sections'] as $s): if ($s['score']===null) continue; $c=$bandCol($s['score']); ?>
          <div style="display:flex;align-items:center;gap:9px;font-size:12px">
            <span style="flex:0 0 200px;color:var(--ink)"><?= e($s['title']) ?></span>
            <span style="flex:1;height:8px;background:color-mix(in srgb,var(--line) 60%,transparent);border-radius:5px;overflow:hidden"><span style="display:block;height:100%;width:<?= max(2,(int)round($s['score'])) ?>%;background:<?= $c ?>"></span></span>
            <span style="flex:0 0 42px;text-align:right;font-variant-numeric:tabular-nums;color:<?= $c ?>;font-weight:600"><?= rtrim(rtrim(number_format($s['score'],1),'0'),'.') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <p class="muted" style="font-size:11px;margin:10px 0 0">Weighted score computed from the rated criteria. Unrated or "not applicable" criteria are excluded. It informs — it does not replace — the assessor's recommendation.</p>
</div>
<?php endif; ?>

<?php // ---- AI Report Auditor — automatic deterministic QA (traffic light) ---- ?>
<?php if (!empty($qa)):
  $st = $qa['status']; $sc = (int)$qa['score']; $cn = $qa['counts'];
  $tone  = $st==='BLOCKED' ? 'var(--bad)' : ($st==='REVIEW' ? '#b45309' : 'var(--ok)');
  $light = $st==='BLOCKED' ? '🔴' : ($st==='REVIEW' ? '🟡' : '🟢');
  $lab   = $st==='BLOCKED' ? 'Blocked' : ($st==='REVIEW' ? 'Review needed' : 'Ready');
  $sevColor = ['critical'=>'var(--bad)','high'=>'#c2410c','medium'=>'#b45309','low'=>'#6b7280','info'=>'#2563eb'];
  $sevLab   = ['critical'=>'CRITICAL','high'=>'HIGH','medium'=>'MEDIUM','low'=>'LOW','info'=>'INFO'];
  $openByDefault = ($cn['critical']>0 || $cn['high']>0 || $cn['medium']>0);
  $canFixDoc = function_exists('idems_can_edit_doc') ? idems_can_edit_doc($doc) : false;
  $card = function($it) use ($sevColor,$sevLab,$doc,$canFixDoc) {
    $sv = $it['severity']; $c = $sevColor[$sv] ?? '#6b7280';
    echo '<div style="border-left:3px solid '.$c.';background:color-mix(in srgb,'.$c.' 6%,transparent);padding:8px 11px;border-radius:8px">';
    echo '<div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap"><span style="font-size:10px;font-weight:700;letter-spacing:.04em;color:'.$c.'">'.($sevLab[$sv]??'').'</span><b style="font-size:13.5px">'.e($it['title']).'</b></div>';
    if (trim((string)$it['why'])!=='') echo '<div class="muted" style="font-size:12.5px;margin-top:3px">'.e($it['why']).'</div>';
    if (trim((string)$it['location'])!=='') echo '<div class="muted" style="font-size:11.5px;margin-top:2px">Where: '.e($it['location']).'</div>';
    if (trim((string)($it['suggestion']??''))!=='') echo '<div style="font-size:12px;margin-top:3px;color:var(--ink)">→ '.e($it['suggestion']).'</div>';
    // A direct link to the exact place this is fixed — so nobody has to hunt for it.
    if ($canFixDoc && function_exists('idems_qa_fix_link')) {
      $fx = idems_qa_fix_link($it, (int)$doc['id']);
      if (!empty($fx['href'])) echo '<div style="margin-top:6px"><a href="'.e($fx['href']).'" style="display:inline-block;font-size:11.5px;font-weight:700;color:'.$c.';border:1px solid '.$c.';border-radius:6px;padding:2px 9px;text-decoration:none">'.e($fx['label']).' →</a></div>';
    }
    echo '</div>';
  };
  $major = array_values(array_filter($qa['issues'], fn($i)=>in_array($i['severity'],['critical','high','medium'],true)));
  $minor = array_values(array_filter($qa['issues'], fn($i)=>in_array($i['severity'],['low','info'],true)));
?>
<details class="panel" data-tab="Checks" style="margin:0 0 12px;border:1px solid <?= $tone ?>" <?= $openByDefault?'open':'' ?>>
  <summary style="list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="font-size:18px"><?= $light ?></span>
    <b style="color:<?= $tone ?>">Report QA — <?= $lab ?></b>
    <span class="muted" style="font-size:12.5px">Score <?= $sc ?>/100 · runs automatically</span>
    <span style="flex:1"></span>
    <?php foreach (['critical','high','medium','low'] as $s): if (($cn[$s]??0)>0): ?>
      <span class="pill" style="background:color-mix(in srgb,<?= $sevColor[$s] ?> 15%,transparent);color:<?= $sevColor[$s] ?>"><?= (int)$cn[$s] ?> <?= ucfirst($s) ?></span>
    <?php endif; endforeach; ?>
  </summary>
  <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
    <?php if (!$qa['issues']): ?>
      <p class="muted" style="margin:0">✓ No issues found — this report passed the automatic checks.</p>
    <?php endif; ?>
    <?php if ($st==='BLOCKED'): ?>
      <p style="margin:0;font-size:12.5px;color:var(--bad)"><b>This report cannot be issued</b> until the critical issue(s) below are resolved or explained by an authorised user.</p>
    <?php endif; ?>
    <?php foreach ($major as $it) $card($it); ?>
    <?php if ($minor): ?>
      <details style="margin-top:2px"><summary style="cursor:pointer;font-size:12.5px" class="muted"><?= count($minor) ?> minor suggestion<?= count($minor)===1?'':'s' ?> (language &amp; style)</summary>
        <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px"><?php foreach ($minor as $it) $card($it); ?></div>
      </details>
    <?php endif; ?>
    <?php // ---- AI advisory (LLM) — optional, never part of the pass/fail ---- ?>
    <?php if (!empty($aiSections)): ?>
      <div style="margin-top:8px;border-top:1px dashed var(--line);padding-top:8px">
        <div style="font-size:12.5px;font-weight:600;color:var(--accent)">🤖 AI suggestions <span class="muted" style="font-weight:400">— advisory only, not part of the pass/fail</span></div>
        <?php foreach ($aiSections as $h => $body): $b = trim((string)$body); if ($b === '' || (stripos($b,'none identified')!==false && strlen($b) < 40)) continue; ?>
          <div style="margin-top:6px"><div style="font-size:11px;font-weight:700;letter-spacing:.03em;color:var(--muted)"><?= e(ucwords(strtolower($h))) ?></div>
            <div style="font-size:12.5px;white-space:pre-wrap;color:var(--ink)"><?= e($b) ?></div></div>
        <?php endforeach; ?>
        <?php if (idems_can_edit_doc($doc)): ?><form method="post" action="/document-ai-review" style="margin-top:6px"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="btn small secondary" type="submit">↻ Re-run AI review</button></form><?php endif; ?>
      </div>
    <?php elseif (!empty($aiOn) && idems_can_edit_doc($doc)): ?>
      <div style="margin-top:8px;border-top:1px dashed var(--line);padding-top:8px">
        <form method="post" action="/document-ai-review"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
          <button class="btn small secondary" type="submit">🤖 Run AI language &amp; consistency review</button>
          <span class="muted" style="font-size:11.5px">Optional — suggests wording and flags possible conflicts. Advisory only; it never changes the report.</span>
        </form>
      </div>
    <?php elseif (empty($aiOn) && is_master()): ?>
      <p class="muted" style="font-size:11px;margin-top:8px;border-top:1px dashed var(--line);padding-top:8px">Tip: enable an AI provider under <b>Settings → AI providers</b> to add optional AI language &amp; consistency suggestions here (advisory only — the checks above always run without it).</p>
    <?php endif; ?>
    <?php if (idems_can_edit_doc($doc) && !empty($hasSchema)): ?>
      <div style="margin-top:8px"><a class="btn small" href="/document-fill?id=<?= (int)$doc['id'] ?>">✍ Open report to fix</a> <a class="btn small secondary" href="/document-edit?id=<?= (int)$doc['id'] ?>">✎ Edit details</a></div>
    <?php endif; ?>
    <?php if ($st === 'BLOCKED' && empty($doc['finalized']) && (is_master() || can('idems.finalize'))): ?>
      <?php if (is_master()): ?>
        <form method="post" action="/document-finalize?id=<?= (int)$doc['id'] ?>" style="margin-top:6px;border-top:1px dashed var(--line);padding-top:8px" onsubmit="return confirm('Override the critical issue(s) and issue this report? This is recorded in the audit trail.')">
          <label style="font-size:12px;font-weight:600;color:var(--bad)">Administrator override — reason required to issue despite the critical issue</label>
          <textarea name="qa_override_reason" class="form-control" rows="2" required placeholder="Explain why this report may be issued despite the critical issue" style="margin-top:4px;width:100%"></textarea>
          <button class="btn small" type="submit" style="margin-top:6px;background:var(--bad);border-color:var(--bad)">Issue anyway (override &amp; log)</button>
        </form>
      <?php else: ?>
        <p style="font-size:12px;color:var(--bad);margin:6px 0 0">This report is blocked from issue until the critical issue is resolved. An administrator can override it with a recorded reason.</p>
      <?php endif; ?>
    <?php endif; ?>
    <p class="muted" style="font-size:11px;margin:2px 0 0">Automatic quality check. It flags possible issues for your review — it never changes the report or its technical findings.</p>
  </div>
</details>
<?php endif; ?>

<?php // ---- Release readiness gate (URADE §10/§55) — evidence-based, advisory ---- ?>
<?php if (!empty($relEligibility)):
  $rs = $relEligibility['status'];
  $gc = $rs==='BLOCKED' ? 'var(--bad)' : ($rs==='CONDITIONAL' ? '#b45309' : 'var(--ok)');
  $glabel = $rs==='BLOCKED' ? 'Blocked' : ($rs==='CONDITIONAL' ? 'Conditional' : 'Ready');
  $glight = $rs==='BLOCKED' ? '🔴' : ($rs==='CONDITIONAL' ? '🟡' : '🟢');
  $stCol = ['PASS'=>'var(--ok)','BLOCKED'=>'var(--bad)','CONDITIONAL'=>'#b45309','FAIL'=>'#c2410c','NA'=>'var(--muted)'];
?>
<details class="panel" data-tab="Checks" style="margin:0 0 12px;border:1px solid <?= $gc ?>" <?= $rs!=='READY'?'open':'' ?>>
  <summary style="list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="font-size:18px"><?= $glight ?></span>
    <b style="color:<?= $gc ?>">Release readiness — <?= e($glabel) ?></b>
    <span class="muted" style="font-size:12.5px">evidence-based gate · advisory · authority decides</span>
    <span style="flex:1"></span>
    <?php if (($relEligibility['inspection_count']??0)>0): ?><span class="muted" style="font-size:11.5px">from <?= (int)$relEligibility['inspection_count'] ?> inspection(s)</span><?php endif; ?>
  </summary>
  <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px">
    <?php if ($rs==='BLOCKED'): ?><p style="margin:0;font-size:12.5px;color:var(--bad)"><b>This release cannot proceed</b> until the blocking condition(s) below are resolved, or an authorized exception is recorded.</p>
    <?php elseif ($rs==='CONDITIONAL'): ?><p style="margin:0;font-size:12.5px;color:#b45309">A <b>conditional release</b> is possible — record the condition(s) and obtain approval.</p>
    <?php else: ?><p style="margin:0;font-size:12.5px;color:var(--ok)">All mandatory release conditions are satisfied.</p><?php endif; ?>
    <?php foreach ($relEligibility['conditions_list'] as $c): if (($c['result']??'')==='NA') continue; $cc=$stCol[$c['state']]??'var(--muted)'; ?>
      <div style="display:flex;gap:9px;align-items:baseline">
        <span class="pill" style="background:color-mix(in srgb,<?= $cc ?> 14%,transparent);color:<?= $cc ?>;min-width:80px;text-align:center;font-size:10px"><?= e(ucfirst(strtolower($c['state']))) ?></span>
        <span style="font-size:12.5px"><b><?= e($c['label']) ?></b><?= !empty($c['reason'])?' <span class="muted">— '.e($c['reason']).'</span>':'' ?></span>
      </div>
    <?php endforeach; ?>
    <p class="muted" style="font-size:11px;margin:2px 0 0">Computed from the linked inspection(s) — result, accepted quantity, tests, documents and open NCRs — against the configurable release rules. It never releases or changes a value; an authorized person makes the decision.</p>
  </div>
</details>
<?php endif; ?>

<?php // ---- Predictive delivery risk (Phase 6) — deterministic, advisory only ---- ?>
<?php if (!empty($expRisk)):
  $rb = $expRisk['band'];
  $rc = $rb==='CRITICAL' ? 'var(--bad)' : ($rb==='HIGH' ? '#c2410c' : ($rb==='MEDIUM' ? '#b45309' : 'var(--ok)'));
  $rtone = ['bad'=>'var(--bad)','warn'=>'#b45309','ok'=>'var(--ok)','neutral'=>'#2563eb'];
  $rOpen = in_array($rb, ['HIGH','CRITICAL'], true);
  $rec = $expRisk['recovery'];
?>
<details class="panel" data-tab="Checks" style="margin:0 0 12px;border:1px solid <?= $rc ?>" <?= $rOpen?'open':'' ?>>
  <summary style="list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="font-size:18px">📡</span>
    <b style="color:<?= $rc ?>">Delivery risk — <?= e(ucfirst(strtolower($rb))) ?></b>
    <span class="muted" style="font-size:12.5px">predictive · advisory only</span>
    <span style="flex:1"></span>
    <span class="pill" style="background:color-mix(in srgb,<?= $rc ?> 15%,transparent);color:<?= $rc ?>;font-variant-numeric:tabular-nums"><?= (int)$expRisk['score'] ?>/100</span>
  </summary>
  <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
    <?php if (!empty($rec['note'])): ?>
      <div style="border-left:3px solid <?= $rc ?>;background:color-mix(in srgb,<?= $rc ?> 6%,transparent);padding:8px 11px;border-radius:8px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.03em;color:<?= $rc ?>">RECOVERY READ<?php if (($rec['gap_days']??0)>0): ?> — <?= (int)$rec['gap_days'] ?> day(s) late<?php if($rec['available_days']!==null): ?>, ~<?= (int)$rec['available_days'] ?> day(s) lead remaining<?php endif; ?><?php endif; ?></div>
        <div style="font-size:12.5px;margin-top:2px;color:var(--ink)"><?= e($rec['note']) ?></div>
      </div>
    <?php endif; ?>
    <?php if (!empty($expRisk['factors'])): ?>
      <div style="font-size:11px;font-weight:700;letter-spacing:.03em;color:var(--muted)">RISK DRIVERS (weighted, heaviest first)</div>
      <div style="display:flex;flex-direction:column;gap:5px">
      <?php foreach ($expRisk['factors'] as $f): $fc2 = $rtone[$f['tone']??'neutral'] ?? '#2563eb'; ?>
        <div style="display:flex;gap:9px;align-items:baseline">
          <span class="pill" style="background:color-mix(in srgb,<?= $fc2 ?> 14%,transparent);color:<?= $fc2 ?>;min-width:34px;text-align:center;font-variant-numeric:tabular-nums">+<?= (int)$f['points'] ?></span>
          <span style="font-size:12.5px"><b><?= e($f['label']) ?></b> <span class="muted">— <?= e($f['detail']) ?></span></span>
        </div>
      <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted" style="margin:0;font-size:12.5px">✓ No material delivery-risk drivers detected on this report.</p>
    <?php endif; ?>
    <p class="muted" style="font-size:11px;margin:2px 0 0">Predicted from this report's forecast variance, milestones, NCRs, dispatch blockers, capacity/sub-supplier signals and the vendor's track record. Advisory — it never gates issue or changes a value.</p>
  </div>
</details>
<?php endif; ?>

<?php // ---- Expediting advisory (Phase 5) — deterministic, advisory only ---- ?>
<?php if (!empty($expAdvisory) && (trim((string)$expAdvisory['summary'])!=='' || !empty($expAdvisory['items']))):
  $advTone = ['bad'=>'var(--bad)','warn'=>'#b45309','ok'=>'var(--ok)','neutral'=>'#2563eb'];
  $advOpen = false; foreach ($expAdvisory['items'] as $it) if (in_array($it['tone']??'', ['bad','warn'], true)) { $advOpen = true; break; }
?>
<details class="panel" data-tab="Checks" style="margin:0 0 12px;border:1px solid #6366f1" <?= $advOpen?'open':'' ?>>
  <summary style="list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="font-size:18px">🧭</span>
    <b style="color:#4f46e5">Expediting advisory</b>
    <span class="muted" style="font-size:12.5px">draft narrative — advisory only, never changes the report</span>
  </summary>
  <div style="margin-top:10px;display:flex;flex-direction:column;gap:9px">
    <?php if (trim((string)$expAdvisory['summary'])!==''): ?>
      <div style="border-left:3px solid #6366f1;background:color-mix(in srgb,#6366f1 6%,transparent);padding:9px 12px;border-radius:8px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.03em;color:#4f46e5">DRAFT EXECUTIVE SUMMARY</div>
        <div style="font-size:13px;margin-top:3px;color:var(--ink)" id="advSummary"><?= e($expAdvisory['summary']) ?></div>
        <button type="button" class="btn small secondary" style="margin-top:6px" onclick="(function(b){var t=document.getElementById('advSummary').innerText;navigator.clipboard&&navigator.clipboard.writeText(t);b.textContent='Copied ✓';setTimeout(function(){b.textContent='Copy summary';},1500);})(this)">Copy summary</button>
      </div>
    <?php endif; ?>
    <?php foreach ($expAdvisory['items'] as $it): $c = $advTone[$it['tone']??'neutral'] ?? '#2563eb'; ?>
      <div style="border-left:3px solid <?= $c ?>;background:color-mix(in srgb,<?= $c ?> 6%,transparent);padding:8px 11px;border-radius:8px">
        <div style="font-size:13px;font-weight:600;color:<?= $c ?>"><?= e($it['title']) ?></div>
        <div class="muted" style="font-size:12.5px;margin-top:2px;color:var(--ink)"><?= e($it['text']) ?></div>
      </div>
    <?php endforeach; ?>
    <p class="muted" style="font-size:11px;margin:2px 0 0">Composed from this report's own milestones, forecast dates, delay &amp; NCR registers and the previous report — every line is reproducible. Read it, then adopt or edit the wording; it is never inserted automatically.</p>
  </div>
</details>
<?php endif; ?>

<?php // ---- Revision lineage ------------------------------------------------ ?>
<?php if (!empty($doc['revises_id']) || !empty($doc['revised_by_id']) || (int)($doc['rev'] ?? 0) > 0): ?>
<div class="panel" data-tab="Report" style="padding:10px 14px;margin:0 0 12px;font-size:13.5px">
  <?php if ((int)($doc['rev'] ?? 0) > 0): ?><b>Revision <?= (int)$doc['rev'] ?></b><?php endif; ?>
  <?php if (!empty($doc['revises_id'])): $pv = ops_one("SELECT id, irn FROM report_docs WHERE id=?", [(int)$doc['revises_id']]); if ($pv): ?>
    · revises <a href="/document?id=<?= (int)$pv['id'] ?>"><?= e($pv['irn']) ?></a><?php endif; endif; ?>
  <?php if (!empty($doc['revised_by_id'])): $nx = ops_one("SELECT id, irn FROM report_docs WHERE id=?", [(int)$doc['revised_by_id']]); if ($nx): ?>
    · superseded by <a href="/document?id=<?= (int)$nx['id'] ?>"><?= e($nx['irn']) ?></a> — this is no longer the current revision<?php endif; endif; ?>
</div>
<?php endif; ?>

<?php // ---- Inspection Completeness Check (pre-submission gate) ------------- ?>
<?php if ($comp !== null): ?>
<div class="panel" id="completeness" data-tab="Checks" style="margin:0 0 12px;border:1px solid <?= $comp['ok']?'var(--ok)':'var(--bad)' ?>">
  <div class="ctitle" style="margin-top:0"><h3>Inspection completeness check
    <span class="pill <?= $comp['ok']?'p-ok':'p-bad' ?>" style="margin-left:6px"><?= (int)$comp['passed'] ?>/<?= (int)$comp['applicable'] ?> passed</span>
    <?php if ($comp['na']): ?><span class="muted" style="font-size:12px">· <?= (int)$comp['na'] ?> n/a</span><?php endif; ?></h3></div>
  <p class="muted" style="margin:0 0 10px">Every applicable check must pass before the report can be submitted for review. Identity, PO, location and specification flow in from the <?= e(Tl('call')) ?> &amp; <?= e(Tl('job')) ?> — fill only what is genuinely new.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:4px 16px">
    <?php
      // A failing check that has a known place to fix it gets a deep link, so
      // "✕ No inspector signature" becomes a task you can finish in one click
      // rather than a dead end.
      $fixFor = function($key) use ($doc) {
        switch ($key) {
          case 'signature':   return ['/my-signature', 'Add my signature'];
          case 'declaration':
          case 'attachments': return ['/document-fill?id=' . (int)$doc['id'], 'Open report'];
          default: return null;
        }
      };
      foreach ($comp['checks'] as $c):
      $pill = $c['status']==='PASS' ? '✓' : ($c['status']==='FAIL' ? '✕' : '–');
      $col  = $c['status']==='PASS' ? 'var(--ok)' : ($c['status']==='FAIL' ? 'var(--bad)' : 'var(--muted)');
      $fix  = $c['status']==='FAIL' ? $fixFor($c['key'] ?? '') : null; ?>
      <div style="display:flex;align-items:baseline;gap:7px;padding:3px 0;font-size:13.5px">
        <span style="color:<?= $col ?>;font-weight:800;width:12px;flex:none"><?= $pill ?></span>
        <span><?= e($c['label']) ?><?php if ($c['status']!=='PASS' && $c['detail']): ?> <span class="muted" style="font-size:11.5px">— <?= e($c['detail']) ?></span><?php endif; ?><?php if ($fix): ?> <a href="<?= e($fix[0]) ?>" style="font-size:11.5px;font-weight:700;white-space:nowrap"><?= e($fix[1]) ?> →</a><?php endif; ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($comp['ok']): ?>
    <form method="post" action="/document-submit?id=<?= (int)$doc['id'] ?>" style="margin-top:12px">
      <button class="btn" type="submit">✓ <?= (int)$comp['passed'] ?>/<?= (int)$comp['applicable'] ?> checks passed — SUBMIT REPORT</button>
    </form>
  <?php else: ?>
    <div class="msg-warning" style="margin-top:12px"><strong><?= (int)$comp['failed'] ?> check(s) failing.</strong> Resolve them in <a href="/document-fill?id=<?= (int)$doc['id'] ?>">the report body</a> or <a href="/document-edit?id=<?= (int)$doc['id'] ?>">the header</a>, then this gate opens.</div>
    <?php if (is_master()): ?>
      <details style="margin-top:8px"><summary class="btn small secondary">Super-admin: override the gate</summary>
        <form method="post" action="/document-submit?id=<?= (int)$doc['id'] ?>" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center" onsubmit="return confirm('Override the completeness gate and submit anyway?')">
          <input type="hidden" name="_force_submit" value="1">
          <input class="form-control" name="override_reason" placeholder="Reason (recorded in the audit trail)" required style="min-width:280px">
          <button class="btn small" type="submit">Override &amp; submit</button>
        </form>
      </details>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- Where this report stands, and the one next thing --------------- ?>
<?php
  $rSt   = $doc['status'] ?? 'DRAFT';
  $rFin  = !empty($doc['finalized']);
  $rBody = !empty($hasSchema);
  $rCanEdit = idems_can_edit_doc($doc);
?>
<?php // ---- Module 07: return-reason banner (prominent, for the preparer) ---- ?>
<?php $m7ret = in_array($rSt, ['DRAFT','REJECTED'], true) ? idems_latest_return($doc) : null; ?>
<?php if ($m7ret): ?>
  <div class="panel" data-tab="Report" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 8%,transparent)">
    <b style="color:var(--bad)">↩ Returned for correction</b> —
    <?= e(['reject'=>'rejected', 'vetting'=>'returned at vetting', 'sendback'=>'sent back by the approver'][$m7ret['kind']] ?? 'returned') ?>
    by <b><?= e($m7ret['by'] ?: '—') ?></b><?= $m7ret['at'] ? ' on ' . e(date('d M Y', strtotime($m7ret['at']))) : '' ?>.
    <?php // Phase 2 §9 — the structured correction detail: which section/field, and by when. ?>
    <?php if (trim((string)($m7ret['area'] ?? '')) !== ''): ?><div style="margin-top:6px">What to correct: <b><?= e($m7ret['area']) ?></b></div><?php endif; ?>
    <div style="margin-top:6px">Reason: <b><?= e($m7ret['reason'] !== '' ? $m7ret['reason'] : '(none recorded)') ?></b></div>
    <?php if (trim((string)($m7ret['deadline'] ?? '')) !== ''): ?><div style="margin-top:4px;color:var(--bad)">Correct by: <b><?= e(date('d M Y', strtotime($m7ret['deadline']))) ?></b></div><?php endif; ?>
  </div>
<?php endif; ?>

<?php // ---- Phase 2 §6: raised outside the agreed deliverables (applicability override) ---- ?>
<?php if (!empty($doc['not_allocated'])): ?>
  <div class="panel" data-tab="Report" style="border:1px solid var(--warn,#b45309);background:color-mix(in srgb,var(--warn,#b45309) 8%,transparent)">
    <b>⚠ Not on the agreed deliverables.</b> This report type was raised via “add anyway”, outside this <?= e(Tl('job')) ?>’s agreed scope.
    <?php if (trim((string)($doc['applic_override_reason'] ?? '')) !== ''): ?><div style="margin-top:6px">Reason given: <b><?= e($doc['applic_override_reason']) ?></b></div><?php endif; ?>
    <div class="muted" style="font-size:12px;margin-top:4px">The override is recorded on the audit trail.</div>
  </div>
<?php endif; ?>

<?php // ---- Module 07: provenance strip — Prepared / Vetted / Approved / Issued ---- ?>
<?php $m7prov = idems_provenance($doc);
  $m7ico = ['done'=>'✓', 'pending'=>'…', 'returned'=>'↩', 'na'=>'—'];
  $m7col = ['done'=>'var(--ok)', 'pending'=>'var(--muted,#6b7280)', 'returned'=>'var(--bad)', 'na'=>'var(--muted,#6b7280)']; ?>
<div class="panel" data-tab="Report">
  <div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px">Provenance — who did what</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:stretch">
    <?php foreach ($m7prov as $p): ?>
      <div style="flex:1 1 150px;min-width:140px;border:1px solid var(--line);border-radius:8px;padding:8px 10px">
        <div style="font-size:11px;color:var(--muted)"><?= e($p['role']) ?></div>
        <div style="font-weight:700;font-size:13px;color:<?= $m7col[$p['state']] ?? 'var(--ink)' ?>"><?= e(($m7ico[$p['state']] ?? '') . ' ' . $p['name']) ?></div>
        <?php if ($p['at']): ?><div class="muted" style="font-size:11px"><?= e(date('d M Y', strtotime($p['at']))) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="nowband" data-tab="Report">
  <?php if ($rFin || $rSt === 'ISSUED'): ?>
    <div class="step">Issued &amp; locked.</div>
    <p class="next">This <?= e(Tl('report')) ?> has gone to the <?= e(Tl('client')) ?> and can no longer be changed. You can still download the PDF or the client format above.</p>
  <?php elseif ($rSt === 'APPROVED'): ?>
    <div class="step">Approved — ready to issue.</div>
    <?php if (!$doc['finalized'] && function_exists('idems_user_approved_doc') && !is_master() && idems_user_approved_doc($doc, (int)(current_user()['id'] ?? 0))): ?>
      <p class="next"><b>You approved this report</b>, so a <b>different person</b> must finalize &amp; issue it — the approver and the issuer cannot be the same (segregation of duties).</p>
    <?php else: ?>
      <p class="next"><b>Next:</b> press <b>Finalize &amp; issue</b> above to send it and lock it. After that nothing on it can change.</p>
    <?php endif; ?>
  <?php elseif ($rSt === 'REJECTED'): ?>
    <div class="step">Sent back for changes.</div>
    <p class="next"><b>Next:</b> read the reviewer's remark below, fix the body, and submit it again.</p>
    <?php if ($rCanEdit && $rBody): ?><div class="cta"><a class="btn small" href="/document-fill?id=<?= (int)$doc['id'] ?>">Fix the report body →</a></div><?php endif; ?>
  <?php elseif (in_array($rSt, ['SUBMITTED','UNDER_REVIEW'], true)): ?>
    <div class="step">Waiting for approval.</div>
    <p class="next">It has gone to the approver named below. Nothing more to do until they approve it or send it back.</p>
  <?php else: /* DRAFT */ ?>
    <div class="step">Still a draft.</div>
    <p class="next"><b>Next:</b> <?= $rBody ? 'fill in the report body, then submit it for review.' : 'this report type has no form designed yet — design it, then fill the body.' ?></p>
    <?php if ($rCanEdit && $rBody): ?><div class="cta"><a class="btn small" href="/document-fill?id=<?= (int)$doc['id'] ?>">Fill the report body →</a></div><?php endif; ?>
  <?php endif; ?>
</div>

<?php // ---- Module 08: "Ready to issue" — a read-only preview of the issue gates,
      // shown at the issue decision point (approved, not yet finalized) to a finalizer. ?>
<?php if (!$doc['finalized'] && $rSt === 'APPROVED' && (is_master() || can('idems.finalize')) && function_exists('idems_issue_readiness')):
        $m8 = idems_issue_readiness($doc);
        $m8ico = ['ok'=>'✓', 'warn'=>'⚠', 'block'=>'⛔'];
        $m8col = ['ok'=>'var(--ok)', 'warn'=>'var(--warn,#b45309)', 'block'=>'var(--bad)']; ?>
  <div class="panel" data-tab="Report" style="border:1px solid <?= $m8['ready'] ? 'var(--ok)' : 'var(--bad)' ?>;background:color-mix(in srgb,<?= $m8['ready'] ? 'var(--ok)' : 'var(--bad)' ?> 6%,transparent)">
    <b style="color:<?= $m8['ready'] ? 'var(--ok)' : 'var(--bad)' ?>"><?= $m8['ready'] ? '✓ Ready to issue' : '⛔ Not ready to issue' ?></b>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
      <?php foreach ($m8['items'] as $it): ?>
        <div style="display:flex;gap:8px;align-items:flex-start">
          <span style="color:<?= $m8col[$it['state']] ?? 'var(--ink)' ?>;font-weight:700"><?= $m8ico[$it['state']] ?? '' ?></span>
          <span style="font-size:13px"><b><?= e($it['label']) ?></b><?= $it['detail'] !== '' ? ' — ' . e($it['detail']) : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="font-size:11.5px;margin:10px 0 0">Issuing locks the report permanently — a later correction is made as a <b>new revision</b>, never by overwriting the issued version.</p>
  </div>
<?php endif; ?>

<?php if ($doc['finalized']): ?>
<div class="panel" data-tab="Report" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 7%,transparent)">
  <b style="color:var(--ok)">🔒 Finalized &amp; issued</b> — locked on <?= e($doc['finalized_at'] ? date('d M Y H:i', strtotime($doc['finalized_at'])) : '—') ?> by <?= e($doc['finalized_by']) ?>. This report is immutable.
</div>

<?php // "Verify it yourself" wins contracts; "trust us" does not. Print this
      // code and this address on the report and the client can check it is
      // genuine and unaltered without an account and without asking you. ?>
<?php if (function_exists('verify_code_for')): $vc = verify_code_for($doc); $vu = verify_url($doc);
        $vst = function_exists('chain_verify') ? chain_verify((int)$doc['id']) : ['ok'=>true,'entries'=>0]; ?>
<details class="panel" data-tab="Checks">
  <summary style="cursor:pointer;font-weight:700">Let the client check this themselves</summary>
  <p class="sub" style="margin-top:8px">Put these two lines on the report. Anyone holding it can confirm it is genuine
    and unaltered — without an account, and without asking you.</p>
  <table class="dt"><tbody>
    <tr><th style="width:150px">Verification code</th>
      <td><code style="font-size:16px;letter-spacing:.06em"><?= e($vc) ?></code></td></tr>
    <tr><th>Where to check it</th><td><a href="<?= e($vu) ?>" target="_blank" rel="noopener"><?= e($vu) ?></a></td></tr>
    <tr><th>Evidence chain</th>
      <td><?= $vst['ok'] ? '<span class="pill p-ok">intact across ' . (int)$vst['entries'] . ' entries</span>'
            : '<span class="pill p-bad">' . count($vst['problems']) . ' problem(s)</span> — '
              . '<a href="/evidence-review?doc=' . (int)$doc['id'] . '">look at it</a>' ?></td></tr>
  </tbody></table>
  <small class="muted">The page shows the report number, when it was issued, who by, how much of the evidence was
    located on site, and whether anything has changed since. It shows no client name, no findings and no prices —
    a verification page that gave those away would breach the confidentiality the report is issued under.</small>
</details>
<?php endif; ?>
<?php endif; ?>

<?php
  // ---- Vetting & debriefing (a distinct review action before approval) ----
  $vetting = $vetting ?? []; $canVet = $canVet ?? false;
  $vetPill = ['VETTED'=>'p-ok','RETURNED'=>'p-bad','DEBRIEFED'=>'p-info'];
  if (($vetting || $canVet) && empty($doc['finalized'])):
?>
<div class="panel" data-tab="Approvals">
  <div class="ctitle" style="margin-top:0"><h3>Vetting &amp; debriefing</h3>
    <?php if (!empty($doc['vet_status']) && isset(IDEMS_VET_STATUS[$doc['vet_status']])): ?>
      <span class="pill <?= $vetPill[$doc['vet_status']] ?? 'p-mut' ?>"><?= e(IDEMS_VET_STATUS[$doc['vet_status']]) ?></span>
    <?php endif; ?>
  </div>
  <p class="muted" style="margin:0 0 8px;font-size:12px">A senior reviewer vets the report (technical check) or records a debrief before it goes for approval. Returning a report sends it back to the inspector as a draft.</p>
  <?php if ($canVet && empty($doc['finalized'])): ?>
    <div style="margin-bottom:10px"><a class="btn small" href="/document-vet-review?id=<?= (int)$doc['id'] ?>">⇋ Vet side by side (report + checklist)</a></div>
  <?php endif; ?>
  <?php if ($vetting): ?>
    <div class="appr-steps" style="margin-bottom:10px">
      <?php foreach ($vetting as $v): ?>
        <div class="appr-step">
          <span class="pill <?= $vetPill[$v['action']] ?? 'p-mut' ?>"><?= e(($v['stage']==='DEBRIEF'?'Debrief · ':'') . (IDEMS_VET_STATUS[$v['action']] ?? $v['action'])) ?></span>
          <span class="muted">— <?= e($v['acted_by']) ?><?= $v['acted_at']?' · '.e(date('d M H:i', strtotime($v['acted_at']))):'' ?></span>
          <?php if ($v['note']): ?><div class="muted" style="font-size:12px">“<?= e($v['note']) ?>”</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($canVet): ?>
    <?php
      $vetChecklistOn = $vetChecklistOn ?? false; $vetChecklistItems = $vetChecklistItems ?? [];
      $vetChecklistState = $vetChecklistState ?? []; $vetChecklistRequireAll = $vetChecklistRequireAll ?? false;
    ?>
    <form method="post" action="/document-vet">
      <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
      <?php if ($vetChecklistOn && $vetChecklistItems): ?>
        <div class="vet-checklist" style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 13px;margin-bottom:10px;background:var(--soft,#f8fafc)">
          <div style="font-weight:600;font-size:12.5px;margin-bottom:7px">Vetting checklist<?= $vetChecklistRequireAll ? ' <span class="muted" style="font-weight:400">— all points required to clear</span>' : '' ?></div>
          <?php foreach ($vetChecklistItems as $it): $k = idems_vetting_item_key($it); ?>
            <label class="chk" style="display:flex;align-items:flex-start;gap:8px;margin-bottom:5px;font-size:13px">
              <input type="checkbox" name="vc[<?= e($k) ?>]" value="1" <?= !empty($vetChecklistState[$k]) ? 'checked' : '' ?> style="margin-top:2px">
              <span><?= e($it) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <input class="form-control" name="note" placeholder="Note (required to return for correction)" style="margin-bottom:8px">
      <?php // Phase 2 §9 — structured correction detail, so the inspector sees exactly what and by when. ?>
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <input class="form-control" name="correction_area" placeholder="Which section / field to correct? (optional)" style="flex:2">
        <input class="form-control" name="correction_deadline" type="date" title="Correct by (optional)" style="flex:1">
      </div>
      <?php if (idems_is_self_review($doc)): ?>
        <label class="chk" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;font-size:12.5px;color:var(--bad)">
          <input type="checkbox" name="self_ack" value="1" style="margin-top:2px">
          <span>I prepared this report. I confirm I am vetting my own work deliberately — segregation of duties.</span>
        </label>
      <?php endif; ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn" type="submit" name="vet_action" value="VETTED">✓ Vet (cleared)</button>
        <button class="btn secondary" type="submit" name="vet_action" value="RETURNED">↩ Return for correction</button>
        <button class="btn secondary" type="submit" name="vet_action" value="DEBRIEFED">🗣 Record debrief</button>
      </div>
    </form>
    <?php if (is_master() || can('idems.type.manage')): ?>
      <div style="margin-top:7px"><a class="muted" style="font-size:11.5px" href="/vetting-checklist"><?= $vetChecklistOn ? 'Edit the vetting checklist' : 'Set up a vetting checklist' ?> →</a></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($approvals)): ?>
<div class="panel" data-tab="Approvals">
  <div class="ctitle" style="margin-top:0"><h3>Approval chain</h3></div>
  <div class="appr-steps">
    <?php foreach ($approvals as $a):
      $nm = trim(($a['first_name']??'').' '.($a['last_name']??'')) ?: ($a['username'] ?? '');
      $target = $nm ?: ($a['approver_role'] ? (ORG_ROLES[$a['approver_role']] ?? $a['approver_role']) : 'Any approver');
      $sp = ['PENDING'=>'p-warn','APPROVED'=>'p-ok','REJECTED'=>'p-bad','SENTBACK'=>'p-bad','DELEGATED'=>'p-info'][$a['status']] ?? 'p-mut'; ?>
      <div class="appr-step">
        <span class="pill <?= $sp ?>">L<?= (int)$a['level'] ?> · <?= e(IDEMS_APPR_STATUS[$a['status']] ?? $a['status']) ?></span>
        <strong><?= e($target) ?></strong>
        <?php if ($a['acted_by']): ?><span class="muted">— <?= e($a['acted_by']) ?><?= $a['acted_at']?' · '.e(date('d M H:i', strtotime($a['acted_at']))):'' ?></span><?php endif; ?>
        <?php if ($a['remarks']): ?><div class="muted" style="font-size:12px">“<?= e($a['remarks']) ?>”</div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($canAct) && !empty($curStep)): ?>
    <form method="post" action="/document-approve" class="appr-act" style="margin-top:10px">
      <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
      <input class="form-control" name="remarks" placeholder="Remarks (required to reject / send back)" style="margin-bottom:8px">
      <?php // Phase 2 §9 — structured correction detail (used when sending back / rejecting). ?>
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <input class="form-control" name="correction_area" placeholder="Which section / field to correct? (optional)" style="flex:2">
        <input class="form-control" name="correction_deadline" type="date" title="Correct by (optional)" style="flex:1">
      </div>
      <?php if (idems_is_self_review($doc)): ?>
        <label class="chk" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;font-size:12.5px;color:var(--bad)">
          <input type="checkbox" name="self_ack" value="1" style="margin-top:2px">
          <span>I prepared this report. I confirm I am approving my own work deliberately — segregation of duties.</span>
        </label>
      <?php endif; ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <button class="btn" type="submit" name="decision" value="approve">✓ Approve</button>
        <button class="btn secondary" type="submit" name="decision" value="sendback">↩ Send back</button>
        <button class="btn secondary" type="submit" name="decision" value="reject">✕ Reject</button>
        <span style="margin-left:auto;display:flex;gap:6px;align-items:center">
          <select class="form-control" name="delegate_to" style="max-width:200px"><option value="">Delegate to…</option>
            <?php foreach (($delegateUsers ?? []) as $u): $un=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>"><?= e($un) ?></option><?php endforeach; ?>
          </select>
          <button class="btn secondary" type="submit" name="decision" value="delegate">Delegate</button>
        </span>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php $srcRef = json_decode($doc['data'] ?: '[]', true); if (!empty($srcRef['source_irn'])): ?>
<div class="panel" data-tab="Report" style="border:1px solid var(--brand)">
  <b>📋 Drafted from inspection report</b> —
  <?php $srcId = (int)($srcRef['source_report_id'] ?? 0); ?>
  <?= $srcId ? '<a href="/document?id='.$srcId.'">'.e($srcRef['source_irn']).'</a>' : e($srcRef['source_irn']) ?>.
  Wording follows that report's findings; edit before issuing.
</div>
<?php endif; ?>

<div class="panel" data-tab="Report">
  <div class="kv-grid">
    <div><span class="k">IRN</span><strong><?= e($doc['irn']) ?></strong></div>
    <div><span class="k">Report type</span><?= e($doc['type_code']) ?> — <?= e($doc['type_name'] ?: '—') ?></div>
    <?php // Which format is actually in play. The questions on this report come
          // from the report type's designed form; the issued document comes from
          // whichever registered template best fits this client and office. Both
          // are stated, so "is our format being used?" is answered on the page
          // rather than guessed at.
          $tpl = idems_pick_template($doc); ?>
    <div><span class="k">Format in use</span>
      <?php if ($tpl): ?>
        <?= e($tpl['name'] ?: 'Template #' . (int)$tpl['id']) ?>
        <span class="pill p-info"><?= e($tpl['client_id'] ? Tl('client') . '’s own' : ($tpl['report_type_id'] ? 'by type' : ($tpl['office_id'] ? 'by ' . Tl('office') : 'default'))) ?></span>
        <a href="/report-templates" style="font-size:12px">manage</a>
      <?php else: ?>
        <span class="muted">Our standard layout</span>
        <span class="muted" style="font-size:12px">— no <a href="/report-templates">template</a> registered for this <?= e(Tl('client')) ?> or type</span>
      <?php endif; ?>
    </div>
    <div><span class="k">Title</span><?= e($doc['title'] ?: '—') ?></div>
    <div><span class="k">Client</span><?= e($doc['client_disp'] ?: $doc['client_name'] ?: '—') ?></div>
    <div><span class="k">Vendor / mfr</span><?= e($doc['vendor_disp'] ?: $doc['vendor_name'] ?: '—') ?></div>
    <div><span class="k">Project</span><?= e(trim(($doc['project_code'] ?? '').' '.($doc['project_name'] ?? '')) ?: '—') ?></div>
    <div><span class="k">Office</span><?= e($doc['office_name'] ?: '—') ?></div>
    <div><span class="k">Inspector</span><?= e($doc['inspector_name'] ?: '—') ?></div>
    <div><span class="k">Approver</span><?= $approver ? e(trim(($approver['first_name']??'').' '.($approver['last_name']??'')) ?: $approver['username']) : '—' ?></div>
    <div><span class="k">Inspection date</span><?= e($doc['inspection_date'] ?: '—') ?></div>
    <div><span class="k">Issue date</span><?= e($doc['issue_date'] ?: '—') ?></div>
    <div><span class="k">PO</span><?= e($doc['po_ref'] ?: '—') ?></div>
    <div><span class="k">Drawing</span><?= e(trim(($doc['drawing_no'] ?? '').' '.($doc['drawing_rev']? 'Rev '.$doc['drawing_rev']:'')) ?: '—') ?></div>
    <div><span class="k">QAP rev</span><?= e($doc['qap_rev'] ?: '—') ?></div>
    <div><span class="k">Standards</span><?= e($doc['standards'] ?: '—') ?></div>
    <div><span class="k">Material / product</span><?= e(trim(($doc['material_grade'] ?? '').' '.($doc['product_category'] ?? '')) ?: '—') ?></div>
    <div><span class="k">Location</span><?= e($doc['location'] ?: '—') ?></div>
    <div><span class="k">Result</span><?= e(lk_options_or('inspection_result', IDEMS_RESULTS)[$doc['result']] ?? '—') ?></div>
    <div><span class="k">Release</span><?= e(lk_options_or('release_status', IDEMS_RELEASE)[$doc['release_status']] ?? '—') ?></div>
  </div>
  <?php if ($doc['remarks']): ?><div class="kv-wide" style="margin-top:8px"><span class="k">Remarks</span><?= nl2br(e($doc['remarks'])) ?></div><?php endif; ?>
</div>

<?php
  // ---- Report body (designed form values) ----
  if (!empty($hasSchema)):
    $bySec = []; foreach ($fields as $f) $bySec[(int)$f['section_id']][] = $f;
    $filesBy = []; foreach ($files as $fl) $filesBy[$fl['field_key']][] = $fl;
    $showVal = function($f) use ($data, $filesBy, $doc) {
      $k=$f['fkey']; $v=$data[$k] ?? '';
      if (in_array($f['ftype'],['photo','file','signature'],true)) {
        if (empty($filesBy[$k])) return '<span class="muted">—</span>';
        $h=''; foreach ($filesBy[$k] as $fl){ if(strpos($fl['mime'],'image/')===0) $h.='<a href="/report-file?id='.(int)$fl['id'].'" target="_blank"><img src="/report-file?id='.(int)$fl['id'].'" class="ev-th"></a> '; else $h.='<a class="pill p-info" href="/report-file?id='.(int)$fl['id'].'" target="_blank">📎 '.e($fl['file_name']).'</a> '; }
        return $h;
      }
      if ($f['ftype']==='table') {
        if(!is_array($v)||!$v) return '<span class="muted">—</span>';
        $cols=idems_table_cols($f);
        // wide tables (PO items has 12 columns) scroll inside their own box
        // instead of overflowing the card / page.
        $h='<div class="rt-scroll"><table class="dt rt-tbl"><thead><tr>';
        foreach($cols as $cl) $h.='<th>'.e($cl).'</th>'; $h.='</tr></thead><tbody>';
        foreach($v as $r){ $r=(array)$r; $h.='<tr>'; foreach($cols as $ck=>$cl) $h.='<td>'.nl2br(e((string)($r[$ck]??''))).'</td>'; $h.='</tr>'; }
        return $h.'</tbody></table></div>';
      }
      if ($f['ftype']==='richtext') { $t=trim((string)($f['options']??'')); return $t!=='' ? '<span class="muted" style="font-size:12.5px">'.nl2br(e($t)).'</span>' : '<span class="muted">—</span>'; }
      if ($f['ftype']==='sigblock') {
        $sg = function_exists('idems_report_signatures') ? idems_report_signatures($doc) : [];
        $rows = function_exists('idems_sigblock_rows') ? idems_sigblock_rows($f,$data,$sg) : [];
        if(!$rows) return '<span class="muted">—</span>';
        $h='<div class="rt-scroll"><table class="dt rt-tbl"><thead><tr><th>Role</th><th>Name</th><th>Designation</th><th>Date</th></tr></thead><tbody>';
        foreach($rows as $r){ $h.='<tr><td>'.e($r['role']).'</td><td>'.e($r['name']?:'—').'</td><td>'.e($r['desig']?:'—').'</td><td>'.e($r['date']?:'—').'</td></tr>'; }
        return $h.'</tbody></table></div>';
      }
      if ($f['ftype']==='multiselect' && is_array($v)) { $o=idems_field_options($f); return e(implode(', ', array_map(fn($x)=>$o[$x]??$x,$v))) ?: '<span class="muted">—</span>'; }
      if (in_array($f['ftype'],['select','radio','unit'],true)) { $o=idems_field_options($f); return e($o[$v] ?? $v) ?: '<span class="muted">—</span>'; }
      if ($f['ftype']==='checkbox') return ($v==='1'||$v===1)?'Yes':'No';
      if ($f['ftype']==='yesno') return $v!=='' ? e($v) : '<span class="muted">—</span>';
      return $v!=='' ? nl2br(e(is_array($v)?'':$v)) : '<span class="muted">—</span>';
    };
?>
<?php foreach ($sections as $s): if (empty($bySec[(int)$s['id']])) continue; ?>
  <div class="panel" data-tab="Report">
    <div class="ctitle" style="margin-top:0"><h3><?= e($s['title']) ?></h3></div>
    <div class="kv-grid">
      <?php foreach ($bySec[(int)$s['id']] as $f): if(in_array($f['ftype'],['heading','note'],true)) continue; ?>
        <div class="<?= (int)$f['col_span']===2 || in_array($f['ftype'],['table','textarea','photo','file','signature'],true) ? 'kv-wide' : '' ?>"><span class="k"><?= e($f['label']) ?></span><?= $showVal($f) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!empty($bySec[0])): ?>
  <div class="panel" data-tab="Report"><div class="kv-grid"><?php foreach ($bySec[0] as $f): if(in_array($f['ftype'],['heading','note'],true)) continue; ?><div class="<?= (int)$f['col_span']===2?'kv-wide':'' ?>"><span class="k"><?= e($f['label']) ?></span><?= $showVal($f) ?></div><?php endforeach; ?></div></div>
<?php endif; ?>
<style>
  .ev-th{width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}
  /* Report-body tables: scroll sideways in their own box, never overflow the card. */
  .rt-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--line);border-radius:8px;margin-top:4px}
  .rt-tbl{width:auto;min-width:max-content;margin:0}
  .rt-tbl th,.rt-tbl td{white-space:nowrap;padding:5px 9px;font-size:12.5px;vertical-align:top}
  .rt-tbl th{background:var(--soft)}
</style>
<?php else: ?>
<p class="muted" style="margin:10px 2px">No form is designed for this report type yet<?= (is_master()||can('idems.type.manage')) ? ' — use "Design this form" above to add sections &amp; fields.' : '.' ?></p>
<?php endif; ?>

<?php // ISO/IEC 17020 §6.2 — the instruments this report relied on. Naming them
      // here is what turns the equipment register from paperwork into evidence,
      // and it is what the finalise gate reads. ?>
<?php // The measuring-equipment register is an accreditation concern; a business
      // that is not an accredited inspection body or laboratory does not show it.
      if (function_exists('report_equipment') && (!function_exists('accredited_pack_on') || accredited_pack_on())):
        $req = report_equipment($doc['id']);
        $eqDate = report_equipment_date($doc);
        $eqBlock = report_equipment_block($doc);
        $canEq = !$doc['finalized'] && (can('mod.equipment.view') || is_master()); ?>
<details class="panel" id="equipment" data-tab="Report" <?= ($eqBlock !== '' || $req) ? 'open' : '' ?>>
  <summary style="cursor:pointer;font-weight:700">📏 Measuring &amp; test equipment used (<?= count($req) ?>)</summary>
  <div style="margin-top:8px"><a href="/equipment">Register →</a></div>
  <?php if ($eqBlock !== ''): ?>
    <div class="msg msg-error" style="margin:6px 0"><?= e($eqBlock) ?>
      This <?= e(Tl('report')) ?> <strong>will not issue</strong> until it is corrected.</div>
  <?php elseif ($req): ?>
    <div class="msg msg-ok" style="margin:6px 0">Every instrument named was within calibration on <?= e(fdate($eqDate)) ?>.</div>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px">None recorded. If a measurement was taken, name the instrument —
      an assessor will ask which one was used and for its certificate.</p>
  <?php endif; ?>
  <?php if ($req): ?>
  <table class="grid">
    <tr><th>Code</th><th>Instrument</th><th>Serial</th><th>Used on</th><th>Calibration</th><?php if ($canEq): ?><th></th><?php endif; ?></tr>
    <?php foreach ($req as $r): $why = equipment_block((int)$r['equipment_id'], $r['used_on'] ?: $eqDate); ?>
      <tr><td><strong><?= e($r['code']) ?></strong></td><td><?= e($r['name']) ?></td>
        <td><?= e($r['serial_no'] ?: '—') ?></td>
        <td><?= e($r['used_on'] ? fdate($r['used_on']) : fdate($eqDate)) ?></td>
        <td><?= $why === '' ? '<span class="pill p-ok">in calibration</span>'
                            : '<span class="pill p-bad" title="' . e($why) . '">not valid</span>' ?></td>
        <?php if ($canEq): ?>
        <td><form method="post" action="/report-equip-del" style="margin:0">
          <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn small secondary" type="submit">Remove</button></form></td>
        <?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if ($canEq): $avail = equipment_all(); ?>
  <form method="post" action="/report-equip-add" class="inline-add" style="margin-top:10px">
    <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
    <div class="ff"><label>Instrument</label>
      <select class="form-control searchable" name="equipment_id" required>
        <option value="">—</option>
        <?php foreach ($avail as $a): $bad = equipment_block($a['id'], $eqDate); ?>
          <option value="<?= (int)$a['id'] ?>"><?= e($a['code'] . ' — ' . $a['name']) ?><?= $bad ? ' (not valid on this date)' : '' ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Used on</label><input class="form-control" type="date" name="used_on" value="<?= e($eqDate) ?>"></div>
    <button class="btn small" type="submit">Add</button>
  </form>
  <?php endif; ?>
</details>
<?php endif; ?>

<?php if (is_master() || can('idems.timestamp.edit')): ?>
<details class="panel" data-tab="Audit" style="border:1px dashed var(--line)">
  <summary style="cursor:pointer;font-weight:700">🔧 Adjust dates <span class="muted" style="font-weight:400">— Branch Application Manager only</span></summary>
  <p class="muted" style="margin:8px 0 8px">System dates are normally locked. A change here is recorded permanently (old &amp; new value, who, when, reason).</p>
  <form method="post" action="/document-timestamp" style="display:flex;gap:6px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
    <div class="ff" style="margin:0"><label>Field</label>
      <select class="form-control" name="field"><option value="inspection_date">Inspection date</option><option value="issue_date">Issue date</option></select></div>
    <div class="ff" style="margin:0"><label>New value</label><input class="form-control" type="date" name="value"></div>
    <div class="ff" style="margin:0;flex:1;min-width:180px"><label>Reason (required)</label><input class="form-control" name="reason" required></div>
    <button class="btn" type="submit">Apply &amp; log</button>
  </form>
</details>
<?php endif; ?>

<details class="fold" data-tab="Audit">
  <summary>Audit trail <span class="sub">every change to this <?= e(Tl('report')) ?>, with who and when (<?= count($audit) ?>)</span></summary>
  <div class="fold-body" style="padding-left:0;padding-right:0">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>When</th><th>Action</th><th>By</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($audit as $a): ?>
      <tr>
        <td class="muted"><?= e($a['created_at'] ? date('d M Y H:i', strtotime($a['created_at'])) : '—') ?></td>
        <td><span class="pill p-mut"><?= e($a['action']) ?></span></td>
        <td><?= e($a['username']) ?></td>
        <td class="muted"><?= e(trim(($a['field']?$a['field'].': ':'').($a['old_value']?$a['old_value'].' → ':'').($a['new_value'] ?? '')) ?: ($a['reason'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  </div>
</details>
</div><!-- /data-tabs (report) -->
