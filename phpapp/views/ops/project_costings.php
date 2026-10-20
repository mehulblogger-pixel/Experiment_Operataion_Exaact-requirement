<?php
// Project costing — list + new-costing form.
$rows = $rows ?? []; $clients = $clients ?? []; $offices = $offices ?? [];
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$canEdit = function_exists('pc_can_edit') && pc_can_edit();
$stTone = ['DRAFT'=>'p-mut','SUBMITTED'=>'p-warn','APPROVED'=>'p-ok','REJECTED'=>'p-bad'];
?>
<div class="master-head">
  <div><h1>Project costing</h1>
    <p class="sub" style="margin:2px 0 0">Whole-project cost build-ups — team roles, cost heads, loadings and man-month / man-day / lump rates, rolled up to project revenue &amp; margin. Reusable from Sales and Recruitment.</p></div>
</div>

<?php if ($canEdit): ?>
<?php $pre = $pre ?? ['inquiry_id' => 0, 'lead_id' => 0]; $inquiries = $inquiries ?? []; $leads = $leads ?? []; $sbuOpts = $sbuOpts ?? []; ?>
<details class="panel" style="margin-bottom:14px"<?= (empty($rows) || $pre['inquiry_id'] || $pre['lead_id']) ? ' open' : '' ?>>
  <summary style="cursor:pointer;font-weight:600">＋ New costing</summary>
  <form method="post" action="/project-costing-new" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <?php // What the costing prices — owner, 20 Oct 2026. The enquiry or lead fills in
          //  the customer, branch and business unit; pick one of the two. ?>
    <div class="form-grid">
      <div class="ff"><label>For — enquiry</label><select class="form-control searchable" name="inquiry_id"><option value="">—</option>
        <?php foreach ($inquiries as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (int)$pre['inquiry_id'] === (int)$i['id'] ? 'selected' : '' ?>><?= $e(trim(($i['inquiry_no'] ?? '') . ' · ' . ($i['client_name'] ?? '') . ' · ' . ($i['subject'] ?? ''), ' ·')) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>…or a lead</label><select class="form-control searchable" name="lead_id"><option value="">—</option>
        <?php foreach ($leads as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$pre['lead_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= $e(trim(($l['ref'] ?? '') . ' · ' . ($l['company_name'] ?? ''), ' ·')) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Business unit <span class="muted">— from the enquiry or lead if left blank</span></label><select class="form-control" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k => $v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?></select></div>
    </div>
    <p class="muted" style="font-size:12.5px;margin:4px 0 10px">Customer, office and title below fill themselves in from the enquiry or lead when you leave them empty. Approval follows the same levels as a quotation of this value — set on Set up Sales.</p>
    <div class="form-grid">
      <div class="ff ff-wide"><label>Title <span class="muted">— optional when an enquiry or lead is picked</span></label><input class="form-control" name="title" placeholder="e.g. Mundra Solar — module &amp; cell manpower"></div>
      <div class="ff"><label>Client</label><select class="form-control" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $e($c['display_name'] ?? $c['legal_name'] ?? ('#'.$c['id'])) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Executing office</label><select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= $e($o['name']) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Site</label><input class="form-control" name="site" placeholder="e.g. Mundra, Gujarat"></div>
      <div class="ff"><label>Basis</label><select class="form-control" name="basis"><?php foreach (PC_BASES as $k=>$v): ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php endforeach; ?></select></div>
    </div>
    <div style="margin-top:8px"><button class="btn" type="submit">Create costing</button></div>
  </form>
</details>
<?php endif; ?>

<div class="panel">
  <?php if (empty($rows)): ?>
    <p class="muted" style="padding:8px">No costings yet.<?= $canEdit ? ' Create one above.' : '' ?></p>
  <?php else: ?>
  <div class="scroll"><table class="grid">
    <thead><tr><th>Code</th><th>Title</th><th>Client</th><th>Basis</th><th style="text-align:right">Revenue</th><th style="text-align:right">Cost</th><th style="text-align:right">Profit</th><th style="text-align:right">Margin</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $rev=(float)$r['exp_revenue']; $pf=(float)$r['exp_profit']; $mg=$rev>0?$pf/$rev*100:0; ?>
      <tr>
        <td><a href="/project-costing?id=<?= (int)$r['id'] ?>"><b><?= $e($r['code']) ?></b></a></td>
        <td><?= $e($r['title']) ?><?= !empty($r['site']) ? '<br><span class="muted" style="font-size:11px">'.$e($r['site']).'</span>' : '' ?></td>
        <td><?= $e($r['client_name'] ?? '—') ?></td>
        <td><span class="muted" style="font-size:12px"><?= $e(PC_BASES[$r['basis']] ?? $r['basis']) ?></span></td>
        <td style="text-align:right"><?= fmoney_short($rev) ?></td>
        <td style="text-align:right"><?= fmoney_short($r['exp_cost']) ?></td>
        <td style="text-align:right;color:var(--<?= $pf>=0?'ok':'bad' ?>)"><?= fmoney_short($pf) ?></td>
        <td style="text-align:right"><?= number_format($mg,1) ?>%</td>
        <td><span class="pill <?= $stTone[$r['status']] ?? 'p-mut' ?>"><?= $e(PC_STATUS[$r['status']] ?? $r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
