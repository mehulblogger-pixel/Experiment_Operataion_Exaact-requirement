<?php // Module 11 — the vendor's own qualification standing. Status and dates only;
      // never the numeric score, rating, band, risk class or internal notes. ?>
<h2 class="ptitle">Your qualification status</h2>
<p class="plead">Where <?= e(cvp_vendor_name()) ?> stands on <?= e(app_name()) ?>'s approved-vendor register,
  and when re-qualification is due. This is your own standing — no scores or internal notes are shown.</p>

<?php if (!$q): ?>
  <p class="pempty">You have not been assessed yet. When an assessment is recorded, your approval status and its
    validity will appear here.</p>
<?php else:
  $tone = ['APPROVED'=>'p-ok','CONDITIONAL'=>'p-warn','UNDER_ASSESSMENT'=>'p-info','PROSPECT'=>'p-mut',
           'EXPIRED'=>'p-bad','SUSPENDED'=>'p-bad','BLACKLISTED'=>'p-bad'][$q['status']] ?? 'p-mut';
?>
<div class="panel" style="<?= $q['expired'] ? 'border:1px solid var(--bad)' : ($q['expiring'] ? 'border:1px solid var(--warn)' : '') ?>">
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <span class="pill <?= $tone ?>" style="font-size:15px;padding:6px 12px"><?= e($q['status_label']) ?></span>
    <?php if ($q['expired']): ?>
      <b style="color:var(--bad)">Your approval has lapsed — please contact us to re-qualify.</b>
    <?php elseif ($q['expiring']): ?>
      <b style="color:var(--warn)">Expires in <?= (int)$q['days_to_expiry'] ?> day(s) — renewal is due.</b>
    <?php endif; ?>
  </div>
  <table class="ptable" style="margin-top:14px">
    <tbody>
      <?php if ($q['vendor_type']): ?><tr><td class="muted" style="width:220px">Vendor type</td><td><?= e($q['vendor_type']) ?></td></tr><?php endif; ?>
      <?php if ($q['category']): ?><tr><td class="muted">Product / service category</td><td><?= e($q['category']) ?></td></tr><?php endif; ?>
      <?php if ($q['approved_on']): ?><tr><td class="muted">Approved on</td><td><?= e(fdate($q['approved_on'])) ?></td></tr><?php endif; ?>
      <tr><td class="muted">Valid until</td><td><?= $q['valid_until'] !== '' ? e(fdate($q['valid_until'])) : '<span class="muted">no expiry recorded</span>' ?></td></tr>
      <?php if ($q['reassess_on']): ?><tr><td class="muted">Next reassessment from</td><td><?= e(fdate($q['reassess_on'])) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($events): ?>
<h3 class="tab-sub" style="margin-top:22px">Status history</h3>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>When</th><th>Status</th><th>By</th></tr></thead>
  <tbody>
  <?php foreach ($events as $e): ?>
    <tr>
      <td><?= $e['at'] !== '' ? e(fdate(substr($e['at'], 0, 10))) : '—' ?></td>
      <td><?= e($e['label'] ?: '—') ?></td>
      <td class="muted"><?= e(['ASSESSMENT'=>'Assessment','AUDIT'=>'Audit','EXPIRY'=>'Expiry','MANUAL'=>'Review'][$e['source']] ?? ($e['source'] ?: '—')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<p class="pnote">If any of this looks wrong, or you are ready to re-qualify, reply to your usual contact at
  <?= e(app_name()) ?> — this page reflects our register and is updated when an assessment is recorded.</p>
<?php endif; ?>
