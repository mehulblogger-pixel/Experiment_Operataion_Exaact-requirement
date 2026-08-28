<?php
  // Connect K16 / backlog #5 — the WhatsApp / SMS / Email channel desk. Set the
  // delivery mode, see provider status, edit the (compliance-approved) templates,
  // and read the outbound log. Zero-Training: mode up top, log below, plain words.
  $mode = $mode ?? 'off'; $channels = $channels ?? []; $templates = $templates ?? [];
  $recent = $recent ?? []; $counts = $counts ?? []; $canManage = $canManage ?? false; $providers = $providers ?? [];
  $modeBlurb = ['off' => 'Recording only — nothing is sent until a channel is connected.',
                'log' => 'Simulated delivery — messages are marked “logged” for testing, not actually sent.',
                'live' => 'Live — approved templates are handed to the connected provider.'];
  $stColor = ['SENT' => '#0b7a4a', 'LOGGED' => '#0f7d7d', 'QUEUED' => '#8a6d12', 'FAILED' => '#b91c1c', 'SKIPPED' => '#777'];
  $when = fn($iso) => $iso ? e(date('d M, H:i', strtotime((string)$iso))) : '';
?>
<div class="crumbs"><a href="/">Home</a> › Channels</div>
<div class="master-head">
  <div><h1>WhatsApp, SMS &amp; email</h1>
    <p class="sub" style="margin:2px 0 0">Reach professionals where they already are — job alerts, “you're shortlisted”,
      “you're hired”, and message nudges. Nothing is sent without the person's opt-in, and (when live) only through
      an approved WhatsApp / DLT template. This is the honest seam: until a provider is connected, messages are
      <strong>recorded</strong>, never faked.</p></div>
</div>

<style>
  .ch-kpi{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0}
  .ch-kpi .k{flex:1 1 120px;padding:12px 14px;border:1px solid var(--line,#e5e7eb);border-radius:12px;background:var(--card,#fff)}
  .ch-kpi .lab{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .ch-kpi .v{font-size:22px;font-weight:700}
  .ch-card{border:1px solid var(--line,#e5e7eb);border-radius:14px;background:var(--card,#fff);padding:16px;margin-top:14px}
  .ch-mode-btn{display:inline-block;padding:9px 15px;border-radius:10px;border:1px solid var(--line,#ddd);background:var(--card,#fff);font-weight:700;cursor:pointer;margin-right:6px}
  .ch-mode-btn.on{background:#0f7d7d;border-color:#0f7d7d;color:#fff}
  .ch-prov{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700}
  .ch-prov.no{background:rgba(185,28,28,.10);color:#b91c1c}
  .ch-prov.yes{background:rgba(16,122,74,.14);color:#0b7a4a}
  .ch-tbl{width:100%;border-collapse:collapse;font-size:14px}
  .ch-tbl th,.ch-tbl td{text-align:left;padding:8px;border-bottom:1px solid var(--line,#eee);vertical-align:top}
  .ch-tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .ch-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
  .ch-appr{background:rgba(16,122,74,.14);color:#0b7a4a}
  .ch-draft{background:rgba(201,162,39,.16);color:#8a6d12}
  .ch-body{font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--muted,#555);white-space:pre-wrap}
  .ch-st{font-weight:700}
  details.ch-ed>summary{cursor:pointer;color:#0f7d7d;font-weight:600;font-size:13px;list-style:none}
  .ch-ed textarea{width:100%;min-height:70px;padding:8px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:13px}
</style>

<!-- Delivery mode -->
<div class="ch-card">
  <h2 style="margin:0 0 6px">Delivery mode</h2>
  <p class="cx-detail" style="margin:0 0 10px;color:var(--muted,#666)"><?= e($modeBlurb[$mode] ?? '') ?></p>
  <?php if ($canManage): ?>
    <form method="post" action="/connect-channels" style="display:inline">
      <input type="hidden" name="action" value="mode">
      <?php foreach (['off' => 'Off (record only)', 'log' => 'Log (simulate)', 'live' => 'Live (provider)'] as $m => $lbl): ?>
        <button class="ch-mode-btn <?= $mode === $m ? 'on' : '' ?>" name="mode" value="<?= e($m) ?>" type="submit"><?= e($lbl) ?></button>
      <?php endforeach; ?>
    </form>
  <?php else: ?><strong><?= e(strtoupper($mode)) ?></strong><?php endif; ?>
  <div style="margin-top:12px">
    <?php foreach ($channels as $ck => $cl): $ok = ($providers[$ck] ?? '') === 'connected'; ?>
      <span style="margin-right:14px"><?= e($cl) ?>: <span class="ch-prov <?= $ok ? 'yes' : 'no' ?>"><?= $ok ? 'connected' : 'not connected' ?></span></span>
    <?php endforeach; ?>
    <p class="cx-detail" style="margin:8px 0 0;color:var(--muted,#888);font-size:13px">A WhatsApp Business / SMS provider connects behind the seam (credentials + approved templates); no code change is needed here.</p>
  </div>
</div>

<!-- Outbound counts -->
<div class="ch-kpi">
  <?php foreach (['QUEUED', 'LOGGED', 'SENT', 'FAILED', 'SKIPPED'] as $s): ?>
    <div class="k"><div class="lab"><?= e(ucfirst(strtolower($s))) ?></div><div class="v" style="color:<?= e($stColor[$s] ?? '#333') ?>"><?= (int)($counts[$s] ?? 0) ?></div></div>
  <?php endforeach; ?>
</div>

<!-- Templates -->
<div class="ch-card">
  <h2 style="margin:0 0 8px">Message templates</h2>
  <div style="overflow-x:auto">
  <table class="ch-tbl">
    <thead><tr><th>Event</th><th>Channel</th><th>Message</th><th>Status</th><?php if ($canManage): ?><th>Edit</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($templates as $t): ?>
        <tr>
          <td><strong><?= e($t['tkey']) ?></strong></td>
          <td><?= e(ucfirst((string)$t['channel'])) ?></td>
          <td><div class="ch-body"><?= e((string)$t['body']) ?></div><?php if (empty($t['enabled'])): ?><span class="ch-pill ch-draft">disabled</span><?php endif; ?></td>
          <td><span class="ch-pill <?= strtoupper((string)$t['approval_status']) === 'APPROVED' ? 'ch-appr' : 'ch-draft' ?>"><?= e(ucfirst(strtolower((string)$t['approval_status']))) ?></span>
            <?php if (!empty($t['provider_ref'])): ?><div class="cx-detail" style="font-size:11px"><?= e($t['provider_ref']) ?></div><?php endif; ?></td>
          <?php if ($canManage): ?>
          <td>
            <details class="ch-ed"><summary>Edit</summary>
              <form method="post" action="/connect-channels" style="margin-top:6px">
                <input type="hidden" name="action" value="template"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <textarea name="body"><?= e((string)$t['body']) ?></textarea>
                <div style="margin-top:6px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                  <label style="font-size:13px"><input type="checkbox" name="enabled" <?= !empty($t['enabled']) ? 'checked' : '' ?>> Enabled</label>
                  <label style="font-size:13px"><input type="checkbox" name="approval_status" value="APPROVED" <?= strtoupper((string)$t['approval_status']) === 'APPROVED' ? 'checked' : '' ?>> Approved (BSP/DLT)</label>
                  <input type="text" name="provider_ref" value="<?= e((string)$t['provider_ref']) ?>" placeholder="template id / name" style="padding:6px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:13px">
                  <button class="btn" type="submit">Save</button>
                </div>
              </form>
            </details>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Outbound log -->
<div class="ch-card">
  <h2 style="margin:0 0 8px">Outbound log</h2>
  <?php if (!$recent): ?>
    <p class="cx-detail" style="margin:0;color:var(--muted,#777)">Nothing yet. Messages appear here as events fire and people opt in.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
    <table class="ch-tbl">
      <thead><tr><th>When</th><th>To</th><th>Channel</th><th>Event</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $m): ?>
          <tr>
            <td><?= $when($m['created_at']) ?></td>
            <td><?= e((string)($m['pro_name'] ?? '')) ?> <span class="cx-detail" style="font-size:11px"><?= e((string)$m['to_masked']) ?></span></td>
            <td><?= e(ucfirst((string)$m['channel'])) ?></td>
            <td><?= e((string)$m['tkey']) ?></td>
            <td><span class="ch-st" style="color:<?= e($stColor[strtoupper((string)$m['status'])] ?? '#333') ?>"><?= e(ucfirst(strtolower((string)$m['status']))) ?></span>
              <?php if (!empty($m['error'])): ?><div class="cx-detail" style="font-size:11px"><?= e((string)$m['error']) ?></div><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
