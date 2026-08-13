<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › SLA targets</div>
<div class="master-head">
  <div><h1>SLA targets</h1>
    <p class="sub" style="margin:2px 0 0">Turnaround targets, in days, for each stage of a service. Set a
      <strong>default</strong> (client “— all —”) and, where a contract demands it, a per-client override. Nothing is
      hard-coded — a stage with no target simply shows “N/A” on the call.</p></div>
</div>

<section class="card" style="margin-bottom:16px">
  <h2 style="margin:0 0 10px">Add / update a target</h2>
  <form method="post" action="/sla-targets" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="ff"><label>Client</label>
      <select class="form-control" name="client_id">
        <option value="0">— all clients (default) —</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?? $c['legal_name'] ?? ('#'.$c['id'])) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Stage</label>
      <select class="form-control" name="stage"><?php foreach ($stages as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Target (days)</label><input class="form-control" type="number" name="target_days" min="0" value="7" style="width:110px"></div>
    <div class="ff"><label>Note</label><input class="form-control" name="note" placeholder="Optional"></div>
    <div class="ff"><button class="btn" type="submit">Save target</button></div>
  </form>
</section>

<section class="card">
  <h2 style="margin:0 0 10px">Current targets</h2>
  <table class="grid">
    <tr><th>Client</th><th>Stage</th><th>Target (days)</th><th>Note</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['client_id'] ? ($r['client_name'] ?: ('#'.$r['client_id'])) : '— all (default) —') ?></td>
      <td><?= e($stages[$r['stage']] ?? $r['stage']) ?></td>
      <td><?= (int)$r['target_days'] ?></td>
      <td><?= e($r['note']) ?></td>
      <td><form method="post" action="/sla-targets" onsubmit="return confirm('Remove this target?');" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn small secondary" type="submit">Remove</button></form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5">No targets yet — add a default set above.</td></tr><?php endif; ?>
  </table>
</section>
