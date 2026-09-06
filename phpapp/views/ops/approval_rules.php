<?php
// Approval rules — configurable matrix + multi-level chains. Data: $rules,$sel,$levels,$roles,$entities.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$rules = $rules ?? []; $sel = $sel ?? null; $levels = $levels ?? []; $roles = $roles ?? []; $entities = $entities ?? [];
$applySummary = function ($r) use ($e) {
    $b = [];
    foreach (['applies_department'=>'Dept','applies_sbu'=>'Unit','applies_grade'=>'Grade','applies_position'=>'Position'] as $c=>$l) if (trim((string)($r[$c]??''))!=='') $b[] = $l.':'.$e($r[$c]);
    if ((float)$r['min_amount']>0 || (float)$r['max_amount']>0) $b[] = 'Amount '.($r['min_amount']>0?$e(number_format($r['min_amount'],0)):'0').'–'.($r['max_amount']>0?$e(number_format($r['max_amount'],0)):'∞');
    return $b ? implode(' · ', $b) : 'Any';
};
?>
<div class="crumbs"><a href="/">Home</a> › Recruitment approvals</div>
<div class="master-head">
  <div><h1>Approval rules &amp; matrix</h1>
    <p class="sub" style="margin:2px 0 0">Configure who approves what, in how many steps — by entity, department, grade or value band — each level with its own SLA, reminder cadence and escalation. The narrowest matching rule applies.</p></div>
  <div class="row-actions"><form method="post" style="display:inline"><input type="hidden" name="do" value="rule_save"><input type="hidden" name="name" value="New rule"><button class="btn">＋ New rule</button></form></div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:18px;align-items:start">
  <div class="panel" style="padding:0">
    <div style="padding:12px 15px;border-bottom:1px solid var(--line,#e5e7eb);font-weight:700;font-size:14px">Rules (<?= count($rules) ?>)</div>
    <?php foreach ($rules as $r): $on = $sel && (int)$r['id']===(int)$sel['id']; ?>
      <a href="/recruit-approvals?id=<?= (int)$r['id'] ?>" style="display:block;padding:11px 15px;border-bottom:1px solid var(--line,#eef1f5);color:inherit;text-decoration:none;<?= $on?'background:var(--brand,#1e40af);color:#fff':'' ?>">
        <div style="font-weight:600;font-size:13.5px"><?= $e($r['name']) ?><?= (int)$r['active']===0?' <span class="pill p-mut" style="font-size:10px">off</span>':'' ?></div>
        <div style="font-size:11.5px;opacity:.85"><?= $e($entities[$r['entity']] ?? $r['entity']) ?> · <?= $e($applySummary($r)) ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <div>
    <?php if (!$sel): ?><div class="panel"><p class="muted">Pick a rule, or create one.</p></div><?php else: ?>
    <div class="panel">
      <h3 class="tab-sub">Rule — when it applies</h3>
      <form method="post"><input type="hidden" name="do" value="rule_save"><input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px">
          <div class="ff"><label>Rule name</label><input class="form-control" name="name" value="<?= $e($sel['name']) ?>"></div>
          <div class="ff"><label>Code</label><input class="form-control" name="code" value="<?= $e($sel['code']) ?>"></div>
          <div class="ff"><label>Applies to</label><select class="form-control" name="entity"><?php foreach ($entities as $k=>$v): ?><option value="<?= $k ?>" <?= $sel['entity']===$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
          <div class="ff"><label>Department</label><input class="form-control" name="applies_department" value="<?= $e($sel['applies_department']) ?>"></div>
          <div class="ff"><label>Business unit</label><input class="form-control" name="applies_sbu" value="<?= $e($sel['applies_sbu']) ?>"></div>
          <div class="ff"><label>Grade</label><input class="form-control" name="applies_grade" value="<?= $e($sel['applies_grade']) ?>"></div>
          <div class="ff"><label>Position</label><input class="form-control" name="applies_position" value="<?= $e($sel['applies_position']) ?>"></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
          <div class="ff"><label>Amount from (0 = any)</label><input class="form-control" type="number" step="any" name="min_amount" value="<?= (float)$sel['min_amount'] ?>"></div>
          <div class="ff"><label>Amount to (0 = ∞)</label><input class="form-control" type="number" step="any" name="max_amount" value="<?= (float)$sel['max_amount'] ?>"></div>
          <div class="ff"><label>Match order</label><input class="form-control" type="number" name="sort" value="<?= (int)$sel['sort'] ?>"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px"><button class="btn">Save rule</button><button class="btn secondary" name="do" value="rule_toggle"><?= (int)$sel['active']===1?'Disable':'Enable' ?></button></div>
      </form>
    </div>

    <div class="panel">
      <h3 class="tab-sub">Approval chain (levels)</h3>
      <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;min-width:760px">
        <tr><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Step</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Label</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Approver role</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">SLA days</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Remind after</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Escalate to</th><th></th></tr>
        <?php foreach ($levels as $lv): ?>
        <tr style="border-top:1px solid var(--line,#eef1f5)">
          <form method="post" style="display:contents"><input type="hidden" name="do" value="level_save"><input type="hidden" name="rule_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="level_id" value="<?= (int)$lv['id'] ?>">
            <td style="padding:6px 8px"><input class="form-control" type="number" name="seq" value="<?= (int)$lv['seq'] ?>" style="width:56px"></td>
            <td style="padding:6px 8px"><input class="form-control" name="label" value="<?= $e($lv['label']) ?>" placeholder="e.g. HR Head"></td>
            <td style="padding:6px 8px"><select class="form-control" name="approver_role"><option value="">—</option><?php foreach ($roles as $rk=>$rl): ?><option value="<?= $rk ?>" <?= $lv['approver_role']===$rk?'selected':'' ?>><?= $e($rl) ?></option><?php endforeach; ?></select></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="sla_days" value="<?= (int)$lv['sla_days'] ?>" style="width:64px"></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="reminder_days" value="<?= (int)$lv['reminder_days'] ?>" style="width:64px"></td>
            <td style="padding:6px 8px"><select class="form-control" name="escalate_role"><option value="">—</option><?php foreach ($roles as $rk=>$rl): ?><option value="<?= $rk ?>" <?= $lv['escalate_role']===$rk?'selected':'' ?>><?= $e($rl) ?></option><?php endforeach; ?></select></td>
            <td style="padding:6px 8px;white-space:nowrap"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Save</button><button class="btn secondary" style="padding:4px 9px;font-size:12px" name="do" value="level_delete" onclick="return confirm('Remove this level?')">✕</button></td>
          </form>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:1px solid var(--line,#eef1f5);background:var(--soft,#f6f8fb)">
          <form method="post" style="display:contents"><input type="hidden" name="do" value="level_save"><input type="hidden" name="rule_id" value="<?= (int)$sel['id'] ?>">
            <td style="padding:6px 8px"><input class="form-control" type="number" name="seq" value="<?= (count($levels)+1)*10 ?>" style="width:56px"></td>
            <td style="padding:6px 8px"><input class="form-control" name="label" placeholder="new level"></td>
            <td style="padding:6px 8px"><select class="form-control" name="approver_role"><option value="">—</option><?php foreach ($roles as $rk=>$rl): ?><option value="<?= $rk ?>"><?= $e($rl) ?></option><?php endforeach; ?></select></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="sla_days" value="2" style="width:64px"></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="reminder_days" value="1" style="width:64px"></td>
            <td style="padding:6px 8px"><select class="form-control" name="escalate_role"><option value="">—</option><?php foreach ($roles as $rk=>$rl): ?><option value="<?= $rk ?>"><?= $e($rl) ?></option><?php endforeach; ?></select></td>
            <td style="padding:6px 8px"><button class="btn" style="padding:4px 11px;font-size:12px">Add</button></td>
          </form>
        </tr>
      </table></div>
      <p class="muted" style="margin-top:8px;font-size:12px">A step's approver gets an email when it's their turn; a reminder after “remind after” days; and an escalation to the escalation role once the SLA is breached. Reminders/escalations run on the daily cron.</p>
    </div>
    <?php endif; ?>
  </div>
</div>
<style>.ff label{display:block;font-size:12px;font-weight:600;margin-bottom:4px}</style>
