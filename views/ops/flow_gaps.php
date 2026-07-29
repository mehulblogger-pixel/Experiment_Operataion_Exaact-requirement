<?php $tot = 0; foreach ($gaps as $g) $tot += $g['n']; ?>
<div class="crumbs"><a href="/">Home</a> › Where the flow is broken</div>
<div class="master-head">
  <div><h1>Where the flow is broken</h1>
  <p class="sub" style="margin:2px 0 0">Every place a handover was skipped, between selling, doing and billing. Each row links to the screen that closes it. Nothing here changes anything on its own — a report that quietly repaired the data would hide how often the handover is missed, and that number is the point.</p></div>
  <a class="btn secondary" href="/flow-gaps?export=csv">⬇ CSV</a>
</div>

<div class="qcards" style="margin-top:16px">
  <?php foreach ($gaps as $k => $g): ?>
    <a class="qcard <?= $g['n'] ? ($k==='job_no_invoice' || $k==='invoice_overdue' ? 'tone-bad' : 'tone-warn') : 'tone-ok' ?>" href="#g-<?= e($k) ?>">
      <div class="qic"><?= $g['n'] ? '!' : '✓' ?></div>
      <div class="qn"><?= (int)$g['n'] ?></div>
      <div class="ql"><?= e($g['label']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$tot): ?>
  <div class="panel" style="margin-top:16px">
    <p style="margin:0"><b>Nothing is broken.</b> Every <?= e(Tl('order')) ?> traces to a quotation, every closed <?= e(Tl('job')) ?> has a <?= e(Tl('report')) ?> and an invoice, and every invoice is either settled or not yet due.</p>
  </div>
<?php endif; ?>

<?php foreach ($gaps as $k => $g): if (!$g['n']) continue; ?>
  <div class="panel" id="g-<?= e($k) ?>" style="margin-top:16px;padding:0;overflow:hidden">
    <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
      <b style="font-size:13.5px"><?= e($g['label']) ?></b>
      <span class="muted" style="font-size:12.5px"> — <?= (int)$g['n'] ?><?= $g['n'] >= 200 ? '+ (showing the first 200)' : '' ?></span>
      <?php if (!empty($g['action'])): ?>
        <a class="btn small" style="float:right" href="<?= e($g['action'][0]) ?>"><?= e($g['action'][1]) ?></a>
      <?php endif; ?>
      <div class="muted" style="font-size:12.5px;margin-top:4px"><?= e($g['why']) ?> <b><?= e($g['fix']) ?></b></div>
    </div>
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only"><?= e($g['label']) ?>, <?= (int)$g['n'] ?> rows</caption>
        <thead><tr><th scope="col">Reference</th><th scope="col">Date</th><th scope="col">Who / what</th><th scope="col"></th></tr></thead>
        <tbody>
        <?php foreach (array_slice($g['rows'], 0, 60) as $r): ?>
          <tr>
            <td><a href="<?= e($g['url'] . (int)$r['id']) ?>"><b><?= e($r['ref'] ?: '#' . (int)$r['id']) ?></b></a></td>
            <td><?= trim((string)$r['d']) !== '' ? e(fdate(substr((string)$r['d'],0,10))) : '<span class="muted">—</span>' ?></td>
            <td><?= e($r['who'] ?: '—') ?></td>
            <td><a class="btn small secondary" href="/trace?kind=<?= e($k==='invoice_overdue'?'INVOICE':($k==='receipt_unmatched'?'RECEIPT':($k==='call_no_quote'?'CALL':'JOB'))) ?>&amp;id=<?= (int)$r['id'] ?>">Trace</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($g['n'] > 60): ?>
      <div style="padding:10px 16px;border-top:1px solid var(--line)" class="muted">
        Showing 60 of <?= (int)$g['n'] ?>. The CSV carries the rest.
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
