<?php
// Phase 3 §49 — the uniform Entity-360 shell. A consistent "whole story" view for any registered
// entity: the same panels (tasks, history, and the kind-appropriate quality / party / money) in the
// same order, whatever the entity is.
?>
<div class="crumbs"><a href="/">Home</a> › <a href="<?= e($e['back']) ?>"><?= e($e['label']) ?></a> › 360</div>
<div class="master-head">
  <div>
    <h1><?= e($e['label']) ?> 360 — <?= e($e['title']) ?></h1>
    <p class="sub" style="margin:2px 0 0">Everything linked to this record, in one place.</p>
  </div>
  <div><a class="btn secondary" href="<?= e($e['back']) ?>">← Open the record</a></div>
</div>

<div style="margin-top:14px;display:flex;flex-direction:column;gap:0">
  <?php entity_360_render_panels($e['kind'], $e['id'], $e['panels']); ?>
</div>
