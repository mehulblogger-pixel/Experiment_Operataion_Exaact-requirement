<?php
$tone = ['OK' => '', 'OK_2FA' => '', 'REPLAY' => 'bad', 'REFUSED' => 'bad',
         'NO_ACCOUNT' => 'warn', 'INACTIVE' => 'warn'];
$said = ['OK' => 'Signed in', 'OK_2FA' => 'Accepted, asked for the code',
         'REPLAY' => 'Token reused — refused', 'REFUSED' => 'Refused',
         'NO_ACCOUNT' => 'No account here', 'INACTIVE' => 'Account switched off'];
?>
<div class="crumbs"><a href="/">Home</a> › Single sign-on</div>
<div class="master-head"><div>
  <h1>Single sign-on</h1>
  <p class="sub" style="margin:2px 0 0">One login across the MGH applications. Somebody signing in to Books, BlogPro or Ads Pro arrives here already identified — but with the role, office and permissions <b>this</b> system holds for them, never the ones the other application holds.</p>
</div></div>

<?php if (!$on): ?>
  <div class="msg msg-info" style="margin-top:12px">
    <?php if (!$secretSet): ?>
      <b>Switched off.</b> Single sign-on does nothing at all until <code>MGH_SSO_SECRET</code> is set in the server
      environment, and it must be the same value on every application in the portfolio. No secret, no feature — there is no
      way to half-enable it.
    <?php else: ?>
      <b>The secret is too short.</b> <code>MGH_SSO_SECRET</code> is set but under 32 characters, so sign-on stays off
      rather than running on a signing key that could be guessed.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="msg msg-success" style="margin-top:12px">
    <b>Active.</b> A signed handoff from a sibling application is accepted here, once, within five minutes of being issued.
  </div>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div class="panel">
    <h3 style="margin-top:0">What this system will and will not do</h3>
    <ul style="margin:0;padding-left:20px;font-size:13.5px;line-height:1.65">
      <li>It <b>verifies</b> a handoff. It never <b>issues</b> one — minting tokens would let this system assert any identity to every sibling application, which is a far larger thing to get right and nobody asked for it.</li>
      <li>It <b>will not create a user</b>. A person here carries a role, an office, a business unit and a scope. Inventing those from an e-mail address would hand somebody a working login with permissions nobody chose. An unknown address is turned away in plain words and the attempt is logged below.</li>
      <li>It <b>will not waive the second factor</b>. Anybody with 2FA switched on still lands on the code screen. The sibling proved who they are; it did not prove possession of their phone.</li>
      <li>It <b>will not accept a token twice</b>, so one left in a browser history or a referrer header cannot be replayed.</li>
      <li>A <b>deactivated account stays locked</b>. Switching somebody off must close every door, not only the one with a password on it.</li>
    </ul>
  </div>

  <div class="panel">
    <h3 style="margin-top:0">The other applications</h3>
    <?php if (!$apps): ?>
      <p class="muted" style="margin:0">None listed. Set <code>MGH_APPS</code> in the environment to draw the switcher —
        a JSON object of <code>{"key":{"name":…,"url":…}}</code>.</p>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($apps as $k => $a): ?>
          <a class="btn small secondary" href="<?= e($a['url']) ?>" rel="noopener noreferrer" target="_blank"><?= e($a['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <p class="muted" style="margin:10px 0 0;font-size:12.5px">
        Following one of these lands on that application's own sign-in unless it has its own session. This system does not
        mint tokens, so it cannot claim on your behalf that you are already signed in over there.
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
    <b style="font-size:13.5px">Every handoff attempted</b>
    <div class="muted" style="font-size:12.5px;margin-top:3px">Refusals are here too, and are the more useful half — a colleague reporting “the link is broken” is usually a person with no account on this system.</div>
  </div>
  <?php if (!$rows): ?>
    <div style="padding:14px 16px" class="muted">Nothing yet.</div>
  <?php else: ?>
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only">Single sign-on attempts</caption>
        <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Result</th><th scope="col">From</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="white-space:nowrap"><?= trim((string)$r['at']) !== '' ? e(fdate(substr((string)$r['at'], 0, 10))) . ' ' . e(substr((string)$r['at'], 11, 5)) : '—' ?></td>
            <td><?= e((string)$r['email'] ?: '—') ?></td>
            <td>
              <span class="pill <?= e($tone[$r['result']] ?? '') ?>"><?= e($said[$r['result']] ?? (string)$r['result']) ?></span>
              <?php if (trim((string)$r['detail']) !== ''): ?>
                <div class="muted" style="font-size:12px"><?= e((string)$r['detail']) ?></div>
              <?php endif; ?>
            </td>
            <td class="muted"><?= e((string)$r['ip'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
