<?php
// Inquiries register (owner, 21 Nov 2026: Sales redesign Stage 3, approved design
// s3-inqs). The same cards, filters, columns, quote buttons and delete as before,
// in the Sales workspace look, now on the shared register (paging, sorting, a
// column chooser and a CSV — approved backend item 3 and question 6). Each row
// opens the inquiry's own read-only page (DEF-14); Edit lives there. Every figure,
// row and refusal still comes from ops_crm_inquiries() in lib/crm.php. The old
// page is kept in tests/fixtures/pre_stage3/inquiry_list.php.
require_once __DIR__ . '/../_sxw_ui.php';
$stPill = ['OPEN' => 'p-warn', 'QUOTED' => 'p-info', 'WON' => 'p-ok', 'LOST' => 'p-bad', 'DROPPED' => 'p-mut'];
$stOpts = function_exists('crm_inquiry_status_opts') ? crm_inquiry_status_opts() : INQUIRY_STATUS;
$funnel = $funnel ?? []; $dueCount = (int)($dueCount ?? 0); $sla = (int)($sla ?? 7); $total = (int)($total ?? count($rows));
$canAdd = can('mod.inquiries.edit');
$canDel = is_admin_level() || is_master();

//  Each inquiry's own current quotation (the accepted one first, then the newest) — for its button.
$inqQuotes = [];
$ids_ = array_values(array_filter(array_map(fn($x) => (int)$x['id'], $rows)));
if ($ids_) foreach (ops_all("SELECT id, quote_no, status, inquiry_id FROM quotations WHERE is_current=1 AND inquiry_id IN (" . implode(',', array_fill(0, count($ids_), '?')) . ")
                             ORDER BY CASE WHEN status='ACCEPTED' THEN 0 WHEN status='LOST' THEN 2 ELSE 1 END, id DESC", $ids_) ?: [] as $qq)
    if (!isset($inqQuotes[(int)$qq['inquiry_id']])) $inqQuotes[(int)$qq['inquiry_id']] = $qq;

//  The status cell: the pill, its drop reason, and ⏳ days for one left open past the service level.
$dt['columns']['status']['render'] = function ($r) use ($stPill, $stOpts, $sla) {
    $h = '<span class="pill ' . ($stPill[$r['status']] ?? 'p-mut') . '"'
       . ($r['status'] === 'DROPPED' && !empty($r['drop_reason']) ? ' title="' . e(INQUIRY_DROP_REASONS[$r['drop_reason']] ?? '') . '"' : '') . '>'
       . e($stOpts[$r['status']] ?? $r['status']) . '</span>';
    if ($r['status'] === 'OPEN') {
        $base = trim((string)($r['received_date'] ?? '')) ?: substr((string)($r['created_at'] ?? ''), 0, 10);
        if ($base !== '') { $age = (int)floor((strtotime(date('Y-m-d')) - strtotime($base)) / 86400);
            if ($age > $sla) $h .= ' <span class="pill p-bad" title="Open ' . $age . ' days, no quotation">⏳ ' . $age . 'd</span>'; }
    }
    if ($r['status'] === 'DROPPED' && !empty($r['drop_reason'])) $h .= '<div class="muted" style="font-size:11.5px">' . e(INQUIRY_DROP_REASONS[$r['drop_reason']] ?? '') . '</div>';
    return $h;
};
//  The row's actions: open it; the quote button by where it stands (owner, 14 Nov 2026 —
//  Open → Quote; Quoted → open it + "+ another"; Won → open it; Lost → Quote again;
//  Dropped → nothing); delete for administrators while it has no quotation.
$dt['columns']['actions']['render'] = function ($r) use ($inqQuotes, $canDel) {
    $h = '<span class="sxw-acts"><a class="sxw-ib" href="/inquiry?id=' . (int)$r['id'] . '">' . rec_icon('eye', 's') . '<span>Open</span></a>';
    $iq = $inqQuotes[(int)$r['id']] ?? null;
    $mayQuote = can('mod.quotes.edit') && (!function_exists('crm_inquiry_can_work') || crm_inquiry_can_work($r));
    $st = (string)$r['status'];
    if ($st === 'OPEN' && $mayQuote) $h .= '<a class="sxw-ib p" href="/quote-new?inquiry=' . (int)$r['id'] . '">' . rec_icon('file', 's') . '<span>Quote</span></a>';
    elseif (in_array($st, ['QUOTED', 'WON'], true) && $iq && can('mod.quotes.view')) {
        $h .= '<a class="sxw-ib" href="/quote?id=' . (int)$iq['id'] . '" title="Open its ' . e(Tl('quote')) . '"><span class="sxq-v">Open ' . e($iq['quote_no']) . '</span></a>';
        if ($st === 'QUOTED' && $mayQuote)
            $h .= '<a class="sxw-ib" href="/quote-new?inquiry=' . (int)$r['id'] . '" title="A second, separate ' . e(Tl('quote')) . ' for this enquiry (a different scope or option). To change the one sent, open it and Revise."'
                . ' onclick="return confirm(\'Make a second, separate ' . e(Tl('quote')) . ' for this enquiry? To change the one already made, open it and press Revise instead.\')"><span class="sxq-v">+ another</span></a>';
    } elseif ($st === 'LOST' && $mayQuote) $h .= '<a class="sxw-ib" href="/quote-new?inquiry=' . (int)$r['id'] . '"><span class="sxq-v">Quote again</span></a>';
    if ($canDel && !in_array($st, ['QUOTED', 'WON', 'LOST'], true))
        $h .= '<button class="sxw-ib bad" type="submit" form="inqdel-' . (int)$r['id'] . '" title="Delete ' . e($r['inquiry_no']) . '" aria-label="Delete ' . e($r['inquiry_no']) . '"'
            . ' onclick="return confirm(\'Delete inquiry ' . e($r['inquiry_no']) . '? This cannot be undone.\')">' . rec_icon('trash', 's') . '<span>Delete</span></button>';
    return $h . '</span>';
};

$fAll = (int)($funnel['ALL'] ?? 0); $fWon = (int)($funnel['WON'] ?? 0); $fLost = (int)($funnel['LOST'] ?? 0);
$fQ = (int)($funnel['QUOTED'] ?? 0) + $fWon + $fLost;
$pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) . '%' : '—';
$href = fn($k) => '/inquiries?st=' . $k . ($q !== '' ? '&q=' . urlencode($q) : '') . (($fy ?? '') !== '' ? '&fy=' . urlencode((string)$fy) : '');
$right = '<div class="sxw-ctl">' . ($canAdd ? '<a class="sxw-bt p" href="/inquiry-new">+ New ' . e(Tl('inquiry')) . '</a>' : '') . '</div>';
?>
<div class="rec sxw has-bn sxl sxq">
  <?php sxw_nav('/inquiries'); ?>
  <?php sxw_head(THP('inquiry'), 'Requests for a price, received by e-mail or telephone — the front of the sales funnel. Each one follows its ' . Tl('quote') . ' by itself: Quoted while one is out, Won or Lost when it is decided. Only Dropped is set by hand.', 'mail', $right); ?>
  <p class="sxl-note" style="margin-top:-4px"><?= e(T_REG('inquiry')) ?> · <?= $total ?> shown.</p>

  <?php //  Owner, 13 Nov 2026: what became of the enquiries in view — the enquiry-to-order rate.
        //  The figures follow the year, search and branch shown; each card filters the list. ?>
  <?php if ($fAll): ?>
  <div class="sxw-kp" style="--n:4">
    <?php sxw_kpi('teal', 'mail', 'Enquiries', TP('inquiry'), (string)$fAll, (int)($funnel['OPEN'] ?? 0) . ' open, not quoted yet', '/inquiries');
          sxw_kpi('info', 'file', 'Quoted', 'Quoted', (string)$fQ, $pct($fQ, $fAll) . ' of enquiries · ' . (int)($funnel['QUOTED'] ?? 0) . ' awaiting an answer', $href('QUOTED_ANY'));
          sxw_kpi('ok', 'trophy', 'Won', 'Won', (string)$fWon, $pct($fWon, $fAll) . ' of enquiries became orders', $href('WON'));
          sxw_kpi($fLost ? 'bad' : 'grey', 'x', 'Lost · Dropped', 'Lost · Dropped', $fLost . ' · ' . (int)($funnel['DROPPED'] ?? 0), 'Hit rate ' . $pct($fWon, $fWon + $fLost) . ' of decided ' . Tlp('quote'), $href('LOST_DROPPED')); ?>
  </div>
  <?php endif; ?>

  <?php // Module 19 — inquiries left open past the response service level, un-quoted. ?>
  <?php if ($dueCount): ?>
  <div class="sxl-ban warn" role="status">
    <span class="ic"><?= rec_icon('hour') ?></span>
    <div class="tx"><b class="t"><?= $dueCount ?> inquir<?= $dueCount == 1 ? 'y' : 'ies' ?> waiting for a quotation</b>
      Open more than <?= $sla ?> days with no quote raised (<?= $sla ?> days is the company setting). A customer who asked for a price and heard nothing is the fastest lost sale.
      See the ranked list on <a href="/advisor">What to fix</a>, or the ⏳ rows below.</div>
    <a class="sxw-bt" href="/inquiries?<?= e(http_build_query(array_filter(['st' => 'OPEN', 'fy' => $fy ?? null, 'sort' => 'received', 'dir' => 'asc']))) ?>">Show only these</a>
  </div>
  <?php endif; ?>

  <div class="sxl-ctl">
    <form method="get" action="/inquiries" class="sxl-f">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if (($fy ?? '') !== ''): ?><input type="hidden" name="fy" value="<?= e($fy) ?>"><?php endif; ?>
      <label class="lab" for="inq-st">Status</label>
      <select class="form-control" id="inq-st" name="st" onchange="this.form.submit()"><option value="">All statuses</option>
        <?php foreach ($stOpts as $k => $v): ?><option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      <noscript><button class="btn small secondary">Show</button></noscript>
    </form>
    <form method="get" action="/inquiries" class="sxl-f">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($st !== ''): ?><input type="hidden" name="st" value="<?= e($st) ?>"><?php endif; ?>
      <label class="lab">Year</label><?= fy_select_html($fy ?? current_fy()) ?>
    </form>
    <?php if ($q !== '' || $st !== '' || ($fy ?? current_fy()) !== current_fy()): ?><a class="sxw-bt" href="/inquiries">Reset</a><?php endif; ?>
  </div>
  <?php // DEF-SAL-024 — a card opened a group of statuses: say which, as the status box shows one at a time.
    $stGroupWords = ['QUOTED_ANY' => 'quoted — whatever the outcome (awaiting an answer, won or lost)', 'LOST_DROPPED' => 'lost or dropped'];
    if (isset($stGroupWords[$st])): ?>
  <p class="sxl-note">Showing the <?= e(Tlp('inquiry')) ?> <?= e($stGroupWords[$st]) ?>. <a href="/inquiries">Show all</a></p>
  <?php endif; ?>

  <section class="rec-card sxl-reg">
    <?= dt_render($dt, $rows, $total, [
          'caption'     => T_REG('inquiry'),
          'search'      => true,
          'search_hint' => 'Search no. / subject / client',
          'export'      => true,
          'empty'       => 'No inquiries yet' . ($canAdd ? ' — click "New inquiry" to log the first one' : '') . '.',
        ]) ?>
    <?php // Delete is a POST of its own, kept outside the register's table (a form cannot sit inside another form). ?>
    <?php if ($canDel) foreach ($rows as $r): if (in_array((string)$r['status'], ['QUOTED', 'WON', 'LOST'], true)) continue; ?>
      <form method="post" action="/inquiry-delete" id="inqdel-<?= (int)$r['id'] ?>" hidden><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form>
    <?php endforeach; ?>
    <p class="sxl-note" style="margin:8px 2px 0">Only <?= e(Tlp('inquiry')) ?> of your branches and <?= e(Tlp('sbu')) ?> are listed (one with no unit or branch is seen by all). Open an <?= e(Tl('inquiry')) ?> to see where it stands; Edit is on its page, for the people who may work it.</p>
  </section>
</div>
<?php sxw_bottom('/inquiries'); ?>
