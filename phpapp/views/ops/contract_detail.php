<?php
  $os = (string)($c['open_status'] ?? 'OPEN');
  $osLbl = defined('CONTRACT_OPEN_STATES') ? (CONTRACT_OPEN_STATES[$os] ?? $os) : $os;
  $osTone = ['PENDING'=>'p-warn','OPEN'=>'p-ok','REJECTED'=>'p-bad','CLOSED'=>'p-mut'][$os] ?? 'p-mut';
  $clientName = $c['display_name'] ?: $c['legal_name'] ?: '—';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/partner?id=<?= (int)$c['partner_id'] ?>&tab=contracts"><?= e($clientName) ?></a> › Contract <?= e($c['contract_number'] ?: '#'.(int)$c['id']) ?></div>
<div class="master-head">
  <div><h1>Contract <?= e($c['contract_number'] ?: '#'.(int)$c['id']) ?> <span class="pill <?= $osTone ?>" style="font-size:12px;vertical-align:middle"><?= e($osLbl) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><a href="/partner?id=<?= (int)$c['partner_id'] ?>"><?= e($clientName) ?></a><?= $c['title'] ? ' · '.e($c['title']) : '' ?><?= $c['branch_name'] ? ' · '.e($c['branch_name']) : '' ?></p></div>
  <a class="btn secondary" href="/partner?id=<?= (int)$c['partner_id'] ?>&tab=contracts">← Contracts</a>
</div>

<?php // ---- Commercial ---------------------------------------------------- ?>
<?php if ($canSeeMoney): ?>
<div class="kpi-row" style="margin:16px 0">
  <div class="kpi"><div class="k">Contract value</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['value'],0) ?></div></div>
  <div class="kpi"><div class="k">Invoiced</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['invoiced'],0) ?></div></div>
  <div class="kpi"><div class="k">Received</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['received'],0) ?></div></div>
  <div class="kpi"><div class="k">Outstanding</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['outstanding'],0) ?></div></div>
</div>
<?php endif; ?>

<div class="panel"><div class="kv-grid">
  <div><span class="k">Contract number</span><b><?= e($c['contract_number'] ?: '—') ?></b></div>
  <div><span class="k">Status</span><span class="pill <?= $osTone ?>"><?= e($osLbl) ?></span></div>
  <?php if ($c['start_date'] || $c['end_date']): ?><div><span class="k">Period</span><?= e(($c['start_date'] ?: '?').' → '.($c['end_date'] ?: '?')) ?></div><?php endif; ?>
  <?php if ($canSeeMoney && (float)($c['value'] ?? 0) > 0): ?><div><span class="k">Value</span><?= e(cur_sym()) ?><?= number_format((float)$c['value'],0) ?></div><?php endif; ?>
  <div><span class="k">Billing branch</span><?= e($c['branch_name'] ?: '—') ?></div>
  <?php if (!empty($c['requested_by'])): ?><div><span class="k">Requested</span><?= e($c['requested_by']) ?><?= $c['requested_at']?' · '.e(substr($c['requested_at'],0,10)):'' ?></div><?php endif; ?>
  <?php if (!empty($c['mgr_endorsed_at'])): ?><div><span class="k">Endorsed (manager)</span><?= e($c['mgr_endorsed_by']) ?> · <?= e(substr($c['mgr_endorsed_at'],0,10)) ?></div><?php endif; ?>
  <?php if (!empty($c['bm_approved_at'])): ?><div><span class="k">Approved (branch mgr)</span><?= e($c['bm_approved_by']) ?> · <?= e(substr($c['bm_approved_at'],0,10)) ?></div><?php endif; ?>
  <?php if (!empty($c['notes'])): ?><div class="kv-wide"><span class="k">Notes</span><?= e($c['notes']) ?></div><?php endif; ?>
</div></div>

<?php // ---- Source quotation ---------------------------------------------- ?>
<?php if ($quote): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Quotation</h3>
  <table class="kv"><tr><th>Quote</th><th>Status</th><?php if ($canSeeMoney): ?><th class="num">Value</th><?php endif; ?><th></th></tr>
    <tr><td><b><?= e($quote['quote_no']) ?><?= (int)$quote['rev']?' r'.(int)$quote['rev']:'' ?></b><?= $quote['subject']?' — '.e($quote['subject']):'' ?></td>
      <td><span class="pill p-mut"><?= e($quote['status']) ?></span></td>
      <?php if ($canSeeMoney): ?><td class="num"><?= e(cur_sym()) ?><?= number_format((float)$quote['total_amount'],0) ?></td><?php endif; ?>
      <td class="num"><a class="btn small secondary" href="/quote?id=<?= (int)$quote['id'] ?>">Open →</a></td></tr>
  </table>
</div>
<?php endif; ?>

<?php // ---- Purchase orders ----------------------------------------------- ?>
<?php if ($pos): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Purchase orders <span class="muted">(<?= count($pos) ?>)</span></h3>
  <table class="dt"><thead><tr><th>PO number</th><th>Title</th><?php if ($canSeeMoney): ?><th class="num">Value</th><?php endif; ?><th>Period</th><th></th></tr></thead><tbody>
  <?php foreach ($pos as $p): ?>
    <tr><td><b><?= e($p['po_number'] ?: '—') ?></b></td><td><?= e($p['title'] ?: '—') ?></td>
      <?php if ($canSeeMoney): ?><td class="num"><?= (float)$p['value'] ? e(cur_sym()).number_format((float)$p['value'],0) : '—' ?></td><?php endif; ?>
      <td class="muted"><?= e(trim(($p['start_date'] ?: '').' → '.($p['end_date'] ?: ''), ' →')) ?: '—' ?></td>
      <td class="num"><a class="btn small secondary" href="/po?id=<?= (int)$p['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php // ---- Inspection calls ---------------------------------------------- ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0"><?= e(ucfirst(Tlp('call'))) ?> raised under this contract <span class="muted">(<?= count($calls) ?>)</span></h3>
  <?php if ($calls): ?>
  <table class="dt"><thead><tr><th><?= e(ucfirst(Tl('call'))) ?></th><th>Status</th><th>Type</th><th class="num"><?= e(ucfirst(Tlp('job'))) ?></th><?php if ($canSeeMoney): ?><th class="num">Invoiced</th><?php endif; ?><th></th></tr></thead><tbody>
  <?php foreach ($calls as $cl): ?>
    <tr><td><b><?= e($cl['call_code']) ?></b> <span class="muted"><?= e($cl['created_at'] ? substr($cl['created_at'],0,10) : '') ?></span></td>
      <td><span class="pill p-mut"><?= e($cl['status']) ?></span></td>
      <td><?= e($cl['inspection_type'] ? (INSPECTION_TYPES[$cl['inspection_type']] ?? $cl['inspection_type']) : '—') ?></td>
      <td class="num"><?= (int)$cl['job_count'] ?></td>
      <?php if ($canSeeMoney): ?><td class="num"><?= (float)$cl['invoiced'] ? e(cur_sym()).number_format((float)$cl['invoiced'],0) : '—' ?></td><?php endif; ?>
      <td class="num"><a class="btn small secondary" href="/call?id=<?= (int)$cl['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="muted" style="margin:0"><?= $os==='OPEN' ? 'No '.e(Tlp('call')).' raised yet — raise one from the contract.' : 'This contract is not open yet, so no '.e(Tlp('call')).' can be raised.' ?></p><?php endif; ?>
</div>

<?php // ---- Jobs ----------------------------------------------------------- ?>
<?php if ($jobs): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0"><?= e(ucfirst(Tlp('job'))) ?> <span class="muted">(<?= count($jobs) ?>)</span></h3>
  <table class="dt"><thead><tr><th><?= e(ucfirst(Tl('job'))) ?></th><th><?= e(ucfirst(Tl('call'))) ?></th><th><?= e(ucfirst(Tl('engineer'))) ?></th><th>Stage</th><th class="num"><?= e(ucfirst(Tlp('report'))) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($jobs as $j): ?>
    <tr><td><b><?= e($j['job_code']) ?></b><?= !empty($j['closed_flag']) ? ' <span class="pill p-ok">closed</span>' : '' ?></td>
      <td class="muted"><?= e($j['call_code'] ?: '—') ?></td>
      <td><?= e($j['inspector_name'] ?: 'Unassigned') ?></td>
      <td><?= e(lk_options_or('job_stage', JOB_STAGES)[$j['stage'] ?? 'ALLOCATED'] ?? ($j['stage'] ?: '—')) ?></td>
      <td class="num"><?= (int)$j['report_count'] ?></td>
      <td class="num"><a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php // ---- Reports -------------------------------------------------------- ?>
<?php if ($reports): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0"><?= e(ucfirst(Tlp('report'))) ?> <span class="muted">(<?= count($reports) ?>)</span></h3>
  <table class="dt"><thead><tr><th>IRN</th><th>Format</th><th><?= e(ucfirst(Tl('job'))) ?></th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($reports as $r): ?>
    <tr><td><b><?= e($r['irn'] ?: '—') ?></b></td>
      <td><?= e(deliverable_options()[$r['type_code']] ?? $r['type_code']) ?></td>
      <td class="muted"><?= e($r['job_code'] ?: '—') ?></td>
      <td><span class="pill <?= !empty($r['finalized']) ? 'p-ok' : 'p-mut' ?>"><?= e(!empty($r['finalized']) ? 'issued' : strtolower((string)($r['status'] ?: 'draft'))) ?></span></td>
      <td class="num"><a class="btn small secondary" href="/document?id=<?= (int)$r['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>
