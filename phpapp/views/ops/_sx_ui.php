<?php
// Simple Sales (plan 42 step C) — shared look: the owner's Excel colour meaning
// (red overdue · amber today · yellow this week · green on track · grey automatic)
// on EXAACT's tokens. Included once per screen.
if (!function_exists('sx_kpi')) {
    //  A KPI card. $tone: red orange yellow blue green brown pink purple slate teal.
    function sx_kpi($tone, $title, $value, $hint = '', $href = '') {
        $tag = $href !== '' ? 'a' : 'div';
        echo '<' . $tag . ' class="sxk sx-' . e($tone) . '"' . ($href !== '' ? ' href="' . e($href) . '"' : '') . '>'
           . '<span class="sxk-t">' . e($title) . '</span><span class="sxk-v">' . e($value) . '</span>'
           . ($hint !== '' ? '<span class="sxk-h">' . e($hint) . '</span>' : '') . '</' . $tag . '>';
    }
    function sx_contact_btns($phone, $label = '') {
        $wa = sx_wa($phone); $tel = sx_tel($phone); $out = '';
        if ($wa !== '') $out .= '<a class="sxc" href="' . e($wa) . '" target="_blank" rel="noopener" aria-label="WhatsApp ' . e($label) . '">💬 WhatsApp</a>';
        if ($tel !== '') $out .= '<a class="sxc" href="' . e($tel) . '" aria-label="Call ' . e($label) . '">📞 Call</a>';
        return $out !== '' ? '<span class="sxcs">' . $out . '</span>' : '<span class="muted" style="font-size:12px">no number</span>';
    }
    //  The person filter (managers) — Me / Everyone / each salesperson.
    function sx_person_chips($route, $whoKey, $people, $seeAll, $extra = '') {
        if (!$seeAll) return;
        echo '<nav class="sxchips" aria-label="Whose list">';
        $chip = function ($k, $lab) use ($route, $whoKey, $extra) {
            $on = (string) $whoKey === (string) $k;
            echo '<a class="sxchip' . ($on ? ' on' : '') . '" href="/' . e($route) . '?who=' . e($k) . e($extra) . '"' . ($on ? ' aria-current="true"' : '') . '>' . e($lab) . '</a>';
        };
        $chip('me', '👤 Me'); $chip('all', 'Everyone');
        foreach ($people as $id => $n) $chip($id, $n);
        echo '</nav>';
    }
}
?>
<style>
.sx{--sx-red:#c62f43;--sx-orange:#d9631a;--sx-yellow:#b98a00;--sx-blue:#2a58cc;--sx-green:#167f4c;--sx-brown:#a2570c;--sx-pink:#b51d70;--sx-purple:#6440c6;--sx-slate:#3b4858;--sx-teal:#0c7f8e}
.sx .sxhead{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:4px}
.sx .sxhead h1{margin:0}
.sx .sxwhy{font-size:13px;color:var(--muted);margin:2px 0 12px;max-width:110ch}
.sx .sxwhy b{color:var(--ink)}
.sx .sxband{background:var(--sx-slate);color:#fff;border-radius:var(--radius,14px);padding:16px 18px;margin-bottom:12px;box-shadow:var(--shadow-sm)}
.sx .sxband h1{color:#fff;margin:0;font-size:22px}
.sx .sxband p{margin:4px 0 0;opacity:.88;font-size:13px}
.sx .sxqa{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.sx .sxqa a{display:inline-flex;align-items:center;gap:6px;background:var(--sx-slate);color:#fff;border-radius:10px;padding:8px 13px;font-weight:700;font-size:13px;text-decoration:none;min-height:38px}
.sx .sxqa a:hover{filter:brightness(1.15)}
.sx .sxlab{font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:16px 0 8px}
.sx .sxkpis{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(128px,1fr))}
.sx .sxk{display:flex;flex-direction:column;border-radius:12px;padding:12px 13px;color:#fff;text-decoration:none;min-width:0;box-shadow:var(--shadow-sm);transition:transform .12s, box-shadow .12s}
.sx a.sxk:hover{transform:translateY(-1px);box-shadow:var(--shadow)}
.sx a.sxk:focus-visible{outline:3px solid var(--focus,#2563eb);outline-offset:2px}
.sx .sxk-t{font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;opacity:.95}
.sx .sxk-v{font-size:24px;font-weight:800;margin:5px 0 2px;font-variant-numeric:tabular-nums;line-height:1.15;overflow-wrap:anywhere}
.sx .sxk-h{font-size:12px;opacity:.9}
.sx .sx-red{background:var(--sx-red)}.sx .sx-orange{background:var(--sx-orange)}.sx .sx-yellow{background:var(--sx-yellow)}.sx .sx-blue{background:var(--sx-blue)}.sx .sx-green{background:var(--sx-green)}.sx .sx-brown{background:var(--sx-brown)}.sx .sx-pink{background:var(--sx-pink)}.sx .sx-purple{background:var(--sx-purple)}.sx .sx-slate{background:var(--sx-slate)}.sx .sx-teal{background:var(--sx-teal)}
.sx .sxsteps{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
.sx .sxst{border:1px solid var(--line);border-radius:12px;overflow:hidden;background:var(--card);text-decoration:none;color:inherit}
.sx .sxst b{display:block;color:#fff;padding:8px 12px;font-size:13.5px}
.sx .sxst span{display:block;padding:9px 12px;font-size:13px;color:var(--muted)}
.sx .sxtiles{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(170px,1fr))}
.sx .sxtile{border:1px solid var(--line);border-radius:12px;overflow:hidden;background:var(--card);text-decoration:none;color:inherit;transition:box-shadow .12s}
.sx .sxtile:hover{box-shadow:var(--shadow)}
.sx .sxtile b{display:block;color:#fff;padding:7px 11px;font-size:13px}
.sx .sxtile span{display:block;padding:8px 11px 2px;font-size:13px;font-weight:600}
.sx .sxtile i{display:block;padding:0 11px 9px;font-size:11.5px;color:var(--muted);font-style:normal}
.sx .sxgrid2{display:grid;gap:12px;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr)}
@media (max-width:960px){.sx .sxgrid2{grid-template-columns:minmax(0,1fr)}}
.sx .sxcard{background:var(--card);border:1px solid var(--line);border-radius:var(--radius,14px);padding:12px 14px;min-width:0;margin-top:12px}
.sx .sxgrid2 .sxcard{margin-top:0}
.sx .sxcard h2{font-size:15px;margin:0 0 8px}
.sx .sxcard .tbl{overflow-x:auto}
.sx table.sxt{border-collapse:collapse;width:100%;font-size:13.5px}
.sx .sxt th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);text-align:left;font-weight:700;padding:7px 8px;border-bottom:1px solid var(--line);white-space:nowrap}
.sx .sxt td{padding:8px;border-bottom:1px solid var(--line);vertical-align:middle}
.sx .sxt tr:last-child td{border-bottom:0}
.sx .sxt td.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.sx .sxt a{font-weight:600}
.sx .sxp{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:2px 9px;white-space:nowrap}
.sx .p-bad{background:var(--bad-bg,#fef2f2);color:var(--bad,#b91c1c)}.sx .p-warn{background:var(--warn-bg,#fffbeb);color:var(--warn,#b45309)}.sx .p-week{background:#fff6cf;color:#8a6a00}.sx .p-ok{background:var(--ok-bg,#ecfdf5);color:var(--ok,#15803d)}.sx .p-mut{background:var(--hover,#f1f5f9);color:var(--muted)}
.sx .sxcs{display:inline-flex;gap:6px;flex-wrap:nowrap}
.sx .sxt td.nw{white-space:nowrap}
.sx .sxc{font-size:12px;font-weight:700;border:1.5px solid var(--line);border-radius:8px;padding:3px 8px;background:var(--card);text-decoration:none;white-space:nowrap;color:var(--ink)}
.sx .sxc:hover{border-color:var(--brand);color:var(--brand)}
.sx .sxchips{display:flex;flex-wrap:wrap;gap:6px}
.sx .sxchip{border:1.5px solid var(--line);border-radius:999px;padding:5px 12px;font-size:13px;font-weight:600;background:var(--card);text-decoration:none;color:var(--ink);min-height:34px;display:inline-flex;align-items:center}
.sx .sxchip.on{border-color:var(--brand);background:color-mix(in srgb,var(--brand) 10%,var(--card));color:var(--brand)}
.sx .sxfix{font-weight:700;font-size:13px;white-space:nowrap}
.sx .sxbars{display:flex;flex-direction:column;gap:7px;font-size:13px}
.sx .sxbar{display:grid;grid-template-columns:minmax(90px,170px) 1fr minmax(70px,auto);gap:8px;align-items:center}
.sx .sxbar i{display:block;height:12px;border-radius:4px;background:var(--brand);min-width:2px}
.sx .sxbar em{font-style:normal;text-align:right;font-variant-numeric:tabular-nums}
.sx .sxempty{color:var(--muted);font-size:13px;padding:6px 0}
.sx .sxlegend{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.sx .sxboard{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(190px,1fr);gap:10px;overflow-x:auto;padding-bottom:6px}
.sx .sxcol{background:var(--card);border:1px solid var(--line);border-radius:12px;min-width:0}
.sx .sxcol>header{color:#fff;padding:8px 11px;border-radius:12px 12px 0 0;font-weight:700;font-size:13px}
.sx .sxcol>header small{display:block;font-weight:500;font-size:11.5px;opacity:.92}
.sx .sxcard2{display:block;margin:8px;border:1px solid var(--line);border-left:4px solid var(--line);border-radius:9px;padding:7px 9px;font-size:12.5px;text-decoration:none;color:inherit;background:var(--card)}
.sx .sxcard2:hover{box-shadow:var(--shadow-sm)}
.sx .sxcard2.r{border-left-color:var(--sx-red)}.sx .sxcard2.o{border-left-color:var(--sx-orange)}.sx .sxcard2.y{border-left-color:var(--sx-yellow)}
.sx .sxcard2 b{display:block;font-size:13px}
.sx .sxcard2 span{color:var(--muted)}
.sx .sxside{display:flex;flex-wrap:wrap;gap:10px 28px;align-items:center;background:var(--sx-slate);color:#fff;border-radius:12px;padding:12px 16px;margin-bottom:12px}
.sx .sxside span{font-size:12px;opacity:.85;display:block}
.sx .sxside strong{display:block;font-size:20px}
</style>
