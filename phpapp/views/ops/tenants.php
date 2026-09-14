<?php
  $reg = $reg ?? ['base_domain' => '', 'tenants' => []];
  $base = (string)($reg['base_domain'] ?? '');
  $tenants = (array)($reg['tenants'] ?? []);
  $on = $base !== '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Cloud workspaces</div>
<div class="master-head">
  <div><h1>Cloud workspaces</h1>
    <p class="sub" style="margin:2px 0 0">Run one copy of the app as many separate businesses — each on its own
      web address (subdomain) and its own database, seeing none of the others. This is the control site; each
      workspace is a full, independent install with its own admin, licence and data.</p></div>
</div>

<?php if (!$on): ?>
<div class="panel settings-form" style="max-width:640px">
  <h3 class="tab-sub" style="margin-top:0">Switch cloud mode on</h3>
  <p class="sub" style="margin:0 0 10px">Tell the app the domain your workspaces will live under. After this, a
    visit to <code>anything.&lt;your-domain&gt;</code> becomes its own workspace, and this main address stays the
    control site where you manage them.</p>
  <form method="post" action="/tenant-enable">
    <div class="ff"><label>Base domain</label>
      <input class="form-control" name="base_domain" placeholder="operations.yourcompany.com" required>
      <small class="muted">The address this control site runs on. Workspaces will be <code>acme.operations.yourcompany.com</code>, and so on.</small></div>
    <button class="btn" type="submit" style="margin-top:10px">Switch cloud mode on</button>
  </form>
  <p class="muted" style="margin-top:12px;font-size:12.5px">Nothing changes for your current data — it stays the
    control site. You can switch this off later by deleting <code>tenants.php</code> in cPanel.</p>
</div>
<?php else: ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Workspaces <span class="muted">(<?= count($tenants) ?>)</span></h3>
    <span class="muted" style="font-size:13px">Base domain: <strong><?= e($base) ?></strong></span></div>
  <table class="dt">
    <thead><tr><th>Workspace</th><th>Company</th><th>Database</th><th>State</th><th></th></tr></thead>
    <tbody>
    <?php if (!$tenants): ?><tr><td colspan="5" class="muted">No workspaces yet — add one below.</td></tr><?php endif; ?>
    <?php foreach ($tenants as $sub => $t): $susp = ($t['status'] ?? 'active') === 'suspended';
          $addr = $sub . '.' . $base; ?>
      <tr<?= $susp ? ' style="opacity:.6"' : '' ?>>
        <td><strong><?= e($sub) ?></strong><br><a class="muted" style="font-size:12px" href="https://<?= e($addr) ?>/" target="_blank" rel="noopener"><?= e($addr) ?> →</a></td>
        <td><?= e($t['company'] ?? $sub) ?></td>
        <td class="muted"><?= e(!empty($t['sqlite']) ? 'SQLite' : (($t['db']['name'] ?? '') ?: '—')) ?></td>
        <td><?= $susp ? '<span class="pill p-bad">suspended</span>' : '<span class="pill p-ok">active</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/tenant-status" style="display:inline">
            <input type="hidden" name="sub" value="<?= e($sub) ?>">
            <input type="hidden" name="status" value="<?= $susp ? 'active' : 'suspended' ?>">
            <button class="btn small secondary" type="submit"><?= $susp ? 'Resume' : 'Suspend' ?></button></form>
          <form method="post" action="/tenant-remove" style="display:inline" onsubmit="return confirm('Remove <?= e($sub) ?> from the list? Its database is NOT deleted.')">
            <input type="hidden" name="sub" value="<?= e($sub) ?>">
            <button class="btn small danger" type="submit">Remove</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
  // --- Workspace requests inbox (a new company applied for itself) ---
  $requests = (array)($requests ?? []);
  $signupOn = !empty($signup_on);
  $statuses = (array)($req_statuses ?? []);
  $pending  = array_values(array_filter($requests, fn($r) => ($r['status'] ?? '') === 'PENDING'));
  $pillFor = function($s) {
    switch ($s) {
      case 'PENDING':     return '<span class="pill p-warn">pending</span>';
      case 'APPROVED':    return '<span class="pill p-mut">approved — set up</span>';
      case 'PROVISIONED': return '<span class="pill p-ok">workspace created</span>';
      case 'REJECTED':    return '<span class="pill p-bad">declined</span>';
    }
    return '<span class="pill p-mut">' . e($s) . '</span>';
  };
?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Workspace requests
      <?php if ($pending): ?><span class="pill p-warn"><?= count($pending) ?> new</span><?php else: ?><span class="muted">(<?= count($requests) ?>)</span><?php endif; ?></h3>
    <form method="post" action="/workspace-signup-toggle" style="margin:0">
      <input type="hidden" name="on" value="<?= $signupOn ? '0' : '1' ?>">
      <button class="btn small <?= $signupOn ? 'secondary' : '' ?>" type="submit">
        <?= $signupOn ? 'Online registration: ON — turn off' : 'Online registration: OFF — turn on' ?></button>
    </form>
  </div>
  <p class="sub" style="margin:0 0 10px">When online registration is on, a new inspection company can apply for its own
    workspace at <code>/get-started</code>. Each request waits here for you to approve; approving creates the workspace
    (automatically if the cPanel API is set up, otherwise you finish it with the two steps below).</p>
  <table class="dt">
    <thead><tr><th>Company</th><th>Contact</th><th>Wants</th><th>State</th><th></th></tr></thead>
    <tbody>
    <?php if (!$requests): ?><tr><td colspan="5" class="muted">No requests yet.</td></tr><?php endif; ?>
    <?php foreach ($requests as $r): $st = (string)($r['status'] ?? 'PENDING'); ?>
      <tr>
        <td><strong><?= e($r['company'] ?? '') ?></strong>
          <?php if (!empty($r['note'])): ?><br><span class="muted" style="font-size:12px"><?= e($r['note']) ?></span><?php endif; ?></td>
        <td><?= e($r['contact_name'] ?? '') ?><br>
          <a class="muted" style="font-size:12px" href="mailto:<?= e($r['email'] ?? '') ?>"><?= e($r['email'] ?? '') ?></a>
          <?php if (!empty($r['phone'])): ?><br><span class="muted" style="font-size:12px"><?= e($r['phone']) ?></span><?php endif; ?></td>
        <?php $reqSub = (string)($r['sub'] ?? ''); $taken = isset($tenants[$reqSub]); ?>
        <td><code><?= e($reqSub) ?></code>.<?= e($base) ?>
          <?php if ($taken && $st === 'PENDING'): ?><br><span class="pill p-warn" style="font-size:11px">name already used — rename below</span><?php endif; ?>
          <?php if (!empty($r['admin_note'])): ?><br><span class="muted" style="font-size:11.5px"><?= e($r['admin_note']) ?></span><?php endif; ?></td>
        <td><?= $pillFor($st) ?></td>
        <td style="white-space:nowrap">
          <?php if ($st === 'PENDING'): ?>
            <form method="post" action="/tenant-request-approve" style="display:inline-flex;gap:5px;align-items:center"
                  onsubmit="return confirm('Approve “<?= e($r['company'] ?? '') ?>” and create this workspace?')">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input name="sub" value="<?= e($reqSub) ?>" pattern="[a-z0-9-]+" title="lowercase letters, digits, hyphens"
                     style="width:110px;font-size:13px;padding:6px 8px;border:1px solid var(--line, #ccc);border-radius:8px"
                     aria-label="Workspace name">
              <button class="btn small" type="submit">Approve</button></form>
            <form method="post" action="/tenant-request-reject" style="display:inline"
                  onsubmit="return confirm('Decline this request?')">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small danger" type="submit">Decline</button></form>
          <?php elseif ($st === 'APPROVED'): ?>
            <form method="post" action="/tenant-request-provisioned" style="display:inline">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small secondary" type="submit">Mark set up</button></form>
          <?php else: ?>
            <span class="muted" style="font-size:12px"><?= e($r['decided_by'] ?? '') ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
  // Automatic databases, in the order worth trying. This sits ABOVE "Add a
  // workspace" on purpose: that form asks for database details, and whether
  // they are needed at all is decided here. Answering the question after
  // presenting the manual form is how an optional step reads as a required one.
  $selfOn  = function_exists('setting_get') && (string) setting_get('saas_db_selfcreate', '') === '1';
  $selfRun = function_exists('setting_get') && (string) setting_get('saas_db_selfcreate_at', '') !== '';
  $autoAny = function_exists('saas_can_autocreate_db') && saas_can_autocreate_db();
?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Automatic databases for new companies
    <?= $autoAny ? '<span class="pill p-ok">on</span>' : '<span class="pill p-mut">off</span>' ?></h3>
  <p class="sub" style="margin:8px 0 12px">Each company gets its own separate database &mdash; that separation is what stops
    one client ever seeing another's data. The only question is who creates it: this app, or you by hand in your hosting panel.</p>

  <?php if ($selfOn): ?>
    <div style="padding:12px 14px;border:1px solid #a7f3d0;background:#ecfdf5;border-radius:10px;font-size:13.5px;color:#065f46">
      <b>&#128737;&#65039; On &mdash; this server lets the app create databases itself.</b>
      Every new company gets its own MySQL database automatically. Nothing to do per client.
    </div>
  <?php else: ?>
    <div style="padding:12px 14px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:10px;font-size:13.5px;color:#1e3a8a">
      <b>Try this first &mdash; it takes ten seconds.</b><br>
      Some hosting lets an application create its own databases and some does not, and the only way to find out is to ask.
      This test creates one empty database with a random name and deletes it again immediately. Nothing you already have is
      read, changed or removed.
      <?php if ($selfRun && !$selfOn): ?><br><span style="color:#7f1d1d"><b>Last answer: no.</b> Your hosting does not allow it &mdash; use one of the options below.</span><?php endif; ?>
    </div>
    <form method="post" action="/db-selfcreate-test" style="margin-top:10px">
      <button class="btn" type="submit">Test whether this server can create databases</button>
    </form>
  <?php endif; ?>

  <?php if (!$autoAny): ?>
    <div class="sub" style="margin-top:14px;font-size:13px">
      <b>If the answer is no</b>, you have two ways to keep every client's data safe, and both are already built in:
      <ul style="margin:6px 0 0 18px;padding:0">
        <li><b>Do nothing</b> &mdash; each company's data is kept in its own private file <b>outside the app folder</b>,
          backed up daily. Re-uploading the app cannot reach it. Fine for a growing client list.</li>
        <li><b>Create a database per client by hand</b> in your hosting panel (Databases), then paste its details when you
          add the company. Strongest, about two minutes each.</li>
      </ul>
    </div>
  <?php endif; ?>
</div>

<?php $cpOn = function_exists('cpanel_configured') && cpanel_configured();
      $cp = function_exists('cpanel_config') ? cpanel_config() : [];
      $appRoot = realpath(__DIR__ . '/../..') ?: ''; ?>
<div class="panel settings-form">
  <h3 class="tab-sub" style="margin-top:0">Add a workspace</h3>
  <form method="post" action="/tenant-add">
    <div class="form-grid">
      <div class="ff"><label>Workspace name <span class="muted">— the subdomain</span></label>
        <input class="form-control" name="sub" placeholder="acme" pattern="[a-z0-9-]+" required>
        <small class="muted">Lowercase letters, digits, hyphens. Becomes <code>&lt;name&gt;.<?= e($base) ?></code>.</small></div>
      <div class="ff"><label>Company name</label><input class="form-control" name="company" placeholder="Acme Inspection Pvt Ltd"></div>
    </div>

    <?php if ($cpOn): ?>
      <div class="ff ff-wide" style="margin:8px 0 2px">
        <label class="chk"><input type="radio" name="db_kind" value="auto" checked onclick="tnMode(1)">
          <strong>Create the database automatically</strong> with cPanel<?= !empty($cp['make_subdomain']) ? ' — and the subdomain' : '' ?></label>
        <label class="chk" style="margin-top:4px"><input type="radio" name="db_kind" value="mysql" onclick="tnMode(0)">
          I created the database myself</label>
      </div>
      <div id="tn-auto"><p class="muted" style="font-size:12.5px;margin:2px 2px 0">cPanel makes the database, its user and privileges<?= !empty($cp['make_subdomain']) ? ', and the subdomain,' : '' ?> for you. The app builds the tables on first visit.</p></div>
    <?php else: ?>
      <input type="hidden" name="db_kind" value="mysql">
      <p class="muted" style="font-size:12.5px;margin:8px 2px 4px">Create the database and subdomain in your hosting panel first (steps below), then fill in the details. If the test above said this server can create databases itself, use <b>Companies &rarr; Add a company</b> instead &mdash; it does all of this for you.</p>
    <?php endif; ?>

    <div id="tn-manual"<?= $cpOn ? ' style="display:none"' : '' ?>>
      <ol class="muted" style="font-size:13px;line-height:1.7;margin:6px 0 12px;padding-left:20px">
        <li><strong>Your hosting panel → Databases</strong> — create a database and a user, add the user with all privileges. The panel adds its own prefix to both names; copy them exactly as it shows them.</li>
        <li><strong>Your hosting panel → Domains / Subdomains</strong> — create <code>&lt;name&gt;.<?= e($base) ?></code>, document root pointing at <strong>this app's folder</strong>.</li>
        <li>Fill in the details. The app builds the tables on first visit.</li>
      </ol>
      <div class="form-grid">
        <div class="ff"><label>Database name</label><input class="form-control" name="db_name" placeholder="youracct_acme"></div>
        <div class="ff"><label>Database user</label><input class="form-control" name="db_user" placeholder="youracct_acme"></div>
        <div class="ff"><label>Database password</label><input class="form-control" type="password" name="db_pass" autocomplete="new-password"></div>
        <div class="ff"><label>Host</label><input class="form-control" name="db_host" value="localhost"></div>
      </div>
    </div>
    <button class="btn" type="submit" style="margin-top:10px">Add workspace</button>
    <span class="muted" style="margin-left:8px;font-size:13px">The database is tested (or created) before the workspace goes live.</span>
  </form>
</div>
<script>function tnMode(auto){var m=document.getElementById('tn-manual');if(m)m.style.display=auto?'none':'block';var a=document.getElementById('tn-auto');if(a)a.style.display=auto?'block':'none';}</script>

<details class="panel" id="cpanelcfg"<?= $cpOn ? '' : ' open' ?>>
  <summary style="cursor:pointer;font-weight:600">Automatic provisioning — cPanel API
    <?= $cpOn ? '<span class="pill p-ok">configured</span>' : '<span class="pill p-mut">off</span>' ?></summary>
  <p class="sub" style="margin:10px 0">Give the app a cPanel API token and it can create each workspace's database (and
    subdomain) for you. In cPanel → <strong>Manage API Tokens</strong>, create a token; paste it below. It has the
    same power as your cPanel login, so it is kept on this control site only and shown masked.</p>
  <?php if (!$cpOn): ?>
  <div style="margin:10px 0 14px;padding:12px 14px;border:1px solid #fcd34d;background:#fffbeb;border-radius:10px;font-size:13.5px;color:#78350f">
    <b>&#9888;&#65039; This also decides how safe each client's data is.</b><br>
    Without it, a new workspace's data is kept in a <b>file</b> on the server. With it, every new workspace gets its
    <b>own real MySQL database</b> &mdash; which no file upload, however careless, can delete. If you can connect
    cPanel, do it before adding your next client.
  </div>
  <?php else: ?>
  <div style="margin:10px 0 14px;padding:12px 14px;border:1px solid #a7f3d0;background:#ecfdf5;border-radius:10px;font-size:13.5px;color:#065f46">
    <b>&#128737;&#65039; Connected &mdash; new workspaces get their own MySQL database automatically.</b>
    Their data is stored in the database, not as a file in this folder, so an upload can never delete it.
  </div>
  <?php endif; ?>
  <form method="post" action="/cpanel-save">
    <div class="form-grid">
      <div class="ff"><label>cPanel host</label><input class="form-control" name="cpanel_host" value="<?= e($cp['host'] ?? '') ?>" placeholder="server.yourhost.com or your domain"></div>
      <div class="ff"><label>Port</label><input class="form-control" type="number" name="cpanel_port" value="<?= e((string)($cp['port'] ?? 2083)) ?>"></div>
      <div class="ff"><label>cPanel username</label><input class="form-control" name="cpanel_user" value="<?= e($cp['user'] ?? '') ?>" placeholder="youracct" autocomplete="off"></div>
      <div class="ff"><label>API token</label><input class="form-control" type="password" name="cpanel_token" autocomplete="new-password" placeholder="<?= !empty($cp['token']) ? '•••••••• (leave blank to keep)' : 'paste the token' ?>"></div>
      <div class="ff"><label>Root domain <span class="muted">— for subdomains</span></label><input class="form-control" name="cpanel_rootdomain" value="<?= e($cp['base_domain'] ?? $base) ?>" placeholder="<?= e($base) ?>"></div>
      <div class="ff"><label>App document root</label><input class="form-control" name="cpanel_docroot" value="<?= e($cp['docroot'] ?? $appRoot) ?>" placeholder="<?= e($appRoot) ?>"><small class="muted">The folder holding index.php — new subdomains point here.</small></div>
    </div>
    <div class="ff ff-check" style="margin-top:6px"><label><input type="checkbox" name="cpanel_make_subdomain" value="1" <?= !empty($cp['make_subdomain']) ? 'checked' : '' ?>> Also create the subdomain automatically</label></div>
    <div class="ff ff-check"><label><input type="checkbox" name="cpanel_verify_ssl" value="1" <?= (!isset($cp['verify_ssl']) || $cp['verify_ssl']) ? 'checked' : '' ?>> Verify the cPanel SSL certificate</label>
      <small class="muted">Leave on. Untick only if your cPanel uses a self-signed certificate and calls fail.</small></div>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <button class="btn" type="submit">Save cPanel settings</button>
    </div>
  </form>
  <form method="post" action="/cpanel-test" style="margin-top:8px">
    <button class="btn secondary" type="submit">Test connection</button>
    <span class="muted" style="margin-left:8px;font-size:13px">Checks the token by asking cPanel to list databases.</span>
  </form>
</details>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">How billing works per workspace</h3>
  <p class="sub" style="margin:0">Each workspace is its own install, so it carries its own <a href="/licence">licence</a> and its own
    Razorpay/payment settings. Issue or activate a licence inside each workspace exactly as you would for a single
    business — suspending a workspace here stops access immediately, whatever its licence says.</p>
</div>
<?php endif; ?>
