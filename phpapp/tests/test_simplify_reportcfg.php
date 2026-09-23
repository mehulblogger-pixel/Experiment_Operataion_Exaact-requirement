<?php
// Simplification, step 3 — set-once report configuration moves out of Reporting
// into Admin, so Reporting stays about writing reports. Outright home-move;
// routes and screens untouched.
t_section('report config moved to Admin (simplify step 3)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
//  SLICE TO THE NEXT `case`, NOT TO A MAGIC NUMBER.
//
//  This read a fixed 1600 bytes from `case 'reporting':`, and B5 broke it by
//  adding a count to the report-register tile -- which pushed "Technical
//  writing" past byte 1600 and made the test report a tile that was still
//  there as missing. The window was already nearly full (the tile sat at byte
//  ~1553), and substr() counts BYTES while this file is full of multi-byte
//  emoji, so the real margin was smaller than it looked.
//
//  A fixed length cannot survive an area gaining a line, and every area
//  eventually does. Slicing to the next `case '` is exact, needs no upkeep,
//  and the assertions below are unchanged in substance: move a tile out of
//  Reporting and they still fail.
$areaSlice = function ($case) use ($a) {
    $i = strpos($a, $case);
    if ($i === false) return '';
    $j = strpos($a, "case '", $i + strlen($case));
    return $j === false ? substr($a, $i) : substr($a, $i, $j - $i);
};
$reporting = $areaSlice("case 'reporting':");
$admin     = $areaSlice("case 'admin':");
t_ok(strlen($reporting) > 900 && strlen($admin) > 3000,
     'ARMING · both area slices were found (' . strlen($reporting) . ' / ' . strlen($admin) . ' bytes)');
t_ok(strpos($reporting, "case 'money':") === false && strpos($admin, "case 'directory':") === false,
     'ARMING · and neither slice ran on into the next area');

// UPDATED IN MILESTONE 11 — the assertion is about WHERE the tile lives, not
// what it is called. M11 renamed the Admin tile to 'Report templates' and gave
// it an explicit destination ('/templates?kind=report'), because the same label
// pointing at the same bare router existed in two areas and which screen you got
// depended on your permissions rather than on the tile you clicked.
//
// The home-move assertion below is unchanged in substance: move the tile back to
// Reporting and it still fails. It simply no longer pins the old wording.
foreach ([['Approver mapping', '/approver-map'], ['Approval rules', '/approval-rules']] as [$lbl, $rt]) {
    t_ok(strpos($reporting, "'$lbl', '$rt'") === false, "$lbl is removed from Reporting");
    t_ok(strpos($admin, "'$lbl', '$rt'") !== false, "$lbl now lives under Admin");
}
t_ok(strpos($reporting, "'/templates") === false, 'the templates tile is removed from Reporting');
t_ok(strpos($admin, "'/templates?kind=report'") !== false, 'the report-template tile now lives under Admin');
t_ok(strpos($admin, "'Report templates'") !== false, 'M11 — and names the library it opens');
t_ok(strpos($reporting, "'Audit trail', '/audit-log'") === false, 'the audit trail is removed from Reporting');
t_ok(strpos($admin, "'Report audit trail', '/audit-log'") !== false, 'the audit trail now lives under Admin');

// Reporting keeps the things you use daily.
t_ok(strpos($reporting, "T_REG('report'), '/documents'") !== false, 'Reporting still has the report register');
t_ok(strpos($reporting, "'Technical writing', '/writing-assistant'") !== false, 'Reporting still has writing help');
t_ok(strpos($admin, "'approver-map','approval-rules'") !== false, 'the moved routes highlight Admin now');
