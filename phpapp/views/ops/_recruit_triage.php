<?php
// ============================================================================
//  THE WORKFORCE TRIAGE PANELS — Today / Risks / Opportunities.
//
//  Lifted out of views/ops/recruitment_home.php on 2026-10-18 so that the one
//  Recruitment home can show them. Until then the product had TWO landing
//  screens under one menu item, each with a button pointing at the other:
//
//    /recruitment     "Recruitment & Workforce"   — Today / Risks / Opportunities
//    /recruitment-cc  "Recruitment Command Centre" — the pipeline and the analysis
//
//  The breadcrumb landed on one, the left menu on the other, and the owner
//  reported it as "duplication of work ... hampering launching". He was right,
//  but the two were not duplicates: one answers "who do we have and whose
//  deployment is ending", the other "what are we recruiting and how is it
//  going". Deleting either would have lost a real answer. They are now one
//  screen, and this is the half that moved.
//
//  Reads $rd — the array recruit_data() returns. Named differently from the
//  command centre's own $d on purpose: both exist on the merged screen.
// ============================================================================
//  EVERY VARIABLE HERE IS PREFIXED, because this file is INCLUDED inside the
//  command centre view, which has its own $d, $rtE and $rtRow. The first version of
//  this partial opened with `$d = $rd` and silently replaced the caller's data
//  for everything drawn after it — the page rendered the panels and then died
//  on the next section. A partial that can overwrite its host's variables is a
//  trap for whoever includes it next.
$rd = $rd ?? [];
$rtE = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
// Friendly designation label (codes like SR_INSPECTOR → "Sr. Inspector"; pass-through if already a label).
$rtDesig = fn($k) => (defined('DESIGNATIONS') && isset(DESIGNATIONS[$k])) ? DESIGNATIONS[$k] : ($k ?: '—');
$rtFdate = function ($s) { $s = substr((string)$s, 0, 10); return $s !== '' && function_exists('fdate') ? fdate($s, $s) : $s; };
// Days from today (negative = overdue/past).
$rtDays = function ($s) { $s = substr((string)$s, 0, 10); if ($s === '') return null; return (int)floor((strtotime($s) - strtotime(date('Y-m-d'))) / 86400); };
// One action row: label (link), meta, and a right-side pill.
$rtRow = function ($href, $label, $meta = '', $pill = null) use ($rtE) {
    echo '<a class="rc-row" href="' . $rtE($href) . '"><span class="rc-main"><span class="rc-lab">' . $rtE($label) . '</span>';
    if ($meta !== '') echo '<span class="rc-meta">' . $rtE($meta) . '</span>';
    echo '</span>';
    if ($pill) echo '<span class="pill ' . $rtE($pill[1]) . '">' . $rtE($pill[0]) . '</span>';
    echo '<span class="rc-go">›</span></a>';
};
// A group header inside a column.
$rtGrp = function ($icon, $title, $n) use ($rtE) {
    echo '<div class="rc-grp"><span class="rc-ic">' . $icon . '</span><span class="rc-gt">' . $rtE($title) . '</span>';
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

<div class="rc-cols">

  <!-- ============ TODAY ============ -->
  <section class="rc-col today">
    <div class="rc-head"><h2>Today</h2><span class="sub">needs action</span></div>
    <?php $rtAnyToday = ($rd['counts']['today'] ?? 0) > 0; ?>
    <?php if (!empty($rd['t_reqs'])): $rtGrp('📋', TP('requisition') . ' to work', count($rd['t_reqs']));
      foreach ($rd['t_reqs'] as $r) {
        //  PHASE 5 — the APPROVED number, not the original ask. A requirement for
        //  ten that gave up four is a requirement for six, and telling a
        //  coordinator "3 of 10" when the business only wants six any more sends
        //  them looking for four people nobody asked for.
        $qty0 = max(1, (int)$r['quantity']);
        $qty  = max(0, $qty0 - min($qty0, max(0, (int)($r['cancelled_qty'] ?? 0))));
        $filled = min((int)$r['filled'], $qty0);
        $tone = ($qty > 0 && $filled >= $qty) ? ['filled', 'p-ok'] : ($filled > 0 ? [$filled . ' of ' . $qty, 'p-warn'] : ['sourcing', 'p-info']);
        $rtRow('/requisition?id=' . (int)$r['id'], $r['req_code'] . ' · ' . $rtDesig($r['designation']) . ($qty > 1 ? ' × ' . $qty : ''),
             trim(($r['office'] ?? '') . ($r['project_site'] ? ' · ' . $r['project_site'] : '')), $tone);
      } endif; ?>
    <?php if (!empty($rd['t_interviews'])): $rtGrp('🗓️', 'Interviews', count($rd['t_interviews']));
      foreach ($rd['t_interviews'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['designation'] ?: '') . ' · ' . $rtFdate($r['interview_date']), null); endif; ?>
    <?php if (!empty($rd['t_offers'])): $rtGrp('✉️', 'Offers out — awaiting reply', count($rd['t_offers']));
      foreach ($rd['t_offers'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'], $r['designation'] ?: '', ['offered', 'p-warn']); endif; ?>
    <?php if (!empty($rd['t_joinings'])): $rtGrp('🎯', 'Hired — to onboard / mobilise', count($rd['t_joinings']));
      foreach ($rd['t_joinings'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['designation'] ?: '') . ($r['proposed_site'] ? ' · ' . $r['proposed_site'] : ''), ['hired', 'p-ok']); endif; ?>
    <?php if (!empty($rd['t_followups'])): $rtGrp('🔔', 'Follow-ups (no movement 7d+)', count($rd['t_followups']));
      foreach ($rd['t_followups'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['designation'] ?: '') . ' · since ' . $rtFdate($r['since']), ['stalled', 'p-mut']); endif; ?>
    <?php if (!empty($rd['t_expiring'])): $rtGrp('⏳', 'Deployments ending ≤30d', count($rd['t_expiring']));
      foreach ($rd['t_expiring'] as $r) { $dt = $rtDays($r['endd']);
        $rtRow('/job?id=' . (int)$r['id'], ($r['inspector'] ?: $r['job_code']),
             ($r['client'] ? $r['client'] . ' · ' : '') . 'ends ' . $rtFdate($r['endd']),
             [$dt !== null ? $dt . 'd' : '—', $dt !== null && $dt <= 7 ? 'p-bad' : 'p-warn']); } endif; ?>
    <?php if (!$rtAnyToday): ?><div class="rc-empty">Nothing outstanding today — a clean desk.</div><?php endif; ?>
  </section>

  <!-- ============ RISKS ============ -->
  <section class="rc-col risk">
    <div class="rc-head"><h2>Risks</h2><span class="sub">slipping</span></div>
    <?php $anyRisk = (($rd['counts']['risks'] ?? 0) > 0) || !empty($rd['r_interviews']); ?>
    <?php // Module 35 — interviews whose date passed with no outcome recorded (chased nowhere else).
      if (!empty($rd['r_interviews'])): $rtGrp('🎤', 'Interviews awaiting an outcome', count($rd['r_interviews']));
      foreach ($rd['r_interviews'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             ($r['stage'] . ' · interview ' . $rtFdate($r['interview_date'])), ['no outcome', 'p-bad']); endif; ?>
    <?php if (!empty($rd['r_reqs'])): $rtGrp('⚠️', TP('requisition') . ' at risk (14d+, no pipeline)', count($rd['r_reqs']));
      foreach ($rd['r_reqs'] as $r) $rtRow('/requisition?id=' . (int)$r['id'], $r['req_code'] . ' · ' . $rtDesig($r['designation']),
             ($r['office'] ?: '') . ' · ' . (int)$r['cands'] . ' candidate' . ((int)$r['cands'] === 1 ? '' : 's'), ['aging', 'p-bad']); endif; ?>
    <?php if (!empty($rd['r_urgent'])): $rtGrp('🚨', 'Deployment ending ≤7d', count($rd['r_urgent']));
      foreach ($rd['r_urgent'] as $r) { $dt = $rtDays($r['endd']);
        $rtRow('/job?id=' . (int)$r['id'], ($r['inspector'] ?: $r['job_code']), 'ends ' . $rtFdate($r['endd']),
             [$dt !== null ? $dt . 'd' : '—', 'p-bad']); } endif; ?>
    <?php if (!empty($rd['r_certs'])): $rtGrp('📜', 'Certificates expiring ≤30d', count($rd['r_certs']));
      foreach ($rd['r_certs'] as $r) $rtRow('/competence', ($r['inspector'] ?: '—'), $r['cert'] . ' · ' . $rtFdate($r['valid_to']), ['expiring', 'p-warn']); endif; ?>
    <?php if (!empty($rd['r_dormant'])): $rtGrp('💤', 'Candidates dormant (14d+)', count($rd['r_dormant']));
      foreach ($rd['r_dormant'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'],
             $r['stage'] . ' · since ' . $rtFdate($r['since']), ['dormant', 'p-mut']); endif; ?>
    <?php if (!$anyRisk): ?><div class="rc-empty">No risks flagged — nothing aging or expiring.</div><?php endif; ?>
  </section>

  <!-- ============ OPPORTUNITIES ============ -->
  <section class="rc-col opp">
    <div class="rc-head"><h2>Opportunities</h2><span class="sub">act before recruiting</span></div>
    <?php $anyOpp = ($rd['counts']['opps'] ?? 0) > 0; ?>
    <?php if (!empty($rd['o_available'])): $rtGrp('🟢', 'Available now — ready to deploy', count($rd['o_available']));
      foreach ($rd['o_available'] as $r) $rtRow('/availability', $r['name'] ?: '—', $r['designation'] ?: '', ['free', 'p-ok']); endif; ?>
    <?php if (!empty($rd['o_freeing'])): $rtGrp('📆', 'Freeing up ≤14d', count($rd['o_freeing']));
      foreach ($rd['o_freeing'] as $r) $rtRow('/job?id=' . (int)$r['id'], $r['inspector'] ?: $r['job_code'], 'frees ' . $rtFdate($r['endd']), null); endif; ?>
    <?php if (!empty($rd['o_match'])): $rtGrp('🎯', 'Dormant candidates match an open role', count($rd['o_match']));
      foreach ($rd['o_match'] as $r) $rtRow('/candidate?id=' . (int)$r['id'], $r['nm'] ?: $r['cand_code'], $r['designation'] ?: '', ['re-engage', 'p-info']); endif; ?>
    <?php if (!empty($rd['o_extensions'])): $rtGrp('🔁', 'Assignments up for extension', count($rd['o_extensions']));
      foreach ($rd['o_extensions'] as $r) $rtRow('/job?id=' . (int)$r['id'], $r['inspector'] ?: $r['job_code'],
             ($r['client'] ? $r['client'] . ' · ' : '') . 'ends ' . $rtFdate($r['endd']), ['extend?', 'p-info']); endif; ?>
    <?php if (!$anyOpp): ?><div class="rc-empty">No idle capacity or dormant matches right now.</div><?php endif; ?>
  </section>

</div>

<p class="sub" style="margin:8px 0 0;font-size:12.5px">Built from what you already have — live
  <?= $rtE(function_exists('Tlp') ? Tlp('requisition') : 'requisitions') ?>, candidates, deployments,
  availability and certificates. Nothing here is new data, and every row opens the screen where you act on it.</p>
