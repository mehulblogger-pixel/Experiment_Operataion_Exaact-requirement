<?php
// ============================================================================
//  CVP — Client & Vendor Portal engine (Phase 10)
//
//  This file is the CONFIDENTIALITY SPINE the rest of Phase 10 is built on. Two
//  external audiences will read records that were written by staff — a client
//  and (from Slice 2) a vendor. The one rule that must never be got wrong is:
//
//      an internal note, an internal investigation, another party's response —
//      none of it is shown to an external audience unless it is EXPLICITLY
//      marked visible to THAT audience.
//
//  The engines already record the intent. A nonconformity carries a `visibility`
//  (INTERNAL / CLIENT_VISIBLE / VENDOR_VISIBLE / RESTRICTED / MGMT_ONLY —
//  ncdca.php NCDCA_VISIBILITY) and a site-log line carries `client_visible`
//  (pdso.php dep_site_log). Until now nothing filtered on either — the portal
//  simply never SELECTed the columns, which is safe only for as long as nobody
//  writes a new query. This turns that convention into an enforced gate that
//  every external read passes through, in the WHERE clause, the same way the
//  client portal already scopes by partner id.
//
//  Nothing here removes or rewrites an existing engine. It is a lens the portal
//  reads through; staff screens are unchanged.
// ============================================================================

// The three audiences a record can be read by. INTERNAL is staff — it sees
// everything, and is the audience of every existing staff screen. CLIENT and
// VENDOR are the two portal audiences.
const CVP_AUDIENCE = ['INTERNAL' => 'Internal', 'CLIENT' => 'Client', 'VENDOR' => 'Vendor'];

// Map each issue-visibility code (ncdca.php NCDCA_VISIBILITY) to the EXTERNAL
// audiences allowed to see a row carrying it. A code that maps to no external
// audience is internal-only. Unknown/blank codes are treated as INTERNAL — the
// safe default is "not shown", never "shown".
const CVP_VISIBILITY_AUDIENCE = [
    'INTERNAL'       => [],                    // staff only
    'RESTRICTED'     => [],                    // staff only, tighter handling
    'MGMT_ONLY'      => [],                    // staff only
    'CLIENT_VISIBLE' => ['CLIENT'],
    'VENDOR_VISIBLE' => ['VENDOR'],
    'SHARED'         => ['CLIENT', 'VENDOR'],  // reserved: visible to both parties
];

function cvp_migrate() {
    static $done = false; if ($done) return; $done = true;
    // Slice 1 adds no tables — it is a read-time gate over columns the engines
    // already own. The function exists so the boot chain can call it and later
    // slices (vendor users, portal notifications) can extend it in one place.
}

// ---------------------------------------------------------------------------
//  Row-level gate — "may this audience see a row with this visibility?"
// ---------------------------------------------------------------------------
// INTERNAL (staff) sees everything. An external audience sees a row only if the
// row's visibility code lists that audience. Used to double-check a single row
// after it is fetched; the SQL builder below is what keeps a whole query clean.
function cvp_can_see($visibility, $audience) {
    $audience = strtoupper((string)$audience);
    if ($audience === 'INTERNAL') return true;
    $code = strtoupper(trim((string)$visibility));
    $allowed = CVP_VISIBILITY_AUDIENCE[$code] ?? [];
    return in_array($audience, $allowed, true);
}

// The set of visibility codes an external audience is allowed to read.
function cvp_visible_codes($audience) {
    $audience = strtoupper((string)$audience);
    $codes = [];
    foreach (CVP_VISIBILITY_AUDIENCE as $code => $aud) {
        if (in_array($audience, $aud, true)) $codes[] = $code;
    }
    return $codes;
}

// Build a WHERE fragment restricting a query to rows the audience may see, on a
// `visibility` column. Returns [sqlFragment, args] so it drops straight into an
// existing prepared query. INTERNAL returns a pass-through. An external audience
// with no visible codes returns a fragment that matches nothing — an empty
// register is the correct answer, never "everything".
//
//   [$vw, $va] = cvp_visibility_sql('n.visibility', 'CLIENT');
//   $rows = ops_all("SELECT ... WHERE n.vendor_id=? AND $vw", array_merge([$vid], $va));
function cvp_visibility_sql($col, $audience) {
    if (strtoupper((string)$audience) === 'INTERNAL') return ['1=1', []];
    $codes = cvp_visible_codes($audience);
    if (!$codes) return ['1=0', []];
    $ph = implode(',', array_fill(0, count($codes), '?'));
    return ["$col IN ($ph)", $codes];
}

// The int-flag mechanism used by dep_site_log (client_visible = 1). Only the
// CLIENT audience is gated by it today; anything else that is not INTERNAL sees
// nothing through this flag. Returns [sqlFragment, args].
function cvp_client_flag_sql($col = 'client_visible', $audience = 'CLIENT') {
    if (strtoupper((string)$audience) === 'INTERNAL') return ['1=1', []];
    if (strtoupper((string)$audience) === 'CLIENT')   return ["COALESCE($col,0) = 1", []];
    return ['1=0', []];
}

// Which audience is the CURRENT reader? Staff (a real current_user) are INTERNAL.
// A signed-in portal person is CLIENT today; Slice 2 adds VENDOR here when a
// vendor session key exists. Kept in one place so every surface agrees.
function cvp_current_audience() {
    if (function_exists('cvp_vendor_user') && cvp_vendor_user()) return 'VENDOR';
    if (function_exists('portal_user') && portal_user())         return 'CLIENT';
    return 'INTERNAL';
}
