<div class="master-head">
  <div><h1>Contract openings</h1>
    <p class="sub" style="margin:2px 0 0">Won orders waiting for their contract number to be opened. Your company's rule: <?= e($ruleWords ?? contract_rule_sentence()) ?>. Once open, the order floats to operations.</p></div>
</div>

<?php if (!$rows): ?>
  <div class="panel" style="margin-top:16px"><p style="margin:0">Nothing is waiting. Every registered contract has been opened (or none is pending).</p></div>
<?php else: ?>
  <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="dt-scroll">
      <table class="dt">
        <thead><tr>
          <th>Contract</th><th><?= e(Tl('client')) ?></th><th>What it's for</th><th>Requested</th><th>Step</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $c):
          $endorsed = trim((string)($c['mgr_endorsed_at'] ?? '')) !== '';
          $sg = contract_open_stage($c);
          $step = $sg['need'] === 'first' ? 'Waiting for the first signature' : ($sg['signatures'] === 2 ? 'Waiting for the final approval' : 'Waiting for approval');
          $desc = trim((string)($c['quote_subject'] ?? ''));
        ?>
          <tr>
            <td><b><?= e($c['contract_number'] ?: ('#' . (int)$c['id'])) ?></b>
              <?php if ($canSeeQuote && !empty($c['quote_id'])): ?><br><a class="muted" style="font-size:12px" href="/quote?id=<?= (int)$c['quote_id'] ?>#contract"><?= e($c['quote_no'] ?: 'quote') ?> ↗</a><?php endif; ?></td>
            <td><?= e($c['client_name'] ?: '—') ?></td>
            <td class="muted" style="font-size:13px"><?= e($desc !== '' ? $desc : '—') ?></td>
            <td class="muted" style="font-size:12.5px"><?= e($c['requested_by'] ?: '—') ?><?= !empty($c['requested_at']) ? '<br>' . e(fdate(substr((string)$c['requested_at'],0,10))) : '' ?></td>
            <td><span class="pill <?= $endorsed ? 'p-info' : 'p-warn' ?>" style="font-size:11px"><?= e($step) ?></span>
              <?php if ($endorsed): ?><br><span class="muted" style="font-size:11px">first signature: <?= e($c['mgr_endorsed_by']) ?></span><?php endif; ?>
              <?php $coordWho = function_exists('call_coordinator_name') ? call_coordinator_name($c) : '';
                    if ($coordWho !== ''): ?><br><span class="muted" style="font-size:11px">👤 <?= e($coordWho) ?> to coordinate</span><?php endif; ?></td>
            <td class="num" style="white-space:nowrap">
              <?php $sgId = (int)$c['id']; $sgKind = 'open'; $sgCoordinators = $myCoordinators ?? []; $sgOfficeName = $myOfficeName ?? ''; $sgCompact = true; include __DIR__ . '/_contract_sign.php'; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php $cRule = contract_open_rule(); if ((int)$cRule['signatures'] === 2 && !$cRule['same_person']): ?>
  <p class="muted" style="margin-top:10px;font-size:12.5px">The two signatures must come from two different people (the Master Admin standing in for a one-person branch is the only exception).</p>
  <?php endif; ?>
<?php endif; ?>
