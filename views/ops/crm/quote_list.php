<?php
  $stPill = ['DRAFT'=>'p-mut','PENDING_APPROVAL'=>'p-warn','APPROVED'=>'p-info','SENT'=>'p-info','ACCEPTED'=>'p-ok','LOST'=>'p-bad','EXPIRED'=>'p-mut'];
  $tabs = ['all'=>'All','open'=>'Open','pending'=>'Pending','closed'=>'Closed (won)','lost'=>'Lost'];
  $qs = function($v) { return '/quotes?' . http_build_query(array_merge($_GET, ['v'=>$v])); };
?>
<div class="master-head">
  <div><h1><?= e(T_REG('quote')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Quote → approval → send → follow-up → acceptance. <?= count($rows) ?> shown.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if (can('crm.quote.approve') || is_master()): ?><a class="btn secondary" href="/quote-approval-rules">Approval rules</a><?php endif; ?>
    <?php if (can('crm.template.manage') || is_master()): ?><a class="btn secondary" href="/crm-templates">Templates</a><?php endif; ?>
    <?php if (can('crm.quote.create') || can('mod.quotes.edit')): ?><a class="btn" href="/quote-new">+ New <?= e(Tl('quote')) ?></a><?php endif; ?>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Open</div><div class="k-val"><?= (int)$counts['open'] ?></div><div class="k-sub">in the funnel</div></div>
  <div class="kpi"><div class="k-lab">Pending</div><div class="k-val"><?= (int)$counts['pending'] ?></div><div class="k-sub">approval / reply awaited</div></div>
  <div class="kpi"><div class="k-lab">Closed (won)</div><div class="k-val up"><?= (int)$counts['closed'] ?></div><div class="k-sub">accepted</div></div>
  <div class="kpi"><div class="k-lab">Lost</div><div class="k-val down"><?= (int)$counts['lost'] ?></div><div class="k-sub">regretted / expired</div></div>
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
    <thead><tr><th>Quote</th><th>Client</th><th>Subject</th><th>SBU</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="/quote?id=<?= (int)$r['id'] ?>"><b><?= e(quote_label($r)) ?></b></a></td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><?= e($r['subject'] ?: '—') ?></td>
      <td><?= e(lk_options_or('sbu', OPS_SBUS)[$r['sbu']] ?? $r['sbu'] ?: '—') ?></td>
      <td class="num"><?= (float)$r['total_amount']>0 ? cur_sym().number_format((float)$r['total_amount'],0) : '—' ?></td>
      <td><span class="pill <?= $stPill[$r['status']] ?? 'p-mut' ?>"><?= e(QUOTE_STATUS[$r['status']] ?? $r['status']) ?></span></td>
      <td class="num"><a class="btn small secondary" href="/quote?id=<?= (int)$r['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" style="text-align:center;padding:24px" class="muted">No <?= e(Tlp('quote')) ?> in this view.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
