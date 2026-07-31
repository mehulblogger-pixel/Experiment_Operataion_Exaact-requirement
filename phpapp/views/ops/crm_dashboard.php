<?php $h = $head; $windows = [30=>'30 days', 90=>'90 days', 180=>'6 months', 365=>'12 months', 730=>'2 years']; ?>
<div class="crumbs"><a href="/">Home</a> › Sales dashboard</div>
<div class="master-head">
  <div><h1>Sales dashboard</h1>
  <p class="sub" style="margin:2px 0 0">How selling is going — the funnel, where deals stop moving, and why they are lost.
    <?php if ($industryLabel): ?>Built for <b><?= e($industryLabel) ?></b>.<?php endif; ?>
    <?php if ($canSetIndustry): ?><a href="/industry"><?= $industry ? 'Change the industry template' : 'Set your industry' ?></a><?php endif; ?>
  </p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($funnel): ?><a class="btn secondary" href="/crm-dashboard?<?= e(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">⬇ Funnel CSV</a><?php endif; ?>
    <a class="btn secondary" href="/opportunities">Pipeline board</a>
  </div>
</div>

<div class="panel" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
  <span class="muted" style="font-size:12.5px">Window</span>
  <?php foreach ($windows as $d => $lbl): ?>
    <a class="btn small<?= $days===$d?'':' secondary' ?>" href="/crm-dashboard?<?= e(http_build_query(array_merge($_GET,['days'=>$d]))) ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
  <?php if (count($pipelines) > 1): ?>
    <span style="margin-left:auto;display:flex;gap:6px;align-items:center">
      <label class="sr-only" for="pipe">Pipeline</label>
      <select id="pipe" onchange="location=this.value">
        <?php foreach ($pipelines as $pl): ?>
          <option value="/crm-dashboard?<?= e(http_build_query(array_merge($_GET,['pipeline'=>$pl['id']]))) ?>" <?= (int)$pl['id']===(int)($funnel['pipeline'] ?? 0)?'selected':'' ?>><?= e($pl['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </span>
  <?php endif; ?>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-info"><div class="qic">◎</div><div class="qn"><?= (int)$h['open'] ?></div><div class="ql">Open deals</div></div>
  <div class="qcard tone-ok"><div class="qic">Σ</div><div class="qn" style="font-size:20px"><?= fmoney_short($h['value']) ?></div><div class="ql">Pipeline value</div></div>
  <div class="qcard tone-warn"><div class="qic">⌀</div><div class="qn" style="font-size:20px"><?= fmoney_short($h['weighted']) ?></div><div class="ql">Weighted forecast</div></div>
  <div class="qcard"><div class="qic">%</div><div class="qn"><?= $h['rate'] === null ? '—' : (int)$h['rate'] . '%' ?></div><div class="ql">Win rate</div></div>
  <div class="qcard"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= $h['avg_deal'] ? fmoney_short($h['avg_deal']) : '—' ?></div><div class="ql">Average won deal</div></div>
  <div class="qcard"><div class="qic">◷</div><div class="qn"><?= $h['cycle_days'] === null ? '—' : (int)$h['cycle_days'] ?></div><div class="ql">Days to win, average</div></div>
  <?php // Effort, in man-days: what winning costs, and what the losses cost. ?>
  <?php $ef = $effort ?? []; ?>
  <div class="qcard"><div class="qic">⏱</div><div class="qn"><?= ($ef['avg_win_md'] ?? null) === null ? '—' : number_format($ef['avg_win_md'], 1) ?></div><div class="ql">Man-days to win, average</div></div>
  <div class="qcard <?= !empty($ef['lost_timed']) && ($ef['lost_md'] ?? 0) >= 1 ? 'tone-bad' : '' ?>"><div class="qic">⌛</div><div class="qn"><?= empty($ef['lost_timed']) ? '—' : number_format($ef['lost_md'], 1) ?></div><div class="ql">Man-days on deals lost<?= !empty($ef['lost_n']) ? ' · ' . (int)$ef['lost_n'] . ' deal' . ((int)$ef['lost_n'] === 1 ? '' : 's') : '' ?></div></div>
  <div class="qcard <?= $h['stalled'] ? 'tone-bad' : '' ?>"><div class="qic">!</div><div class="qn"><?= (int)$h['stalled'] ?></div><div class="ql">Stopped moving</div></div>
  <div class="qcard <?= $h['closing_soon'] ? 'tone-warn' : '' ?>"><div class="qic">▸</div><div class="qn"><?= (int)$h['closing_soon'] ?></div><div class="ql">Due to close in 30 days</div></div>
</div>

<?php // ---- The funnel ---------------------------------------------------- ?>
<?php if (!$funnel): ?>
  <div class="panel" style="margin-top:16px">
    <p style="margin:0"><b>No funnel yet.</b> <?php if ($canSetIndustry): ?><a href="/industry">Pick your industry</a> and one is built for you — stages, probabilities, service levels, sources and loss reasons.<?php else: ?>Ask an administrator to set the industry template.<?php endif; ?></p>
  </div>
<?php else: ?>
  <div class="panel" style="margin-top:16px">
    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
      <h3 style="margin:0">The funnel</h3>
      <span class="muted" style="font-size:12.5px"><?= (int)$funnel['entered'] ?> deals entered in the window<?= $funnel['overall'] !== null ? ' · ' . (int)$funnel['overall'] . '% went on to be won' : '' ?></span>
    </div>
    <p class="muted" style="font-size:12.5px;margin:6px 0 14px">
      The bar is what is sitting in that stage <b>now</b>. The percentage beside it is the real conversion from the stage before —
      worked out from the stage history (of everything that ever reached the previous stage, how much ever reached this one),
      not by dividing one bar by another, which compares two snapshots and means nothing when stages move at different speeds.
    </p>

    <?php $maxN = 1; foreach ($funnel['stages'] as $s) $maxN = max($maxN, (int)$s['now']); ?>
    <?php foreach ($funnel['stages'] as $s): $st = $s['stage']; $pct = (int)round($s['now'] * 100 / $maxN); ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap">
          <b style="font-size:13.5px;min-width:170px"><?= e($st['name']) ?></b>
          <span class="muted" style="font-size:12px"><?= (int)$st['probability'] ?>%</span>
          <span style="font-size:13px"><b><?= (int)$s['now'] ?></b> here<?= $s['value'] ? ' · ' . e(fmoney($s['value'])) : '' ?></span>
          <?php if ($s['conv_from_prev'] !== null): ?>
            <span class="pill <?= $s['conv_from_prev'] >= 50 ? 'p-ok' : ($s['conv_from_prev'] >= 25 ? 'p-warn' : 'p-bad') ?>" style="font-size:11px">
              <?= (int)$s['conv_from_prev'] ?>% from the stage before</span>
          <?php elseif ($s['reached'] === 0): ?>
            <span class="pill p-mut" style="font-size:11px">nobody has reached this stage yet</span>
          <?php endif; ?>
          <span class="muted" style="margin-left:auto;font-size:12px"><?= (int)$s['reached'] ?> ever reached it</span>
        </div>
        <div style="background:var(--soft);border-radius:6px;height:16px;margin-top:4px;overflow:hidden">
          <div style="height:100%;width:<?= max($s['now'] ? 3 : 0, $pct) ?>%;background:<?= $st['kind']==='WON'?'var(--green,#16a34a)':($st['kind']==='LOST'?'var(--red,#dc2626)':'var(--brand)') ?>"></div>
        </div>
        <?php if ($s['rows']): ?>
          <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:5px">
            <?php foreach ($s['rows'] as $r): ?>
              <a class="pill p-mut" style="font-size:11px" href="/opportunity?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?><?= $r['value'] ? ' · ' . fmoney_short($r['value']) : '' ?></a>
            <?php endforeach; ?>
            <?php if ($s['now'] > count($s['rows'])): ?><span class="muted" style="font-size:11px">+<?= $s['now'] - count($s['rows']) ?> more</span><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div>
    <?php // ---- Top of funnel ------------------------------------------- ?>
    <div class="panel" style="margin-bottom:14px">
      <h3 style="margin-top:0">From first contact to won</h3>
      <div class="kv-grid">
        <div><span class="k">Leads created</span><span><?= (int)$top['leads'] ?></span></div>
        <div><span class="k">Became opportunities</span><span><?= (int)$top['to_opp'] ?><?= $top['lead_to_opp'] !== null ? ' <span class="muted">(' . (int)$top['lead_to_opp'] . '%)</span>' : '' ?></span></div>
        <div><span class="k">Quotations raised</span><span><?= (int)$top['quotes'] ?></span></div>
        <div><span class="k">Deals won</span><span><b><?= (int)$top['won'] ?></b></span></div>
      </div>
      <p class="muted" style="font-size:12.5px;margin:10px 0 0">Quotations are counted separately from deals on purpose — one deal often carries three, and counting documents would inflate every figure above.</p>
    </div>

    <?php // ---- Stuck ---------------------------------------------------- ?>
    <?php if ($stuck): ?>
      <div class="panel" style="margin-bottom:14px;border-left:3px solid var(--bad)">
        <h3 style="margin-top:0">Deals that have stopped moving</h3>
        <p class="muted" style="font-size:12.5px;margin:0 0 10px">Past the days their stage allows. The most useful list on this screen.</p>
        <table class="dt">
          <caption class="sr-only">Stalled deals</caption>
          <thead><tr><th scope="col">Deal</th><th scope="col">Stage</th><th scope="col">Owner</th><th scope="col" class="num">Days</th><th scope="col" class="num">Value</th></tr></thead>
          <tbody>
          <?php foreach ($stuck as $o): ?>
            <tr><td><a href="/opportunity?id=<?= (int)$o['id'] ?>"><?= e($o['name']) ?></a>
                    <br><span class="muted" style="font-size:12px"><?= e($o['client_name'] ?: $o['partner_name']) ?></span></td>
                <td><?= e($o['stage_name'] ?: '—') ?></td>
                <td><?= e($o['owner_name'] ?: '—') ?></td>
                <td class="num"><span class="pill p-bad"><?= opp_days_in_stage($o) ?></span></td>
                <td class="num"><?= e(fmoney($o['value'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php // ---- Losses --------------------------------------------------- ?>
    <?php if ($losses): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Why we lose</h3>
        <table class="dt">
          <caption class="sr-only">Loss reasons</caption>
          <thead><tr><th scope="col">Reason</th><th scope="col" class="num">Deals</th><th scope="col" class="num">Value</th></tr></thead>
          <tbody>
          <?php foreach ($losses as $l): ?>
            <tr><td><?= e($l['reason']) ?></td><td class="num"><?= (int)$l['n'] ?></td><td class="num"><?= e(fmoney($l['value'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="muted" style="font-size:12.5px;margin:10px 0 0">Won <?= (int)$h['won'] ?> (<?= e(fmoney($h['won_value'])) ?>) against lost <?= (int)$h['lost'] ?> in this window.</p>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <?php // ---- Owners --------------------------------------------------- ?>
    <?php if ($owners): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Who is selling</h3>
        <table class="dt">
          <caption class="sr-only">By owner</caption>
          <thead><tr><th scope="col">Owner</th><th scope="col" class="num">Open</th><th scope="col" class="num">Pipeline</th><th scope="col" class="num">Won</th><th scope="col" class="num">Lost</th></tr></thead>
          <tbody>
          <?php foreach ($owners as $o): ?>
            <tr><td><?= e($o['owner']) ?></td>
                <td class="num"><?= (int)$o['open_n'] ?></td>
                <td class="num"><?= e(fmoney($o['open_value'])) ?></td>
                <td class="num"><?= (int)$o['won_n'] ?><?= (float)$o['won_value'] ? '<br><span class="muted" style="font-size:11.5px">' . e(fmoney($o['won_value'])) . '</span>' : '' ?></td>
                <td class="num"><?= (int)$o['lost_n'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="muted" style="font-size:12.5px;margin:10px 0 0">A big pipeline with no wins is a conversation, not a scoreboard.</p>
      </div>
    <?php endif; ?>

    <?php // ---- Activity -------------------------------------------------- ?>
    <?php if ($activity): ?>
      <div class="panel">
        <div style="display:flex;align-items:baseline;gap:10px">
          <h3 style="margin:0">Effort behind it</h3>
          <a style="margin-left:auto;font-size:12.5px" href="/activities?manual=1">All of it →</a>
        </div>
        <p class="muted" style="font-size:12.5px;margin:6px 0 10px">Calls, meetings and visits somebody typed in, last <?= (int)$activity['days'] ?> days. A funnel with no effort behind it is a wish list.</p>
        <div class="kv-grid">
          <div><span class="k">Recorded by a person</span><span><b><?= (int)$activity['total'] ?></b></span></div>
          <?php foreach ($activity['rows'] as $r): ?>
            <div><span class="k"><?= e(ACT_KINDS[$r['kind']] ?? $r['kind']) ?></span><span><?= (int)$r['n'] ?></span></div>
          <?php endforeach; ?>
        </div>
        <?php if ($activity['silent']): ?>
          <p style="margin:12px 0 0"><a href="/activities"><b><?= (int)$activity['silent'] ?></b> customers</a> have had nothing at all for 30 days.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
