<?php
  $stPill = ['DRAFT'=>'p-mut','PENDING_APPROVAL'=>'p-warn','APPROVED'=>'p-info','SENT'=>'p-info',
             'ACCEPTED'=>'p-ok','LOST'=>'p-bad','REJECTED'=>'p-bad','EXPIRED'=>'p-mut'];
  $tabs = ['all'=>'All','open'=>'Open','pending'=>'Pending','closed'=>'Closed (won)','lost'=>'Lost'];
  $qs = function($v) { return '/quotes?' . http_build_query(array_merge($_GET, ['v'=>$v])); };
?>
<div class="master-head">
  <div><h1><?= e(T_REG('quote')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Quote → approval → send → follow-up → acceptance. <?= count($rows) ?> shown.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/quotes?<?= e(http_build_query(array_merge($_GET, ['export'=>'1']))) ?>">⬇ Export</a>
    <?php if (can('crm.quote.approve') || is_master()): ?><a class="btn secondary" href="/approval-rules?module=quote">Approval rules</a><?php endif; ?>
    <?php if (can('crm.template.manage') || is_master()): ?><a class="btn secondary" href="/templates?kind=quote">Templates</a><?php endif; ?>
    <?php if (can('crm.quote.create') || can('mod.quotes.edit')): ?><a class="btn secondary" href="/quote-external">+ External</a><?php endif; ?>
    <?php if (can('crm.quote.create') || can('mod.quotes.edit')): ?><a class="btn" href="/quote-new">+ New <?= e(Tl('quote')) ?></a><?php endif; ?>
  </div>
</div>

<?php // §3 — new duplicates are refused at entry and by a unique index. A database
      // that already held duplicates before that rule existed cannot build the
      // index, so the ones already there are named here instead of being hidden.
      $dupQ = ops_all("SELECT quote_no, rev, COUNT(*) n FROM quotations GROUP BY quote_no, rev HAVING COUNT(*)>1");
      $dupC = ops_all("SELECT contract_number, COUNT(*) n FROM partner_contracts WHERE contract_number<>'' GROUP BY contract_number HAVING COUNT(*)>1");
?>
<?php if ($dupQ || $dupC): ?>
<div class="panel" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
  <b style="color:var(--warn)">⚠ Numbers used more than once</b>
  <div class="muted" style="margin-top:4px">
    New duplicates are now refused, but these were recorded before that rule existed. Until they are merged or
    renumbered, anything counted against them — revenue, contract expiry, quantity used — is reading more than one record.
  </div>
  <div style="margin-top:6px;font-size:13px">
    <?php foreach ($dupQ as $d): ?>
      <span class="pill p-warn"><?= e(Tl('quote')) ?> <?= e($d['quote_no']) ?><?= (int)$d['rev'] ? ' R' . (int)$d['rev'] : '' ?> × <?= (int)$d['n'] ?></span>
    <?php endforeach; ?>
    <?php foreach ($dupC as $d): ?>
      <span class="pill p-warn">contract <?= e($d['contract_number']) ?> × <?= (int)$d['n'] ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php // Each card filters the register to its own view, so the number is a way in
      // rather than a read-out. Won and lost carry a tone rail. ?>
<div class="kpi-row">
  <a class="kpi tone-info" href="<?= e($qs('open')) ?>"><span class="kic">📝</span>
    <span class="k-lab">Open</span><span class="k-val"><?= (int)$counts['open'] ?></span><span class="k-sub">in the funnel</span></a>
  <a class="kpi tone-warn" href="<?= e($qs('pending')) ?>"><span class="kic">◷</span>
    <span class="k-lab">Pending</span><span class="k-val"><?= (int)$counts['pending'] ?></span><span class="k-sub">approval / reply awaited</span></a>
  <a class="kpi tone-ok" href="<?= e($qs('closed')) ?>"><span class="kic">🏆</span>
    <span class="k-lab">Closed (won)</span><span class="k-val"><?= (int)$counts['closed'] ?></span><span class="k-sub">accepted</span></a>
  <a class="kpi tone-bad" href="<?= e($qs('lost')) ?>"><span class="kic">✕</span>
    <span class="k-lab">Lost</span><span class="k-val"><?= (int)$counts['lost'] ?></span><span class="k-sub">regretted / expired</span></a>
</div>

<div class="chip-row" style="margin:10px 0">
  <?php foreach ($tabs as $k=>$lbl): ?>
    <a class="ct" href="<?= e($qs($k)) ?>" style="<?= $view===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
  <form method="get" action="/quotes" style="display:inline-flex;gap:6px;margin-left:auto">
    <input type="hidden" name="v" value="<?= e($view) ?>">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search no./subject/client" style="min-width:200px">
    <button class="btn small" type="submit">Search</button>
  </form>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Quote</th><th>Client</th><th>Subject</th><th><?= e(T('sbu')) ?></th><th class="num">Total</th><th>Contract</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="/quote?id=<?= (int)$r['id'] ?>"><b><?= e(quote_label($r)) ?></b></a></td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><?= e($r['subject'] ?: '—') ?></td>
      <td><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?: '—') ?></td>
      <td class="num"><?= (float)$r['total_amount']>0 ? cur_sym().number_format((float)$r['total_amount'],0) : '—' ?></td>
      <?php // The contract number, from whichever end it was settled — registered
            // by accounts on the order, or recorded on the client's Contracts tab.
            // A won order without one is work nobody can invoice. ?>
      <td><?php $cn = trim((string)($r['contract_number'] ?? ''));
        if ($cn !== '') { echo e($cn); }
        elseif (in_array($r['status'], ['ACCEPTED','APPROVED'], true)) { echo '<span class="pill p-warn">pending</span>'; }
        else { echo '<span class="muted">—</span>'; } ?></td>
      <td><span class="pill <?= $stPill[$r['status']] ?? 'p-mut' ?>"><?= e(lk_options_or('quote_status', QUOTE_STATUS)[$r['status']] ?? $r['status']) ?></span></td>
      <td class="num"><a class="btn small secondary" href="/quote?id=<?= (int)$r['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;padding:24px" class="muted">No <?= e(Tlp('quote')) ?> in this view.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
