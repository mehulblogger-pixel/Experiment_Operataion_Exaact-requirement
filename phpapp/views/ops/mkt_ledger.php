<?php
  // Financial control — the money-truth board. GMV (facilitated), Connect revenue (ours),
  // payment-provider cost (an expense) and professional payable are shown SEPARATELY and
  // are never summed into one another.
  $from = $from ?? date('Y-m-01'); $to = $to ?? date('Y-m-d');
  $totals = $totals ?? []; $streams = $streams ?? []; $takeRate = (float)($takeRate ?? 0);
  $recent = $recent ?? []; $cats = $cats ?? []; $streamLabels = $streamLabels ?? []; $cur = $currency ?? '₹';
  $money = fn($n) => e($cur) . number_format((float)$n, 0);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/super-admin">Super Admin</a> › Financial control</div>
<div class="master-head">
  <div><h1>Financial control</h1>
    <p class="sub">GMV, Connect revenue and payment-provider cost are three different things — this board keeps them apart. <b>Connect revenue is only our own fees; the professional’s service value is GMV, not our income.</b></p></div>
  <a class="btn secondary" href="/super-admin">← Super Admin</a>
</div>

<form method="get" action="/financial-control" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:14px">
  <div class="ff"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="ff"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn" type="submit">Apply</button>
</form>

<?php // ---- The three monies, kept apart ---- ?>
<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));max-width:1000px">
  <div class="panel" style="margin:0;padding:16px 18px;border-left:4px solid #6b7a86">
    <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">GMV (facilitated)</div>
    <div style="font-size:24px;font-weight:800"><?= $money($totals['GMV'] ?? 0) ?></div>
    <div class="muted" style="font-size:12px">Underlying professional service value — <b>not</b> our revenue</div>
  </div>
  <div class="panel" style="margin:0;padding:16px 18px;border-left:4px solid var(--teal,#0a5c5c)">
    <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Connect revenue</div>
    <div style="font-size:24px;font-weight:800;color:var(--teal,#0a5c5c)"><?= $money($totals['CONNECT_REVENUE'] ?? 0) ?></div>
    <div class="muted" style="font-size:12px">Take rate <b><?= $takeRate ?>%</b> of GMV</div>
  </div>
  <div class="panel" style="margin:0;padding:16px 18px;border-left:4px solid #9a2a2a">
    <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Provider fee (expense)</div>
    <div style="font-size:24px;font-weight:800;color:#9a2a2a"><?= $money($totals['PROVIDER_FEE'] ?? 0) ?></div>
    <div class="muted" style="font-size:12px">Razorpay / Route cost — never revenue</div>
  </div>
  <div class="panel" style="margin:0;padding:16px 18px;border-left:4px solid #8a5a00">
    <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Payable to pros</div>
    <div style="font-size:24px;font-weight:800"><?= $money($totals['PRO_PAYABLE'] ?? 0) ?></div>
    <div class="muted" style="font-size:12px">A liability, settled to professionals</div>
  </div>
</div>

<?php // ---- Revenue by stream ---- ?>
<div class="panel" style="max-width:1000px;margin-top:16px">
  <h3 style="margin-top:0">Connect revenue by stream</h3>
  <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <?php foreach ($streamLabels as $k => $lbl): ?>
      <div style="background:var(--soft,#f6faf9);border:1px solid var(--line);border-radius:8px;padding:10px 12px">
        <div class="muted" style="font-size:12px"><?= e($lbl) ?></div>
        <div style="font-size:18px;font-weight:700"><?= $money($streams[$k] ?? 0) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="font-size:12px;margin:10px 0 0">Revenue appears here only when money is actually taken (a confirmed payment or a settled marketplace fee). It is empty until you go live — which is the honest state.</p>
</div>

<?php // ---- Recent entries ---- ?>
<div class="panel" style="max-width:1000px;margin-top:16px">
  <h3 style="margin-top:0">Recent money events</h3>
  <?php if (!$recent): ?>
    <p class="muted">No entries yet. Entries post automatically when payments confirm and escrow releases.</p>
  <?php else: ?>
    <div style="overflow-x:auto"><table class="grid" style="margin:0"><thead><tr>
      <th>Date</th><th>Category</th><th>Stream</th><th>Amount</th><th>Ref</th>
    </tr></thead><tbody>
      <?php foreach ($recent as $r): $cat = strtoupper((string)$r['category']); $metric = !empty($r['is_metric']); ?>
        <tr>
          <td class="muted" style="font-size:12.5px"><?= e($r['occurred_on']) ?></td>
          <td><?= e($cats[$cat]['label'] ?? $cat) ?><?php if ($metric): ?> <span class="muted" style="font-size:11px">(metric)</span><?php endif; ?></td>
          <td class="muted" style="font-size:12.5px"><?= e($streamLabels[strtoupper((string)$r['subtype'])] ?? $r['subtype']) ?></td>
          <td><?= $money($r['amount']) ?></td>
          <td class="muted" style="font-size:12px"><?= e($r['context']) ?>-<?= (int)$r['ref_id'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
