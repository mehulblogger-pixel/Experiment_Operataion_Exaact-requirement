<?php
// ===========================================================================
//  Organisation administration — one screen that owns the whole org structure
//
//  Three things used to live apart and drift apart with them:
//    * offices        — created in Masters, with no notion of one office
//                       sitting under another, so "the organisation" was a
//                       flat list of branches;
//    * people         — created one at a time in Users, where the reporting
//                       manager is one field buried in a long form;
//    * the chart      — a read-only picture drawn from whatever those two
//                       happened to contain.
//
//  They are now one screen (/hierarchy) with tabs over the SAME data, so the
//  chart a Business Director looks at is the thing that gets edited, not a
//  report of it. This file holds everything behind those tabs: the office
//  tree, the bulk people editor, the spreadsheet round-trip, and the gap
//  check that says out loud where the single source of truth is incomplete.
// ===========================================================================

// ---------------------------------------------------------------------------
//  Schema
// ---------------------------------------------------------------------------
function orgadmin_migrate() {
    // An office can sit under another office (region → branch → sub-office),
    // and has a head who is a real system user rather than a typed-in name.
    ensure_column('offices', 'parent_office_id', 'INT NULL');
    ensure_column('offices', 'head_user_id', 'INT NULL');
    ensure_column('offices', 'office_type', "VARCHAR(20) DEFAULT 'BRANCH'");
    ensure_column('offices', 'region', "VARCHAR(80) DEFAULT ''");
    ensure_column('offices', 'is_active', 'INT DEFAULT 1');
    ensure_column('offices', 'sort_order', 'INT DEFAULT 0');
    ensure_column('offices', 'address', "VARCHAR(255) DEFAULT ''");
    ensure_column('offices', 'phone', "VARCHAR(60) DEFAULT ''");
    // Seed a sensible starting point once: the managing office is the head
    // office. Guarded by a setting rather than by the value itself, so an admin
    // who deliberately demotes it does not get it flipped back on the next boot.
    try {
        db()->exec("UPDATE offices SET is_active=1 WHERE is_active IS NULL");
        if (setting_get('org_types_seeded', '') !== '1') {
            db()->exec("UPDATE offices SET office_type='HEAD_OFFICE' WHERE is_ahmedabad=1");
            setting_set('org_types_seeded', '1');
        }
    } catch (Throwable $e) { /* first run, before the columns exist */ }
}

const OFFICE_TYPES = [
    'HEAD_OFFICE' => 'Head office',
    'REGION'      => 'Region / zone',
    'BRANCH'      => 'Branch office',
    'SITE_OFFICE' => 'Site / project office',
    'IBO'         => 'Affiliate (IBO)',
];

// ---------------------------------------------------------------------------
//  Office tree
// ---------------------------------------------------------------------------
function office_rows($activeOnly = false) {
    $w = $activeOnly ? "WHERE is_active=1" : "";
    return ops_all("SELECT * FROM offices $w ORDER BY sort_order, is_ahmedabad DESC, name");
}
// Nested office tree; offices whose parent is missing or inactive become roots
// so nothing can be orphaned out of sight.
function office_tree($activeOnly = false) {
    $rows = office_rows($activeOnly);
    $byId = []; $kids = [];
    foreach ($rows as $r) { $r['children'] = []; $byId[(int)$r['id']] = $r; }
    $roots = [];
    foreach ($byId as $id => $r) {
        $p = (int)($r['parent_office_id'] ?? 0);
        if ($p && isset($byId[$p]) && $p !== $id) $kids[$p][] = $id; else $roots[] = $id;
    }
    $build = function ($id) use (&$build, &$byId, &$kids) {
        $n = $byId[$id];
        foreach ($kids[$id] ?? [] as $c) $n['children'][] = $build($c);
        return $n;
    };
    $out = [];
    foreach ($roots as $id) $out[] = $build($id);
    return $out;
}
// Every office at or below $id — used to stop an office being moved under
// itself, which would make the tree disappear into a loop.
function office_descendants($id) {
    $all = ops_all("SELECT id, parent_office_id FROM offices");
    $kids = [];
    foreach ($all as $r) $kids[(int)($r['parent_office_id'] ?? 0)][] = (int)$r['id'];
    $out = []; $stack = [(int)$id];
    while ($stack) {
        $cur = array_pop($stack);
        foreach ($kids[$cur] ?? [] as $c) { if (!isset($out[$c])) { $out[$c] = true; $stack[] = $c; } }
    }
    return array_keys($out);
}
// Descendants of every office in one pass — the per-row "sits under" pickers
// need this for all rows at once, and calling office_descendants() per row
// would re-read the whole table each time.
function office_descendant_map() {
    $kids = [];
    foreach (ops_all("SELECT id, parent_office_id FROM offices") as $r)
        $kids[(int)($r['parent_office_id'] ?? 0)][] = (int)$r['id'];
    $out = [];
    $walk = function ($id) use (&$walk, &$kids, &$out) {
        if (isset($out[$id])) return $out[$id];
        $out[$id] = [];                       // set first: guards a broken cycle in data
        $acc = [];
        foreach ($kids[$id] ?? [] as $c) { $acc[] = $c; $acc = array_merge($acc, $walk($c)); }
        return $out[$id] = array_values(array_unique($acc));
    };
    foreach ($kids as $p => $cs) $walk($p);
    foreach (ops_all("SELECT id FROM offices") as $r) $walk((int)$r['id']);
    return $out;
}
// Head-count per office, so the office tree can say what it actually carries.
function office_headcounts() {
    $out = [];
    foreach (ops_all("SELECT home_office_id oid, COUNT(*) n FROM users WHERE is_active=1 GROUP BY home_office_id") as $r)
        $out[(int)($r['oid'] ?? 0)] = (int)$r['n'];
    return $out;
}
function office_flat_options($excludeId = 0) {
    // "— " indented labels, so a <select> still reads as a tree.
    $out = []; $skip = $excludeId ? array_merge([(int)$excludeId], office_descendants($excludeId)) : [];
    $walk = function ($nodes, $depth) use (&$walk, &$out, $skip) {
        foreach ($nodes as $n) {
            if (in_array((int)$n['id'], $skip, true)) continue;
            $out[(int)$n['id']] = str_repeat('— ', $depth) . $n['name'] . ($n['code'] ? ' (' . $n['code'] . ')' : '');
            if ($n['children']) $walk($n['children'], $depth + 1);
        }
    };
    $walk(office_tree(), 0);
    return $out;
}

// ---------------------------------------------------------------------------
//  Gap check — what stops the hierarchy being a single source of truth
//
//  A chart is only trustworthy if you can see what is missing from it. Each
//  gap names the people or offices involved and links straight to the fix.
// ---------------------------------------------------------------------------
// "1 person has" / "4 people have" — a gap title that reads like English is
// worth the four lines, because these are the sentences a director acts on.
function org_people_are($n, $verbSingular, $verbPlural) {
    return $n === 1 ? ('1 person ' . $verbSingular) : ($n . ' people ' . $verbPlural);
}
function org_things_are($n, $singular, $plural, $verbSingular, $verbPlural) {
    return $n === 1 ? ('1 ' . $singular . ' ' . $verbSingular) : ($n . ' ' . $plural . ' ' . $verbPlural);
}
function org_health() {
    $users = ops_all("SELECT id, username, first_name, last_name, role, home_office_id, reports_to_id, reports_to_name, is_active, email FROM users WHERE is_active=1");
    $byId = []; foreach ($users as $u) $byId[(int)$u['id']] = $u;
    $name = fn($u) => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
    $g = [];

    // 1. no reporting manager (excluding the one person who legitimately has none)
    $roots = array_values(array_filter($users, fn($u) => !(int)($u['reports_to_id'] ?? 0)));
    if (count($roots) > 1) {
        $g[] = ['key' => 'roots', 'level' => 'bad',
            'title' => org_people_are(count($roots), 'sits at the top with no reporting manager', 'sit at the top with no reporting manager'),
            'why' => 'Only one person — the head of the organisation — should have nobody above them. Everyone else here shows up as a separate tree, which is why the chart looks like a row of boxes.',
            'fix' => 'Use “Arrange automatically”, or set each person’s manager on the People tab.',
            'people' => array_map(fn($u) => ['id' => (int)$u['id'], 'label' => $name($u) . ' · ' . (ORG_ROLES[$u['role']] ?? $u['role'])], $roots)];
    }
    // 2. manager typed in as free text but not linked to a login
    $manual = array_values(array_filter($users, fn($u) => !(int)($u['reports_to_id'] ?? 0) && trim($u['reports_to_name'] ?? '') !== ''));
    if ($manual) $g[] = ['key' => 'manual', 'level' => 'warn',
        'title' => org_people_are(count($manual), 'reports to a manager who has no login', 'report to a manager who has no login'),
        'why' => 'Their manager is a typed-in name, so the chart cannot join them to anybody and they hang off the top instead.',
        'fix' => 'Create a user for that manager, or point these people at somebody who has a login.',
        'people' => array_map(fn($u) => ['id' => (int)$u['id'], 'label' => $name($u) . ' → “' . $u['reports_to_name'] . '”'], $manual)];
    // 3. no office
    $noOff = array_values(array_filter($users, fn($u) => !(int)($u['home_office_id'] ?? 0)));
    if ($noOff) $g[] = ['key' => 'nooffice', 'level' => 'warn',
        'title' => org_people_are(count($noOff), 'is not attached to any ' . Tl('office'), 'are not attached to any ' . Tl('office')),
        'why' => 'Scoping, credit and every ' . Tl('office') . '-wise report skip a person with no ' . Tl('office') . '.',
        'fix' => 'Set the home ' . Tl('office') . ' on the People tab.',
        'people' => array_map(fn($u) => ['id' => (int)$u['id'], 'label' => $name($u) . ' · ' . (ORG_ROLES[$u['role']] ?? $u['role'])], $noOff)];
    // 4. manager who is inactive or deleted
    $dangling = [];
    foreach ($users as $u) {
        $m = (int)($u['reports_to_id'] ?? 0);
        if ($m && !isset($byId[$m])) $dangling[] = ['id' => (int)$u['id'], 'label' => $name($u) . ' → manager no longer active'];
    }
    if ($dangling) $g[] = ['key' => 'dangling', 'level' => 'bad',
        'title' => org_people_are(count($dangling), 'reports to somebody who has left or been deactivated', 'report to somebody who has left or been deactivated'),
        'why' => 'Approvals routed to that manager have nowhere to land.',
        'fix' => 'Re-point them at their new manager.', 'people' => $dangling];
    // 5. reporting loops
    $loop = [];
    foreach ($users as $u) {
        $seen = []; $cur = (int)$u['id'];
        while ($cur && isset($byId[$cur])) {
            if (isset($seen[$cur])) { $loop[] = ['id' => (int)$u['id'], 'label' => $name($u)]; break; }
            $seen[$cur] = true; $cur = (int)($byId[$cur]['reports_to_id'] ?? 0);
        }
    }
    if ($loop) $g[] = ['key' => 'loop', 'level' => 'bad',
        'title' => org_people_are(count($loop), 'is caught in a reporting loop', 'are caught in a reporting loop'),
        'why' => 'Two people report to each other (directly or round a chain), so the chart can never draw them.',
        'fix' => 'Break the loop by re-pointing one of them.', 'people' => $loop];
    // 6. person reporting into a different office without a management role
    $offName = []; foreach (office_rows() as $o) $offName[(int)$o['id']] = $o['name'];
    $cross = [];
    foreach ($users as $u) {
        $m = (int)($u['reports_to_id'] ?? 0);
        if (!$m || !isset($byId[$m])) continue;
        $a = (int)($u['home_office_id'] ?? 0); $b = (int)($byId[$m]['home_office_id'] ?? 0);
        if ($a && $b && $a !== $b && !in_array($byId[$m]['role'], ['MASTER_ADMIN','BUSINESS_DIRECTOR','SBU_HEAD'], true))
            $cross[] = ['id' => (int)$u['id'], 'label' => $name($u) . ' (' . ($offName[$a] ?? '?') . ') → ' . $name($byId[$m]) . ' (' . ($offName[$b] ?? '?') . ')'];
    }
    if ($cross) $g[] = ['key' => 'cross', 'level' => 'info',
        'title' => org_people_are(count($cross), 'reports across ' . Tlp('office'), 'report across ' . Tlp('office')),
        'why' => 'Perfectly legitimate for a shared or matrix role — worth a look in case it is a leftover.',
        'fix' => 'Leave as is if intended.', 'people' => $cross];
    // 7. offices with no head
    $headless = [];
    foreach (office_rows(true) as $o) {
        $h = (int)($o['head_user_id'] ?? 0);
        if (!$h || !isset($byId[$h])) $headless[] = ['id' => 0, 'label' => $o['name']];
    }
    if ($headless) $g[] = ['key' => 'nohead', 'level' => 'warn',
        'title' => org_things_are(count($headless), Tl('office'), Tlp('office'), 'has no head', 'have no head'),
        'why' => 'Escalations and ' . Tl('office') . '-level approvals fall back to whoever the system can find.',
        'fix' => 'Set the head on the ' . TP('office') . ' tab.', 'people' => $headless];
    // 8. offices with nobody in them
    $hc = office_headcounts(); $empty = [];
    foreach (office_rows(true) as $o) if (!($hc[(int)$o['id']] ?? 0)) $empty[] = ['id' => 0, 'label' => $o['name']];
    if ($empty) $g[] = ['key' => 'empty', 'level' => 'info',
        'title' => org_things_are(count($empty), Tl('office'), Tlp('office'), 'has nobody in it', 'have nobody in them'),
        'why' => 'Either the ' . Tl('office') . ' is new, or its people were never attached to it.',
        'fix' => 'Attach people, or mark the ' . Tl('office') . ' inactive.', 'people' => $empty];
    return $g;
}

// ---------------------------------------------------------------------------
//  Spreadsheet round-trip
//
//  The point is a round trip, not an import: download the register you already
//  have, edit it in Excel with Role and Reports-to as proper dropdowns, upload
//  it back. Rows that match an existing username are updated, the rest are
//  created — so the same file works for the first load and for every change
//  after it, and the reporting column is what builds the chart.
// ---------------------------------------------------------------------------
function org_import_columns() {
    return [
        'username'            => 'Username',
        'first_name'          => 'First name',
        'last_name'           => 'Last name',
        'email'               => 'Email',
        'role'                => 'Role',
        'position_title'      => 'Designation',
        'office'              => TH('office'),
        'reports_to'          => 'Reports to',
        'sbu'                 => T('sbu'),
        'weekly_working_days' => 'Weekly working days',
        'active'              => 'Active',
        'password'            => 'Password (new people only)',
    ];
}
// The current register, in exactly the shape the importer reads back.
function org_register_rows() {
    $offName = []; foreach (office_rows() as $o) $offName[(int)$o['id']] = $o['name'];
    $users = ops_all("SELECT * FROM users ORDER BY is_active DESC, username");
    $byId = []; foreach ($users as $u) $byId[(int)$u['id']] = $u;
    $rows = [];
    foreach ($users as $u) {
        $m = (int)($u['reports_to_id'] ?? 0);
        $rows[] = [
            'username' => $u['username'],
            'first_name' => $u['first_name'] ?? '',
            'last_name' => $u['last_name'] ?? '',
            'email' => $u['email'] ?? '',
            'role' => ORG_ROLES[$u['role']] ?? $u['role'],
            'position_title' => $u['position_title'] ?? '',
            'office' => $offName[(int)($u['home_office_id'] ?? 0)] ?? '',
            'reports_to' => $m && isset($byId[$m]) ? $byId[$m]['username'] : ($u['reports_to_name'] ?? ''),
            'sbu' => $u['scope_sbus'] ?? '',
            'weekly_working_days' => rtrim(rtrim((string)($u['weekly_working_days'] ?? '6'), '0'), '.') ?: '6',
            'active' => ((int)($u['is_active'] ?? 1)) ? 'Yes' : 'No',
            'password' => '',
        ];
    }
    return $rows;
}

// ---- Minimal .xlsx writer -------------------------------------------------
//  Hand-rolled because there is no Composer on the host. It writes inline
//  strings (no shared-string table) and one hidden sheet of allowed values,
//  which the Role / Office / Reports-to columns point at with a list
//  validation — that is what makes them real dropdowns in Excel.
function xl_col($i) {                      // 0 → A, 26 → AA
    $s = '';
    for ($i = $i + 1; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}
function xl_esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function xl_sheet($rows, $validations = '', $freezeHeader = true) {
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    if ($freezeHeader) $x .= '<sheetViews><sheetView workbookViewId="0" tabSelected="1">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    $x .= '<sheetFormatPr defaultRowHeight="15"/><cols>';
    $n = $rows ? count($rows[0]) : 1;
    for ($c = 0; $c < $n; $c++) $x .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="20" customWidth="1"/>';
    $x .= '</cols><sheetData>';
    foreach ($rows as $r => $row) {
        $x .= '<row r="' . ($r + 1) . '">';
        $c = 0;
        foreach ($row as $v) {
            $ref = xl_col($c) . ($r + 1);
            $x .= '<c r="' . $ref . '" t="inlineStr"' . ($r === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">' . xl_esc($v) . '</t></is></c>';
            $c++;
        }
        $x .= '</row>';
    }
    $x .= '</sheetData>' . $validations . '</worksheet>';
    return $x;
}
// $lists = ['A' => ['title', v, v, ...], ...] keyed by the column letter they fill.
function org_xlsx($dataRows, $lists, $validations) {
    if (!class_exists('ZipArchive')) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) return null;

    $z->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $z->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $z->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
        . '<sheet name="People" sheetId="1" r:id="rId1"/>'
        . '<sheet name="Lists" sheetId="2" state="hidden" r:id="rId2"/>'
        . '</sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    // style 1 = bold header on a light fill
    $z->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF3F6E8C"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
        // Excel asks to "repair" a workbook with no named Normal style, so
        // declare it even though nothing references it.
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2"/>'
        . '</styleSheet>');

    // The hidden Lists sheet: one column per dropdown, longest first is fine.
    $maxLen = 0; foreach ($lists as $L) $maxLen = max($maxLen, count($L));
    $listRows = [];
    for ($r = 0; $r < $maxLen; $r++) {
        $row = [];
        foreach ($lists as $L) $row[] = $L[$r] ?? '';
        $listRows[] = $row;
    }
    $z->addFromString('xl/worksheets/sheet1.xml', xl_sheet($dataRows, $validations));
    $z->addFromString('xl/worksheets/sheet2.xml', xl_sheet($listRows, '', false));
    $z->close();
    $bytes = file_get_contents($tmp);
    @unlink($tmp);
    return $bytes;
}
// Build the workbook for the user register, with dropdowns wired up.
function org_register_xlsx($withData = true) {
    $cols = org_import_columns();
    $head = array_values($cols);
    $keys = array_keys($cols);
    $rows = [$head];
    if ($withData) foreach (org_register_rows() as $r) {
        $line = []; foreach ($keys as $k) $line[] = $r[$k] ?? '';
        $rows[] = $line;
    }
    // Dropdown sources, in the order they are written onto the hidden sheet.
    $roleList   = array_merge(['Role'], array_values(ORG_ROLES));
    $officeList = array_merge([TH('office')], array_map(fn($o) => $o['name'], office_rows(true)));
    $peopleList = array_merge(['Reports to'], array_map(fn($u) => $u['username'], ops_all("SELECT username FROM users WHERE is_active=1 ORDER BY username")));
    $sbuList    = array_merge([T('sbu')], array_values(lk_options_or('sbu', OPS_SBUS)));
    $yesNo      = ['Active', 'Yes', 'No'];
    $wwd        = ['Weekly working days', '5', '5.5', '6'];
    $lists = ['A' => $roleList, 'B' => $officeList, 'C' => $peopleList, 'D' => $sbuList, 'E' => $yesNo, 'F' => $wwd];

    $last = max(count($rows) + 200, 500);   // room to add people below the data
    $colOf = fn($key) => xl_col(array_search($key, $keys, true));
    $dv = '<dataValidations count="6">';
    $pairs = [
        ['role', 'A', count($roleList)], ['office', 'B', count($officeList)],
        ['reports_to', 'C', count($peopleList)], ['sbu', 'D', count($sbuList)],
        ['active', 'E', count($yesNo)], ['weekly_working_days', 'F', count($wwd)],
    ];
    foreach ($pairs as [$key, $listCol, $n]) {
        $c = $colOf($key);
        $dv .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="0"'
             . ' sqref="' . $c . '2:' . $c . $last . '">'
             . '<formula1>Lists!$' . $listCol . '$2:$' . $listCol . '$' . max(2, $n) . '</formula1></dataValidation>';
    }
    $dv .= '</dataValidations>';
    return org_xlsx($rows, $lists, $dv);
}
function org_register_csv($withData = true) {
    $cols = org_import_columns();
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, array_values($cols));
    if ($withData) foreach (org_register_rows() as $r) {
        $line = []; foreach (array_keys($cols) as $k) $line[] = $r[$k] ?? '';
        fputcsv($fh, $line);
    }
    rewind($fh);
    $out = stream_get_contents($fh);
    fclose($fh);
    return $out;
}

// ---- Reading an uploaded sheet --------------------------------------------
// Returns a list of rows, each an array of cell strings (first row = header).
function org_read_sheet($path, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') return org_read_xlsx($path);
    // CSV / TSV — sniff the delimiter from the header line.
    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);          // strip a BOM Excel adds
    $first = strtok($raw, "\n");
    $delim = (substr_count($first, "\t") > substr_count($first, ',')) ? "\t" : ',';
    $rows = [];
    $fh = fopen('php://temp', 'r+'); fwrite($fh, $raw); rewind($fh);
    while (($r = fgetcsv($fh, 0, $delim)) !== false) {
        if (count($r) === 1 && trim((string)$r[0]) === '') continue;
        $rows[] = $r;
    }
    fclose($fh);
    return $rows;
}
// Read sheet 1 of an .xlsx without any library: the file is a ZIP of XML.
function org_read_xlsx($path) {
    if (!class_exists('ZipArchive')) return [];
    $z = new ZipArchive();
    if ($z->open($path) !== true) return [];
    // Shared strings (Excel writes most text there rather than inline).
    $shared = [];
    $ss = $z->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        // each <si> is one string, possibly split across several <t> runs
        if (preg_match_all('~<si>(.*?)</si>~s', $ss, $m)) {
            foreach ($m[1] as $si) {
                $txt = '';
                if (preg_match_all('~<t[^>]*>(.*?)</t>~s', $si, $t)) $txt = implode('', $t[1]);
                $shared[] = html_entity_decode($txt, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
    }
    // The first sheet is not always sheet1.xml — follow the workbook rels.
    $target = 'xl/worksheets/sheet1.xml';
    $wb = $z->getFromName('xl/workbook.xml');
    $rels = $z->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb !== false && $rels !== false && preg_match('~<sheet[^>]*r:id="([^"]+)"~', $wb, $mm)) {
        if (preg_match('~Id="' . preg_quote($mm[1], '~') . '"[^>]*Target="([^"]+)"~', $rels, $tt))
            $target = 'xl/' . ltrim($tt[1], '/');
    }
    $xml = $z->getFromName($target);
    $z->close();
    if ($xml === false) return [];

    $rows = [];
    preg_match_all('~<row[^>]*r="(\d+)"[^>]*>(.*?)</row>~s', $xml, $rm, PREG_SET_ORDER);
    foreach ($rm as $r) {
        $cells = [];
        preg_match_all('~<c\b([^>]*)>(.*?)</c>~s', $r[2], $cm, PREG_SET_ORDER);
        foreach ($cm as $c) {
            $attr = $c[1]; $body = $c[2];
            $col = 0;
            if (preg_match('~r="([A-Z]+)~', $attr, $rr)) {
                foreach (str_split($rr[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
                $col--;
            } else $col = count($cells);
            $type = preg_match('~t="([^"]+)"~', $attr, $tm) ? $tm[1] : 'n';
            if ($type === 's') {                       // shared string index
                $v = preg_match('~<v>(.*?)</v>~s', $body, $vm) ? ($shared[(int)$vm[1]] ?? '') : '';
            } elseif ($type === 'inlineStr') {
                $v = preg_match_all('~<t[^>]*>(.*?)</t>~s', $body, $vm) ? implode('', $vm[1]) : '';
                $v = html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
            } else {
                $v = preg_match('~<v>(.*?)</v>~s', $body, $vm) ? $vm[1] : '';
                $v = html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $cells[$col] = $v;
        }
        if (!$cells) continue;
        $wide = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $wide; $i++) $line[] = $cells[$i] ?? '';
        if (implode('', array_map('trim', $line)) === '') continue;
        $rows[] = $line;
    }
    return $rows;
}

// ---- Matching an uploaded sheet to the system ------------------------------
function org_norm($s) { return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$s))); }
// Header row → column index per known field. Accepts the label, the key, and a
// few things people actually type ("reporting manager", "branch").
function org_map_header($header) {
    $aliases = [
        'username' => ['username', 'userid', 'loginid', 'login'],
        'first_name' => ['firstname', 'first', 'name', 'fullname', 'employeename'],
        'last_name' => ['lastname', 'last', 'surname'],
        'email' => ['email', 'emailid', 'mail', 'officialemail'],
        'role' => ['role', 'designationrole', 'systemrole', 'userrole'],
        'position_title' => ['designation', 'positiontitle', 'position', 'title', 'jobtitle'],
        'office' => ['office', 'branch', 'location', 'homeoffice', 'ibo', 'officebranch'],
        'reports_to' => ['reportsto', 'reportingmanager', 'manager', 'reportingto', 'n1', 'supervisor'],
        'sbu' => ['sbu', 'businessunit', 'strategicbusinessunit'],
        'weekly_working_days' => ['weeklyworkingdays', 'workingdays', 'wwd'],
        'active' => ['active', 'status', 'isactive'],
        'password' => ['password', 'passwordnewpeopleonly', 'initialpassword'],
    ];
    foreach (org_import_columns() as $k => $label) $aliases[$k][] = org_norm($label);
    $map = [];
    foreach ($header as $i => $h) {
        $n = org_norm($h);
        if ($n === '') continue;
        foreach ($aliases as $key => $alts) {
            if (isset($map[$key])) continue;
            if (in_array($n, array_map('org_norm', $alts), true)) { $map[$key] = $i; break; }
        }
    }
    return $map;
}
// Role written as a label, a code, or something close to either.
function org_match_role($txt) {
    $n = org_norm($txt);
    if ($n === '') return '';
    foreach (ORG_ROLES as $code => $label)
        if ($n === org_norm($code) || $n === org_norm($label)) return $code;
    foreach (ORG_ROLES as $code => $label)                       // then a contains-match
        if (strlen($n) > 3 && (str_contains(org_norm($label), $n) || str_contains($n, org_norm($label)))) return $code;
    return '';
}
function org_match_office($txt) {
    $n = org_norm($txt);
    if ($n === '') return 0;
    foreach (office_rows() as $o) {
        if ($n === org_norm($o['name']) || $n === org_norm($o['code'])) return (int)$o['id'];
    }
    foreach (office_rows() as $o) {
        if (strlen($n) > 2 && (str_contains(org_norm($o['name']), $n) || str_contains(org_norm($o['city'] ?? ''), $n))) return (int)$o['id'];
    }
    return 0;
}
// Parse an uploaded sheet into preview rows: what will happen to each person,
// what could not be matched, and the choices a human can correct on screen.
function org_import_parse($sheet) {
    if (!$sheet) return ['rows' => [], 'error' => 'The file had no readable rows.'];
    $map = org_map_header($sheet[0]);
    if (!isset($map['username']) && !isset($map['email']) && !isset($map['first_name']))
        return ['rows' => [], 'error' => 'Could not find a Username, Email or Name column in the header row. Download the template and use its headings.'];

    $existing = ops_all("SELECT id, username, email, first_name, last_name, role, home_office_id, reports_to_id, position_title, scope_sbus, weekly_working_days, is_active FROM users");
    $byUser = []; $byMail = [];
    foreach ($existing as $u) {
        $byUser[strtolower($u['username'])] = $u;
        if (trim($u['email'] ?? '') !== '') $byMail[strtolower($u['email'])] = $u;
    }
    $get = function ($row, $key) use ($map) {
        return isset($map[$key]) ? trim((string)($row[$map[$key]] ?? '')) : '';
    };
    $rows = [];
    for ($i = 1; $i < count($sheet); $i++) {
        $r = $sheet[$i];
        $first = $get($r, 'first_name'); $last = $get($r, 'last_name');
        // "Full name" pasted into one column — split it so the chart shows a name.
        if ($last === '' && str_contains($first, ' ')) {
            $parts = preg_split('/\s+/', $first, 2);
            $first = $parts[0]; $last = $parts[1] ?? '';
        }
        $username = strtolower($get($r, 'username'));
        $email = $get($r, 'email');
        if ($username === '' && $email === '' && $first === '') continue;   // blank line
        // No username given? Derive one, and keep it unique.
        if ($username === '') {
            $base = $email !== '' ? strtolower(explode('@', $email)[0])
                                  : strtolower(preg_replace('/[^a-z0-9]/i', '.', trim($first . '.' . $last, '.')));
            $base = trim(preg_replace('/\.+/', '.', $base), '.') ?: 'user';
            $username = $base; $n = 2;
            while (isset($byUser[$username])) $username = $base . $n++;
        }
        $match = $byUser[$username] ?? ($email !== '' ? ($byMail[strtolower($email)] ?? null) : null);
        $roleTxt = $get($r, 'role');
        $role = org_match_role($roleTxt) ?: ($match['role'] ?? '');
        $offTxt = $get($r, 'office');
        $office = org_match_office($offTxt) ?: (int)($match['home_office_id'] ?? 0);
        $activeTxt = strtolower($get($r, 'active'));
        $active = $activeTxt === '' ? (int)($match['is_active'] ?? 1)
                : (in_array($activeTxt, ['no','n','0','inactive','false','left','resigned'], true) ? 0 : 1);
        $wwdTxt = $get($r, 'weekly_working_days');
        $wwd = in_array($wwdTxt, ['5','5.5','6'], true) ? $wwdTxt : (string)($match['weekly_working_days'] ?? '6');

        $issues = [];
        if ($roleTxt !== '' && !org_match_role($roleTxt)) $issues[] = 'Role “' . $roleTxt . '” not recognised';
        if ($role === '') $issues[] = 'No role';
        if ($offTxt !== '' && !org_match_office($offTxt)) $issues[] = TH('office') . ' “' . $offTxt . '” not found';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $issues[] = 'E-mail looks wrong';

        $rows[] = [
            'line' => $i + 1,
            'action' => $match ? 'update' : 'create',
            'user_id' => $match ? (int)$match['id'] : 0,
            'username' => $username,
            'first_name' => $first ?: ($match['first_name'] ?? ''),
            'last_name' => $last ?: ($match['last_name'] ?? ''),
            'email' => $email ?: ($match['email'] ?? ''),
            'role' => $role,
            'role_raw' => $roleTxt,
            'position_title' => $get($r, 'position_title') ?: ($match['position_title'] ?? ''),
            'office_id' => $office,
            'office_raw' => $offTxt,
            'reports_to_raw' => $get($r, 'reports_to'),
            'sbu' => $get($r, 'sbu') ?: ($match['scope_sbus'] ?? ''),
            'weekly_working_days' => $wwd,
            'active' => $active,
            'password' => $get($r, 'password'),
            'issues' => $issues,
        ];
    }
    // Resolve "reports to" only once every row is known, so a manager listed
    // further down the sheet still links up.
    $inSheet = [];
    foreach ($rows as $idx => $r) {
        $inSheet[strtolower($r['username'])] = $idx;
        if ($r['email'] !== '') $inSheet[strtolower($r['email'])] = $idx;
        $full = org_norm($r['first_name'] . $r['last_name']);
        if ($full !== '') $inSheet['name:' . $full] = $idx;
    }
    foreach ($rows as $idx => &$r) {
        $t = trim($r['reports_to_raw']);
        $r['manager_user_id'] = 0; $r['manager_row'] = -1; $r['manager_label'] = '';
        if ($t === '') continue;
        $k = strtolower($t);
        if (isset($byUser[$k])) { $r['manager_user_id'] = (int)$byUser[$k]['id']; $r['manager_label'] = $byUser[$k]['username']; }
        elseif (isset($byMail[$k])) { $r['manager_user_id'] = (int)$byMail[$k]['id']; $r['manager_label'] = $byMail[$k]['username']; }
        if (!$r['manager_user_id']) {
            $hit = $inSheet[$k] ?? $inSheet['name:' . org_norm($t)] ?? null;
            if ($hit !== null && $hit !== $idx) { $r['manager_row'] = $hit; $r['manager_label'] = $rows[$hit]['username'] . ' (in this file)'; }
        }
        if (!$r['manager_user_id'] && $r['manager_row'] < 0) {
            // last resort: match an existing person by full name
            foreach ($existing as $u) {
                if (org_norm(($u['first_name'] ?? '') . ($u['last_name'] ?? '')) === org_norm($t)) {
                    $r['manager_user_id'] = (int)$u['id']; $r['manager_label'] = $u['username']; break;
                }
            }
        }
        if (!$r['manager_user_id'] && $r['manager_row'] < 0) $r['issues'][] = 'Manager “' . $t . '” not found';
    }
    unset($r);
    return ['rows' => $rows, 'error' => ''];
}
// Write the previewed rows. Two passes: everybody exists before any reporting
// line is set, so a manager listed below their report still links up.
function org_import_apply($rows) {
    $pdo = db();
    $created = 0; $updated = 0; $linked = 0; $skipped = 0; $noRole = 0;
    $idOfRow = [];
    foreach ($rows as $idx => $r) {
        if (!empty($r['skip'])) { $skipped++; continue; }
        // A blank role would otherwise be quietly filled in with whatever the
        // default is, which is how somebody ends up with the wrong access.
        if (($r['role'] ?? '') === '') { $noRole++; continue; }
        $role = $r['role'];
        $off = (int)$r['office_id'] ?: null;
        $isSuper = $role === 'MASTER_ADMIN' ? 1 : 0;
        if ((int)$r['user_id']) {
            $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, role=?, is_superuser=?, is_active=?, home_office_id=?, position_title=?, scope_sbus=?, weekly_working_days=? WHERE id=?")
                ->execute([$r['first_name'], $r['last_name'], $r['email'], $role, $isSuper, (int)$r['active'], $off,
                           $r['position_title'], $r['sbu'], (float)$r['weekly_working_days'], (int)$r['user_id']]);
            if (trim((string)($r['password'] ?? '')) !== '')
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($r['password'], PASSWORD_DEFAULT), (int)$r['user_id']]);
            $idOfRow[$idx] = (int)$r['user_id'];
            $updated++;
        } else {
            $pw = trim((string)($r['password'] ?? '')) !== '' ? $r['password'] : 'changeme123';
            $pdo->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,home_office_id,position_title,scope_sbus,scope_offices,permissions,weekly_working_days)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'','',?)")
                ->execute([$r['username'], password_hash($pw, PASSWORD_DEFAULT), $r['first_name'], $r['last_name'],
                           $r['email'], $role, $isSuper, (int)$r['active'], $off, $r['position_title'], $r['sbu'],
                           (float)$r['weekly_working_days']]);
            $idOfRow[$idx] = (int)$pdo->lastInsertId();
            $created++;
        }
    }
    // Pass 2 — reporting lines.
    foreach ($rows as $idx => $r) {
        if (!empty($r['skip']) || !isset($idOfRow[$idx])) continue;
        $mid = (int)($r['manager_user_id'] ?? 0);
        if (!$mid && ($r['manager_row'] ?? -1) >= 0) $mid = (int)($idOfRow[$r['manager_row']] ?? 0);
        if (!$mid || $mid === $idOfRow[$idx]) continue;
        if (org_would_loop($idOfRow[$idx], $mid)) continue;      // never write a cycle
        $m = ops_one("SELECT first_name, last_name, username, role FROM users WHERE id=?", [$mid]);
        $mn = $m ? (trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['username']) : '';
        $pdo->prepare("UPDATE users SET reports_to_id=?, reports_to_name=?, reports_to_position=? WHERE id=?")
            ->execute([$mid, $mn, $m ? (ORG_ROLES[$m['role']] ?? $m['role']) : '', $idOfRow[$idx]]);
        $linked++;
    }
    return ['created' => $created, 'updated' => $updated, 'linked' => $linked, 'skipped' => $skipped, 'no_role' => $noRole];
}
// Would making $managerId the manager of $userId close a loop?
function org_would_loop($userId, $managerId) {
    $seen = []; $cur = (int)$managerId;
    while ($cur) {
        if ($cur === (int)$userId) return true;
        if (isset($seen[$cur])) return true;
        $seen[$cur] = true;
        $cur = (int)(ops_val("SELECT reports_to_id FROM users WHERE id=?", [$cur]) ?: 0);
    }
    return false;
}
// Shared by the People tab and the chart: set one person's manager safely.
function org_set_manager($userId, $managerId) {
    $userId = (int)$userId; $managerId = (int)$managerId;
    if (!$userId || $userId === $managerId) return false;
    if ($managerId && org_would_loop($userId, $managerId)) return false;
    if ($managerId) {
        $m = ops_one("SELECT first_name, last_name, username, role FROM users WHERE id=?", [$managerId]);
        if (!$m) return false;
        $mn = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['username'];
        db()->prepare("UPDATE users SET reports_to_id=?, reports_to_name=?, reports_to_position=? WHERE id=?")
            ->execute([$managerId, $mn, ORG_ROLES[$m['role']] ?? $m['role'], $userId]);
    } else {
        db()->prepare("UPDATE users SET reports_to_id=NULL, reports_to_name='', reports_to_position='' WHERE id=?")->execute([$userId]);
    }
    return true;
}

// ---------------------------------------------------------------------------
//  Handlers
// ---------------------------------------------------------------------------
// Download: the register itself (round-trip) or an empty template.
function ops_org_template() {
    ops_require(is_master() || can('users.manage.global'), 'You cannot export the user register.');
    $withData = ($_GET['blank'] ?? '') !== '1';
    $fmt = ($_GET['fmt'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
    $stamp = date('Y-m-d');
    if ($fmt === 'xlsx') {
        $bytes = org_register_xlsx($withData);
        if ($bytes === null) {                       // no ZipArchive on this host
            flash('Excel export needs the ZIP extension, which this server does not have. A CSV has been downloaded instead — it opens in Excel and uploads back the same way.', 'error');
            $fmt = 'csv';
        } else {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="user-register-' . $stamp . '.xlsx"');
            header('Content-Length: ' . strlen($bytes));
            echo $bytes;
            return true;
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="user-register-' . $stamp . '.csv"');
    echo "\xEF\xBB\xBF" . org_register_csv($withData);   // BOM so Excel reads UTF-8
    return true;
}

// The one screen: chart, offices, people, import, gaps — all the same data.
function ops_hierarchy_screen($method) {
    ops_require(is_master() || can('org.hierarchy.view') || can('users.manage.global') || can('users.manage.branch') || can('settings.manage'),
        'You cannot view the organisation hierarchy.');
    $canEdit = is_master() || can('users.manage.global');
    $tab = $_GET['tab'] ?? 'chart';
    if (!in_array($tab, ['chart','offices','people','import','gaps'], true)) $tab = 'chart';

    if ($method === 'POST') {
        ops_require($canEdit, 'You cannot change the organisation structure.');
        $do = $_POST['do'] ?? '';
        $back = '/hierarchy' . (($_POST['tab'] ?? '') !== '' ? '?tab=' . urlencode($_POST['tab']) : '');

        if ($do === 'auto') {
            $n = count(org_auto_arrange(true, true));
            flash($n ? ($n . ' person(s) placed under a reporting manager. Correct any of them on the People tab.')
                     : 'Nothing to place — everyone already reports to somebody, or there is nobody above them.');

        } elseif ($do === 'set') {                          // inline move on a chart card
            if (!org_set_manager($_POST['user_id'] ?? 0, $_POST['manager_id'] ?? 0))
                flash('That reporting line would create a loop, so it was not saved.', 'error');
            else flash('Reporting line updated.');

        } elseif ($do === 'people-save') {                  // bulk edit on the People tab
            $roles = (array)($_POST['p_role'] ?? []);
            $offs  = (array)($_POST['p_office'] ?? []);
            $mgrs  = (array)($_POST['p_manager'] ?? []);
            $pos   = (array)($_POST['p_position'] ?? []);
            $n = 0; $loops = 0;
            foreach ($mgrs as $uid => $mid) {
                $uid = (int)$uid;
                $u = ops_one("SELECT role, home_office_id, reports_to_id, position_title FROM users WHERE id=?", [$uid]);
                if (!$u) continue;
                $role = isset($roles[$uid]) && isset(ORG_ROLES[$roles[$uid]]) ? $roles[$uid] : $u['role'];
                $off = isset($offs[$uid]) ? ((int)$offs[$uid] ?: null) : ($u['home_office_id'] ?: null);
                $pt = isset($pos[$uid]) ? trim((string)$pos[$uid]) : ($u['position_title'] ?? '');
                if ($role !== $u['role'] || (int)$off !== (int)($u['home_office_id'] ?? 0) || $pt !== ($u['position_title'] ?? '')) {
                    db()->prepare("UPDATE users SET role=?, is_superuser=?, home_office_id=?, position_title=? WHERE id=?")
                        ->execute([$role, $role === 'MASTER_ADMIN' ? 1 : 0, $off, $pt, $uid]);
                    $n++;
                }
                if ((int)$mid !== (int)($u['reports_to_id'] ?? 0)) {
                    if (org_set_manager($uid, (int)$mid)) $n++; else $loops++;
                }
            }
            flash($n . ' change(s) saved.' . ($loops ? ' ' . $loops . ' were skipped because they would create a reporting loop.' : ''),
                  $loops ? 'error' : 'success');

        } elseif ($do === 'office-save') {
            $id = (int)($_POST['office_id'] ?? 0);
            $name = trim($_POST['o_name'] ?? '');
            if ($name === '') { flash(TH('office') . ' name is required.', 'error'); redirect($back); }
            $parent = (int)($_POST['o_parent'] ?? 0) ?: null;
            if ($id && $parent && in_array($parent, array_merge([$id], office_descendants($id)), true)) {
                flash('An ' . Tl('office') . ' cannot sit under itself or under one of its own sub-' . Tlp('office') . '.', 'error');
                redirect($back);
            }
            $type = isset(OFFICE_TYPES[$_POST['o_type'] ?? '']) ? $_POST['o_type'] : 'BRANCH';
            $vals = [trim($_POST['o_code'] ?? ''), $name, trim($_POST['o_city'] ?? ''), trim($_POST['o_region'] ?? ''),
                     $type, $parent, (int)($_POST['o_head'] ?? 0) ?: null, trim($_POST['o_address'] ?? ''),
                     trim($_POST['o_phone'] ?? ''), !empty($_POST['o_active']) ? 1 : 0, (int)($_POST['o_sort'] ?? 0)];
            if ($id) {
                $vals[] = $id;
                db()->prepare("UPDATE offices SET code=?, name=?, city=?, region=?, office_type=?, parent_office_id=?, head_user_id=?, address=?, phone=?, is_active=?, sort_order=? WHERE id=?")->execute($vals);
                flash(TH('office') . ' updated.');
            } else {
                db()->prepare("INSERT INTO offices (code,name,city,region,office_type,parent_office_id,head_user_id,address,phone,is_active,sort_order,is_ahmedabad) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)")->execute($vals);
                flash(TH('office') . ' added.');
            }

        } elseif ($do === 'office-move') {                  // one-click re-parent
            $id = (int)($_POST['office_id'] ?? 0);
            $parent = (int)($_POST['parent_office_id'] ?? 0) ?: null;
            if ($id && $parent && in_array($parent, array_merge([$id], office_descendants($id)), true))
                flash('That would put an ' . Tl('office') . ' under itself.', 'error');
            elseif ($id) {
                db()->prepare("UPDATE offices SET parent_office_id=? WHERE id=?")->execute([$parent, $id]);
                flash(TH('office') . ' moved.');
            }

        } elseif ($do === 'office-toggle') {
            $id = (int)($_POST['office_id'] ?? 0);
            if ($id) {
                $cur = (int)ops_val("SELECT is_active FROM offices WHERE id=?", [$id]);
                db()->prepare("UPDATE offices SET is_active=? WHERE id=?")->execute([$cur ? 0 : 1, $id]);
                flash(TH('office') . ($cur ? ' marked inactive.' : ' reactivated.'));
            }

        } elseif ($do === 'import-preview') {
            if (empty($_FILES['sheet']['tmp_name']) || !is_uploaded_file($_FILES['sheet']['tmp_name'])) {
                flash('Choose a file first.', 'error'); redirect('/hierarchy?tab=import');
            }
            $sheet = org_read_sheet($_FILES['sheet']['tmp_name'], $_FILES['sheet']['name']);
            $res = org_import_parse($sheet);
            if ($res['error']) { flash($res['error'], 'error'); redirect('/hierarchy?tab=import'); }
            if (!$res['rows']) { flash('No people were found in that file.', 'error'); redirect('/hierarchy?tab=import'); }
            $_SESSION['org_import'] = $res['rows'];
            $_SESSION['org_import_name'] = $_FILES['sheet']['name'];
            redirect('/hierarchy?tab=import');

        } elseif ($do === 'import-cancel') {
            unset($_SESSION['org_import'], $_SESSION['org_import_name']);
            redirect('/hierarchy?tab=import');

        } elseif ($do === 'import-apply') {
            $rows = $_SESSION['org_import'] ?? [];
            if (!$rows) { flash('The preview expired — upload the file again.', 'error'); redirect('/hierarchy?tab=import'); }
            // Apply the corrections made in the preview grid before writing.
            $skip = (array)($_POST['i_skip'] ?? []);
            foreach ($rows as $i => &$r) {
                $r['skip'] = !empty($skip[$i]);
                if (isset($_POST['i_role'][$i]) && isset(ORG_ROLES[$_POST['i_role'][$i]])) $r['role'] = $_POST['i_role'][$i];
                if (isset($_POST['i_office'][$i])) $r['office_id'] = (int)$_POST['i_office'][$i];
                if (isset($_POST['i_manager'][$i])) {
                    $v = (string)$_POST['i_manager'][$i];
                    if (str_starts_with($v, 'row:')) { $r['manager_row'] = (int)substr($v, 4); $r['manager_user_id'] = 0; }
                    else { $r['manager_user_id'] = (int)$v; $r['manager_row'] = -1; }
                }
            }
            unset($r);
            $res = org_import_apply($rows);
            unset($_SESSION['org_import'], $_SESSION['org_import_name']);
            flash($res['created'] . ' created, ' . $res['updated'] . ' updated, ' . $res['linked']
                . ' reporting line(s) set' . ($res['skipped'] ? ', ' . $res['skipped'] . ' skipped' : '') . '.'
                . ($res['no_role'] ? ' ' . $res['no_role'] . ' row(s) were left out because no role was chosen for them.' : ''),
                $res['no_role'] ? 'error' : 'success');
            redirect('/hierarchy?tab=chart');
        }
        redirect($back);
    }

    // ---- read side --------------------------------------------------------
    $tree = org_hierarchy_tree();
    foreach ($tree as &$root) org_count_below($root);
    unset($root);
    $all = ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name, username");
    $vars = [
        'tab' => $tab, 'tree' => $tree, 'canEdit' => $canEdit, 'all' => $all,
        'unplaced' => count($tree),
        'totalPeople' => (int)ops_val("SELECT COUNT(*) FROM users WHERE is_active=1"),
        'proposals' => $canEdit ? org_auto_arrange(false, true) : [],
        'health' => org_health(),
        'offTree' => office_tree(), 'offCounts' => office_headcounts(),
        'offOpts' => office_flat_options(), 'offDesc' => office_descendant_map(),
        'editOffice' => null, 'importRows' => $_SESSION['org_import'] ?? [],
        'importName' => $_SESSION['org_import_name'] ?? '',
        'hasZip' => class_exists('ZipArchive'),
    ];
    if ($tab === 'offices' && ($_GET['office'] ?? '') !== '') {
        $vars['editOffice'] = ($_GET['office'] === 'new') ? ['id' => 0, 'is_active' => 1, 'office_type' => 'BRANCH', 'sort_order' => 0]
            : ops_one("SELECT * FROM offices WHERE id=?", [(int)$_GET['office']]);
        if ($vars['editOffice']) $vars['offOpts'] = office_flat_options((int)($vars['editOffice']['id'] ?? 0));
    }
    if ($tab === 'people') {
        $vars['people'] = ops_all("SELECT id, username, first_name, last_name, email, role, position_title, home_office_id, reports_to_id, reports_to_name FROM users WHERE is_active=1 ORDER BY role, first_name, username");
        $vars['offAll'] = [];
        foreach (office_rows() as $o) $vars['offAll'][(int)$o['id']] = $o['name'];
    }
    view('ops/hierarchy', $vars);
    return true;
}
