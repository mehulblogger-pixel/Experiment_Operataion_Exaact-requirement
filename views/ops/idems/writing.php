<?php
  // Technical writing assistant — shorthand in, engineering language out. Works without AI.
  $byCat = [];
  foreach ($phrases as $p) $byCat[$p['category']][] = $p;
?>
<div class="crumbs"><a href="/">Home</a> › Writing assistant</div>
<div class="master-head">
  <div><h1>Technical writing assistant</h1>
    <p class="sub" style="margin:2px 0 0">Type what you saw in plain shorthand — one point per line. The system converts it into correct, factual engineering language using the standard phrase library. No AI required.</p></div>
  <?php if (is_master() || can('idems.type.manage') || can('master.manage')): ?><a class="btn secondary" href="/phrase-library">Phrase library</a><?php endif; ?>
</div>

<div class="dash-2col" style="align-items:start">
  <div>
    <form method="post" action="/writing-assistant" class="panel">
      <label>Your observations <span class="muted">— one per line, e.g. “dimension ok”, “minor dent”, “witnessed hydro test”</span></label>
      <textarea class="form-control" name="text" rows="8" placeholder="dimension ok&#10;surface clean&#10;minor dent&#10;witnessed hydro test"><?= e($input) ?></textarea>
      <div style="margin-top:10px"><button class="btn" type="submit">Convert to report language</button></div>
    </form>

    <?php if ($result): ?>
      <div class="panel">
        <div class="ctitle" style="margin-top:0"><h3>Report text</h3><button class="btn small secondary" onclick="idemsCopy()">Copy</button></div>
        <textarea class="form-control" id="outText" rows="8"><?= e($result['text']) ?></textarea>
        <?php if (!empty($result['lines'])): ?>
        <div style="margin-top:10px">
          <div class="muted" style="font-size:12px;margin-bottom:4px">How each line was handled:</div>
          <?php foreach ($result['lines'] as $l): ?>
            <div style="font-size:12px;margin-bottom:4px">
              <span class="pill <?= $l['matched'] ? 'p-ok' : 'p-mut' ?>"><?= $l['matched'] ? 'library' : 'tidied' ?></span>
              <span class="muted"><?= e($l['in']) ?></span> → <?= e($l['out']) ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Standard phrases</h3></div>
    <p class="muted" style="margin:0 0 6px">Click any phrase to copy it.</p>
    <?php foreach ($cats as $ck=>$cl): if (empty($byCat[$ck])) continue; ?>
      <div style="margin-bottom:10px">
        <div style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand)"><?= e($cl) ?></div>
        <?php foreach ($byCat[$ck] as $p): ?>
          <div class="phr" onclick="idemsCopyText(this.getAttribute('data-p'))" data-p="<?= e($p['phrase']) ?>" title="Click to copy">
            <?php if ($p['shorthand']): ?><code><?= e($p['shorthand']) ?></code> <?php endif; ?><?= e(mb_strimwidth($p['phrase'], 0, 110, '…')) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<style>
  .phr{padding:5px 0;border-bottom:1px solid var(--line);font-size:12px;cursor:pointer}
  .phr:last-child{border-bottom:0}
  .phr:hover{color:var(--brand)}
  .phr code{font-size:11px;background:var(--soft);padding:1px 4px;border-radius:4px}
</style>
<script>
function idemsCopy(){ var t=document.getElementById('outText'); t.select(); document.execCommand('copy'); }
function idemsCopyText(s){ var a=document.createElement('textarea'); a.value=s; document.body.appendChild(a); a.select(); document.execCommand('copy'); document.body.removeChild(a); }
</script>
