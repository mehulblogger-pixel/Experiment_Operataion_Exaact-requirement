<?php
// Two boundaries, and the lead states both: the assistant sees only this
// company's records, and within those only what THIS signed-in user is
// permitted to open for themselves.
$scope = $scope ?? ['reports', 'issues'];
$names = []; if (in_array('reports', $scope, true)) $names[] = 'reports';
if (in_array('issues', $scope, true)) $names[] = 'nonconformities';
?>
<h2 class="ptitle">Assistant</h2>
<p class="plead">
<?php if ($names): ?>
  Ask about your own <?= e(implode(' and ', $names)) ?> in plain words — "which reports are on hold?",
  "what is due this month?". It only ever sees <?= e(portal_client_name()) ?>'s own records, nothing belonging
  to anybody else, and within those only the <?= e(implode(' and ', $names)) ?> your own access covers.
<?php else: ?>
  The assistant answers from reports and nonconformities. Your access here covers neither, so there is
  nothing it can tell you.
<?php endif; ?>
</p>

<?php if (!$names): ?>
  <div class="pmsg">Nothing to ask about yet. If you need reports or nonconformities, ask your main contact
    at <?= e(portal_client_name()) ?> to add them to your access.</div>
<?php elseif (empty($available)): ?>
  <div class="pmsg">The assistant is not switched on for this account yet. Everything is still on the pages here —
    <?php $l = [];
          if (pcan('reports')) $l[] = '<a href="/portal/reports">reports</a>';
          if (pcan('issues'))  $l[] = '<a href="/portal/issues">nonconformities</a>';
          echo 'your ' . implode(' and ', $l) . '.'; ?></div>
<?php else: ?>
<form method="post" action="/portal/assistant" class="pcard" style="max-width:680px">
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
