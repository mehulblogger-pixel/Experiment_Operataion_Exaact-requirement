<?php // Per-inspector approver mapping (individual / common / temp cover) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › Approver mapping</div>
<div class="master-head"><div><h1>Approver mapping</h1>
  <p class="sub" style="margin:2px 0 0">Assign each inspector's approver. The same person can approve for many inspectors (common approver). Set a temporary cover during leave. Reports can't be submitted for an inspector with no approver.</p></div>
  <a class="btn secondary" href="/idems-approval-rules">Approval rules →</a>
</div>

<?php $uopt = function($users, $sel) { $h='<option value="">—</option>'; foreach ($users as $u){ $n=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; $h.='<option value="'.(int)$u['id'].'" '.((int)$sel===(int)$u['id']?'selected':'').'>'.e($n).' · '.e(ORG_ROLES[$u['role']] ?? $u['role']).'</option>'; } return $h; }; ?>

<?php // Each row's <form> lives OUTSIDE the table; the row's controls bind to it
      // by the HTML5 form="" id. A <form> nested inside <tr> is invalid HTML — the
      // browser hoisted it out, so the per-row inspector_id never posted and the
      // save silently did nothing (looked like "saved but not shown"). ?>
<?php foreach ($rows as $r): ?>
  <form id="apf-<?= (int)$r['inspector_id'] ?>" method="post" action="/approver-map"><input type="hidden" name="inspector_id" value="<?= (int)$r['inspector_id'] ?>"></form>
<?php endforeach; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Inspector</th><th>Approver</th><th>Temp cover</th><th>From</th><th>To</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r):
        $fid = 'apf-' . (int)$r['inspector_id'];
        $ap = trim(($r['af']??'').' '.($r['al']??'')) ?: ($r['au_name'] ?? '');
        $tp = trim(($r['tf']??'').' '.($r['tl']??'')) ?: ($r['tu_name'] ?? ''); ?>
      <tr>
        <td><strong><?= e($r['inspector_name']) ?></strong><?= $r['emp_code']?' <span class="muted">'.e($r['emp_code']).'</span>':'' ?><?= !$ap ? ' <span class="pill p-bad" style="padding:0 5px">no approver</span>':'' ?></td>
        <td><select class="form-control searchable" form="<?= $fid ?>" name="approver_user_id"><?= $uopt($users, $r['approver_user_id']) ?></select></td>
        <td><select class="form-control searchable" form="<?= $fid ?>" name="temp_user_id"><?= $uopt($users, $r['temp_user_id']) ?></select></td>
        <td><input class="form-control" form="<?= $fid ?>" type="date" name="temp_from" value="<?= e($r['temp_from'] ?? '') ?>" style="min-width:140px"></td>
        <td><input class="form-control" form="<?= $fid ?>" type="date" name="temp_to" value="<?= e($r['temp_to'] ?? '') ?>" style="min-width:140px"></td>
        <td><button class="btn small" form="<?= $fid ?>" type="submit">Save</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:16px">No active inspectors.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
