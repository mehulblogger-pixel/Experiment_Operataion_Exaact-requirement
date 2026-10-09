<?php
// ============================================================================
//  EVERY WRITE ACTION IS GUARDED — and the central gate stays central   (R-20)
// ============================================================================
//  The permission audit (docs/phase7/PERMISSION-MODEL-AUDIT.md) established two
//  facts the app's safety rests on. Neither is obvious from reading one file, and
//  both are easy to break by accident while adding a feature, so they are locked
//  here rather than left to the next audit to re-discover.
//
//    1. No route that creates, changes or deletes data reaches its handler
//       without at least one authority check.
//    2. Every route passes through ops_module_gate(), and that gate's permission
//       decision stays in ONE place. This is what makes the move from two verbs
//       to six affordable: widen the map, change one line. A second gate growing
//       up beside it would quietly undo that.
//
//  The audit tool does the parsing; this file asserts what it found.
// ============================================================================
t_section('The central module gate is still a single chokepoint');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');

t_ok(preg_match('/function ops_dispatch\([^)]*\)\s*\{\s*\n\s*ops_module_gate\(\$route\);/', $ops) === 1,
     '*** ops_dispatch() still calls ops_module_gate() before anything else');

// The gate asks can("mod.<module>.view") in exactly one place. If a second copy
// appears, the verb migration has two lines to change instead of one — and the
// one somebody forgets becomes a permission that is enforced on some routes only.
$gateChecks = preg_match_all('/!can\("mod\.\$mod\.view"\)/', $ops);
t_eq($gateChecks, 1, '*** the module permission decision lives in exactly one line');

t_section('The route-to-module map still covers the app');

// A route absent from the map falls back to ops_module_family(). Both must exist:
// the map for precision, the family fallback so a new route is never ungated.
t_ok(strpos($ops, 'static $map = [') !== false, '*** the route-to-module map is present');
t_ok(preg_match('/function ops_module_family\(/', $ops) === 1,
     '*** the family fallback that catches unmapped routes is present');
t_ok(preg_match('/if \(\$mod === null\) \$mod = ops_module_family\(\$base\);/', $ops) === 1,
     '*** an unmapped route still falls through to the family fallback');

t_section('No destructive action is reachable without an authority check');

// This needs a dominator analysis, not a text search, and the reason is worth
// recording. Three honest patterns coexist in this codebase:
//
//   (a) the guard sits in the action's own branch        (inquiry-delete)
//   (b) one guard covers every write branch below it     (ops_ncr: ncr_can_raise)
//   (c) one guard covers the whole POST block            (ops_hierarchy_screen)
//
// All three are fine. What is NOT fine is a guard that only protects a SIBLING
// branch — that looks identical to a text search but protects nothing. So this
// walks the enclosing function tracking brace depth and ignores any guard found
// inside a different route's branch. A first version of this test did a flat
// search, and a mutation deleting inquiry-delete's own guard survived it.
$DESTRUCTIVE = ['assign-cancel','audit-close','bill-delete','billable-cancel','call-delete',
  'capa-close','complaint-close','complaint-reopen','contract-delete','document-delete',
  'endorsement-delete','expense-delete','import-cancel','inquiry-delete','invoice-cancel',
  'job-close','lead-delete','ncr-close','ncr-reopen','office-delete','office-merge',
  'opportunity-delete','tenant-enable','tenant-remove','user-retire'];

$GUARDS = ['ops_require','is_master','is_admin_level','is_coordinator_level','master_access_ok',
           'is_master_of','can','hiring_admin_can','books_can','appr_can_act','partner_readable'];
$GUARD_RX = '/\b(' . implode('|', $GUARDS) . ')\s*\(|\$canEdit\b/';
// A line that opens a branch for ONE named action. A guard inside such a branch
// protects that action only, so it must not count for any other.
$BRANCH_RX = "/(if\s*\(\s*\\\$(route|do|act|action)\s*===|case\s+'[a-z0-9_-]+'\s*:|case\s+\\\$route\s*===)/";

function perm_guard_dominates(array $L, int $target, int $fnStart, string $guardRx, string $branchRx): bool {
    $depth = 0; $branchDepth = [];   // brace depths opened by a per-action branch
    for ($i = $fnStart; $i < $target; $i++) {
        $ln = $L[$i];
        $opensBranch = (bool)preg_match($branchRx, $ln);
        $hasGuard    = (bool)preg_match($guardRx, $ln);
        // A guard counts only when we are not inside another action's branch.
        if ($hasGuard && !$branchDepth && !$opensBranch) return true;
        $o = substr_count($ln, '{'); $c = substr_count($ln, '}');
        if ($opensBranch && $o > $c) $branchDepth[] = $depth + 1;
        $depth += $o - $c;
        while ($branchDepth && $depth < end($branchDepth)) array_pop($branchDepth);
    }
    return false;
}

$files = array_merge(glob(__DIR__ . '/../lib/*.php'), [__DIR__ . '/../index.php']);
$missing = [];
foreach ($DESTRUCTIVE as $act) {
    $seen = false; $guarded = false;
    foreach ($files as $f) {
        $L = file($f);
        for ($i = 0; $i < count($L); $i++) {
            if (!preg_match("/(===\s*'" . preg_quote($act, '/') . "'|case\s*'" . preg_quote($act, '/') . "')/", $L[$i])) continue;
            $seen = true;
            // (a) the action's own branch, to the end of its block
            $own = ''; $d = 0; $started = false;
            for ($j = $i; $j < min($i + 60, count($L)); $j++) {
                $own .= $L[$j];
                $d += substr_count($L[$j], '{') - substr_count($L[$j], '}');
                if (!$started && strpos($L[$j], '{') !== false) $started = true;
                if ($started && $d <= 0 && $j > $i) break;
            }
            if (preg_match($GUARD_RX, $own)) { $guarded = true; break 2; }
            // (b)/(c) a guard that dominates this line inside the same function
            $fnStart = 0;
            for ($k = $i; $k >= 0; $k--) if (preg_match('/^function\s/', $L[$k])) { $fnStart = $k; break; }
            if (perm_guard_dominates($L, $i, $fnStart, $GUARD_RX, $BRANCH_RX)) { $guarded = true; break 2; }
        }
    }
    if ($seen && !$guarded) $missing[] = $act;
}
t_eq($missing, [],
     '*** every destructive action is reached only through an authority check'
     . ($missing ? ' — UNREACHED BY ANY GUARD: ' . implode(', ', $missing) : ''));

t_section('The policy the audit read is still the policy in force');

// Pinned from reading each site during the R-20 audit. This is the assertion that
// catches a guard being loosened or deleted — the dominator test above only proves
// SOME check runs, not that the RIGHT one does. Each pair is file => exact guard.
// ncr-reopen is the one to look at twice: closing needs the dedicated ncr.close
// right, but REOPENING a closed nonconformity needs only the generic edit tick,
// because there is no "activate" right to ask for. That is R-20 in one line.
$POLICY = [
  'inquiry-delete'     => ["lib/crm.php",        "is_admin_level() || is_master()"],
  'opportunity-delete' => ["lib/opportunities.php", "is_admin_level() || is_master()"],
  'contract-delete'    => ["lib/contracts.php",  "can('crm.contract.register') || is_master()"],
  'call-delete'        => ["lib/ops.php",        "is_master() || can('ops.call.delete')"],
  'lead-delete'        => ["lib/leads.php",      "can('mod.leads.edit') || is_master()"],
  'ncr-close'          => ["lib/ncr.php",        "ncr_can_close()"],
  'ncr-reopen'         => ["lib/ncr.php",        "ncr_can_raise()"],
  'capa-close'         => ["lib/capa.php",       "capa_can_close()"],
  'assign-cancel'      => ["lib/tosrm.php",      "tosrm_can_edit()"],
];
foreach ($POLICY as $act => [$file, $guard]) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    t_ok(strpos($src, $guard) !== false,
         "*** $act is still gated by: $guard");
}
t_section('The two-verb vocabulary is still what the audit measured');

// Characterisation, not aspiration. The audit's headline finding is that a module
// yields exactly two rights and the edit tick is labelled "add / edit". When the
// six-verb model lands, THIS is the assertion that must be updated deliberately,
// in the same commit — which is the point of pinning it.
// UPDATED when the six-verb model landed, which is what pinning it was for.
// The merged "add / edit" tick is gone: a module now yields its own verbs, and
// Add and Edit are separate rights.
$acc = file_get_contents(__DIR__ . '/../lib/access.php');
t_ok(strpos($acc, '"$l — add / edit"') === false,
     '*** the merged "add / edit" tick is gone');
t_eq(PERM_VERBS, ['view','add','edit','archive','delete','approve'],
     '*** a module now yields six verbs, not two');
t_ok(perm_verb_key('leads', 'delete') === 'mod.leads.delete',
     '*** Delete is a right of its own that can be withheld from an editor');

t_section('Where the Edit tick also grants a destructive action (migration-critical)');

// The audit found that in several modules the generic "add / edit" tick IS the
// delete-or-close right. This is the fact a careless migration would break: the
// obvious rule "everyone keeps View+Add+Edit, nobody gets Delete until it is
// ticked" would silently TAKE AWAY what these people can do today.
//
// Each pair is pinned so the overlap cannot change without this test saying so.
// When the six-verb model lands, every row here must become an explicit Delete or
// Archive grant for whoever holds that edit tick — not dropped.
$EDIT_GRANTS_DESTRUCTION = [
  'delete a lead'                   => ['lib/leads.php',  "function leads_can_edit() { return can('mod.leads.edit')"],
  're-open a closed nonconformity'  => ['lib/ncr.php',    "function ncr_can_raise() { return can('mod.ncr.edit')"],
  'close an internal audit'         => ['lib/audits.php', "function aud_can_edit()  { return can('mod.audits.edit'); }"],
  'delete a bill on an open job'    => ['lib/bills.php',  "can('mod.jobs.edit')"],
];
foreach ($EDIT_GRANTS_DESTRUCTION as $what => [$file, $needle]) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    t_ok(strpos($src, $needle) !== false,
         "*** the edit tick still grants: $what  (R-20 §1 — carry this into the migration)");
}
