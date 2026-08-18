<?php // Configure the vetting authority's checklist — fully editable, on/off. ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <a href="/report-types">Report types</a> › Vetting checklist</div>
<div class="master-head"><div><h1>Vetting checklist</h1>
  <p class="sub" style="margin:2px 0 0">The check points the vetting authority ticks before clearing a <?= e(Tl('report')) ?>. Fully editable, and you can switch it on or off — nothing is fixed by the system.</p></div></div>

<form method="post" action="/vetting-checklist" class="panel" style="max-width:760px">
  <label class="chk" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
    <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
    <b>Show the vetting checklist</b> during vetting
  </label>
  <p class="muted" style="margin:0 0 12px;font-size:12.5px">When on, a senior reviewer sees these points as tick-boxes in the “Vetting &amp; debriefing” panel on every open <?= e(Tl('report')) ?>. When off, vetting works exactly as before.</p>

  <label class="chk" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
    <input type="checkbox" name="require_all" value="1" <?= $require_all ? 'checked' : '' ?>>
    Require <b>every point ticked</b> before a report can be vetted (cleared)
  </label>
  <p class="muted" style="margin:0 0 14px;font-size:12.5px">Leave this off to let the reviewer clear a report with the checklist as guidance only. It never blocks “Return for correction” or “Record debrief”.</p>

  <div class="ff">
    <label>Check points — one per line</label>
    <textarea class="form-control" name="items" rows="12" style="font-family:inherit;font-size:13.5px" placeholder="One check point per line…"><?= e($items) ?></textarea>
    <small class="muted">Edit freely — add, remove or reword any line. These are suggestions to get you started; make them your own.</small>
  </div>

  <div style="margin-top:14px"><button class="btn" type="submit">Save vetting checklist</button>
    <a class="btn secondary" href="/report-types">Back to report types</a></div>
</form>
