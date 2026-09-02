<?php
// Recruitment & Workforce — Command Centre (read-only; data from recruit_data()).
$d = $d ?? [];
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
// Friendly designation label (codes like SR_INSPECTOR → "Sr. Inspector"; pass-through if already a label).
$desig = fn($k) => (defined('DESIGNATIONS') && isset(DESIGNATIONS[$k])) ? DESIGNATIONS[$k] : ($k ?: '—');
$fdate = function ($s) { $s = substr((string)$s, 0, 10); return $s !== '' && function_exists('fdate') ? fdate($s, $s) : $s; };
// Days from today (negative = overdue/past).
$daysTo = function ($s) { $s = substr((string)$s, 0, 10); if ($s === '') return null; return (int)floor((strtotime($s) - strtotime(date('Y-m-d'))) / 86400); };
// One action row: label (link), meta, and a right-side pill.
$row = function ($href, $label, $meta = '', $pill = null) use ($e) {
    echo '<a class="rc-row" href="' . $e($href) . '"><span class="rc-main"><span class="rc-lab">' . $e($label) . '</span>';
    if ($meta !== '') echo '<span class="rc-meta">' . $e($meta) . '</span>';
    echo '</span>';
    if ($pill) echo '<span class="pill ' . $e($pill[1]) . '">' . $e($pill[0]) . '</span>';
    echo '<span class="rc-go">›</span></a>';
};
// A group header inside a column.
$grp = function ($icon, $title, $n) use ($e) {
    echo '<div class="rc-grp"><span class="rc-ic">' . $icon . '</span><span class="rc-gt">' . $e($title) . '</span>';
    if ($n) echo '<span class="rc-n">' . (int)$n . '</span>';
    echo '</div>';
};
?>
<style>
  .rc-quick{display:flex;flex-wrap:wrap;gap:8px;margin:2px 0 4px}
  .rc-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin:14px 0 4px}
  .rc-kpi{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-left:4px solid var(--brand,#1e40af);
    border-radius:12px;padding:11px 13px;text-decoration:none;color:inherit;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06))}
  .rc-kpi .v{font-size:24px;font-weight:700;line-height:1.1}
  .rc-kpi .l{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a);margin-top:3px}
  .rc-kpi.warn{border-left-color:var(--amber,#d97706)} .rc-kpi.warn .v{color:var(--amber,#d97706)}
  .rc-kpi.good{border-left-color:var(--green,#16a34a)} .rc-kpi.good .v{color:var(--green,#16a34a)}
  .rc-cols{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:20px;align-items:start}
  .rc-col{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:6px 6px 10px;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06))}
  .rc-col.today{border-top:3px solid var(--brand,#1e40af)} .rc-col.risk{border-top:3px solid var(--red,#dc2626)} .rc-col.opp{border-top:3px solid var(--green,#16a34a)}
  .rc-head{display:flex;align-items:baseline;gap:9px;padding:11px 12px 4px}
  .rc-head h2{margin:0;font-size:15.5px;font-weight:700} .rc-head .sub{font-size:12px;color:var(--muted,#656e7a)}
  .rc-grp{display:flex;align-items:center;gap:8px;padding:12px 12px 5px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted,#656e7a);font-weight:700}
  .rc-grp .rc-ic{font-size:14px} .rc-grp .rc-n{margin-left:auto;font-family:ui-monospace,monospace;font-size:11.5px;color:var(--muted,#656e7a);background:var(--soft,#f1f5f9);border-radius:20px;padding:1px 8px}
  .rc-row{display:flex;align-items:center;gap:9px;padding:8px 12px;text-decoration:none;color:inherit;border-radius:8px}
  .rc-row:hover{background:var(--soft,#f5f8fc)}
  .rc-main{min-width:0;flex:1;display:flex;flex-direction:column}
  .rc-lab{font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rc-meta{font-size:12px;color:var(--muted,#656e7a);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rc-go{color:#c7ccd4;font-size:16px} .rc-row .pill{flex:0 0 auto}
  .rc-empty{padding:8px 12px 12px;color:var(--muted,#656e7a);font-size:12.5px}
  .rc-note{background:#fffdf5;border:1px solid #f0e6c8;border-radius:12px;padding:11px 15px;font-size:12.5px;color:#6b5d2f;margin-top:18px}
  @media (max-width:980px){ .rc-kpis{grid-template-columns:repeat(3,1fr)} .rc-cols{grid-template-columns:1fr} }
  @media (max-width:560px){ .rc-kpis{grid-template-columns:repeat(2,1fr)} }
</style>

<div class="crumbs"><a href="/">Home</a> › <a href="/operations">Operations</a> › Recruitment</div>
<div class="master-head">
  <div>
    <h1>Recruitment &amp; Workforce</h1>
    <p class="sub" style="margin:2px 0 0">One place for what needs action today, what's at risk, and who you can deploy before recruiting outside. Every figure is live and scoped to your branches.</p>
    <?php // Phase 6 — engagement mode: how this agency hires. A setting, not re-decided per candidate.
    $mode = function_exists('recruit_engagement_mode') ? recruit_engagement_mode() : 'BOTH';
    $modeShort = ['BOTH' => 'Recruitment + Manpower', 'RECRUITMENT' => 'Recruitment only', 'MANPOWER' => 'Manpower only'];
    ?>
    <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <span class="pill p-info" style="font-size:11px" title="How this installation engages people">Engagement: <?= $e($modeShort[$mode] ?? $mode) ?></span>
      <?php if (function_exists('is_admin_level') && is_admin_level()): ?>
      <details style="display:inline-block">
        <summary style="cursor:pointer;font-size:12px;color:var(--muted,#656e7a)">change</summary>
        <form method="post" action="/recruit-config" style="margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <select class="form-control" name="engagement_mode" style="max-width:340px;font-size:13px">
            <?php foreach (RECRUIT_ENGAGEMENT_MODES as $k => $v): ?><option value="<?= $e($k) ?>"<?= $mode === $k ? ' selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?>
          </select>
          <button class="btn secondary" type="submit" style="padding:4px 12px">Save</button>
        </form>
        <p class="muted" style="font-size:11.5px;margin:5px 0 0;max-width:420px">Sets the default hire type on every candidate. It never removes an option — a coordinator can still pick the other on any hire.</p>
      </details>
      <?php endif; ?>
    </div>
  </div>
  <div class="row-actions rc-quick">
    <?php if (can('mod.hiring.view')): ?>
      <a class="btn primary" href="/recruitment-cc">📊 Command centre</a>
      <a class="btn secondary" href="/requisition-new">＋ New requirement</a>
      <a class="btn secondary" href="/candidate-new">＋ Add candidate</a>
      <a class="btn secondary" href="/requisitions">Requirements</a>
      <a class="btn secondary" href="/candidates">Candidates</a>
      <a class="btn secondary" href="/candidate-pool">🔗 Candidate pool</a>
    <?php endif; ?>
  </div>
</div>

<div class="rc-kpis">
  <a class="rc-kpi" href="/requisitions"><div class="v"><?= (int)($d['open_reqs'] ?? 0) ?></div><div class="l">Open requirements</div></a>
  <a class="rc-kpi" href="/candidates"><div class="v"><?= (int)($d['pipeline'] ?? 0) ?></div><div class="l">In pipeline</div></a>
  <a class="rc-kpi" href="/candidates"><div class="v"><?= (int)($d['interviews'] ?? 0) ?></div><div class="l">Interviews ≤7d</div></a>
  <a class="rc-kpi warn" href="/candidates"><div class="v"><?= (int)($d['offers'] ?? 0) ?></div><div class="l">Offers out</div></a>
  <a class="rc-kpi warn" href="/deputations"><div class="v"><?= (int)($d['expiring'] ?? 0) ?></div><div class="l">Expiring ≤30d</div></a>
  <a class="rc-kpi good" href="/availability"><div class="v"><?= (int)($d['available'] ?? 0) ?></div><div class="l">Available now</div></a>
</div>

<div class="rc-cols">

  <!-- ============ TODAY ============ -->
  <section class="rc-col today">
    <div class="rc-head"><h2>Today</h2><span class="sub">needs action</span></div>
    <?php $anyToday = ($d['counts']['today'] ?? 0) > 0; ?>
    <?php if (!empty($d['t_reqs'])): $grp('📋', 'Requirements to work', count($d['t_reqs']));
      foreach ($d['t_reqs'] as $r) {
        $qty = max(1, (int)$r['quantity']); $filled = (int)$r['filled'];
        $tone = $filled >= $qty ? ['filled', 'p-ok'] : ($filled > 0 ? [$filled . ' of ' . $qty, 'p-warn'] : ['sourcing', 'p-info']);
        $row('/requisition?id=' . (int)$r['id'], $r['req_code'] . ' · ' . $desig($r['designation']) . ($qty > 1 ? ' × ' . $qty : ''),
             trim(($r['office'] ?? '') . ($r['project_site'] ? ' · ' . $r['project_site'] : '')), $tone);
      } endif; ?>
    <?php if (!empty($d['t_interviews'])): $grp('🗓️', 'Interviews', count($d['t_interviews']));
      foreach ($d['t_interviews'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['designation'] ?: '') . ' · ' . $fdate($r['interview_date']), null); endif; ?>
    <?php if (!empty($d['t_offers'])): $grp('✉️', 'Offers out — awaiting reply', count($d['t_offers']));
      foreach ($d['t_offers'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'], $r['designation'] ?: '', ['offered', 'p-warn']); endif; ?>
    <?php if (!empty($d['t_joinings'])): $grp('🎯', 'Hired — to onboard / mobilise', count($d['t_joinings']));
      foreach ($d['t_joinings'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['designation'] ?: '') . ($r['proposed_site'] ? ' · ' . $r['proposed_site'] : ''), ['hired', 'p-ok']); endif; ?>
    <?php if (!empty($d['t_followups'])): $grp('🔔', 'Follow-ups (no movement 7d+)', count($d['t_followups']));
      foreach ($d['t_followups'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['designation'] ?: '') . ' · since ' . $fdate($r['since']), ['stalled', 'p-mut']); endif; ?>
    <?php if (!empty($d['t_expiring'])): $grp('⏳', 'Deployments ending ≤30d', count($d['t_expiring']));
      foreach ($d['t_expiring'] as $r) { $dt = $daysTo($r['endd']);
        $row('/job?id=' . (int)$r['id'], ($r['inspector'] ?: $r['job_code']),
             ($r['client'] ? $r['client'] . ' · ' : '') . 'ends ' . $fdate($r['endd']),
             [$dt !== null ? $dt . 'd' : '—', $dt !== null && $dt <= 7 ? 'p-bad' : 'p-warn']); } endif; ?>
    <?php if (!$anyToday): ?><div class="rc-empty">Nothing outstanding today — a clean desk.</div><?php endif; ?>
  </section>

  <!-- ============ RISKS ============ -->
  <section class="rc-col risk">
    <div class="rc-head"><h2>Risks</h2><span class="sub">slipping</span></div>
    <?php $anyRisk = (($d['counts']['risks'] ?? 0) > 0) || !empty($d['r_interviews']); ?>
    <?php // Module 35 — interviews whose date passed with no outcome recorded (chased nowhere else).
      if (!empty($d['r_interviews'])): $grp('🎤', 'Interviews awaiting an outcome', count($d['r_interviews']));
      foreach ($d['r_interviews'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['stage'] . ' · interview ' . $fdate($r['interview_date'])), ['no outcome', 'p-bad']); endif; ?>
    <?php if (!empty($d['r_reqs'])): $grp('⚠️', 'Requirements at risk (14d+, no pipeline)', count($d['r_reqs']));
      foreach ($d['r_reqs'] as $r) $row('/requisition?id=' . (int)$r['id'], $r['req_code'] . ' · ' . $desig($r['designation']),
             ($r['office'] ?: '') . ' · ' . (int)$r['cands'] . ' candidate' . ((int)$r['cands'] === 1 ? '' : 's'), ['aging', 'p-bad']); endif; ?>
    <?php if (!empty($d['r_urgent'])): $grp('🚨', 'Deployment ending ≤7d', count($d['r_urgent']));
      foreach ($d['r_urgent'] as $r) { $dt = $daysTo($r['endd']);
        $row('/job?id=' . (int)$r['id'], ($r['inspector'] ?: $r['job_code']), 'ends ' . $fdate($r['endd']),
             [$dt !== null ? $dt . 'd' : '—', 'p-bad']); } endif; ?>
    <?php if (!empty($d['r_certs'])): $grp('📜', 'Certificates expiring ≤30d', count($d['r_certs']));
      foreach ($d['r_certs'] as $r) $row('/competence', ($r['inspector'] ?: '—'), $r['cert'] . ' · ' . $fdate($r['valid_to']), ['expiring', 'p-warn']); endif; ?>
    <?php if (!empty($d['r_dormant'])): $grp('💤', 'Candidates dormant (14d+)', count($d['r_dormant']));
      foreach ($d['r_dormant'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             $r['stage'] . ' · since ' . $fdate($r['since']), ['dormant', 'p-mut']); endif; ?>
    <?php if (!$anyRisk): ?><div class="rc-empty">No risks flagged — nothing aging or expiring.</div><?php endif; ?>
  </section>

  <!-- ============ OPPORTUNITIES ============ -->
  <section class="rc-col opp">
    <div class="rc-head"><h2>Opportunities</h2><span class="sub">act before recruiting</span></div>
    <?php $anyOpp = ($d['counts']['opps'] ?? 0) > 0; ?>
    <?php if (!empty($d['o_available'])): $grp('🟢', 'Available now — ready to deploy', count($d['o_available']));
      foreach ($d['o_available'] as $r) $row('/availability', $r['name'] ?: '—', $r['designation'] ?: '', ['free', 'p-ok']); endif; ?>
    <?php if (!empty($d['o_freeing'])): $grp('📆', 'Freeing up ≤14d', count($d['o_freeing']));
      foreach ($d['o_freeing'] as $r) $row('/job?id=' . (int)$r['id'], $r['inspector'] ?: $r['job_code'], 'frees ' . $fdate($r['endd']), null); endif; ?>
    <?php if (!empty($d['o_match'])): $grp('🎯', 'Dormant candidates match an open role', count($d['o_match']));
      foreach ($d['o_match'] as $r) $row('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'], $r['designation'] ?: '', ['re-engage', 'p-info']); endif; ?>
    <?php if (!empty($d['o_extensions'])): $grp('🔁', 'Assignments up for extension', count($d['o_extensions']));
      foreach ($d['o_extensions'] as $r) $row('/job?id=' . (int)$r['id'], $r['inspector'] ?: $r['job_code'],
             ($r['client'] ? $r['client'] . ' · ' : '') . 'ends ' . $fdate($r['endd']), ['extend?', 'p-info']); endif; ?>
    <?php if (!$anyOpp): ?><div class="rc-empty">No idle capacity or dormant matches right now.</div><?php endif; ?>
  </section>

</div>

<div class="rc-note"><b>Built from what you already have.</b> This command centre reads your live requirements,
  candidates, deployments, availability and certificates — it adds no new data and changes nothing.
  Every row opens the existing screen where you act on it.</div>
