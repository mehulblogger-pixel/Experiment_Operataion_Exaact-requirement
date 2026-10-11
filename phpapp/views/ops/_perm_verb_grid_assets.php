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
/*  The bulk row — deliberately NOT styled like the headings above it. It is a
    control, it says so, and it is visually separated so nobody reads it as part
    of the table's title row. */
.vgrid tr.vbulk th{background:var(--soft);border-top:1px solid var(--line);
  font-weight:400;padding-top:4px;padding-bottom:4px}
.vgrid tr.vbulk .vbulk-lbl{font-size:11px;font-weight:600;letter-spacing:.02em;
  text-transform:none;color:var(--muted)}
.vgrid tr.vbulk .vcol{justify-content:center}
.vgrid tr.vbulk .vcol-all{font-size:10.5px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)}
/*  ONE undo bar for the page, pinned to the bottom of the viewport so it is
    where your eye already is after a bulk change, whichever block you were in.
    Hidden until a bulk action has actually happened. */
.vgrid-undo{position:fixed;left:50%;transform:translateX(-50%);bottom:16px;z-index:60;
  display:flex;align-items:center;gap:12px;padding:9px 12px 9px 14px;max-width:min(620px,calc(100vw - 24px));
  border:1px solid var(--line);border-radius:999px;background:var(--card);
  box-shadow:0 6px 24px rgba(0,0,0,.16);font-size:12.5px;color:var(--ink)}
.vgrid-undo[hidden]{display:none}
.vgrid-undo-what{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vgrid-undo button{flex:none}
@media (max-width:760px){
  .vgrid tr.vbulk{display:none}   /* the phone layout has no columns to bulk-set */
}
</style>

<div class="vgrid-undo" id="vgrid-undo" hidden>
  <span class="vgrid-undo-what"></span>
  <button type="button" class="btn secondary small">Undo</button>
</div>

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
  //  EVERY BULK CONTROL IS SCOPED TO ITS OWN BLOCK.
  //
  //  UAT 1.5.3: "when any heading like view, edit, delete etc. at top of any
  //  modules is changed it is reflected to every module." Exactly so, and it was
  //  a plain bug: colBoxes() asked the whole DOCUMENT for boxes of that verb, so
  //  the View header inside the Recruitment block toggled View on Sales,
  //  Operations, Money and every other block on the page — and its own tick-state
  //  was read from the whole page too, which is why it showed a dash that had
  //  nothing to do with the modules underneath it. A control inside a block must
  //  act on that block and report that block, and nothing else.
  function block(el){ return el.closest('.vgrid-block') || document; }
  function boxesIn(root, sel){ return [].slice.call(root.querySelectorAll(sel)); }

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
  //  The boxes for one verb WITHIN one block — never the whole page.
  function colBoxes(c){ return boxesIn(block(c), '.vbox[data-verb="' + c.dataset.verb + '"]'); }
  var cols = [].slice.call(document.querySelectorAll('.col-all'));
  var rows = [].slice.call(document.querySelectorAll('tr.vrow'));

  //  ---- UNDO -------------------------------------------------------------
  //  A bulk control changes many ticks at once, and before this there was no way
  //  back: one click rewrote a column and the only recovery was to remember what
  //  had been there. Every bulk action now photographs the grid first, and the
  //  Undo button puts it back. Nothing here saves anything — Save is still the
  //  only thing that writes — but an administrator should not have to know that
  //  to feel safe pressing a button.
  function snapshot(){
    return [].slice.call(document.querySelectorAll('.vbox')).map(function(b){ return b.checked; });
  }
  function restore(shot){
    [].slice.call(document.querySelectorAll('.vbox')).forEach(function(b,i){ b.checked = !!shot[i]; });
    window.permGridRefresh();
  }
  var undoStack = [];
  function remember(what){
    undoStack.push({shot: snapshot(), what: what});
    if(undoStack.length > 25) undoStack.shift();
    showUndo();
  }
  function showUndo(){
    var bar = document.getElementById('vgrid-undo');
    if(!bar) return;
    var top = undoStack[undoStack.length - 1];
    bar.hidden = !top;
    if(top) bar.querySelector('.vgrid-undo-what').textContent = top.what;
  }
  window.permGridRemember = remember;
  document.addEventListener('click', function(ev){
    var b = ev.target.closest('#vgrid-undo button');
    if(!b) return;
    ev.preventDefault();
    var top = undoStack.pop();
    if(top) restore(top.shot);
    showUndo();
  });

  // "Give this one thing to every module IN THIS GROUP" / "full control of this
  // module". Both photograph the grid first, so either can be taken back.
  cols.forEach(function(c){
    c.addEventListener('change', function(){
      var grp = block(c).getAttribute('data-group') || 'this group';
      remember((c.checked ? 'Gave ' : 'Removed ') + (c.dataset.verbLabel || c.dataset.verb)
               + ' on every module in ' + grp);
      colBoxes(c).forEach(function(b){ b.checked = c.checked; cascade(b); });
      window.permGridRefresh();
    });
  });
  rows.forEach(function(tr){
    var t = tr.querySelector('.row-all');
    if(!t) return;
    t.addEventListener('change', function(){
      remember((t.checked ? 'Gave full control of ' : 'Removed everything on ')
               + (tr.getAttribute('data-modlabel') || 'that module'));
      boxesIn(tr, '.vbox').forEach(function(b){ b.checked = t.checked; });
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
    cols.forEach(function(c){ sync(c, colBoxes(c)); });
    rows.forEach(function(tr){ sync(tr.querySelector('.row-all'), boxesIn(tr, '.vbox')); });
    showUndo();
  };
  window.permGridCascade = cascade;
  window.permGridRefresh();
})();
</script>
