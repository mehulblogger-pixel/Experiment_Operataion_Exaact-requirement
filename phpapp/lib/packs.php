<?php
// ============================================================================
//  Industry packs — how a general product carries specialist rules
//
//  CORRECTING MYSELF. Two messages ago I argued that accreditation rules must
//  live in code and must not be administrator-configurable, and the owner
//  agreed. That was the right answer for ONE inspection body running its own
//  system. It is the wrong answer for a product sold to many businesses, which
//  is what this is. A trading company installing this CRM must not meet an
//  ISO/IEC 17020 competence gate on its way to allocating a delivery.
//
//  Both things are true at once, and this file is how:
//
//    * The CORE has no industry rules at all.
//    * A PACK carries them, and a pack is installed or it is not.
//    * Inside a pack, its rules ARE hard — an inspection body cannot switch
//      off §8.7.3 any more than it could before, because the pack encodes a
//      standard, not a preference.
//
//  So "configurable" happens at install time, by choosing packs, not at run
//  time by unticking a rule. That keeps the accreditation honest and the
//  product universal.
//
//  WHAT THIS COSTS, MEASURED RATHER THAN GUESSED. The application is roughly
//  half general (8,657 lines) and half inspection-specific (8,831 lines), plus
//  ops.php in the middle. But the COUPLING is tiny: six call sites where a
//  specialist rule reaches into a shared path — four in job allocation, two in
//  report finalisation. Most were already written behind function_exists(),
//  which is an accidental plug-in boundary. This file makes it a deliberate one.
//
//  Deliberately NOT built: a marketplace, remote pack download, or packs that
//  ship executable code from outside the repository. A pack is a file in this
//  application that registers handlers at boot. Installing one is switching it
//  on. Anything more is a supply-chain problem nobody here wants.
// ============================================================================

// The named points where a pack may intervene. Adding a hook is a deliberate
// act — it is a promise to keep that point stable, so the list stays short.
const PACK_HOOKS = [
    'work.assign'      => 'Before somebody is assigned to work (allocation)',
    'document.issue'   => 'Before a document or report is issued',
    'record.close'     => 'Before a record is closed',
    'customer.create'  => 'Before a customer is created',
    'timeline.extra'   => 'Extra entries for the activity timeline',
];

// Which packs this installation runs. A setting, so one codebase serves a
// trading company and an inspection body without a build flag.
function packs_enabled($reload = false) {
    static $on = null;
    if ($on !== null && !$reload) return $on;
    $raw = (string)setting_get('packs_enabled', 'inspection');
    $on = array_values(array_filter(array_map('trim', explode(',', $raw))));
    return $on;
}

function pack_on($key) { return in_array($key, packs_enabled(), true); }

// Packs register at boot. $fn receives a context array and returns either
// nothing, or ['block' => 'why'] / ['warn' => 'why'].
function pack_register($hook, $packKey, callable $fn) {
    if (!isset(PACK_HOOKS[$hook])) return;
    $GLOBALS['__packs'][$hook][] = ['pack' => $packKey, 'fn' => $fn];
}

// Run every handler for a hook whose pack is switched on, and collect what
// they say. A handler that throws is skipped and does NOT take the request
// with it — a specialist rule failing must not stop general work.
function pack_fire($hook, array $ctx = []) {
    $out = ['block' => [], 'warn' => []];
    foreach ($GLOBALS['__packs'][$hook] ?? [] as $h) {
        if (!pack_on($h['pack'])) continue;
        try { $r = ($h['fn'])($ctx); } catch (Throwable $e) { continue; }
        if (!is_array($r)) continue;
        // A message is either a plain sentence, or ['why' => ..., 'override' =>
        // ...] when the caller can offer a way past it. Both shapes are kept as
        // they are; only the emptiness test has to understand the difference.
        foreach (['block', 'warn'] as $k) {
            if (empty($r[$k])) continue;
            foreach ((array)$r[$k] as $m) {
                $text = is_array($m) ? (string)($m['why'] ?? '') : (string)$m;
                if (trim($text) !== '') $out[$k][] = $m;
            }
        }
    }
    return $out;
}

// The common case: one sentence, or '' when nothing objects.
function pack_block($hook, array $ctx = []) {
    $r = pack_fire($hook, $ctx);
    if (!$r['block']) return '';
    return implode(' ', array_map(fn($m) => is_array($m) ? (string)($m['why'] ?? '') : (string)$m, $r['block']));
}

// What an administrator sees on the settings screen.
function packs_available() {
    return [
        'inspection' => [
            'label' => 'Inspection &amp; certification (ISO/IEC 17020)',
            'desc'  => 'Competence and authorisation gates on allocation, impartiality, '
                     . 'site-entry documents, equipment calibration on report issue, and the '
                     . 'signatory check. Switch this OFF for a business that is not an '
                     . 'accredited inspection body — the registers stay, the gates stop firing.',
        ],
        'labs' => [
            'label' => 'Testing &amp; calibration laboratory (ISO/IEC 17025)',
            'desc'  => 'The same accreditation registers, tuned for a laboratory: competence and '
                     . 'authorisation gates on who may run a test, an impartiality check, and — on '
                     . 'issuing a test certificate — that every measuring instrument was in '
                     . 'calibration and the signatory was authorised. No site-entry gate, because '
                     . 'the sample comes to the laboratory. Switch OFF for a business that is not an '
                     . 'accredited laboratory.',
        ],
    ];
}

// The packs that carry an ISO conformity-assessment standard and therefore
// share the same registers — equipment, competence, impartiality, nonconformity,
// corrective action, internal audit, management review, data control. The menu,
// the routes and the on-screen wording key off "any of these is on", not off
// one named pack, so a laboratory sees the same apparatus as an inspection body.
const ACCREDITATION_PACKS = ['inspection', 'labs'];

function accredited_pack_on() {
    foreach (ACCREDITATION_PACKS as $p) if (pack_on($p)) return true;
    return false;
}

// The standard whose name and clause numbers the shared screens should show.
// If a body somehow runs both, inspection is named — the screens can only carry
// one, and 17020 is the one with the site work these screens grew up around.
function accreditation_standard() {
    if (pack_on('inspection')) return 'ISO/IEC 17020';
    if (pack_on('labs'))       return 'ISO/IEC 17025';
    return '';
}

// The clause a shared obligation carries under the active standard. The two
// standards number the same duties differently, so a laboratory must not be
// shown a 17020 clause. Returns '' when no accreditation pack is on.
function accreditation_clause($topic) {
    if (!accredited_pack_on()) return '';
    $labs = pack_on('labs') && !pack_on('inspection');
    $map = [
        'competence'    => ['6.1',  '6.2'],
        'impartiality'  => ['4.1',  '4.1'],
        'equipment'     => ['6.2',  '6.4'],
        'signatory'     => ['6.1',  '6.2'],
        'complaints'    => ['7.5',  '7.9'],
        'appeals'       => ['7.6',  '7.9'],
        'nonconformity' => ['8.7',  '7.10'],
        'audit'         => ['8.8',  '8.8'],
        'review'        => ['8.9',  '8.9'],
        'datacontrol'   => ['7.11', '7.11'],
    ];
    return isset($map[$topic]) ? $map[$topic][$labs ? 1 : 0] : '';
}

// Convenience: "ISO/IEC 17025 §6.2" for a topic, or '' when no pack is on.
function accreditation_ref($topic) {
    $std = accreditation_standard();
    $cl  = accreditation_clause($topic);
    return $std === '' ? '' : ($cl === '' ? $std : $std . ' §' . $cl);
}

function packs_save($csv) {
    $keys = array_keys(packs_available());
    $want = array_values(array_intersect(array_map('trim', explode(',', (string)$csv)), $keys));
    setting_set('packs_enabled', implode(',', $want));
    packs_enabled(true);
}

// ---- The inspection pack ---------------------------------------------------
// Everything below is the ONLY place the ISO rules reach into shared paths.
// The functions themselves stay where they are — this just wires them.
function packs_boot() {
    static $done = false; if ($done) return; $done = true;

    pack_register('work.assign', 'inspection', function (array $c) {
        $out = ['block' => [], 'warn' => []];
        $insp = (int)($c['person_id'] ?? 0);
        if (!$insp) return $out;
        $on = (string)($c['on_date'] ?? date('Y-m-d'));

        // §6.1 — a required certificate that had lapsed on the day of the work.
        if (function_exists('competence_block') && ($w = competence_block($insp, $on)) !== '')
            $out['block'][] = ['why' => $w, 'override' => 'cert_override_note'];

        // §6.1 again — authorised for THIS work, not merely certificated.
        if (function_exists('auth_block')) {
            $w = auth_block($insp, (string)($c['work_type'] ?? ''), (int)($c['activity_id'] ?? 0),
                            (int)($c['partner_id'] ?? 0), $on);
            if ($w !== '') $out['block'][] = ['why' => $w, 'override' => ''];
        }

        // §4.1 — a declared threat that has not been decided.
        if (function_exists('imp_block')) {
            $w = imp_block($insp, [(int)($c['partner_id'] ?? 0), (int)($c['vendor_id'] ?? 0)], $on);
            if ($w !== '') $out['block'][] = ['why' => $w, 'override' => ''];
        }

        // Whether the site will admit them at all.
        if (function_exists('sitedoc_check')) {
            $sd = sitedoc_check($insp, (int)($c['partner_id'] ?? 0), (int)($c['site_id'] ?? 0), $on);
            if (function_exists('sitedoc_block_message') && ($w = sitedoc_block_message($sd)) !== '')
                $out['block'][] = ['why' => $w, 'override' => 'sitedoc_override_note'];
            foreach ($sd['warn'] ?? [] as $x) $out['warn'][] = $x;
        }
        return $out;
    });

    pack_register('document.issue', 'inspection', function (array $c) {
        $out = ['block' => [], 'warn' => []];
        $doc = $c['doc'] ?? null;
        if (!$doc) return $out;
        // §6.2 — never overridable: a measurement from an uncalibrated
        // instrument is void, not merely irregular.
        if (function_exists('report_equipment_block') && ($w = report_equipment_block($doc)) !== '')
            $out['block'][] = $w;
        // §6.1 — warns and records, because the report is still valid work.
        if (function_exists('report_signatory_warning') && ($w = report_signatory_warning($doc)) !== '')
            $out['warn'][] = $w;
        return $out;
    });

    // ---- The testing & calibration laboratory pack (ISO/IEC 17025) ----------
    // The same real obligations as inspection — competent people, calibrated
    // equipment, declared impartiality — because the shared functions enforce
    // them the same way whichever standard is in force. What differs: no
    // site-entry gate (the sample comes to the laboratory), and the clause
    // numbers on the messages, which come from the active standard.
    pack_register('work.assign', 'labs', function (array $c) {
        $out = ['block' => [], 'warn' => []];
        $who = (int)($c['person_id'] ?? 0);
        if (!$who) return $out;
        $on = (string)($c['on_date'] ?? date('Y-m-d'));
        // §6.2 — a required qualification that had lapsed on the day of the test.
        if (function_exists('competence_block') && ($w = competence_block($who, $on)) !== '')
            $out['block'][] = ['why' => $w, 'override' => 'cert_override_note'];
        // §6.2 — authorised for THIS test method, not merely qualified.
        if (function_exists('auth_block')) {
            $w = auth_block($who, (string)($c['work_type'] ?? ''), (int)($c['activity_id'] ?? 0),
                            (int)($c['partner_id'] ?? 0), $on);
            if ($w !== '') $out['block'][] = ['why' => $w, 'override' => ''];
        }
        // §4.1 — a declared threat to impartiality that has not been decided.
        if (function_exists('imp_block')) {
            $w = imp_block($who, [(int)($c['partner_id'] ?? 0), (int)($c['vendor_id'] ?? 0)], $on);
            if ($w !== '') $out['block'][] = ['why' => $w, 'override' => ''];
        }
        return $out;
    });

    pack_register('document.issue', 'labs', function (array $c) {
        $out = ['block' => [], 'warn' => []];
        $doc = $c['doc'] ?? null;
        if (!$doc) return $out;
        // §6.4 / §6.5 — metrological traceability: a result from an instrument
        // out of calibration is void, and cannot be overridden.
        if (function_exists('report_equipment_block') && ($w = report_equipment_block($doc)) !== '')
            $out['block'][] = $w;
        // §6.2 / §7.8 — the person who authorised the result must be competent
        // to. A warning plus a nonconformity, not a block on a client's result.
        if (function_exists('report_signatory_warning') && ($w = report_signatory_warning($doc)) !== '')
            $out['warn'][] = $w;
        return $out;
    });
}
