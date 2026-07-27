<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Server check</div>
<div class="master-head">
  <div><h1>Server check</h1>
    <p class="sub" style="margin:2px 0 0">What this build is, and whether this server can run all of it.
      Quote the version below on any support message.</p></div>
  <a class="btn secondary" href="/settings">← Back</a>
</div>

<div class="panel" style="max-width:760px">
  <div class="kv-grid">
    <div><span class="k">Version</span><strong><?= e($version) ?></strong></div>
    <div><span class="k">Released</span><?= e(fdate($built)) ?></div>
    <div><span class="k">Database</span><?= e($driver) ?></div>
    <div><span class="k">PHP</span><?= e(PHP_VERSION) ?></div>
  </div>
</div>

<?php $blockers = array_values(array_filter($checks, fn($c) => $c[3] && !$c[2])); ?>
<?php if ($blockers): ?>
  <div class="msg msg-error" style="max-width:760px">
    <strong>This server is missing something the app needs.</strong>
    Until it is fixed, parts of the app will fail rather than degrade:
    <?= e(implode(', ', array_map(fn($c) => $c[1], $blockers))) ?>.
  </div>
<?php else: ?>
  <div class="msg msg-ok" style="max-width:760px">Everything this build requires is present on this server.</div>
<?php endif; ?>

<div class="panel" style="max-width:760px">
  <h3 class="tab-sub" style="margin-top:0">Requirements</h3>
  <table class="grid">
    <tr><th style="width:40%">What</th><th>Needed?</th><th>Here?</th><th>If it is missing</th></tr>
    <?php foreach ($checks as [$key, $label, $ok, $req, $note]): ?>
      <tr>
        <td><strong><?= e($label) ?></strong></td>
        <td><?= $req ? '<span class="pill p-info">required</span>' : '<span class="muted">optional</span>' ?></td>
        <td><?= $ok ? '<span class="pill p-ok">yes</span>'
                    : ($req ? '<span class="pill p-bad">NO</span>' : '<span class="pill p-warn">no</span>') ?></td>
        <td class="muted"><?= e($note ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="margin:10px 2px 0">An <strong>optional</strong> item that is missing does not break the app —
    it switches one feature off, and the screen that needed it says so.</p>
</div>

<?php if ($modules): ?>
<div class="panel" style="max-width:760px">
  <h3 class="tab-sub" style="margin-top:0">Modules on this installation</h3>
  <table class="grid">
    <tr><th style="width:40%">Module</th><th>State</th><th>Covers</th></tr>
    <?php foreach ($modules as $key => $m): ?>
      <tr>
        <td><strong><?= e($m['label']) ?></strong></td>
        <td><?= $m['on'] ? '<span class="pill p-ok">on</span>' : '<span class="pill p-mut">off</span>' ?>
            <?= $m['core'] ? ' <span class="muted">always</span>' : '' ?></td>
        <td class="muted"><?= e($m['desc']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
