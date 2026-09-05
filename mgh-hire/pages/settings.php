<?php
// Branding & settings — configurable product name, colours, logo, company.
require_can('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post('do');
    if ($do === 'brand') {
        setting_set('product_name', post('product_name','MGH Hire') ?: 'MGH Hire');
        setting_set('company_name', post('company_name'));
        setting_set('currency', post('currency','₹') ?: '₹');
        $bc = post('brand_color'); $ac = post('accent_color');
        if (preg_match('/^#[0-9a-fA-F]{6}$/',$bc)) setting_set('brand_color',$bc);
        if (preg_match('/^#[0-9a-fA-F]{6}$/',$ac)) setting_set('accent_color',$ac);
        // Optional logo upload (stored as a data URI).
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $bytes = file_get_contents($_FILES['logo']['tmp_name']);
            if (strlen($bytes) <= 1024*1024 && @getimagesizefromstring($bytes)) {
                setting_set('logo_data', 'data:'.($_FILES['logo']['type']?:'image/png').';base64,'.base64_encode($bytes));
            } else flash('Logo must be an image under 1 MB.', 'err');
        }
        if (post('remove_logo')) setting_set('logo_data','');
        flash('Branding saved.');
    } elseif ($do === 'email') {
        setting_set('mail_enabled', post('mail_enabled')?'1':'0');
        setting_set('mail_from_name', post('mail_from_name','MGH Hire'));
        $fe = post('mail_from_email'); $hr = post('mail_hr_inbox');
        setting_set('mail_from_email', filter_var($fe,FILTER_VALIDATE_EMAIL)?$fe:'');
        setting_set('mail_hr_inbox',   filter_var($hr,FILTER_VALIDATE_EMAIL)?$hr:'');
        // SMTP
        setting_set('smtp_host', trim(post('smtp_host')));
        setting_set('smtp_port', (string)max(0,(int)post('smtp_port','587')));
        setting_set('smtp_user', trim(post('smtp_user')));
        if (post('smtp_pass') !== '') setting_set('smtp_pass', post('smtp_pass')); // keep existing if blank
        setting_set('smtp_security', in_array(post('smtp_security'),['tls','ssl','none'],true)?post('smtp_security'):'tls');
        flash('Email settings saved.');
    } elseif ($do === 'careers') {
        setting_set('careers_enabled', post('careers_enabled')?'1':'0');
        setting_set('careers_intro', post('careers_intro'));
        flash('Careers page settings saved.');
    } elseif ($do === 'email_test') {
        $to = post('test_to');
        if (!filter_var($to,FILTER_VALIDATE_EMAIL)) flash('Enter a valid test address.', 'err');
        else { notify($to,'Test','MGH Hire test email','<p>This is a test message from MGH Hire. If you can read this, email delivery works.</p>','test',0); flash('Test email queued — see the outbox below for its status.'); }
    }
    redirect('?p=settings');
}

$outbox = db()->query("SELECT * FROM emails ORDER BY id DESC LIMIT 10")->fetchAll();

$logo = setting('logo_data','');
layout_top('Branding');
?>
<div class="card">
  <h2 class="mt0">Branding &amp; theme</h2>
  <p class="muted">Everything here is white-label. Change the name, colours and logo and the whole product re-themes instantly — no code, no developer.</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="do" value="brand"><?= csrf_field() ?>
    <div class="row2">
      <div><label>Product name (shown everywhere)</label><input name="product_name" value="<?= e(product_name()) ?>"></div>
      <div><label>Company / workspace name</label><input name="company_name" value="<?= e(setting('company_name','')) ?>"></div>
    </div>
    <div class="row3">
      <div><label>Primary colour</label><input name="brand_color" type="color" value="<?= e(brand_color()) ?>" style="height:42px;padding:4px"></div>
      <div><label>Accent colour</label><input name="accent_color" type="color" value="<?= e(accent_color()) ?>" style="height:42px;padding:4px"></div>
      <div><label>Currency symbol</label><input name="currency" value="<?= e(currency()) ?>" maxlength="4"></div>
    </div>
    <div class="row2">
      <div>
        <label>Logo (image, under 1 MB — optional)</label>
        <input type="file" name="logo" accept="image/*">
        <?php if ($logo): ?><div style="margin-top:8px;display:flex;align-items:center;gap:10px"><img src="<?= e($logo) ?>" style="height:40px;border-radius:8px"><label style="margin:0"><input type="checkbox" name="remove_logo" style="width:auto;margin-right:6px">Remove logo</label></div><?php endif; ?>
      </div>
      <div></div>
    </div>
    <div style="margin-top:16px"><button class="btn">Save branding</button></div>
  </form>
</div>

<?php $careersUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'your-site').strtok($_SERVER['REQUEST_URI']??'/','?').'?p=careers'; ?>
<div class="card">
  <h2>Public careers page</h2>
  <p class="muted mt0">A public page where anyone can see your open positions and apply. Applications land as candidates at the first stage, with the CV auto-read.</p>
  <form method="post">
    <input type="hidden" name="do" value="careers"><?= csrf_field() ?>
    <label style="font-weight:600"><input type="checkbox" name="careers_enabled" value="1" <?= setting('careers_enabled','1')==='1'?'checked':'' ?> style="width:auto;margin-right:7px">Enable the public careers page</label>
    <label>Intro line shown to applicants</label>
    <input name="careers_intro" value="<?= e(setting('careers_intro','')) ?>">
    <div style="margin-top:14px"><button class="btn">Save careers settings</button></div>
  </form>
  <p class="muted" style="margin-top:12px">Public link: <a href="?p=careers" target="_blank"><?= e($careersUrl) ?></a> — share this on your website or job posts.</p>
</div>

<div class="card">
  <h2>Email notifications</h2>
  <p class="muted mt0">Automatic emails on key events — requisition approved, interview scheduled, offer issued, and new applications from the careers page. Every message is logged in the outbox below whether or not sending is switched on.</p>
  <form method="post">
    <input type="hidden" name="do" value="email"><?= csrf_field() ?>
    <label style="font-weight:600"><input type="checkbox" name="mail_enabled" value="1" <?= mail_enabled()?'checked':'' ?> style="width:auto;margin-right:7px">Send emails automatically</label>
    <div class="row3">
      <div><label>From name</label><input name="mail_from_name" value="<?= e(setting('mail_from_name','MGH Hire')) ?>"></div>
      <div><label>From email</label><input name="mail_from_email" type="email" value="<?= e(setting('mail_from_email','')) ?>" placeholder="hr@yourcompany.com"></div>
      <div><label>HR inbox (new-application alerts)</label><input name="mail_hr_inbox" type="email" value="<?= e(setting('mail_hr_inbox','')) ?>" placeholder="careers@yourcompany.com"></div>
    </div>
    <div style="border-top:1px dashed var(--line);margin-top:14px;padding-top:12px">
      <b style="font-size:13px">SMTP server <span class="muted" style="font-weight:400">(recommended — for reliable delivery via Gmail, Outlook 365, SendGrid, SES, or your company mail)</span></b>
      <div class="row3">
        <div><label>SMTP host</label><input name="smtp_host" value="<?= e(setting('smtp_host','')) ?>" placeholder="smtp.gmail.com"></div>
        <div><label>Port</label><input name="smtp_port" type="number" value="<?= e(setting('smtp_port','587')) ?>" placeholder="587"></div>
        <div><label>Security</label>
          <select name="smtp_security">
            <?php foreach (['tls'=>'STARTTLS (587)','ssl'=>'SSL/TLS (465)','none'=>'None'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= setting('smtp_security','tls')===$k?'selected':'' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row2">
        <div><label>SMTP username</label><input name="smtp_user" value="<?= e(setting('smtp_user','')) ?>" placeholder="your login / API user"></div>
        <div><label>SMTP password <span class="muted" style="font-weight:400">(leave blank to keep)</span></label><input name="smtp_pass" type="password" placeholder="<?= setting('smtp_pass','')?'•••••••• (saved)':'app password / API key' ?>"></div>
      </div>
      <p class="muted" style="margin-top:6px">Leave the host blank to use the server's built-in mail instead. Gmail/Outlook need an <b>app password</b>, not your normal password.</p>
    </div>
    <div style="margin-top:14px"><button class="btn">Save email settings</button></div>
  </form>
  <form method="post" style="margin-top:14px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;border-top:1px dashed var(--line);padding-top:14px">
    <input type="hidden" name="do" value="email_test"><?= csrf_field() ?>
    <div style="min-width:240px"><label>Send a test email to</label><input name="test_to" type="email" placeholder="you@example.com"></div>
    <div><button class="btn ghost">Send test</button></div>
  </form>
</div>

<div class="card">
  <h2>Email outbox <span class="muted" style="font-weight:400;font-size:12px">(last 10)</span></h2>
  <?php if (!$outbox): ?><p class="muted mt0">No emails yet.</p><?php else: ?>
  <table>
    <tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr>
    <?php foreach ($outbox as $m):
      $sp = ['sent'=>'g','pending'=>'a','failed'=>'r','skipped'=>'s'][$m['status']] ?? 's'; ?>
      <tr><td class="muted"><?= fdate($m['created_at'],true) ?></td>
          <td><?= e($m['to_addr'] ?: '—') ?></td>
          <td><?= e($m['subject']) ?></td>
          <td><span class="pill <?= $sp ?>"><?= e($m['status']) ?></span><?= $m['error']?'<div class="muted" style="font-size:11px">'.e($m['error']).'</div>':'' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="margin-top:10px"><b>pending</b> = saved but not sent yet (sending off, or the host's mail isn't set up). <b>skipped</b> = no email address on file.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>About this workspace</h2>
  <p class="muted mt0">
    <b><?= e(product_name()) ?></b> — a standalone Recruitment &amp; Selection product.
    Data is stored in this workspace only. On a cloud hosting we operate for you, your data is encrypted and not readable by us;
    you can request a full export or restore at any time. On your own server, the data never leaves your premises.
  </p>
</div>
<?php layout_bottom();
