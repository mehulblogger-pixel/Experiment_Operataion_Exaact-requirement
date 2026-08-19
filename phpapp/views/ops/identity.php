<?php $sel = (int)($_GET['i'] ?? 0); ?>
<div class="crumbs"><a href="/">Home</a> › Identity documents</div>
<div class="master-head">
  <div><h1>Identity documents</h1>
    <p class="sub" style="margin:2px 0 0">Held for one stated reason, for a limited time, and every look is recorded.</p></div>
  <a class="btn secondary" href="/">← Back</a>
</div>

<div class="panel" style="border-left:4px solid var(--accent)">
  <h3 class="tab-sub" style="margin-top:0">What this is for</h3>
  <p style="margin:0"><?= e($purpose) ?></p>
  <p class="sub" style="margin:6px 0 0">Under the DPDP Act 2023 this purpose is what makes holding the document
    lawful, so it is stamped onto every entry as it is filed and it is not used for anything else. Copies are
    kept for <strong><?= (int)$ready['retain_days'] ?> days past the document’s own expiry</strong> and then the
    file and the number are wiped automatically — the record that identity was checked stays.</p>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🪪</span><div class="k">Verified for site access</div>
    <div class="v"><?= (int)$ready['verified'] ?></div><div class="d">of <?= (int)$ready['people'] ?> active</div></div>
  <div class="kpi"><span class="kic">❓</span><div class="k">Nothing on file</div>
    <div class="v"><?= $ready['without'] ? '<span class="down">' . (int)$ready['without'] . '</span>' : '0' ?></div></div>
  <div class="kpi"><span class="kic">⏳</span><div class="k">Expiring within 60 days</div>
    <div class="v"><?= (int)$ready['expiring'] ?></div><div class="d"><?= (int)$ready['expired'] ?> already expired</div></div>
  <div class="kpi"><span class="kic">🗄</span><div class="k">Copies we are holding</div>
    <div class="v"><?= (int)$ready['copies_held'] ?></div>
    <div class="d"><?= $ready['due_redaction'] ? '<span class="down">' . (int)$ready['due_redaction'] . ' past retention</span>' : 'none past retention' ?></div></div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3><?= e(THP('engineer')) ?> <span class="muted">(<?= count($people) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Name</th><th>Code</th><th>Documents</th><th>Site access</th></tr></thead>
    <tbody>
    <?php foreach ($people as $r): $p = $r['p']; ?>
      <tr<?= $sel === (int)$p['id'] ? ' style="background:var(--soft)"' : '' ?>>
        <td><a href="/identity?i=<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a></td>
        <td class="muted"><?= e($p['emp_code']) ?></td>
        <td><?= (int)$r['n'] ?: '—' ?></td>
        <td><?= $r['ok'] ? '<span class="pill p-ok">verified</span>'
                         : '<span class="pill p-warn">nothing current</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$people): ?><tr><td colspan="4">No active <?= e(Tlp('engineer')) ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($person): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3><?= e($person['name']) ?> — documents held</h3></div>

  <?php if (is_array($revealed) && (int)($revealed['id'] ?? 0) === (int)$reveal): ?>
    <p class="pill p-warn" style="display:inline-block">Full number: <strong><?= e($revealed['n']) ?></strong>
      — shown once. Your reading of it has been recorded.</p>
  <?php endif; ?>

  <table class="dt">
    <thead><tr><th>Kind</th><th>Number</th><th>Issued by</th><th>Issued</th><th>Expires</th><th>Kept until</th><th>File</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($docs as $d):
      $exp = ($d['expires_on'] !== '' && $d['expires_on'] < date('Y-m-d')); ?>
      <tr>
        <td><strong><?= e($kinds[$d['doc_kind']] ?? $d['doc_kind']) ?></strong>
          <?php if (!empty($d['note'])): ?><div class="muted" style="font-size:12px"><?= e($d['note']) ?></div><?php endif; ?></td>
        <td><?= !empty($d['redacted_at']) ? '<span class="muted">redacted</span>' : e(iddoc_mask($d)) ?></td>
        <td class="muted"><?= e($d['issuing_authority'] ?: '—') ?><?= $d['issuing_country'] ? '<br>' . e($d['issuing_country']) : '' ?></td>
        <td class="muted"><?= e($d['issued_on'] ? fdate($d['issued_on']) : '—') ?></td>
        <td><?= $exp ? '<span class="pill p-bad">' . e(fdate($d['expires_on'])) . '</span>'
                     : e($d['expires_on'] ? fdate($d['expires_on']) : '—') ?></td>
        <td class="muted"><?= !empty($d['redacted_at'])
              ? 'wiped ' . e(substr((string)$d['redacted_at'], 0, 10))
              : e($d['retain_until'] ? fdate($d['retain_until']) : '—') ?></td>
        <td><?= (!empty($d['file_name']) && empty($d['redacted_at']))
              ? '<a href="/iddoc-file?id=' . (int)$d['id'] . '">' . e($d['file_name']) . '</a>'
              : '<span class="muted">—</span>' ?></td>
        <?php if ($canManage): ?>
        <td style="min-width:260px">
          <?php if (empty($d['redacted_at'])): ?>
          <form method="post" action="/iddoc-reveal" style="display:flex;gap:4px;margin:0 0 4px">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <input class="form-control" style="width:150px" name="reason" placeholder="why the full number?" required>
            <button class="btn small secondary" type="submit">Reveal</button></form>
          <form method="post" action="/iddoc-share" style="display:flex;gap:4px;margin:0 0 4px">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <input class="form-control" style="width:150px" name="recipient" placeholder="sent a copy to…" required>
            <button class="btn small secondary" type="submit">Log it</button></form>
          <form method="post" action="/iddoc-redact" style="margin:0"
                onsubmit="return confirm('This wipes the file and the number for good. The record that identity was checked stays. Continue?')">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <input type="hidden" name="reason" value="Redacted on request.">
            <button class="btn small danger" type="submit">Redact now</button></form>
          <?php else: ?><span class="muted">nothing left to open</span><?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$docs): ?><tr><td colspan="<?= $canManage ? 8 : 7 ?>">Nothing held for this person.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canManage): ?>
  <h3 class="tab-sub">File a document</h3>
  <form method="post" action="/iddoc-add" enctype="multipart/form-data" class="inline-add">
    <input type="hidden" name="person_id" value="<?= (int)$person['id'] ?>">
    <div class="ff"><label>Kind *</label>
      <select class="form-control" name="doc_kind" required>
        <?php foreach ($kinds as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Number <span class="muted">— if it has one</span></label><input class="form-control" name="doc_number">
      <small class="muted">Required for an ID/passport; a declaration or degree has none.</small></div>
    <div class="ff"><label>Issued by</label><input class="form-control" name="issuing_authority" placeholder="issuing office / board"></div>
    <div class="ff"><label>Country</label><input class="form-control" name="issuing_country"></div>
    <div class="ff"><label>Issued on</label><input class="form-control" type="date" name="issued_on"></div>
    <div class="ff"><label>Expires on <span class="muted">— if it expires</span></label><input class="form-control" type="date" name="expires_on">
      <small class="muted">Required for anything that expires (passport, medical). A PAN, declaration or degree does not.</small></div>
    <div class="ff"><label>Scan / photo</label><input class="form-control" type="file" name="doc_file"></div>
    <div class="ff"><label>Told them on</label><input class="form-control" type="date" name="consent_on" value="<?= e(date('Y-m-d')) ?>">
      <small class="muted">When the person was told what this is for.</small></div>
    <div class="ff ff-wide"><label>Note</label>
      <input class="form-control" name="note" placeholder="e.g. required by the plant’s security desk"></div>
    <button class="btn small" type="submit">File it</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Who has looked</h3></div>
  <p class="sub" style="margin-top:0">Every open, download, reveal and copy sent out. This is the part that turns
    “we are careful with it” into something an assessor can check.</p>
  <table class="dt">
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Reason / recipient</th><th>From</th></tr></thead>
    <tbody>
    <?php foreach ($log as $a): ?>
      <tr>
        <td class="muted"><?= e(substr((string)$a['at'], 0, 16)) ?></td>
        <td><?= e($a['username'] ?: '—') ?></td>
        <td><?= e(IDDOC_ACTIONS[$a['action']] ?? $a['action']) ?></td>
        <td><?= e($a['recipient'] ? $a['recipient'] . ' — ' : '') ?><span class="muted"><?= e($a['reason']) ?></span></td>
        <td class="muted"><?= e($a['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$log): ?><tr><td colspan="5">Nothing yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Purpose and retention</h3>
  <p class="sub" style="margin-top:0">Change the wording and it applies to documents filed from now on — what was
    already agreed with somebody is not rewritten behind them. Saving also runs the retention sweep immediately.</p>
  <form method="post" action="/iddoc-retention" class="inline-add">
    <div class="ff"><label>Keep copies for (days past expiry)</label>
      <input class="form-control" type="number" min="1" name="iddoc_retain_days" value="<?= (int)$ready['retain_days'] ?>"></div>
    <div class="ff ff-wide"><label>Purpose, in this company’s words</label>
      <input class="form-control" name="iddoc_purpose" value="<?= e($purpose) ?>"></div>
    <button class="btn small" type="submit">Save &amp; sweep</button>
  </form>
</div>
<?php endif; ?>
