<?php
// The one page a client opens without an account, without asking us, and
// without seeing anything confidential. It answers three questions and stops:
// is this report genuine, has it been altered, and was the evidence behind it
// captured on site.
//
// What it deliberately does NOT show: the client's name, the findings, the
// prices, or anything a competitor holding a leaked code could mine. A
// verification page that leaks the report defeats the report's confidentiality
// clause, which would be a strange way to prove trustworthiness.
$code = strtoupper(trim((string)($_GET['c'] ?? '')));
$v = $code !== '' ? verify_lookup($code) : null;
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(app_name()) ?> — Verify a report</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
<style>
  body{margin:0;background:var(--bg);color:var(--ink)}
  .wrap{max-width:620px;margin:0 auto;padding:48px 22px 72px}
  .hd{border-bottom:1px solid var(--line);padding-bottom:18px;margin-bottom:24px}
  .hd h1{margin:0 0 6px;font-size:clamp(23px,3vw,30px);letter-spacing:-.02em}
  .hd p{margin:0;color:var(--muted);font-size:14px}
  .verdict{padding:18px 20px;border-radius:12px;margin-bottom:22px;border:1px solid var(--line)}
  .verdict.good{border-left:5px solid var(--ok)}
  .verdict.bad{border-left:5px solid var(--bad)}
  .verdict h2{margin:0 0 6px;font-size:19px}
  .verdict p{margin:0;font-size:14.5px;line-height:1.55}
  table.f{width:100%;border-collapse:collapse;margin:0 0 22px}
  table.f th{text-align:left;width:44%;padding:9px 0;border-bottom:1px solid var(--line);
    font-weight:600;font-size:14px;color:var(--muted);vertical-align:top}
  table.f td{padding:9px 0;border-bottom:1px solid var(--line);font-size:14.5px}
  form.q{display:flex;gap:8px;margin:0 0 22px}
  form.q input{flex:1}
  .note{font-size:13px;color:var(--muted);line-height:1.6}
  .foot{margin-top:34px;padding-top:14px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)}
  .foot a{color:inherit}
</style>
</head><body>
<div class="wrap">
  <div class="hd">
    <h1>Verify an inspection report</h1>
    <p><?= e(app_name()) ?><?= $v && !empty($v['ib_type']) ? ' · ' . e($v['ib_type']) : '' ?></p>
  </div>

  <form class="q" method="get" action="/verify">
    <input class="form-control" name="c" value="<?= e($code) ?>" placeholder="the code printed on the report"
           autocapitalize="characters" spellcheck="false">
    <button class="btn" type="submit">Check</button>
  </form>

  <?php if ($code === ''): ?>
    <p class="note">Every report we issue carries a code. Type it above and this page will tell you whether the
      report is genuine, whether anything has been altered since it was issued, and how much of the evidence behind
      it was located on site. You do not need an account, and you do not need to ask us.</p>

  <?php elseif ($v === null): ?>
    <div class="verdict bad">
      <h2>We do not recognise that code</h2>
      <p>No report we have issued carries it. Check for a mistyped character, or ask whoever gave you the report.
        If you were told the report came from us and this code does not work, that is worth telling us about.</p>
    </div>

  <?php elseif ($v['state'] === 'error'): ?>
    <div class="verdict bad">
      <h2>We cannot answer that right now</h2>
      <p>Something on our side is not working. This is recorded and somebody will look at it. Please try again
        shortly, or contact us and quote the code you were given — the report itself is not affected.</p>
    </div>

  <?php elseif ($v['state'] === 'not_issued'): ?>
    <div class="verdict bad">
      <h2>That report has not been issued</h2>
      <p>The reference exists in our system but the report is still a draft. A draft is not a report — do not rely
        on it, whatever you have been sent.</p>
    </div>

  <?php else:
        $chainOk   = !empty($v['chain']['ok']);
        $contentOk = ($v['content']['ok'] ?? true);
        $sealed    = !empty($v['content']['sealed']);
        $ok = $chainOk && $contentOk; ?>
    <div class="verdict <?= $ok ? 'good' : 'bad' ?>">
      <h2><?= $ok ? 'Genuine, and unaltered since it was issued' : 'Genuine — but something has changed since it was issued' ?></h2>
      <p><?php if ($ok): ?>We issued this report, and both the report and every piece of evidence behind it still match what was recorded when it was issued.<?php elseif (!$contentOk): ?>We issued this report, but its content no longer matches what was recorded when it was issued. Please contact us before relying on it — and please quote this code.<?php else: ?>We issued this report, but the evidence behind it no longer matches what was recorded when it was issued. Please contact us before relying on it — and please quote this code.<?php endif; ?></p>
    </div>

    <table class="f">
      <tr><th>Report number</th><td><?= e($v['irn']) ?></td></tr>
      <tr><th>Issued</th><td><?= e($v['issued_on'] ? fdate(substr((string)$v['issued_on'], 0, 10)) : '—') ?></td></tr>
      <tr><th>Issued by</th><td><?= e($v['issued_by'] ?: '—') ?></td></tr>
      <?php if ($v['inspector']): ?>
      <tr><th>Carried out by</th><td><?= e($v['inspector']) ?>
        <?php if ($v['authorised'] === true): ?><br><span class="note">Authorised for this work at the time of issue.</span>
        <?php elseif ($v['authorised'] === false): ?><br><span class="note">Authorisation not recorded — ask us.</span><?php endif; ?></td></tr>
      <?php endif; ?>
      <?php if ($v['result']): ?><tr><th>Result</th><td><?= e($v['result']) ?></td></tr><?php endif; ?>
      <?php if ($sealed): ?><tr><th>Report content</th><td><?= $contentOk ? 'Unchanged since it was issued' : 'ALTERED since it was issued — do not rely on this copy' ?></td></tr><?php endif; ?>
      <tr><th>Evidence on file</th><td><?= (int)$v['evidence'] ?> item(s)</td></tr>
      <tr><th>Located on site</th><td><?= (int)$v['on_site'] ?> of <?= (int)$v['evidence'] ?>
        <br><span class="note">Counted from the location the camera recorded inside each photograph at the moment
        it was taken — not from where the file was later uploaded.</span></td></tr>
      <?php if ((int)$v['flagged'] > 0): ?>
      <tr><th>Flagged for review</th><td><?= (int)$v['flagged'] ?>
        <br><span class="note">Our supervisor reviews anything the system questions. Ask us and we will tell you what
        was found.</span></td></tr>
      <?php endif; ?>
      <tr><th>Evidence chain</th><td><?= $chainOk ? 'Intact across ' . (int)$v['chain']['entries'] . ' entries'
        : 'Broken — ' . count($v['chain']['problems']) . ' problem(s)' ?></td></tr>
    </table>

    <p class="note"><strong>What this page does not show.</strong> Not the client, not the findings, not the
      commercial terms. A verification page that gave those away would breach the confidentiality every inspection
      report is issued under — so it answers whether the report is real and leaves the contents to the parties
      entitled to them.</p>
  <?php endif; ?>

  <div class="foot">
    <a href="/complaints-policy">If something is wrong, complain</a> · <a href="/login">Staff sign-in</a>
  </div>
</div>
</body></html>
