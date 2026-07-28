<?php
// ============================================================================
//  One table component, for forty-two registers
//
//  Blueprint 002 U1, and the highest-leverage item in any of the blueprints:
//  42 list screens today share no code, so sorting, paging, choosing columns
//  and acting on several rows at once would otherwise be 42 separate builds
//  that drift apart. This is built once and adopted.
//
//  SERVER-SIDE, and that is a consequence of the hosting answer. Sorting and
//  paging in the browser means shipping every row to it first — fine for the
//  160 rows there are now, useless at the volume the blueprint talks about,
//  and on MilesWeb there is no cache to hide behind. So the database sorts and
//  the database pages, always.
//
//  Four things that make this safe rather than merely convenient:
//
//   1. **Sort columns are whitelisted, never interpolated.** A sort key that is
//      not declared in the column list is ignored and the default is used. An
//      ORDER BY built from a query string is an injection waiting to happen,
//      and it is the single most common way a table component goes wrong.
//   2. **Page size is capped.** "Show all" on a register with a million rows is
//      a way to take the server down from a URL.
//   3. **Bulk actions declare what they do and confirm it.** Anything
//      destructive says how many rows and cannot be reached by a GET.
//   4. **Column choice is per user, not per browser.** Somebody who arranges
//      their register and then signs in on a phone should find it arranged.
//
//  Accessibility is built in here rather than retrofitted, because this is the
//  one place a fix reaches 42 screens: real <th scope>, aria-sort that reflects
//  the actual state, a caption naming the register, and a checkbox column with
//  labels rather than bare boxes. The audit found ONE aria attribute across
//  122 screens — this is how that number moves.
// ============================================================================

const DT_PAGE_SIZES = [25, 50, 100, 200];
const DT_PAGE_MAX   = 200;

function dt_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS user_prefs (
        id $pk, user_id INT, pref_key VARCHAR(80) DEFAULT '', pref_value TEXT,
        updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) act_index('user_prefs', 'idx_pref_user', '(user_id, pref_key)');
}

function dt_pref_get($key, $default = '') {
    dt_migrate();
    $u = current_user();
    if (!$u) return $default;
    try {
        $v = ops_val("SELECT pref_value FROM user_prefs WHERE user_id=? AND pref_key=?", [(int)$u['id'], $key]);
        return $v === false || $v === null ? $default : (string)$v;
    } catch (Throwable $e) { return $default; }
}

function dt_pref_set($key, $value) {
    dt_migrate();
    $u = current_user();
    if (!$u) return;
    try {
        $has = ops_val("SELECT id FROM user_prefs WHERE user_id=? AND pref_key=?", [(int)$u['id'], $key]);
        if ($has) db()->prepare("UPDATE user_prefs SET pref_value=?, updated_at=? WHERE id=?")
                      ->execute([(string)$value, date('c'), (int)$has]);
        else db()->prepare("INSERT INTO user_prefs (user_id,pref_key,pref_value,updated_at) VALUES (?,?,?,?)")
                 ->execute([(int)$u['id'], $key, (string)$value, date('c')]);
    } catch (Throwable $e) { /* a preference is never worth breaking a page for */ }
}

// ---- Reading the request ----------------------------------------------------
// Everything the user asked for, validated against what the caller declared.
// Nothing from the query string reaches SQL without passing through here.
function dt_state($key, array $columns, array $opt = []) {
    $sortKey = (string)($_GET['sort'] ?? '');
    // The whitelist. An undeclared or unsortable column falls back silently —
    // a register that errors because somebody edited the URL is worse than one
    // that ignores them.
    if ($sortKey === '' || empty($columns[$sortKey]['sort'])) $sortKey = (string)($opt['default_sort'] ?? '');
    if ($sortKey !== '' && empty($columns[$sortKey]['sort'])) $sortKey = '';
    $dir = strtolower((string)($_GET['dir'] ?? ($opt['default_dir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';

    $per = (int)($_GET['per'] ?? 0);
    if (!in_array($per, DT_PAGE_SIZES, true)) $per = (int)($opt['per'] ?? 25);
    $per = max(1, min(DT_PAGE_MAX, $per));
    $page = max(1, (int)($_GET['page'] ?? 1));

    // Which columns this person wants to see. Blank means the caller's default.
    $chosen = trim((string)dt_pref_get('dt.' . $key . '.cols', ''));
    $visible = $chosen !== ''
        ? array_values(array_intersect(array_map('trim', explode(',', $chosen)), array_keys($columns)))
        : array_values(array_filter(array_keys($columns), fn($c) => empty($columns[$c]['optional'])));
    if (!$visible) $visible = array_keys($columns);

    return [
        'key' => $key, 'sort' => $sortKey, 'dir' => $dir, 'per' => $per, 'page' => $page,
        'offset' => ($page - 1) * $per, 'q' => trim((string)($_GET['q'] ?? '')),
        'visible' => $visible, 'columns' => $columns,
    ];
}

// The ORDER BY / LIMIT tail. The only place a sort expression is built, and it
// comes from the caller's own declaration, never from the request.
function dt_sql_tail(array $state, array $columns, $fallbackOrder = '') {
    $order = $state['sort'] !== '' && !empty($columns[$state['sort']]['sort'])
        ? $columns[$state['sort']]['sort'] . ' ' . $state['dir']
        : ($fallbackOrder ?: '1');
    return " ORDER BY $order LIMIT " . (int)$state['per'] . " OFFSET " . (int)$state['offset'];
}

// ---- Rendering --------------------------------------------------------------
function dt_url(array $over = []) {
    $q = array_merge($_GET, $over);
    foreach ($q as $k => $v) if ($v === null) unset($q[$k]);
    return '?' . http_build_query($q);
}

function dt_sort_link($colKey, array $col, array $state) {
    if (empty($col['sort'])) return e($col['label']);
    $active = $state['sort'] === $colKey;
    $next = ($active && $state['dir'] === 'ASC') ? 'desc' : 'asc';
    $arrow = $active ? ($state['dir'] === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a href="' . e(dt_url(['sort' => $colKey, 'dir' => $next, 'page' => 1])) . '">'
         . e($col['label']) . $arrow . '</a>';
}

// aria-sort has to say what is actually true, or a screen reader is told the
// table is sorted by something it is not.
function dt_aria_sort($colKey, array $col, array $state) {
    if (empty($col['sort'])) return '';
    if ($state['sort'] !== $colKey) return ' aria-sort="none"';
    return ' aria-sort="' . ($state['dir'] === 'ASC' ? 'ascending' : 'descending') . '"';
}

// The whole component: toolbar, table, paging. $rows are already fetched by the
// caller using dt_sql_tail(), because only the caller knows its own joins.
function dt_render(array $state, array $rows, $total, array $opt = []) {
    $cols = $state['columns'];
    $vis  = $state['visible'];
    $key  = $state['key'];
    $bulk = $opt['bulk'] ?? [];
    $caption = (string)($opt['caption'] ?? 'Register');
    $idField = (string)($opt['id_field'] ?? 'id');
    $pages = max(1, (int)ceil($total / max(1, $state['per'])));
    ob_start();
    ?>
    <div class="dt-wrap">
      <div class="dt-bar">
        <?php if (!empty($opt['search'])): ?>
          <form method="get" class="dt-search" role="search">
            <?php foreach ($_GET as $k => $v): if (in_array($k, ['q','page'], true) || is_array($v)) continue; ?>
              <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <label class="sr-only" for="dtq-<?= e($key) ?>">Search this register</label>
            <input id="dtq-<?= e($key) ?>" name="q" value="<?= e($state['q']) ?>"
                   placeholder="<?= e($opt['search_hint'] ?? 'Search…') ?>">
            <button class="btn small secondary" type="submit">Find</button>
            <?php if ($state['q'] !== ''): ?><a class="btn small secondary" href="<?= e(dt_url(['q'=>null,'page'=>1])) ?>">Clear</a><?php endif; ?>
          </form>
        <?php endif; ?>

        <div class="dt-bar-right">
          <span class="muted" style="font-size:12.5px"><?= (int)$total ?> row<?= $total==1?'':'s' ?></span>
          <details class="dt-cols">
            <summary class="btn small secondary">Columns</summary>
            <form method="post" action="/dt-columns" class="dt-colbox">
              <input type="hidden" name="key" value="<?= e($key) ?>">
              <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
              <?php foreach ($cols as $ck => $c): ?>
                <label><input type="checkbox" name="cols[]" value="<?= e($ck) ?>"
                       <?= in_array($ck, $vis, true) ? 'checked' : '' ?>> <?= e($c['label']) ?></label>
              <?php endforeach; ?>
              <button class="btn small" type="submit">Apply</button>
            </form>
          </details>
          <label class="sr-only" for="dtper-<?= e($key) ?>">Rows per page</label>
          <select id="dtper-<?= e($key) ?>" onchange="location=this.value">
            <?php foreach (DT_PAGE_SIZES as $n): ?>
              <option value="<?= e(dt_url(['per'=>$n,'page'=>1])) ?>" <?= $state['per']===$n?'selected':'' ?>><?= $n ?> / page</option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($opt['export'])): ?>
            <a class="btn small secondary" href="<?= e(dt_url(['export'=>'csv'])) ?>">⬇ CSV</a>
          <?php endif; ?>
        </div>
      </div>

      <form method="post" action="<?= e($opt['bulk_action'] ?? '') ?>" id="dtform-<?= e($key) ?>">
      <div class="dt-scroll">
        <table class="dt">
          <caption class="sr-only"><?= e($caption) ?>, <?= (int)$total ?> rows<?php
            if ($state['sort'] !== '') echo ', sorted by ' . e($cols[$state['sort']]['label'] ?? '')
                                          . ' ' . ($state['dir'] === 'ASC' ? 'ascending' : 'descending'); ?></caption>
          <thead>
            <tr>
              <?php if ($bulk): ?>
                <th scope="col" style="width:34px">
                  <label class="sr-only" for="dtall-<?= e($key) ?>">Select every row on this page</label>
                  <input type="checkbox" id="dtall-<?= e($key) ?>" data-dt-all="<?= e($key) ?>">
                </th>
              <?php endif; ?>
              <?php foreach ($vis as $ck): $c = $cols[$ck]; ?>
                <th scope="col"<?= dt_aria_sort($ck, $c, $state) ?><?= !empty($c['num']) ? ' class="num"' : '' ?>>
                  <?= dt_sort_link($ck, $c, $state) ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="<?= count($vis) + ($bulk?1:0) ?>" class="muted" style="padding:20px;text-align:center">
              <?= e($opt['empty'] ?? 'Nothing here.') ?></td></tr>
          <?php else: foreach ($rows as $r): $rid = (int)($r[$idField] ?? 0); ?>
            <tr>
              <?php if ($bulk): ?>
                <td><label class="sr-only" for="dtr-<?= e($key) ?>-<?= $rid ?>">Select this row</label>
                    <input type="checkbox" id="dtr-<?= e($key) ?>-<?= $rid ?>" name="ids[]" value="<?= $rid ?>" data-dt-row="<?= e($key) ?>"></td>
              <?php endif; ?>
              <?php foreach ($vis as $ck): $c = $cols[$ck]; ?>
                <td<?= !empty($c['num']) ? ' class="num"' : '' ?>><?php
                  echo isset($c['render']) ? ($c['render'])($r) : e((string)($r[$ck] ?? ''));
                ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($bulk && $rows): ?>
        <div class="dt-bulk">
          <span class="muted" style="font-size:12.5px">With the rows you have ticked:</span>
          <?php // A bulk action that needs one more answer — a reason, a person —
                // puts its control here, inside the same form as the buttons. ?>
          <?= (string)($opt['bulk_extra'] ?? '') ?>
          <?php foreach ($bulk as $bk => $b): ?>
            <button class="btn small<?= !empty($b['danger']) ? ' danger' : ' secondary' ?>"
                    name="bulk" value="<?= e($bk) ?>"
                    <?= !empty($b['confirm']) ? 'onclick="return dtConfirm(this,\'' . e($key) . '\',\'' . e($b['confirm']) . '\')"' : '' ?>>
              <?= e($b['label']) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </form>

      <?php if ($pages > 1): ?>
        <nav class="dt-pages" aria-label="Pages">
          <a class="btn small secondary<?= $state['page']<=1?' disabled':'' ?>"
             href="<?= e(dt_url(['page'=>max(1,$state['page']-1)])) ?>" <?= $state['page']<=1?'aria-disabled="true"':'' ?>>← Previous</a>
          <span class="muted" style="font-size:12.5px">Page <?= (int)$state['page'] ?> of <?= $pages ?></span>
          <a class="btn small secondary<?= $state['page']>=$pages?' disabled':'' ?>"
             href="<?= e(dt_url(['page'=>min($pages,$state['page']+1)])) ?>" <?= $state['page']>=$pages?'aria-disabled="true"':'' ?>>Next →</a>
        </nav>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Saving a column choice is its own route so every register shares it.
function ops_dt_columns($route, $method) {
    if ($method !== 'POST') redirect('/');
    $key  = preg_replace('/[^a-z0-9_.-]/i', '', (string)($_POST['key'] ?? ''));
    $cols = array_values(array_filter(array_map(
        fn($c) => preg_replace('/[^a-z0-9_]/i', '', (string)$c), (array)($_POST['cols'] ?? []))));
    if ($key !== '') dt_pref_set('dt.' . $key . '.cols', implode(',', $cols));
    // Only ever back to a path on this application.
    $back = (string)($_POST['back'] ?? '/');
    if ($back === '' || $back[0] !== '/' || strpos($back, '//') === 0) $back = '/';
    redirect($back);
}
