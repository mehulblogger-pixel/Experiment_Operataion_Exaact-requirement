<div class="crumbs"><a href="/">Home</a> › <span>Two-step sign-in</span></div>

<div class="master-head">
  <div><h1>Two-step sign-in</h1>
    <p class="sub" style="margin:2px 0 0">A six-digit code from your phone, on top of your password.</p></div>
  <div><span class="pill <?= $on ? 'p-ok' : 'p-mut' ?>"><?= $on ? 'On' : 'Off' ?></span>
    <?php if ($required): ?><span class="pill p-info">Required for your role</span><?php endif; ?></div>
</div>

<?php // Shown once, immediately after switching on or re-issuing. There is no
      // second chance: only hashes of these are kept, exactly as with a password. ?>
<?php if (!empty($fresh)): ?>
<div class="panel" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 7%,transparent)">
  <b style="color:var(--ok)">✔ Your recovery codes — save them now</b>
  <div class="muted" style="margin-top:4px">
    Each one works once, in place of the code from your phone. Print them, or put them where you keep
    the company's other keys. <strong>They are not shown again</strong> — only a scrambled copy is kept here,
    exactly as with a password, so nobody including an administrator can read them back to you.
  </div>
  <div style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px">
    <?php foreach ($fresh as $c): ?>
      <code style="font-size:15px;letter-spacing:.06em;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);text-align:center"><?= e($c) ?></code>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel-split">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Why this matters</h3></div>
    <p class="muted" style="font-size:13.5px;line-height:1.65">
      A password can be watched over a shoulder on a shop floor, guessed if it was reused on a website that
      has since been broken into, or handed over to a convincing e-mail. A code that changes every thirty
      seconds is the one defence that survives all three, because knowing the password is no longer enough.
    </p>
    <p class="muted" style="font-size:13.5px;line-height:1.65">
      You need a free authenticator app on your phone — Google Authenticator, Microsoft Authenticator and
      Authy all work. Nothing is sent by SMS, so it works with no signal, and there is nothing to pay.
    </p>
    <?php if ($u['last_login_at']): ?>
      <p class="muted" style="font-size:12.5px">Last signed in <?= e(fdate($u['last_login_at'])) ?><?= $u['last_login_ip'] ? ' from ' . e($u['last_login_ip']) : '' ?>.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <?php if (!$on && !$pending): ?>
      <div class="ctitle" style="margin-top:0"><h3>Switch it on</h3></div>
      <p class="muted" style="font-size:13.5px">Takes about a minute. You will need your phone in your hand.</p>
      <form method="post" action="/two-factor">
        <input type="hidden" name="_do" value="start">
        <button class="btn" type="submit">Set it up →</button>
      </form>

    <?php elseif ($pending): ?>
      <div class="ctitle" style="margin-top:0"><h3>Step 1 — add this to your app</h3></div>
      <?php $qrSvg = ($uri !== '' && function_exists('qr_svg')) ? qr_svg($uri, 190, 'M') : ''; ?>
      <?php if ($qrSvg !== ''): ?>
        <p class="muted" style="font-size:13.5px">
          In your authenticator app choose <strong>Add account → Scan a QR code</strong> and point your
          camera at this. That is the whole of Step 1.
        </p>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;margin:10px 0">
          <div style="padding:10px;border:1px solid var(--line);border-radius:12px;background:#fff;line-height:0">
            <?= $qrSvg ?>
          </div>
          <div style="flex:1;min-width:200px">
            <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em">Can't scan? Type this setup key instead</div>
            <code style="font-size:16px;letter-spacing:.12em;word-break:break-all"><?= e(chunk_split($pending, 4, ' ')) ?></code>
            <div class="muted" style="font-size:11.5px;margin-top:6px">Account: <?= e($u['username']) ?> · Type: time-based · 6 digits · 30 seconds</div>
          </div>
        </div>
      <?php else: ?>
        <p class="muted" style="font-size:13.5px">
          In your authenticator app choose <strong>Add account → Enter a setup key</strong>, then type the key below.
          Give it any name you like — <?= e(app_name()) ?> is the obvious one.
        </p>
        <div style="margin:10px 0;padding:12px;border:1px dashed var(--line);border-radius:10px;background:var(--field)">
          <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em">Setup key</div>
          <code style="font-size:17px;letter-spacing:.14em;word-break:break-all"><?= e(chunk_split($pending, 4, ' ')) ?></code>
          <div class="muted" style="font-size:11.5px;margin-top:6px">Account: <?= e($u['username']) ?> · Type: time-based · 6 digits · 30 seconds</div>
        </div>
      <?php endif; ?>

      <div class="ctitle"><h3>Step 2 — prove it works</h3></div>
      <p class="muted" style="font-size:13.5px">Type the six digits your app is showing right now. Nothing is switched on until this matches.</p>
      <form method="post" action="/two-factor" class="form-grid">
        <input type="hidden" name="_do" value="confirm">
        <div class="ff"><label>Code from the app</label>
          <input class="form-control" name="code" inputmode="numeric" autocomplete="one-time-code" required
                 placeholder="000000" style="letter-spacing:.3em;font-size:18px;text-align:center"></div>
        <div class="ff ff-check" style="align-items:end"><button class="btn" type="submit">Turn it on</button></div>
      </form>

    <?php else: ?>
      <div class="ctitle" style="margin-top:0"><h3>It is on</h3></div>
      <p class="muted" style="font-size:13.5px">
        Switched on <?= e(fdate($u['totp_confirmed_at'])) ?>. You have <strong><?= (int)$left ?></strong> recovery
        code<?= (int)$left === 1 ? '' : 's' ?> left.
        <?php if ((int)$left <= 3): ?><span class="pill p-warn">running low</span><?php endif; ?>
      </p>
      <form method="post" action="/two-factor" style="display:inline-block;margin-right:8px">
        <input type="hidden" name="_do" value="regen">
        <button class="btn secondary" type="submit" onclick="return confirm('Issue a new set? Your current recovery codes stop working immediately.')">Issue new recovery codes</button>
      </form>

      <?php if (!$required): ?>
        <div class="ctitle"><h3>Switch it off</h3></div>
        <p class="muted" style="font-size:13.5px">Your password is needed, so that somebody who finds this screen open cannot weaken your account.</p>
        <form method="post" action="/two-factor" class="form-grid">
          <input type="hidden" name="_do" value="off">
          <div class="ff"><label>Your password</label><input class="form-control" type="password" name="password" required></div>
          <div class="ff ff-check" style="align-items:end"><button class="btn secondary" type="submit">Turn it off</button></div>
        </form>
      <?php else: ?>
        <p class="muted" style="font-size:13px">Your role requires this, so it cannot be switched off here. A Master Admin can change which roles it applies to under Settings.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
