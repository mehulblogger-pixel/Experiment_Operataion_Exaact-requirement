<?php
  // Organisation chart — a real top-down tree with connector lines, built from
  // each person's reporting manager. Branches collapse, because a real org is
  // wider than a screen.
  function org_node_html($n, $all, $canEdit, $depth = 0) {
    $nm  = trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? '')) ?: ($n['username'] ?? '—');
    $pos = $n['position_title'] ?: (ORG_ROLES[$n['role']] ?? $n['role']);
    $kids = $n['children'] ?? [];
    $init = mb_strtoupper(mb_substr($nm, 0, 1));
    echo '<li>';
    echo '<div class="oc-node">';
    echo   '<div class="oc-card' . ($depth === 0 ? ' oc-top' : '') . '">';
    echo     '<div class="oc-head"><span class="oc-av">' . e($init) . '</span>';
    echo       '<div class="oc-id"><b>' . e($nm) . '</b><span class="oc-role">' . e($pos) . '</span></div></div>';
    if (!empty($n['email'])) echo '<div class="oc-mail">' . e($n['email']) . '</div>';
    if ($kids) {
      echo '<div class="oc-meta"><span class="pill p-info">' . (int)$n['direct_n'] . ' direct</span>';
      if ((int)$n['total_n'] > (int)$n['direct_n']) echo ' <span class="pill p-mut">' . (int)$n['total_n'] . ' total</span>';
      echo '</div>';
    }
    if ($canEdit) {
      echo '<div class="oc-act">';
      echo   '<a href="/user-edit?id=' . (int)$n['id'] . '">edit</a>';
      echo   '<form method="post" action="/hierarchy" class="oc-move"><input type="hidden" name="do" value="set">';
      echo     '<input type="hidden" name="user_id" value="' . (int)$n['id'] . '">';
      echo     '<select name="manager_id" onchange="this.form.submit()" title="Move under another manager">';
      echo       '<option value="">— top level —</option>';
      foreach ($all as $u) {
        if ((int)$u['id'] === (int)$n['id']) continue;
        $un = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
        $sel = (int)($n['reports_to_id'] ?? 0) === (int)$u['id'] ? ' selected' : '';
        echo '<option value="' . (int)$u['id'] . '"' . $sel . '>' . e($un) . '</option>';
      }
      echo     '</select></form>';
      echo '</div>';
    }
    echo   '</div>';
    if ($kids) echo '<button type="button" class="oc-toggle" aria-label="Collapse or expand">−</button>';
    echo '</div>';
    if ($kids) {
      echo '<ul>';
      foreach ($kids as $c) org_node_html($c, $all, $canEdit, $depth + 1);
      echo '</ul>';
    }
    echo '</li>';
  }
?>
<div class="crumbs"><a href="/">Home</a> › Organisation hierarchy</div>
<div class="master-head">
  <div><h1>Organisation hierarchy</h1>
    <p class="sub" style="margin:2px 0 0">Built from each person's reporting manager. <?= (int)$totalPeople ?> active people<?= $unplaced ? ' · ' . (int)$unplaced . ' at the top level' : '' ?>.</p></div>
  <div style="display:flex;gap:6px">
    <a class="btn secondary" href="/users">Users</a>
    <button class="btn secondary" onclick="window.print()">Print</button>
  </div>
</div>

<?php if ($canEdit && $proposals && $unplaced > 1): ?>
<div class="panel" style="border:1px solid var(--warn)">
  <b>⚠ Most people have no reporting manager, so the chart is a flat row.</b>
  <p class="sub" style="margin:6px 0 8px">
    A hierarchy only forms once people are placed under somebody. This proposes the obvious chain from the role
    ladder — an <?= e(ORG_ROLES['INSPECTOR'] ?? 'Inspector') ?> under a Coordinator, a Coordinator under an
    Operation Manager, and so on — preferring somebody in the same <?= e(T('office')) ?>.
    Apply it, then correct anyone who is wrong using the picker on their card.
  </p>
  <details style="margin-bottom:10px">
    <summary class="muted" style="cursor:pointer"><?= count($proposals) ?> proposed reporting line(s) — see them first</summary>
    <table class="dt" style="margin-top:8px">
      <thead><tr><th>Person</th><th>Would report to</th></tr></thead>
      <tbody>
      <?php foreach ($proposals as $p): ?>
        <tr><td><?= e($p['user']) ?> <span class="muted"><?= e(ORG_ROLES[$p['role']] ?? $p['role']) ?></span></td>
            <td><?= e($p['manager']) ?> <span class="muted"><?= e(ORG_ROLES[$p['manager_role']] ?? $p['manager_role']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </details>
  <form method="post" action="/hierarchy" style="display:inline">
    <input type="hidden" name="do" value="auto">
    <button class="btn" type="submit">Arrange these <?= count($proposals) ?> automatically</button>
  </form>
  <span class="muted" style="margin-left:8px">Only people who have no manager are touched.</span>
</div>
<?php endif; ?>

<?php if (!$tree): ?>
  <div class="panel"><p class="muted">No active users yet.</p></div>
<?php else: ?>
  <div class="panel oc-wrap">
    <div class="oc-tools">
      <button type="button" class="btn small secondary" id="oc-expand">Expand all</button>
      <button type="button" class="btn small secondary" id="oc-collapse">Collapse all</button>
      <span class="muted" style="margin-left:6px">Click − on a card to fold that branch. Drag sideways to pan.</span>
    </div>
    <div class="oc-scroll">
      <ul class="oc-chart">
        <?php foreach ($tree as $root) org_node_html($root, $all, $canEdit); ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<style>
  /* ---- Top-down organisation chart -------------------------------------
     Each level is a flex row; the connectors are drawn with borders on the
     ::before / ::after of every node, which is what turns a nested list into
     a chart with lines instead of an indented outline. */
  .oc-wrap{padding:12px}
  .oc-tools{display:flex;gap:6px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
  .oc-scroll{overflow-x:auto;overflow-y:hidden;padding:10px 4px 18px}
  .oc-chart, .oc-chart ul{display:flex;justify-content:center;list-style:none;margin:0;padding:0}
  .oc-chart ul{padding-top:26px;position:relative}
  .oc-chart li{position:relative;padding:26px 10px 0;text-align:center;list-style:none}

  /* the two half-width lines that join siblings together */
  .oc-chart li::before, .oc-chart li::after{
    content:'';position:absolute;top:0;right:50%;width:50%;height:26px;
    border-top:2px solid var(--line);
  }
  .oc-chart li::after{right:auto;left:50%;border-left:2px solid var(--line)}
  /* an only child needs no horizontal line, just the vertical drop */
  .oc-chart li:only-child::after,.oc-chart li:only-child::before{display:none}
  .oc-chart li:only-child{padding-top:26px}
  /* trim the outer edges so the line does not overhang the first/last card */
  .oc-chart li:first-child::before,.oc-chart li:last-child::after{border:0 none}
  .oc-chart li:last-child::before{border-right:2px solid var(--line);border-radius:0 6px 0 0}
  .oc-chart li:first-child::after{border-radius:6px 0 0 0}
  /* the drop from a parent down to its children's connector line */
  .oc-chart ul::before{content:'';position:absolute;top:0;left:50%;width:0;height:26px;border-left:2px solid var(--line)}
  /* the roots sit side by side with no line above them */
  .oc-chart > li{padding-top:0}
  .oc-chart > li::before,.oc-chart > li::after{display:none}

  .oc-node{position:relative;display:inline-block}
  .oc-card{display:inline-flex;flex-direction:column;gap:3px;text-align:left;min-width:190px;max-width:230px;
    background:var(--card);border:1px solid var(--line);border-radius:12px;padding:10px 12px;
    box-shadow:0 1px 2px rgba(0,0,0,.05)}
  .oc-card.oc-top{border-color:var(--brand);box-shadow:0 0 0 2px color-mix(in srgb,var(--brand) 18%,transparent)}
  .oc-head{display:flex;gap:9px;align-items:center}
  .oc-av{width:30px;height:30px;flex:0 0 30px;border-radius:50%;background:var(--brand);color:#fff;
    display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
  .oc-id{display:flex;flex-direction:column;min-width:0}
  .oc-id b{font-size:13.5px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-role{font-size:11.5px;color:var(--brand);font-weight:600;line-height:1.3}
  .oc-mail{font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-meta{margin-top:2px}
  .oc-meta .pill{font-size:10.5px;padding:1px 7px}
  .oc-act{display:flex;gap:6px;align-items:center;margin-top:4px;font-size:11px}
  .oc-act select{font-size:11px;max-width:118px;border:1px solid var(--line);border-radius:6px;
    padding:2px 4px;background:var(--field);color:var(--ink)}
  .oc-move{margin:0}

  .oc-toggle{position:absolute;left:50%;bottom:-13px;transform:translateX(-50%);
    width:22px;height:22px;border-radius:50%;border:1px solid var(--line);background:var(--card);
    color:var(--muted);font-size:14px;line-height:1;cursor:pointer;z-index:2;padding:0}
  .oc-toggle:hover{border-color:var(--brand);color:var(--brand)}
  /* a folded branch hides its children and flips the button to + */
  li.oc-folded > ul{display:none}

  @media print{
    .master-head .btn,.side,.nav-toggle,.oc-tools,.oc-act,.oc-toggle,.crumbs{display:none!important}
    .oc-scroll{overflow:visible}
    .oc-card{box-shadow:none}
  }
</style>
<script>
(function(){
  var chart = document.querySelector('.oc-chart');
  if (!chart) return;
  function setFolded(li, folded){
    li.classList.toggle('oc-folded', folded);
    var node = li.firstElementChild;
    var btn = node ? node.querySelector('.oc-toggle') : null;
    if (btn) btn.textContent = folded ? '+' : '−';
  }
  chart.addEventListener('click', function(e){
    var btn = e.target.closest ? e.target.closest('.oc-toggle') : null;
    if (!btn) return;
    var li = btn.closest('li');
    setFolded(li, !li.classList.contains('oc-folded'));
  });
  var ex = document.getElementById('oc-expand'), co = document.getElementById('oc-collapse');
  if (ex) ex.addEventListener('click', function(){
    chart.querySelectorAll('li').forEach(function(li){ setFolded(li, false); });
  });
  // Collapse everything that has children — the readable default for a big org.
  if (co) co.addEventListener('click', function(){
    chart.querySelectorAll('li').forEach(function(li){
      if (li.querySelector('ul')) setFolded(li, true);
    });
  });
  // Drag to pan, so a wide chart can be moved without hunting for the scrollbar.
  var sc = document.querySelector('.oc-scroll'), down = false, x0 = 0, l0 = 0;
  if (sc) {
    sc.addEventListener('mousedown', function(e){
      if (e.target.closest && e.target.closest('.oc-card')) return;   // let card clicks work
      down = true; x0 = e.pageX; l0 = sc.scrollLeft; sc.style.cursor = 'grabbing';
    });
    ['mouseup','mouseleave'].forEach(function(ev){
      sc.addEventListener(ev, function(){ down = false; sc.style.cursor = ''; });
    });
    sc.addEventListener('mousemove', function(e){
      if (!down) return; e.preventDefault(); sc.scrollLeft = l0 - (e.pageX - x0);
    });
  }
})();
</script>
