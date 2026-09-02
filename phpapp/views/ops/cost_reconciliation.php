<?php
  // Revamp P8 — sub-contractor cost reconciliation worklist (read-only). The cost-side
  // twin of the revenue reconciliation screen: it shows where a job's legacy subcon_cost
  // disagrees with what a committed cost run allocated to the ledger, so finance can drive
  // the drift to green. Changes nothing — no figure moves, no reader switches.
  $summary = $summary ?? []; $rows = $rows ?? []; $tol = $tol ?? 1;
  $m = fn($n) => function_exists('fmoney_short') ? fmoney_short($n) : number_format((float)$n, 2);
  $green = !empty($summary['green']);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/money">Money</a> › Cost reconciliation</div>
<div class="master-head">
  <div><h1>Cost reconciliation</h1>
    <p class="sub" style="margin:2px 0 0">Where a <?= e(Tl('job')) ?>'s legacy sub-contractor cost disagrees with what a committed
      cost run allocated to the ledger. Read-only — it changes no number. Driving this to <strong>green</strong> means both copies of
      the cost agree everywhere. Tolerance: <?= e($m($tol)) ?>.</p></div>
  <a class="btn secondary" href="/money">← Money</a>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic"><?= $green ? '✅' : '⚠️' ?></span><div class="k">Reconciliation</div>
    <div class="v"><?= $green ? 'Green' : ((int)($summary['diverging'] ?? 0) . ' diverging') ?></div>
    <div class="d"><?= (int)($summary['reconciled'] ?? 0) ?> / <?= (int)($summary['candidates'] ?? 0) ?> reconcile</div></div>
  <div class="kpi"><span class="kic">🧾</span><div class="k">Legacy cost total</div>
    <div class="v"><?= e($m($summary['legacy_total'] ?? 0)) ?></div>
    <div class="d">typed on the <?= e(Tlp('job')) ?></div></div>
  <div class="kpi"><span class="kic">📗</span><div class="k">Ledger cost total</div>
    <div class="v"><?= e($m($summary['ledger_total'] ?? 0)) ?></div>
    <div class="d">committed by cost runs</div></div>
  <div class="kpi"><span class="kic">↔</span><div class="k">One-sided</div>
    <div class="v"><?= (int)($summary['legacy_only'] ?? 0) ?> · <?= (int)($summary['ledger_only'] ?? 0) ?></div>
    <div class="d">legacy-only · ledger-only</div></div>
</div>

<div class="panel" style="margin-top:12px">
  <?php if ($green): ?>
    <p class="msg msg-ok" style="margin:0">Every candidate <?= e(Tlp('job')) ?> reconciles between the legacy sub-contractor figure and
      the committed cost ledger. Both copies of the cost agree.</p>
  <?php elseif (!$rows): ?>
    <p class="muted" style="margin:0">Nothing to reconcile.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px;font-size:12px">Each row's job figure disagrees with the committed ledger figure.
      Re-run the month's cost run to re-commit, or correct the <?= e(Tl('job')) ?>.</p>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th><?= e(THP('job')) ?></th><th>Client</th><th style="text-align:right">Legacy cost</th>
          <th style="text-align:right">Ledger cost</th><th style="text-align:right">Δ</th><th>Why</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $delta = round((float)$r['legacy'] - (float)$r['ledger'], 2); ?>
          <tr>
            <td><a href="/job?id=<?= (int)$r['job_id'] ?>"><?= e($r['job_code']) ?></a></td>
            <td><?= e($r['client_name'] ?: '—') ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['legacy'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['ledger'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><span class="pill <?= abs($delta) > 0 ? 'p-warn' : 'p-mut' ?>"><?= e($m($delta)) ?></span></td>
            <td style="font-size:12px"><?= e($r['reason']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
