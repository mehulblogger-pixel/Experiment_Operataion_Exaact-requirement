<?php
// ===========================================================================
//  The same company, twice
//
//  The add screens refuse an exact repeat — same name, same GSTIN, same PAN.
//  What they cannot catch is the pair that were typed months apart by two
//  people who spelt it differently: "Kaveri Pressure Vessels" and "Kaveri
//  Pressure Vessel Pvt. Ltd.", or one with the M/s and one without.
//
//  Nobody notices until somebody adds up two lots of the same customer by hand
//  and gets a different answer from the system. So: find the likely pairs, show
//  them side by side with everything hanging off each one, and let a person say
//  yes or no. Never merge automatically — a false merge is far worse than a
//  duplicate, because it cannot be undone from the screen.
// ===========================================================================

// A name reduced to what is actually distinctive: no punctuation, no company
// suffixes, no "M/s", and the words in a fixed order so "Kaveri Vessels" and
// "Vessels Kaveri" land in the same place.
function dd_key($name) {
    $n = normalize_name($name);
    $n = preg_replace('/\b(india|indian|group|industries|industry|engineering|engineers|works|corporation|corp|enterprises|enterprise|international|technologies|technology|services|service|solutions|systems|and|the)\b/', ' ', $n);
    $w = array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($n)))));
    sort($w);
    return implode(' ', $w);
}

// How alike two names are, 0..1. Cheap and predictable — this only has to be
// good enough to put a pair in front of a person.
function dd_similar($a, $b) {
    $a = dd_key($a); $b = dd_key($b);
    if ($a === '' || $b === '') return 0.0;
    if ($a === $b) return 1.0;
    similar_text($a, $b, $pct);
    // Sharing every word of the shorter name counts for a lot: "Kaveri Vessels"
    // inside "Kaveri Pressure Vessels" is the commonest way this happens.
    $wa = explode(' ', $a); $wb = explode(' ', $b);
    $common = count(array_intersect($wa, $wb));
    $cover = $common / max(1, min(count($wa), count($wb)));
    return max($pct / 100, $cover >= 1 ? 0.9 : $cover * 0.8);
}

// How much is hanging off a partner record. A pair where one side has
// everything and the other has nothing is the easy, safe merge.
function dd_weight($id) {
    $id = (int)$id;
    $n = 0;
    foreach ([['calls', 'client_id'], ['calls', 'vendor_id'], ['quotations', 'client_id'],
              ['crm_inquiries', 'client_id'], ['boss_numbers', 'client_id'],
              ['partner_contacts', 'partner_id'], ['partner_addresses', 'partner_id'],
              ['partner_contracts', 'partner_id'], ['partner_purchase_orders', 'partner_id'],
              ['partner_notes', 'partner_id'], ['partner_registrations', 'partner_id']] as [$t, $c]) {
        try { $n += (int)ops_val("SELECT COUNT(*) FROM $t WHERE $c = ?", [$id]); } catch (Throwable $e) {}
    }
    return $n;
}

// Everything that would move if these two were merged, named so a person can
// weigh it up before saying yes.
function dd_holdings($id) {
    $id = (int)$id;
    $out = [];
    $count = function ($t, $c) use ($id) {
        try { return (int)ops_val("SELECT COUNT(*) FROM $t WHERE $c = ?", [$id]); } catch (Throwable $e) { return 0; }
    };
    $bits = [
        'contacts'   => ['partner_contacts', 'partner_id', 'contact'],
        'addresses'  => ['partner_addresses', 'partner_id', 'address'],
        'contracts'  => ['partner_contracts', 'partner_id', 'contract'],
        'orders'     => ['partner_purchase_orders', 'partner_id', 'purchase order'],
        'inquiries'  => ['crm_inquiries', 'client_id', Tl('inquiry')],
        'quotes'     => ['quotations', 'client_id', Tl('quote')],
        'boss'       => ['boss_numbers', 'client_id', T('boss') . ' number'],
        'callsC'     => ['calls', 'client_id', Tl('call') . ' as ' . Tl('client')],
        'callsV'     => ['calls', 'vendor_id', Tl('call') . ' as site'],
        'notes'      => ['partner_notes', 'partner_id', 'note'],
    ];
    foreach ($bits as $k => [$t, $c, $label]) {
        $n = $count($t, $c);
        if ($n) $out[] = $n . ' ' . $label . ($n === 1 ? '' : 's');
    }
    return $out;
}

// Candidate pairs, most alike first. Only pairs that share a role — a client
// and a site of the same name may genuinely be two records, and often are.
function dd_candidates($limit = 60) {
    $rows = ops_all("SELECT id, code, legal_name, display_name, is_client, is_vendor, gstin, pan, status, created_at
                     FROM business_partners ORDER BY id");
    $out = [];
    $n = count($rows);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $a = $rows[$i]; $b = $rows[$j];
            // Different tax identities are different companies, full stop.
            $ga = strtoupper(trim((string)$a['gstin'])); $gb = strtoupper(trim((string)$b['gstin']));
            if ($ga !== '' && $gb !== '' && $ga !== $gb) continue;
            $pa = strtoupper(trim((string)$a['pan']));   $pb = strtoupper(trim((string)$b['pan']));
            if ($pa !== '' && $pb !== '' && $pa !== $pb) continue;
            $score = dd_similar($a['legal_name'], $b['legal_name']);
            $why = 'the names are alike';
            if ($ga !== '' && $ga === $gb) { $score = 1.0; $why = 'the same GSTIN'; }
            elseif ($pa !== '' && $pa === $pb) { $score = max($score, 0.95); $why = 'the same PAN'; }
            if ($score < 0.82) continue;
            $out[] = [
                'a' => $a, 'b' => $b, 'score' => round($score, 3), 'why' => $why,
                'aw' => dd_weight($a['id']), 'bw' => dd_weight($b['id']),
            ];
        }
    }
    usort($out, function ($x, $y) { return $y['score'] <=> $x['score']; });
    return array_slice($out, 0, $limit);
}

// Everywhere a partner id is written down. Derived from the schema rather than
// listed by hand, so a table added next year is not quietly left behind holding
// a pointer to a record that no longer exists.
function dd_reference_columns() {
    $out = [];
    foreach (db_tables() as $t) {
        if ($t === 'business_partners') continue;
        foreach (db_columns($t) as $c) {
            if (in_array($c, ['client_id', 'vendor_id', 'partner_id', 'related_id', 'subcontractor_id'], true))
                $out[] = [$t, $c];
        }
    }
    return $out;
}

// Merge b into a. Everything pointing at b is repointed at a, anything a is
// missing is taken from b, and b is retired rather than deleted — a record
// somebody may still be looking for should not simply vanish.
function dd_merge($keepId, $dropId, $reason = '') {
    $keepId = (int)$keepId; $dropId = (int)$dropId;
    if (!$keepId || !$dropId || $keepId === $dropId) return ['ok' => false, 'error' => 'Pick two different records.'];
    $keep = ops_one("SELECT * FROM business_partners WHERE id=?", [$keepId]);
    $drop = ops_one("SELECT * FROM business_partners WHERE id=?", [$dropId]);
    if (!$keep || !$drop) return ['ok' => false, 'error' => 'One of those records no longer exists.'];

    $moved = [];
    foreach (dd_reference_columns() as [$t, $c]) {
        try {
            $st = db()->prepare("UPDATE $t SET $c = ? WHERE $c = ?");
            $st->execute([$keepId, $dropId]);
            if ($st->rowCount() > 0) $moved[$t . '.' . $c] = $st->rowCount();
        } catch (Throwable $e) { continue; }
    }
    // Fields the surviving record does not have, taken from the one going away.
    $fill = ['display_name','client_type','industry','ownership_type','gstin','pan','cin','tan',
             'msme_udyam','state','website','description','inspection_types','home_branch_id'];
    $set = []; $args = [];
    foreach ($fill as $f) {
        if (!array_key_exists($f, $keep)) continue;
        if (trim((string)($keep[$f] ?? '')) !== '') continue;
        if (trim((string)($drop[$f] ?? '')) === '') continue;
        $set[] = "$f = ?"; $args[] = $drop[$f];
    }
    // Roles are additive: whatever either one was, the survivor is.
    $set[] = 'is_client = ?';        $args[] = ((int)$keep['is_client'] || (int)$drop['is_client']) ? 1 : 0;
    $set[] = 'is_vendor = ?';        $args[] = ((int)$keep['is_vendor'] || (int)$drop['is_vendor']) ? 1 : 0;
    $set[] = 'is_subcontractor = ?'; $args[] = ((int)($keep['is_subcontractor'] ?? 0) || (int)($drop['is_subcontractor'] ?? 0)) ? 1 : 0;
    $args[] = $keepId;
    db()->prepare("UPDATE business_partners SET " . implode(', ', $set) . " WHERE id = ?")->execute($args);

    // The record going away is retired, not deleted, and says where it went.
    $note = 'Merged into ' . $keep['code'] . ' — ' . $keep['legal_name']
          . ($reason !== '' ? ' (' . $reason . ')' : '');
    db()->prepare("UPDATE business_partners SET status='MERGED', legal_name=?, description=? WHERE id=?")
        ->execute([$drop['legal_name'] . ' [merged]',
                   trim(($drop['description'] ?? '') . "\n" . $note), $dropId]);
    db()->prepare("INSERT INTO partner_notes (partner_id,note,author_name,created_at) VALUES (?,?,?,?)")
        ->execute([$keepId, $drop['code'] . ' — ' . $drop['legal_name'] . ' was merged into this record. '
                   . ($reason !== '' ? $reason : ''), user_name(current_user()), date('c')]);
    idems_log('partner', $keepId, 'MERGE', ['field' => $drop['code'], 'reason' => $reason,
        'new' => json_encode($moved)]);
    return ['ok' => true, 'moved' => $moved, 'total' => array_sum($moved),
            'keep' => $keep, 'drop' => $drop];
}

// ---------------------------------------------------------------------------
//  The screen
// ---------------------------------------------------------------------------
function ops_dedupe($method) {
    ops_require(is_admin_level() || can('mod.clients.edit') || can('mod.vendors.edit'),
                'You cannot merge ' . Tlp('client') . ' or ' . Tlp('vendor') . '.');
    if ($method === 'POST') {
        ops_require(is_master() || can('settings.manage') || can('mod.clients.edit'),
                    'Only an administrator can merge two records.');
        $res = dd_merge($_POST['keep'] ?? 0, $_POST['drop'] ?? 0, trim((string)($_POST['reason'] ?? '')));
        if (!$res['ok']) { flash($res['error'], 'error'); redirect('/duplicates'); }
        flash($res['drop']['legal_name'] . ' merged into ' . $res['keep']['legal_name'] . '. '
            . $res['total'] . ' record(s) repointed. The old record is marked merged rather than deleted, '
            . 'so anybody searching for it still finds where it went.');
        redirect('/partner?id=' . $res['keep']['id']);
    }
    $pairs = dd_candidates();
    foreach ($pairs as &$p) {
        $p['ah'] = dd_holdings($p['a']['id']);
        $p['bh'] = dd_holdings($p['b']['id']);
    }
    unset($p);
    view('ops/dedupe', ['pairs' => $pairs]);
}
