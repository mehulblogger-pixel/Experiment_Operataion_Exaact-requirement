<?php
// ============================================================================
//  B1 — ACCESSIBILITY AND GLOBAL VISUAL TOKENS
//
//  F-A2-2. app.css declares --muted:#656e7a, which is 5.17:1 on white and
//  passes WCAG AA. theme_style_tag() then re-declares :root on EVERY page --
//  it is emitted from layout_top.php, so this is not a branded-workspace
//  problem, it is every workspace -- and derived the same token by mixing the
//  surface toward the ink by a fixed 45%.
//
//  A fixed fraction cannot promise a ratio. Measured before the fix:
//
//      secondary text   2.67:1 on the card, 2.43:1 on a panel, 2.46:1 on page
//      placeholder      1.91:1   (muted, then multiplied by opacity:.7)
//      field border     1.31:1   (WCAG 1.4.11 asks 3:1 for a control boundary)
//      focus ring       2.10:1   on a pale-gold brand -- a keyboard user
//                                literally cannot see where they are
//
//  The correction derives the same tokens and then moves them the MINIMUM
//  distance needed to reach the ratio. A workspace whose colours already pass
//  is left untouched, so branding survives.
//
//  The contrast maths below is written out again rather than calling
//  theme_contrast(), so that the code under test cannot flatter itself.
// ============================================================================

t_section('B1 — accessibility tokens');

$b1lum = function ($hex) {
    $c = [];
    foreach ([1, 3, 5] as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $c[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};
$b1r = function ($a, $b) use ($b1lum) {
    $la = $b1lum($a); $lb = $b1lum($b);
    return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
};

// ---- A · arming: the ruler is correct before anything is measured with it --
t_eq($b1r('#000000', '#ffffff'), 21.0, 'A1 ARMING · black on white measures 21:1');
t_eq($b1r('#ffffff', '#ffffff'), 1.0,  'A2 ARMING · white on white measures 1:1');
t_eq($b1r('#656e7a', '#ffffff'), 5.17, 'A3 ARMING · the value app.css declares measures 5.17:1, as the audit said');
t_ok(function_exists('theme_contrast') && function_exists('theme_readable'),
     'A4 ARMING · the engine exposes a contrast function and a readable-derivation function');
// The app's own function must agree with this independent one.
t_eq(round(theme_contrast('#656e7a', '#ffffff'), 2), 5.17,
     'A5 ARMING · the app\'s theme_contrast() agrees with the independent calculation');

// ---- B · the old derivation really was the defect ------------------------
//  If this ever stops failing, the test below has stopped proving anything.
$b1old = theme_mix('#ffffff', '#1f2937', 0.45);
t_ok($b1r($b1old, '#ffffff') < 3.0,
     'B1 ARMING · the pre-B1 derivation (fixed 45% mix) measures ' . $b1r($b1old, '#ffffff')
     . ':1 — below 3, let alone 4.5');

// ---- C · every theme, every surface the token actually lands on ----------
$b1keys = ['c_primary', 'c_accent', 'c_bg', 'c_surface', 'c_text'];
$b1orig = [];
foreach ($b1keys as $k) $b1orig[$k] = setting_get($k, '');

$b1themes = [
    'default (nothing chosen)' => [],
    'deep teal brand'          => ['c_primary' => '#0f5f5c'],
    'pale gold brand'          => ['c_primary' => '#d4af37'],
    'near-white brand'         => ['c_primary' => '#f2f2f2'],
    'dark surface'             => ['c_primary' => '#0f5f5c', 'c_surface' => '#111827',
                                   'c_text' => '#e5e7eb', 'c_bg' => '#0b1220'],
    'cream surface'            => ['c_surface' => '#fdfaf3', 'c_bg' => '#f7f2e7'],
];
$b1tok = function ($css, $name) {
    return preg_match('/--' . preg_quote($name, '/') . ':(#[0-9a-f]{6})/', $css, $m) ? $m[1] : '';
};
$b1worst = ['muted' => 99.0, 'field-line' => 99.0, 'focus' => 99.0];
foreach ($b1themes as $b1name => $b1cfg) {
    foreach ($b1keys as $k) setting_set($k, $b1cfg[$k] ?? '');
    $css   = theme_style_tag();
    $muted = $b1tok($css, 'muted');   $card = $b1tok($css, 'card');
    $soft  = $b1tok($css, 'soft');    $bg   = $b1tok($css, 'bg');
    $fline = $b1tok($css, 'field-line'); $field = $b1tok($css, 'field');
    $focus = $b1tok($css, 'focus');
    $bink  = $b1tok($css, 'brand-ink'); $btnbg = $b1tok($css, 'btn-bg');
    $onbr  = $b1tok($css, 'on-brand');

    t_ok($muted && $card && $soft && $bg && $fline && $field && $focus && $bink && $btnbg && $onbr,
         "C ARMING [$b1name] · the style tag emits every token this checks");

    foreach (['card' => $card, 'panel' => $soft, 'page' => $bg] as $where => $onto) {
        $v = $b1r($muted, $onto);
        $b1worst['muted'] = min($b1worst['muted'], $v);
        t_ok($v >= 4.5, "C [$b1name] · secondary text on the $where = {$v}:1 (AA needs 4.5)");
    }
    foreach (['card' => $card, 'field' => $field, 'page' => $bg] as $where => $onto) {
        $v = $b1r($fline, $onto);
        $b1worst['field-line'] = min($b1worst['field-line'], $v);
        t_ok($v >= 3.0, "C [$b1name] · a form control's border on the $where = {$v}:1 (1.4.11 needs 3)");
    }
    foreach (['card' => $card, 'panel' => $soft, 'page' => $bg] as $where => $onto) {
        $v = $b1r($focus, $onto);
        $b1worst['focus'] = min($b1worst['focus'], $v);
        t_ok($v >= 3.0, "C [$b1name] · the focus ring on the $where = {$v}:1 (needs 3)");
    }
    //  Brand used as TEXT — every link in the product is painted with this.
    //  a{color:var(--brand)} meant a pale-brand workspace had 2.1:1 links, which
    //  the first browser run missed because it never sampled a link.
    foreach (['card' => $card, 'panel' => $soft, 'page' => $bg] as $where => $onto) {
        $v = $b1r($bink, $onto);
        $b1worst['brand-ink'] = min($b1worst['brand-ink'] ?? 99.0, $v);
        t_ok($v >= 4.5, "C [$b1name] · a link / brand-coloured label on the $where = {$v}:1 (AA needs 4.5)");
    }
    //  A solid brand button: the label against the button's own background.
    $v = $b1r($onbr, $btnbg);
    $b1worst['button'] = min($b1worst['button'] ?? 99.0, $v);
    t_ok($v >= 4.5, "C [$b1name] · a primary button's label on the button = {$v}:1 (AA needs 4.5)");
}
t_ok(true, 'C SUMMARY · worst measured across all themes — secondary text '
     . $b1worst['muted'] . ':1, control border ' . $b1worst['field-line']
     . ':1, focus ring ' . $b1worst['focus'] . ':1, link ' . $b1worst['brand-ink']
     . ':1, button label ' . $b1worst['button'] . ':1');

// ---- D · branding is preserved, not overridden ---------------------------
//  A colour that already passes must come back byte-identical, or this fix
//  would be quietly repainting every customer's workspace.
t_eq(theme_readable('#0f5f5c', ['#ffffff'], 4.5), '#0f5f5c',
     'D1 · a brand colour that already passes is returned untouched');
t_eq(theme_readable('#656e7a', ['#ffffff'], 4.5), '#656e7a',
     'D2 · and so is the compliant value the stylesheet already declared');
//  A colour that fails is moved, but stays recognisably itself. Gold stays gold.
$b1gold = theme_readable('#d4af37', ['#ffffff'], 3.0);
t_ok($b1gold !== '#d4af37', 'D3 ARMING · a pale gold brand IS adjusted (it measured 2.1:1)');
$g = [hexdec(substr($b1gold,1,2)), hexdec(substr($b1gold,3,2)), hexdec(substr($b1gold,5,2))];
t_ok($g[0] > $g[1] && $g[1] > $g[2],
     'D4 · and it is still gold — red > green > blue, as the original was (' . $b1gold . ')');
t_ok($b1r($b1gold, '#ffffff') >= 3.0,
     'D5 · while now reaching ' . $b1r($b1gold, '#ffffff') . ':1');
//  A background that cannot carry the ratio must terminate, not spin.
$b1mid = theme_readable('#808080', ['#767676'], 7.0);
t_ok(in_array($b1mid, ['#000000', '#ffffff'], true),
     'D6 · an impossible target gives up at black or white rather than looping (' . $b1mid . ')');

// ---- E · the stylesheet does not undo the engine -------------------------
$b1css = file_get_contents(__DIR__ . '/../assets/css/app.css');
t_ok(preg_match('/::placeholder\{color:var\(--muted\);opacity:\.(7|85)\}/', $b1css) !== 1,
     'E1 · no placeholder rule multiplies the muted token back down with opacity');
t_ok(substr_count($b1css, 'outline:2px solid var(--brand)') === 0,
     'E2 · no focus outline is painted in the raw brand colour — all use --focus');
//  E2b — the browser caught what E2 alone could not. A focus indicator does not
//  have to be an outline: .form-control:focus replaces it with a border and a
//  ring, and BOTH were painted in the raw brand, so a pale-gold workspace
//  showed a 2.1:1 focus indicator. Any rule that mentions :focus may not reach
//  for --brand at all.
preg_match_all('/[^{}]*:focus[^{}]*\{[^}]*\}/', $b1css, $mFocus);
$b1focusRules = $mFocus[0] ?? [];
t_ok(count($b1focusRules) >= 8,
     'E2b ARMING · ' . count($b1focusRules) . ' focus rules found to inspect');
$b1focusBrand = array_values(array_filter($b1focusRules, fn($r) => strpos($r, 'var(--brand)') !== false));
t_eq($b1focusBrand, [], 'E2b · no :focus rule paints its indicator in the raw brand colour');
//  E2c — brand as TEXT anywhere. `a{color:var(--brand)}` is every link there is.
preg_match_all('/(?<!-)color:var\(--brand\)(?!-)/', $b1css, $mInk);
t_eq($mInk[0] ?? [], [], 'E2c · no rule uses the raw brand as a text colour — all use --brand-ink');
t_ok(strpos($b1css, 'color:var(--on-brand)') !== false,
     'E2d · and a solid button takes its label colour from --on-brand, not a hard-coded #fff');
t_ok(strpos($b1css, '--focus-ring:0 0 0 3px color-mix(in srgb, var(--focus)') !== false,
     'E3 · and the focus ring is built from --focus too');
t_ok(strpos($b1css, 'rgba(30,64,175') === false,
     'E4 · the hard-coded blue brand fallback is gone (F-A2-1)');
//  ARMING — prove E1/E2 would catch a regression.
t_ok(preg_match('/::placeholder\{color:var\(--muted\);opacity:\.(7|85)\}/',
     '.x::placeholder{color:var(--muted);opacity:.7}') === 1,
     'E ARMING · the placeholder scan does detect the shape it forbids');

// ---- F · public pages carry their own :root, and they already pass -------
//  These five are self-contained marketing/portal pages: they never receive
//  theme_style_tag(). They were hand-authored with accessible values, which is
//  why the defect was confined to the DERIVED tokens. Pinned so they stay that way.
foreach ([
    'views/ops/connect_join.php', 'views/ops/connect_passport_public.php',
    'views/ops/connect_front.php', 'views/public/get_started.php', 'views/pro/top.php',
] as $b1f) {
    $src = file_get_contents(__DIR__ . '/../' . $b1f);
    if (!preg_match_all('/--muted:(#[0-9a-f]{6})/', $src, $mm)) { t_ok(false, "F · $b1f declares a --muted"); continue; }
    $lightOk = $b1r($mm[1][0], '#ffffff') >= 4.5;
    t_ok($lightOk, 'F · ' . basename($b1f) . ' secondary text = ' . $b1r($mm[1][0], '#ffffff') . ':1 on white');
}

// ---- restore -------------------------------------------------------------
foreach ($b1keys as $k) setting_set($k, (string) $b1orig[$k]);
t_ok(true, 'B1 · theme settings restored');
