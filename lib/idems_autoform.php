<?php
// ============================================================================
//  Auto-build a report form from a PLAIN .docx — no {{tokens}} to insert by hand
//
//  The existing template engine fills {{token}} placeholders. This bridges to it:
//  a person uploads their ORDINARY Word form, and the app
//    1. detects the fields — every "Label: ____" line and every label|value
//       table row and header+blank-body table — with no key-matching, and
//    2. writes a copy of the SAME document with {{tokens}} inserted at those
//       spots, saved as the report type's client format.
//  After a quick review the fields are created, and because the tokenised copy
//  keeps the original layout, the finished report comes back in the very same
//  format (Word, and PDF).
//
//  It leans on DOMDocument so the Word XML stays valid, and reuses idems'
//  key/label/type helpers so detected fields match hand-built ones.
// ============================================================================

const AF_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

function autoform_supported() { return class_exists('ZipArchive') && class_exists('DOMDocument'); }

// Concatenated visible text of a Word element (paragraph or cell).
function autoform_text($el) {
    $t = '';
    foreach ($el->getElementsByTagNameNS(AF_NS, 't') as $tn) $t .= $tn->textContent;
    return trim(preg_replace('/\s+/', ' ', $t));
}

// Does this text look like a label waiting for an answer? Returns the cleaned
// label, or '' if not. Accepts "Name:", "Date of test", "Result :", "Sr. No.".
function autoform_label($t) {
    $t = trim($t);
    if ($t === '') return '';
    // strip a trailing colon / dashes / underscores used as a blank
    $t = preg_replace('/[:\-–_\s]+$/u', '', $t);
    if (mb_strlen($t) < 2 || mb_strlen($t) > 60) return '';
    if (!preg_match('/[A-Za-z]/', $t)) return '';           // must have a letter
    return $t;
}

// Put {{token}} as a run at the end of the first paragraph inside $container.
function autoform_put_token($dom, $container, $token) {
    $p = $container->getElementsByTagNameNS(AF_NS, 'p')->item(0);
    if (!$p) { $p = $dom->createElementNS(AF_NS, 'w:p'); $container->appendChild($p); }
    $r = $dom->createElementNS(AF_NS, 'w:r');
    $t = $dom->createElementNS(AF_NS, 'w:t', '{{' . $token . '}}');
    $t->setAttribute('xml:space', 'preserve');
    $r->appendChild($t); $p->appendChild($r);
}

// Analyze a plain .docx. Returns ['plan'=>[fields], 'tokenised'=>binary, 'err'=>?].
// Each plan field: [key,label,type,kind('field'|'table'),cols?].
function autoform_analyze_docx($binary) {
    if (!autoform_supported()) return ['plan' => [], 'tokenised' => null, 'err' => 'This server is missing the zip or DOM PHP extension, so a plain file cannot be read automatically. Insert {{tokens}} by hand instead.'];
    $tmp = tempnam(sys_get_temp_dir(), 'afz');
    if ($tmp === false || file_put_contents($tmp, $binary) === false) return ['plan' => [], 'tokenised' => null, 'err' => 'Could not write a temporary file.'];
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { @unlink($tmp); return ['plan' => [], 'tokenised' => null, 'err' => 'That is not a valid Word (.docx) file.']; }
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) { $zip->close(); @unlink($tmp); return ['plan' => [], 'tokenised' => null, 'err' => 'Could not read the Word document.']; }

    $dom = new DOMDocument(); $dom->preserveWhiteSpace = true;
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($xml)) { $zip->close(); @unlink($tmp); return ['plan' => [], 'tokenised' => null, 'err' => 'The Word document could not be parsed.']; }

    $plan = []; $used = [];
    $mkkey = function ($label) use (&$used) {
        $k = idems_clean_key($label); if ($k === '') $k = 'field';
        $base = $k; $i = 2; while (isset($used[$k])) { $k = $base . '_' . $i; $i++; }
        $used[$k] = 1; return $k;
    };
    $addField = function ($label, $key, $type = null) use (&$plan) {
        $plan[] = ['key' => $key, 'label' => $label, 'type' => $type ?: idems_guess_type($key, $label), 'kind' => 'field'];
    };

    // ---- 1. Tables --------------------------------------------------------
    // Real inspection forms mix, inside ONE Word table, a labelled header grid
    // ("File No:" | "Report No:"), multi-column DATA tables (an 8-column
    // quantity table, a 6-column instrument-calibration table) and single-line
    // "Label:" rows. So each table is walked ROW BY ROW rather than classified
    // as a whole — the old all-or-nothing rule flattened every real table into
    // stray text fields and lost every column.
    //
    //  · A column-title header row (≥2 non-empty cells, none ending in a colon)
    //    directly followed by one or more fully-empty rows → a repeatable
    //    {{table.column}} field spanning those columns.
    //  · A "Label:" cell → an inline field ({{token}} placed after the label).
    //  · A Label cell whose right-hand neighbour is blank → a field in that
    //    neighbour cell.
    $isColonLabel = fn($t) => (bool)preg_match('/[:：]\s*$/u', (string)$t);   // "File No:"
    foreach (iterator_to_array($dom->getElementsByTagNameNS(AF_NS, 'tbl')) as $tbl) {
        $rows = [];
        foreach ($tbl->childNodes as $ch) if ($ch->localName === 'tr') $rows[] = $ch;
        if (!$rows) continue;
        $grid = [];
        foreach ($rows as $ri => $tr) { $grid[$ri] = []; foreach ($tr->childNodes as $c) if ($c->localName === 'tc') $grid[$ri][] = $c; }
        $nrows = count($grid);
        $txtOf = fn($cells) => array_map('autoform_text', $cells);
        $nonEmptyCount = fn($vals) => count(array_filter($vals, fn($v) => $v !== ''));

        $ri = 0;
        while ($ri < $nrows) {
            $cells = $grid[$ri];
            $vals  = $txtOf($cells);

            // -- Data table: a column-title header (≥2 titles, no trailing colon)
            //    followed by ≥1 fully-empty row → repeatable multi-column table.
            $isHeader = count($cells) >= 2 && $nonEmptyCount($vals) >= 2;
            if ($isHeader) foreach ($vals as $v) if ($v !== '' && $isColonLabel($v)) { $isHeader = false; break; }
            $emptyRun = 0;
            if ($isHeader) {
                for ($rj = $ri + 1; $rj < $nrows; $rj++) {
                    if ($nonEmptyCount($txtOf($grid[$rj])) === 0) $emptyRun++; else break;
                }
            }
            if ($isHeader && $emptyRun >= 1) {
                $tkey = $mkkey(autoform_text($cells[0]) . ' table');
                $cols = []; $seen = [];
                foreach ($cells as $c) {
                    $lbl = autoform_text($c) ?: 'Column';
                    $ck = idems_clean_key($lbl) ?: ('c' . (count($cols) + 1));
                    $b = $ck; $x = 2; while (isset($seen[$ck])) { $ck = $b . '_' . $x; $x++; }
                    $seen[$ck] = 1; $cols[$ck] = $lbl;
                }
                // tokenise the FIRST empty row: {{tkey.colkey}} per cell; the fill
                // engine repeats that row once per data row entered.
                $bodyRow = $grid[$ri + 1]; $ci = 0;
                foreach ($cols as $ck => $lbl) { if (isset($bodyRow[$ci])) autoform_put_token($dom, $bodyRow[$ci], $tkey . '.' . $ck); $ci++; }
                $plan[] = ['key' => $tkey, 'label' => idems_label_from_key($tkey), 'type' => 'table', 'kind' => 'table', 'cols' => $cols];
                $ri += 1 + $emptyRun;
                continue;
            }

            // -- Otherwise: label cells (inline "Label:" or Label|blank pairs). --
            $handled = [];
            for ($i = 0; $i < count($cells); $i++) {
                if (isset($handled[$i]) || $vals[$i] === '') continue;
                if ($isColonLabel($vals[$i])) {                       // "File No:" → inline token
                    $lbl = autoform_label($vals[$i]); if ($lbl === '') continue;
                    $key = $mkkey($lbl);
                    autoform_put_token($dom, $cells[$i], $key);
                    $addField($lbl, $key);
                } else {                                             // Label | (blank value cell)
                    $lbl = autoform_label($vals[$i]);
                    if ($lbl !== '' && isset($cells[$i + 1]) && $vals[$i + 1] === '') {
                        $key = $mkkey($lbl);
                        autoform_put_token($dom, $cells[$i + 1], $key);
                        $addField($lbl, $key);
                        $handled[$i + 1] = 1;
                    }
                }
            }
            $ri++;
        }
    }

    // ---- 2. Paragraphs: "Label: ______" (outside tables) ------------------
    foreach (iterator_to_array($dom->getElementsByTagNameNS(AF_NS, 'p')) as $p) {
        // skip paragraphs inside a table (handled above)
        $anc = $p->parentNode; $inTable = false;
        while ($anc) { if ($anc->localName === 'tc') { $inTable = true; break; } $anc = $anc->parentNode; }
        if ($inTable) continue;
        $txt = autoform_text($p);
        if ($txt === '' || strpos($txt, '{{') !== false) continue;
        // Needs a colon or a run of blanks to be a fill-in line, not prose.
        if (!preg_match('/^(.{2,60}?)[:：]\s*[_\.\s]*$/u', $txt, $m) && !preg_match('/^(.{2,60}?)\s*[_]{2,}\s*$/u', $txt, $m)) continue;
        $lbl = autoform_label($m[1]); if ($lbl === '') continue;
        $key = $mkkey($lbl);
        autoform_put_token($dom, $p, $key);
        $addField($lbl, $key);
    }

    // ---- write the tokenised copy ----------------------------------------
    $newXml = $dom->saveXML();
    $zip->deleteName('word/document.xml'); $zip->addFromString('word/document.xml', $newXml);
    $zip->close();
    $tokenised = file_get_contents($tmp); @unlink($tmp);
    return ['plan' => $plan, 'tokenised' => $tokenised];
}
