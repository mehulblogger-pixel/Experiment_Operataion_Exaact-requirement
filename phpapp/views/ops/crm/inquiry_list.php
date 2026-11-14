<?php $stPill = ['OPEN'=>'p-warn','QUOTED'=>'p-info','WON'=>'p-ok','LOST'=>'p-bad','DROPPED'=>'p-mut'];
      $stOpts = function_exists('crm_inquiry_status_opts') ? crm_inquiry_status_opts() : INQUIRY_STATUS; $funnel = $funnel ?? []; ?>
<div class="master-head">
  <div><h1><?= e(T_REG('inquiry')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Received via email / phone — the front of the sales funnel. <?= count($rows) ?> shown.</p></div>
  <?php if (can('mod.inquiries.edit')): ?><a class="btn" href="/inquiry-new">+ New inquiry</a><?php endif; ?>
</div>

<?php // Module 19 — inquiries left open past the response service level, un-quoted.
$dueCount = $dueCount ?? 0; $sla = $sla ?? 7; ?>
<?php if (!empty($dueCount)): ?>
<div class="panel" style="border-left:3px solid var(--bad);margin-top:12px">
  <b>🔔 <?= (int)$dueCount ?> inquiry<?= $dueCount==1?'':'s' ?> waiting for a quotation</b>
  <span class="muted" style="font-size:12.5px">— open more than <?= (int)$sla ?> days with no quote raised. A customer who
  asked for a price and heard nothing is the fastest lost sale. See the ranked list on the
  <a href="/advisor">business advisor</a>, or the ⏳ rows below.</span>
</div>
<?php endif; ?>

<?php //  Owner, 13 Nov 2026: what became of the enquiries in view — the enquiry-to-order rate.
      //  The figures follow the year, search and branch shown; each card filters the list.
  if (!empty($funnel['ALL'])):
    $fAll = (int)$funnel['ALL']; $fWon = (int)($funnel['WON'] ?? 0); $fLost = (int)($funnel['LOST'] ?? 0);
    $fQ = (int)($funnel['QUOTED'] ?? 0) + $fWon + $fLost;
    $pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) . '%' : '—';
    $href = fn($k) => '/inquiries?st=' . $k . ($q !== '' ? '&q=' . urlencode($q) : '') . (($fy ?? '') !== '' ? '&fy=' . urlencode((string)$fy) : ''); ?>
<div class="kpi-row" style="margin:12px 0">
  <a class="kpi" href="/inquiries" style="text-decoration:none;color:inherit"><span class="kic">📨</span><div class="k">Enquiries</div><div class="v"><?= $fAll ?></div><div class="d"><?= (int)($funnel['OPEN'] ?? 0) ?> open, not quoted yet</div></a>
  <a class="kpi" href="<?= e($href('QUOTED')) ?>" style="text-decoration:none;color:inherit"><span class="kic">📝</span><div class="k">Quoted</div><div class="v"><?= $fQ ?></div><div class="d"><?= $pct($fQ, $fAll) ?> of enquiries · <?= (int)($funnel['QUOTED'] ?? 0) ?> awaiting an answer</div></a>
  <a class="kpi tone-ok" href="<?= e($href('WON')) ?>" style="text-decoration:none;color:inherit"><span class="kic">🏆</span><div class="k">Won</div><div class="v"><?= $fWon ?></div><div class="d"><?= $pct($fWon, $fAll) ?> of enquiries became orders</div></a>
  <a class="kpi<?= $fLost ? ' tone-bad' : '' ?>" href="<?= e($href('LOST')) ?>" style="text-decoration:none;color:inherit"><span class="kic">📉</span><div class="k">Lost · Dropped</div><div class="v"><?= $fLost ?> · <?= (int)($funnel['DROPPED'] ?? 0) ?></div><div class="d">Hit rate <?= $pct($fWon, $fWon + $fLost) ?> of decided <?= e(Tlp('quote')) ?></div></a>
</div>
<?php endif; ?>

<form method="get" action="/inquiries" class="filter-bar" style="gap:8px;flex-wrap:wrap">
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search no. / subject / client" style="min-width:220px">
  <select class="form-control" name="st" onchange="this.form.submit()"><option value="">All statuses</option>
    <?php foreach ($stOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <?= fy_select_html($fy ?? current_fy()) ?>
  <button class="btn small" type="submit">Search</button>
  <?php if ($q!==''||$st!==''||($fy ?? current_fy())!==current_fy()): ?><a class="btn small secondary" href="/inquiries">Reset</a><?php endif; ?>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Inquiry</th><th>Received</th><th>Client</th><th>Subject</th><th>Status</th><th>Assigned to</th><th>Branch</th><th><?= e(T('sbu')) ?></th><th>Source</th><th></th></tr></thead>
    <tbody>
    <?php //  Each enquiry's own current quotation (the accepted one first, then the newest) — for its button.
      $inqQuotes = [];
      $ids_ = array_values(array_filter(array_map(fn($x) => (int)$x['id'], $rows)));
      if ($ids_) foreach (ops_all("SELECT id, quote_no, status, inquiry_id FROM quotations WHERE is_current=1 AND inquiry_id IN (" . implode(',', array_fill(0, count($ids_), '?')) . ")
                                   ORDER BY CASE WHEN status='ACCEPTED' THEN 0 WHEN status='LOST' THEN 2 ELSE 1 END, id DESC", $ids_) ?: [] as $qq)
          if (!isset($inqQuotes[(int)$qq['inquiry_id']])) $inqQuotes[(int)$qq['inquiry_id']] = $qq; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['inquiry_no']) ?></b>
        <?php if (!empty($r['origin_ref'])): ?><div class="muted" style="font-size:12px">from lead <a href="/lead?id=<?= (int)$r['lead_id'] ?>"><?= e($r['origin_ref']) ?></a></div><?php endif; ?></td>
      <td><?= e($r['received_date'] ?: '—') ?></td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><?= e($r['subject'] ?: '—') ?></td>
      <td><span class="pill <?= $stPill[$r['status']] ?? 'p-mut' ?>"<?= $r['status']==='DROPPED' && !empty($r['drop_reason']) ? ' title="' . e(INQUIRY_DROP_REASONS[$r['drop_reason']] ?? '') . '"' : '' ?>><?= e($stOpts[$r['status']] ?? $r['status']) ?></span><?php
        // Module 19 — an OPEN inquiry past the response service level with no quote.
        if ($r['status']==='OPEN') { $base = trim((string)($r['received_date'] ?? '')) ?: substr((string)($r['created_at'] ?? ''),0,10);
          if ($base!=='') { $age=(int)floor((strtotime(date('Y-m-d'))-strtotime($base))/86400); if ($age > (int)$sla) echo ' <span class="pill p-bad" title="Open '.$age.' days, no quotation">⏳ '.$age.'d</span>'; } } ?></td>
      <?php // Owner, SAL-016 (12 Nov 2026): who has it and which branch, each in its own column, in plain text.
        $who = trim((string)($r['owner_first'] ?? '') . ' ' . (string)($r['owner_last'] ?? '')) ?: (string)($r['owner_username'] ?? '');
        if ($who === '') $who = trim((string)($r['lead_owner_name'] ?? '')); ?>
      <td><?= $who !== '' ? e($who) : '<span class="muted">Nobody yet</span>' ?></td>
      <td><?= !empty($r['office_name']) ? e($r['office_name']) : '<span class="muted" title="No branch set — every branch sees it">All branches</span>' ?></td>
      <td><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?: '—') ?></td>
      <td><?= e(lk_options_or('inquiry_source', INQUIRY_SOURCES)[$r['source']] ?? $r['source']) ?></td>
      <td class="num" style="white-space:nowrap">
        <?php if (can('mod.inquiries.edit')): ?><a class="btn small secondary" href="/inquiry-edit?id=<?= (int)$r['id'] ?>">Edit</a><?php endif; ?>
        <?php //  THE QUOTE BUTTON FOLLOWS WHERE THE ENQUIRY STANDS (owner, 14 Nov 2026: "One the quote is
              //  submitted why we have quote button there" — chose: Open → Quote; Quoted → open it, plus a
              //  small "+ another"; Won → open it only; Lost → Quote again; Dropped → nothing).
          $iq = $inqQuotes[(int)$r['id']] ?? null; $mayQuote = can('mod.quotes.edit') && (!function_exists('crm_inquiry_can_work') || crm_inquiry_can_work($r));
          $st_ = (string)$r['status']; ?>
        <?php if ($st_ === 'OPEN' && $mayQuote): ?><a class="btn small" href="/quote-new?inquiry=<?= (int)$r['id'] ?>">Quote</a>
        <?php elseif (in_array($st_, ['QUOTED', 'WON'], true) && $iq && can('mod.quotes.view')): ?><a class="btn small" href="/quote?id=<?= (int)$iq['id'] ?>" title="Open its <?= e(Tl('quote')) ?>">Open <?= e($iq['quote_no']) ?></a>
          <?php if ($st_ === 'QUOTED' && $mayQuote): ?><a class="btn small secondary" href="/quote-new?inquiry=<?= (int)$r['id'] ?>" title="A second, separate <?= e(Tl('quote')) ?> for this enquiry (a different scope or option). To change the one sent, open it and Revise." onclick="return confirm('Make a second, separate <?= e(Tl('quote')) ?> for this enquiry? To change the one already made, open it and press Revise instead.')">+ another</a><?php endif; ?>
        <?php elseif ($st_ === 'LOST' && $mayQuote): ?><a class="btn small secondary" href="/quote-new?inquiry=<?= (int)$r['id'] ?>">Quote again</a>
        <?php endif; ?>
        <?php if ((is_admin_level() || is_master()) && !in_array($r['status'], ['QUOTED','WON','LOST'], true)): ?>
          <form method="post" action="/inquiry-delete" style="display:inline" onsubmit="return confirm('Delete inquiry <?= e($r['inquiry_no']) ?>? This cannot be undone.')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn small danger" type="submit">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" style="text-align:center;padding:24px" class="muted">No inquiries yet<?= can('mod.inquiries.edit')?' — click "New inquiry" to log the first one':'' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
