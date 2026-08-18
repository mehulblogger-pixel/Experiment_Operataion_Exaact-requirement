<?php // Put a client on hold / block, or clear it — a pre-order control. ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/clients"><?= e(T_REG('client')) ?></a> › Client holds</div>
<div class="master-head"><div><h1>Client holds &amp; blocks</h1>
  <p class="sub" style="margin:2px 0 0">A pre-order control: put a client <b>on hold</b> (a warning shows when raising a quote or call) or <b>block</b> them (raising work is stopped for non-managers). Always with a reason.</p></div></div>

<form method="post" action="/client-holds" class="panel" style="max-width:720px">
  <h3 class="tab-sub" style="margin-top:0">Set or clear a hold</h3>
  <div class="form-grid">
    <div class="ff"><label>Client *</label>
      <select class="form-control searchable" name="client_id" required>
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nm']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Status</label>
      <select class="form-control" name="hold_status">
        <?php foreach (PARTNER_HOLD_STATES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <div class="ff"><label>Reason <span class="muted">(required for hold / block; shown to whoever raises the quote or call)</span></label>
    <input class="form-control" name="hold_reason" maxlength="400" placeholder="e.g. Payments overdue beyond 90 days"></div>
  <div style="margin-top:12px"><button class="btn" type="submit">Save</button></div>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th><?= e(TH('client')) ?></th><th>Status</th><th>Reason</th><th></th></tr></thead>
    <tbody>
      <?php if (!$held): ?>
        <tr><td colspan="4" class="muted" style="padding:14px">No clients are on hold or blocked — everything is clear.</td></tr>
      <?php else: foreach ($held as $h): ?>
      <tr>
        <td><strong><?= e($h['nm']) ?></strong></td>
        <td><span class="pill <?= $h['hold_status']==='BLOCKED' ? 'p-bad' : 'p-warn' ?>"><?= e(PARTNER_HOLD_STATES[$h['hold_status']] ?? $h['hold_status']) ?></span></td>
        <td class="muted"><?= e($h['hold_reason']) ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/client-holds" style="display:inline" onsubmit="return confirm('Clear the hold on <?= e($h['nm']) ?>?')">
            <input type="hidden" name="client_id" value="<?= (int)$h['id'] ?>">
            <input type="hidden" name="hold_status" value="">
            <button class="btn small secondary" type="submit">Clear</button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
