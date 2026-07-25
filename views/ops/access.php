<?php
  $has = fn($p) => in_array($p, $current, true);
  $rec = fn($p) => in_array($p, $recommended, true);
  $allPerm = all_permissions();
  // short plain-English description of each role (who they are)
  $roleBlurb = [
    'BUSINESS_DIRECTOR' => 'Top management. Sees everything across all offices and SBUs — all dashboards, revenue, salary and profitability — but does not run day-to-day operations.',
    'SBU_HEAD' => 'Heads one SBU across all offices. Full dashboards & figures for their SBU; approves inspection reports for their people.',
    'BRANCH_MANAGER' => 'Runs one office end-to-end: calls, jobs, availability, report approvals, masters, and users in that office.',
    'BRANCH_APP_MANAGER' => 'Application manager in a branch — masters, overheads and branch users; limited operational view.',
    'OPERATION_MANAGER' => 'Under the branch manager — allocates & closes jobs, runs the availability board, approves reports.',
    'ASST_MANAGER' => 'Assists operations — creates calls, allocates jobs, manages availability. No money figures.',
    'COORDINATOR' => 'Day-to-day desk — schedules and closes jobs, availability board, sees revenue but not salary/profit.',
    'BUSINESS_DEV_MANAGER' => 'Sales — creates & sends ' . Tlp('quote') . ' and runs follow-ups across offices.',
    'KEY_ACCOUNTS_MANAGER' => 'Sales — owns key accounts; creates & sends ' . Tlp('quote') . ' and follow-ups.',
    'MARKETING_MANAGER' => 'Senior sales — everything BDM/KAM can do plus approve ' . Tlp('quote') . ' and manage templates.',
    'MARKETING_EXECUTIVE' => 'Junior sales — drafts ' . Tlp('quote') . ' and manages follow-ups (no send/approve).',
    'FINANCE' => 'Accounts — invoicing, credit reconciliation, register clients/contracts; sees all figures.',
    'INSPECTOR' => 'Field engineer — only their own My Jobs / My Voucher; no admin screens.',
  ];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Roles &amp; access</div>
<div class="master-head">
  <div><h1>Roles &amp; permissions</h1>
    <p class="sub" style="margin:2px 0 0">Pick a role, apply the recommended set in one click, then fine-tune. Applies to everyone in that role without a personal override. <strong>Master Admin</strong> always has everything.</p></div>
  <a class="btn secondary" href="/settings">← Settings</a>
</div>

<div class="chip-row" style="margin-bottom:14px">
  <?php foreach ($roles as $k => $lbl): ?>
    <a class="ct" href="/access?role=<?= e($k) ?>" style="<?= $sel===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel" style="margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start">
    <div style="flex:1;min-width:260px">
      <h3 style="margin:0 0 4px"><?= e($roles[$sel]) ?></h3>
      <p class="muted" style="margin:0"><?= e($roleBlurb[$sel] ?? '') ?></p>
      <p class="muted" style="margin:6px 0 0">Recommended data scope: <strong>Offices <?= e($scope['offices'] ?? 'OWN') ?></strong> · <strong>SBUs <?= e($scope['sbus'] ?? 'OWN') ?></strong></p>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <form method="post" action="/access" onsubmit="return confirm('Apply the recommended permission set for <?= e($roles[$sel]) ?>? This replaces the current ticks below (you can still adjust before saving).')">
        <input type="hidden" name="role" value="<?= e($sel) ?>"><input type="hidden" name="_do" value="preset">
        <button class="btn" type="submit">✨ Apply recommended set</button>
      </form>
      <form method="post" action="/access" onsubmit="return confirm('Reset <?= e($roles[$sel]) ?> to the built-in default (removes your custom override)?')">
        <input type="hidden" name="role" value="<?= e($sel) ?>"><input type="hidden" name="_do" value="reset">
        <button class="btn secondary" type="submit">Reset to default</button>
      </form>
    </div>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:12px">Rows marked <span class="pill p-ok" style="padding:1px 6px">recommended</span> are in the suggested set for this role.</p>
</div>

<form method="post" action="/access" class="panel">
  <input type="hidden" name="role" value="<?= e($sel) ?>">

  <h3 class="tab-sub" style="margin-top:0;">Modules — what screens they can open</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Module</th><th style="text-align:center">View</th><th style="text-align:center">Add / edit</th></tr></thead>
    <tbody>
    <?php foreach ($moduleGroups as $grp => $keys): ?>
      <tr><td colspan="3" style="background:var(--soft);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.03em"><?= e($grp) ?></td></tr>
      <?php foreach ($keys as $k): if (!isset(ACCESS_MODULES[$k])) continue; ?>
      <tr>
        <td><b><?= e(ACCESS_MODULES[$k]) ?></b> <?= $rec("mod.$k.view")?'<span class="pill p-ok" style="padding:0 5px;font-size:10px">recommended</span>':'' ?></td>
        <td style="text-align:center"><input type="checkbox" name="perms[mod.<?= e($k) ?>.view]" value="1" <?= $has("mod.$k.view")?'checked':'' ?>></td>
        <td style="text-align:center"><input type="checkbox" name="perms[mod.<?= e($k) ?>.edit]" value="1" <?= $has("mod.$k.edit")?'checked':'' ?>></td>
      </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h3 class="tab-sub">Data &amp; feature permissions — what they can do &amp; see</h3>
  <?php foreach ($permGroups as $grp => $keys): ?>
    <div style="margin:10px 0 4px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand)"><?= e($grp) ?></div>
    <div class="checkgrid">
      <?php foreach ($keys as $k): if (!isset($allPerm[$k])) continue; ?>
        <label class="chk<?= $rec($k)?' rec':'' ?>"><input type="checkbox" name="perms[<?= e($k) ?>]" value="1" <?= $has($k)?'checked':'' ?>> <?= e($allPerm[$k]) ?><?= $rec($k)?' <span class="pill p-ok" style="padding:0 5px;font-size:10px">rec</span>':'' ?></label>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save access for <?= e($roles[$sel]) ?></button>
    <a class="btn secondary" href="/access?role=<?= e($sel) ?>">Cancel changes</a>
  </div>
</form>

<p class="muted" style="margin-top:10px">Tip: <strong>Add / edit</strong> also grants <strong>View</strong>. Individual exceptions per person are set under <a href="/users">Users</a>. See the whole reporting tree in <a href="/hierarchy">Org hierarchy</a>.</p>

<style>.checkgrid .chk.rec{border-left:3px solid var(--ok);padding-left:7px}</style>
