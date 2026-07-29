<?php
// Four columns that are never blended: spent, in play, invoiced, collected.
// The distance between the last two is the finding.
$money = fn($v) => e(fmoney((float)$v));
$mult  = function ($v) {
    if ($v === null) return '<span class="muted">—</span>';
    $s = number_format((float)$v, 1) . '×';
    return (float)$v >= 1 ? '<b>' . e($s) . '</b>' : '<b style="color:var(--bad,#b42318)">' . e($s) . '</b>';
};
?>
<div class="crumbs"><a href="/">Home</a> › What the advertising actually earned</div>
<div class="master-head">
  <div><h1>What the advertising actually earned</h1>
  <p class="sub" style="margin:2px 0 0">Not conversion value from a pixel, and not closed-won from a text box. This follows campaign → lead → deal → order → invoice → <b>receipt</b>, and reports the four figures separately, because averaging them is how a campaign that brings in customers who never pay goes on being funded.</p></div>
  <?php if ($by): ?><a class="btn secondary" href="/ads-roi?export=csv<?= $from ? '&amp;from=' . e($from) : '' ?><?= $to ? '&amp;to=' . e($to) : '' ?>">⬇ CSV</a><?php endif; ?>
</div>

<form method="get" class="filter-bar" style="margin-top:12px">
  <label class="form-label" style="margin:0">Leads imported from</label>
  <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:170px">
  <label class="form-label" style="margin:0">to</label>
  <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:170px">
  <button class="btn small" type="submit">Apply</button>
  <?php if ($from || $to): ?><a class="btn small secondary" href="/ads-roi">Clear</a><?php endif; ?>
</form>

<?php if (!$by): ?>
  <div class="panel" style="margin-top:16px">
    <p style="margin:0"><b>Nothing to report yet.</b></p>
    <p class="muted" style="margin:6px 0 0;max-width:640px">
      <?php if (!$connected): ?>
        Ads Pro is not connected. <a href="/adspro">Connect it</a>, then bring the leads across — this screen fills itself
        from the leads and the spend it holds.
      <?php else: ?>
        Connected, but no leads or spend have been imported yet. Open <a href="/adspro">the connection</a> and press
        <b>Bring leads across</b>.
      <?php endif; ?>
    </p>
  </div>
<?php else: ?>

  <div class="qcards" style="margin-top:16px">
    <div class="qcard"><div class="qic">₹</div><div class="qn" style="font-size:19px"><?= $money($tot['spend']) ?></div><div class="ql">Spent on advertising</div></div>
    <div class="qcard"><div class="qic">↗</div><div class="qn" style="font-size:19px"><?= $money($tot['invoiced']) ?></div><div class="ql">Invoiced from those leads</div></div>
    <div class="qcard tone-ok"><div class="qic">✓</div><div class="qn" style="font-size:19px"><?= $money($tot['collected']) ?></div><div class="ql">Actually collected</div></div>
    <div class="qcard <?= $tot['uncollected'] > 0 ? 'tone-bad' : 'tone-ok' ?>"><div class="qic">!</div><div class="qn" style="font-size:19px"><?= $money($tot['uncollected']) ?></div><div class="ql">Invoiced but not received</div></div>
  </div>

  <div class="panel" style="margin-top:12px">
    <p style="margin:0;font-size:14px">
      <?php if (!empty($tot['nothing_closed'])): ?>
        <b><?= $money($tot['spend']) ?> spent, and nothing has closed from it yet.</b>
        That is not the same as a return of zero, so no return is shown. It becomes answerable the moment the first
        deal from these leads is won and billed
        <?php if ($tot['in_play'] > 0): ?> — there is <?= $money($tot['in_play']) ?> of pipeline open against them now<?php endif; ?>.
      <?php else: ?>
        Every rupee of advertising has brought back
        <?= $mult($tot['roi_cash']) ?> <b>in cash</b>
        <?php if ($tot['roi_inv'] !== null): ?>, and <?= $mult($tot['roi_inv']) ?> on paper<?php endif; ?>.
        <?php if ($tot['uncollected'] > 0): ?>
          The difference is <?= $money($tot['uncollected']) ?> that has been billed and not paid —
          <a href="/receivables">chase it</a> and the return on this spend rises without spending anything more.
        <?php endif; ?>
      <?php endif; ?>
    </p>
    <p class="muted" style="margin:8px 0 0;font-size:12.5px">
      <?= (int)$tot['leads'] ?> leads across <?= (int)$tot['campaigns'] ?> campaigns, <?= (int)$tot['won'] ?> won.
      <?= $tot['cpl'] !== null ? 'Cost per lead ' . $money($tot['cpl']) . ' — the figure every ad platform shows you, and the least useful one here.' : '' ?>
      Still open and not counted above: <?= $money($tot['in_play']) ?> of pipeline.
    </p>
  </div>

  <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only">Advertising return by campaign</caption>
        <thead><tr>
          <th scope="col">Campaign</th>
          <th scope="col" style="text-align:right">Spent</th>
          <th scope="col" style="text-align:right">Leads</th>
          <th scope="col" style="text-align:right">Won</th>
          <th scope="col" style="text-align:right">In play</th>
          <th scope="col" style="text-align:right">Invoiced</th>
          <th scope="col" style="text-align:right">Collected</th>
          <th scope="col" style="text-align:right">Per ₹ spent</th>
        </tr></thead>
        <tbody>
        <?php foreach ($by as $b): ?>
          <tr>
            <td>
              <b><?= e($b['name']) ?></b>
              <?php if ($b['platform']): ?><span class="pill"><?= e($b['platform']) ?></span><?php endif; ?>
              <?php if ($b['too_early']): ?>
                <div class="muted" style="font-size:12px;margin-top:2px">No leads from it yet — too early to judge, not a failure.</div>
              <?php endif; ?>
              <?php foreach ($b['breaks'] as $k => $n): if (!isset($breaks[$k])) continue; ?>
                <div style="font-size:12px;margin-top:2px">
                  <a href="<?= e($breaks[$k][1]) ?>"><?= e($breaks[$k][0]) ?></a>
                  <span class="muted">— <?= (int)$n ?></span>
                </div>
              <?php endforeach; ?>
            </td>
            <td style="text-align:right"><?= $b['spend'] > 0 ? $money($b['spend']) : '<span class="muted">—</span>' ?></td>
            <td style="text-align:right"><?= (int)$b['leads'] ?></td>
            <td style="text-align:right"><?= (int)$b['won'] ?></td>
            <td style="text-align:right"><?= $b['in_play'] > 0 ? '<span class="muted">' . $money($b['in_play']) . '</span>' : '<span class="muted">—</span>' ?></td>
            <td style="text-align:right"><?= $b['invoiced'] > 0 ? $money($b['invoiced']) : '<span class="muted">—</span>' ?></td>
            <td style="text-align:right">
              <?= $b['collected'] > 0 ? '<b>' . $money($b['collected']) . '</b>' : '<span class="muted">—</span>' ?>
              <?php if ($b['uncollected'] > 0): ?>
                <div class="muted" style="font-size:12px"><?= $money($b['uncollected']) ?> outstanding</div>
              <?php endif; ?>
            </td>
            <td style="text-align:right"><?= $mult($b['roi_cash']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel" style="margin-top:16px">
    <b style="font-size:13.5px">How to read this, and what it is not</b>
    <ul class="muted" style="margin:8px 0 0;padding-left:20px;font-size:12.5px;line-height:1.6">
      <li><b>Per ₹ spent is cash, not invoices.</b> A campaign at 8× on paper and 2× in cash is bringing you customers who do not pay. That is a reason to stop it, and no advertising dashboard will ever tell you, because none of them can see a bank statement.</li>
      <li><b>Nothing is split across touchpoints.</b> A lead came from one campaign and that campaign is credited with all of it. Fractional multi-touch attribution is a guess, and a guess does not belong in the same column as a receipt.</li>
      <li><b>“In play” is a forecast</b> and is kept out of every return figure on this screen. It is shown so a campaign that is working but has not closed yet is not mistaken for one that failed.</li>
      <li><b>A dash is not a zero.</b> A campaign with no leads yet shows its spend and a dash. Two weeks of spend in a trade with a three-month sales cycle has not failed.</li>
      <li><b>Breaks in the chain are named, not absorbed.</b> Revenue that cannot be traced from campaign to receipt is reported as the break it is, with a link to the screen that closes it — quietly counting it would flatter the return.</li>
    </ul>
  </div>
<?php endif; ?>
