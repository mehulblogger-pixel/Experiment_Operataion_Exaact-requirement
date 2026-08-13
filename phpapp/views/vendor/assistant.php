<h2 class="ptitle">Assistant</h2>
<p class="plead">Ask about the reports shared with you and the nonconformities raised to you, in plain words. It
  only ever sees <?= e(cvp_vendor_name()) ?>'s own records, nothing belonging to anybody else.</p>

<?php if (empty($available)): ?>
  <div class="pmsg">The assistant is not switched on for this account yet. Everything is still on the pages here —
    your <a href="/vendor/reports">reports</a> and <a href="/vendor/issues">nonconformities</a>.</div>
<?php else: ?>
<form method="post" action="/vendor/assistant" class="pcard" style="max-width:680px">
  <textarea class="form-control" name="q" rows="2" required autofocus placeholder="Ask about your reports or nonconformities…"><?= e($q ?? '') ?></textarea>
  <div style="margin-top:12px"><button class="btn" type="submit">Ask</button></div>
</form>
<?php if (!empty($aerr)): ?><div class="pmsg err"><?= e($aerr) ?></div><?php endif; ?>
<?php if (!empty($ans)): ?>
  <div class="pcard" style="white-space:pre-wrap;line-height:1.6"><?= e($ans) ?></div>
  <p class="pnote">Always check anything important against the report itself. The assistant answers only from
    your own records and can make mistakes.</p>
<?php endif; ?>
<?php endif; ?>
