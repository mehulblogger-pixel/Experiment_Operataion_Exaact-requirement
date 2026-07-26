<div class="crumbs"><a href="/">Home</a> › <a href="/data-requests">Requests about personal data</a> › <span>Erase</span></div>

<div class="master-head">
  <div><h1>Erase <?= e($prev['name']) ?></h1>
    <p class="sub" style="margin:2px 0 0">Read what stays before you do this. It cannot be undone.</p></div>
</div>

<?php // An honest erasure is not "delete everything". A report a client relies on,
      // and the trail behind it, are records this company is obliged to keep. Saying
      // so plainly is better than a promise that quietly is not kept. ?>
<div class="panel-split">
  <div class="panel" style="border:1px solid var(--bad)">
    <div class="ctitle" style="margin-top:0"><h3 style="color:var(--bad)">What goes</h3></div>
    <ul style="font-size:14px;line-height:1.8;margin:0;padding-left:20px">
      <?php foreach ($prev['removed'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <div class="panel" style="border:1px solid var(--info)">
    <div class="ctitle" style="margin-top:0"><h3>What stays, and why</h3></div>
    <ul style="font-size:14px;line-height:1.8;margin:0;padding-left:20px">
      <?php foreach ($prev['kept'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?>
    </ul>
    <p class="muted" style="font-size:12.5px;margin-top:10px">
      The DPDP Act allows data to be kept where another law requires it. An inspection record and its audit trail
      are exactly that. If the person asks why their name is still on an old report, this is the answer to give.
    </p>
  </div>
</div>

<form method="post" action="/person-erase?kind=<?= e($kind) ?>&id=<?= (int)$id ?>" class="panel">
  <input type="hidden" name="_do" value="erase">
  <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-grid">
    <div class="ff ff-wide"><label>Why — this goes on the audit trail</label>
      <input class="form-control" name="reason" required placeholder="e.g. asked to be removed by e-mail on 12 August, request logged as #4"></div>
    <div class="ff"><label>Type <strong>ERASE</strong> to confirm</label>
      <input class="form-control" name="confirm" required placeholder="ERASE"></div>
    <div class="ff ff-check" style="align-items:end">
      <button class="btn" type="submit" style="background:var(--bad)">Erase</button>
      <a class="btn secondary" href="/data-requests" style="margin-left:8px">Cancel</a></div>
  </div>
</form>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Before you erase — send them their copy</h3></div>
  <p class="muted" style="font-size:13.5px">
    If they asked for a copy as well, take it first: once erased it cannot be produced.
  </p>
  <a class="btn secondary" href="/person-data?kind=<?= e($kind) ?>&id=<?= (int)$id ?>">⬇ Download everything held about them</a>
</div>
