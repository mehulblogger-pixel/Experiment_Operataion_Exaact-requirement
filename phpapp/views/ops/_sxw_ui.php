<?php
// ============================================================================
//  SALES WORKSPACE — shared pieces of Sales home, Today, Pipeline board and
//  Sales dashboard (owner, 21 Nov 2026: Stage 1 of the Sales redesign).
//  Built on the RECORD PAGE KIT (views/ops/_rec_ui.php). Styles: app.css,
//  "SALES WORKSPACE". Look and feel only — every figure, list and permission
//  still comes from the handlers in lib/sales_simple.php.
// ============================================================================
require_once __DIR__ . '/_rec_ui.php';
require_once __DIR__ . '/_sx_ui.php';   // sx_kpi / sx_person_chips / the older Sales look, still used by other Sales screens

if (!function_exists('sxw_head')) {

//  The page title with its icon tile; $rightHtml (filters) on the right.
function sxw_head($title, $sub, $icon, $rightHtml = '') {
    echo '<div class="sxw-hd"><div class="sxw-ttl"><span class="tile">' . rec_icon($icon) . '</span><div><h1>' . e($title) . '</h1>'
       . ($sub !== '' ? '<p>' . e($sub) . '</p>' : '') . '</div></div>' . $rightHtml . '</div>';
}

//  The screens of an area this person may open — lib/areas.php, area_screen_tiles():
//  read from the area itself (ops_area_def / the Operations nav index), the same
//  permission, feature-switch and module-gate rules as its menu page, never a second list.
//  Generalised 22 Nov 2026 (Operations Batch 1): $area defaults to Sales, as before.
function sxw_area_tiles($area = 'sales') {
    return function_exists('area_screen_tiles') ? area_screen_tiles($area) : [];
}

//  The area's section navigation (laptop and tablet): the one dark bar, in the theme's
//  colour (app.css --area-bar). Owner, 22 Nov 2026: "use the same, make it universal".
function sxw_nav($current, $area = 'sales') {
    $tiles = sxw_area_tiles($area);
    $label = $area === 'operations' ? 'Operations screens' : 'Sales screens';
    echo '<nav class="sxw-nav" aria-label="' . e($label) . '">';
    foreach (area_bar_order($area) as [$r, $lab]) {
        if (!isset($tiles[$r])) continue;
        $c = $tiles[$r]['count'] ?? null;
        echo '<a href="' . e($r) . '"' . ($r === $current ? ' class="on" aria-current="page"' : '') . '>' . e($lab)
           . ($c ? '<em>' . (int) $c . '</em>' : '') . '</a>';
    }
    $all = area_bar_all($area, $current);
    echo '<a class="all" href="' . e($all['href']) . '">' . e($all['label']) . '</a></nav>';
}

//  Phone bottom bar. Its items are a company setting (Settings → Display & terms →
//  "Phone bottom bar"; owner, 22 Nov 2026: "make it configurable, admin can set that"),
//  starting from today's set. An item shows only when the person may open it.
//  Punch in / out is added for anyone who may punch today (existing rules, lib/trust.php).
//  Drawn OUTSIDE the page's container so position:fixed is the screen's, not the container's.
function sxw_bottom($current, $due = null, $area = 'sales') {
    $items = function_exists('area_bn_items') ? area_bn_items($area, 'office') : [];
    $punch = function_exists('area_bn_punch') ? area_bn_punch() : null;
    if (!$items && !$punch) return;
    $n = count($items) + ($punch ? 1 : 0);
    echo '<nav class="sxw-bn" aria-label="' . e($area === 'operations' ? 'Operations' : 'Sales') . ' — quick switch" style="--n:' . $n . '">';
    foreach ($items as [$r, $ic, $lab]) {
        $b = ($r === '/sales-today' && $due) ? '<b>' . (int) $due . '</b>' : '';
        echo '<a href="' . e($r) . '"' . ($r === $current ? ' class="on" aria-current="page"' : '') . '>' . rec_icon($ic) . e($lab) . $b . '</a>';
    }
    if ($punch) echo '<a href="' . e($punch['href']) . '" data-punch="' . e($punch['next']) . '">' . rec_icon('pin') . e($punch['label']) . '</a>';
    echo '</nav>';
}

//  Whose figures: Me · Everyone, and every salesperson — the same addresses as
//  the old chips (/route?who=me|all|id + $extra); managers only, as before.
function sxw_person($route, $whoKey, $people, $seeAll, $extra = '') {
    if (!$seeAll) return '';
    $u = fn($k) => '/' . $route . '?who=' . rawurlencode((string) $k) . $extra;
    $cur = (string) $whoKey;
    //  'team' (owner, 21 Nov 2026): a manager without "See other salespeople's
    //  lists" — Me, or one of the people who report to them. No "Everyone".
    $team = $seeAll === 'team';
    $h = '<div class="sxw-ctl"><span class="lab">View:</span><nav class="sxw-seg" aria-label="Whose list">'
       . '<a href="' . e($u('me')) . '"' . (($cur === 'me' || ($team && !isset($people[(int) $cur]))) ? ' class="on" aria-current="true"' : '') . '>Me</a>'
       . ($team ? '' : '<a href="' . e($u('all')) . '"' . ($cur === 'all' ? ' class="on" aria-current="true"' : '') . '>Everyone</a>') . '</nav>';
    if ($people) {
        $name = isset($people[$cur]) ? $people[$cur] : ($people[(int) $cur] ?? ($team ? 'My team' : 'Choose a person'));
        $h .= '<details class="sxw-pp"><summary>' . rec_icon('user', 's') . '<span>' . e($name) . '</span>' . rec_icon('down', 's') . '</summary><div>';
        foreach ($people as $id => $n) $h .= '<a href="' . e($u($id)) . '"' . ((string) $id === $cur ? ' class="on" aria-current="true"' : '') . '>' . e($n) . '</a>';
        $h .= '</div></details>';
    }
    return $h . '</div>';
}

//  A figure tile. $key is the old card's own title (without its emoji), kept as
//  data-k so the figure on this page can be checked against the one before.
//  D8 (owner, 21 Nov 2026 — findings SH-03, "as recommended"): every Sales figure card
//  is the product's ONE KPI card (.kpi — white card, coloured header band with the label
//  and icon, the big number, the note), the card the Dashboard and Money use. The tone
//  colours the band (and the number) exactly as .kpi.tone-* does elsewhere; "teal" and
//  "grey" are the plain brand band. Same arguments, same data-k / data-v hooks.
function sxw_kpi($tone, $icon, $key, $label, $value, $hint = '', $href = '') {
    $tag = $href !== '' ? 'a' : 'div';
    $cls = ['bad' => 'tone-bad', 'warn' => 'tone-warn', 'info' => 'tone-info', 'ok' => 'tone-ok'][$tone] ?? '';
    echo '<' . $tag . ' class="kpi sxw-kpi' . ($cls !== '' ? ' ' . $cls : '') . '" data-k="' . e($key) . '"' . ($href !== '' ? ' href="' . e($href) . '"' : '') . '>'
       . '<span class="k">' . e($label) . '</span><span class="kic" aria-hidden="true">' . rec_icon($icon, 's') . '</span><span class="v" data-v>' . e($value) . '</span>'
       . ($hint !== '' ? '<span class="d">' . e($hint) . '</span>' : '') . '</' . $tag . '>';
}

//  Call / WhatsApp for a number — the same links sx_contact_btns() gives, in the new look.
function sxw_contact($phone, $label) {
    $wa = sx_wa($phone); $tel = sx_tel($phone); $o = '';
    if ($tel !== '') $o .= '<a class="sxw-ib" href="' . e($tel) . '" aria-label="Call ' . e($label) . '">' . rec_icon('phone') . '<span>Call</span></a>';
    if ($wa !== '') $o .= '<a class="sxw-ib" href="' . e($wa) . '" target="_blank" rel="noopener" aria-label="WhatsApp ' . e($label) . '">' . rec_icon('wa') . '<span>WhatsApp</span></a>';
    return $o !== '' ? $o : '<span class="muted" style="font-size:12px">no number</span>';
}

//  The due label without its emoji (the words and the colour class are sx_due_label()'s).
function sxw_due($date) {
    [$lab, $cls] = sx_due_label($date);
    $lab = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', (string) $lab));
    return '<span class="sxw-due ' . e($cls) . '">' . e($lab) . '</span>';
}

//  Tabs, search and sort — a few lines of page script, shared. Without script every
//  section simply shows, one below the other.
function sxw_script() {
    static $done = false; if ($done) return; $done = true; ?>
<script>
(function () {
  // Tabs: [data-sxw-tabs] holds buttons with data-for="panelId"; panels carry data-sxw-panel.
  document.querySelectorAll('[data-sxw-tabs]').forEach(function (bar) {
    var btns = bar.querySelectorAll('[data-for]'), panels = [];
    btns.forEach(function (b) { var p = document.getElementById(b.getAttribute('data-for')); if (p) panels.push(p); });
    function show(id, focus) {
      btns.forEach(function (b) { var on = b.getAttribute('data-for') === id; b.classList.toggle('on', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
      panels.forEach(function (p) { p.hidden = p.id !== id; });
    }
    btns.forEach(function (b) { b.setAttribute('role', 'tab'); b.addEventListener('click', function () { show(b.getAttribute('data-for')); }); });
    bar.setAttribute('role', 'tablist');
    // A link to an anchor inside a panel (#pay, #stale …) opens that panel first.
    function fromHash() {
      var h = location.hash.slice(1); if (!h) return false;
      var t = document.getElementById(h); if (!t) return false;
      for (var i = 0; i < panels.length; i++) if (panels[i] === t || panels[i].contains(t)) { show(panels[i].id); setTimeout(function () { t.scrollIntoView({block: 'start'}); }, 20); return true; }
      return false;
    }
    var first = bar.querySelector('[data-for].on') || btns[0];
    if (!fromHash() && first) show(first.getAttribute('data-for'));
    window.addEventListener('hashchange', fromHash);
  });
  // In-page search: <input data-sxw-search="tableId">.
  document.querySelectorAll('[data-sxw-search]').forEach(function (inp) {
    var tb = document.getElementById(inp.getAttribute('data-sxw-search')); if (!tb) return;
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase();
      tb.querySelectorAll('tbody tr').forEach(function (tr) { tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) < 0; });
    });
  });
  // Column sort: <th data-sort="text|num">; a cell may carry data-s (its sort value).
  document.querySelectorAll('table[data-sxw-sort]').forEach(function (tb) {
    tb.querySelectorAll('th[data-sort]').forEach(function (th, idx) {
      var col = Array.prototype.indexOf.call(th.parentNode.children, th), dir = 1;
      th.setAttribute('tabindex', '0'); th.setAttribute('role', 'button');
      function go() {
        var body = tb.tBodies[0], rows = Array.prototype.slice.call(body.rows), num = th.getAttribute('data-sort') === 'num';
        rows.sort(function (a, b) {
          var x = a.cells[col], y = b.cells[col];
          var vx = x ? (x.getAttribute('data-s') !== null ? x.getAttribute('data-s') : x.textContent.trim()) : '';
          var vy = y ? (y.getAttribute('data-s') !== null ? y.getAttribute('data-s') : y.textContent.trim()) : '';
          if (num) { vx = parseFloat(vx) || 0; vy = parseFloat(vy) || 0; return (vx - vy) * dir; }
          return vx.localeCompare(vy) * dir;
        });
        rows.forEach(function (r) { body.appendChild(r); }); dir = -dir;
      }
      th.addEventListener('click', go); th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
  });
})();
</script>
<?php }

}
