<?php
// Styles and behaviour for the permission verb grid. Included ONCE per page,
// after the last grid. Kept beside _perm_verb_grid.php so the markup and the
// script that drives it are changed together.
?>
<style>
/* ---- The verb grid -------------------------------------------------------
   One markup, two layouts. On a laptop it is a grid an administrator can scan
   a column at a time; on a phone each module becomes a short list of large,
   labelled switches. Deliberately NOT a squeezed table: six columns at 390px
   is unreadable, and CLAUDE.md forbids averaging a desk screen and a phone
   screen into one middle. This screen is desk-first — administrators sit at a
   laptop — but it must still be usable in a hand, not broken.              */
.vgrid-block{margin:18px 0 6px}
.vgrid-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 6px;
  border-bottom:1px solid var(--line,#e5e7eb);padding-bottom:4px}
.vgrid-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--brand,#234e70)}
.vgrid-allof{margin-left:auto;font-size:12px;font-weight:600;color:var(--accent,#234e70)}
.vgrid th.vh{text-align:center;white-space:nowrap;vertical-align:bottom}
.vgrid .vcol{font-weight:700;font-size:12px;justify-content:center;cursor:pointer}
.vgrid .vcell{text-align:center;width:1%}
.vgrid .vcell input{width:18px;height:18px;cursor:pointer}
.vgrid .vname{min-width:190px}
.vgrid .rowall{font-weight:400;cursor:pointer}
.vgrid .vna,.vgrid .vlock{opacity:.45}
.vgrid tbody.mgroup tr.vrow:hover td{background:var(--soft)}

@media (max-width:760px){
  /* Gloves and sunlight: every switch is its own full-width row with a real
     word beside it and a 44px-plus target. No horizontal scrolling. */
  .vgrid-wrap{overflow:visible}
  .vgrid,.vgrid tbody,.vgrid tr,.vgrid td{display:block;width:auto}
  .vgrid thead{display:none}
  .vgrid tr.vrow{border:1px solid var(--line,#e3e8ef);border-radius:10px;margin:10px 0;overflow:hidden}
  .vgrid td.vname{display:block;padding:12px;background:var(--soft);border-bottom:1px solid var(--line,#e3e8ef)}
  .vgrid td.vcell{display:flex;align-items:center;justify-content:space-between;
                  gap:14px;width:auto;text-align:left;min-height:44px;padding:8px 14px;
                  border-top:1px solid var(--line,#eef1f5)}
  .vgrid td.vcell::before{content:attr(data-v);font-weight:600;font-size:14px}
  .vgrid td.vcell input{width:24px;height:24px}
  .vgrid td.vcell.vna::before,.vgrid td.vcell.vlock::before{opacity:.55}
}
</style>

<script>
(function(){
  // ---- Verb implications, the same chain PERM_VERB_IMPLIES holds in PHP ----
  // Ticking a strong verb fills in the weaker ones it must include; unticking a
  // weak one clears the stronger ones that depended on it. Doing BOTH matters:
  // without the second half, unticking View would appear to work, the server
  // would silently re-add it when it closed the set, and the administrator
  // would be told something was saved that was not.
  var IMPLIES = {add:['view'], edit:['view'], archive:['view','edit'],
                 delete:['view','edit'], approve:['view']};
  var byMod = {};
  [].slice.call(document.querySelectorAll('.vbox')).forEach(function(b){
    (byMod[b.dataset.mod] = byMod[b.dataset.mod] || {})[b.dataset.verb] = b;
  });
  function cascade(box){
    var row = byMod[box.dataset.mod] || {}, v = box.dataset.verb;
    if(box.checked){
      (IMPLIES[v]||[]).forEach(function(w){ if(row[w]) row[w].checked = true; });
    } else {
      Object.keys(IMPLIES).forEach(function(s){
        if(IMPLIES[s].indexOf(v) >= 0 && row[s]) row[s].checked = false;
      });
    }
  }
  function sync(el, boxes){
    if(!el) return;
    var on = boxes.filter(function(b){return b.checked;}).length;
    el.checked = on === boxes.length && boxes.length > 0;
    el.indeterminate = on > 0 && on < boxes.length;
  }
  function colBoxes(v){ return [].slice.call(document.querySelectorAll('.vbox[data-verb="'+v+'"]')); }
  var cols = [].slice.call(document.querySelectorAll('.col-all'));
  var rows = [].slice.call(document.querySelectorAll('tr.vrow'));

  // "Give this one thing everywhere" / "full control of this module".
  cols.forEach(function(c){
    c.addEventListener('change', function(){
      colBoxes(c.dataset.verb).forEach(function(b){ b.checked = c.checked; cascade(b); });
      window.permGridRefresh();
    });
  });
  rows.forEach(function(tr){
    var t = tr.querySelector('.row-all');
    if(!t) return;
    t.addEventListener('change', function(){
      [].slice.call(tr.querySelectorAll('.vbox')).forEach(function(b){ b.checked = t.checked; });
      window.permGridRefresh();
    });
  });
  [].slice.call(document.querySelectorAll('.vbox')).forEach(function(b){
    b.addEventListener('change', function(){ cascade(b); window.permGridRefresh(); });
  });

  // One place that re-derives every heading from the boxes, so no toggle has to
  // remember what any other toggle did. Exposed so the page's own group and
  // "select everything" toggles can call it after they run.
  window.permGridRefresh = function(){
    cols.forEach(function(c){ sync(c, colBoxes(c.dataset.verb)); });
    rows.forEach(function(tr){ sync(tr.querySelector('.row-all'), [].slice.call(tr.querySelectorAll('.vbox'))); });
  };
  window.permGridCascade = cascade;
  window.permGridRefresh();
})();
</script>
