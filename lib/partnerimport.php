<?php
// ===========================================================================
//  Clients and vendors, brought in from a spreadsheet
//
//  Every company already has this list — in an Excel file somebody maintains
//  by hand. Retyping four hundred companies into a form is how a new system
//  gets abandoned in week one. So: download a workbook with the right columns
//  and real dropdowns, fill it in (or paste from the file you already have),
//  upload it, look at what the system thinks it is going to do, and only then
//  press the button.
//
//  Nothing is written until the preview has been seen. A company that is
//  already on file is matched by code, GSTIN, PAN or name — it is updated,
//  never duplicated — and every row can be skipped by hand before applying.
// ===========================================================================

// The columns of the template, in order. Key => heading as it appears in the
// sheet. The heading is what people read; the key is what the code uses.
function partner_import_columns() {
    return [
        'code'          => 'Code (leave blank to generate)',
        'legal_name'    => 'Legal name *',
        'display_name'  => 'Short / display name',
        'role'          => 'Role *',
        'client_type'   => 'Type',
        'industry'      => 'Industry',
        'ownership_type'=> 'Ownership',
        'gstin'         => 'GSTIN',
        'pan'           => 'PAN',
        'cin'           => 'CIN',
        'tan'           => 'TAN',
        'msme_udyam'    => 'MSME / Udyam',
        'state'         => 'State',
        'website'       => 'Website',
        'status'        => 'Status',
        'contact_name'  => 'Contact person',
        'contact_designation' => 'Contact designation',
        'contact_email' => 'Contact e-mail',
        'contact_mobile'=> 'Contact mobile',
        'line1'         => 'Address line 1',
        'line2'         => 'Address line 2',
        'city'          => 'City',
        'pincode'       => 'Pincode',
        'country'       => 'Country',
        'description'   => 'Notes',
    ];
}

// What can be typed in the Role column, and what each one turns on.
function partner_import_roles() {
    return [
        'Client'          => ['is_client' => 1, 'is_vendor' => 0, 'is_subcontractor' => 0],
        'Vendor'          => ['is_client' => 0, 'is_vendor' => 1, 'is_subcontractor' => 0],
        'Both'            => ['is_client' => 1, 'is_vendor' => 1, 'is_subcontractor' => 0],
        'Sub-contractor'  => ['is_client' => 0, 'is_vendor' => 1, 'is_subcontractor' => 1],
    ];
}
function partner_match_role($txt) {
    $n = org_norm($txt);
    if ($n === '') return '';
    foreach (partner_import_roles() as $label => $_) if ($n === org_norm($label)) return $label;
    if (in_array($n, ['customer', 'buyer', 'enduser', 'endclient'], true)) return 'Client';
    if (in_array($n, ['supplier', 'manufacturer', 'seller', 'oem'], true)) return 'Vendor';
    if (in_array($n, ['clientvendor', 'vendorclient', 'clientandvendor'], true)) return 'Both';
    if (in_array($n, ['subcon', 'subcontractor', 'manpower', 'subvendor'], true)) return 'Sub-contractor';
    return '';
}
// A coded list written as its label, its code, or something close.
function partner_match_option($txt, array $opts) {
    $n = org_norm($txt);
    if ($n === '') return '';
    foreach ($opts as $code => $label)
        if ($n === org_norm($code) || $n === org_norm($label)) return $code;
    foreach ($opts as $code => $label)
        if (strlen($n) > 3 && (str_contains(org_norm($label), $n) || str_contains($n, org_norm($label)))) return $code;
    return '';
}

// ---------------------------------------------------------------------------
//  The template
// ---------------------------------------------------------------------------
function partner_template_rows($withData, $kind) {
    $keys = array_keys(partner_import_columns());
    $rows = [array_values(partner_import_columns())];
    if (!$withData) return $rows;
    $where = $kind === 'vendors' ? 'is_vendor = 1' : ($kind === 'clients' ? 'is_client = 1' : '1=1');
    foreach (ops_all("SELECT * FROM business_partners WHERE $where ORDER BY legal_name") as $p) {
        $c = ops_one("SELECT * FROM partner_contacts WHERE partner_id=? ORDER BY is_primary DESC, id", [$p['id']]);
        $a = ops_one("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id", [$p['id']]);
        $role = !empty($p['is_subcontractor']) ? 'Sub-contractor'
              : ((!empty($p['is_client']) && !empty($p['is_vendor'])) ? 'Both'
              : (!empty($p['is_vendor']) ? 'Vendor' : 'Client'));
        $line = [];
        foreach ($keys as $k) {
            switch ($k) {
                case 'role': $line[] = $role; break;
                case 'client_type':    $line[] = CLIENT_TYPES[$p['client_type'] ?? ''] ?? ''; break;
                case 'industry':       $line[] = INDUSTRIES[$p['industry'] ?? ''] ?? ''; break;
                case 'ownership_type': $line[] = OWNERSHIP[$p['ownership_type'] ?? ''] ?? ''; break;
                case 'contact_name':        $line[] = $c['name'] ?? ''; break;
                case 'contact_designation': $line[] = $c['designation'] ?? ''; break;
                case 'contact_email':       $line[] = $c['email'] ?? ''; break;
                case 'contact_mobile':      $line[] = $c['mobile'] ?? ''; break;
                case 'line1':   $line[] = $a['line1'] ?? ''; break;
                case 'line2':   $line[] = $a['line2'] ?? ''; break;
                case 'city':    $line[] = $a['city'] ?? ''; break;
                case 'pincode': $line[] = $a['pincode'] ?? ''; break;
                case 'country': $line[] = $a['country'] ?? ''; break;
                default: $line[] = (string)($p[$k] ?? ''); break;
            }
        }
        $rows[] = $line;
    }
    return $rows;
}

function partner_template_xlsx($withData, $kind) {
    $cols = partner_import_columns();
    $keys = array_keys($cols);
    $rows = partner_template_rows($withData, $kind);

    $roleList  = array_merge(['Role'], array_keys(partner_import_roles()));
    $typeList  = array_merge(['Type'], array_values(CLIENT_TYPES));
    $indList   = array_merge(['Industry'], array_values(INDUSTRIES));
    $ownList   = array_merge(['Ownership'], array_values(OWNERSHIP));
    $statList  = ['Status', 'ACTIVE', 'INACTIVE'];
    $lists = ['A' => $roleList, 'B' => $typeList, 'C' => $indList, 'D' => $ownList, 'E' => $statList];

    $last = max(count($rows) + 300, 500);
    $colOf = function ($key) use ($keys) { return xl_col(array_search($key, $keys, true)); };
    $pairs = [
        ['role', 'A', count($roleList)], ['client_type', 'B', count($typeList)],
        ['industry', 'C', count($indList)], ['ownership_type', 'D', count($ownList)],
        ['status', 'E', count($statList)],
    ];
    $dv = '<dataValidations count="' . count($pairs) . '">';
    foreach ($pairs as [$key, $listCol, $n]) {
        $c = $colOf($key);
        $dv .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="0"'
             . ' sqref="' . $c . '2:' . $c . $last . '">'
             . '<formula1>Lists!$' . $listCol . '$2:$' . $listCol . '$' . max(2, $n) . '</formula1></dataValidation>';
    }
    $dv .= '</dataValidations>';
    return org_xlsx($rows, $lists, $dv, 'Companies');
}
function partner_template_csv($withData, $kind) {
    $fh = fopen('php://temp', 'r+');
    foreach (partner_template_rows($withData, $kind) as $r) fputcsv($fh, $r);
    rewind($fh);
    $out = stream_get_contents($fh);
    fclose($fh);
    return $out;
}

// ---------------------------------------------------------------------------
//  Reading what was uploaded
// ---------------------------------------------------------------------------
function partner_map_header($header) {
    $aliases = [
        'code'          => ['code', 'partnercode', 'clientcode', 'vendorcode', 'customercode', 'accountcode'],
        'legal_name'    => ['legalname', 'name', 'companyname', 'company', 'clientname', 'vendorname', 'partyname', 'customername'],
        'display_name'  => ['displayname', 'shortname', 'alias', 'tradename'],
        'role'          => ['role', 'type', 'partytype', 'clientorvendor', 'category'],
        'client_type'   => ['clienttype', 'customertype', 'partnertype', 'businesstype'],
        'industry'      => ['industry', 'sector', 'segment'],
        'ownership_type'=> ['ownership', 'ownershiptype', 'constitution', 'entitytype'],
        'gstin'         => ['gstin', 'gst', 'gstno', 'gstnumber'],
        'pan'           => ['pan', 'panno', 'pannumber'],
        'cin'           => ['cin', 'cinno'],
        'tan'           => ['tan', 'tanno'],
        'msme_udyam'    => ['msme', 'udyam', 'msmeudyam', 'udyamno'],
        'state'         => ['state'],
        'website'       => ['website', 'web', 'url', 'site'],
        'status'        => ['status', 'active', 'isactive'],
        'contact_name'  => ['contactperson', 'contactname', 'contact', 'personname', 'spoc'],
        'contact_designation' => ['contactdesignation', 'designation', 'contacttitle'],
        'contact_email' => ['contactemail', 'email', 'emailid', 'mail'],
        'contact_mobile'=> ['contactmobile', 'mobile', 'phone', 'mobileno', 'contactno', 'phoneno'],
        'line1'         => ['addressline1', 'address', 'addressline', 'line1'],
        'line2'         => ['addressline2', 'line2'],
        'city'          => ['city', 'town', 'location'],
        'pincode'       => ['pincode', 'pin', 'postcode', 'zip', 'zipcode'],
        'country'       => ['country'],
        'description'   => ['notes', 'note', 'remarks', 'description', 'comment'],
    ];
    foreach (partner_import_columns() as $k => $label) $aliases[$k][] = org_norm($label);
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

// Turn the sheet into preview rows. Each says what will happen and why, so the
// screen can show it before anything is written.
function partner_import_parse($sheet, $defaultRole = 'Client') {
    if (!$sheet) return ['rows' => [], 'error' => 'The file had no readable rows.'];
    $map = partner_map_header($sheet[0]);
    if (!isset($map['legal_name']))
        return ['rows' => [], 'error' => 'Could not find a company-name column in the header row. '
              . 'Download the template and use its headings, or name the column “Legal name”.'];

    $get = function ($row, $key) use ($map) {
        return isset($map[$key]) ? trim((string)($row[$map[$key]] ?? '')) : '';
    };
    $seenNames = [];        // catches duplicates inside the uploaded file itself
    $rows = [];
    for ($i = 1; $i < count($sheet); $i++) {
        $r = $sheet[$i];
        $name = $get($r, 'legal_name');
        if ($name === '') continue;                       // blank line

        $gstin = clean_gstin($get($r, 'gstin'));
        $pan   = strtoupper($get($r, 'pan')) ?: ($gstin ? pan_from_gstin($gstin) : '');
        $state = $get($r, 'state') ?: ($gstin ? state_from_gstin($gstin) : '');
        $code  = $get($r, 'code');

        $row = [
            'line' => $i + 1,
            'code' => $code,
            'legal_name' => $name,
            'display_name' => $get($r, 'display_name'),
            'role' => partner_match_role($get($r, 'role')) ?: $defaultRole,
            'role_given' => $get($r, 'role'),
            'client_type' => partner_match_option($get($r, 'client_type'), CLIENT_TYPES),
            'industry' => partner_match_option($get($r, 'industry'), INDUSTRIES),
            'ownership_type' => partner_match_option($get($r, 'ownership_type'), OWNERSHIP),
            'gstin' => $gstin, 'pan' => $pan, 'cin' => strtoupper($get($r, 'cin')),
            'tan' => strtoupper($get($r, 'tan')), 'msme_udyam' => $get($r, 'msme_udyam'),
            'state' => $state, 'website' => $get($r, 'website'),
            'status' => (strtolower($get($r, 'status')) === 'inactive' || strtolower($get($r, 'status')) === 'no')
                        ? 'INACTIVE' : 'ACTIVE',
            'description' => $get($r, 'description'),
            'contact_name' => $get($r, 'contact_name'), 'contact_designation' => $get($r, 'contact_designation'),
            'contact_email' => $get($r, 'contact_email'), 'contact_mobile' => $get($r, 'contact_mobile'),
            'line1' => $get($r, 'line1'), 'line2' => $get($r, 'line2'), 'city' => $get($r, 'city'),
            'pincode' => $get($r, 'pincode'), 'country' => $get($r, 'country') ?: 'India',
            'skip' => false, 'notes' => [],
        ];

        // Already on file? Code first — it is the one thing a person chose on
        // purpose — then the tax numbers, then the name.
        $match = null; $by = '';
        if ($code !== '') {
            $m = ops_one("SELECT id, code, legal_name, is_client, is_vendor FROM business_partners WHERE code=?", [$code]);
            if ($m) { $match = $m; $by = 'code'; }
        }
        if (!$match) {
            $d = find_duplicate_partner($name, $gstin, $pan, $row['tan'], 0);
            if ($d) { $match = $d['row']; $by = $d['by']; }
        }
        $row['match_id'] = $match ? (int)$match['id'] : 0;
        $row['match_by'] = $by;
        $row['match_name'] = $match['legal_name'] ?? '';
        $row['action'] = $match ? 'update' : 'create';

        // The same company twice in one sheet: keep the first, flag the rest.
        $norm = normalize_name($name);
        if (isset($seenNames[$norm])) {
            $row['skip'] = true;
            $row['notes'][] = 'Same company as line ' . $seenNames[$norm] . ' of this file.';
        } else $seenNames[$norm] = $i + 1;

        if ($gstin !== '' && !is_valid_gstin($gstin)) $row['notes'][] = 'GSTIN does not look like a valid 15-character number.';
        if ($row['role_given'] !== '' && partner_match_role($row['role_given']) === '')
            $row['notes'][] = 'Role “' . $row['role_given'] . '” was not recognised, so ' . $defaultRole . ' was used.';
        $rows[] = $row;
    }
    return ['rows' => $rows, 'error' => ''];
}

// Write it. Creates what is new, updates what already exists, and never blanks
// a field the sheet left empty — an empty cell means "no change", not "erase".
function partner_import_apply(array $rows) {
    $created = 0; $updated = 0; $skipped = 0; $contacts = 0; $addresses = 0;
    foreach ($rows as $r) {
        if (!empty($r['skip'])) { $skipped++; continue; }
        $roles = partner_import_roles()[$r['role']] ?? partner_import_roles()['Client'];
        $id = (int)($r['match_id'] ?? 0);

        $fields = ['display_name', 'client_type', 'industry', 'ownership_type', 'gstin', 'pan',
                   'cin', 'tan', 'msme_udyam', 'state', 'website', 'description'];
        if ($id) {
            // Roles are additive on an update: a company already marked a client
            // and now appearing on a vendor sheet is both, not suddenly only one.
            // Worked out here rather than in SQL — a bound "1" is a string, and
            // SQLite does not consider '1' equal to 1, so a CASE WHEN in the
            // query silently did nothing.
            $cur = ops_one("SELECT is_client, is_vendor, is_subcontractor FROM business_partners WHERE id=?", [$id]) ?: [];
            $set = ['is_client = ?', 'is_vendor = ?', 'is_subcontractor = ?', 'status = ?'];
            $args = [
                ((int)($cur['is_client'] ?? 0) || $roles['is_client']) ? 1 : 0,
                ((int)($cur['is_vendor'] ?? 0) || $roles['is_vendor']) ? 1 : 0,
                ((int)($cur['is_subcontractor'] ?? 0) || $roles['is_subcontractor']) ? 1 : 0,
                $r['status'],
            ];
            $set[] = 'legal_name = ?'; $args[] = $r['legal_name'];
            foreach ($fields as $f) {
                if (trim((string)($r[$f] ?? '')) === '') continue;    // blank never erases
                $set[] = "$f = ?"; $args[] = $r[$f];
            }
            $args[] = $id;
            db()->prepare("UPDATE business_partners SET " . implode(', ', $set) . " WHERE id = ?")->execute($args);
            $updated++;
        } else {
            $code = trim((string)$r['code']);
            if ($code === '') $code = partner_next_code($r['legal_name']);
            $cols = ['code', 'legal_name', 'is_client', 'is_vendor', 'is_subcontractor', 'status', 'created_at'];
            $vals = [$code, $r['legal_name'], $roles['is_client'], $roles['is_vendor'],
                     $roles['is_subcontractor'], $r['status'], date('c')];
            foreach ($fields as $f) {
                if (trim((string)($r[$f] ?? '')) === '') continue;
                $cols[] = $f; $vals[] = $r[$f];
            }
            db()->prepare("INSERT INTO business_partners (" . implode(',', $cols) . ") VALUES ("
                . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
            $id = (int)db()->lastInsertId();
            $created++;
        }

        // A contact, if one was given and that person is not already on file.
        if (trim((string)$r['contact_name']) !== '') {
            $have = ops_val("SELECT id FROM partner_contacts WHERE partner_id=? AND LOWER(name)=?",
                            [$id, strtolower(trim($r['contact_name']))]);
            if (!$have) {
                $isFirst = !ops_val("SELECT id FROM partner_contacts WHERE partner_id=?", [$id]);
                db()->prepare("INSERT INTO partner_contacts (partner_id,name,designation,email,mobile,is_primary)
                               VALUES (?,?,?,?,?,?)")
                    ->execute([$id, $r['contact_name'], $r['contact_designation'], $r['contact_email'],
                               $r['contact_mobile'], $isFirst ? 1 : 0]);
                $contacts++;
            }
        }
        // An address, if one was given and this partner has none yet.
        if (trim((string)$r['line1']) !== '' || trim((string)$r['city']) !== '') {
            $have = ops_val("SELECT id FROM partner_addresses WHERE partner_id=?", [$id]);
            if (!$have) {
                db()->prepare("INSERT INTO partner_addresses (partner_id,address_type,line1,line2,city,state,pincode,country,is_primary)
                               VALUES (?,'REGISTERED',?,?,?,?,?,?,1)")
                    ->execute([$id, $r['line1'], $r['line2'], $r['city'], $r['state'], $r['pincode'], $r['country']]);
                $addresses++;
            }
        }
        idems_log('partner', $id, $r['match_id'] ? 'IMPORT_UPDATE' : 'IMPORT_CREATE',
                  ['field' => $r['legal_name']]);
    }
    return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped,
            'contacts' => $contacts, 'addresses' => $addresses];
}

// The same GEN-XXXX-0001 series the rest of the app uses, so an imported
// company is indistinguishable from one typed in by hand.
function partner_next_code($name) {
    $token = short_token($name);
    $last = ops_val("SELECT code FROM business_partners WHERE code LIKE ? ORDER BY code DESC LIMIT 1", ["GEN-$token-%"]);
    $seq = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
    return sprintf('GEN-%s-%04d', $token, $seq);
}

// ---------------------------------------------------------------------------
//  Handlers
// ---------------------------------------------------------------------------
function ops_partner_template() {
    ops_require(can('mod.clients.view') || can('mod.vendors.view') || is_admin_level(),
                'You cannot export the ' . Tl('client') . ' register.');
    $withData = ($_GET['blank'] ?? '') !== '1';
    $kind = in_array($_GET['kind'] ?? '', ['clients', 'vendors'], true) ? $_GET['kind'] : 'all';
    $fmt  = ($_GET['fmt'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
    $stamp = date('Y-m-d');
    $base = 'clients-and-vendors-' . $stamp;
    if ($fmt === 'xlsx') {
        $bytes = partner_template_xlsx($withData, $kind);
        if ($bytes === null) {
            flash('Excel export needs the ZIP extension, which this server does not have. A CSV has been downloaded instead — it opens in Excel and uploads back the same way.', 'error');
            $fmt = 'csv';
        } else {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
            header('Content-Length: ' . strlen($bytes));
            echo $bytes;
            return true;
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    echo "\xEF\xBB\xBF" . partner_template_csv($withData, $kind);
    return true;
}

function ops_partner_import($method) {
    ops_require(is_admin_level() || can('mod.clients.edit') || can('mod.vendors.edit'),
                'You cannot import ' . Tlp('client') . ' or ' . Tlp('vendor') . '.');
    if ($method === 'POST') {
        $do = $_POST['do'] ?? '';
        if ($do === 'preview') {
            if (empty($_FILES['sheet']['tmp_name']) || !is_uploaded_file($_FILES['sheet']['tmp_name'])) {
                flash('Choose a file first.', 'error'); redirect('/partner-import');
            }
            // The front controller has already refused anything dangerous —
            // it screens every upload on every request, not just this one.
            $sheet = org_read_sheet($_FILES['sheet']['tmp_name'], $_FILES['sheet']['name']);
            $role = partner_match_role($_POST['default_role'] ?? '') ?: 'Client';
            $res = partner_import_parse($sheet, $role);
            if ($res['error']) { flash($res['error'], 'error'); redirect('/partner-import'); }
            if (!$res['rows']) { flash('No companies were found in that file.', 'error'); redirect('/partner-import'); }
            $_SESSION['pi_rows'] = $res['rows'];
            $_SESSION['pi_name'] = $_FILES['sheet']['name'];
            redirect('/partner-import');
        }
        if ($do === 'cancel') {
            unset($_SESSION['pi_rows'], $_SESSION['pi_name']);
            redirect('/partner-import');
        }
        if ($do === 'apply') {
            $rows = $_SESSION['pi_rows'] ?? [];
            if (!$rows) { flash('The preview expired — upload the file again.', 'error'); redirect('/partner-import'); }
            $skip = (array)($_POST['skip'] ?? []);
            $roleOverride = (array)($_POST['row_role'] ?? []);
            foreach ($rows as $i => &$r) {
                if (!empty($skip[$i])) $r['skip'] = true;
                if (isset($roleOverride[$i]) && isset(partner_import_roles()[$roleOverride[$i]]))
                    $r['role'] = $roleOverride[$i];
            }
            unset($r);
            $res = partner_import_apply($rows);
            unset($_SESSION['pi_rows'], $_SESSION['pi_name']);
            flash($res['created'] . ' created, ' . $res['updated'] . ' updated'
                . ($res['skipped'] ? ', ' . $res['skipped'] . ' skipped' : '') . '. '
                . $res['contacts'] . ' contact(s) and ' . $res['addresses'] . ' address(es) added.');
            redirect('/clients');
        }
        redirect('/partner-import');
    }
    view('ops/partner_import', [
        'rows' => $_SESSION['pi_rows'] ?? [], 'fileName' => $_SESSION['pi_name'] ?? '',
        'roles' => array_keys(partner_import_roles()), 'columns' => partner_import_columns(),
    ]);
}
