<?php $groups = $res['groups']; ?>
<div class="crumbs"><a href="/">Home</a> › Search</div>
<div class="master-head">
  <div><h1>Search</h1>
  <p class="sub" style="margin:2px 0 0">One box across every register you are allowed to see. References, company names, contacts, invoice numbers, report numbers — whatever you have to hand.</p></div>
</div>

<form method="get" action="/search" class="panel" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:16px" role="search">
  <label class="sr-only" for="gsq">What are you looking for</label>
  <input class="form-control" id="gsq" name="q" value="<?= e($q) ?>" autofocus
         placeholder="A reference, a company, a person, an invoice number…"
         style="flex:1;min-width:240px;padding:9px 12px;font-size:14px">
  <?php if ($only !== ''): ?><input type="hidden" name="in" value="<?= e($only) ?>"><?php endif; ?>
  <button class="btn">Search</button>
  <?php if ($q !== ''): ?><a class="btn secondary" href="/search">Clear</a><?php endif; ?>
</form>

<?php if (!empty($res['failed'])): ?>
  <?php // Naming what failed matters: "no results" and "that register is broken"
        // must never look the same, or somebody concludes a record is missing. ?>
  <div class="msg msg-warning" style="margin-top:12px">
    These could not be searched just now, so anything in them is not shown:
    <b><?= e(implode(', ', $res['failed'])) ?></b>.
  </div>
<?php endif; ?>

<?php if (!empty($res['short'])): ?>
  <?php if ($q !== ''): ?>
    <p class="muted" style="margin-top:16px">Type at least <?= SEARCH_MIN ?> characters — one letter matches nearly everything.</p>
  <?php else: ?>
    <div class="panel" style="margin-top:16px">
      <h3 style="margin-top:0">What this looks in</h3>
      <p class="muted" style="font-size:13px;margin:0 0 10px">Only the registers your role can open. Branch scoping applies here exactly as it does on each register — search is not a way around it.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach (search_sources() as $k => $s): ?>
          <span class="pill p-mut"><?= $s['icon'] ?> <?= e($s['label']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

<?php elseif (!$groups): ?>
  <div class="panel" style="margin-top:16px">
    <p style="margin:0">Nothing matches <b><?= e($q) ?></b> in any register you can open.</p>
    <p class="muted" style="font-size:13px;margin:8px 0 0">Part of a word works — this matches anywhere inside a field, not just the start. If you expected a record from another branch, it is there but outside your scope.</p>
  </div>

<?php else: ?>
  <div class="panel" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span class="muted" style="font-size:12.5px"><?= (int)$res['total'] ?> match<?= $res['total']==1?'':'es' ?><?= $only!=='' ? '' : ' across ' . count($groups) . ' register' . (count($groups)==1?'':'s') ?></span>
    <span style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
      <a class="btn small<?= $only===''?'':' secondary' ?>" href="/search?q=<?= e(urlencode($q)) ?>">Everything</a>
      <?php foreach ($groups as $k => $g): ?>
        <a class="btn small<?= $only===$k?'':' secondary' ?>" href="/search?q=<?= e(urlencode($q)) ?>&amp;in=<?= e($k) ?>"><?= $g['icon'] ?> <?= e($g['label']) ?></a>
      <?php endforeach; ?>
    </span>
  </div>

  <?php foreach ($groups as $k => $g): ?>
    <div class="panel" style="margin-top:14px;padding:0;overflow:hidden">
      <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:8px">
        <b style="font-size:13.5px"><?= $g['icon'] ?> <?= e($g['label']) ?></b>
        <span class="muted" style="font-size:12px"><?= count($g['rows']) ?></span>
        <?php if ($g['more'] && $only === ''): ?>
          <a style="margin-left:auto;font-size:12.5px" href="/search?q=<?= e(urlencode($q)) ?>&amp;in=<?= e($k) ?>">There may be more — show all →</a>
        <?php endif; ?>
      </div>
      <ul style="list-style:none;margin:0;padding:0">
        <?php foreach ($g['rows'] as $r): ?>
          <li style="border-bottom:1px solid var(--line)">
            <a href="<?= e($r['url']) ?>" style="display:block;padding:11px 16px;text-decoration:none<?= !empty($r['dim']) ? ';opacity:.62' : '' ?>">
              <b style="font-size:13.5px"><?= e($r['title']) ?></b>
              <?php if (trim((string)($r['subtitle'] ?? '')) !== ''): ?>
                <span class="muted" style="font-size:12.5px"> — <?= e($r['subtitle']) ?></span>
              <?php endif; ?>
              <?php if (trim((string)($r['meta'] ?? '')) !== ''): ?>
                <div class="muted" style="font-size:12px;margin-top:2px"><?= e($r['meta']) ?></div>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
