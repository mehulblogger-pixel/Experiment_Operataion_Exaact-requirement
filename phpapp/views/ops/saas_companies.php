<?php
// Super-Admin — Companies console. Every company on the platform in one place:
// plan, purchased seats, modules and status, with the levers to change them and
// to log in as a company. Data: $companies, $plans, $modules, $sel, $base_domain.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$companies = $companies ?? []; $plans = $plans ?? []; $modules = $modules ?? [];
$sel = $sel ?? null; $base_domain = $base_domain ?? ''; $can_autocreate = $can_autocreate ?? false;
$modLabel = fn($k) => $modules[$k][0] ?? ucfirst($k);
?>
<style>
  .sc-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px}
  .sc-head h1{margin:0;font-size:21px}
  .sc-sub{color:var(--muted,#656e7a);font-size:13px;margin:4px 0 0}
  .sc-card{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));overflow:hidden;margin-bottom:16px}
  .sc-card h2{margin:0;padding:13px 16px;font-size:14px;border-bottom:1px solid var(--line,#e5e7eb);background:var(--soft,#f6f8fb)}
  .sc-scroll{overflow-x:auto}
  table.sc{width:100%;border-collapse:collapse;min-width:760px}
  table.sc th,table.sc td{padding:10px 12px;border-bottom:1px solid var(--line,#eef1f5);text-align:left;font-size:13px;vertical-align:middle}
  table.sc th{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a)}
  .sc-meta{font-size:11.5px;color:var(--muted,#656e7a);line-height:1.35}
  .sc-co{font-weight:600} .sc-key{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;color:var(--muted,#656e7a)}
  .pill{display:inline-block;padding:1px 9px;border-radius:20px;font-size:11px;font-weight:700}
  .pill.on{background:#ecfdf5;color:#047857}.pill.off{background:#fee2e2;color:#dc2626}
  .pill.mod{background:#eef2ff;color:#4338ca;margin:1px 2px 1px 0;font-weight:600}
  .sc-actions{display:flex;gap:6px;flex-wrap:wrap}
  .btn.xs{padding:4px 9px;font-size:12px}
  .sc-edit{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px}
  .sc-edit .ff{display:flex;flex-direction:column;gap:5px}
  .sc-edit label{font-size:12px;font-weight:600;color:var(--ink,#28313f)}
  .sc-edit input,.sc-edit select{padding:8px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px;font:inherit;width:100%}
  .sc-mods{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
  .sc-mods label{display:flex;align-items:center;gap:6px;font-weight:500;font-size:13px}
  .sc-note{font-size:12px;color:var(--muted,#656e7a);margin:2px 2px 10px}
  @media(max-width:820px){.sc-edit{grid-template-columns:1fr}}
</style>

<div class="sc-head">
  <div>
    <h1>Companies</h1>
    <div class="sc-sub">Every company on the platform<?= $base_domain ? ' · <b>' . $e($base_domain) . '</b>' : '' ?> — plan, seats, modules and status. <a href="/super-admin">‹ Control panel</a></div>
  </div>
</div>

<details class="sc-card" style="padding:0">
  <summary style="cursor:pointer;padding:13px 16px;font-size:14px;font-weight:600;background:var(--soft,#f6f8fb);border-bottom:1px solid var(--line,#e5e7eb)">＋ Add a company</summary>
  <form method="post" style="padding:16px">
    <input type="hidden" name="do" value="company_add">
    <div class="sc-edit" style="padding:0">
      <div class="ff"><label>Company name *</label><input name="company" required placeholder="Asme Pharmaceutical Private Limited"></div>
      <div class="ff"><label>Workspace key <span class="sc-key">(optional — made from the name)</span></label><input name="new_key" placeholder="asme"></div>
      <div class="ff"><label>Owner name</label><input name="owner_name" placeholder="Asme Admin"></div>
      <div class="ff"><label>Owner email * <span class="sc-key">(their login)</span></label><input type="email" name="owner_email" required placeholder="admin@asmehr.com"></div>
      <div class="ff"><label>Temporary password <span class="sc-key">(blank = auto)</span></label><input name="owner_pass" placeholder="leave blank to auto-generate"></div>
      <div class="ff"><label>Plan</label><select name="new_plan"><?php foreach ($plans as $pk => $p): ?><option value="<?= $e($pk) ?>" <?= $pk === 'RECRUITMENT' ? 'selected' : '' ?>><?= $e($p['label']) ?> — <?= implode(', ', array_map($modLabel, $p['mods'])) ?></option><?php endforeach; ?></select></div>
    </div>
    <div style="margin-top:12px;border-top:1px dashed var(--line,#e5e7eb);padding-top:12px">
      <!-- WHERE THIS COMPANY'S DATA LIVES.
           With a hosting API the app makes a real MySQL database itself and the
           operator ticks nothing. WITHOUT one (mPanel and most simple panels
           have no API), the choice is put in front of them in plain language
           instead of being made silently - because the two options differ in
           how the data can be lost, and that is not a detail to hide. Either
           way the data is placed where a file upload cannot reach it. -->
      <?php if ($can_autocreate): ?>
      <input type="hidden" name="db_kind" id="db_kind" value="auto">
      <div style="display:flex;gap:8px;align-items:center;font-size:13px;color:#047857">
        <span style="font-size:15px">&#128737;&#65039;</span>
        <span><b>Its own private database is set up automatically</b> &mdash; a fresh, isolated MySQL database on this server. Nothing to configure.</span>
      </div>
      <details style="margin-top:10px">
        <summary style="cursor:pointer;font-size:12.5px;color:var(--muted,#6b7280)">Advanced: use a MySQL database I created myself</summary>
        <div class="sc-edit" style="padding:8px 0 0" onclick="document.getElementById('db_kind').value='mysql'">
          <div class="ff"><label>MySQL host</label><input name="db_host" value="localhost"></div>
          <div class="ff"><label>MySQL database name</label><input name="db_name" placeholder="prefix_asme"></div>
          <div class="ff"><label>MySQL user</label><input name="db_user" placeholder="prefix_asme"></div>
          <div class="ff"><label>MySQL password</label><input type="password" name="db_pass" autocomplete="new-password"></div>
          <div class="sc-note">Filling these in and creating the company uses this database instead of the automatic one.</div>
        </div>
      </details>
      <?php else: ?>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:8px">Where this company's data is stored</label>
      <input type="hidden" name="db_kind" id="db_kind" value="auto">
      <div style="display:grid;gap:8px">
        <label style="display:flex;gap:10px;align-items:flex-start;padding:11px 13px;border:1px solid var(--line,#e5e7eb);border-radius:10px;cursor:pointer;font-size:13.5px">
          <input type="radio" name="storage_choice" value="auto" checked style="margin-top:3px"
                 onchange="document.getElementById('db_kind').value='auto';document.getElementById('sc-my').style.display='none'">
          <span><b>Set it up for me</b> <span class="sc-key">&mdash; nothing to do</span><br>
            <span style="color:var(--muted,#6b7280)">A private data file, kept <b>outside the app folder</b>. Re-uploading the app cannot reach it. Ready in one click.</span></span>
        </label>
        <label style="display:flex;gap:10px;align-items:flex-start;padding:11px 13px;border:1px solid var(--line,#e5e7eb);border-radius:10px;cursor:pointer;font-size:13.5px">
          <input type="radio" name="storage_choice" value="mysql" style="margin-top:3px"
                 onchange="document.getElementById('db_kind').value='mysql';document.getElementById('sc-my').style.display='block'">
          <span><b>Use a MySQL database</b> <span class="sc-key">&mdash; strongest, about 2 minutes</span><br>
            <span style="color:var(--muted,#6b7280)">A real database, exactly like the Books app uses. Make one in your hosting panel first, then paste its details here.</span></span>
        </label>
      </div>
      <div id="sc-my" style="display:none;margin-top:10px">
        <div style="padding:11px 13px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:10px;font-size:12.5px;color:#1e3a8a;margin-bottom:10px">
          <b>In your hosting panel &rarr; Databases:</b> create a database, create a user with a strong password, then add that
          user to that database with <b>all privileges</b>. Your panel adds its own prefix to both names &mdash; copy them exactly as it shows them.
        </div>
        <div class="sc-edit" style="padding:0">
          <div class="ff"><label>MySQL host</label><input name="db_host" value="localhost"></div>
          <div class="ff"><label>MySQL database name</label><input name="db_name" placeholder="prefix_asme"></div>
          <div class="ff"><label>MySQL user</label><input name="db_user" placeholder="prefix_asme"></div>
          <div class="ff"><label>MySQL password</label><input type="password" name="db_pass" autocomplete="new-password"></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <button class="btn" style="margin-top:6px">Create company</button>
  </form>
</details>

<?php if (!$companies): ?>
  <div class="sc-card"><div style="padding:18px" class="sc-note">
    No companies in the directory yet. Use <strong>＋ Add a company</strong> above to create one in a single step — the app builds its database and the owner signs in and completes their own onboarding. (Existing workspaces registered from <a href="/tenants">Cloud workspaces</a> also appear here for plan, seat and module management.)
  </div></div>
<?php else: ?>
<div class="sc-card">
  <h2><?= count($companies) ?> compan<?= count($companies) === 1 ? 'y' : 'ies' ?></h2>
  <div class="sc-scroll">
  <table class="sc">
    <tr><th>Company</th><th>Plan</th><th>Seats</th><th>Modules</th><th>Storage</th><th>Last used</th><th>Status</th><th></th></tr>
    <?php foreach ($companies as $c): $k = $e($c['tenant_key']);
      $active = ($c['status'] ?? 'active') !== 'suspended' && ($c['route_status'] ?? '') !== 'suspended'; ?>
    <tr>
      <td><div class="sc-co"><?= $e($c['company'] ?: $c['tenant_key']) ?></div>
          <div class="sc-key"><?= $k ?><?= $base_domain ? ' · ' . $e($c['owner_email'] ?? '') : '' ?></div></td>
      <td><?= $c['plan'] === 'TRIAL' ? 'Free trial' : ($c['plan'] === 'CUSTOM' ? 'Own choice' : $e($plans[$c['plan']]['label'] ?? $c['plan'])) ?>
        <?php if (!empty($c['self'])): ?><div class="sc-meta">signed itself up</div>
          <?php if (($c['converted_at'] ?? '') !== ''): ?><div class="sc-meta" style="color:#047857">converted from free trial <?= $e(fdate(substr($c['converted_at'], 0, 10))) ?></div><?php endif; ?>
          <?php if (($c['paid_until'] ?? '') !== ''): ?><div class="sc-meta">paid until <?= $e(fdate($c['paid_until'])) ?></div>
          <?php elseif (($c['trial_until'] ?? '') !== ''): ?><div class="sc-meta">trial <?= ($c['trial_until'] >= date('Y-m-d')) ? 'until' : 'ended' ?> <?= $e(fdate($c['trial_until'])) ?></div><?php endif; ?>
        <?php endif; ?></td>
      <td><?php if ((int) $c['seat_limit'] === 0): ?><b>no limit</b>
          <?php elseif ($c['plan'] === 'CUSTOM'): ?><b><?= (int) $c['seat_limit'] ?></b> <span class="sc-meta">bought</span>
          <?php else: ?><b><?= (int) $c['seat_limit'] ?></b> <span class="sc-key">(<?= (int) $c['base_logins'] ?> + <?= (int) ($c['extra_user_seats'] ?? 0) ?>)</span><?php endif; ?></td>
      <td><?php foreach ($c['mods_list'] as $m): ?><span class="pill mod"><?= $e($modLabel($m)) ?></span><?php endforeach; ?></td>
      <td><?php $su = (string) ($c['storage_used'] ?? ''); $sl = (int) ($c['storage_limit'] ?? 0); ?>
        <?php if ($su === ''): ?><span class="sc-meta">not read yet</span>
        <?php else: $sp = $sl > 0 ? (int) floor(100 * (int) $su / $sl) : null; ?>
          <?= $e(saas_bytes_label((int) $su)) ?><div class="sc-meta" style="<?= $sp !== null && $sp >= 95 ? 'color:#b91c1c' : ($sp !== null && $sp >= 80 ? 'color:#b45309' : '') ?>"><?= $sp !== null ? $sp . '% of ' . $e(saas_bytes_label($sl)) : 'no limit' ?></div>
        <?php endif; ?></td>
      <td><?php $la = (string) ($c['last_active_at'] ?? '') ?: (string) ($c['last_login_at'] ?? ''); ?>
        <?= $la !== '' ? $e(fdate(substr($la, 0, 10))) . '<div class="sc-meta">' . (int) $c['idle_days'] . ' day' . ((int) $c['idle_days'] === 1 ? '' : 's') . ' ago</div>' : '<span class="sc-meta">' . (!empty($c['self']) ? 'not yet' : '—') . '</span>' ?>
        <?php if (!empty($c['delete_on'])): ?><div class="sc-meta" style="color:#b45309">deleted <?= $e(fdate($c['delete_on'])) ?> if unused<?= (int) ($c['inactive_notices'] ?? 0) ? ' · ' . (int) $c['inactive_notices'] . ' warning' . ((int) $c['inactive_notices'] === 1 ? '' : 's') . ' sent' : '' ?></div><?php endif; ?></td>
      <td><?= $active ? '<span class="pill on">Active</span>' : '<span class="pill off">Suspended</span>' ?></td>
      <td><div class="sc-actions">
        <a class="btn xs ghost" href="/companies?key=<?= $k ?>">Manage</a>
        <form method="post" style="display:inline"><input type="hidden" name="do" value="company_login_as"><input type="hidden" name="key" value="<?= $k ?>"><input type="hidden" name="company" value="<?= $e($c['company']) ?>"><button class="btn xs">Log in as ›</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('<?= $active ? 'Suspend' : 'Reactivate' ?> <?= $e($c['company']) ?>?')"><input type="hidden" name="do" value="company_status"><input type="hidden" name="key" value="<?= $k ?>"><input type="hidden" name="status" value="<?= $active ? 'suspended' : 'active' ?>"><button class="btn xs ghost" style="color:<?= $active ? '#dc2626' : '#047857' ?>"><?= $active ? 'Suspend' : 'Activate' ?></button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('PERMANENTLY REMOVE “<?= $e($c['company']) ?>”?\n\nThis deletes its workspace and all its data and cannot be undone. Its owner email will be free to use again.\n\nTip: use Suspend instead if you only want to block sign-in for now.')"><input type="hidden" name="do" value="company_remove"><input type="hidden" name="key" value="<?= $k ?>"><button class="btn xs ghost" style="color:#b91c1c" title="Permanently remove this company and its data">Remove</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php endif; ?>

<?php if ($sel): $sk = $e($sel['tenant_key']); $selMods = json_decode((string) ($sel['enabled_modules'] ?? '[]'), true) ?: []; ?>
<div class="sc-card">
  <h2>Manage — <?= $e($sel['company'] ?: $sel['tenant_key']) ?></h2>

  <form method="post">
    <input type="hidden" name="do" value="company_plan"><input type="hidden" name="key" value="<?= $sk ?>">
    <div class="sc-edit" style="border-bottom:1px solid var(--line,#eef1f5)">
      <div class="ff"><label>Plan</label>
        <select name="plan"><?php foreach ($plans as $pk => $p): ?><option value="<?= $e($pk) ?>" <?= ($sel['plan'] ?? '') === $pk ? 'selected' : '' ?>><?= $e($p['label']) ?> — <?= (int) $p['base'] ?> base logins</option><?php endforeach; ?></select>
        <div class="sc-note">Sets which modules this company gets and its base seat count. (Applies to the company's live database in the next increment.)</div>
      </div>
      <div class="ff"><label>Plan valid until (optional)</label><input name="plan_expiry" value="<?= $e($sel['plan_expiry'] ?? '') ?>" placeholder="YYYY-MM-DD">
        <div style="margin-top:8px"><button class="btn">Save plan</button></div></div>
    </div>
  </form>

  <form method="post">
    <input type="hidden" name="do" value="company_seats"><input type="hidden" name="key" value="<?= $sk ?>">
    <div class="sc-edit" style="border-bottom:1px solid var(--line,#eef1f5)">
      <div class="ff"><label>Purchased seats (over the plan base)</label>
        <input type="number" name="extra_user_seats" min="0" value="<?= (int) ($sel['extra_user_seats'] ?? 0) ?>">
        <div class="sc-note">Total logins allowed = plan base (<?= (int) saas_plan_logins($sel['plan'] ?? '') ?>) + purchased. Enforcement at sign-in lands in Increment 4.</div></div>
      <div class="ff" style="justify-content:flex-end"><div><button class="btn">Save seats</button></div></div>
    </div>
  </form>

  <form method="post">
    <input type="hidden" name="do" value="company_storage"><input type="hidden" name="key" value="<?= $sk ?>">
    <div class="sc-edit" style="border-bottom:1px solid var(--line,#eef1f5)">
      <div class="ff"><label>Extra storage granted (GB)</label>
        <input type="number" name="storage_extra_gb" min="0" step="0.5" value="<?= $e($sel['storage_extra_gb'] ?? '') ?>" placeholder="none">
        <div class="sc-note">Added on top of what its plan gives (the Storage allowance panel below) and any packs it bought — for a special arrangement with this company.</div></div>
      <div class="ff" style="justify-content:flex-end"><div><button class="btn">Save storage</button></div></div>
    </div>
  </form>

  <form method="post">
    <input type="hidden" name="do" value="company_modules"><input type="hidden" name="key" value="<?= $sk ?>">
    <div style="padding:16px">
      <label style="font-size:12px;font-weight:600">Modules this company has bought</label>
      <div class="sc-mods">
        <?php foreach ($modules as $mk => $m): $core = !empty($m[3]); $on = $core || in_array($mk, $selMods, true); ?>
          <label><input type="checkbox" name="mods[]" value="<?= $e($mk) ?>" <?= $on ? 'checked' : '' ?> <?= $core ? 'disabled' : '' ?>><?= $e($m[0] ?? $mk) ?><?= $core ? ' <span class="sc-key">(always on)</span>' : '' ?></label>
        <?php endforeach; ?>
      </div>
      <div class="sc-note">À-la-carte: tick exactly what they pay for. Saving applies the change to the company's live workspace.</div>
      <button class="btn">Save modules</button>
    </div>
  </form>

  <?php // ---- Data storage & safety: move a file-backed workspace into MySQL ----
    $si = function_exists('saas_tenant_storage_info') ? saas_tenant_storage_info($sel['tenant_key']) : ['type'=>'unknown','at_risk'=>false];
    $fmtSize = function($b){ $b=(int)$b; if($b<1024) return $b.' B'; if($b<1048576) return round($b/1024).' KB'; return round($b/1048576,2).' MB'; };
  ?>
  <div style="padding:16px;border-top:1px solid var(--line,#eef1f5)">
    <label style="font-size:12px;font-weight:600">Where this workspace's data is stored</label>
    <?php if ($si['type'] === 'mysql'): ?>
      <div style="margin-top:8px;display:flex;gap:8px;align-items:center;font-size:13.5px;color:#047857">
        <span style="font-size:16px">🛡️</span><b>MySQL database — safe.</b>
        <span style="color:var(--muted,#6b7280)">A file upload can never touch this data.</span>
      </div>
    <?php elseif ($si['type'] === 'sqlite' && !empty($si['at_risk'])): ?>
      <div style="margin-top:8px;padding:12px 14px;border:1px solid #fca5a5;background:#fef2f2;border-radius:10px">
        <div style="display:flex;gap:8px;align-items:flex-start;font-size:13.5px;color:#b91c1c">
          <span style="font-size:16px;line-height:1.2">⚠️</span>
          <div><b>Stored as a file inside the app folder</b> (<?= $e($fmtSize($si['size'])) ?>,
            <?= (int) $si['rows'] ?> records across <?= (int) $si['tables'] ?> tables).<br>
            <span style="color:#7f1d1d">If you update by <b>deleting every file</b> and re-uploading, this file — and all its data — is deleted with them.
            Move it into MySQL to make this workspace upload-proof. This is <b>safe and reversible</b>: the file is copied, never touched, and kept as a backup.</span>
          </div>
        </div>
        <form method="post" style="margin-top:12px" onsubmit="return confirm('Move <?= $e($sel['company'] ?: $sel['tenant_key']) ?> into MySQL now? The original file is kept as a backup — nothing is deleted.');">
          <input type="hidden" name="do" value="migrate_mysql"><input type="hidden" name="key" value="<?= $sk ?>">
          <div style="display:flex;flex-direction:column;gap:7px;font-size:13px">
            <?php if (!empty($can_autocreate)): ?>
              <label style="display:flex;gap:6px;align-items:center;font-weight:500"><input type="radio" name="mig_mode" value="auto" checked onchange="document.getElementById('mig-manual').style.display='none'"> ✨ Create the MySQL database automatically <span class="sc-key">(recommended)</span></label>
              <label style="display:flex;gap:6px;align-items:center;font-weight:500"><input type="radio" name="mig_mode" value="manual" onchange="document.getElementById('mig-manual').style.display='block'"> Use a MySQL database I created in my hosting panel</label>
            <?php else: ?>
              <input type="hidden" name="mig_mode" value="manual">
              <div class="sc-note" style="margin:0">First create an empty MySQL database and user in your hosting panel (Databases), then paste the details below.</div>
            <?php endif; ?>
          </div>
          <div id="mig-manual" style="<?= !empty($can_autocreate) ? 'display:none;' : '' ?>margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:520px">
            <div class="ff" style="grid-column:1/2"><label>Database host</label><input name="db_host" value="localhost" placeholder="localhost"></div>
            <div class="ff" style="grid-column:2/3"><label>Database name</label><input name="db_name" placeholder="myacc_xyzrecruit"></div>
            <div class="ff" style="grid-column:1/2"><label>Database user</label><input name="db_user" placeholder="myacc_xyz"></div>
            <div class="ff" style="grid-column:2/3"><label>Database password</label><input name="db_pass" type="password" placeholder="••••••••"></div>
          </div>
          <div style="margin-top:10px"><button class="btn" style="background:#b91c1c">🛡️ Move to MySQL now</button></div>
        </form>
      </div>
    <?php elseif ($si['type'] === 'sqlite'): ?>
      <div style="margin-top:8px;font-size:13.5px;color:var(--muted,#6b7280)">File (SQLite), <?= $e($fmtSize($si['size'])) ?> — stored outside the app folder, so a normal upload will not remove it.</div>
    <?php else: ?>
      <div style="margin-top:8px;font-size:13.5px;color:var(--muted,#6b7280)">Not wired up yet.</div>
    <?php endif; ?>
  </div>

  <?php // ---- Live à-la-carte quote: modules + seats -> price, monthly / yearly ----
    $pb = $price_book ?? ['seat'=>['month'=>0,'year'=>0],'modules'=>[],'currency'=>'INR'];
    $cur = ($pb['currency'] === 'INR') ? '₹' : ($e($pb['currency']) . ' ');
    $base = function_exists('saas_plan_logins') ? saas_plan_logins($sel['plan'] ?? '') : 0;
  ?>
  <div style="padding:16px;border-top:1px solid var(--line,#eef1f5);background:var(--soft,#f6f8fb)">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <label style="font-size:12px;font-weight:600">Price for this configuration</label>
      <label style="font-size:12.5px">Bill: <select id="scq-period" style="padding:4px 8px;border:1px solid var(--line,#d7dde5);border-radius:7px"><option value="month">Monthly</option><option value="year">Annual</option></select></label>
    </div>
    <div id="scq-lines" style="margin:8px 0 6px;font-size:13px"></div>
    <div id="scq-total" style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:20px"></div>
    <div class="sc-note">Seats counted = plan base (<?= (int) $base ?>) + purchased (the box above). Prices are set on <a href="/pricing-usage">Plans, pricing &amp; usage</a>; "not set" items are not for sale. A customer pays this via your Razorpay checkout.</div>
  </div>
  <script>
  (function(){
    var PB=<?= json_encode($pb, JSON_UNESCAPED_SLASHES) ?>, BASE=<?= (int) $base ?>, CUR=<?= json_encode($cur) ?>;
    function money(n){ return CUR + Number(n||0).toLocaleString(); }
    function calc(){
      var per=document.getElementById('scq-period').value==='year'?'year':'month';
      var mods=[].slice.call(document.querySelectorAll('input[name="mods[]"]:checked')).map(function(c){return c.value;});
      var extra=parseInt((document.querySelector('input[name=extra_user_seats]')||{}).value||'0',10)||0;
      var seats=Math.max(0,BASE+extra), lines=[], total=0;
      Object.keys(PB.modules).forEach(function(k){ if(mods.indexOf(k)>=0){ var a=PB.modules[k][per]; total+=a; lines.push([PB.modules[k].label, a]); } });
      var sa=seats*PB.seat[per]; total+=sa; lines.push([seats+' seat'+(seats===1?'':'s'), PB.seat[per] > 0 ? sa : 0]);
      document.getElementById('scq-lines').innerHTML=lines.map(function(l){return '<div style="display:flex;justify-content:space-between"><span>'+l[0]+'</span><span>'+(l[1] > 0 ? money(l[1]) : 'not set')+'</span></div>';}).join('');
      document.getElementById('scq-total').textContent=money(total)+' / '+(per==='year'?'year':'month');
    }
    document.getElementById('scq-period').addEventListener('change',calc);
    document.querySelectorAll('input[name="mods[]"]').forEach(function(c){c.addEventListener('change',calc);});
    var sb=document.querySelector('input[name=extra_user_seats]'); if(sb) sb.addEventListener('input',calc);
    calc();
  })();
  </script>
</div>
<?php endif; ?>

<?php // ---- Self-serve sign-up (super-admin): the owner's rules of 22 Oct 2026 -------
  $ssc = $ss_cfg ?? null; if ($ssc): $raw = fn($k) => (string) setting_get($k, ''); ?>
<details class="sc-card" style="padding:0" <?= empty($ss_on) ? '' : 'open' ?>>
  <summary style="cursor:pointer;padding:13px 16px;font-size:14px;font-weight:600;background:var(--soft,#f6f8fb);border-bottom:1px solid var(--line,#e5e7eb)">🚀 Self sign-up &amp; free trial — <?= !empty($ss_on) ? 'ON' : 'OFF' ?></summary>
  <form method="post" style="padding:16px">
    <input type="hidden" name="do" value="selfserve_save">
    <?php if (empty($ss_ready)): ?><div class="msg msg-warning">Cloud mode is not switched on yet (Admin → Super Admin → Cloud workspaces → Base domain). Until it is, the sign-up page stays closed.</div><?php endif; ?>
    <label style="display:flex;gap:9px;align-items:flex-start;margin-bottom:12px;font-size:13.5px"><input type="checkbox" name="ss_on" value="1" <?= !empty($ss_on) ? 'checked' : '' ?> style="margin-top:2px">
      <span><b>Companies can sign themselves up</b> at <code>/start</code>: they confirm their e-mail with a code and go straight into their own workspace on a free trial of every module.
        (Off = <?= function_exists('tenant_signup_enabled') && tenant_signup_enabled() ? 'they apply at <code>/get-started</code> and you approve' : 'nobody can sign up online — only you add companies' ?>. All three choices are on <a href="/tenants">Cloud workspaces</a>.)</span></label>
    <div class="sc-edit" style="padding:0;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
      <div class="ff"><label>Free trial (days)</label><input type="number" name="ss_trial_days"<?= commercial_suggest_attr('saas_trial_days') ?> min="1" value="<?= $e($raw('saas_trial_days')) ?>" placeholder="<?= (int) SELFSERVE_TRIAL_DAYS_DEFAULT ?>"><?= commercial_hint('saas_trial_days') ?></div>
      <div class="ff"><label>Trial storage limit (MB)</label><input type="number" name="ss_storage_mb"<?= commercial_suggest_attr('saas_trial_storage_mb') ?> min="0" value="<?= $e($raw('saas_trial_storage_mb')) ?>" placeholder="not set — no limit"><?= commercial_hint('saas_trial_storage_mb') ?></div>
      <div class="ff"><label>Logins during the trial</label><input type="number" name="ss_trial_seats"<?= commercial_suggest_attr('saas_trial_seats') ?> min="0" value="<?= $e($raw('saas_trial_seats')) ?>" placeholder="not set — no limit"><?= commercial_hint('saas_trial_seats') ?></div>
      <div class="ff"><label>Warn unused trials every (days)</label><input type="number" name="ss_notice_days"<?= commercial_suggest_attr('saas_inactive_notice_days') ?> min="1" value="<?= $e($raw('saas_inactive_notice_days')) ?>" placeholder="<?= (int) SELFSERVE_NOTICE_DAYS_DEFAULT ?>"><?= commercial_hint('saas_inactive_notice_days') ?></div>
      <div class="ff"><label>Delete unused trials after (days)</label><input type="number" name="ss_delete_days"<?= commercial_suggest_attr('saas_inactive_delete_days') ?> min="1" value="<?= $e($raw('saas_inactive_delete_days')) ?>" placeholder="<?= (int) SELFSERVE_DELETE_DAYS_DEFAULT ?>"><?= commercial_hint('saas_inactive_delete_days') ?></div>
    </div>
    <label style="display:flex;gap:9px;align-items:flex-start;margin:12px 0;font-size:13.5px"><input type="checkbox" name="ss_autodelete" value="1" <?= $ssc['auto_delete'] ? 'checked' : '' ?> style="margin-top:2px">
      <span><b>Delete unused free trials automatically.</b> Only companies that signed themselves up and never paid. Each gets an e-mail every <?= (int) $ssc['notice_days'] ?> days of no use; it is deleted after <?= (int) $ssc['delete_days'] ?> days, and never sooner than <?= (int) $ssc['notice_days'] ?> days after its first warning. A company that has ever paid is never deleted — it becomes view-only.</span></label>
    <div class="sc-actions"><button class="btn">Save</button> <button type="button" class="btn ghost cm-fill">Fill in the suggested values</button></div>
    <div class="sc-note">After the trial a company becomes view-only until it chooses a plan: it can open, print and export everything, but not save. Prices come from the panel below, and payment from your Razorpay keys (Billing).</div>
  </form>
  <form method="post" style="padding:0 16px 16px"><input type="hidden" name="do" value="selfserve_sync"><button class="btn ghost xs">Check when every company was last used</button>
    <span class="sc-key">Refreshes "last used" and plan dates. Sends nothing, deletes nothing. It also runs every night.</span></form>
  <?php if (!empty($ss_deleted)): ?>
  <div class="sc-scroll" style="padding:0 16px 16px"><table class="sc" style="min-width:520px">
    <tr><th>Deleted</th><th>Company</th><th>Owner</th><th>Why</th></tr>
    <?php foreach ($ss_deleted as $d): ?><tr><td><?= $e(fdate(substr((string) $d['deleted_at'], 0, 10))) ?></td><td><?= $e($d['company']) ?></td><td class="sc-key"><?= $e($d['owner_email']) ?></td><td><?= $e($d['reason']) ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</details>
<?php endif; ?>

<?php // ---- Storage allowance (super-admin) — owner, 22 Oct 2026 --------------------
  if ($ssc): $raw2 = fn($k) => (string) setting_get($k, ''); ?>
<details class="sc-card" style="padding:0">
  <summary style="cursor:pointer;padding:13px 16px;font-size:14px;font-weight:600;background:var(--soft,#f6f8fb);border-bottom:1px solid var(--line,#e5e7eb)">💾 Storage allowance — how much each company may store</summary>
  <form method="post" style="padding:16px">
    <input type="hidden" name="do" value="storage_save">
    <p class="sc-note" style="margin-top:0">A company on a plan may store: <b>base</b> + <b>GB per login</b> × its logins + the <b>extra packs</b> it buys + any extra you grant it (Manage). Leave everything empty for no limit. At the limit only NEW FILES are refused — records still save and nothing is ever deleted. Owners are e-mailed at 80%, 95% and 100%. (Free trials use the trial storage limit above.)</p>
    <div class="sc-edit" style="padding:0;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
      <div class="ff"><label>Base storage per company (GB)</label><input type="number" min="0" step="0.5" name="st_base_gb"<?= commercial_suggest_attr('saas_storage_gb_base') ?> value="<?= $e($raw2('saas_storage_gb_base')) ?>" placeholder="not set"><?= commercial_hint('saas_storage_gb_base') ?></div>
      <div class="ff"><label>Storage per login (GB)</label><input type="number" min="0" step="0.5" name="st_login_gb"<?= commercial_suggest_attr('saas_storage_gb_per_login') ?> value="<?= $e($raw2('saas_storage_gb_per_login')) ?>" placeholder="not set"><?= commercial_hint('saas_storage_gb_per_login') ?></div>
      <div class="ff"><label>Extra storage pack size (GB)</label><input type="number" min="0" step="0.5" name="st_pack_gb"<?= commercial_suggest_attr('saas_storage_pack_gb') ?> value="<?= $e($raw2('saas_storage_pack_gb')) ?>" placeholder="not offered"><?= commercial_hint('saas_storage_pack_gb') ?></div>
      <div class="ff"><label>Pack price per month</label><input type="number" min="0" name="st_pack_month"<?= commercial_suggest_attr('saas_storage_pack_price_month') ?> value="<?= $e($raw2('saas_storage_pack_price_month')) ?>" placeholder="not offered"><?= commercial_hint('saas_storage_pack_price_month') ?></div>
      <div class="ff"><label>Pack price per year</label><input type="number" min="0" name="st_pack_year"<?= commercial_suggest_attr('saas_storage_pack_price_year') ?> value="<?= $e($raw2('saas_storage_pack_price_year')) ?>" placeholder="not offered"><?= commercial_hint('saas_storage_pack_price_year') ?></div>
    </div>
    <div class="sc-actions" style="margin-top:12px"><button class="btn">Save and apply to every company</button> <button type="button" class="btn ghost cm-fill">Fill in the suggested values</button></div>
  </form>
</details>
<?php endif; ?>

<?php // Prices live in ONE place: Plans, pricing & usage (lib/commercial.php). ?>
<a class="sc-card" href="/pricing-usage" style="display:block;padding:13px 16px;font-size:14px;font-weight:600;text-decoration:none;color:inherit">💳 Pricing &amp; GST — what each module and login costs →
  <span class="sc-key" style="display:block;font-weight:400">Set on Plans, pricing &amp; usage, with suggested values from market research.</span></a>
<script>
document.querySelectorAll('.cm-fill').forEach(function(b){ b.addEventListener('click', function(){
  var n = 0; b.closest('form').querySelectorAll('[data-suggest]').forEach(function(el){
    if (el.value === '') { el.value = el.dataset.suggest; el.style.background = '#fffbe6'; n++; } });
  b.textContent = n ? n + ' filled — check, then Save' : 'Nothing empty to fill'; }); });
</script>
