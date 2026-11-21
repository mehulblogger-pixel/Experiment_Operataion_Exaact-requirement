<?php
// Stage 4 of the Sales redesign (owner, 21 Nov 2026) — the approval rule form in the Sales
// workspace look: the Sales bar on a laptop, the bottom bar on a phone, the record-page
// cards. Every field, button, link and rule below is the one it had (the old page is kept
// in tests/fixtures/pre_stage4/); only the frame around it changed.
require_once __DIR__ . '/../_sxw_ui.php';
$__sxq4 = !(function_exists('is_embed') && is_embed());   // never inside the in-page popup
?>
<?php if ($__sxq4): ?><div class="rec sxw has-bn sxq sxq4"><?php sxw_nav('/quotes'); ?><?php endif; ?>
<div class="master-head">
  <div><h1><?= $r ? 'Edit approval rule' : 'Add approval rule' ?></h1></div>
  <a class="btn secondary" href="<?= e(nav_back($cur)) ?>">← <?= e(nav_back_label($cur)) ?></a>
</div>

<form method="post" action="/<?= $r ? 'quote-approval-rule-edit?id=' . (int)$r['id'] : 'quote-approval-rule-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Rule name *</label><input class="form-control" name="name" required value="<?= e($r['name'] ?? '') ?>" placeholder="e.g. Manager approval > 5 lakh"></div>
    <div class="ff"><label>Level <span class="muted">(1 = first approver)</span></label><input class="form-control" type="number" min="1" name="level" value="<?= e($r['level'] ?? 1) ?>"></div>
    <div class="ff" style="align-self:end"><label class="chk"><input type="checkbox" name="active" value="1" <?= ($r['active'] ?? 1)?'checked':'' ?>> Active</label></div>

    <div class="ff"><label>Applies to</label>
      <select class="form-control" name="match_type" id="mt" onchange="document.getElementById('sbuwrap').style.display=this.value==='SBU'?'block':'none'">
        <?php $isUnit = $r && crm_rule_is_unit($r); ?><option value="ANY" <?= !$isUnit?'selected':'' ?>>Any <?= e(Tl('sbu')) ?></option>
        <option value="SBU" <?= $isUnit?'selected':'' ?>>A specific <?= e(Tl('sbu')) ?></option>
      </select></div>
    <div class="ff" id="sbuwrap" style="<?= $isUnit?'':'display:none' ?>"><label><?= e(T('sbu')) ?></label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($r['sbu'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"></div>

    <div class="ff"><label>Amount from (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="min_amount" value="<?= e($r['min_amount'] ?? 0) ?>"></div>
    <div class="ff"><label>Amount up to (<?= e(cur_sym()) ?>) <span class="muted">— 0 = no upper limit</span></label><input class="form-control" type="number" step="0.01" name="max_amount" value="<?= e($r['max_amount'] ?? 0) ?>"></div>
    <div class="ff"></div>

    <div class="ff"><label>Approver role</label>
      <select class="form-control searchable" name="approver_role"><option value="">— any approver —</option>
        <option value="REPORTS_TO" <?= (($r['approver_role'] ?? '')==='REPORTS_TO')?'selected':'' ?>>↑ Reporting manager (up the chain by level)</option>
        <?php foreach ($roles as $k=>$v): if($k==='INSPECTOR')continue; ?><option value="<?= e($k) ?>" <?= (($r['approver_role'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>…or a specific person <span class="muted">(overrides role)</span></label>
      <select class="form-control searchable" name="approver_user_id"><option value="">—</option>
        <?php foreach ($users as $u): $nm = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= (string)($r['approver_user_id'] ?? '')===(string)$u['id']?'selected':'' ?>><?= e($nm) ?></option><?php endforeach; ?></select></div>
  </div>
  <small class="muted" style="display:block;margin-top:8px">The approver must also hold the "Approve <?= e(Tlp('quote')) ?>" permission. Leave role &amp; person blank for "any approver".</small>
  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $r ? 'Save rule' : 'Add rule' ?></button>
    <a class="btn secondary" href="/quote-approval-rules">Cancel</a>
  </div>
</form>
<?php if ($__sxq4): ?></div><?php sxw_bottom('/quotes'); ?><?php endif; ?>
