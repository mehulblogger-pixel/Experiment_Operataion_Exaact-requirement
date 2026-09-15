<?php
// Department list & terminology. Data: $tab, $mayEdit, $tree, $editing, $terms,
// $pending, $dupe, $all.
//
// Deliberately plain language: a user sees "Department" and "Also known as",
// never canonical_id, term_norm or vocabulary_key.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$tab = $tab ?? 'manage'; $tree = $tree ?? []; $terms = $terms ?? []; $pending = $pending ?? [];
$all = $all ?? []; $editing = $editing ?? null; $dupe = $dupe ?? null; $mayEdit = $mayEdit ?? false;
$csrf = fn() => function_exists('csrf_field') ? csrf_field() : '';
$show = fn($v) => function_exists('vocab_display') ? vocab_display($v) : ($v['label'] ?? '');
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/departments">Departments</a> › <?= $tab === 'review' ? 'Words to confirm' : 'Department list' ?></div>
<div class="master-head">
  <div><h1><?= $tab === 'review' ? 'Words to confirm' : 'Department list' ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= $tab === 'review'
      ? 'Wording found in your data that EXAACT does not yet recognise. Tell it what each one means, and it will be understood everywhere from then on.'
      : 'Your organisation’s departments. Call them whatever is right for you — add the other words your team uses and EXAACT will recognise them all as the same department.' ?></p></div>
  <div class="row-actions">
    <a class="btn secondary" href="/departments">← Department hub</a>
    <?php if ($tab !== 'review'): ?><a class="btn secondary" href="/departments?tab=review">Words to confirm<?= $pending ? ' (' . count($pending) . ')' : '' ?></a><?php endif; ?>
    <?php if ($tab === 'review'): ?><a class="btn secondary" href="/departments?tab=manage">Department list</a><?php endif; ?>
  </div>
</div>

<?php if ($tab === 'review'): ?>
  <div class="panel">
    <?php if (!$pending): ?>
      <p class="muted" style="margin:0">Nothing to confirm. Every department word in your data is recognised.</p>
    <?php else: ?>
      <table class="grid">
        <tr><th>Word found</th><th>EXAACT suggests</th><th style="width:44%">What does it mean?</th></tr>
        <?php foreach ($pending as $t): $sg = $t['suggested_value_id'] ? (function_exists('vocab_value') ? vocab_value((int) $t['suggested_value_id']) : null) : null; ?>
        <tr>
          <td><strong><?= $e($t['term']) ?></strong></td>
          <td><?= $sg ? '<span class="pill p-info">' . $e($show($sg)) . '</span>' : '<span class="muted">no suggestion</span>' ?></td>
          <td>
            <?php if ($mayEdit): ?>
            <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
              <?= $csrf() ?><input type="hidden" name="do" value="term-approve"><input type="hidden" name="term_id" value="<?= (int) $t['id'] ?>">
              <select name="value_id" class="form-control" style="width:auto;min-width:190px">
                <?php foreach ($all as $v): ?>
                  <option value="<?= (int) $v['id'] ?>" <?= $sg && (int) $sg['id'] === (int) $v['id'] ? 'selected' : '' ?>><?= $e($show($v)) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn small" type="submit">It means this</button>
              <button class="btn small secondary" type="submit" formmethod="post" name="do" value="term-reject">Not a department</button>
            </form>
            <?php else: ?><span class="muted">An administrator can confirm this.</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <p class="muted" style="margin:10px 0 0">Nothing here changes your records. Confirming a word only teaches EXAACT which department it refers to.</p>
    <?php endif; ?>
  </div>

<?php else: ?>
  <?php if ($dupe): ?>
    <div class="panel" style="border-left:3px solid #d08700">
      <strong>That looks like a department you already have:</strong> <?= $e($show($dupe)) ?>.
      <p class="muted" style="margin:6px 0 0">Use the existing one, or tick “this really is a separate department” below and save again.</p>
    </div>
  <?php endif; ?>

  <div class="panel">
    <table class="grid">
      <tr><th>Department</th><th>Short code</th><th>Type</th><th>Also known as</th><th>Status</th><th></th></tr>
      <?php foreach ($tree as $d): $tn = function_exists('vocab_terms') ? vocab_terms('department', (int) $d['id'], 'APPROVED') : []; ?>
      <tr<?= (int) $d['active'] ? '' : ' style="opacity:.55"' ?>>
        <td><?= str_repeat('<span class="muted">│&nbsp;&nbsp;</span>', (int) ($d['depth'] ?? 0)) ?><strong><?= $e($show($d)) ?></strong>
          <?php if (trim((string) ($d['display_name'] ?? '')) !== '' && $d['display_name'] !== $d['label']): ?>
            <span class="muted" style="font-size:11.5px"> · EXAACT knows this as <?= $e($d['label']) ?></span><?php endif; ?>
          <?php if (trim((string) ($d['description'] ?? '')) !== ''): ?><div class="muted" style="font-size:11.5px"><?= $e($d['description']) ?></div><?php endif; ?>
        </td>
        <td><code><?= $e($d['code']) ?></code></td>
        <td><?= $e(DEPT_TYPES[$d['attr_type'] ?? ''] ?? '—') ?></td>
        <td style="font-size:11.5px">
          <?php $syn = array_values(array_filter($tn, fn($t) => $t['term_type'] !== 'CANONICAL' && strcasecmp($t['term'], $d['code']) !== 0));
                echo $syn ? $e(implode(', ', array_column(array_slice($syn, 0, 4), 'term'))) . (count($syn) > 4 ? ' …' : '') : '<span class="muted">—</span>'; ?>
        </td>
        <td><?= (int) $d['active'] ? '<span class="pill p-ok">In use</span>' : '<span class="pill p-mut">Switched off</span>' ?></td>
        <td class="row-actions"><?php if ($mayEdit): ?><a class="btn small secondary" href="/departments?tab=manage&edit=<?= (int) $d['id'] ?>">Edit</a><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$tree): ?><tr><td colspan="6" class="muted">No departments yet — add the first one below.</td></tr><?php endif; ?>
    </table>
  </div>

  <?php if ($mayEdit): ?>
  <h3 class="tab-sub"><?= $editing ? 'Edit ' . $e($show($editing)) : 'Add a department' ?></h3>
  <div class="panel">
    <form method="post">
      <?= $csrf() ?><input type="hidden" name="do" value="dept-save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="ff-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        <div class="ff"><label>Department name *</label>
          <input class="form-control" name="label" required value="<?= $e($editing['label'] ?? '') ?>" placeholder="e.g. Human Resources"></div>
        <div class="ff"><label>What your team calls it <span class="muted">— optional</span></label>
          <input class="form-control" name="display_name" value="<?= $e($editing['display_name'] ?? '') ?>" placeholder="e.g. People &amp; Culture">
          <small class="muted">Shown everywhere instead of the name above. Changing it never breaks existing records.</small></div>
        <div class="ff"><label>Short code</label>
          <input class="form-control" name="code" value="<?= $e($editing['code'] ?? '') ?>" placeholder="e.g. HR">
          <small class="muted">Left blank, one is made for you.</small></div>
        <div class="ff"><label>Type</label>
          <select class="form-control" name="attr_type"><option value="">—</option>
            <?php foreach (DEPT_TYPES as $k => $v): ?><option value="<?= $e($k) ?>" <?= ($editing['attr_type'] ?? '') === $k ? 'selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label>Sits inside <span class="muted">— optional</span></label>
          <select class="form-control" name="parent_value_id"><option value="0">— top level —</option>
            <?php foreach ($tree as $p): if ($editing && (int) $p['id'] === (int) $editing['id']) continue; ?>
              <option value="<?= (int) $p['id'] ?>" <?= (int) ($editing['parent_value_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= str_repeat('— ', (int) ($p['depth'] ?? 0)) . $e($show($p)) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="ff ff-wide" style="grid-column:1/-1"><label>Description <span class="muted">— optional</span></label>
          <input class="form-control" name="description" value="<?= $e($editing['description'] ?? '') ?>"></div>
        <div class="ff"><label>Your own reference <span class="muted">— optional</span></label>
          <input class="form-control" name="external_ref" value="<?= $e($editing['external_ref'] ?? '') ?>" placeholder="e.g. a code from your payroll system"></div>
        <div class="ff"><label>In use from / to <span class="muted">— optional</span></label>
          <div style="display:flex;gap:6px">
            <input class="form-control" type="date" name="effective_from" value="<?= $e(substr((string) ($editing['effective_from'] ?? ''), 0, 10)) ?>">
            <input class="form-control" type="date" name="effective_to" value="<?= $e(substr((string) ($editing['effective_to'] ?? ''), 0, 10)) ?>">
          </div></div>
      </div>
      <label style="display:block;margin:10px 0"><input type="checkbox" name="active" value="1" <?= !$editing || (int) $editing['active'] ? 'checked' : '' ?>> In use</label>
      <?php if (!$editing): ?>
        <label style="display:block;margin:0 0 10px"><input type="checkbox" name="confirm_new" value="1"> This really is a separate department, even if it looks like one we already have</label>
      <?php endif; ?>
      <button class="btn" type="submit"><?= $editing ? 'Save department' : 'Add department' ?></button>
      <?php if ($editing): ?><a class="btn secondary" href="/departments?tab=manage">Cancel</a><?php endif; ?>
    </form>
  </div>

    <?php if ($editing): ?>
    <h3 class="tab-sub">Other words for <?= $e($show($editing)) ?></h3>
    <div class="panel">
      <p class="sub" style="margin:0 0 10px">Anyone can type any of these and EXAACT will know they mean <?= $e($show($editing)) ?>. Searching, reports and approvals all treat them as one department.</p>
      <table class="grid">
        <tr><th>Word</th><th>Kind</th><th>Added</th><th></th></tr>
        <?php foreach ($terms as $t): ?>
        <tr<?= $t['status'] !== 'APPROVED' ? ' style="opacity:.6"' : '' ?>>
          <td><?= $e($t['term']) ?><?= $t['status'] !== 'APPROVED' ? ' <span class="pill p-mut">' . $e(VOCAB_STATUSES[$t['status']] ?? $t['status']) . '</span>' : '' ?></td>
          <td><?= $e(VOCAB_TERM_TYPES[$t['term_type']] ?? $t['term_type']) ?></td>
          <td class="muted" style="font-size:11.5px"><?= $e($t['approved_by'] ?: $t['created_by'] ?: '—') ?><?= $t['approved_at'] ? ' · ' . $e(substr($t['approved_at'], 0, 10)) : '' ?></td>
          <td class="row-actions"><?php if ($t['term_type'] !== 'CANONICAL'): ?>
            <form method="post" style="display:inline"><?= $csrf() ?>
              <input type="hidden" name="do" value="term-del"><input type="hidden" name="term_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
              <button class="btn small secondary" type="submit">Remove</button></form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <h4 class="tab-sub" style="margin-top:14px">Add another word</h4>
      <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <?= $csrf() ?><input type="hidden" name="do" value="term-add"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <div class="ff"><label>Word</label><input class="form-control" name="term" required placeholder="e.g. Personnel"></div>
        <div class="ff"><label>Kind</label><select class="form-control" name="term_type">
          <?php foreach (VOCAB_TERM_TYPES as $k => $v) { if ($k === 'CANONICAL') continue; ?><option value="<?= $e($k) ?>"><?= $e($v) ?></option><?php } ?>
        </select></div>
        <button class="btn" type="submit">Add</button>
      </form>
    </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
