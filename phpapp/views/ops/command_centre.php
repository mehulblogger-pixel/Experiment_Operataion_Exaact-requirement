<?php
// Phase 3 §20 — Command Centre. Business, money and platform health as three separate bands
// (never blended into one score). Read-only; composes attention_summary + financial_rollup + system_status.
$sevTone = ['ok'=>'#15803d', 'warn'=>'#b45309', 'bad'=>'var(--bad,#c0392b)', 'info'=>'#2563eb', 'critical'=>'var(--bad,#c0392b)'];
$m = fn($n) => function_exists('fmoney_short') ? fmoney_short($n) : ('₹' . number_format((float)$n));
$worst = $health_worst ?? 'ok';
$worstLabel = ['ok'=>'All systems healthy', 'warn'=>'Some items need attention', 'bad'=>'Action needed'][$worst] ?? 'Healthy';
$attnHi = 0; foreach (($business ?? []) as $b) if (($b['sev'] ?? '') === 'bad' || ($b['sev'] ?? '') === 'warn') $attnHi++;
?>
<div class="crumbs"><a href="/">Home</a> › Command Centre</div>
<div class="master-head">
  <div>
    <h1>Command Centre</h1>
    <p class="sub" style="margin:2px 0 0">The state of the business at a glance — what needs attention, where the money is, and whether the platform is healthy.</p>
  </div>
</div>

<?php // ---- Band 1: Business — what needs attention -------------------------- ?>
<div class="panel" style="margin-top:16px">
  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px">
    <h3 class="tab-sub" style="margin:0">Needs attention</h3>
    <span class="muted" style="font-size:12.5px"><?= $attnHi ? $attnHi . ' pressing' : 'nothing pressing' ?></span>
  </div>
  <?php if (empty($business)): ?>
    <p class="muted" style="margin:8px 0 0">Nothing across leads, expiries, receivables, certificates or interviews needs attention right now.</p>
  <?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px">
      <?php foreach ($business as $b): $c = $sevTone[$b['sev'] ?? 'info'] ?? $sevTone['info']; ?>
        <a href="<?= e($b['url']) ?>" style="text-decoration:none;flex:1 1 170px;min-width:170px;border:1px solid var(--line,#e5e7eb);border-left:3px solid <?= $c ?>;border-radius:8px;padding:11px 13px;background:var(--soft,#f8fafc);color:inherit">
          <div style="font-size:22px;font-weight:700;color:<?= $c ?>;font-variant-numeric:tabular-nums"><?= $b['value'] !== null ? e($b['value']) : (int)$b['n'] ?></div>
          <div style="font-weight:600;font-size:13px;margin-top:1px"><?= e($b['label']) ?></div>
          <?php if (!empty($b['sub'])): ?><div class="muted" style="font-size:11.5px"><?= e($b['sub']) ?></div><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php // ---- Band 2: Money — the §27 stream ---------------------------------- ?>
<?php if (!empty($money)): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Money — company</h3>
  <div style="display:flex;flex-wrap:wrap;gap:22px;margin-top:6px">
    <?php foreach ([['Committed','committed','#2563eb'],['Billed (net)','net_billed','#0f6e7e'],['Received','received','#15803d'],['Outstanding','outstanding','#b45309']] as [$lab,$k,$c]): ?>
      <div>
        <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em"><?= e($lab) ?></div>
        <div style="font-weight:700;font-size:20px;color:<?= $c ?>;font-variant-numeric:tabular-nums"><?= e($m($money[$k] ?? 0)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="margin:10px 0 0;font-size:12px">Committed = accepted quotations · Billed = issued invoices (net of cancellations) · from the one financial-event stream (§27). <a href="/mis">Full dashboard →</a></p>
</div>
<?php endif; ?>

<?php // ---- Band 3: Platform health (kept separate from the business, §20/§21) ?>
<div class="panel" style="margin-top:16px">
  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px">
    <h3 class="tab-sub" style="margin:0">Platform health</h3>
    <span class="pill" style="background:<?= $sevTone[$worst] ?? $sevTone['ok'] ?>;color:#fff;font-size:11px;padding:2px 9px"><?= e($worstLabel) ?></span>
  </div>
  <?php if (empty($health)): ?>
    <p class="muted" style="margin:8px 0 0">No health checks are reporting.</p>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;margin-top:6px">
      <?php foreach ($health as $h): $c = $sevTone[$h['severity'] ?? 'ok'] ?? $sevTone['ok']; ?>
        <a href="<?= e($h['url'] ?? '#') ?>" style="display:flex;align-items:center;gap:12px;padding:8px 4px;border-top:1px solid var(--line,#e5e7eb);text-decoration:none;color:inherit">
          <span style="width:9px;height:9px;border-radius:50%;background:<?= $c ?>;flex:none"></span>
          <span style="flex:1;min-width:0"><span style="font-weight:600;font-size:13.5px"><?= e($h['label']) ?></span>
            <span class="muted" style="display:block;font-size:11.5px"><?= e($h['detail'] ?? '') ?></span></span>
          <span style="font-size:12.5px;color:<?= $c ?>;font-weight:600"><?= e($h['headline'] ?? '') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="margin:8px 0 0;font-size:12px">Business KPIs (above) and platform health (here) are kept separate on purpose. <a href="/system-status">Full health board →</a></p>
  <?php endif; ?>
</div>
