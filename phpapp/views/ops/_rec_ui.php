<?php
// ============================================================================
//  RECORD PAGE KIT — the shared building blocks of a record screen
//  (owner, 20 Nov 2026: the lead page design "shall be made universal ... for
//  all the screen"). The lead page is the first user; the deal, quotation,
//  enquiry, costing and order pages are to follow with the same pieces.
//  Styles: assets/css/app.css, "RECORD PAGE KIT". Look and feel only — what a
//  person may do is decided by the page, never here.
//
//  Every helper echoes markup. Text arguments are escaped here; arguments
//  named *Html are already-safe markup built by the caller.
// ============================================================================
if (!function_exists('rec_icon')) {

//  One line-icon set (24 × 24, stroke), so every screen speaks the same picture language.
function rec_icon_paths() {
    static $p = [
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14.5c2 .6 3.5 2.4 3.5 5.5"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'rupee' => '<path d="M6 4h12M6 9h12M6 4h4a5 5 0 0 1 0 10H6l8 7"/>',
        'home' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
        'list' => '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>', 'right' => '<path d="m9 6 6 6-6 6"/>', 'left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'more' => '<circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/>',
        'pen' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'checkc' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>', 'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'clip' => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8 21h8M9 17h6"/>',
        'msg' => '<path d="M21 12a8 8 0 0 1-12 7l-5 1 1-4.5A8 8 0 1 1 21 12z"/>',
        'note' => '<path d="M4 4h16v11l-5 5H4z"/><path d="M15 20v-5h5M8 9h8M8 13h4"/>',
        'task' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m8 12 3 3 5-6"/>',
        'chart' => '<path d="M5 20V12M12 20V5M19 20v-9"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17v.5"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'brief' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'pin' => '<path d="M12 21s-7-6-7-12a7 7 0 0 1 14 0c0 6-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'hist' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'flow' => '<circle cx="5" cy="12" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="19" cy="18" r="2"/><path d="M7 12h4M11 12c2 0 2-6 6-6M11 12c2 0 2 6 6 6"/>',
        'swap' => '<path d="M4 12a8 8 0 0 1 14-5l2 2M20 12a8 8 0 0 1-14 5l-2-2"/><path d="M20 4v5h-5M4 20v-5h5"/>',
        'up' => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'calc' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.5M12 12h.5M16 12h.5M8 16h.5M12 16h.5M16 16h.5"/>',
        'hat' => '<path d="M3 17h18M5 17v-3a7 7 0 0 1 14 0v3M10 7V5h4v2"/>',
        'wa' => '<path d="M21 12a8 8 0 0 1-12 7l-5 1 1-4.5A8 8 0 1 1 21 12z"/><path d="M9 9c0 3 3 6 6 6l1-1.5-2-1-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1-2z"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return $p;
}
function rec_icon_path($name) { return rec_icon_paths()[$name] ?? ''; }
function rec_icon($name, $cls = '') {
    return '<svg class="rec-i' . ($cls !== '' ? ' ' . e($cls) : '') . '" viewBox="0 0 24 24" aria-hidden="true">' . rec_icon_path($name) . '</svg>';
}

//  (Column counts travel as --n, never as an inline grid-template-columns: the phone
//  stylesheet collapses every inline "repeat(" grid to one column.)
//  Three (or more) equal fact boxes: [[icon, value, label], ...].
function rec_kpis(array $boxes, $attrs = '') {
    echo '<div class="rec-kpi"' . ($attrs !== '' ? ' ' . $attrs : '') . ' style="--n:' . max(1, count($boxes)) . '">';
    foreach ($boxes as [$ic, $v, $k]) echo '<div><span class="ico">' . rec_icon($ic) . '</span><span class="v" title="' . e($v) . '">' . e($v) . '</span><span class="k">' . e($k) . '</span></div>';
    echo '</div>';
}

//  The stage path: [['name' => …, 'state' => done|now|won|lost|'', 'date' => …], ...].
function rec_steps(array $steps) {
    $n = max(1, count($steps)); $w = 100 / $n;
    $green = 0;
    foreach ($steps as $i => $s) if (in_array($s['state'], ['done', 'won'], true)) $green = $i;
    foreach ($steps as $i => $s) if ($s['state'] === 'now') $green = $i;
    $hasGreen = false; foreach ($steps as $s) if (in_array($s['state'], ['done', 'won', 'now'], true)) $hasGreen = true;
    echo '<div class="rec-steps" style="--n:' . $n . '" role="list" aria-label="Where it is">';
    echo '<span class="line" style="left:' . round($w / 2, 3) . '%;right:' . round($w / 2, 3) . '%"></span>';
    if ($hasGreen) echo '<span class="line g" style="left:' . round($w / 2, 3) . '%;width:' . round($green * $w, 3) . '%"></span>';
    foreach ($steps as $s) {
        $st = (string) $s['state'];
        $in = in_array($st, ['done', 'won'], true) ? '<svg viewBox="0 0 24 24" class="rec-i">' . rec_icon_path('check') . '</svg>'
            : ($st === 'lost' ? '<svg viewBox="0 0 24 24" class="rec-i">' . rec_icon_path('x') . '</svg>' : '');
        $sr = ['done' => 'passed', 'won' => 'reached', 'now' => 'current', 'lost' => 'closed here', '' => 'not yet'][$st] ?? '';
        echo '<div class="rec-st ' . e($st) . '" role="listitem"><span class="rec-dot">' . $in . '</span><span class="n">' . e($s['name'])
           . '<span class="visually-hidden" style="position:absolute;left:-9999px"> — ' . e($sr) . '</span></span><span class="d">' . e((string) ($s['date'] ?? '')) . '</span></div>';
    }
    echo '</div>';
}

//  A card with a title row. $rightHtml is the action at the right of the title (Edit, View all …).
function rec_card_open($title, $icon = '', $rightHtml = '', $attrs = '', $cls = '') {
    echo '<section class="rec-card' . ($cls !== '' ? ' ' . e($cls) : '') . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '>';
    if ($title !== '') echo '<div class="rec-ch">' . ($icon !== '' ? '<span class="ico">' . rec_icon($icon) . '</span>' : '') . '<h3>' . e($title) . '</h3>' . $rightHtml . '</div>';
}
function rec_card_close() { echo '</section>'; }

//  Label / value rows: [[icon, label, valueHtml], ...]. A value starting "<span class=\"v" is used as is.
function rec_rows(array $rows) {
    echo '<div class="rec-rows">';
    foreach ($rows as [$ic, $k, $vHtml]) {
        echo '<div class="row"><span class="ico">' . rec_icon($ic, 's') . '</span><span class="k">' . e($k) . '</span>'
           . (strncmp((string) $vHtml, '<span class="v', 14) === 0 ? $vHtml : '<span class="v">' . $vHtml . '</span>') . '</div>';
    }
    echo '</div>';
}

//  Summary rows: [[label, valueHtml], ...].
function rec_summary(array $rows) {
    echo '<div class="rec-sum">';
    foreach ($rows as [$k, $vHtml]) echo '<div class="r"><span>' . e($k) . '</span>' . $vHtml . '</div>';
    echo '</div>';
}

//  History: [[tone, icon, title, whatHtml, date], ...]; tone ok|bad|info|gold|''.
function rec_timeline(array $items) {
    echo '<div class="rec-tl">';
    foreach ($items as [$tone, $ic, $t, $wHtml, $d])
        echo '<div class="it"><span class="rec-bub ' . e($tone) . '">' . rec_icon($ic) . '</span><div><div class="t">' . e($t) . '</div>'
           . ($wHtml !== '' ? '<div class="w">' . $wHtml . '</div>' : '') . '</div><span class="dt">' . e($d) . '</span></div>';
    echo '</div>';
}

//  Quick actions: [[icon, label, href, extraAttrs], ...] — four equal buttons.
function rec_quick(array $acts) {
    echo '<div class="rec-qa">';
    foreach ($acts as $a) { [$ic, $lab, $href] = $a; $x = $a[3] ?? '';
        echo '<a href="' . e($href) . '"' . ($x !== '' ? ' ' . $x : '') . '><span class="ico">' . rec_icon($ic) . '</span>' . e($lab) . '</a>'; }
    echo '</div>';
}

//  Plain words for a quotation's status (the lead and deal pages share them).
function rec_quote_status($st) {
    $m = ['DRAFT' => ['Being prepared', 'mut'], 'PENDING_APPROVAL' => ['Waiting for approval', 'warn'], 'APPROVED' => ['Approved — ready to send', 'info'],
          'SENT' => ['Sent — waiting for their answer', 'info'], 'REJECTED' => ['Sent back by the approver', 'bad'], 'ACCEPTED' => ['Accepted', 'ok'],
          'LOST' => ['They said no', 'bad'], 'EXPIRED' => ['Expired', 'mut'], 'REVISED' => ['Replaced by a revision', 'mut'], 'CANCELLED' => ['Cancelled', 'mut']];
    return $m[(string) $st] ?? [ucfirst(strtolower(str_replace('_', ' ', (string) $st))), 'mut'];
}

}
