<?php
// Connect K0+ — Universal taxonomy graph ADMIN. Add/edit/retire nodes, manage
// synonyms and relationships — so new professions are added without code.
$summary = $summary ?? []; $kinds = $kinds ?? []; $nodes = $nodes ?? []; $node = $node ?? null;
$aliases = $aliases ?? []; $edges = $edges ?? []; $picker = $picker ?? [];
$kind = (string)($kind ?? ''); $q = (string)($q ?? '');
$pill = fn($s) => '<span class="cxpill ' . (strtoupper((string)$s) === 'RETIRED' ? 'muted' : 'ok') . '">' . e(ucfirst(strtolower((string)$s))) . '</span>';
$opt = function ($sel = 0) use ($picker) {
    $by = [];
    foreach ($picker as $p) $by[$p['kind']][] = $p;
    $h = '';
    foreach ($by as $k => $rows) {
        $h .= '<optgroup label="' . e($k) . '">';
        foreach ($rows as $r) $h .= '<option value="' . (int)$r['id'] . '"' . ((int)$r['id'] === (int)$sel ? ' selected' : '') . '>' . e($r['name']) . '</option>';
        $h .= '</optgroup>';
    }
    return $h;
};
?>
<style>
  .cxpill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:600}
  .cxpill.ok{background:#e7f5ef;color:#0f7d5a}.cxpill.muted{background:#eceff1;color:#5b6b6a}.cxpill.info{background:#e6f0fb;color:#1858a8}
  .taxtbl{width:100%;border-collapse:collapse;font-size:13.5px}
  .taxtbl th{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line,#e3ebea);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted,#667)}
  .taxtbl td{padding:8px 10px;border-bottom:1px solid var(--line,#eee);vertical-align:middle}
  .taxtbl tr.on td{background:rgba(15,125,125,.06)}
  .taxform label{display:block;font-size:12.5px;color:var(--muted,#667);margin:8px 0 3px}
  .taxform input,.taxform select{width:100%;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px;background:var(--card,#fff);color:inherit;font-size:14px}
  .g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .achip{display:inline-flex;align-items:center;gap:6px;background:rgba(15,125,125,.08);border:1px solid rgba(15,125,125,.25);border-radius:999px;padding:3px 6px 3px 11px;font-size:12.5px;margin:3px 4px 0 0}
  .achip form{display:inline;margin:0} .achip button{border:0;background:transparent;color:#9a2a2a;cursor:pointer;font-size:13px}
</style>

<div class="crumbs"><a href="/">Home</a> › <a href="/connect-taxonomy">Taxonomy</a> › Manage graph</div>
<div class="master-head"><div><h1>Taxonomy graph — admin</h1>
  <p class="sub" style="margin:2px 0 0">Add, rename, retire, relate and alias technical concepts. New professions are added here — no code change. The marketplace search, profile and matching read this live.</p></div></div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">🧩</span><div class="k">Active nodes</div><div class="v"><?= (int)($summary['nodes'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🗂️</span><div class="k">Aliases</div><div class="v"><?= (int)($summary['aliases'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🔗</span><div class="k">Relations</div><div class="v"><?= (int)($summary['edges'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🚫</span><div class="k">Retired</div><div class="v"><?= (int)($summary['retired'] ?? 0) ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-top:14px;align-items:start">
  <div class="panel">
    <form method="get" action="/connect-taxonomy-admin" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
      <select name="kind" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"><option value="">All kinds</option>
        <?php foreach ($kinds as $k): ?><option value="<?= e($k) ?>" <?= $kind===$k?'selected':'' ?>><?= e($k) ?></option><?php endforeach; ?></select>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / alias" style="flex:1;min-width:140px;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
      <button class="btn" type="submit">Filter</button>
    </form>
    <div style="overflow:auto;max-height:640px">
      <table class="taxtbl">
        <thead><tr><th>Name</th><th>Kind</th><th>Parent</th><th>Aliases</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($nodes as $n): ?>
          <tr class="<?= $node && (int)$node['id']===(int)$n['id']?'on':'' ?>">
            <td><a href="/connect-taxonomy-admin?<?= e(http_build_query(['kind'=>$kind,'q'=>$q,'node'=>(int)$n['id']])) ?>"><strong><?= e($n['name']) ?></strong></a>
              <?php if (strtoupper((string)$n['status'])==='RETIRED'): ?> <?= $pill($n['status']) ?><?php endif; ?></td>
            <td><span class="cxpill info"><?= e($n['kind']) ?></span></td>
            <td class="sub" style="font-size:12.5px"><?= e($n['parent_name'] ?: '—') ?></td>
            <td><?= (int)$n['alias_count'] ?></td>
            <td style="text-align:right">
              <form method="post" action="/connect-taxonomy-admin" style="margin:0;display:inline">
                <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="node" value="<?= (int)$n['id'] ?>">
                <input type="hidden" name="action" value="node_status"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <input type="hidden" name="status" value="<?= strtoupper((string)$n['status'])==='RETIRED'?'ACTIVE':'RETIRED' ?>">
                <button class="btn sec" type="submit" style="padding:3px 9px;font-size:11.5px"><?= strtoupper((string)$n['status'])==='RETIRED'?'Restore':'Retire' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$nodes): ?><tr><td colspan="5" class="sub" style="padding:16px">No nodes match.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="panel">
      <h3 style="margin:0 0 8px">Add a node</h3>
      <form method="post" action="/connect-taxonomy-admin" class="taxform">
        <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="action" value="node_add">
        <div class="g2">
          <div><label>Kind</label><select name="kind_new"><?php foreach ($kinds as $k): ?><option value="<?= e($k) ?>" <?= $kind===$k?'selected':'' ?>><?= e($k) ?></option><?php endforeach; ?></select></div>
          <div><label>Code (optional)</label><input name="code" placeholder="e.g. RT"></div>
        </div>
        <label>Name</label><input name="name" required placeholder="e.g. Rope Access Inspector">
        <label>Parent (optional)</label><select name="parent_id"><option value="0">— none —</option><?= $opt() ?></select>
        <button class="btn" type="submit" style="margin-top:12px">Add node</button>
      </form>
    </div>

    <?php if ($node): ?>
    <div class="panel">
      <h3 style="margin:0 0 8px">Edit · <?= e($node['name']) ?> <span class="cxpill info"><?= e($node['kind']) ?></span> <?= $pill($node['status']) ?></h3>
      <form method="post" action="/connect-taxonomy-admin" class="taxform">
        <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="node" value="<?= (int)$node['id'] ?>">
        <input type="hidden" name="action" value="node_update"><input type="hidden" name="id" value="<?= (int)$node['id'] ?>">
        <div class="g2">
          <div><label>Name</label><input name="name" value="<?= e($node['name']) ?>" required></div>
          <div><label>Code</label><input name="code" value="<?= e($node['code']) ?>"></div>
        </div>
        <label>Parent</label><select name="parent_id"><option value="0">— none —</option><?= $opt((int)$node['parent_id']) ?></select>
        <button class="btn" type="submit" style="margin-top:12px">Save changes</button>
      </form>

      <div style="border-top:1px solid var(--line,#eee);margin-top:14px;padding-top:12px">
        <h4 style="margin:0 0 6px">Synonyms &amp; abbreviations</h4>
        <div style="margin-bottom:8px">
          <?php foreach ($aliases as $al): ?>
            <span class="achip"><?= e($al['alias']) ?>
              <form method="post" action="/connect-taxonomy-admin"><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="node" value="<?= (int)$node['id'] ?>"><input type="hidden" name="action" value="alias_del"><input type="hidden" name="alias_id" value="<?= (int)$al['id'] ?>"><button type="submit" title="Remove">✕</button></form>
            </span>
          <?php endforeach; ?>
        </div>
        <form method="post" action="/connect-taxonomy-admin" style="display:flex;gap:8px">
          <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="node" value="<?= (int)$node['id'] ?>"><input type="hidden" name="action" value="alias_add"><input type="hidden" name="node_id" value="<?= (int)$node['id'] ?>">
          <input name="alias" placeholder="Add a synonym / abbreviation" style="flex:1;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"><button class="btn sec" type="submit">Add</button>
        </form>
      </div>

      <div style="border-top:1px solid var(--line,#eee);margin-top:14px;padding-top:12px">
        <h4 style="margin:0 0 6px">Related concepts</h4>
        <div style="margin-bottom:8px">
          <?php foreach ($edges as $ed): ?>
            <span class="achip"><span class="cxpill <?= $ed['rel']==='SUGGESTS'?'info':'muted' ?>" style="font-size:10px"><?= e($ed['rel']) ?></span> <?= e($ed['to_name']) ?>
              <form method="post" action="/connect-taxonomy-admin"><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="node" value="<?= (int)$node['id'] ?>"><input type="hidden" name="action" value="edge_del"><input type="hidden" name="edge_id" value="<?= (int)$ed['id'] ?>"><button type="submit" title="Remove">✕</button></form>
            </span>
          <?php endforeach; ?>
          <?php if (!$edges): ?><span class="sub" style="font-size:12.5px">None yet.</span><?php endif; ?>
        </div>
        <form method="post" action="/connect-taxonomy-admin" style="display:flex;gap:8px;flex-wrap:wrap">
          <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="node" value="<?= (int)$node['id'] ?>"><input type="hidden" name="action" value="relate"><input type="hidden" name="from_id" value="<?= (int)$node['id'] ?>">
          <select name="rel" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"><option value="RELATED">Related to</option><option value="SUGGESTS">Suggests</option></select>
          <select name="to_id" style="flex:1;min-width:160px;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"><?= $opt() ?></select>
          <button class="btn sec" type="submit">Link</button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="panel sub" style="font-size:13px">Pick a node on the left to edit its name, synonyms and relationships.</div>
    <?php endif; ?>
  </div>
</div>
