<?php
// Prefill order: what was typed and bounced back beats what arrived in the URL.
// Somebody whose session timed out mid-form gets their twenty fields returned,
// not an empty screen and an apology.
$p = $prefill ?? [];
$val = function ($k, $d = '') use ($p) { return form_old($k, $p[$k] ?? $d); };
$me  = current_user();
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/leads">Leads</a> › New</div>
<div class="master-head"><div><h1>New lead</h1>
  <p class="sub" style="margin:2px 0 0">Somebody worth chasing. If they become a customer, everything here comes across — you will not type it again.</p></div></div>

<?php if ($dup): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--warn)">
  <b>We may already know them.</b>
  <p style="margin:6px 0 0">
    <?php if ($dup['kind'] === 'partner'): ?>
      <a href="/client?id=<?= (int)$dup['row']['id'] ?>"><?= e($dup['row']['display_name'] ?? $dup['row']['legal_name']) ?></a>
      is already on the customer master — matched on <?= e($dup['by']) ?>.
      Chasing them as a new lead builds a second relationship with the same company.
    <?php else: ?>
      There is already an open lead <a href="/lead?id=<?= (int)$dup['row']['id'] ?>"><?= e($dup['row']['ref']) ?></a>
      for <?= e($dup['row']['company_name']) ?>.
    <?php endif; ?>
  </p>
</div>
<?php endif; ?>

<?php if (form_is_retry()): ?>
  <div class="msg msg-warning mt-4">Nothing was lost — everything you typed is below. Press <b>Create it</b> again.</div>
<?php endif; ?>

<form method="post" action="/lead-new" class="panel mt-4">
  <div class="form-sec"><h3>Who they are</h3>
    <p>The minimum that makes a lead chaseable. Everything here comes across if they become a customer.</p></div>
  <div class="form-grid">
    <div class="ff ff-wide ff-req"><label>Company or person</label>
      <input class="form-control" name="company_name" required maxlength="200"
             value="<?= e($val('company_name')) ?>" placeholder="Who are we chasing?"></div>
    <div class="ff"><label>Contact name</label>
      <input class="form-control" name="contact_name" maxlength="150" value="<?= e($val('contact_name')) ?>"></div>
    <div class="ff"><label>E-mail</label>
      <input class="form-control" type="email" inputmode="email" name="contact_email" maxlength="200"
             value="<?= e($val('contact_email')) ?>"></div>
    <div class="ff"><label>Telephone</label>
      <input class="form-control" type="tel" inputmode="tel" name="contact_phone" maxlength="60"
             value="<?= e($val('contact_phone')) ?>"></div>
    <div class="ff"><label>Where they came from</label>
      <select class="form-control" name="source"><option value="">—</option>
        <?php foreach ($sources as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $val('source') === (string)$k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>

  <div class="form-sec"><h3>What they want</h3></div>
  <div class="form-grid">
    <div class="ff ff-wide"><label>The requirement</label>
      <textarea class="form-control" name="requirement" rows="3"
        placeholder="Scope, quantities, the standard — anything that decides whether this is worth chasing"><?= e($val('requirement')) ?></textarea></div>
    <div class="ff"><label>Value they are worth <span class="muted">(estimate, <?= e(cur_sym()) ?>)</span></label>
      <input class="form-control" type="number" inputmode="decimal" step="0.01" min="0" name="value"
             value="<?= e($val('value')) ?>"></div>
    <div class="ff"><label>Expected to close</label>
      <input class="form-control" type="date" name="expected_close" value="<?= e($val('expected_close')) ?>"></div>
  </div>

  <div class="form-sec"><h3>Who chases it, and what happens next</h3>
    <p>A lead nobody owns is a lead nobody rings.</p></div>
  <div class="form-grid">
    <?php // Allocating at creation. Before this the only way to hand a lead to
          // somebody was "Assign to me" on the register — which does the
          // opposite of allocating it to a colleague. ?>
    <div class="ff"><label>Allocated to</label>
      <select class="form-control searchable" name="owner_user_id">
        <?php $sel = $val('owner_user_id', (string)($me['id'] ?? '')); ?>
        <?php foreach (($users ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (string)$u['id'] === (string)$sel ? 'selected' : '' ?>>
            <?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) ?><?php
              if ((int)$u['id'] === (int)($me['id'] ?? 0)) echo ' — me'; ?></option>
        <?php endforeach; ?>
      </select>
      <div class="ff-help">Defaults to you. Change it if somebody else will chase this one.</div></div>

    <div class="ff"><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">— mine —</option>
        <?php foreach ($offices as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= $val('office_id') === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="ff"><label>Pipeline</label>
      <select class="form-control" name="pipeline_id">
        <?php foreach ($pipelines as $pl): ?>
          <option value="<?= (int)$pl['id'] ?>" <?= $val('pipeline_id') === (string)$pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <?php // Pick from the list or type your own. A plain text box gave us twenty
          // spellings of "send profile" and nothing countable; a locked dropdown
          // would block the one action nobody predicted. A datalist is both. ?>
    <div class="ff"><label>Next thing to do</label>
      <input class="form-control" name="next_action" maxlength="255" list="next-actions"
             value="<?= e($val('next_action')) ?>" placeholder="Pick one, or type your own">
      <datalist id="next-actions">
        <?php foreach (($nextActions ?: [
              'Send the capability statement','Call to qualify','Arrange a meeting','Send a quotation',
              'Ask for the specification','Site visit','Follow up on the quotation','Send credentials and approvals',
          ]) as $k => $v): ?>
          <option value="<?= e(is_string($k) && !is_int($k) ? $v : $v) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <div class="ff-help">Kept as a list under <a href="/masters" target="_blank">Masters</a>, so the same words are
        used by everybody and the follow-up report adds up.</div></div>

    <div class="ff"><label>By when</label>
      <input class="form-control" type="date" name="next_action_on" value="<?= e($val('next_action_on')) ?>"></div>
  </div>

  <p class="t-sm t-mut mt-3">A lead with no e-mail and no telephone number scores badly on purpose — it is a lead
    nobody can chase.</p>

  <div class="form-actions">
    <button class="btn" type="submit">Create it</button>
    <a class="btn secondary" href="/leads">Cancel</a>
  </div>
</form>
