<?php // Configure the pre-order review checklist — editable, on/off. ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/quotes"><?= e(THP('quote')) ?></a> › Pre-order checklist</div>
<div class="master-head"><div><h1>Pre-order review checklist</h1>
  <p class="sub" style="margin:2px 0 0">The points reviewed before a <?= e(Tl('quote')) ?> is approved — enquiry / tender / contract review and the commercial checks. Fully editable, and you can switch it on or off.</p></div></div>

<form method="post" action="/preorder-checklist" class="panel" style="max-width:760px">
  <label class="chk" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
    <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
    <b>Show the pre-order checklist</b> on quotations
  </label>
  <p class="muted" style="margin:0 0 12px;font-size:12.5px">When on, the checklist appears on every <?= e(Tl('quote') ) ?> so the owner and approver can tick the points. When off, quoting works exactly as before.</p>

  <label class="chk" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
    <input type="checkbox" name="require_all" value="1" <?= $require_all ? 'checked' : '' ?>>
    Require <b>every point ticked</b> before a <?= e(Tl('quote')) ?> can be approved
  </label>
  <p class="muted" style="margin:0 0 14px;font-size:12.5px">Leave this off to keep the checklist as guidance only. It never blocks a rejection.</p>

  <div class="ff">
    <label>Check points — one per line</label>
    <textarea class="form-control" name="items" rows="10" style="font-family:inherit;font-size:13.5px" placeholder="One check point per line…"><?= e($items) ?></textarea>
    <small class="muted">Edit freely — these are suggestions to get you started; make them your own.</small>
  </div>

  <div style="margin-top:14px"><button class="btn" type="submit">Save pre-order checklist</button>
    <a class="btn secondary" href="/quotes">Back to <?= e(THP('quote')) ?></a></div>
</form>
