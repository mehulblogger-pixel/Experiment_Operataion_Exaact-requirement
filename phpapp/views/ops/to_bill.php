<div class="crumbs"><a href="/">Home</a> › <a href="/invoices">Invoices</a> › Waiting to be billed</div>
<div class="master-head">
  <div><h1>Work waiting to be billed</h1>
  <p class="sub" style="margin:2px 0 0">Every <?= e(Tl('job')) ?> that has been closed and is not on any invoice. This is the handover from operations to the books, and until now nobody could see it — work was invoiced when somebody remembered it.</p></div>
</div>

<?php if (!$groups): ?>
  <div class="panel" style="margin-top:16px">
    <p style="margin:0">Nothing is waiting. Every closed <?= e(Tl('job')) ?> is on an invoice.</p>
    <p class="muted" style="font-size:13px;margin:8px 0 0">Work only appears here once it is <b>closed</b> — an open <?= e(Tl('job')) ?> is not finished work.</p>
  </div>
<?php else: ?>
  <?php $grand = 0; foreach ($groups as $g) $grand += $g['value']; ?>
  <div class="qcards" style="margin-top:16px">
    <div class="qcard tone-warn"><div class="qic">⧗</div><div class="qn"><?= count($groups) ?></div><div class="ql">Customers with unbilled work</div></div>
    <div class="qcard tone-bad"><div class="qic">Σ</div><div class="qn" style="font-size:20px"><?= fmoney_short($grand) ?></div><div class="ql">Not yet invoiced</div></div>
  </div>

  <?php foreach ($groups as $cid => $g): ?>
    <form method="post" action="/invoice-new" class="panel" style="margin-top:14px;padding:0;overflow:hidden">
      <input type="hidden" name="partner_id" value="<?= (int)$cid ?>">
      <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <b style="font-size:14px"><?= e($g['name']) ?></b>
        <span class="muted" style="font-size:12.5px"><?= count($g['rows']) ?> <?= e(count($g['rows'])==1 ? Tl('job') : Tlp('job')) ?> · <?= e(fmoney($g['value'])) ?></span>
        <span style="margin-left:auto;display:flex;gap:8px">
          <a class="btn small secondary" href="/ledger?id=<?= (int)$cid ?>">Ledger</a>
          <?php if ($canIssue && $cid): ?>
            <button class="btn small" type="submit">Draft one invoice for the ticked work</button>
          <?php endif; ?>
        </span>
      </div>
      <div class="dt-scroll">
        <table class="dt">
          <caption class="sr-only">Unbilled work for <?= e($g['name']) ?></caption>
          <thead><tr>
            <th scope="col" style="width:34px"><span class="sr-only">Include</span></th>
            <th scope="col"><?= e(TH('job')) ?></th><th scope="col">Closed</th><th scope="col" class="num">Value</th>
          </tr></thead>
          <tbody>
          <?php foreach ($g['rows'] as $r): $val = (float)($r['billable_value'] ?: $r['invoice_value'] ?: 0); ?>
            <tr>
              <td><label class="sr-only" for="jb-<?= (int)$r['id'] ?>">Include <?= e($r['job_code']) ?></label>
                  <input type="checkbox" id="jb-<?= (int)$r['id'] ?>" name="jobs[]" value="<?= (int)$r['id'] ?>" checked></td>
              <td><a href="/job?id=<?= (int)$r['id'] ?>"><?= e($r['job_code']) ?></a></td>
              <td><?= $r['closed_at'] ? e(fdate(substr((string)$r['closed_at'],0,10))) : '<span class="muted">—</span>' ?></td>
              <td class="num"><?= $val ? e(fmoney($val)) : '<span class="pill p-warn">no value</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  <?php endforeach; ?>
  <p class="muted" style="margin-top:12px;font-size:12.5px">A <?= e(Tl('job')) ?> with no value still appears — leaving it off the list would hide work that was never priced, which is the more expensive mistake. Set the rate on the line once the invoice is drafted.</p>
<?php endif; ?>
