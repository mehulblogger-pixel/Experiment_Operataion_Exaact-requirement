<?php // Module 26 — DPO access review: every look at an identity document, across all
      // people. Reasons, recipients and actors — never a document number. ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/identity">Identity documents</a> › Access review</div>
<div class="master-head">
  <div><h1>Identity-document access review</h1>
    <p class="sub" style="margin:2px 0 0">Every look, reveal, copy-out and redaction — across everyone — in one place, for a
      data-protection review. This shows <strong>who looked and why</strong>; it never shows a document number.</p></div>
  <a class="btn secondary" href="/identity">← Register</a>
</div>

<?php $actLabels = $actions; ?>
<div class="stat-row" style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap">
  <?php foreach (['REVEAL'=>'tone-bad','SHARE'=>'tone-bad','DOWNLOAD'=>'tone-warn','UPLOAD'=>'','REDACT'=>'','VIEW'=>''] as $ac=>$tone): if (!isset($summary[$ac])) continue; ?>
    <div class="qcard <?= $tone ?>"><div class="qn" style="font-size:22px"><?= (int)$summary[$ac] ?></div><div class="ql"><?= e($actLabels[$ac] ?? $ac) ?></div></div>
  <?php endforeach; ?>
</div>

<form method="get" action="/iddoc-access" class="panel" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:14px">
  <div class="ff" style="margin:0"><label>Action</label>
    <select class="form-control" name="action">
      <option value="">All actions</option>
      <?php foreach ($actLabels as $k=>$lbl): ?><option value="<?= e($k) ?>" <?= ($f['action']??'')===$k?'selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($f['from'] ?? '') ?>"></div>
  <div class="ff" style="margin:0"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($f['to'] ?? '') ?>"></div>
  <button class="btn small" type="submit">Filter</button>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="dt-scroll"><table class="dt">
    <thead><tr><th>When</th><th>Who looked</th><th>Action</th><th>Whose document</th><th>Kind</th><th>Reason / recipient</th><th>From</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= e(date('d-M-Y H:i', strtotime((string)$r['at']))) ?></td>
        <td><b><?= e($r['username'] ?: '—') ?></b></td>
        <td><span class="pill <?= in_array($r['action'], ['REVEAL','SHARE'], true) ? 'p-bad' : (in_array($r['action'],['DOWNLOAD'],true)?'p-warn':'p-mut') ?>"><?= e($actLabels[$r['action']] ?? $r['action']) ?></span></td>
        <td><?= e($r['person_name'] ?: '—') ?></td>
        <td class="muted"><?= e($r['doc_kind'] ?: '—') ?></td>
        <td style="font-size:12.5px"><?= e($r['reason'] ?: '') ?><?= trim((string)$r['recipient'])!=='' ? ' <span class="muted">→ '.e($r['recipient']).'</span>' : '' ?></td>
        <td class="muted" style="font-size:11.5px"><?= e($r['ip'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px;text-align:center">No access recorded in this window.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="panel" style="margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Who can open a document <span class="muted" style="font-weight:400;font-size:12px">(<?= count($holders) ?>)</span></h3>
  <p class="muted" style="margin:0 0 8px;font-size:12.5px">Every non-master account that currently holds “Hold identity documents”. The master administrator can
    always open one and is always logged; fewer names here is the whole point.</p>
  <?php if ($holders): ?>
  <div style="display:flex;flex-wrap:wrap;gap:6px">
    <?php foreach ($holders as $h): ?><span class="pill p-warn"><?= e(trim(($h['first_name']??'').' '.($h['last_name']??'')) ?: $h['username']) ?> <span class="muted" style="font-size:11px">· <?= e($h['role']) ?></span></span><?php endforeach; ?>
  </div>
  <?php else: ?><p class="muted" style="margin:0">Only the master administrator can open a document.</p><?php endif; ?>
</div>
