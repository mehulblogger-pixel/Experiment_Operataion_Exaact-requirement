<?php
// Approval rules — configurable matrix + multi-level chains. Data: $rules,$sel,$levels,$roles,$entities.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$rules = $rules ?? []; $sel = $sel ?? null; $levels = $levels ?? []; $roles = $roles ?? []; $entities = $entities ?? [];
// Org-chart approver options ("reporting manager", "HOD", …) offered alongside
// the fixed roles — the approval then follows the reporting lines automatically.
$orgApprovers = defined('APPR_ORG_APPROVERS') ? APPR_ORG_APPROVERS : [];
$roleOptions = function ($selected) use ($e, $roles, $orgApprovers) {
    $h = '<option value="">—</option>';
    foreach ($roles as $rk => $rl) $h .= '<option value="' . $e($rk) . '"' . ((string) $selected === (string) $rk ? ' selected' : '') . '>' . $e($rl) . '</option>';
    if ($orgApprovers) {
        $h .= '<optgroup label="Follow the org chart">';
        foreach ($orgApprovers as $tk => $tl) $h .= '<option value="' . $e($tk) . '"' . ((string) $selected === (string) $tk ? ' selected' : '') . '>' . $e($tl) . '</option>';
        $h .= '</optgroup>';
    }
    return $h;
};
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

<?php
// ---------------------------------------------------------------------------
//  GATE 4 — SELF-APPROVAL POLICY, for this organisation.
//
//  Placed ABOVE the rules, deliberately: a chain that routes an approval to the
//  person who raised it is not an approval, so whether that is permitted at all is
//  a more fundamental question than who is on which level. Two switches, because
//  "may anybody decide their own request" and "may a superuser" are different
//  questions an organisation answers separately.
//
//  Built from the existing panel/pill classes. No new UI framework.
$sp = $selfPolicy ?? ['self_approval' => false, 'master_exception' => false, 'migrated' => ''];
?>
<div class="panel" style="margin-bottom:18px<?= $sp['self_approval'] ? ';border-left:4px solid var(--amber,#d97706);background:#fffdf5' : '' ?>">
  <h3 class="tab-sub" style="margin-top:0">Who may approve their own request
    <?php if ($sp['self_approval']): ?>
      <span class="pill p-bad" style="font-size:11px">Self-approval is ON</span>
    <?php else: ?>
      <span class="pill p-ok" style="font-size:11px">Self-approval is OFF</span>
    <?php endif; ?>
    <?php if ($sp['master_exception']): ?>
      <span class="pill p-warn" style="font-size:11px">Superuser exception enabled</span>
    <?php endif; ?>
  </h3>
  <p class="muted" style="margin:0 0 10px">
    Normally the person who raises a hiring request, a requirement, an offer, a salary
    structure or a change to an approved requirement cannot be the person who approves
    it — that separation is the whole point of having an approval step. These two
    switches are this organisation's own decision, and they apply to every one of
    those records.
  </p>
  <form method="post" style="margin:0">
    <input type="hidden" name="do" value="self_policy">
    <label style="display:block;margin-bottom:8px">
      <input type="checkbox" name="self_approval" value="1"<?= $sp['self_approval'] ? ' checked' : '' ?>>
      <strong>Allow anyone to approve their own request</strong>
      <span class="muted" style="font-size:12px">— off by default. Switching this on removes the
      separation for everybody, not just administrators.</span>
    </label>
    <label style="display:block;margin-bottom:10px">
      <input type="checkbox" name="master_exception" value="1"<?= $sp['master_exception'] ? ' checked' : '' ?>>
      <strong>Allow a superuser to approve their own request</strong>
      <span class="muted" style="font-size:12px">— for a workspace where one person legitimately
      does both. Every time it is used it is recorded in the audit trail as a self-approval
      exception. An ordinary administrator does <em>not</em> get this; only a superuser.</span>
    </label>
    <button class="btn">Save policy</button>
  </form>
  <?php if ($sp['migrated'] !== ''): ?>
    <p class="muted" style="margin:10px 0 0;font-size:11.5px">
      Policy first applied to this organisation: <?= $e($sp['migrated']) ?>.
      <?php if (strpos((string) $sp['migrated'], 'EXISTING') === 0): ?>
        This workspace was already in use, so the superuser exception was kept as it
        behaved before — you can switch it off here, which was not possible previously.
      <?php endif; ?>
    </p>
  <?php endif; ?>
</div>

<?php //  R1-UI (Gate 6B) — WHICH REQUIREMENT CHANGES RAISE A REVIEW.
      //
      //  The trigger has been on since Gate 3; what was missing was any way for an
      //  administrator to see it or change it. Shown on the screen that already
      //  carries the organisation's other recruitment governance decisions, read
      //  from the engine (crev_trigger_state()) rather than the settings table, and
      //  saved through the same setting_set() path, so the change is audited.
      //
      //  'stricter' is shown as a fixed fact, not a switch: it is a constant in the
      //  engine and there is deliberately no control that could turn it off. ?>
<?php $rt = $reviewTriggers ?? ['mandatory' => ['stricter'], 'optional' => []]; ?>
<div class="panel" style="margin-bottom:16px">
  <h3 style="margin:0 0 4px">When does a requirement change need its candidates re-checked?
    <?php if (!empty($rt['optional']['redefined'])): ?>
      <span class="pill p-ok" style="font-size:11px">Redefined: ON</span>
    <?php else: ?>
      <span class="pill p-mut" style="font-size:11px">Redefined: OFF</span>
    <?php endif; ?>
  </h3>
  <p class="muted" style="margin:0 0 10px">
    When an approved requirement changes, the people already in the process may no longer
    be the right people. Where that is so, each of them gets a <strong>Review Required</strong>
    flag that somebody has to decide before they can be offered a job or marked as joined.
  </p>
  <p class="muted" style="margin:0 0 10px;font-size:12.5px">
    <strong>A requirement that became stricter always raises a review</strong> — a higher
    experience floor, a higher qualification, or an extra essential skill. That is not
    configurable, because the candidates were judged against the old, lower bar.
    A requirement that was <em>relaxed</em> never raises one: lowering the bar reopens nobody.
  </p>
  <form method="post" style="margin:0">
    <input type="hidden" name="do" value="review_triggers">
    <label style="display:block;margin-bottom:10px">
      <input type="checkbox" name="trigger_redefined" value="1"<?= !empty($rt['optional']['redefined']) ? ' checked' : '' ?>>
      <strong>Also raise a review when the requirement is redefined</strong>
      <span class="muted" style="font-size:12px">— on by default. A change that is not a
      higher bar but makes the people in the process the <em>wrong</em> people: the role became
      Electrician where it said Welder, the branch moved from Mumbai to Dubai, or the
      department, grade, team, trade or skill changed. Switch it off only if this
      organisation re-labels roles without changing what it actually wants.</span>
    </label>
    <button class="btn">Save triggers</button>
  </form>
  <p class="muted" style="margin:10px 0 0;font-size:11.5px">
    Covers <?= (int) count(RVER_REDEFINES) ?> requirement fields. Changing this is recorded in
    the configuration audit trail like any other setting.
  </p>
</div>

<?php //  PRE-EXISTING RESPONSIVE DEFECT, found by the Gate 6B browser battery at 360px.
      //
      //  This was an inline `grid-template-columns:280px 1fr`. A 280px fixed rail
      //  plus the 18px gap cannot fit a 360px phone, so the right-hand panel ran
      //  31px past the viewport and the whole page scrolled sideways — measured
      //  identically with and without the R1-UI panel above, so it predates it.
      //
      //  An inline style cannot carry a media query, so the layout moves to a class
      //  and stacks on narrow screens. Same rule, same breakpoint and same 280px
      //  rail as views/ops/recruit_pipelines.php, which already solved this. ?>
<style>
  .ar-split{display:grid;grid-template-columns:280px minmax(0,1fr);gap:18px;align-items:start}
  @media(max-width:1080px){.ar-split{grid-template-columns:1fr}}
</style>
<div class="ar-split">
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
      <?php // M2 — the two things that quietly break an approval policy: a level
            // nobody can action, and a rule that ties with another. Say both out loud.
            $orphans = $orphans ?? []; $ties = $ties ?? []; ?>
      <?php if ($orphans): ?>
        <div class="panel" style="border-left:3px solid #b42318;margin-bottom:12px">
          <strong>This policy cannot run as configured.</strong>
          <ul style="margin:6px 0 0 18px">
            <?php foreach ($orphans as $o): ?>
              <li>Level <?= (int)$o['level']['seq'] ?> (<?= $e($o['level']['label'] ?: 'unnamed') ?>) — <?= $e($o['why']) ?>.</li>
            <?php endforeach; ?>
          </ul>
          <p class="muted" style="margin:6px 0 0">A hiring request matching this policy will wait for an approver who does not exist. It will never be approved by accident, and never becomes executable — but nobody can act on it until this is fixed.</p>
        </div>
      <?php endif; ?>
      <?php if ($ties): ?>
        <div class="panel" style="border-left:3px solid #b45309;margin-bottom:12px">
          <strong>Another policy is equally specific and has the same match order.</strong>
          <div class="muted" style="margin-top:4px">
            <?php foreach ($ties as $t): ?><div>· <?= $e($t['name']) ?> (match order <?= (int)$t['sort'] ?>)</div><?php endforeach; ?>
            The older rule wins, which is predictable but probably not what you meant. Give one of them a lower match order.
          </div>
        </div>
      <?php endif; ?>
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
          <div class="ff"><label>Branch</label>
            <select class="form-control" name="applies_office_id">
              <option value="0">Any branch (global policy)</option>
              <?php foreach (($offices ?? []) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($sel['applies_office_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= $e($o['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="ff"><label>In force from</label><input class="form-control" type="date" name="effective_from" value="<?= $e(substr((string)($sel['effective_from'] ?? ''),0,10)) ?>"></div>
          <div class="ff"><label>In force until</label><input class="form-control" type="date" name="effective_to" value="<?= $e(substr((string)($sel['effective_to'] ?? ''),0,10)) ?>"></div>
          <div class="ff"><label>Match order</label><input class="form-control" type="number" name="sort" value="<?= (int)$sel['sort'] ?>">
            <small class="muted">Used only when two rules are equally specific — lower wins.</small></div>
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
            <td style="padding:6px 8px"><select class="form-control" name="approver_role"><?= $roleOptions($lv['approver_role']) ?></select></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="sla_days" value="<?= (int)$lv['sla_days'] ?>" style="width:64px"></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="reminder_days" value="<?= (int)$lv['reminder_days'] ?>" style="width:64px"></td>
            <td style="padding:6px 8px"><select class="form-control" name="escalate_role"><?= $roleOptions($lv['escalate_role']) ?></select></td>
            <td style="padding:6px 8px;white-space:nowrap"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Save</button><button class="btn secondary" style="padding:4px 9px;font-size:12px" name="do" value="level_delete" onclick="return confirm('Remove this level?')">✕</button></td>
          </form>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:1px solid var(--line,#eef1f5);background:var(--soft,#f6f8fb)">
          <form method="post" style="display:contents"><input type="hidden" name="do" value="level_save"><input type="hidden" name="rule_id" value="<?= (int)$sel['id'] ?>">
            <td style="padding:6px 8px"><input class="form-control" type="number" name="seq" value="<?= (count($levels)+1)*10 ?>" style="width:56px"></td>
            <td style="padding:6px 8px"><input class="form-control" name="label" placeholder="new level"></td>
            <td style="padding:6px 8px"><select class="form-control" name="approver_role"><?= $roleOptions('') ?></select></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="sla_days" value="2" style="width:64px"></td>
            <td style="padding:6px 8px"><input class="form-control" type="number" name="reminder_days" value="1" style="width:64px"></td>
            <td style="padding:6px 8px"><select class="form-control" name="escalate_role"><?= $roleOptions('') ?></select></td>
            <td style="padding:6px 8px"><button class="btn" style="padding:4px 11px;font-size:12px">Add</button></td>
          </form>
        </tr>
      </table></div>
      <p class="muted" style="margin-top:8px;font-size:12px">A step's approver gets an email when it's their turn; a reminder after “remind after” days; and an escalation to the escalation role once the SLA is breached. Reminders/escalations run on the daily cron.</p>
    </div>
    <?php endif; ?>

    <?php // M2 §14 — "why this approval?". Type the facts of a request and see
          // which policy would win, what ties with it, and who could actually act.
    if ($sel): $pv = $preview ?? null; ?>
    <div class="panel">
      <h3 class="tab-sub" style="margin-top:0">Try it — which policy would apply?</h3>
      <form method="get">
        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="pv" value="1">
        <div class="ff-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
          <div class="ff"><label>Department</label><input class="form-control" name="pv_department" value="<?= $e($_GET['pv_department'] ?? '') ?>"></div>
          <div class="ff"><label>Grade</label><input class="form-control" name="pv_grade" value="<?= $e($_GET['pv_grade'] ?? '') ?>"></div>
          <div class="ff"><label>Position / designation</label><input class="form-control" name="pv_position" value="<?= $e($_GET['pv_position'] ?? '') ?>"></div>
          <div class="ff"><label>Branch</label>
            <select class="form-control" name="pv_office_id"><option value="0">—</option>
              <?php foreach (($offices ?? []) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($_GET['pv_office_id'] ?? 0)===(int)$o['id']?'selected':'' ?>><?= $e($o['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="ff"><label>How many / amount</label><input class="form-control" type="number" step="any" name="pv_amount" value="<?= $e($_GET['pv_amount'] ?? '') ?>"></div>
          <div class="ff" style="align-self:end"><button class="btn secondary">Show me</button></div>
        </div>
      </form>
      <?php if ($pv): ?>
        <?php if ($pv['no_match']): ?>
          <div style="margin-top:12px;border-left:3px solid #6b7280;padding-left:10px">
            <strong>No policy matches.</strong>
            <div class="muted">The request would not go through an approval chain. It stays <em>Submitted</em> and is decided directly by an administrator — the behaviour a workspace with no rules configured has always had.</div>
          </div>
        <?php else: ?>
          <div style="margin-top:12px;border-left:3px solid #1a7f37;padding-left:10px">
            <strong><?= $e($pv['matched']['name']) ?></strong> would apply.
            <?php if ($pv['ambiguous']): ?><span class="pill p-warn" style="font-size:11px">ties with another rule</span><?php endif; ?>
            <table class="table" style="font-size:13px;margin-top:8px">
              <tr><th>Level</th><th>Authority</th><th>Who could act</th></tr>
              <?php foreach ($pv['levels'] as $L): $el = $L['eligible']; ?>
                <tr><td><?= (int)$L['level']['seq'] ?></td>
                    <td><?= $e($L['level']['label'] ?: ($el['role'] ?: 'unnamed')) ?></td>
                    <td><?php
                      if ($el['users']) {
                          $ns = [];
                          foreach ($el['users'] as $u) { $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); $ns[] = $e($n !== '' ? $n : ($u['username'] ?? '')); }
                          echo implode(', ', $ns);
                      } elseif ($el['kind'] === 'ORG') { echo '<span class="muted">resolved from the org chart when a request is raised</span>'; }
                      else { echo '<span class="pill p-bad" style="font-size:11px">nobody</span>'; }
                    ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$pv['levels']): ?><tr><td colspan="3" class="muted">This policy has no levels, so it cannot start a chain.</td></tr><?php endif; ?>
            </table>
            <?php if (count($pv['ranked']) > 1): ?>
              <p class="muted" style="margin:6px 0 0;font-size:12px">Also matched, in order:
                <?php $rest = array_slice($pv['ranked'], 1); $ls = [];
                      foreach ($rest as $r2) $ls[] = $e($r2['rule']['name']) . ' (specificity ' . (int)$r2['score'] . ')';
                      echo implode(' · ', $ls); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<style>.ff label{display:block;font-size:12px;font-weight:600;margin-bottom:4px}</style>
