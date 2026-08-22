<div class="crumbs"><a href="/">Home</a> › Billing › Waiting to be billed</div>
<?php billing_tabs('to-bill'); ?>
<div class="master-head">
  <div><h1>Work waiting to be billed</h1>
  <p class="sub" style="margin:2px 0 0">Every closed <?= e(Tl('job')) ?> not yet on an invoice, grouped by <?= e(Tl('client')) ?> and then by contract / project. Bill one <?= e(Tl('call')) ?>, a whole project, or a month of it — tick what belongs on the invoice and draft it.</p></div>
</div>

<?php // Month filter — the pool is scoped by the CLOSED month, so a monthly account
      // is "pick the month, then draft each project's invoice". ?>
<form method="get" action="/to-bill" class="panel" style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
  <label style="font-weight:600;margin:0">Month (by closed date)</label>
  <select class="form-control" name="month" onchange="this.form.submit()" style="max-width:220px">
    <option value="">All unbilled work</option>
    <?php foreach ($months as $m): ?>
      <option value="<?= e($m) ?>" <?= $month === $m ? 'selected' : '' ?>><?= e(date('F Y', strtotime($m . '-01'))) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($month !== ''): ?><a class="btn small secondary" href="/to-bill">Clear</a><?php endif; ?>
  <noscript><button class="btn small" type="submit">Apply</button></noscript>
</form>

<?php if (!$groups): ?>
  <div class="panel" style="margin-top:14px">
    <p style="margin:0"><?= $month !== '' ? 'Nothing closed in ' . e(date('F Y', strtotime($month . '-01'))) . ' is waiting to be billed.' : 'Nothing is waiting. Every closed ' . e(Tl('job')) . ' is on an invoice.' ?></p>
    <p class="muted" style="font-size:13px;margin:8px 0 0">Work only appears here once it is <b>closed</b> — an open <?= e(Tl('job')) ?> is not finished work.</p>
  </div>
<?php else: ?>
  <?php $grand = 0; $projCount = 0; foreach ($groups as $g) { $grand += $g['value']; $projCount += count($g['projects']); } ?>
  <div class="qcards" style="margin-top:14px">
    <div class="qcard tone-warn"><div class="qic">⧗</div><div class="qn"><?= count($groups) ?></div><div class="ql"><?= e(Tlp('client')) ?> with unbilled work</div></div>
    <div class="qcard tone-info"><div class="qic">▤</div><div class="qn"><?= (int)$projCount ?></div><div class="ql">Projects / contracts</div></div>
    <div class="qcard tone-bad"><div class="qic">Σ</div><div class="qn" style="font-size:20px"><?= fmoney_short($grand) ?></div><div class="ql">Not yet invoiced<?= $month !== '' ? ' · ' . e(date('M Y', strtotime($month . '-01'))) : '' ?></div></div>
  </div>

  <?php foreach ($groups as $cid => $g): $multi = count($g['projects']) > 1; ?>
    <!-- One form per client, so a combined invoice can span the client's projects. -->
    <form method="post" action="/invoice-new" class="panel" style="margin-top:16px;padding:0;overflow:hidden">
      <input type="hidden" name="partner_id" value="<?= (int)$cid ?>">
      <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <b style="font-size:15px"><?= e($g['name']) ?></b>
        <span class="muted" style="font-size:12.5px"><?= count($g['projects']) ?> project<?= $multi?'s':'' ?> · <?= e(fmoney($g['value'])) ?></span>
        <span style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn small secondary" href="/ledger?id=<?= (int)$cid ?>">Ledger</a>
          <?php if ($canIssue && $cid): ?>
            <button class="btn small" type="submit" name="only" value="">
              <?= $multi ? 'Draft combined invoice (all projects' . ($month !== '' ? ', ' . e(date('M Y', strtotime($month . '-01'))) : '') . ')' : 'Draft invoice for the ticked work' ?>
            </button>
          <?php endif; ?>
        </span>
      </div>

      <?php foreach ($g['projects'] as $pk => $pj):
        $cn = $pj['contract']; ?>
        <div style="border-bottom:1px solid var(--line)">
          <div style="padding:10px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:var(--surface)">
            <span style="font-weight:600;font-size:13px">
              <?= $cn !== '' ? '▤ Contract ' . e($cn) : '<span class="muted">Direct ' . e(Tlp('call')) . ' (no contract)</span>' ?>
            </span>
            <span class="muted" style="font-size:12px"><?= count($pj['rows']) ?> <?= e(count($pj['rows'])==1 ? Tl('job') : Tlp('job')) ?> · <?= e(fmoney($pj['value'])) ?></span>
            <?php if ($cn !== ''): ?><a class="muted" style="font-size:12px" href="/calls?contract=<?= e(urlencode($cn)) ?>">all <?= e(Tlp('call')) ?> →</a><?php endif; ?>
            <?php if ($canIssue && $cid && $multi && $cn !== ''): ?>
              <button class="btn small secondary" type="submit" name="only" value="<?= e($cn) ?>" style="margin-left:auto">Draft this project only</button>
            <?php endif; ?>
          </div>
          <div class="dt-scroll">
            <table class="dt">
              <caption class="sr-only">Unbilled work for <?= e($g['name']) ?><?= $cn !== '' ? ', contract ' . e($cn) : '' ?></caption>
              <thead><tr>
                <th scope="col" style="width:34px"><span class="sr-only">Include</span></th>
                <th scope="col"><?= e(TH('job')) ?></th><th scope="col"><?= e(T('call')) ?></th><th scope="col">Closed</th><th scope="col" class="num">Value</th>
              </tr></thead>
              <tbody>
              <?php foreach ($pj['rows'] as $r): $val = (float)($r['billable_value'] ?: $r['invoice_value'] ?: 0); ?>
                <tr>
                  <td><label class="sr-only" for="jb-<?= (int)$r['id'] ?>">Include <?= e($r['job_code']) ?></label>
                      <input type="checkbox" id="jb-<?= (int)$r['id'] ?>" name="jobs[]" value="<?= (int)$r['id'] ?>" checked></td>
                  <td><a href="/job?id=<?= (int)$r['id'] ?>"><?= e($r['job_code']) ?></a></td>
                  <td><?= !empty($r['call_id']) ? '<a href="/call?id=' . (int)$r['call_id'] . '">' . e($r['call_code'] ?: ('#' . (int)$r['call_id'])) . '</a>' : '<span class="muted">—</span>' ?></td>
                  <td><?= $r['closed_at'] ? e(fdate(substr((string)$r['closed_at'],0,10))) : '<span class="muted">—</span>' ?></td>
                  <td class="num"><?= $val ? e(fmoney($val)) : '<span class="pill p-warn">no value</span>' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </form>
  <?php endforeach; ?>
  <p class="muted" style="margin-top:12px;font-size:12.5px">A client with more than one project gets <b>one combined monthly invoice</b> — its lines are grouped by contract on the bill — or you can draft a single project on its own. Untick a <?= e(Tl('job')) ?> to hold it back, or bill a single <?= e(Tl('call')) ?> by ticking only its work. A <?= e(Tl('job')) ?> with no value still shows — set the rate on the line once the invoice is drafted.</p>
<?php endif; ?>
