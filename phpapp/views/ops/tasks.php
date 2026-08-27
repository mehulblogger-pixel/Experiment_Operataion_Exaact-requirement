<?php
// Phase 3 §26 — My tasks. Human-authored follow-ups, separate from the derived "waiting on me"
// counts on the My Work page (those stay where they are).
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$today = date('Y-m-d');
?>
<div class="page-head"><h1>My tasks</h1>
  <p class="muted" style="margin:2px 0 0">Follow-ups you write down and tick off — separate from the approvals and reports the system already surfaces for you on <a href="/my-work">My Work</a>.</p>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Add a task</h3>
  <form method="post" action="/tasks" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="do" value="add">
    <label style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:3px">
      <span class="muted" style="font-size:12px">What needs doing</span>
      <input name="title" placeholder="e.g. Chase the calibration certificate for JOB-104" required>
    </label>
    <label style="display:flex;flex-direction:column;gap:3px">
      <span class="muted" style="font-size:12px">Due</span>
      <input type="date" name="due_on" min="<?= e($today) ?>">
    </label>
    <?php if (!empty($assignees)): ?>
    <label style="display:flex;flex-direction:column;gap:3px">
      <span class="muted" style="font-size:12px">Assign to</span>
      <select name="assigned_to">
        <option value="0">Myself</option>
        <?php foreach ($assignees as $uid => $nm): ?>
          <option value="<?= (int)$uid ?>"><?= e($nm) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <button class="btn" type="submit">Add task</button>
  </form>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Open · <?= count($open) ?></h3>
  <?php if (!$open): ?>
    <p class="muted" style="margin:6px 0">Nothing on your list. Add a follow-up above, or from any job, report or case.</p>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:2px">
    <?php foreach ($open as $t):
      $overdue = $t['due_on'] && $t['due_on'] < $today; ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid var(--line,#e5e5e5)">
        <form method="post" action="/tasks" style="margin:0">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="do" value="done">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <button class="btn small secondary" type="submit" title="Mark done">✓</button>
        </form>
        <div style="flex:1">
          <div style="font-size:14.5px"><?= e($t['title']) ?></div>
          <div class="muted" style="font-size:12px">
            <?php if ($t['due_on']): ?><span class="<?= $overdue ? 'pill p-bad' : '' ?>" style="<?= $overdue ? 'font-size:10px' : '' ?>">due <?= e($t['due_on']) ?><?= $overdue ? ' · overdue' : '' ?></span> · <?php endif; ?>
            <?= e($t['assigned_to_name'] ?: '—') ?>
            <?php if (!empty($t['entity_kind']) && !empty($t['entity_id'])): ?> · <a href="<?= e(route_for_entity($t['entity_kind'], (int)$t['entity_id'])) ?>"><?= e(ucfirst(strtolower($t['entity_kind']))) ?> #<?= (int)$t['entity_id'] ?></a><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($recentDone)): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Recently done</h3>
  <div style="display:flex;flex-direction:column;gap:2px">
  <?php foreach ($recentDone as $t): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-top:1px solid var(--line,#e5e5e5);opacity:.6">
      <form method="post" action="/tasks" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="do" value="reopen">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <button class="btn small secondary" type="submit" title="Reopen">↺</button>
      </form>
      <span style="flex:1;font-size:13.5px;text-decoration:line-through"><?= e($t['title']) ?></span>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
