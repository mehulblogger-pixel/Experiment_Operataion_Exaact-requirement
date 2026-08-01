<?php $rows = $rows ?? []; $live = 0; foreach ($rows as $r) if (trim((string)$r['withdrawn_at']) === '') $live++; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/compliance">Where we stand</a> › Consent register</div>
<div class="master-head">
  <div><h1>Consent &amp; lawful basis</h1>
    <p class="sub" style="margin:2px 0 0">For each person whose personal data we hold — what we use it for, and on what
      basis. The DPDP Act turns on this being <strong>recorded</strong>, not assumed. Client portal users are added
      here automatically when they accept their invitation; add anyone else by hand.</p></div>
  <div><span class="pill p-ok"><?= (int)$live ?> live</span></div>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">The register</h3>
  <table class="dt">
    <thead><tr><th>Whose data</th><th>Kind</th><th>Purpose</th><th>Basis</th><th>Given</th><th>State</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="muted">Nothing recorded yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $withdrawn = trim((string)$r['withdrawn_at']) !== ''; ?>
      <tr<?= $withdrawn ? ' style="opacity:.6"' : '' ?>>
        <td><strong><?= e($r['subject_name'] ?: '—') ?></strong></td>
        <td class="muted"><?= e(CONSENT_SUBJECTS[$r['subject_kind']] ?? $r['subject_kind']) ?></td>
        <td><?= e($r['purpose']) ?></td>
        <td class="muted"><?= e(CONSENT_BASES[$r['basis']] ?? $r['basis']) ?></td>
        <td class="muted"><?= e($r['given_at'] ? fdate(substr((string)$r['given_at'],0,10)) : '—') ?></td>
        <td><?= $withdrawn
              ? '<span class="pill p-bad">withdrawn ' . e(fdate(substr((string)$r['withdrawn_at'],0,10))) . '</span>'
              : '<span class="pill p-ok">given</span>' ?></td>
        <td><?php if (!$withdrawn): ?>
          <form method="post" action="/consent-withdraw" style="margin:0" onsubmit="return confirm('Record this consent as withdrawn?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn small secondary" type="submit">Withdraw</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Record a consent</h3>
  <form method="post" action="/consent-add" class="inline-add">
    <div class="ff"><label>Whose data</label>
      <select class="form-control" name="subject_kind">
        <?php foreach (CONSENT_SUBJECTS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Their name</label><input class="form-control" name="subject_name" required placeholder="the person"></div>
    <div class="ff ff-wide"><label>Purpose <span class="muted">— what we use it for</span></label>
      <input class="form-control" name="purpose" required placeholder="e.g. arranging inspections and sending reports"></div>
    <div class="ff"><label>Basis</label>
      <select class="form-control" name="basis">
        <?php foreach (CONSENT_BASES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Given on</label><input class="form-control" type="date" name="given_at" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff ff-wide"><label>Note</label><input class="form-control" name="note" placeholder="how it was given — e-mail, signed form, in the contract"></div>
    <button class="btn small" type="submit">Record</button>
  </form>
</div>
