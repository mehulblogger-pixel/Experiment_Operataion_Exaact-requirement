<?php
// Quotations register (owner, 21 Nov 2026: Sales redesign Stage 4, approved design
// s4-quotes). The same rows, cards, tabs, "mine" lists, duplicate warning, export and
// buttons as before, in the Sales workspace look, on the shared register (backend
// item 8: paging, sorting, a column chooser, filters by owner / branch / business unit
// and a search that also finds the contact and the contract number). DEF-06: the value
// shown is the figure BEFORE GST, with the total with GST beside it. DEF-18: "+ External"
// is drawn only for the right its server asks for. The rows and figures still come from
// ops_crm_quotes() in lib/crm.php; the old page is kept in tests/fixtures/pre_stage4/.
require_once __DIR__ . '/../_sxw_ui.php';
$stPill = ['DRAFT'=>'p-mut','PENDING_APPROVAL'=>'p-warn','APPROVED'=>'p-info','SENT'=>'p-info',
           'ACCEPTED'=>'p-ok','LOST'=>'p-bad','REJECTED'=>'p-bad','EXPIRED'=>'p-mut'];
$tabs = ['all'=>'All','open'=>'Open','pending'=>'Pending','closed'=>'Won','lost'=>'Lost','expired'=>'Expired'];
$qs = function($v) { $g = $_GET; unset($g['mine'], $g['page']); return '/quotes?' . http_build_query(array_merge($g, ['v'=>$v])); };
$mineApprove = !empty($mineApprove); $mineContract = !empty($mineContract);
$total = (int)($total ?? count($rows));
$stL = lk_options_or('quote_status', QUOTE_STATUS);
$money = fn($v) => cur_sym() . nf((float)$v, fmod((float)$v, 1) ? 2 : 0);

//  The cells the register draws from the row (lib/crm.php declares the columns).
$dt['columns']['no']['render'] = fn($r) => '<a href="/quote?id=' . (int)$r['id'] . '"><b>' . e($r['quote_no']) . '</b></a>'
    . '<div class="muted" style="font-size:12px">' . e(quote_label($r) !== $r['quote_no'] ? 'Rev ' . str_pad((string)(int)$r['rev'], 2, '0', STR_PAD_LEFT) : 'Rev 00') . '</div>';
$dt['columns']['value']['render'] = function ($r) use ($money) {
    $bg = crm_quote_before_gst($r); $tot = (float)$r['total_amount'];
    if ($bg <= 0 && $tot <= 0) return '<span class="muted">—</span>';
    return '<b>' . e($money($bg)) . '</b>' . ($tot > 0 && abs($tot - $bg) > 0.004 ? '<div class="muted" style="font-size:12px">' . e($money($tot)) . ' incl. GST</div>' : '');
};
//  The contract number, from whichever end it was settled — registered by accounts on the
//  order, or recorded on the client's Contracts tab. A won order without one is work nobody
//  can invoice: "pending" is said on a WON quotation only (an approved one is not an order yet).
$dt['columns']['contract']['render'] = function ($r) {
    $cn = trim((string)($r['contract_number'] ?? ''));
    if ($cn !== '') return e($cn);
    if ($r['status'] === 'ACCEPTED') return '<span class="pill p-warn">pending</span>';
    return '<span class="muted">—</span>';
};
$dt['columns']['status']['render'] = function ($r) use ($stPill, $stL) {
    $h = '<span class="pill ' . ($stPill[$r['status']] ?? 'p-mut') . '">' . e($stL[$r['status']] ?? $r['status']) . '</span>';
    // Module 03 — a still-SENT quote whose validity has already lapsed, before the
    // daily sweep stamps it EXPIRED. Show the truth now.
    if ($r['status'] === 'SENT') { $rv = quote_validity($r); if ($rv['expired']) $h .= ' <span class="pill p-mut" title="Past its ' . (int)$r['validity_days'] . '-day validity — expires on the next sweep">past validity</span>'; }
    return $h;
};
//  Owner, 22 Nov 2026 (design request 7b, "Print a quote"): every row also carries its PDF,
//  the same document the quotation page prints (/quote-pdf), so printing is one click here.
$dt['columns']['actions']['render'] = fn($r) => '<span class="sxw-acts"><a class="sxw-ib" href="/quote?id=' . (int)$r['id'] . '">' . rec_icon('eye', 's') . '<span>Open</span></a>'
    . '<a class="sxw-ib" href="/quote-pdf?id=' . (int)$r['id'] . '" title="Download the PDF to print">' . rec_icon('file', 's') . '<span>PDF</span></a></span>';
$printMode = !empty($_GET['print']);

$canNew = can('crm.quote.create') || can('mod.quotes.edit');
$canExt = can('crm.quote.create') || is_master();     // DEF-18 — the server's own rule for /quote-external
$right = '<div class="sxw-ctl">'
    //  The words are the old buttons' own ("+ External", "+ New quote") — people and the robot look for them.
    . ($canExt ? '<a class="sxw-bt" href="/quote-external">+ External</a>' : '')
    . ($canNew ? '<a class="sxw-bt p" href="/quote-new">+ New ' . e(Tl('quote')) . '</a>' : '')
    . '</div>';
$sbuL = lk_options_or('sbu', OPS_SBUS);
?>
<div class="rec sxw has-bn sxl sxq sxqt">
  <?php sxw_nav('/quotes'); ?>
  <?php sxw_head(T_REG('quote'), TH('quote') . ' → approval → send → follow-up → acceptance. The current revision of every ' . Tl('quote') . '. Values are shown before GST (the figure approvals, contracts and the board use); the total with GST is beside it.', 'file', $right); ?>

  <?php if ($mineApprove): ?>
  <div class="sxl-ban warn" role="status"><span class="ic"><?= rec_icon('lock') ?></span>
    <div class="tx"><b class="t">Awaiting your approval</b><?= count($rows) ?> <?= e(Tl('quote')) ?><?= count($rows)===1?'':'s' ?> routed to you for approval — from any office. Decide each on its page, or from the <a href="/approvals">Approvals inbox</a>.</div>
    <a class="sxw-bt" href="/quotes?v=all">See all <?= e(Tlp('quote')) ?> →</a></div>
  <?php elseif ($mineContract): ?>
  <div class="sxl-ban warn" role="status"><span class="ic"><?= rec_icon('file') ?></span>
    <div class="tx"><b class="t">To register a contract</b><?= count($rows) ?> accepted <?= e(Tl('quote')) ?><?= count($rows)===1?'':'s' ?> waiting for a contract number — open one and register it in its Contract panel.</div>
    <a class="sxw-bt" href="/quotes?v=all">See all <?= e(Tlp('quote')) ?> →</a></div>
  <?php else: ?>
  <p class="sxl-note" style="margin-top:-4px"><?= $total ?> shown.</p>
  <?php endif; ?>

  <?php // Each card filters the register to its own view, so the number is a way in
        // rather than a read-out. The figures are exactly the ones the old cards read. ?>
  <div class="sxw-kp" style="--n:<?= !empty($awaitingMe) ? 6 : 5 ?>">
    <?php sxw_kpi('teal', 'file', 'Open', 'Open', (string)(int)$counts['open'], 'in the funnel', $qs('open'));
          sxw_kpi('warn', 'hour', 'Pending', 'Pending', (string)(int)$counts['pending'], 'approval / reply awaited', $qs('pending'));
          sxw_kpi('ok', 'trophy', 'Closed (won)', 'Won', (string)(int)$counts['closed'], 'accepted', $qs('closed'));
          sxw_kpi('bad', 'x', 'Lost', 'Lost', (string)(int)$counts['lost'], 'regretted', $qs('lost'));
          sxw_kpi('grey', 'cal', 'Expired', 'Expired', (string)(int)($counts['expired'] ?? 0), 'validity lapsed', $qs('expired'));
          if (!empty($awaitingMe)) sxw_kpi('info', 'lock', 'Awaiting your approval', 'Awaiting your approval', (string)(int)$awaitingMe, 'at your level', '/quotes?mine=approve'); ?>
  </div>

  <?php // §3 — new duplicates are refused at entry and by a unique index. A database
        // that already held duplicates before that rule existed cannot build the
        // index, so the ones already there are named here instead of being hidden.
        $dupQ = ops_all("SELECT quote_no, rev, COUNT(*) n FROM quotations GROUP BY quote_no, rev HAVING COUNT(*)>1");
        $dupC = ops_all("SELECT contract_number, COUNT(*) n FROM partner_contracts WHERE contract_number<>'' GROUP BY contract_number HAVING COUNT(*)>1"); ?>
  <?php if ($dupQ || $dupC): ?>
  <div class="sxl-ban bad" role="status"><span class="ic"><?= rec_icon('alert') ?></span>
    <div class="tx"><b class="t">Numbers used more than once</b>
      New duplicates are now refused, but these were recorded before that rule existed. Until they are merged or
      renumbered, anything counted against them — revenue, contract expiry, quantity used — is reading more than one record.
      <div style="margin-top:6px">
        <?php foreach ($dupQ as $d): ?><span class="pill p-warn"><?= e(Tl('quote')) ?> <?= e($d['quote_no']) ?><?= (int)$d['rev'] ? ' R' . (int)$d['rev'] : '' ?> × <?= (int)$d['n'] ?></span> <?php endforeach; ?>
        <?php foreach ($dupC as $d): ?><span class="pill p-warn">contract <?= e($d['contract_number']) ?> × <?= (int)$d['n'] ?></span> <?php endforeach; ?>
      </div></div>
  </div>
  <?php endif; ?>

  <?php if ($printMode): ?>
  <div class="sxl-ban" role="status"><span class="ic"><?= rec_icon('file') ?></span>
    <div class="tx"><b class="t">Print a <?= e(Tl('quote')) ?></b>
      Find the <?= e(Tl('quote')) ?> below (search, or pick Open / Won), then press <b>PDF</b> on its row — the same document its own page prints.</div></div>
  <?php endif; ?>
  <div class="sxl-ctl sxqt-tabs">
    <nav class="sxw-seg" aria-label="Which <?= e(Tlp('quote')) ?>">
      <?php foreach ($tabs as $k=>$lbl): $on = !$mineApprove && !$mineContract && $view===$k; ?>
        <a href="<?= e($qs($k)) ?>"<?= $on ? ' class="on" aria-current="true"' : '' ?>><?= e($lbl) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if (!empty($awaitingMe)): ?><a class="sxw-bt<?= $mineApprove ? ' p' : '' ?>" href="/quotes?mine=approve"><?= rec_icon('lock', 's') ?> Awaiting your approval · <?= (int)$awaitingMe ?></a><?php endif; ?>
    <?php if (!empty($toRegister)): ?><a class="sxw-bt<?= $mineContract ? ' p' : '' ?>" href="/quotes?mine=contract">To register a contract · <?= (int)$toRegister ?></a><?php endif; ?>
    <span class="sxqt-links">
      <?php if (can('crm.quote.approve') || is_master()): ?><a class="sxw-lnk" href="/approval-rules?module=quote"><?= rec_icon('flow', 's') ?> Approval rules</a><?php endif; ?>
      <?php if (can('crm.template.manage') || is_master()): ?><a class="sxw-lnk" href="/templates?kind=quote"><?= rec_icon('file', 's') ?> Templates</a><?php endif; ?>
      <?php if (!function_exists('crm_quote_can_export') || crm_quote_can_export()): ?><a class="sxw-lnk" href="/quotes?<?= e(http_build_query(array_merge(array_diff_key($_GET, ['page' => 1]), ['export'=>'1']))) ?>">⬇ Export</a><?php endif; ?>
    </span>
  </div>

  <form method="get" action="/quotes" class="sxl-ctl sxqt-f">
    <input type="hidden" name="v" value="<?= e($view) ?>">
    <label class="sr-only" for="qt-q">Search</label>
    <input class="form-control" id="qt-q" name="q" value="<?= e($q) ?>" placeholder="Number, subject, <?= e(Tl('client')) ?>, contact, contract no." style="min-width:240px;flex:1 1 260px">
    <?= fy_select_html($fy ?? current_fy()) ?>
    <?php if (!empty($owners)): ?>
    <label class="sr-only" for="qt-own">Assigned to</label>
    <select class="form-control" id="qt-own" name="own"><option value="">Assigned to: anyone</option>
      <?php foreach ($owners as $o): $on = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: $o['username']; ?>
        <option value="<?= (int)$o['id'] ?>" <?= (int)($fOwner ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($on) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
    <?php if (count($branches ?? []) > 1): ?>
    <label class="sr-only" for="qt-br">Branch</label>
    <select class="form-control" id="qt-br" name="br"><option value="">Branch: all of mine</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)($fBranch ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
    <?php if (count($units ?? []) > 1): ?>
    <label class="sr-only" for="qt-unit"><?= e(T('sbu')) ?></label>
    <select class="form-control" id="qt-unit" name="unit"><option value=""><?= e(T('sbu')) ?>: all</option>
      <?php foreach ($units as $u): ?><option value="<?= e($u) ?>" <?= ($fUnit ?? '') === $u ? 'selected' : '' ?>><?= e($sbuL[$u] ?? $u) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
    <button class="btn small" type="submit">Search</button>
    <?php if ($q !== '' || !empty($fOwner) || !empty($fBranch) || ($fUnit ?? '') !== ''): ?><a class="sxw-bt" href="<?= e('/quotes?v=' . urlencode($view)) ?>">Reset</a><?php endif; ?>
  </form>

  <section class="rec-card sxl-reg">
    <?= dt_render($dt, $rows, $total, [
          'caption' => T_REG('quote'),
          'search'  => false,
          'export'  => false,
          'empty'   => 'No ' . Tlp('quote') . ' in this view.',
        ]) ?>
    <p class="sxl-note" style="margin:8px 2px 0">Rows: your branches and <?= e(Tlp('sbu')) ?>, as before. "Contract pending" shows on a won <?= e(Tl('quote')) ?> that has no contract number yet. Values before GST; the total with GST is beside each.</p>
  </section>
</div>
<?php sxw_bottom('/quotes'); ?>
