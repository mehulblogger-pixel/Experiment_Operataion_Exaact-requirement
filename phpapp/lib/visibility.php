<?php
// Phase 2 §72 — one visibility vocabulary and one single-record gate, over the EXISTING flags.
//
// Visibility is already enforced, but by several different per-record mechanisms: report_docs carry
// a `vendor_visible` int flag (cvp.php), nonconformities carry a `visibility` code (ncdca.php's
// NCDCA_VISIBILITY, mapped to audiences by CVP_VISIBILITY_AUDIENCE), site-log lines carry a
// `client_visible` int flag. Each is filtered at its own portal query (cvp_visibility_sql /
// cvp_client_flag_sql). There was no ONE answer to "given this single record, who may see it?" — the
// scalar twin of those SQL fragments — and no shared vocabulary across the record types.
//
// This adds exactly that, as a thin canonical layer that DELEGATES to cvp_can_see() for every code
// cvp already knows, so the two can never disagree. It reads the existing flags; it changes none of
// them, and it removes nothing. (Mirrors the §51 scope_allows()/scope_clause() and §23/24 party pattern.)

// The canonical classification, a superset of the existing codes. Ordered most-open to most-closed.
const VIS_CLASSES = [
    'PUBLIC'       => 'Public',          // anyone, including unauthenticated
    'SHARED'       => 'Client & vendor',
    'CLIENT'       => 'Client only',
    'VENDOR'       => 'Vendor only',
    'INTERNAL'     => 'Internal (staff)',
    'CONFIDENTIAL' => 'Confidential (restricted staff)',
];

// The reader types. Staff are INTERNAL; a signed-in portal person is CLIENT or VENDOR; PUBLIC is an
// unauthenticated reader (used only by records explicitly classified PUBLIC).
const VIS_AUDIENCES = ['INTERNAL', 'CLIENT', 'VENDOR', 'PUBLIC'];

// Fold any legacy / per-record code onto one canonical class. Unknown or blank → CONFIDENTIAL, so the
// safe default is always "not shown", never "shown".
function visibility_normalize($code) {
    $c = strtoupper(trim((string)$code));
    static $map = [
        'PUBLIC' => 'PUBLIC',
        'SHARED' => 'SHARED', 'BOTH' => 'SHARED',
        'CLIENT' => 'CLIENT', 'CLIENT_VISIBLE' => 'CLIENT', 'CLIENTVISIBLE' => 'CLIENT',
        'VENDOR' => 'VENDOR', 'VENDOR_VISIBLE' => 'VENDOR', 'VENDORVISIBLE' => 'VENDOR',
        'INTERNAL' => 'INTERNAL',
        'CONFIDENTIAL' => 'CONFIDENTIAL', 'RESTRICTED' => 'CONFIDENTIAL', 'MGMT_ONLY' => 'CONFIDENTIAL',
    ];
    return $map[$c] ?? 'CONFIDENTIAL';
}

// The single-record gate: may this audience see a record of this class? INTERNAL (staff) sees
// everything. For the codes cvp already knows, delegate to cvp_can_see() so this never diverges from
// the portal-query filtering; PUBLIC/SHARED/CONFIDENTIAL are the canonical extensions.
function visibility_can_see($class, $audience) {
    $cl = visibility_normalize($class);
    $au = strtoupper(trim((string)$audience));
    if ($au === 'INTERNAL') return true;                      // staff
    if ($cl === 'PUBLIC') return true;                        // anyone
    if ($cl === 'INTERNAL' || $cl === 'CONFIDENTIAL') return false;
    // CLIENT / VENDOR / SHARED — the codes cvp owns. Reuse its map verbatim.
    if (function_exists('cvp_can_see')) {
        $legacy = ['CLIENT' => 'CLIENT_VISIBLE', 'VENDOR' => 'VENDOR_VISIBLE', 'SHARED' => 'SHARED'];
        return cvp_can_see($legacy[$cl] ?? $cl, $au);
    }
    // Fallback if cvp is not loaded (kept identical to CVP_VISIBILITY_AUDIENCE).
    $allow = ['CLIENT' => ['CLIENT'], 'VENDOR' => ['VENDOR'], 'SHARED' => ['CLIENT', 'VENDOR']];
    return in_array($au, $allow[$cl] ?? [], true);
}

// The canonical class of one actual record, read from whichever existing flag its kind uses.
// kind: 'REPORT' (report_docs.vendor_visible), 'NCR' (nonconformities.visibility),
//       'SITELOG'/'PDSO' (client_visible int flag). Anything else falls back to the row's own
//       `visibility` column if present, else INTERNAL.
function visibility_class_of($kind, $row) {
    $k = strtoupper((string)$kind);
    $row = (array)$row;
    if ($k === 'REPORT') {
        // A report is staff-internal until explicitly shared to the vendor portal.
        return !empty($row['vendor_visible']) ? 'VENDOR' : 'INTERNAL';
    }
    if ($k === 'NCR' || $k === 'ISSUE') {
        return visibility_normalize($row['visibility'] ?? 'INTERNAL');
    }
    if ($k === 'SITELOG' || $k === 'PDSO' || $k === 'DEPARTURE') {
        return !empty($row['client_visible']) ? 'CLIENT' : 'INTERNAL';
    }
    if (array_key_exists('visibility', $row)) return visibility_normalize($row['visibility']);
    return 'INTERNAL';
}

// Convenience: can the CURRENT reader (staff/client/vendor, via cvp_current_audience) see this record?
function visibility_reader_can_see($kind, $row) {
    $au = function_exists('cvp_current_audience') ? cvp_current_audience() : 'INTERNAL';
    return visibility_can_see(visibility_class_of($kind, $row), $au);
}
