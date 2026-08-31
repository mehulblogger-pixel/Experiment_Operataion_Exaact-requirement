<?php
// ============================================================================
//  CONNECT — Role-based client-portal dashboard  (K0+, additive)
//
//  A client-portal user sees tiles appropriate to their ROLE (technical manager,
//  project manager, commercial), every value COMPUTED LIVE from that client's own
//  data — never hard-coded. Scoped strictly to the signed-in client's party, so
//  no client sees another's figures. Reuses the existing tables (cx_requirements,
//  cx_applications, cx_pro_certs, jobs/calls/job_visits, dep_att_approval,
//  cx_engagements, billable_events, report_docs). DB-agnostic (MySQL + SQLite):
//  date bounds are computed in PHP and bound as parameters.
// ============================================================================

/** The dashboard role for a portal user, from role_preset then perms. */
function connect_client_dash_role($user) {
    $rp = strtoupper((string)($user['role_preset'] ?? ''));
    if (in_array($rp, ['TECHNICAL', 'QUALITY'], true)) return 'technical';
    if ($rp === 'PROJECT')    return 'project';
    if ($rp === 'COMMERCIAL') return 'commercial';
    if ($rp === 'READONLY')   return 'site';
    return 'admin';
}

/** Indian digit grouping (lakh/crore): 1250000 -> "12,50,000". */
function connect_inr_group($n) {
    $n = (string)(int)$n; $neg = false;
    if ($n[0] === '-') { $neg = true; $n = substr($n, 1); }
    if (strlen($n) <= 3) return ($neg ? '-' : '') . $n;
    $last3 = substr($n, -3); $rest = substr($n, 0, -3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return ($neg ? '-' : '') . $rest . ',' . $last3;
}

/** The role tiles for one client party. Every value is a live COUNT/SUM. */
function connect_client_dash($party, $role) {
    $party = (int)$party;
    $today = date('Y-m-d'); $in60 = date('Y-m-d', strtotime('+60 days'));
    $n = function ($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    $jobsOf = "(SELECT j.id FROM jobs j JOIN calls c ON c.id=j.call_id WHERE c.client_id=" . $party . ")";

    if ($role === 'technical') {
        return [
            ['label' => 'My Open Requirements', 'value' => $n("SELECT COUNT(*) FROM cx_requirements WHERE poster_party_id=? AND status='OPEN'", [$party]), 'url' => '/portal/hire', 'tone' => ''],
            ['label' => 'Matching in Progress', 'value' => $n("SELECT COUNT(*) FROM cx_requirements r WHERE r.poster_party_id=? AND r.status='OPEN' AND (SELECT COUNT(*) FROM cx_applications a WHERE a.requirement_id=r.id)>0", [$party]), 'url' => '/portal/hire', 'tone' => 'info'],
            ['label' => 'Shortlisted', 'value' => $n("SELECT COUNT(*) FROM cx_applications a JOIN cx_requirements r ON r.id=a.requirement_id WHERE r.poster_party_id=? AND a.status='SHORTLISTED'", [$party]), 'url' => '/portal/hire', 'tone' => 'ok'],
            ['label' => 'Resource Requests Awaiting Action', 'value' => $n("SELECT COUNT(*) FROM cx_applications a JOIN cx_requirements r ON r.id=a.requirement_id WHERE r.poster_party_id=? AND a.status='APPLIED'", [$party]), 'url' => '/portal/hire', 'tone' => 'warn'],
            ['label' => 'Pending Technical Reviews', 'value' => $n("SELECT COUNT(*) FROM report_docs WHERE client_id=? AND status='ISSUED' AND COALESCE(client_decision,'')=''", [$party]), 'url' => '/portal/reports', 'tone' => 'warn'],
            ['label' => 'Expiring Resource Credentials', 'value' => $n("SELECT COUNT(*) FROM cx_pro_certs pc WHERE pc.pro_id IN (SELECT a.applicant_professional_id FROM cx_applications a JOIN cx_requirements r ON r.id=a.requirement_id WHERE r.poster_party_id=?) AND pc.expiry_date>=? AND pc.expiry_date<=?", [$party, $today, $in60]), 'url' => '/portal/find', 'tone' => 'warn'],
        ];
    }
    if ($role === 'project') {
        return [
            ['label' => 'Active Projects', 'value' => $n("SELECT COUNT(*) FROM jobs WHERE id IN $jobsOf AND dep_status='ACTIVE'"), 'url' => '/portal/deputations', 'tone' => ''],
            ['label' => 'Resources Deployed', 'value' => $n("SELECT COUNT(*) FROM job_visits WHERE status='ACTIVE' AND job_id IN $jobsOf"), 'url' => '/portal/deputations', 'tone' => 'ok'],
            ['label' => 'Upcoming Deployments', 'value' => $n("SELECT COUNT(*) FROM job_visits WHERE status='PLANNED' AND visit_date>? AND job_id IN $jobsOf", [$today]), 'url' => '/portal/deputations', 'tone' => 'info'],
            ['label' => 'Pending Mobilizations', 'value' => $n("SELECT COUNT(*) FROM jobs WHERE id IN $jobsOf AND dep_status='MOB_PENDING'"), 'url' => '/portal/deputations', 'tone' => 'warn'],
            ['label' => 'Schedule Conflicts', 'value' => $n("SELECT COUNT(*) FROM job_visits WHERE status='CONFLICT' AND job_id IN $jobsOf"), 'url' => '/portal/deputations', 'tone' => 'bad'],
            ['label' => 'Pending Site Actions', 'value' => $n("SELECT COUNT(*) FROM dep_att_approval WHERE client_id=? AND status='CLIENT_REVIEW'", [$party]), 'url' => '/portal/deputations', 'tone' => 'warn'],
        ];
    }
    if ($role === 'commercial') {
        return [
            ['label' => 'Pending Commercial Review', 'value' => $n("SELECT COUNT(*) FROM billable_events WHERE party_id=? AND status='PENDING'", [$party]), 'url' => '/portal/invoices', 'tone' => 'warn'],
            ['label' => 'Draft Engagements', 'value' => $n("SELECT COUNT(*) FROM cx_engagements WHERE poster_party_id=? AND status='BOOKED'", [$party]), 'url' => '/portal/hire', 'tone' => 'info'],
            ['label' => 'Pending Client Approval', 'value' => $n("SELECT COUNT(*) FROM dep_att_approval WHERE client_id=? AND status='SUBMITTED'", [$party]), 'url' => '/portal/deputations', 'tone' => 'warn'],
            ['label' => 'Invoices Awaiting Review', 'value' => $n("SELECT COUNT(*) FROM billable_events WHERE party_id=? AND status='APPROVED'", [$party]), 'url' => '/portal/invoices', 'tone' => 'warn'],
            ['label' => 'Approved Invoice Value', 'value' => '₹' . connect_inr_group($n("SELECT COALESCE(SUM(amount),0) FROM billable_events WHERE party_id=? AND status='BILLED'", [$party])), 'url' => '/portal/invoices', 'tone' => 'ok', 'money' => true],
        ];
    }
    return [
        ['label' => 'Open Requirements', 'value' => $n("SELECT COUNT(*) FROM cx_requirements WHERE poster_party_id=? AND status='OPEN'", [$party]), 'url' => '/portal/hire', 'tone' => ''],
        ['label' => 'Reports Issued', 'value' => $n("SELECT COUNT(*) FROM report_docs WHERE client_id=? AND finalized=1", [$party]), 'url' => '/portal/reports', 'tone' => 'ok'],
    ];
}

/** Render the role tiles + a recent-activity feed (reuses the activity spine). */
function connect_client_dash_render($party, $user) {
    $role = connect_client_dash_role($user);
    $tiles = connect_client_dash($party, $role);
    $roleLabel = ['technical' => 'Technical Manager', 'project' => 'Project Manager', 'commercial' => 'Commercial', 'site' => 'Site', 'admin' => 'Administrator'][$role];
    $acts = [];
    try { $acts = ops_all("SELECT kind, subject, occurred_at FROM activities WHERE partner_id=? ORDER BY occurred_at DESC, id DESC LIMIT 8", [(int)$party]) ?: []; } catch (Throwable $e) {}
    ?>
    <div class="ccd">
      <div class="ccd-head"><span class="ccd-role"><?= e($roleLabel) ?> dashboard</span></div>
      <div class="ccd-tiles">
        <?php foreach ($tiles as $t): ?>
          <a class="ccd-tile <?= e($t['tone'] ?? '') ?>" href="<?= e($t['url'] ?? '#') ?>">
            <div class="ccd-val"><?= is_string($t['value']) ? e($t['value']) : (int)$t['value'] ?></div>
            <div class="ccd-lab"><?= e($t['label']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($acts): ?>
        <div class="ccd-acts">
          <div class="ccd-acts-h">Recent activity</div>
          <?php foreach ($acts as $a): ?>
            <div class="ccd-act"><span class="ccd-act-t"><?= e(substr((string)$a['occurred_at'], 0, 10)) ?></span> <?= e($a['subject']) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <style>
      .ccd{margin:6px 0 20px}
      .ccd-head{margin:0 0 10px}
      .ccd-role{font-weight:700;font-size:15px;color:var(--ink,#17242b)}
      .ccd-tiles{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
      .ccd-tile{display:block;background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:13px;padding:15px 16px;text-decoration:none;color:inherit;border-left:4px solid #c9d3d2}
      .ccd-tile.ok{border-left-color:#0f7d5a}.ccd-tile.warn{border-left-color:#c98a12}.ccd-tile.bad{border-left-color:#9a2a2a}.ccd-tile.info{border-left-color:#1858a8}
      .ccd-val{font-size:26px;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
      .ccd-lab{color:var(--muted,#5b6b72);font-size:12.5px;margin-top:2px}
      .ccd-acts{margin-top:16px;background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:12px;padding:12px 15px}
      .ccd-acts-h{font-weight:700;font-size:13px;margin-bottom:6px}
      .ccd-act{font-size:13px;padding:4px 0;border-bottom:1px solid var(--line,#eef1f0)}
      .ccd-act:last-child{border-bottom:0}
      .ccd-act-t{color:var(--muted,#5b6b72);font-family:ui-monospace,monospace;font-size:11.5px;margin-right:8px}
    </style>
    <?php
}
