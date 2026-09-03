<?php
  $name = $p['display_name'] ?: $p['legal_name'];
  $t = $touch;
  // How worried to be about silence. Not a guess dressed as science — three
  // plain bands, and the number of days is always shown beside them.
  $silence = $t['days'] === null ? ['p-mut', 'never'] :
             ($t['days'] <= 30 ? ['p-ok', $t['days'] . ' days ago'] :
             ($t['days'] <= 90 ? ['p-warn', $t['days'] . ' days ago'] : ['p-bad', $t['days'] . ' days ago']));
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/clients">Customers</a> › <?= e($name) ?></div>
<div class="master-head">
  <div><h1><?= e($name) ?></h1>
    <p class="sub" style="margin:2px 0 0">
      <?= e($p['code']) ?>
      <span class="pill <?= $p['status']==='ACTIVE'?'p-ok':'p-mut' ?>"><?= e($p['status']) ?></span>
      <?= $p['gstin'] ? ' · GSTIN ' . e($p['gstin']) : '' ?>
      <?= $p['state'] ? ' · ' . e($p['state']) : '' ?>
      · last contact <span class="pill <?= $silence[0] ?>"><?= e($silence[1]) ?></span>
    </p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php // Field-finding #9/#10 — add in place, without leaving this screen. The popup opens the existing
          // form; on save it closes and this page refreshes to show the new record. ?>
    <?php if (function_exists('can') && (can('ops.call.create') || is_master())): ?>
      <a class="btn" href="/call-new?client_id=<?= (int)$p['id'] ?>" data-embed="/call-new?client_id=<?= (int)$p['id'] ?>" data-embed-title="Raise inspection <?= e(Tl('call')) ?>">➕ Raise inspection <?= e(Tl('call')) ?></a>
    <?php endif; ?>
    <?php if (!empty($canWrite)): ?>
      <a class="btn secondary" href="/partner-edit?id=<?= (int)$p['id'] ?>" data-embed="/partner-edit?id=<?= (int)$p['id'] ?>" data-embed-title="Edit <?= e(Tl('client')) ?> &amp; addresses">✎ Edit / add address</a>
    <?php endif; ?>
    <a class="btn secondary" href="/partner?id=<?= (int)$p['id'] ?>">Master record</a>
    <?php if ($money !== null): ?><a class="btn secondary" href="/ledger?id=<?= (int)$p['id'] ?>">Ledger</a><?php endif; ?>
    <a class="btn secondary" href="/customer?id=<?= (int)$p['id'] ?>&amp;export=csv">⬇ Timeline CSV</a>
  </div>
</div>

<?php // ---- The headline row: what you need before you ring them ------------ ?>
<div class="qcards" style="margin-top:16px">
  <?php if ($money !== null): ?>
    <a class="qcard <?= $money['outstanding'] > 0 ? ($money['overdue'] > 0 ? 'tone-bad' : 'tone-info') : 'tone-ok' ?>" href="/ledger?id=<?= (int)$p['id'] ?>">
      <div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney_short($money['outstanding']) ?></div>
      <div class="ql">Outstanding<?= $money['overdue'] > 0 ? ' · ' . fmoney_short($money['overdue']) . ' overdue' : '' ?></div>
    </a>
    <?php if ($money['on_account'] > 0.01): ?>
      <div class="qcard tone-warn"><div class="qic">⇄</div><div class="qn" style="font-size:20px"><?= fmoney_short($money['on_account']) ?></div>
        <div class="ql">Received, not yet matched</div></div>
    <?php endif; ?>
  <?php endif; ?>
  <?php // Revamp P4 — unbilled operational work captured as billable events (not yet invoiced).
    $bill = $billable ?? null;
    if ($bill !== null && (($bill['pending'] ?? 0) + ($bill['approved'] ?? 0)) > 0): ?>
    <a class="qcard tone-warn" href="/billable-events?party=<?= (int)$p['id'] ?>">
      <div class="qic">🧾</div><div class="qn" style="font-size:20px"><?= fmoney_short($bill['unbilled_amt']) ?></div>
      <div class="ql">Unbilled work<?= ($bill['approved'] ?? 0) ? ' · ' . (int)$bill['approved'] . ' approved' : '' ?></div>
    </a>
  <?php endif; ?>
  <?php if ($pipeline !== null): ?>
    <a class="qcard tone-info" href="/opportunities?v=list&amp;q=<?= e(urlencode($name)) ?>">
      <div class="qic">💡</div><div class="qn"><?= count($pipeline['open']) ?></div>
      <div class="ql">Open deals<?= $pipeline['weighted'] ? ' · ' . fmoney_short($pipeline['weighted']) . ' weighted' : '' ?></div>
    </a>
  <?php endif; ?>
  <?php if ($work !== null): ?>
    <div class="qcard <?= $work['open_jobs'] ? 'tone-info' : '' ?>"><div class="qic">🗂</div>
      <div class="qn"><?= (int)$work['open_jobs'] ?></div><div class="ql">Work in flight</div></div>
    <?php if ($work['unbilled']): ?>
      <a class="qcard tone-bad" href="/to-bill?partner=<?= (int)$p['id'] ?>"><div class="qic">⧗</div>
        <div class="qn"><?= (int)$work['unbilled'] ?></div><div class="ql">Finished, never billed</div></a>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($quality['any']): ?>
    <div class="qcard <?= $quality['open'] ? 'tone-bad' : 'tone-warn' ?>"><div class="qic">!</div>
      <div class="qn"><?= (int)$quality['total'] ?></div>
      <div class="ql">Complaints, nonconformities, rejections<?= $quality['open'] ? ' · ' . (int)$quality['open'] . ' still open' : ' · all closed' ?></div></div>
  <?php endif; ?>
</div>

<?php // ---- Group / parent company -----------------------------------------
      // A customer can belong to a group. Setting the parent here is what makes
      // the quotation screen show the whole group's earlier offers together, so
      // you price a subsidiary knowing what the parent was quoted. ?>
<?php if (!empty($canWrite) && function_exists('geofence_editor')): ?>
<details class="panel" style="margin-top:16px">
  <summary style="cursor:pointer;font-weight:600">📍 Site location <span class="muted" style="font-weight:400">— for geofenced attendance</span>
    <?= ($p['site_lat'] ?? null) !== null && $p['site_lat'] !== '' ? '<span class="pill p-ok">set</span>' : '<span class="pill p-mut">not set</span>' ?></summary>
  <p class="sub" style="margin:10px 0">Where this party's site is. When geofencing is on, an inspector allocated here can only punch
    in/out within range of this point. Each job can override it with its exact site.</p>
  <?= geofence_editor('partner-geo', (int)$p['id'], ['lat'=>$p['site_lat'] ?? null, 'lon'=>$p['site_lon'] ?? null, 'rad'=>(int)($p['geofence_m'] ?? 0)]) ?>
</details>
<?php endif; ?>
<?php // Phase 3 §27 — this client's money as one time-ordered stream (quotes → invoices → receipts → credits). ?>
<?php if (function_exists('financial_events_render')) financial_events_render(['partner_id' => (int)$p['id']], 'Money timeline'); ?>

<?php $g = $group ?? ['parent'=>null,'subs'=>[],'opts'=>[]]; ?>
<?php if ($g['parent'] || $g['subs'] || !empty($canWrite)): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Group</h3>
  <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">
    <div style="min-width:220px">
      <div class="muted" style="font-size:12.5px">Parent company</div>
      <?php if ($g['parent']): ?>
        <a href="/customer?id=<?= (int)$g['parent']['id'] ?>"><b><?= e($g['parent']['display_name'] ?: $g['parent']['legal_name']) ?></b></a>
      <?php else: ?>
        <span class="muted">— none; this is a top-level company —</span>
      <?php endif; ?>
    </div>
    <div style="min-width:220px">
      <div class="muted" style="font-size:12.5px">Companies under it (<?= count($g['subs']) ?>)</div>
      <?php if ($g['subs']): ?>
        <?php foreach ($g['subs'] as $s): ?>
          <div><a href="/customer?id=<?= (int)$s['id'] ?>"><?= e($s['display_name'] ?: $s['legal_name']) ?></a></div>
        <?php endforeach; ?>
      <?php else: ?>
        <span class="muted">— none —</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($canWrite)): ?>
    <form method="post" action="/customer-parent" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <div class="ff" style="min-width:260px;margin:0"><label for="pp">Parent company</label>
        <select class="form-control searchable" id="pp" name="parent_id">
          <option value="">— none (top-level) —</option>
          <?php foreach ($g['opts'] as $o): ?>
            <option value="<?= (int)$o['id'] ?>" <?= (int)($p['parent_id'] ?? 0)===(int)$o['id']?'selected':'' ?>><?= e($o['display_name'] ?: $o['legal_name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <button class="btn small">Save group</button>
    </form>
    <div class="ff-help" style="margin-top:6px">Set the parent, and this customer and its group share one quotation history on the quote screen.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // Billing branch — attach this client to an office, so every invoice for them
      // is raised from the right branch and never lands on "No branch is set". ?>
<?php if (!empty($canWrite)): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Billing branch</h3>
  <p class="muted" style="font-size:12.5px;margin:0 0 10px">The office every new invoice for this client is raised from — so their invoices are never stuck on “No branch is set”. You can change it any time.</p>
  <form method="post" action="/customer" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="action" value="set_branch">
    <div class="ff" style="min-width:260px;margin:0"><label for="hb">Billing branch</label>
      <select class="form-control searchable" id="hb" name="home_branch_id">
        <option value="">— not set —</option>
        <?php foreach (ops_all("SELECT id, name FROM offices WHERE is_active=1 ORDER BY name") as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)($p['home_branch_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <button class="btn small">Save branch</button>
  </form>
</div>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div>
    <?php // ---- Money ---------------------------------------------------- ?>
    <?php if ($money !== null): ?>
      <div class="panel" style="margin-bottom:14px">
        <div style="display:flex;align-items:baseline;gap:10px">
          <h3 style="margin:0">Money</h3>
          <a style="margin-left:auto;font-size:12.5px" href="/ledger?id=<?= (int)$p['id'] ?>">Full ledger →</a>
        </div>
        <div class="kv-grid" style="margin-top:10px">
          <div><span class="k">Billed and open</span><span><?= e(fmoney($money['billed'])) ?></span></div>
          <div><span class="k">Settled</span><span><?= e(fmoney($money['settled'])) ?></span></div>
          <div><span class="k">Outstanding</span><span><b><?= e(fmoney($money['outstanding'])) ?></b></span></div>
          <?php if ($money['oldest_days'] !== null): ?>
            <div><span class="k">Oldest overdue</span><span><?= (int)$money['oldest_days'] ?> days</span></div>
          <?php endif; ?>
        </div>
        <?php if ($money['buckets']): ?>
          <?php // The same buckets and the same labels the receivables report
                // uses. Two ageing screens with two sets of bands is how an
                // argument about "60 days" starts. ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px">
            <?php foreach ((defined('AR_BUCKETS') ? AR_BUCKETS : []) as $b): ?>
              <?php $k = $b['key']; if (empty($money['buckets'][$k])) continue; ?>
              <span class="pill <?= $k === 'notdue' ? 'p-ok' : ($k === 'b90p' ? 'p-bad' : 'p-warn') ?>">
                <?= e($b['label']) ?>: <?= e(fmoney($money['buckets'][$k])) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($money['invoices']): ?>
          <table class="dt" style="margin-top:12px">
            <caption class="sr-only">Recent invoices</caption>
            <thead><tr><th scope="col">Invoice</th><th scope="col">Date</th><th scope="col" class="num">Total</th><th scope="col">Status</th></tr></thead>
            <tbody>
            <?php foreach ($money['invoices'] as $i): ?>
              <tr><td><a href="/invoice?id=<?= (int)$i['id'] ?>"><?= e($i['invoice_no'] ?: '#' . (int)$i['id']) ?></a></td>
                  <td><?= e(fdate($i['invoice_date'])) ?></td>
                  <td class="num"><?= e(fmoney($i['total'])) ?></td>
                  <td><span class="pill <?= $i['status']==='PAID'?'p-ok':($i['status']==='CANCELLED'?'p-mut':'p-warn') ?>"><?= e(INV_STATUS[$i['status']] ?? $i['status']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // ---- Pipeline ------------------------------------------------- ?>
    <?php if ($pipeline !== null && ($pipeline['open'] || $pipeline['recent'] || $pipeline['leads'])): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">What we are selling them</h3>
        <?php if ($pipeline['open']): ?>
          <table class="dt">
            <caption class="sr-only">Open opportunities</caption>
            <thead><tr><th scope="col">Deal</th><th scope="col">Stage</th><th scope="col" class="num">Estimate</th><th scope="col" class="num">Weighted</th></tr></thead>
            <tbody>
            <?php foreach ($pipeline['open'] as $o): ?>
              <tr><td><a href="/opportunity?id=<?= (int)$o['id'] ?>"><?= e($o['name']) ?></a>
                      <br><span class="muted" style="font-size:12px"><?= e($o['ref']) ?></span></td>
                  <td><?= e($o['stage_name'] ?: '—') ?></td>
                  <td class="num"><?= e(fmoney($o['value'])) ?></td>
                  <td class="num"><?= e(fmoney(opp_weighted($o))) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted" style="margin:0 0 10px">Nothing open. <?= $money !== null && $money['billed'] > 0 ? 'They are a paying customer with nothing in the pipeline — worth a call.' : '' ?></p>
        <?php endif; ?>
        <?php if ($pipeline['recent']): ?>
          <p class="muted" style="font-size:12.5px;margin:10px 0 4px">Recently closed</p>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php foreach ($pipeline['recent'] as $o): ?>
              <a class="pill <?= $o['status']==='WON'?'p-ok':'p-bad' ?>" href="/opportunity?id=<?= (int)$o['id'] ?>">
                <?= e($o['name']) ?> · <?= e(fmoney($o['value'])) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // ---- Quotations ----------------------------------------------- ?>
    <?php if ($quotes): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Quotations</h3>
        <table class="dt">
          <caption class="sr-only">Current quotations</caption>
          <thead><tr><th scope="col">Quotation</th><th scope="col">Subject</th><th scope="col" class="num">Value</th><th scope="col">Status</th></tr></thead>
          <tbody>
          <?php foreach ($quotes as $q): ?>
            <tr><td><a href="/quote?id=<?= (int)$q['id'] ?>"><?= e($q['quote_no']) ?><?= (int)$q['rev'] ? ' r' . (int)$q['rev'] : '' ?></a></td>
                <td><?= e(mb_substr((string)$q['subject'], 0, 50) ?: '—') ?></td>
                <td class="num"><?= e(fmoney($q['total_amount'])) ?></td>
                <td><span class="pill p-mut"><?= e($q['status']) ?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php // ---- Work ----------------------------------------------------- ?>
    <?php if ($work !== null && ($work['calls'] || $work['jobs'])): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Work</h3>
        <?php if ($work['calls']): ?>
          <p class="muted" style="font-size:12.5px;margin:0 0 6px">Orders <span class="muted">(<?= (int)$work['open_calls'] ?> open)</span></p>
          <table class="dt">
            <caption class="sr-only">Recent orders</caption>
            <thead><tr><th scope="col">Order</th><th scope="col">Received</th><th scope="col">Status</th></tr></thead>
            <tbody>
            <?php foreach ($work['calls'] as $c): ?>
              <tr><td><a href="/call?id=<?= (int)$c['id'] ?>"><?= e($c['call_code']) ?></a></td>
                  <td><?= $c['call_received_date'] ? e(fdate($c['call_received_date'])) : '—' ?></td>
                  <td><span class="pill p-mut"><?= e($c['status']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <?php if ($work['jobs']): ?>
          <p class="muted" style="font-size:12.5px;margin:12px 0 6px"><?= e(THP('job')) ?></p>
          <table class="dt">
            <caption class="sr-only">Recent <?= e(Tlp('job')) ?></caption>
            <thead><tr><th scope="col"><?= e(TH('job')) ?></th><th scope="col">Who</th><th scope="col">State</th></tr></thead>
            <tbody>
            <?php foreach ($work['jobs'] as $j): ?>
              <tr><td><a href="/job?id=<?= (int)$j['id'] ?>"><?= e($j['job_code']) ?></a></td>
                  <td><?= e($j['inspector_name'] ?: '—') ?></td>
                  <td><span class="pill <?= $j['closed_flag'] ? 'p-ok' : 'p-warn' ?>"><?= $j['closed_flag'] ? 'Closed' : 'Open' ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // ---- Quality --------------------------------------------------- ?>
    <?php if ($quality['any']): ?>
      <div class="panel" style="margin-bottom:14px;border-left:3px solid var(--warn)">
        <h3 style="margin-top:0">Where we have let them down
          <span class="muted" style="font-weight:400;font-size:13px">— <?= (int)$quality['total'] ?> in total<?= $quality['total'] > (count($quality['complaints']) + count($quality['ncrs'])) ? ', newest shown' : '' ?></span></h3>
        <?php if ($quality['rejections']): ?>
          <p style="margin:0 0 8px"><b><?= (int)$quality['rejections'] ?></b> report<?= $quality['rejections']==1?'':'s' ?> rejected by them.</p>
        <?php endif; ?>
        <?php foreach ($quality['complaints'] as $c): ?>
          <div style="padding:6px 0;border-bottom:1px solid var(--line)">
            <a href="/complaint?id=<?= (int)$c['id'] ?>"><b><?= e($c['ref']) ?></b></a>
            <span class="pill p-mut" style="font-size:11px"><?= $c['kind']==='APPEAL'?'Appeal':'Complaint' ?></span>
            <span class="pill <?= $c['status']==='CLOSED'?'p-ok':'p-warn' ?>" style="font-size:11px"><?= e($c['status']) ?></span>
            <div class="muted" style="font-size:12.5px"><?= e($c['subject']) ?></div>
          </div>
        <?php endforeach; ?>
        <?php foreach ($quality['ncrs'] as $n): ?>
          <div style="padding:6px 0;border-bottom:1px solid var(--line)">
            <a href="/ncr-item?id=<?= (int)$n['id'] ?>"><b><?= e($n['ref']) ?></b></a>
            <span class="pill <?= $n['severity']==='MAJOR'?'p-bad':'p-warn' ?>" style="font-size:11px"><?= e(ucfirst(strtolower($n['severity']))) ?></span>
            <div class="muted" style="font-size:12.5px"><?= e($n['title']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php // ---- Reports issued (Module 15) -------------------------------- ?>
    <?php if (isset($reports) && $reports !== null): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Reports issued
          <?php if (!empty($reports['rows'])): ?><span class="muted" style="font-weight:400;font-size:13px">— <?= (int)$reports['total'] ?> in total<?= $reports['total'] > count($reports['rows']) ? ', newest shown' : '' ?></span><?php endif; ?></h3>
        <?php if (!empty($reports['rows'])): ?>
          <table class="dt"><thead><tr><th>IRN</th><th>Format</th><th>Issued</th><th></th></tr></thead><tbody>
            <?php foreach ($reports['rows'] as $r): ?>
              <tr><td><b><?= e($r['irn'] ?: '—') ?></b></td>
                <td><?= e(function_exists('deliverable_options') ? (deliverable_options()[$r['type_code']] ?? $r['type_code']) : $r['type_code']) ?></td>
                <td class="muted"><?= e($r['issue_date'] ? substr($r['issue_date'], 0, 10) : '') ?></td>
                <td class="num"><a class="btn small secondary" href="/document?id=<?= (int)$r['id'] ?>">Open →</a></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        <?php else: ?>
          <p class="muted" style="margin:0">No reports issued to this <?= e(Tl('client')) ?> yet.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <?php // ---- Who to talk to -------------------------------------------- ?>
    <div class="panel" style="margin-bottom:14px">
      <h3 style="margin-top:0">Who to talk to</h3>
      <?php if (!$contacts): ?>
        <p class="muted" style="margin:0">Nobody recorded. <a href="/partner?id=<?= (int)$p['id'] ?>&amp;tab=contacts">Add a contact</a> — a customer with no named person is a customer nobody can chase.</p>
      <?php else: foreach ($contacts as $c): ?>
        <div style="padding:7px 0;border-bottom:1px solid var(--line)">
          <b><?= e($c['name']) ?></b><?= !empty($c['is_primary']) ? ' <span class="pill p-ok" style="font-size:10.5px">main</span>' : '' ?>
          <?php if ($c['designation']): ?><div class="muted" style="font-size:12.5px"><?= e($c['designation']) ?></div><?php endif; ?>
          <div style="font-size:12.5px">
            <?php if ($c['email']): ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php endif; ?>
            <?php if ($c['mobile']): ?> · <?= e($c['mobile']) ?><?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
      <?php // Module 15 — every site, not just the primary (the full list used to live
            // only on the master record). Compact; deep-links to the addresses tab. ?>
      <?php $c360Sites = $sites ?? ($address ? [$address] : []); if ($c360Sites): ?>
        <div style="margin-top:10px"><div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px">Sites (<?= count($c360Sites) ?>)</div>
          <?php foreach (array_slice($c360Sites, 0, 6) as $ad): ?>
            <p class="muted" style="font-size:12.5px;margin:0 0 6px">
              <?= !empty($ad['is_primary']) ? '<span class="pill p-ok" style="font-size:10px">main</span> ' : '' ?>
              <?= e(trim(($ad['label'] ?? '') . ' ' . ($ad['line1'] ?? '') . ' ' . ($ad['line2'] ?? ''))) ?><br>
              <?= e(trim(($ad['city'] ?? '') . ' ' . ($ad['state'] ?? '') . ' ' . ($ad['pincode'] ?? ''))) ?>
            </p>
          <?php endforeach; ?>
          <?php if (count($c360Sites) > 6): ?><a class="muted" style="font-size:12px" href="/partner?id=<?= (int)$p['id'] ?>&amp;tab=addresses">All <?= count($c360Sites) ?> sites →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php // ---- Satisfaction (Module 15) ---------------------------------- ?>
    <?php if (!empty($csat)): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Satisfaction</h3>
        <div style="display:flex;gap:18px;align-items:baseline">
          <div><div style="font-size:26px;font-weight:800;color:<?= $csat['latest'] <= (int)ceil($csat['scale']*0.5) ? 'var(--bad)' : 'var(--ok)' ?>"><?= (int)$csat['latest'] ?><span style="font-size:14px;color:var(--muted)">/<?= (int)$csat['scale'] ?></span></div>
            <div class="muted" style="font-size:11.5px">latest score</div></div>
          <div><div style="font-size:18px;font-weight:700"><?= e((string)$csat['avg']) ?></div><div class="muted" style="font-size:11.5px">avg of <?= (int)$csat['count'] ?></div></div>
          <?php if ($csat['recommend'] !== ''): ?><div><div style="font-size:14px;font-weight:700"><?= strtoupper($csat['recommend'])==='Y' ? '👍 would recommend' : '👎 would not' ?></div></div><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($contracts): ?>
      <div class="panel" style="margin-bottom:14px">
        <h3 style="margin-top:0">Contracts</h3>
        <?php $canRaiseCall = (function_exists('can') && can('ops.call.create')) || (function_exists('is_master') && is_master()); ?>
        <?php foreach ($contracts as $ct): $cos = (string)($ct['open_status'] ?? 'OPEN'); ?>
          <div style="padding:6px 0;border-bottom:1px solid var(--line);font-size:13px;display:flex;gap:10px;align-items:baseline;flex-wrap:wrap">
            <div style="flex:1;min-width:180px">
              <b><?= e($ct['contract_number']) ?></b>
              <?php if ($ct['end_date']): ?><span class="muted"> — to <?= e(fdate($ct['end_date'])) ?></span><?php endif; ?>
              <?php if ($ct['title']): ?><div class="muted" style="font-size:12.5px"><?= e($ct['title']) ?></div><?php endif; ?>
            </div>
            <?php // Raise inspection / deputation calls FROM the contract, any day. ?>
            <?php if ($canRaiseCall && strtoupper($cos) === 'OPEN'): ?>
              <a class="btn small" href="/call-new?contract_id=<?= (int)$ct['id'] ?>&contract=<?= e(urlencode((string)$ct['contract_number'])) ?>">▶ Raise <?= e(Tl('call')) ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php // ---- The timeline ---------------------------------------------- ?>
    <div class="panel">
      <div style="display:flex;align-items:baseline;gap:10px">
        <h3 style="margin:0">What has happened</h3>
        <a style="margin-left:auto;font-size:12.5px" href="/activities?partner=<?= (int)$p['id'] ?>">All of it →</a>
      </div>
      <?php if (!$timeline): ?>
        <p class="muted" style="margin:10px 0 0">Nothing recorded against this customer yet. The timeline fills itself as quotations go out, work is done and reports are issued — and anybody can add what was said on the phone.</p>
      <?php else: ?>
        <div style="margin-top:10px">
        <?php foreach ($timeline as $a): ?>
          <div style="padding:8px 0;border-bottom:1px solid var(--line)">
            <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
              <span class="muted" style="font-size:12px;min-width:78px"><?= e(fdate(substr((string)$a['occurred_at'],0,10))) ?></span>
              <span class="pill <?= $a['auto'] ? 'p-mut' : 'p-ok' ?>" style="font-size:10.5px"><?= e(ACT_KINDS[$a['kind']] ?? $a['kind']) ?></span>
              <span style="font-size:13px;flex:1"><?= e($a['subject']) ?></span>
            </div>
            <?php if (trim((string)$a['body']) !== ''): ?>
              <div class="muted" style="font-size:12.5px;margin:2px 0 0 86px;white-space:pre-wrap"><?= e(mb_substr((string)$a['body'], 0, 160)) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
