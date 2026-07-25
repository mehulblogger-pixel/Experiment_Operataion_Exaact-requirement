<?php
  // Organisation hierarchy (N+1) — derived automatically from each user's reporting manager.
  function render_org_node($n) {
    $nm = trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? '')) ?: ($n['username'] ?? '—');
    $pos = $n['position_title'] ?: (ORG_ROLES[$n['role']] ?? $n['role']);
    echo '<li><div class="org-card">';
    echo '<strong>' . e($nm) . '</strong>';
    echo '<span class="org-role">' . e($pos) . '</span>';
    if (!empty($n['email'])) echo '<span class="org-mail">' . e($n['email']) . '</span>';
    if (can('users.manage.global') || is_master()) echo ' <a class="org-edit" href="/user-edit?id=' . (int)$n['id'] . '">edit</a>';
    echo '</div>';
    if (!empty($n['children'])) {
      echo '<ul>';
      foreach ($n['children'] as $c) render_org_node($c);
      echo '</ul>';
    }
    echo '</li>';
  }
?>
<div class="master-head">
  <div><h1>Organisation hierarchy</h1>
    <p class="sub" style="margin:2px 0 0">Built automatically from each person's reporting manager. Set "Reports to" on a user to place them in the chain.</p></div>
  <div style="display:flex;gap:6px">
    <a class="btn secondary" href="/users">Users</a>
    <button class="btn secondary" onclick="window.print()">Print</button>
  </div>
</div>

<?php if (!$tree): ?>
  <div class="panel"><p class="muted">No active users yet.</p></div>
<?php else: ?>
  <div class="panel org-wrap">
    <ul class="org-tree">
      <?php foreach ($tree as $root) render_org_node($root); ?>
    </ul>
  </div>
<?php endif; ?>

<style>
  .org-wrap{overflow-x:auto}
  .org-tree, .org-tree ul{list-style:none;margin:0;padding-left:22px}
  .org-tree > li{padding:6px 0}
  .org-tree ul{border-left:2px solid var(--line);margin-left:6px}
  .org-card{display:inline-flex;flex-direction:column;gap:1px;background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:8px 12px;margin:3px 0}
  .org-card strong{font-size:14px}
  .org-role{font-size:12px;color:var(--brand);font-weight:600}
  .org-mail{font-size:11px;color:var(--muted)}
  .org-edit{font-size:11px}
  @media print{ .master-head .btn, .side, .nav-toggle{display:none!important} }
</style>
