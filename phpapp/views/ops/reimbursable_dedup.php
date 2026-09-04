<?php
  // P8 (C + B) — reimbursable duplication worklist. Jobs whose reimbursables sit on BOTH
  // the closure expenses and the inspector voucher, so profit sums both. Option C: reconcile
  // each at source from this list. Option B: the de-duplication toggle (off by default) that
  // nets the detected overlap in the profit engine as an estimate.
  $rows = $rows ?? []; $summary = $summary ?? []; $mode = $mode ?? 'off'; $modes = $modes ?? [];
  $m = fn($n) => function_exists('fmoney_short') ? fmoney_short($n) : number_format((float)$n, 2);
  $clean = empty($rows);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/money">Money</a> › Reimbursable duplication</div>
<div class="master-head">
  <div><h1>Reimbursable duplication</h1>
    <p class="sub" style="margin:2px 0 0">Reimbursables (travel · lodging · food) can be recorded on <strong>two doors</strong> — the <?= e(Tl('job')) ?>&rsquo;s
      closure <strong>expenses</strong> and the <?= e(Tl('engineer')) ?>&rsquo;s monthly <strong>voucher</strong>. The profit engine adds both, so where the
      <em>same</em> trip is on both it is double-counted. Reconcile each job below — remove the duplicate on the wrong door at source.</p></div>
  <a class="btn secondary" href="/money">← Money</a>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic"><?= $clean ? '✅' : '⚠️' ?></span><div class="k">Both-sided jobs</div>
    <div class="v"><?= (int)($summary['jobs'] ?? 0) ?></div>
    <div class="d">reimbursables on both doors</div></div>
  <div class="kpi"><span class="kic">🧾</span><div class="k">Overlap at risk</div>
    <div class="v"><?= e($m($summary['overlap_total'] ?? 0)) ?></div>
    <div class="d">upper bound on double-count</div></div>
  <div class="kpi"><span class="kic">⚙️</span><div class="k">Handling</div>
    <div class="v" style="font-size:15px"><?= $mode === 'net' ? 'Netting estimate' : 'Counting both' ?></div>
    <div class="d"><?= $mode === 'net' ? 'overlap subtracted' : 'accurate total' ?></div></div>
</div>

<?php // Option B — the de-duplication toggle (off by default). Changing it moves profit. ?>
<div class="panel" style="margin-top:12px">
  <h3 class="tab-sub" style="margin-top:0">How profit treats these <span class="pill <?= $mode === 'net' ? 'p-warn' : 'p-ok' ?>" style="font-size:11px"><?= e($modes[$mode] ?? $mode) ?></span></h3>
  <p class="muted" style="font-size:12.5px;margin:0 0 10px"><strong>Count both doors</strong> (default) is the accurate total — clear each real duplicate from the list below.
    <strong>Net the detected overlap</strong> subtracts <code>min(expenses, voucher)</code> per both-sided job as an <em>estimate</em>; it is convenient but over-removes when the two doors hold genuinely different trips, so it is off by default. It changes displayed profit; switch back any time.</p>
  <form method="post" action="/reimbursable-dedup" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php foreach ($modes as $k => $label): ?>
      <label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;<?= $k === $mode ? 'font-weight:600' : '' ?>">
        <input type="radio" name="reimbursable_dedupe" value="<?= e($k) ?>" <?= $k === $mode ? 'checked' : '' ?>> <?= e($label) ?>
      </label>
    <?php endforeach; ?>
    <button class="btn secondary" type="submit" style="padding:4px 12px">Apply</button>
    <?php if ($mode === 'net'): ?><span class="pill p-warn" style="font-size:11px">Estimate — reconciling per job is exact</span><?php endif; ?>
  </form>
</div>

<div class="panel" style="margin-top:12px">
  <?php if ($clean): ?>
    <p class="msg msg-ok" style="margin:0">No <?= e(Tlp('job')) ?> records reimbursables on both doors. Nothing to reconcile.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px;font-size:12px">Largest likely overlap first. Open each <?= e(Tl('job')) ?> and confirm the two sides are different trips — if they are the same, remove it from the wrong door.</p>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th><?= e(THP('job')) ?></th><th>Client</th><th style="text-align:right">Closure expenses</th>
          <th style="text-align:right">Voucher</th><th style="text-align:right">Overlap ≤</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="/job?id=<?= (int)$r['job_id'] ?>"><?= e($r['job_code'] ?: ('#' . $r['job_id'])) ?></a></td>
            <td><?= e($r['client_name'] ?: '—') ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['expenses'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['voucher'])) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><span class="pill p-warn"><?= e($m($r['overlap'])) ?></span></td>
            <td style="text-align:right"><a class="btn btn-ghost" href="/job?id=<?= (int)$r['job_id'] ?>" style="padding:2px 9px;font-size:12px">Reconcile</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
