<?php
  // Super-Admin — the compliance rule master (versioned, effective-dated) + the fee engine.
  // Statutory config: master only. Nothing here is hard-coded; every rate is a dated rule.
  $ruleTypes = $ruleTypes ?? []; $rules = $rules ?? []; $feeRules = $feeRules ?? [];
  $feePayers = $feePayers ?? []; $feeBases = $feeBases ?? []; $cur = $currency ?? '₹';
  $money = fn($n) => e($cur) . number_format((float)$n, 2);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/super-admin">Super Admin</a> › Compliance rules</div>
<div class="master-head">
  <div><h1>Compliance rules &amp; fees</h1>
    <p class="sub">Every GST / SAC / RCM / TDS / TCS value and every marketplace fee is a <b>versioned, effective-dated rule</b> — never hard-coded. Add a new version when the law changes; old transactions keep the version they used.</p></div>
  <a class="btn secondary" href="/super-admin">← Super Admin</a>
</div>

<div class="panel" style="border-left:4px solid var(--amber,#8a5a00);max-width:960px">
  <p style="margin:0"><b>These are configuration, not legal advice.</b> Seeded rows are marked <i>“CANDIDATE — confirm with CA”</i>. Enter your CA’s confirmed rates and SAC classifications here; the marketplace engines read them by transaction date.</p>
</div>

<?php // ---- Tax / compliance rule master ---- ?>
<div class="panel" style="max-width:960px">
  <h3 style="margin-top:0">Tax &amp; compliance rules (in force today)</h3>
  <?php if ($rules): ?>
    <div style="overflow-x:auto"><table class="grid" style="margin:0"><thead><tr>
      <th>Type</th><th>Code</th><th>Description</th><th>Rate</th><th>Effective from</th><th>v</th><th>Source</th>
    </tr></thead><tbody>
      <?php foreach ($rules as $r): ?>
        <tr>
          <td><b><?= e($ruleTypes[$r['rule_type']] ?? $r['rule_type']) ?></b></td>
          <td class="mono" style="font-family:monospace"><?= e($r['code']) ?></td>
          <td class="muted" style="font-size:13px"><?= e($r['label']) ?></td>
          <td><b><?= rtrim(rtrim(number_format((float)$r['rate'],2),'0'),'.') ?>%</b></td>
          <td class="muted" style="font-size:13px"><?= e($r['effective_from']) ?><?= $r['effective_until'] ? ' → ' . e($r['effective_until']) : '' ?></td>
          <td class="muted"><?= (int)$r['version'] ?></td>
          <td class="muted" style="font-size:11.5px"><?= e($r['source_ref']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php else: ?><p class="muted">No rules yet.</p><?php endif; ?>

  <h4 style="margin:16px 0 6px">Add / supersede a rule</h4>
  <form method="post" action="/compliance-rules" class="form-grid" style="align-items:end">
    <input type="hidden" name="action" value="save_rule">
    <div class="ff"><label>Type</label><select class="form-control" name="rule_type">
      <?php foreach ($ruleTypes as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
    </select></div>
    <div class="ff"><label>Code</label><input class="form-control" name="code" placeholder="e.g. 998346 / STD / 194J" required></div>
    <div class="ff"><label>Rate (%)</label><input class="form-control" name="rate" type="number" step="0.01" min="0" value="0"></div>
    <div class="ff"><label>Effective from</label><input class="form-control" name="effective_from" type="date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="ff" style="grid-column:span 2"><label>Description</label><input class="form-control" name="label" placeholder="What this rule is"></div>
    <div class="ff" style="grid-column:span 2"><label>Source reference</label><input class="form-control" name="source_ref" placeholder="Notification / circular / CA note"></div>
    <div class="ff"><button class="btn" type="submit">Save version</button></div>
  </form>
  <p class="muted" style="font-size:12px;margin:8px 0 0">Adding a rate for a code that already exists creates a <b>new version</b> — the previous one is closed the day before the new effective date and kept for history.</p>
</div>

<?php // ---- Fee-rule engine ---- ?>
<div class="panel" style="max-width:960px;margin-top:16px">
  <h3 style="margin-top:0">Marketplace fee rules</h3>
  <?php if ($feeRules): ?>
    <div style="overflow-x:auto"><table class="grid" style="margin:0"><thead><tr>
      <th>Name</th><th>Payer</th><th>Method</th><th>Rate</th><th>Base</th><th>Min / Max</th><th>From</th><th></th>
    </tr></thead><tbody>
      <?php foreach ($feeRules as $f): $active = strtoupper((string)$f['status'])==='ACTIVE'; ?>
        <tr<?= $active ? '' : ' style="opacity:.5"' ?>>
          <td><b><?= e($f['name']) ?></b><br><span class="muted" style="font-size:11px"><?= e($f['code']) ?></span></td>
          <td><?= e($feePayers[$f['payer']] ?? $f['payer']) ?></td>
          <td class="muted" style="font-size:12.5px"><?= e($f['method']) ?></td>
          <td><?= (float)$f['percent'] > 0 ? rtrim(rtrim(number_format((float)$f['percent'],2),'0'),'.').'%' : '' ?><?= (float)$f['fixed']>0 ? ' +'.$money($f['fixed']) : '' ?></td>
          <td class="muted" style="font-size:12.5px"><?= e($feeBases[$f['base']] ?? $f['base']) ?></td>
          <td class="muted" style="font-size:12px"><?= (float)$f['min_fee']>0?$money($f['min_fee']):'—' ?> / <?= (float)$f['max_fee']>0?$money($f['max_fee']):'—' ?></td>
          <td class="muted" style="font-size:12px"><?= e($f['effective_from']) ?></td>
          <td><?php if ($active): ?><form method="post" action="/compliance-rules" style="display:inline" onsubmit="return confirm('Retire this fee rule?')"><input type="hidden" name="action" value="retire_fee"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn small" style="background:#9a2a2a" type="submit">×</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php else: ?><p class="muted">No fee rules yet.</p><?php endif; ?>

  <h4 style="margin:16px 0 6px">Add a fee rule</h4>
  <form method="post" action="/compliance-rules" class="form-grid" style="align-items:end">
    <input type="hidden" name="action" value="save_fee">
    <div class="ff" style="grid-column:span 2"><label>Name</label><input class="form-control" name="name" placeholder="e.g. Client marketplace fee" required></div>
    <div class="ff"><label>Payer</label><select class="form-control" name="payer"><?php foreach ($feePayers as $k=>$lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Method</label><select class="form-control" name="method"><option value="PERCENT">Percentage</option><option value="FIXED">Fixed</option><option value="PERCENT_PLUS_FIXED">Percent + fixed</option></select></div>
    <div class="ff"><label>Percent (%)</label><input class="form-control" name="percent" type="number" step="0.01" min="0" value="0"></div>
    <div class="ff"><label>Fixed (<?= e($cur) ?>)</label><input class="form-control" name="fixed" type="number" step="0.01" min="0" value="0"></div>
    <div class="ff"><label>Base</label><select class="form-control" name="base"><?php foreach ($feeBases as $k=>$lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Min fee</label><input class="form-control" name="min_fee" type="number" step="0.01" min="0" value="0"></div>
    <div class="ff"><label>Max fee</label><input class="form-control" name="max_fee" type="number" step="0.01" min="0" value="0"></div>
    <div class="ff"><label>Effective from</label><input class="form-control" name="effective_from" type="date" value="<?= date('Y-m-d') ?>"></div>
    <div class="ff"><button class="btn" type="submit">Add fee rule</button></div>
  </form>
</div>
<p class="muted" style="font-size:12px;max-width:960px;margin-top:10px">Computing a fee here moves no money — it is read at settlement time (Phase 5). Every computed fee stores the exact base and rule version used, so a settlement can always be reproduced.</p>

<?php // ---- Tax-admin role (master only) ---- ?>
<?php if (!empty($isMaster)): ?>
<div class="panel" style="max-width:960px;margin-top:16px">
  <h3 style="margin-top:0">Who may edit these rules</h3>
  <p class="muted" style="font-size:13px;margin:0 0 10px">Statutory rules are restricted. The Master always has access; add <b>Tax admins</b> here by e-mail. Commercial admins never qualify — this keeps tax config separate from pricing config.</p>
  <form method="post" action="/compliance-rules" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="action" value="save_taxadmins">
    <div class="ff" style="flex:1;min-width:280px"><label>Tax-admin e-mails (comma-separated)</label>
      <input class="form-control" name="tax_admin_emails" value="<?= e($taxAdmins ?? '') ?>" placeholder="tax@yourco.com, ca@firm.com"></div>
    <button class="btn" type="submit">Save tax admins</button>
  </form>
</div>
<?php endif; ?>
