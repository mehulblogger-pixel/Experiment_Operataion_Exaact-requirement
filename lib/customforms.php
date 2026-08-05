<?php
// ============================================================================
//  No-code custom forms — build a whole new register anywhere in the app
//
//  An admin defines a form (a name + which nav group it belongs to), then adds
//  fields to it exactly the way custom fields are added to a Call: text, number,
//  date, or a dropdown/cascading-dropdown backed by ANY master list — the ones
//  that already exist AND new ones created under Masters. The form then gets its
//  own register (list + add/edit + view) and a menu entry that appears
//  automatically under the chosen module. Nothing is written in code.
//
//  It reuses the proven pieces:
//   - custom_fields (entity = the form's slug) for the field definitions,
//   - custom_save / custom_values_map / custom_display for the values,
//   - the Masters engine for every dropdown.
//  Only the form registry and a thin records table are new here.
// ============================================================================

// The nav groups a form can be filed under — the same headings the sidebar uses.
function cform_nav_groups() {
    return ['Operations', 'Sales', 'Quality & accreditation', 'Reporting', 'Money', 'Insights', 'Directory', 'My work'];
}

function cforms_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS custom_forms (
            id $pk, name VARCHAR(120) DEFAULT '', slug VARCHAR(60) DEFAULT '', nav_group VARCHAR(40) DEFAULT 'Operations',
            icon VARCHAR(8) DEFAULT '📋', help VARCHAR(300) DEFAULT '', active INT DEFAULT 1, sort_order INT DEFAULT 0,
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS custom_records (
            id $pk, form_id INT, title VARCHAR(200) DEFAULT '', created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
}

function cforms_all($activeOnly = true) {
    cforms_migrate();
    return ops_all("SELECT * FROM custom_forms " . ($activeOnly ? "WHERE active=1 " : "") . "ORDER BY nav_group, sort_order, name") ?: [];
}
function cform_by_id($id) { cforms_migrate(); return ops_one("SELECT * FROM custom_forms WHERE id=?", [(int)$id]); }
function cform_by_slug($slug) { cforms_migrate(); return ops_one("SELECT * FROM custom_forms WHERE slug=?", [(string)$slug]); }
function cform_slugs() { return array_column(cforms_all(false), 'slug'); }

// A URL/entity-safe slug from a name, unique against existing forms.
function cform_make_slug($name) {
    $base = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string)$name)), '_');
    if ($base === '') $base = 'form';
    $base = 'f_' . $base;                                  // namespaced so it never clashes with a built-in entity
    $slug = substr($base, 0, 20); $i = 2;                  // the entity column is VARCHAR(20)
    while (cform_by_slug($slug)) { $slug = substr($base, 0, 17) . '_' . $i; $i++; }
    return $slug;
}

function cform_can_manage() { return is_master() || (function_exists('can') && can('settings.manage')); }
function cform_can_use($form) {
    // Anyone who can reach the app can use a form the admin published; management
    // (design/delete) stays with admins.
    return !empty($form) && !empty($form['active']);
}

// ---- Sidebar injection ------------------------------------------------------
// Emits the menu links for the active forms filed under $group. Called from the
// layout for every nav group, so a new form appears under its module by itself.
function custom_forms_nav($group) {
    if ($group === '' || !function_exists('db')) return;
    $forms = @cforms_all(true);
    if (!$forms) return;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $curSlug = (string)($_GET['f'] ?? '');
    foreach ($forms as $f) {
        if (($f['nav_group'] ?? 'Operations') !== $group) continue;
        $on = ($path === '/cform' && $curSlug === $f['slug']) ? ' active' : '';
        echo '<a class="s-item' . $on . '" href="/cform?f=' . rawurlencode($f['slug']) . '">'
           . '<span class="s-ic">' . htmlspecialchars($f['icon'] ?: '📋', ENT_QUOTES) . '</span>'
           . '<span>' . htmlspecialchars($f['name'], ENT_QUOTES) . '</span></a>';
    }
}

// ---- Admin: the form builder (define forms) ---------------------------------
function ops_customforms($route, $method) {
    ops_require(cform_can_manage(), 'Only an administrator can build forms.');
    cforms_migrate();
    $pdo = db();

    if ($route === 'cform-def-save' && $method === 'POST') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $grp  = in_array($_POST['nav_group'] ?? '', cform_nav_groups(), true) ? $_POST['nav_group'] : 'Operations';
        $icon = substr(trim((string)($_POST['icon'] ?? '📋')), 0, 8) ?: '📋';
        $help = substr(trim((string)($_POST['help'] ?? '')), 0, 300);
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($name === '') { flash('Give the form a name.', 'error'); redirect('/cforms'); }
        if ($id) {
            $pdo->prepare("UPDATE custom_forms SET name=?, nav_group=?, icon=?, help=?, active=? WHERE id=?")
                ->execute([$name, $grp, $icon, $help, $active, $id]);
            flash('Form updated.');
            redirect('/cforms');
        }
        $slug = cform_make_slug($name);
        $pdo->prepare("INSERT INTO custom_forms (name,slug,nav_group,icon,help,active,sort_order,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$name, $slug, $grp, $icon, $help, 1, 99, function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
        flash('Form “' . $name . '” created. Now add its fields.');
        redirect('/custom-fields?entity=' . $slug);
    }

    if ($route === 'cform-def-del' && $method === 'POST') {
        $f = cform_by_id((int)($_POST['id'] ?? 0));
        if ($f) {
            // Keep the records and field definitions; just retire the form so it
            // leaves the menu. A hard delete would orphan data the business kept.
            $pdo->prepare("UPDATE custom_forms SET active=0 WHERE id=?")->execute([(int)$f['id']]);
            flash('Form “' . $f['name'] . '” retired — it leaves the menu; its records are kept.');
        }
        redirect('/cforms');
    }

    view('ops/cforms_admin', ['forms' => cforms_all(false), 'groups' => cform_nav_groups(),
        'counts' => cform_record_counts(), 'fieldCounts' => cform_field_counts()]);
    return true;
}

function cform_record_counts() {
    cforms_migrate();
    $out = [];
    foreach (ops_all("SELECT form_id, COUNT(*) c FROM custom_records GROUP BY form_id") ?: [] as $r) $out[(int)$r['form_id']] = (int)$r['c'];
    return $out;
}
function cform_field_counts() {
    $out = [];
    foreach (cforms_all(false) as $f) {
        try { $out[$f['slug']] = (int)ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity=?", [$f['slug']]); }
        catch (Throwable $e) { $out[$f['slug']] = 0; }
    }
    return $out;
}

// ---- Runtime: the form's own register (records) -----------------------------
function ops_cform_records($route, $method) {
    cforms_migrate();
    $pdo = db();
    $slug = (string)($_GET['f'] ?? $_POST['f'] ?? '');
    // For edit/view/save the form is found via the record.
    $recId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($slug === '' && $recId) { $rec0 = ops_one("SELECT form_id FROM custom_records WHERE id=?", [$recId]); if ($rec0) { $f0 = cform_by_id((int)$rec0['form_id']); $slug = $f0['slug'] ?? ''; } }
    $form = $slug !== '' ? cform_by_slug($slug) : null;
    if (!$form) { flash('That form was not found.', 'error'); redirect('/'); }
    ops_require(cform_can_use($form) || cform_can_manage(), 'That form is not available.');
    $canEdit = cform_can_manage() || (function_exists('can') && can('mod.calls.edit')) || is_master();

    if ($route === 'cform-save' && $method === 'POST') {
        ops_require($canEdit, 'You cannot add records here.');
        $title = substr(trim((string)($_POST['_title'] ?? '')), 0, 200);
        if ($recId) {
            $rec = ops_one("SELECT * FROM custom_records WHERE id=? AND form_id=?", [$recId, $form['id']]);
            if (!$rec) { http_response_code(404); view('notfound'); return true; }
            $pdo->prepare("UPDATE custom_records SET title=?, updated_at=? WHERE id=?")->execute([$title, date('c'), $recId]);
            custom_save($form['slug'], $recId, $_POST);
            flash('Saved.'); redirect('/cform-view?id=' . $recId);
        }
        $pdo->prepare("INSERT INTO custom_records (form_id,title,created_by,created_at,updated_at) VALUES (?,?,?,?,?)")
            ->execute([$form['id'], $title, function_exists('user_name') ? user_name(current_user()) : '', date('c'), date('c')]);
        $id = (int)$pdo->lastInsertId();
        custom_save($form['slug'], $id, $_POST);
        flash($form['name'] . ' entry added.');
        redirect('/cform-view?id=' . $id);
    }

    if ($route === 'cform-del' && $method === 'POST') {
        ops_require($canEdit, 'You cannot delete records here.');
        $pdo->prepare("DELETE FROM custom_records WHERE id=? AND form_id=?")->execute([$recId, $form['id']]);
        try { $pdo->prepare("DELETE FROM custom_values WHERE entity=? AND record_id=?")->execute([$form['slug'], $recId]); } catch (Throwable $e) {}
        flash('Entry deleted.'); redirect('/cform?f=' . rawurlencode($form['slug']));
    }

    if ($route === 'cform-new' || $route === 'cform-edit') {
        ops_require($canEdit, 'You cannot add records here.');
        $rec = $route === 'cform-edit' ? ops_one("SELECT * FROM custom_records WHERE id=? AND form_id=?", [$recId, $form['id']]) : null;
        if ($route === 'cform-edit' && !$rec) { http_response_code(404); view('notfound'); return true; }
        view('ops/cform_record_form', ['form' => $form, 'rec' => $rec,
            'cfvals' => $rec ? custom_values_map($form['slug'], $rec['id']) : [],
            'hasFields' => !empty(custom_fields_for($form['slug']))]);
        return true;
    }

    if ($route === 'cform-view') {
        $rec = ops_one("SELECT * FROM custom_records WHERE id=? AND form_id=?", [$recId, $form['id']]);
        if (!$rec) { http_response_code(404); view('notfound'); return true; }
        view('ops/cform_record_view', ['form' => $form, 'rec' => $rec,
            'values' => function_exists('custom_display') ? custom_display($form['slug'], $rec['id']) : [], 'canEdit' => $canEdit]);
        return true;
    }

    // /cform — the register (list)
    $q = trim((string)($_GET['q'] ?? ''));
    $where = "form_id=?"; $args = [$form['id']];
    if ($q !== '') { $where .= " AND title LIKE ?"; $args[] = '%' . $q . '%'; }
    $rows = ops_all("SELECT * FROM custom_records WHERE $where ORDER BY id DESC", $args) ?: [];
    view('ops/cform_records', ['form' => $form, 'rows' => $rows, 'q' => $q, 'canEdit' => $canEdit,
        'fields' => custom_fields_for($form['slug'])]);
    return true;
}
