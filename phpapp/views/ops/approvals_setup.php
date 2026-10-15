<?php
//  The approvals workbench — one place that answers "who approves what, and
//  where do I change it?". See lib/approvals_setup.php for why this is one screen
//  organised by module rather than four screens in four modules.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<div class="master-head">
  <div>
    <h1>Approvals</h1>
    <p class="sub" style="margin:2px 0 0">
      Every approval chain in the business, in one place. Each one still belongs to its
      own module — open it here or from that module's own setup.
    </p>
  </div>
</div>

<?= $strip ?>

<?php if (!$open): ?>
  <div class="panel" style="margin-top:14px">
    <h3 class="tab-sub">Nothing here is yours to change</h3>
    <p class="muted" style="margin:6px 0 0">
      Approval chains are configured by an administrator. If you need one changed — who signs
      off a quotation, how long an approver has before it escalates, or who covers for
      somebody on leave — ask your workspace administrator.
    </p>
    <p class="muted" style="margin:8px 0 0">
      To see what is waiting on <strong>you</strong>, open <a href="/my-approvals">My approvals</a>.
    </p>
  </div>
<?php else: ?>

  <div class="card-grid" style="margin-top:14px">
    <?php foreach ($open as $a): ?>
      <a class="master-card" href="<?= $e($a['url']) ?>" style="display:grid;gap:6px;align-content:start">
        <strong style="font-size:15px"><?= $e($a['icon']) ?> <?= $e($a['label']) ?></strong>
        <span class="muted" style="font-size:13px;line-height:1.5"><?= $e($a['what']) ?></span>
        <span class="muted" style="font-size:12px"><code><?= $e($a['url']) ?></code></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
    //  Name what is NOT shown, and why. A blank space where a colleague sees a
    //  tile is the thing that makes people think a screen is missing — which is
    //  exactly the report that started this (UAT 1.7.1: "recruitment approvals
    //  do not exist"). It never says WHO may open it, only that it exists.
    $closed = array_values(array_filter($areas, fn($x) => !$x['can']));
    if ($closed): ?>
    <div class="panel" style="margin-top:14px">
      <h3 class="tab-sub">Also in this workspace, but not yours to change</h3>
      <ul class="muted" style="margin:6px 0 0;padding-left:20px;font-size:13px;line-height:1.7">
        <?php foreach ($closed as $c): ?>
          <li><strong><?= $e($c['label']) ?></strong> — <?= $e($c['what']) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="muted" style="margin:8px 0 0;font-size:12.5px">
        These exist and are configured by somebody else. Listed so a chain is never assumed
        missing just because this account cannot open it.
      </p>
    </div>
  <?php endif; ?>

  <div class="panel" style="margin-top:14px">
    <h3 class="tab-sub">How these fit together</h3>
    <ul class="muted" style="margin:6px 0 0;padding-left:20px;font-size:13px;line-height:1.7">
      <li>A <strong>chain</strong> decides who signs off a record, in what order, and how long each
        person has before it escalates.</li>
      <li>A chain belongs to <strong>one module</strong> — recruitment chains never route a quotation,
        and quote chains never route an offer.</li>
      <li><strong>Delegation</strong> is company-wide on purpose: cover for somebody on leave applies
        to everything they approve, not one module at a time.</li>
      <li>Nobody may approve their own submission unless your workspace deliberately allows it —
        that switch lives with the recruitment chain and applies to the whole workspace.</li>
    </ul>
    <p class="muted" style="margin:10px 0 0;font-size:12.5px">
      Waiting on you right now? That is <a href="/my-approvals">My approvals</a>, not this screen —
      this one is where the rules are set.
    </p>
  </div>

<?php endif; ?>
