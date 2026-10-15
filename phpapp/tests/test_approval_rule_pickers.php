<?php
// ---------------------------------------------------------------------------
//  UAT 1.7.1 / 1.7.2 — the recruitment approval chain, reported by the owner:
//
//    "In recruitment Approval there are not drop down as per company setting
//     or master for department, business unit, grade, position."
//    "when we click on the add approval chain (levels) the screen goes back to
//     the top and we need to scroll down to the bottom."
//
//  Why the first one matters more than it looks. All four were free-text
//  boxes, so a rule matched only if the administrator re-typed the value
//  EXACTLY as a requisition spells it — "Quality" against "QUALITY", "Sr.
//  Inspector" against "Senior Inspector". A rule that silently matches nothing
//  is worse than no rule: the request falls through to the fallback approver,
//  the chain is skipped, and nothing anywhere says so. A picker that offers the
//  company's own values is what makes the rule match the records it is about.
//
//  A datalist rather than a <select>, deliberately: it offers the master values
//  and still accepts a value already stored, so no existing rule loses its
//  matching criteria and no migration is needed. Blank still means "applies to
//  all" — none of the four is required.
// ---------------------------------------------------------------------------

$view = file_get_contents(__DIR__ . '/../views/ops/approval_rules.php');
$lib  = file_get_contents(__DIR__ . '/../lib/recruit_approval.php');

// ---- all four match fields offer the company's values --------------------
foreach (['applies_department' => 'Department',
          'applies_sbu'        => 'Business unit',
          'applies_grade'      => 'Grade',
          'applies_position'   => 'Position'] as $field => $label) {
    t_ok(strpos($view, "'" . $field . "'") !== false,
        "$label is still asked for on the rule");
    t_ok(strpos($view, 'list="') !== false,
        "$label offers a list rather than a bare text box");
}
t_ok(substr_count($view, '$pick(') === 4,
    'all four match fields are drawn by the same picker — none left as free text');
t_ok(strpos($view, 'Leave blank to apply to all') !== false,
    'and each says that blank means "every one of them", so a rule is not narrowed by accident');

// ---- the values come from the COMPANY's masters, not a hard-coded list ----
t_ok(strpos($lib, "lk_options_or('department', DEPARTMENTS)") !== false,
    'departments come from the company master list, falling back to the shipped set');
t_ok(strpos($lib, "lk_options_or('sbu', OPS_SBUS)") !== false,
    'business units come from the company master list');
t_ok(strpos($lib, "lk_options_or('grade'") !== false,
    'grades come from the company master list where one exists');
t_ok(strpos($lib, 'FROM positions') !== false,
    'positions come from the positions register — which is what the rule actually matches against');

//  The position picker must NOT be filled from designations: a rule matching a
//  designation that is not a position would never fire, which is the exact
//  failure this change exists to prevent.
t_ok(strpos($lib, "'l_positions'") !== false, 'the position list is passed to the screen');
$posBlock = substr($lib, strpos($lib, "'l_positions'"), 400);
t_ok(strpos($posBlock, 'designation') === false,
    'and it is not padded with designations, which would create rules that never match');

// ---- a datalist never destroys a value already stored --------------------
//  Asserted on the picker itself rather than on the whole file: the screen has
//  other, legitimate <select>s (the entity, the office), and the field markup
//  is built at run time, so searching the source for the finished HTML proves
//  nothing. The property that matters is what the picker emits.
$pickStart = strpos($view, '$pick = function');
t_ok($pickStart !== false, 'the four match fields share one picker definition');
$pickBody = substr($view, $pickStart, 900);
t_ok(strpos($pickBody, '<input') !== false && strpos($pickBody, 'list="') !== false,
    'it emits an input bound to a list — a value already stored survives');
t_ok(strpos($pickBody, '<select') === false,
    'and never a closed <select>, which would silently drop a stored value that is not on the list');
t_ok(strpos($pickBody, '<datalist') !== false,
    'and it emits the list of the company\'s own values beside it');

// ---- 1.7.2 — saving a level returns to the chain, not the top ------------
t_ok(strpos($view, 'id="levels"') !== false,
    'the approval chain has an anchor to return to');
t_ok(substr_count($lib, "'#levels'") >= 2,
    'both saving and removing a level return to the chain rather than the top of the screen');
t_ok(strpos($view, 'scroll-margin-top') !== false,
    'and the anchor clears the sticky header instead of hiding under it');

// ---- nothing about who may configure has changed -------------------------
t_ok(strpos($lib, 'appr_config_can()') !== false,
    'the write still asks appr_config_can() — this change adds no permission');
t_ok(strpos($lib, "ops_require(hiring_admin_can(), 'Only an administrator can configure approval rules.')") !== false,
    'and the screen still admits only an administrator');
