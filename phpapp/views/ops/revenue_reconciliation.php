<?php
  // Revamp §29 — recognised-revenue reconciliation worklist (read-only). Shows where a
  // job's legacy invoice snapshot disagrees with the books ledger, so finance can drive
  // it to green before any revenue reader is switched onto the ledger (§28). Changes nothing.
  $summary = $summary ?? []; $rows = $rows ?? []; $tol = $tol ?? 1;
  $m = fn($n) => function_exists('fmoney_short') ? fmoney_short($n) : number_format((float)$n, 2);
  $green = !empty($summary['green']);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/money">Money</a> › Revenue reconciliation</div>
<div class="master-head">
  <div><h1>Revenue reconciliation</h1>
    <p class="sub" style="margin:2px 0 0">Where a <?= e(Tl('job')) ?>'s legacy invoice figure disagrees with the books ledger.
      Read-only — it changes no number. Driving this to <strong>green</strong> is what lets the dashboards move fully onto the
      one ledger truth (§28). Tolerance: <?= e($m($tol)) ?>.</p></div>
  <a class="btn secondary" href="/money">← Money</a>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic"><?= $green ? '✅' : '⚠️' ?></span><div class="k">Reconciliation</div>
    <div class="v"><?= $green ? 'Green' : ((int)($summary['diverging'] ?? 0) . ' diverging') ?></div>
    <div class="d"><?= (int)($summary['reconciled'] ?? 0) ?> / <?= (int)($summary['candidates'] ?? 0) ?> reconcile</div></div>
  <div class="kpi"><span class="kic">🧾</span><div class="k">Legacy snapshot total</div>
    <div class="v"><?= e($m($summary['legacy_total'] ?? 0)) ?></div></div>
  <div class="kpi"><span class="kic">📗</span><div class="k">Ledger net total</div>
    <div class="v"><?= e($m($summary['ledger_net_total'] ?? 0)) ?></div></div>
  <div class="kpi"><span class="kic">↔</span><div class="k">One-sided</div>
    <div class="v"><?= (int)($summary['legacy_only'] ?? 0) ?> · <?= (int)($summary['ledger_only'] ?? 0) ?></div>
    <div class="d">legacy-only · ledger-only</div></div>
</div>

<div class="panel" style="margin-top:12px">
  <?php if ($green): ?>
    <p class="msg msg-ok" style="margin:0">Every candidate <?= e(Tlp('job')) ?> reconciles between the legacy snapshot and the ledger.
      The revenue readers can be switched onto the ledger as a deliberate §28 step.</p>
  <?php elseif (!$rows): ?>
    <p class="muted" style="margin:0">Nothing to reconcile.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px;font-size:12px">Each row's legacy figure matches neither the net nor the gross ledger total.
      Open the <?= e(Tl('job')) ?> to correct the figure or the invoice.</p>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th><?= e(THP('job')) ?></th><th>Client</th><th style="text-align:right">Legacy</th>
          <th style="text-align:right">Ledger net</th><th style="text-align:right">Ledger gross</th><th style="text-align:right">Δ (net)</th><th>Why</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $delta = round((float)$r['legacy'] - (float)$r['ledger_net'], 2); ?>
          <tr>
            <td><a href="/job?id=<?= (int)$r['job_id'] ?>"><?= e($r['job_code']) ?></a></td>
            <td><?= e($r['client_name'] ?: '—') ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['legacy'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['ledger_net'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['ledger_gross'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><span class="pill <?= abs($delta) > 0 ? 'p-warn' : 'p-mut' ?>"><?= e($m($delta)) ?></span></td>
            <td style="font-size:12px"><?= e($r['reason']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
