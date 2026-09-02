<?php
// ===========================================================================
//  Gap-8 — the UNIFIED PERSON, as a resolve-through-link view (never a merge).
//
//  EXAACT keeps three identity records for the same human — a marketplace
//  professional (cx_professionals), an internal inspector (inspectors) and a
//  recruitment candidate (candidates) — already joined by the reversible
//  cx_identity_link ledger (professional_id is the hub). Passport, credentials,
//  taxonomy and geo attach to the professional record only, so the other
//  identities did not "inherit" them, and a candidate↔inspector link was only
//  reachable transitively.
//
//  This closes that WITHOUT a destructive merge, exactly as the canonical model
//  demands ("convergence through read-views and mapping layers, never table
//  merges"): one resolver returns every identity of a person from ANY of them,
//  and one gatherer reads that person's credentials across all pools through the
//  single Gap-5 verification ladder. Additive and read-only — no record is
//  merged, moved or deleted; unlink still fully reverses it.
// ===========================================================================

/**
 * Every identity of the person behind one identity. $kind ∈ professional|inspector|candidate.
 * Resolves through the professional hub, so a candidate and an inspector linked to the same
 * professional are found for each other (the transitive case). Read-only.
 *   → ['professional_ids'=>[…], 'inspector_ids'=>[…], 'candidate_ids'=>[…]]
 */
function connect_person_resolve($kind, $id) {
    if (function_exists('connect_identity_migrate')) { try { connect_identity_migrate(); } catch (Throwable $e) {} }
    $id = (int)$id; $kind = strtolower((string)$kind);
    $pros = []; $insps = []; $cands = [];
    if ($kind === 'professional' && $id > 0) $pros[$id] = 1;
    elseif ($kind === 'inspector' && $id > 0) $insps[$id] = 1;
    elseif ($kind === 'candidate' && $id > 0) $cands[$id] = 1;
    else return ['professional_ids' => [], 'inspector_ids' => [], 'candidate_ids' => []];

    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    // 1) from an inspector/candidate seed, climb to its professional hub(s)
    foreach (array_keys($insps) as $i)
        foreach ($all("SELECT professional_id FROM cx_identity_link WHERE inspector_id=? AND status='LINKED'", [$i]) as $r)
            if ((int)$r['professional_id'] > 0) $pros[(int)$r['professional_id']] = 1;
    foreach (array_keys($cands) as $c)
        foreach ($all("SELECT professional_id FROM cx_identity_link WHERE candidate_id=? AND status='LINKED'", [$c]) as $r)
            if ((int)$r['professional_id'] > 0) $pros[(int)$r['professional_id']] = 1;
    // 2) from every professional hub, gather all linked inspectors and candidates
    foreach (array_keys($pros) as $p)
        foreach ($all("SELECT inspector_id, candidate_id FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$p]) as $r) {
            if ((int)$r['inspector_id'] > 0) $insps[(int)$r['inspector_id']] = 1;
            if ((int)$r['candidate_id'] > 0) $cands[(int)$r['candidate_id']] = 1;
        }
    return ['professional_ids' => array_keys($pros), 'inspector_ids' => array_keys($insps), 'candidate_ids' => array_keys($cands)];
}

/** Is this person present in more than one pool? (i.e. genuinely linked across identities) */
function connect_person_is_linked($resolve) {
    $n = (count($resolve['professional_ids'] ?? []) > 0 ? 1 : 0)
       + (count($resolve['inspector_ids'] ?? []) > 0 ? 1 : 0)
       + (count($resolve['candidate_ids'] ?? []) > 0 ? 1 : 0);
    return $n >= 2;
}

/** A display name for the person — first professional, else inspector, else candidate. */
function connect_person_name($resolve) {
    $one = function ($sql, $a) { try { return (string)(ops_val($sql, $a) ?? ''); } catch (Throwable $e) { return ''; } };
    foreach (($resolve['professional_ids'] ?? []) as $p) { $n = $one("SELECT name FROM cx_professionals WHERE id=?", [$p]); if ($n !== '') return $n; }
    foreach (($resolve['inspector_ids'] ?? []) as $i) { $n = $one("SELECT name FROM inspectors WHERE id=?", [$i]); if ($n !== '') return $n; }
    foreach (($resolve['candidate_ids'] ?? []) as $c) {
        try { $row = ops_one("SELECT first_name, last_name FROM candidates WHERE id=?", [$c]); } catch (Throwable $e) { $row = null; }
        $n = trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')));
        if ($n !== '') return $n;
    }
    return '';
}

/**
 * The person's credentials across EVERY linked pool, each read through the one Gap-5
 * verification ladder (connect_cred_verify_state). So an inspector's certs and a
 * professional's certs are read on the same Declared→Documented→Verified→Expired
 * scale — the "inheritance" the merge would have given, without the merge. Read-only.
 *   → [ ['source'=>'professional'|'inspector', 'owner_id'=>…, 'name'=>…, 'state'=>[…ladder…]] … ]
 */
function connect_person_credentials($resolve) {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $ladder = fn($c) => function_exists('connect_cred_verify_state') ? connect_cred_verify_state($c) : ['code' => '?', 'label' => '', 'tone' => 'mut'];
    $out = [];
    foreach (($resolve['professional_ids'] ?? []) as $p)
        foreach ($all("SELECT * FROM cx_pro_certs WHERE pro_id=?", [$p]) as $c)
            $out[] = ['source' => 'professional', 'owner_id' => (int)$p, 'name' => (string)($c['name'] ?? ''), 'state' => $ladder($c)];
    foreach (($resolve['inspector_ids'] ?? []) as $i)
        foreach ($all("SELECT * FROM inspector_certs WHERE inspector_id=?", [$i]) as $c)
            $out[] = ['source' => 'inspector', 'owner_id' => (int)$i, 'name' => (string)($c['name'] ?? ''), 'state' => $ladder($c)];
    return $out;
}

/** Compact summary for display: the identities, a name, and a cross-pool credential tally. */
function connect_person_summary($kind, $id) {
    $r = connect_person_resolve($kind, $id);
    $creds = connect_person_credentials($r);
    $verified = 0; foreach ($creds as $c) if (($c['state']['code'] ?? '') === 'VERIFIED') $verified++;
    return [
        'resolve'    => $r,
        'linked'     => connect_person_is_linked($r),
        'name'       => connect_person_name($r),
        'pools'      => ['professional' => count($r['professional_ids']), 'inspector' => count($r['inspector_ids']), 'candidate' => count($r['candidate_ids'])],
        'credentials'=> count($creds),
        'verified'   => $verified,
    ];
}
