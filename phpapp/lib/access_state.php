<?php
// ============================================================================
//  MILESTONE 12 — WHY CAN'T I OPEN THIS?
//
//  Until now every refusal in the application went through one line:
//
//      ops_require(false, 'some sentence');   →   flash + redirect('/')
//
//  So whatever had gone wrong — the company never bought the module, the
//  licence has lapsed, the workspace is suspended, or this person simply has no
//  right to the screen — the user was bounced to the dashboard with a red toast.
//  They lost their place, were told one line with no next step, and on a POST or
//  an AJAX call the redirect was the wrong answer entirely.
//
//  This file is a PRESENTER, not a second entitlement system. It asks the
//  existing engine — module_state() from M3, licence_owner() from M2, can() from
//  the RBAC layer — and turns the answer into words a person can act on. It
//  decides nothing about access; M5–M10 remain the only thing that controls it.
//
//  The distinction §14 makes mandatory:
//
//      the company has not got it   →  "not included in your subscription"
//      you have not got it          →  "you don't have permission"
//
//  Telling somebody to ask their administrator for a module the company never
//  bought sends them on an errand that cannot succeed. Telling somebody to
//  upgrade when they simply lack a permission is just as wrong.
// ============================================================================

// The states a user-facing answer can take. The first seven mirror M3's
// module_state() exactly; NO_PERMISSION is the RBAC answer, which is a different
// question and must never be confused with the others.
const ACCESS_STATES = ['CORE', 'ENTITLED', 'NOT_ENTITLED', 'LICENCE_BLOCKED',
                       'TENANT_DISABLED', 'UNKNOWN', 'INVALID_MODULE', 'NO_PERMISSION'];

// Is this person able to act on a subscription at all? Only they should be
// offered an upgrade route; everyone else is told who to ask. This reuses the
// same gate that guards /subscription, so the offer can never point at a screen
// the person cannot open.
function access_can_subscribe() {
    return function_exists('billing_can_manage')
        ? billing_can_manage()
        : (function_exists('is_master') && is_master());
}

// Does this request want JSON rather than a page? A blocked fetch() must not be
// answered with a redirect to the dashboard (§10).
function access_wants_json() {
    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') return true;
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if ($accept !== '' && stripos($accept, 'application/json') !== false
        && stripos($accept, 'text/html') === false) return true;
    return false;
}

// ---------------------------------------------------------------------------
//  The answer, as data. Pure: it reads state and returns words. It renders
//  nothing, redirects nowhere and writes no session, so it can be tested.
//
//  $accessModule is a fine-grained access module ('hiring', 'idems', …) or a
//  product key ('hr'). $why lets a caller override the sentence where it knows
//  something more specific (an accreditation pack, for instance).
// ---------------------------------------------------------------------------
function access_state_for($accessModule, $why = null) {
    $mod     = (string) $accessModule;
    $product = function_exists('licence_owner') ? licence_owner($mod) : null;
    // A product key used directly (the M2 anomaly A1 route, and how Marketplace
    // resolves since M9).
    if ($product === null && defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$mod])) $product = $mod;

    // Never show an internal key to a user (§22). Fall back to a neutral word.
    $label = ($product !== null && defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$product]))
        ? PRODUCT_MODULES[$product][0]
        : 'This feature';

    $state = 'UNKNOWN';
    if ($product === null)                     $state = 'INVALID_MODULE';
    elseif (function_exists('module_state'))   $state = module_state($product);

    // The module is live for the company, so the subscription is not the problem.
    // Whether there is a problem at all is then a question about THIS PERSON —
    // and it is asked, not assumed: this function is also used to describe a
    // module that is perfectly available, and answering NO_PERMISSION for
    // somebody who holds the permission would be a lie.
    if (in_array($state, ['CORE', 'ENTITLED'], true)) {
        $has = !function_exists('can') || can('mod.' . $mod . '.view');
        if (!$has) $state = 'NO_PERMISSION';
    }

    $canBuy = access_can_subscribe();
    $ask    = 'Ask your workspace administrator if this should be available to you.';

    switch ($state) {
        case 'NOT_ENTITLED':
            $msg = $label . ' isn’t included in your current subscription.';
            $act = $canBuy ? ['label' => 'Review your subscription', 'route' => '/subscription']
                           : ['label' => null, 'route' => null];
            $hint = $canBuy ? 'You can add it to your plan, or contact your provider.'
                            : 'Your workspace administrator can add it to your plan.';
            break;
        case 'LICENCE_BLOCKED':
            $msg = $label . ' is unavailable because this installation’s licence is not active.';
            $act = $canBuy ? ['label' => 'Check the licence', 'route' => '/licence']
                           : ['label' => null, 'route' => null];
            $hint = $canBuy ? 'Re-check or renew the licence to restore it.'
                            : 'Your workspace administrator can restore it.';
            break;
        case 'TENANT_DISABLED':
            $msg = $label . ' has been switched off for this workspace.';
            $act = $canBuy ? ['label' => 'Review your subscription', 'route' => '/subscription']
                           : ['label' => null, 'route' => null];
            $hint = $canBuy ? 'You can switch it back on — the work already recorded in it is untouched.'
                            : 'Your workspace administrator can switch it back on. Nothing recorded in it has been lost.';
            break;
        case 'CORE':
        case 'ENTITLED':
            $msg  = $label . ' is available in this workspace.';
            $hint = '';
            $act  = ['label' => null, 'route' => null];
            break;
        case 'NO_PERMISSION':
            $msg = 'You don’t have permission to open ' . $label . '.';
            $act = ['label' => null, 'route' => null];
            $hint = $ask;
            break;
        default:   // UNKNOWN, INVALID_MODULE — say nothing about the internals.
            $msg = $label . ' isn’t available in this workspace.';
            $act = $canBuy ? ['label' => 'Review your subscription', 'route' => '/subscription']
                           : ['label' => null, 'route' => null];
            $hint = $canBuy ? 'If you believe this is wrong, contact your provider.' : $ask;
            break;
    }
    if ($why !== null && $why !== '') { $msg = $why; }

    return ['state' => $state, 'product' => $product, 'label' => $label,
            'message' => $msg, 'hint' => $hint, 'action' => $act,
            'is_permission' => $state === 'NO_PERMISSION',
            'available'     => in_array($state, ['CORE', 'ENTITLED'], true)];
}

// ---------------------------------------------------------------------------
//  Deliver the answer in the shape this request asked for, and stop.
//  A page gets a page. A fetch() gets JSON. Both get 403, and neither gets a
//  redirect to somewhere the user did not ask to be.
// ---------------------------------------------------------------------------
function access_deny($accessModule, $why = null) {
    $a = access_state_for($accessModule, $why);
    if (!headers_sent()) http_response_code(403);

    if (access_wants_json()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => false,
            'error'   => 'access',
            'reason'  => $a['is_permission'] ? 'permission' : 'subscription',
            'message' => $a['message'],
            'hint'    => $a['hint'],
            'action'  => $a['action']['route'] ? $a['action'] : null,
        ]);
        exit;
    }
    if (function_exists('view')) { view('ops/module_locked', ['a' => $a]); exit; }
    header('Content-Type: text/plain; charset=utf-8');
    echo $a['message'] . "\n" . $a['hint'] . "\n";
    exit;
}
