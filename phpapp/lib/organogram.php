<?php
// ============================================================================
//  EXAACT — Organogram importer.
//
//  Upload a company's org chart and the system turns it into OFFICES,
//  DESIGNATIONS, POSITIONS, auto-generated CODES and the REPORTING LINES — all
//  shown on a preview the administrator approves before anything is written.
//
//  Formats, and which need AI:
//    • Excel (.xlsx) / CSV / pasted text ......... read directly (no AI, exact)
//    • PowerPoint (.pptx) / Visio (.vsdx) ........ titles read directly (no AI);
//                                                  reporting lines set on preview
//    • Picture (.png/.jpg…) / scanned PDF ........ read by AI (metered against the
//                                                  workspace's monthly AI cap)
//
//  Additive & non-destructive: reuses the position master (position_save), the
//  offices table and the designation/department lookups. No new permissions —
//  gated exactly like the existing position/org-chart screens.
// ============================================================================

// ---- Column recognition (headers) & the normalised row ---------------------
const ORGA_HEADS = [
    'name' => 'name', 'employee' => 'name', 'person' => 'name',
    'title' => 'designation', 'designation' => 'designation', 'role' => 'designation', 'position' => 'designation',
    'department' => 'department', 'dept' => 'department', 'function' => 'department',
    'office' => 'office', 'branch' => 'office', 'location' => 'office', 'site' => 'office',
    'grade' => 'grade', 'band' => 'grade', 'level' => 'grade',
    'code' => 'code', 'position code' => 'code', 'emp code' => 'code',
    'reports to' => 'reports_to', 'reports_to' => 'reports_to', 'manager' => 'reports_to',
    'reporting to' => 'reports_to', 'supervisor' => 'reports_to', 'parent' => 'reports_to',
    'sanctioned' => 'sanctioned', 'sanctioned headcount' => 'sanctioned', 'headcount' => 'sanctioned',
    'occupied' => 'occupied', 'occupied headcount' => 'occupied', 'filled' => 'occupied',
];

function orga_norm_row($r) {
    $name  = trim((string) ($r['name'] ?? ''));
    $desig = trim((string) ($r['designation'] ?? ''));
    if ($name === '')  $name = $desig;        // a chart usually gives only a title
    if ($desig === '') $desig = $name;
    return [
        'name' => $name, 'designation' => $desig,
        'department' => trim((string) ($r['department'] ?? '')),
        'office' => trim((string) ($r['office'] ?? '')),
        'grade' => trim((string) ($r['grade'] ?? '')),
        'code' => trim((string) ($r['code'] ?? '')),
        'reports_to' => trim((string) ($r['reports_to'] ?? '')),
        'sanctioned' => (int) preg_replace('/\D+/', '', (string) ($r['sanctioned'] ?? '')),
        'occupied' => (int) preg_replace('/\D+/', '', (string) ($r['occupied'] ?? '')),
    ];
}

// ---- Auto-generated codes --------------------------------------------------
// Initials of the words (Talent Acquisition Specialist -> TAS); falls back to the
// first letters of the label; de-duplicated against $used (which the caller seeds
// with the codes already in the database and fills as it goes).
function orga_code($label, array &$used, $len = 4) {
    $up = strtoupper((string) $label);
    $words = preg_split('/[^A-Z0-9]+/', $up, -1, PREG_SPLIT_NO_EMPTY);
    $code = '';
    foreach ($words as $w) $code .= $w[0];
    if (strlen($code) < 2) $code = preg_replace('/[^A-Z0-9]/', '', $up);
    $code = substr($code, 0, $len);
    if ($code === '') $code = 'X';
    $base = $code; $n = 1;
    while (isset($used[$code])) { $n++; $suffix = (string) $n; $code = substr($base, 0, max(1, $len - strlen($suffix))) . $suffix; }
    $used[$code] = true;
    return $code;
}

// ---- Table (CSV / TSV / pasted text) parser --------------------------------
function orga_parse_table($text) {
    $text = trim((string) $text);
    if ($text === '') return [];
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $rows = []; $map = null;
    $order = ['name', 'designation', 'department', 'office', 'grade', 'reports_to', 'sanctioned', 'occupied'];
    foreach ($lines as $ln) {
        if (trim($ln) === '') continue;
        $delim = (strpos($ln, "\t") !== false) ? "\t" : ',';
        $cells = array_map(fn($c) => trim($c, " \t\"'"), explode($delim, $ln));
        if ($map === null) {   // is the first non-empty line a header?
            $lc = array_map('strtolower', $cells);
            $known = 0; foreach ($lc as $c) if (isset(ORGA_HEADS[$c])) $known++;
            // A header row: two or more known column names, OR a single-column list
            // whose one column IS a known name (e.g. just "Title" from a chart).
            if ($known >= 2 || ($known >= 1 && $known === count($lc))) {
                $map = []; foreach ($lc as $i => $c) if (isset(ORGA_HEADS[$c])) $map[ORGA_HEADS[$c]] = $i; continue;
            }
            $map = false;      // no header — fixed column order
        }
        $get = function ($field) use ($cells, $map, $order) {
            if (is_array($map)) return isset($map[$field]) ? ($cells[$map[$field]] ?? '') : '';
            $idx = array_search($field, $order, true); return $idx !== false ? ($cells[$idx] ?? '') : '';
        };
        $row = orga_norm_row([
            'name' => $get('name'), 'designation' => $get('designation'), 'department' => $get('department'),
            'office' => $get('office'), 'grade' => $get('grade'), 'code' => $get('code'),
            'reports_to' => $get('reports_to'), 'sanctioned' => $get('sanctioned'), 'occupied' => $get('occupied'),
        ]);
        if ($row['name'] !== '') $rows[] = $row;
    }
    return $rows;
}

// ---- Zip helpers (xlsx / pptx / vsdx are zip-of-XML) -----------------------
function orga_zip_entry($bytes, $name) {
    $entries = orga_zip_entries($bytes, '#^' . preg_quote($name, '#') . '$#');
    return $entries[$name] ?? null;
}
function orga_zip_entries($bytes, $pattern) {
    if (!class_exists('ZipArchive')) return [];
    $tmp = @tempnam(sys_get_temp_dir(), 'orga');
    if ($tmp === false) return [];
    @file_put_contents($tmp, $bytes);
    $res = [];
    $z = new ZipArchive();
    if ($z->open($tmp) === true) {
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if ($n !== false && preg_match($pattern, $n)) { $c = $z->getFromIndex($i); if ($c !== false) $res[$n] = $c; }
        }
        $z->close();
    }
    @unlink($tmp);
    return $res;
}

// ---- Excel (.xlsx) ---------------------------------------------------------
function orga_col_index($letters) {
    $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $letters));
    $n = 0; for ($i = 0, $L = strlen($letters); $i < $L; $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
    return max(0, $n - 1);
}
function orga_read_xlsx($bytes) {
    if (!class_exists('ZipArchive')) return [[], '', 'This server cannot open Excel files directly. Please save the sheet as CSV and upload that instead.'];
    $shared = [];
    $ss = orga_zip_entry($bytes, 'xl/sharedStrings.xml');
    if ($ss && preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $ss, $m))
        foreach ($m[1] as $t) $shared[] = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_XML1);
    $sheets = orga_zip_entries($bytes, '#^xl/worksheets/sheet\d+\.xml$#');
    if (!$sheets) return [[], '', 'Could not read any sheet from that Excel file. Save it as CSV and try again.'];
    ksort($sheets);
    $grid = orga_xlsx_grid((string) reset($sheets), $shared);
    if (!$grid) return [[], '', 'That Excel sheet looked empty.'];
    $tsv = implode("\n", array_map(fn($r) => implode("\t", $r), $grid));
    return [orga_parse_table($tsv), 'Read directly from Excel.', ''];
}
function orga_xlsx_grid($sheetXml, $shared) {
    $grid = [];
    $xml = @simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData)) return $grid;
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $col = orga_col_index((string) $c['r']);
            $t = (string) $c['t'];
            if ($t === 's')            $v = $shared[(int) $c->v] ?? '';
            elseif ($t === 'inlineStr') $v = (string) $c->is->t;
            else                        $v = (string) $c->v;
            $cells[$col] = trim($v);
        }
        if ($cells) { $max = max(array_keys($cells)); $line = []; for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? ''; $grid[] = $line; }
    }
    return $grid;
}

// ---- PowerPoint (.pptx) / Visio (.vsdx) — titles, no AI --------------------
// Reporting lines from connector geometry are unreliable across tools, so we read
// the boxes' text and let the admin set "Reports to" on the preview. Honest and safe.
function orga_read_pptx($bytes) {
    $slides = orga_zip_entries($bytes, '#^ppt/slides/slide\d+\.xml$#');
    if (!$slides) return [[], '', 'Could not read the PowerPoint slides. If the chart is a picture, use the image option; or export it to Excel/CSV.'];
    $rows = [];
    foreach ($slides as $xmlStr) {
        if (preg_match_all('/<p:sp\b.*?<\/p:sp>/s', $xmlStr, $sp))
            foreach ($sp[0] as $shape)
                if (preg_match_all('/<a:t>(.*?)<\/a:t>/s', $shape, $tm)) {
                    $txt = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(implode(' ', $tm[1])), ENT_QUOTES | ENT_XML1)));
                    if ($txt !== '') $rows[] = orga_norm_row(['name' => $txt]);
                }
    }
    if (!$rows) return [[], '', 'No text boxes were found in that PowerPoint.'];
    return [$rows, 'Read the titles from PowerPoint. Reporting lines were not detected — set the “Reports to” column on the preview where needed.', ''];
}
function orga_read_vsdx($bytes) {
    $pages = orga_zip_entries($bytes, '#^visio/pages/page\d+\.xml$#');
    if (!$pages) return [[], '', 'Could not read that Visio file. Export it to Excel/CSV, or use the image option.'];
    $rows = [];
    foreach ($pages as $xmlStr)
        if (preg_match_all('/<Text[^>]*>(.*?)<\/Text>/s', $xmlStr, $tm))
            foreach ($tm[1] as $t) {
                $txt = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_XML1)));
                if ($txt !== '') $rows[] = orga_norm_row(['name' => $txt]);
            }
    if (!$rows) return [[], '', 'No shapes with text were found in that Visio file.'];
    return [$rows, 'Read the titles from Visio. Reporting lines were not detected — set the “Reports to” column on the preview where needed.', ''];
}

// ---- Picture / scanned PDF — via the platform AI (metered) -----------------
function orga_read_ai($file) {
    if (!function_exists('ai_chat_doc')) return [[], '', 'AI reading is not available on this workspace. Save the chart as Excel/CSV instead.'];
    $sys = 'You convert an organisation chart into a clean table. You never invent roles; you copy the exact wording shown on the chart.';
    $user = "This is an organisation chart. List EVERY box as one row. Return ONLY tab-separated values, one row per box, with EXACTLY these five columns in this order:\n"
          . "Title<TAB>Department<TAB>Office<TAB>Grade<TAB>ReportsToTitle\n"
          . "Use the exact title text on each box. Leave a cell empty if the chart does not show it. 'ReportsToTitle' is the title of the box this one connects up to (blank for the top box). No header row, no notes, no markdown — only the rows.";
    [$text, $err] = ai_chat_doc($sys, $user, [$file], 2500);
    if ($err) return [[], '', $err];
    // Prepend a header so the table parser maps the five columns correctly.
    $rows = orga_parse_table("title\tdepartment\toffice\tgrade\treports to\n" . $text);
    if (!$rows) return [[], '', 'The AI could not read a chart out of that file. Try a clearer image, or save the chart as Excel/CSV.'];
    return [$rows, 'Read by AI from the uploaded ' . (strpos((string) ($file['mime'] ?? ''), 'pdf') !== false ? 'PDF' : 'image') . ' (one AI action used).', ''];
}

// ---- Dispatch: read whatever was uploaded / pasted into normalised rows ----
// Returns ['rows'=>[], 'note'=>'', 'error'=>'', 'ai'=>bool, 'format'=>''].
function orga_read($rawText, $file) {
    $ext = '';
    if (is_array($file) && !empty($file['name'])) $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $bytes = (is_array($file) && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) ? (string) @file_get_contents($file['tmp_name']) : '';
    $mime = is_array($file) ? strtolower((string) ($file['type'] ?? '')) : '';

    $wrap = fn($r, $fmt, $ai = false) => ['rows' => $r[0], 'note' => $r[1], 'error' => $r[2], 'ai' => $ai, 'format' => $fmt];

    if ($ext === 'xlsx' || strpos($mime, 'spreadsheetml') !== false) return $wrap(orga_read_xlsx($bytes), 'Excel');
    if ($ext === 'xls')  return ['rows' => [], 'note' => '', 'error' => 'Old-format .xls is not supported — open it in Excel and “Save As” .xlsx or CSV.', 'ai' => false, 'format' => 'Excel (old)'];
    if ($ext === 'pptx' || strpos($mime, 'presentationml') !== false) return $wrap(orga_read_pptx($bytes), 'PowerPoint');
    if ($ext === 'vsdx') return $wrap(orga_read_vsdx($bytes), 'Visio');
    $isImg = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'], true) || strpos($mime, 'image/') === 0;
    $isPdf = $ext === 'pdf' || strpos($mime, 'pdf') !== false;
    if ($isImg || $isPdf) {
        if ($bytes === '') return ['rows' => [], 'note' => '', 'error' => 'No file was received.', 'ai' => true, 'format' => $isPdf ? 'PDF (AI)' : 'Image (AI)'];
        return $wrap(orga_read_ai(['name' => $file['name'] ?? 'chart', 'mime' => $mime ?: ($isPdf ? 'application/pdf' : 'image/png'), 'data' => $bytes]), $isPdf ? 'PDF (AI)' : 'Image (AI)', true);
    }
    // CSV / TXT file, or the paste box.
    $text = $rawText;
    if (trim($text) === '' && $bytes !== '') $text = $bytes;
    return ['rows' => orga_parse_table($text), 'note' => trim($text) !== '' ? 'Read directly from the text.' : '', 'error' => '', 'ai' => false, 'format' => $ext === 'csv' ? 'CSV' : 'Text'];
}

// ---- What would be created (for the preview summary) -----------------------
function orga_master_index($typeKey) {   // [labelsLower=>true, codesUpper=>true]
    if (!function_exists('lk_type')) return [[], []];
    $t = lk_type($typeKey); if (!$t) return [[], []];
    $byLabel = []; $codes = [];
    try { foreach (ops_all("SELECT code,label FROM lookup_values WHERE type_id=?", [$t['id']]) as $r) { $byLabel[strtolower(trim($r['label']))] = true; $codes[strtoupper(trim($r['code']))] = true; } }
    catch (Throwable $e) {}
    return [$byLabel, $codes];
}
function orga_preview_summary($rows) {
    $rows = array_map('orga_norm_row', $rows);
    [$desLabels] = orga_master_index('designation');
    [$depLabels] = orga_master_index('department');
    $offNames = [];
    if (function_exists('offices_list')) foreach (offices_list() as $o) $offNames[strtolower(trim($o['name']))] = true;
    $newOff = []; $newDes = []; $newDep = [];
    foreach ($rows as $r) {
        if ($r['office'] !== '' && !isset($offNames[strtolower($r['office'])])) $newOff[strtolower($r['office'])] = true;
        if ($r['designation'] !== '' && !isset($desLabels[strtolower($r['designation'])])) $newDes[strtolower($r['designation'])] = true;
        if ($r['department'] !== '' && !isset($depLabels[strtolower($r['department'])])) $newDep[strtolower($r['department'])] = true;
    }
    return ['positions' => count($rows), 'offices' => count($newOff), 'designations' => count($newDes), 'departments' => count($newDep)];
}

// ---- Apply (only after the admin confirms the preview) ---------------------
function orga_apply($rows) {
    position_migrate();
    $rows = array_values(array_filter(array_map('orga_norm_row', $rows), fn($r) => $r['name'] !== ''));
    $res = ['offices' => 0, 'designations' => 0, 'departments' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'unresolved' => []];
    if (!$rows) return $res;

    // 1) Offices — ensure each distinct office exists (auto code), build name->id.
    $offByName = []; $usedOff = [];
    if (function_exists('offices_list')) foreach (offices_list() as $o) { $offByName[strtolower(trim($o['name']))] = (int) $o['id']; if (trim((string) $o['code']) !== '') $usedOff[strtoupper(trim($o['code']))] = true; }
    foreach ($rows as $r) {
        $on = $r['office']; if ($on === '') continue; $k = strtolower($on);
        if (isset($offByName[$k])) continue;
        try {
            db()->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES (?,?,?,0)")->execute([orga_code($on, $usedOff, 4), $on, '']);
            $offByName[$k] = (int) db()->lastInsertId(); $res['offices']++;
        } catch (Throwable $e) {}
    }

    // 2) Designation + department master values — ensure each distinct label.
    $ensureMaster = function ($typeKey, $labels) use (&$res) {
        if (!function_exists('lk_type') || !lk_type($typeKey)) return;
        [$byLabel, $codes] = orga_master_index($typeKey);
        foreach ($labels as $label) {
            $label = trim($label); if ($label === '' || isset($byLabel[strtolower($label)])) continue;
            $code = orga_code($label, $codes, 6);
            if (function_exists('lk_ensure_value')) { lk_ensure_value($typeKey, $code, $label); $byLabel[strtolower($label)] = true; $res[$typeKey === 'designation' ? 'designations' : 'departments']++; }
        }
    };
    $ensureMaster('designation', array_unique(array_map(fn($r) => $r['designation'], $rows)));
    $ensureMaster('department',  array_unique(array_filter(array_map(fn($r) => $r['department'], $rows))));

    // 3) Positions — create/update (pass 1), then link reports-to (pass 2).
    $byCode = []; $byName = []; $usedPos = [];
    foreach (positions_all(false) as $p) {
        if (trim((string) $p['code']) !== '') { $byCode[strtolower(trim($p['code']))] = (int) $p['id']; $usedPos[strtoupper(trim($p['code']))] = true; }
        $byName[strtolower(trim($p['name']))] = (int) $p['id'];
    }
    $rowId = [];
    foreach ($rows as $i => $r) {
        $existing = 0;
        if ($r['code'] !== '' && isset($byCode[strtolower($r['code'])])) $existing = $byCode[strtolower($r['code'])];
        elseif (isset($byName[strtolower($r['name'])])) $existing = $byName[strtolower($r['name'])];
        $code = $r['code'] !== '' ? $r['code'] : ($existing ? '' : orga_code($r['name'], $usedPos, 6));
        $post = [
            'code' => $code, 'name' => $r['name'], 'department' => $r['department'], 'grade' => $r['grade'],
            'office_id' => $r['office'] !== '' ? ($offByName[strtolower($r['office'])] ?? null) : null,
            'sanctioned_headcount' => $r['sanctioned'] ?: 1, 'occupied_headcount' => $r['occupied'],
            'budgeted_headcount' => $r['sanctioned'] ?: 1,
        ];
        $id = position_save($existing, $post);
        if ($existing) $res['updated']++; else $res['created']++;
        $rowId[$i] = $id;
        if ($code !== '') $byCode[strtolower($code)] = $id;
        $byName[strtolower($r['name'])] = $id;
    }
    foreach ($rows as $i => $r) {
        $rt = trim((string) $r['reports_to']); if ($rt === '') continue;
        $pid = $byCode[strtolower($rt)] ?? $byName[strtolower($rt)] ?? 0;
        if (!$pid || $pid === $rowId[$i]) { if (!$pid) $res['unresolved'][] = $r['name'] . ' → ' . $rt; continue; }
        db()->prepare("UPDATE positions SET reports_to_id=? WHERE id=?")->execute([$pid, $rowId[$i]]);
        $res['linked']++;
    }
    return $res;
}

// A ready-to-fill CSV template for the "download template" link.
function orga_template_csv() {
    return "Title,Department,Office,Grade,Reports To,Sanctioned,Occupied\n"
         . "Managing Director,Executive,Head Office,E1,,1,1\n"
         . "HR Head,Human Resources,Head Office,M1,Managing Director,1,1\n"
         . "Recruitment Manager,Human Resources,Head Office,M2,HR Head,1,0\n"
         . "Recruiter,Human Resources,Mumbai,O1,Recruitment Manager,3,1\n";
}
