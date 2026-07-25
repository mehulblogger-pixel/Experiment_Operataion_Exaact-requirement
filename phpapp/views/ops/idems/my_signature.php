<?php // Self-service: capture your digital signature (added automatically to reports you approve). ?>
<div class="crumbs"><a href="/">Home</a> › My signature</div>
<div class="master-head"><div><h1>My signature</h1>
  <p class="sub" style="margin:2px 0 0">Draw or upload your signature once. It is added <strong>automatically</strong> to reports you approve, and to reports where you are the inspector — no manual upload each time.</p></div></div>

<div class="panel" style="max-width:520px">
  <?php if (!empty($sig)): ?>
    <div style="margin-bottom:10px"><span class="muted">Current signature:</span><br><img src="<?= e($sig) ?>" alt="signature" style="max-width:280px;border:1px solid var(--line);border-radius:8px;background:#fff;margin-top:6px"></div>
  <?php endif; ?>
  <form method="post" action="/my-signature" enctype="multipart/form-data">
    <label>Draw your signature</label>
    <div><canvas id="sigpad" width="460" height="150" style="border:1px solid var(--line);border-radius:8px;background:#fff;touch-action:none;max-width:100%"></canvas></div>
    <input type="hidden" name="sig" id="sigv">
    <div style="margin:6px 0"><button type="button" class="btn small secondary" onclick="sigClear()">Clear</button></div>
    <label style="margin-top:8px">…or upload an image (PNG/JPG)</label>
    <input class="form-control" type="file" name="sigfile" accept="image/*">
    <div style="margin-top:12px;display:flex;gap:6px">
      <button class="btn" type="submit">Save signature</button>
      <?php if (!empty($sig)): ?><button class="btn secondary" type="submit" name="_do" value="clear" onclick="return confirm('Remove your saved signature?')">Remove</button><?php endif; ?>
    </div>
  </form>
</div>

<script>
(function(){
  var c=document.getElementById('sigpad'), ctx=c.getContext('2d'), draw=false, dirty=false;
  ctx.lineWidth=2.2; ctx.lineCap='round'; ctx.strokeStyle='#111';
  function pos(e){ var r=c.getBoundingClientRect(); var t=e.touches?e.touches[0]:e; return {x:(t.clientX-r.left)*(c.width/r.width),y:(t.clientY-r.top)*(c.height/r.height)}; }
  function s(e){ draw=true; var p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault(); }
  function m(e){ if(!draw)return; var p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); dirty=true; e.preventDefault(); }
  function en(){ draw=false; if(dirty) document.getElementById('sigv').value=c.toDataURL('image/png'); }
  c.addEventListener('mousedown',s); c.addEventListener('mousemove',m); window.addEventListener('mouseup',en);
  c.addEventListener('touchstart',s); c.addEventListener('touchmove',m); c.addEventListener('touchend',en);
  window.sigClear=function(){ ctx.clearRect(0,0,c.width,c.height); document.getElementById('sigv').value=''; };
})();
</script>
