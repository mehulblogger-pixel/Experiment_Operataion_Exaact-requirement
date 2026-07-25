<div class="crumbs"><a href="/">Home</a> › <a href="/quotes">Approval rules</a> › <?= e(T('quote')) ?> approvals</div>
<div class="master-head">
  <div><h1>Approval rules</h1>
    <p class="sub" style="margin:2px 0 0">Who must approve a <?= e(Tl('quote')) ?> — by <strong>amount band</strong> and/or <strong><?= e(T('sbu')) ?></strong>. When a <?= e(Tl('quote')) ?> is submitted, matching rules become its approval chain (lower levels first).</p></div>
  <a class="btn" href="/quote-approval-rule-new">+ Add rule</a>
</div>
<?= approval_rule_tabs('/approval-rules?module=quote') ?>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th class="num">Level</th><th>Rule</th><th>Applies to</th><th class="num">From <?= e(cur_sym()) ?></th><th class="num">Up to <?= e(cur_sym()) ?></th><th>Approver</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $nm = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ($r['username'] ?? ''); ?>
    <tr>
      <td class="num"><b><?= (int)$r['level'] ?></b></td>
      <td><?= e($r['name'] ?: '—') ?></td>
      <td><?= $r['match_type']==='SBU' && $r['sbu'] ? 'SBU: '.e(lk_options_or('sbu',OPS_SBUS)[$r['sbu']] ?? $r['sbu']) : 'Any SBU' ?></td>
      <td class="num"><?= number_format((float)$r['min_amount'],0) ?></td>
      <td class="num"><?= (float)$r['max_amount']>0 ? number_format((float)$r['max_amount'],0) : '—' ?></td>
      <td><?= $r['approver_user_id'] ? e($nm) : ($r['approver_role'] ? e(ORG_ROLES[$r['approver_role']] ?? $r['approver_role']) : '<span class="muted">any approver</span>') ?></td>
      <td><span class="pill <?= $r['active']?'p-ok':'p-mut' ?>"><?= $r['active']?'Active':'Off' ?></span></td>
      <td class="num" style="white-space:nowrap">
        <a class="btn small secondary" href="/quote-approval-rule-edit?id=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" action="/quote-approval-rule-delete?id=<?= (int)$r['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this rule?')"><button class="btn small danger" type="submit">✕</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;padding:24px" class="muted">No rules yet. Without any rule, a submitted quote needs a single approval from any approver.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
