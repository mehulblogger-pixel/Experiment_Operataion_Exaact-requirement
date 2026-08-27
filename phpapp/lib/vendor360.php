<?php
// Phase 3 §16 — Vendor-360 depth: bring the vendor screen to parity with the client-360.
//
// The vendor detail is already rich on the QUALITY side (assessments, audits, scorecard, CAPAs,
// expediting, delivery risk). What it lacked, and the client-360 has, are two things: the vendor's
// people (contacts, each recognised as one person across the system, §23/24) and the vendor's full
// activity history (the one activity spine, §17). This adds exactly those two, reusing the engines that
// already exist. Read-only; nothing about the vendor's data model changes.

// A vendor's contacts (from the shared partner_contacts table).
function vendor360_contacts($pid) {
    try { return ops_all("SELECT * FROM partner_contacts WHERE partner_id=? ORDER BY is_primary DESC, name", [(int)$pid]) ?: []; }
    catch (Throwable $e) { return []; }
}

// The other places one contact appears (candidate, user, client contact, …) via the §23/24 matcher —
// excluding the contact record itself. Returns [['kind','label','name','url'], …].
function vendor360_contact_also($contact) {
    if (!function_exists('party_records_for')) return [];
    $mobile = trim((string)($contact['mobile'] ?? $contact['phone'] ?? ''));
    $email  = trim((string)($contact['email'] ?? ''));
    if ($mobile === '' && $email === '') return [];
    $out = [];
    foreach (party_records_for($mobile, $email) as $r) {
        if (($r['kind'] ?? '') === 'CONTACT' && (int)($r['id'] ?? 0) === (int)($contact['id'] ?? 0)) continue; // itself
        if (($r['kind'] ?? '') === 'CONTACT') continue;   // other contact rows add little here
        $out[] = $r;
    }
    return $out;
}

// The two parity panels for the vendor detail: contacts (with cross-system links) + the activity history.
function vendor360_render($pid) {
    $pid = (int)$pid;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);

    // ---- Contacts, each recognised as one person across the system (§23/24) ----
    $contacts = vendor360_contacts($pid);
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">Contacts '
       . '<span class="muted" style="font-weight:400;font-size:12px">— the people at this vendor</span></h3>';
    if (!$contacts) {
        echo '<p class="muted" style="margin:6px 0 0">No contacts recorded for this vendor yet.</p>';
    } else {
        echo '<div style="display:flex;flex-direction:column;gap:2px">';
        foreach ($contacts as $c) {
            $also = vendor360_contact_also($c);
            echo '<div style="padding:8px 0;border-top:1px solid var(--line,#e5e7eb)">'
               . '<div style="font-size:14px;font-weight:600">' . $e($c['name'] ?: '—')
               . (!empty($c['is_primary']) ? ' <span class="pill p-ok" style="font-size:10px">primary</span>' : '')
               . '</div>'
               . '<div class="muted" style="font-size:12px">'
               . $e(trim(($c['designation'] ?? $c['role'] ?? '') . ''))
               . (($c['mobile'] ?? $c['phone'] ?? '') ? ' · ' . $e($c['mobile'] ?? $c['phone']) : '')
               . (($c['email'] ?? '') ? ' · ' . $e($c['email']) : '') . '</div>';
            if ($also) {
                echo '<div style="margin-top:3px;font-size:12px">'
                   . '<span class="muted">Also appears as:</span> ';
                foreach ($also as $r)
                    echo '<a class="pill p-mut" style="font-size:11px;text-decoration:none;margin-right:4px" href="' . $e($r['url']) . '">'
                       . $e($r['label']) . ($r['name'] !== '—' ? ' · ' . $e($r['name']) : '') . '</a>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';

    // ---- The full activity history (the one spine, same as client-360) ----
    if (function_exists('act_render_timeline'))
        act_render_timeline('PARTNER', $pid, 'Vendor history');
}
