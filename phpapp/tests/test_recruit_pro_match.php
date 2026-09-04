<?php
// P1b — automated marketplace matching for a recruitment requisition. recruit_pro_fit_score()
// scores a marketplace professional (cx_professionals) against a requisition with the same
// explainable ['score','factors'] shape as the candidate fit, and recruit_pro_pool() returns
// the ranked, filtered, capped shortlist. Read-only — it reads the bench, changes nothing.
t_section('marketplace auto-match for a requisition (P1b)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate();

    // A welding-inspection requisition in Jamnagar, billing 12000/day.
    $req = ['discipline' => 'Welding Inspection', 'skills' => 'CSWIP, welding, NDT', 'designation' => 'Welding Inspector',
            'deploy_location' => 'Jamnagar', 'project_site' => 'Jamnagar Refinery', 'billing_rate' => 12000, 'sbu' => 'IND'];

    // A strong-fit professional: right discipline+skills, available, verified, pan-India, priced under.
    db()->prepare("INSERT INTO cx_professionals (email,name,headline,disciplines,skills,work_types,base_city,pan_india,verification_tier,availability,day_rate_min,is_active,created_at)
                   VALUES ('strong.p1b@ex.com','Strong Fit','Welding & CSWIP inspector','Welding Inspection','CSWIP, welding, NDT, piping','FREELANCE','Vadodara',1,'verified','AVAILABLE',9000,1,?)")->execute([date('c')]);
    $strong = (int)db()->lastInsertId();
    // A weak-fit professional: unrelated discipline, busy, unverified, expensive.
    db()->prepare("INSERT INTO cx_professionals (email,name,headline,disciplines,skills,work_types,base_city,pan_india,verification_tier,availability,day_rate_min,is_active,created_at)
                   VALUES ('weak.p1b@ex.com','Weak Fit','Electrical designer','Electrical Design','AutoCAD, wiring','CONTRACT','Chennai',0,'registered','BUSY',30000,1,?)")->execute([date('c')]);
    $weak = (int)db()->lastInsertId();
    // An inactive strong-fit professional: must be excluded from the pool.
    db()->prepare("INSERT INTO cx_professionals (email,name,disciplines,skills,verification_tier,availability,day_rate_min,is_active,created_at)
                   VALUES ('inactive.p1b@ex.com','Inactive Ace','Welding Inspection','CSWIP, welding, NDT','verified','AVAILABLE',8000,0,?)")->execute([date('c')]);
    $inactive = (int)db()->lastInsertId();

    $sStrong = recruit_pro_fit_score(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$strong]), $req);
    $sWeak   = recruit_pro_fit_score(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$weak]),   $req);
    t_ok($sStrong['score'] >= 80, 'a strong-fit professional scores high (>=80): got ' . $sStrong['score']);
    t_ok($sWeak['score'] < 55, 'a weak-fit professional scores low (<55): got ' . $sWeak['score']);
    t_ok($sStrong['score'] > $sWeak['score'], 'the strong fit outranks the weak fit');
    // the factor breakdown is explainable (same shape as recruit_fit_score)
    $labels = array_column($sStrong['factors'], 'label');
    t_ok(in_array('Discipline', $labels, true) && in_array('Availability', $labels, true) && in_array('Verification', $labels, true),
        'the fit is explainable — discipline, availability and verification factors are present');
    t_ok(recruit_fit_band($sStrong['score'])[0] === 'Strong', 'recruit_fit_band renders the professional score unchanged');

    // the pool ranks strongest-first, filters out the weak one, and excludes the inactive pro
    $pool = recruit_pro_pool($req, 5, 55);
    $ids = array_map(fn($p) => (int)$p['id'], $pool);
    t_ok(in_array($strong, $ids, true), 'the pool includes the strong-fit professional');
    t_ok(!in_array($weak, $ids, true), 'the pool excludes the below-threshold weak fit');
    t_ok(!in_array($inactive, $ids, true), 'the pool excludes an inactive professional even if a strong fit');
    t_ok(!empty($pool) && (int)$pool[0]['id'] === $strong, 'the strongest fit is ranked first');
    t_ok(count($pool) <= 5, 'the pool is capped at the limit');
    t_ok(isset($pool[0]['fit']['score']), 'each pooled professional carries its fit breakdown');

    // read-only: scoring/pooling mutated no professional row
    t_eq((string)ops_val("SELECT availability FROM cx_professionals WHERE id=?", [$strong]), 'AVAILABLE', 'the professional row is untouched by matching');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
