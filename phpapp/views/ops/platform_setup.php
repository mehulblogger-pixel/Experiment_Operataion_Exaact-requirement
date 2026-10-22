<?php
// Platform setup — the platform owner's go-live checklist, the platform e-mail
// (general + account & password senders), the payment keys check and the
// recent e-mails, and Super Admin's own address. Data: $mail, $items, $log, $pay, $me, $enc, $adm.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$done = count(array_filter($items, fn($i) => !empty($i['ok']))); $all = count($items);
$ch = (array) ($mail['checks'] ?? []);
$g = $mail['general']; $a = $mail['accounts'];
$adm = (array) ($adm ?? []); $ah = (string) ($adm['host'] ?? '');
?>
<style>
  .ps-wrap{max-width:980px}
  .ps-card{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;margin-bottom:16px;overflow:hidden}
  .ps-card h2{margin:0;padding:13px 16px;font-size:15px;border-bottom:1px solid var(--line,#e5e7eb);background:var(--soft,#f6f8fb)}
  .ps-body{padding:16px}
  .ps-list{list-style:none;margin:0;padding:0}
  .ps-list li{display:flex;gap:12px;align-items:flex-start;padding:11px 16px;border-bottom:1px solid var(--line,#eef1f5)}
  .ps-list li:last-child{border-bottom:0}
  .ps-mk{flex:0 0 26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px}
  .ps-mk.ok{background:#dcfce7;color:#166534}.ps-mk.no{background:#fef3c7;color:#92400e}
  .ps-l{font-weight:600;font-size:14px}.ps-h{color:var(--muted,#656e7a);font-size:13px;margin-top:2px}
  .ps-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
  .ps-grid label{display:block;font-size:12px;font-weight:600;margin-bottom:4px}
  .ps-grid input{width:100%;padding:9px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px;font:inherit}
  .ps-sub{font-size:13px;font-weight:700;margin:16px 0 8px}
  .ps-note{color:var(--muted,#656e7a);font-size:12.5px;margin:8px 0 0}
  .ps-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}
  .ps-row input{padding:9px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px;font:inherit;min-width:240px}
  table.ps-log{width:100%;border-collapse:collapse;min-width:640px}
  table.ps-log td,table.ps-log th{padding:7px 10px;border-bottom:1px solid var(--line,#eef1f5);font-size:12.5px;text-align:left;vertical-align:top}
  table.ps-log th{font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a)}
  .ps-ok{color:#166534;font-weight:700}.ps-bad{color:#b91c1c;font-weight:700}
</style>
<div class="ps-wrap">
<div class="master-head"><div><h1>Platform setup</h1>
  <p class="sub" style="margin:2px 0 0">Everything that must work before companies sign up and pay — <?= $done ?> of <?= $all ?> ready.</p></div></div>

<div class="ps-card">
  <h2>Go-live checklist</h2>
  <ul class="ps-list">
    <?php foreach ($items as $i): ?>
      <li><span class="ps-mk <?= $i['ok'] ? 'ok' : 'no' ?>" aria-label="<?= $i['ok'] ? 'Done' : 'To do' ?>"><?= $i['ok'] ? '✓' : '!' ?></span>
        <div><div class="ps-l"><?= $e($i['label']) ?></div>
          <div class="ps-h"><?= $e($i['how']) ?><?php if (!$i['ok'] && $i['link'] !== ''): ?> <a href="<?= $e($i['link']) ?>">Open →</a><?php endif; ?></div></div></li>
    <?php endforeach; ?>
  </ul>
</div>

<form class="ps-card" method="post" id="mail">
  <input type="hidden" name="do" value="mail_save">
  <h2>Platform e-mail — used by every company that has not set up its own</h2>
  <div class="ps-body">
    <p class="ps-note" style="margin-top:0">Your mail provider gives you these (for Microsoft 365: smtp.office365.com, port 587, the mailbox address and its password).
      Kept in the protected data folder on the server — never in any company's database, so no company can see it<?= $enc ? ', and the passwords are encrypted' : '' ?>.
      A company that fills in its own e-mail under its Settings uses its own instead.</p>
    <div class="ps-grid">
      <div><label for="ps-host">Mail server (SMTP host)</label><input id="ps-host" name="host" value="<?= $e($mail['host']) ?>" placeholder="smtp.office365.com"></div>
      <div><label for="ps-port">Port</label><input id="ps-port" name="port" type="number" value="<?= (int) $mail['port'] ?>"></div>
    </div>
    <div class="ps-sub">1 · General e-mails — reminders, trial and storage notices, approvals</div>
    <div class="ps-grid">
      <div><label>Sender name</label><input name="g_name" value="<?= $e($g['name']) ?>" placeholder="EXAACT"></div>
      <div><label>From address</label><input name="g_from" type="email" value="<?= $e($g['from']) ?>" placeholder="noreply@your-domain"></div>
      <div><label>Mailbox login (username)</label><input name="g_user" value="<?= $e($g['user']) ?>" autocomplete="off"></div>
      <div><label>Mailbox password</label><input name="g_pass" type="password" autocomplete="new-password" placeholder="<?= $g['pass'] !== '' ? 'saved — leave blank to keep' : '' ?>"></div>
    </div>
    <div class="ps-sub">2 · Account &amp; password e-mails — sign-up codes, temporary passwords, password-reset links</div>
    <div class="ps-grid">
      <div><label>Sender name</label><input name="a_name" value="<?= $e($a['name']) ?>" placeholder="EXAACT Accounts"></div>
      <div><label>From address</label><input name="a_from" type="email" value="<?= $e($a['from']) ?>" placeholder="accounts@your-domain"></div>
      <div><label>Mailbox login <span style="font-weight:400">— blank = same as general</span></label><input name="a_user" value="<?= $e($a['user']) ?>" autocomplete="off"></div>
      <div><label>Mailbox password</label><input name="a_pass" type="password" autocomplete="new-password" placeholder="<?= $a['pass'] !== '' ? 'saved — leave blank to keep' : '' ?>"></div>
    </div>
    <p class="ps-note">Leave the login blank when the account address is an alias the general mailbox may "send as" (your mail admin sets that up). Inside a company, readers see the company's name, and replies go to the company's own e-mail.</p>
    <div class="ps-row"><button class="btn">Save platform e-mail</button></div>
  </div>
</form>

<div class="ps-card">
  <h2>Send a test e-mail</h2>
  <div class="ps-body">
    <?php foreach (['general' => 'General sender', 'accounts' => 'Account & password sender'] as $w => $lab): $c = $ch['mail_' . $w] ?? null; ?>
      <form method="post" class="ps-row">
        <input type="hidden" name="do" value="mail_test"><input type="hidden" name="which" value="<?= $w ?>">
        <strong style="min-width:200px"><?= $e($lab) ?></strong>
        <input name="to" type="email" value="<?= $e($me) ?>" placeholder="send the test to…" aria-label="Send the test to">
        <button class="btn ghost">Send a test e-mail</button>
        <?php if ($c): ?><span class="<?= !empty($c['ok']) ? 'ps-ok' : 'ps-bad' ?>"><?= !empty($c['ok']) ? 'Sent' : 'Failed' ?></span>
          <span class="ps-note" style="margin:0"><?= $e(substr((string) $c['at'], 0, 16) . ' — ' . $c['msg']) ?></span><?php endif; ?>
      </form>
    <?php endforeach; ?>
    <p class="ps-note">"Sent" means your mail server accepted it. Open the inbox (and spam folder) to be sure it arrived, and check the "from" address on it.</p>
  </div>
</div>

<div class="ps-card" id="pay">
  <h2>Payment (Razorpay)</h2>
  <div class="ps-body">
    <p style="margin:0 0 6px;font-size:14px">Keys saved: <b><?= !empty($pay['enabled']) ? 'yes' : 'no' ?></b>
      <?php if (!empty($pay['key_id'])): ?> · Key ID <code><?= $e(substr((string) $pay['key_id'], 0, 13)) ?>…</code><?php endif; ?></p>
    <p class="ps-note" style="margin-top:0">Your keys come from the Razorpay Dashboard → Account &amp; Settings → API Keys. Enter them under <a href="/billing">Billing</a>.
      Start with TEST keys (rzp_test_…): payments are pretend, so you can try the whole purchase with Razorpay's test cards. Switch to LIVE keys (rzp_live_…) to take real money.</p>
    <form method="post" class="ps-row"><input type="hidden" name="do" value="pay_check"><button class="btn ghost">Check payment keys</button>
      <?php $pc = $ch['payment'] ?? null; if ($pc): ?><span class="<?= !empty($pc['ok']) ? 'ps-ok' : 'ps-bad' ?>"><?= !empty($pc['ok']) ? 'Working' : 'Not working' ?></span>
        <span class="ps-note" style="margin:0"><?= $e(substr((string) $pc['at'], 0, 16) . ' — ' . $pc['msg']) ?></span><?php endif; ?></form>
    <p class="ps-note">The check only asks Razorpay to confirm the keys; it creates nothing and moves no money.</p>
  </div>
</div>

<div class="ps-card" id="admin">
  <h2>Super Admin address — a separate sign-in for the platform owner</h2>
  <div class="ps-body">
    <?php if (!empty($adm['locked'])): ?>
      <p style="margin:0 0 6px;font-size:14px"><span class="ps-ok">Locked</span> — Super Admin opens only at <b>https://<?= $e($ah) ?></b>.
        On the main address it is not shown, and at this address only platform-owner accounts can sign in.</p>
      <?php if (!empty($adm['override'])): ?><p class="ps-bad" style="font-size:13px">The emergency unlock is switched on on the server, so the lock is not enforced right now. Delete the file <code><?= $e($adm['unlock_file']) ?></code> to enforce it again.</p><?php endif; ?>
      <form method="post" class="ps-row"><input type="hidden" name="do" value="admin_unlock"><button class="btn ghost" onclick="return confirm('Unlock? Super Admin will open on the main address again.')">Unlock</button></form>
    <?php else: ?>
      <p class="ps-note" style="margin-top:0">Give Super Admin its own web address, for example <b><?= $e($adm['suggest'] ?: 'admin.your-domain') ?></b>. Companies never use it, your browser keeps a separate sign-in there,
        and once locked the Super Admin screens open nowhere else.</p>
      <form method="post" class="ps-row"><input type="hidden" name="do" value="admin_host">
        <label for="ps-ah" style="font-weight:600;font-size:13px">Address</label>
        <input id="ps-ah" name="admin_host" value="<?= $e($ah) ?>" placeholder="<?= $e($adm['suggest'] ?: 'admin.your-domain') ?>">
        <button class="btn">Save address</button></form>
      <?php if ($ah !== ''): ?>
        <ol style="margin:14px 0 0;padding-left:20px;font-size:13.5px;line-height:1.7">
          <li><b>Create the address in your hosting.</b>
            <?php if (!empty($adm['cpanel'])): ?>
              <form method="post" style="display:inline"><input type="hidden" name="do" value="admin_cpanel"><button class="btn ghost" style="padding:3px 10px;font-size:12.5px">Create it in cPanel for me</button></form>
              — or by hand:<?php endif; ?>
            cPanel → <b>Domains</b> (or <b>Subdomains</b>) → create <b><?= $e($ah) ?></b> with the <b>same folder</b> as the main site. cPanel adds the free security certificate (AutoSSL) within about an hour.</li>
          <li><b>Open <a href="https://<?= $e($ah) ?>/platform-setup#admin" target="_blank" rel="noopener">https://<?= $e($ah) ?></a></b> and sign in with this same platform-owner account.</li>
          <li><b>Press "Lock Super Admin to this address"</b> on this page there.</li>
        </ol>
        <form method="post" class="ps-row"><input type="hidden" name="do" value="admin_lock">
          <button class="btn"<?= empty($adm['here']) ? ' disabled aria-disabled="true"' : '' ?>>Lock Super Admin to this address</button>
          <?php if (empty($adm['here'])): ?><span class="ps-note" style="margin:0">Available when this page is opened at https://<?= $e($ah) ?> — so you never lock yourself out of an address that does not work yet.</span><?php endif; ?></form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($ah !== ''): ?>
      <p class="ps-note">Locked out (the address stopped working)? In cPanel → File Manager create an empty file named <code>superadmin-unlock</code> in the folder <code><?= $e(dirname((string) ($adm['unlock_file'] ?? ''))) ?></code>.
        Super Admin then opens on the main address again until you delete that file.</p>
    <?php endif; ?>
  </div>
</div>

<details class="ps-card">
  <summary style="cursor:pointer;padding:13px 16px;font-size:15px;font-weight:700;background:var(--soft,#f6f8fb)">Recent e-mails from the main site (last <?= count($log) ?>) — <?= count(array_filter($log, fn($l) => (int) $l['sent_ok'])) ?> sent, <?= count(array_filter($log, fn($l) => !(int) $l['sent_ok'])) ?> not sent</summary>
  <div style="overflow-x:auto">
    <table class="ps-log">
      <tr><th>When</th><th>To</th><th>Subject</th><th>Result</th></tr>
      <?php if (!$log): ?><tr><td colspan="4">Nothing sent yet.</td></tr><?php endif; ?>
      <?php foreach ($log as $l): ?>
        <tr><td><?= $e(substr((string) $l['created_at'], 0, 16)) ?></td><td><?= $e($l['to_addr']) ?></td><td><?= $e($l['subject']) ?></td>
          <td><?= (int) $l['sent_ok'] ? '<span class="ps-ok">Sent</span>' : '<span class="ps-bad">Not sent</span><div class="ps-note" style="margin:2px 0 0">' . $e($l['error']) . '</div>' ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <p class="ps-note" style="padding:0 16px 14px">This list shows who and what only. Sign-up codes, temporary passwords and reset links are never kept.</p>
</details>
</div>
