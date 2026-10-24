<?php
// Owner, test book ONB-001 (24 Oct 2026): "the switch is off but still I can
// generate the account from /start". Two switches on two screens decided how a
// new company gets a workspace. Now it is ONE choice, and both settings follow it.
t_section('How a new company gets a workspace — one choice');

$keep = [(string) setting_get('selfserve_signup_enabled', ''), (string) setting_get('workspace_signup_enabled', '')];
try {
    t_eq(array_keys(WORKSPACE_ENTRY_MODES), ['closed', 'apply', 'instant'], 'three choices: only you add companies · they apply and you approve · they sign up themselves');
    foreach (['closed' => [false, false], 'apply' => [false, true], 'instant' => [true, true]] as $mode => [$self, $apply]) {
        t_ok(workspace_entry_set($mode), "choosing \"$mode\" is saved");
        t_eq([selfserve_enabled(), tenant_signup_enabled()], [$self, $apply], "\"$mode\": self sign-up " . ($self ? 'on' : 'off') . ', online applications ' . ($apply ? 'on' : 'off'));
        t_eq(workspace_entry_mode(), $mode, "…and the screen reads it back as \"$mode\"");
    }
    t_ok(!workspace_entry_set('anything-else'), 'an unknown choice changes nothing');
    t_eq(workspace_entry_mode(), 'instant', '…the last real choice stands');
    // The owner's exact case: online registration off must close /start too.
    workspace_entry_set('closed');
    t_ok(!selfserve_enabled(), '"Only you add companies" closes /start as well — not just /get-started');
} finally {
    setting_set('selfserve_signup_enabled', $keep[0]); setting_set('workspace_signup_enabled', $keep[1]);
}
