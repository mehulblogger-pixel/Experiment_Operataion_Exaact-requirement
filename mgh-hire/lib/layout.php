<?php
// =========================================================================
//  MGH Hire — page chrome. Branding (name / colour / logo) is read live from
//  settings, so a customer re-themes the whole product with no code change.
// =========================================================================

function nav_items() {
    // [route, label, icon, permission]
    return [
        ['dashboard',    'Dashboard',    '▪', 'view'],
        ['requisitions', 'Requisitions', '▤', 'view'],
        ['candidates',   'Candidates',   '☰', 'view'],
        ['pipeline',     'Pipeline',     '⇥', 'pipeline.edit'],
        ['users',        'Users',        '◍', 'users'],
        ['settings',     'Branding',     '✦', 'settings'],
    ];
}

function layout_top($title = '') {
    $u        = current_user();
    $brand    = brand_color();
    $accent   = accent_color();
    $product  = e(product_name());
    $company  = e(setting('company_name', ''));
    $logo     = setting('logo_data', '');
    $active   = get('p', 'dashboard');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ? e($title) . ' · ' : '' ?><?= $product ?></title>
<style>
  :root{
    --brand: <?= e($brand) ?>;
    --accent: <?= e($accent) ?>;
    --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f6f7fb; --card:#fff;
    --ok:#059669; --warn:#b45309; --bad:#dc2626; --radius:12px;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:"Inter","Segoe UI",system-ui,Arial,sans-serif;background:var(--bg);color:var(--ink);font-size:14px;line-height:1.5}
  a{color:var(--brand);text-decoration:none}
  .app{display:flex;min-height:100vh}
  /* Sidebar (desk-first) */
  .side{width:230px;background:#0b1020;color:#cbd5e1;flex:none;display:flex;flex-direction:column;position:sticky;top:0;height:100vh}
  .side .logo{display:flex;align-items:center;gap:10px;padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
  .side .logo .mark{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--brand),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;overflow:hidden}
  .side .logo img{width:34px;height:34px;object-fit:cover;border-radius:9px}
  .side .logo b{color:#fff;font-size:15px}
  .side nav{padding:10px 8px;display:flex;flex-direction:column;gap:2px}
  .side nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;color:#cbd5e1;font-weight:500}
  .side nav a .ic{width:18px;text-align:center;opacity:.8}
  .side nav a:hover{background:rgba(255,255,255,.06);color:#fff}
  .side nav a.on{background:var(--brand);color:#fff}
  .side .who{margin-top:auto;padding:14px 16px;border-top:1px solid rgba(255,255,255,.08);font-size:12.5px}
  .side .who .nm{color:#fff;font-weight:600}
  .side .who .rl{color:#94a3b8}
  .side .who a{color:#93c5fd;font-size:12px}
  /* Main */
  .main{flex:1;min-width:0;display:flex;flex-direction:column}
  .top{background:#fff;border-bottom:1px solid var(--line);padding:14px 26px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:5}
  .top h1{margin:0;font-size:18px}
  .top .co{color:var(--muted);font-size:12.5px}
  .wrap{padding:24px 26px;max-width:1180px;width:100%}
  /* Cards & bits */
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;margin-bottom:18px}
  .card h2{margin:0 0 14px;font-size:15px}
  .grid{display:grid;gap:16px}
  .kpis{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
  .kpi{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:16px 18px}
  .kpi .n{font-size:26px;font-weight:800}
  .kpi .l{color:var(--muted);font-size:12.5px;margin-top:2px}
  .kpi.b{border-left:4px solid var(--brand)}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top}
  th{font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);background:#f8fafc}
  tr:hover td{background:#fbfcfe}
  .btn{display:inline-flex;align-items:center;gap:7px;background:var(--brand);color:#fff;border:none;padding:9px 15px;border-radius:9px;font-weight:600;cursor:pointer;font-size:13.5px}
  .btn:hover{filter:brightness(1.06)}
  .btn.ghost{background:#fff;color:var(--ink);border:1px solid var(--line)}
  .btn.sm{padding:6px 11px;font-size:12.5px}
  .btn.ok{background:var(--ok)} .btn.bad{background:var(--bad)} .btn.warn{background:var(--warn)}
  label{display:block;font-size:12.5px;font-weight:600;color:#334155;margin:12px 0 5px}
  input,select,textarea{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}
  input:focus,select:focus,textarea:focus{outline:2px solid var(--brand);outline-offset:-1px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
  .pill{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:700}
  .pill.g{background:#ecfdf5;color:#047857}.pill.a{background:#fffbeb;color:#b45309}
  .pill.r{background:#fef2f2;color:#dc2626}.pill.n{background:#eef2ff;color:#4338ca}.pill.s{background:#f1f5f9;color:#475569}
  .flash{padding:11px 15px;border-radius:10px;margin-bottom:16px;font-weight:600}
  .flash.ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
  .flash.err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
  .muted{color:var(--muted)} .right{text-align:right} .mt0{margin-top:0}
  .crumbs{font-size:12.5px;color:var(--muted);margin-bottom:12px}
  /* Stage timeline */
  .steps{display:flex;flex-wrap:wrap;gap:6px;margin:4px 0 6px}
  .steps .st{font-size:11px;padding:4px 9px;border-radius:16px;background:#f1f5f9;color:#64748b;white-space:nowrap}
  .steps .st.done{background:#dcfce7;color:#15803d}
  .steps .st.now{background:var(--brand);color:#fff;font-weight:700}
  .timeline{list-style:none;margin:0;padding:0}
  .timeline li{position:relative;padding:0 0 16px 20px;border-left:2px solid var(--line)}
  .timeline li::before{content:"";position:absolute;left:-6px;top:2px;width:10px;height:10px;border-radius:50%;background:var(--brand)}
  .timeline li:last-child{border-left-color:transparent}
  .timeline .tm{font-size:11.5px;color:var(--muted)}
  @media(max-width:820px){
    .app{flex-direction:column}
    .side{width:100%;height:auto;position:static;flex-direction:row;overflow-x:auto}
    .side .logo{border:none}.side nav{flex-direction:row}.side .who{display:none}
    .row2,.row3{grid-template-columns:1fr}
    .wrap{padding:16px}
  }
</style>
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="logo">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?>
        <div class="mark"><?= e(mb_substr(product_name(),0,1)) ?></div><?php endif; ?>
      <b><?= $product ?></b>
    </div>
    <nav>
      <?php foreach (nav_items() as $it): if (!can($it[3])) continue; ?>
        <a href="?p=<?= $it[0] ?>" class="<?= $active===$it[0]?'on':'' ?>">
          <span class="ic"><?= $it[2] ?></span><?= e($it[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ($u): ?>
    <div class="who">
      <div class="nm"><?= e($u['name']) ?></div>
      <div class="rl"><?= e(ROLES()[$u['role']] ?? $u['role']) ?></div>
      <a href="?p=logout">Sign out</a>
    </div>
    <?php endif; ?>
  </aside>
  <div class="main">
    <div class="top">
      <h1><?= e($title ?: 'Dashboard') ?></h1>
      <div class="co"><?= $company ?: 'Recruitment workspace' ?></div>
    </div>
    <div class="wrap">
      <?php foreach (flash_take() as $f): ?>
        <div class="flash <?= $f['t']==='ok'?'ok':'err' ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
<?php }

function layout_bottom() { ?>
    </div>
  </div>
</div>
</body>
</html>
<?php }

// Small helpers for status pills.
function req_pill($s) {
    $map = ['draft'=>['s','Draft'],'pending_approval'=>['a','Pending approval'],'approved'=>['n','Approved'],
            'on_hold'=>['a','On hold'],'filled'=>['g','Filled'],'closed'=>['s','Closed'],'cancelled'=>['r','Cancelled']];
    $m = $map[$s] ?? ['s', ucfirst($s)];
    return '<span class="pill '.$m[0].'">'.e($m[1]).'</span>';
}
function cand_pill($s) {
    $map = ['active'=>['n','In process'],'offer'=>['a','At offer'],'hired'=>['g','Hired'],
            'rejected'=>['r','Rejected'],'withdrawn'=>['s','Withdrawn'],'on_hold'=>['a','On hold']];
    $m = $map[$s] ?? ['s', ucfirst($s)];
    return '<span class="pill '.$m[0].'">'.e($m[1]).'</span>';
}
