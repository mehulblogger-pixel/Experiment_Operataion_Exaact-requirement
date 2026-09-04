<?php
// Requirement reuse (duplicate + templates) and configurable match weights (K0+).
t_section('connect requirement reuse + match weights (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    if (function_exists('connect_market_migrate')) connect_market_migrate();
    connect_reqtemplates_migrate();
    $party = 7101;

    // a source requirement + a crew position
    $rid = cx_requirement_create(['title'=>'Welding inspector','poster_party_id'=>$party,'poster_name'=>'Acme',
        'discipline_code'=>'WELD','location'=>'Dahej','positions'=>2,'rate_min'=>4000,'rate_max'=>6000,'description'=>'PV FAT'], true);
    t_ok($rid>0, 'a source requirement is created (OPEN)');
    if (function_exists('cx_position_add')) cx_position_add($rid, ['role'=>'Welding inspector','quantity'=>2]);

    // --- Duplicate → a fresh DRAFT, shape copied, award/status NOT copied -------
    $dup = connect_requirement_duplicate($rid, $party);
    t_ok($dup>0 && $dup!==$rid, 'duplicate creates a new requirement');
    $d = cx_requirement_get($dup);
    t_eq(strtoupper((string)$d['status']), 'DRAFT', 'the duplicate is a DRAFT, not posted');
    t_eq((int)$d['awarded_application_id'], 0, 'the duplicate carries no award');
    t_eq($d['location'], 'Dahej', 'the duplicate copies the shape (location)');
    t_eq((int)$d['positions'], 2, 'the duplicate copies positions count');
    t_ok(stripos($d['title'],'Copy of') === 0, 'the duplicate title is prefixed "Copy of"');
    t_eq((int)$d['poster_party_id'], $party, 'the duplicate belongs to the same client');
    if (function_exists('cx_positions_for')) t_ok(count(cx_positions_for($dup)) >= 1, 'crew positions are copied to the duplicate');

    // --- Templates: save from a requirement, then create from it ---------------
    [$tok,,$tid] = connect_reqtemplate_save_from_requirement($party, $rid, 'PV welding inspector');
    t_ok($tok && $tid>0, 'a requirement is saved as a template');
    t_eq(count(connect_reqtemplates_for($party)), 1, 'the template is listed for the owner');
    t_eq(count(connect_reqtemplates_for(9999)), 0, 'another party does not see it');
    $fromT = connect_reqtemplate_create_requirement($tid, $party, [], true);
    t_ok($fromT>0, 'a new requirement is created from the template');
    $ft = cx_requirement_get($fromT);
    t_eq($ft['discipline_code'], 'WELD', 'the template carries the discipline');
    t_eq(strtoupper((string)$ft['status']), 'OPEN', 'template posting goes OPEN when requested');
    connect_reqtemplate_delete($tid, 9999); t_eq(count(connect_reqtemplates_for($party)), 1, 'another party cannot delete the template');
    connect_reqtemplate_delete($tid, $party); t_eq(count(connect_reqtemplates_for($party)), 0, 'owner deletes the template');

    // ============ Configurable match weights (§23) =============================
    connect_match_weights_reset_cache();
    if (function_exists('setting_set')) setting_set('connect_match_weights', '');
    connect_match_weights_reset_cache();
    $def = connect_match_weights_defaults();
    $w = connect_match_weights();
    t_eq((int)$w['skills'], (int)$def['skills'], 'defaults are used when nothing is saved');
    t_eq((int)$w['credentials'], 15, 'credential cap default is the historical 15');
    t_eq((int)$w['elig_eligible'], 15, 'eligible points default is the historical 15');

    // saving changes the live weights (clamped)
    connect_match_weights_save(['skills'=>50,'credentials'=>200,'elig_eligible'=>-5]);
    $w2 = connect_match_weights();
    t_eq((int)$w2['skills'], 50, 'a saved weight is used');
    t_eq((int)$w2['credentials'], 100, 'a runaway weight is clamped to 100');
    t_eq((int)$w2['elig_eligible'], 0, 'a negative weight is clamped to 0');
    // unspecified keys keep their defaults
    t_eq((int)$w2['reputation'], (int)$def['reputation'], 'an unspecified weight keeps its default');

    // the scorer honours the weights: a fresh professional with a matching skill
    db()->prepare("INSERT INTO cx_professionals (email,name,skills,disciplines,is_active,created_at) VALUES ('w@pro.test','W','welding inspection','WELD',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    $cand = ['id'=>$pro,'kind'=>'professional','skills'=>'welding inspection','disciplines'=>'WELD'];
    connect_match_weights_save(['skills'=>40]);
    $s40 = cx_match_score($cand, ['title'=>'welding inspection','start_date'=>''])['parts']['skills'];
    connect_match_weights_save(['skills'=>80]);
    $s80 = cx_match_score($cand, ['title'=>'welding inspection','start_date'=>''])['parts']['skills'];
    t_ok($s80 > $s40, 'raising the skills weight raises the skills sub-score');

    // reset restores defaults
    if (function_exists('setting_set')) setting_set('connect_match_weights', '');
    connect_match_weights_reset_cache();
    t_eq((int)connect_match_weights()['skills'], (int)$def['skills'], 'reset restores the default skills weight');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
