<?php
// Quotation playbook — the sales forward path (build -> approve -> send ->
// answer -> contract -> raise order). One 'do this now' at a time; a rejection
// is a blocker; lost/expired closes it. Mirrors the call/job playbooks.
t_section('quotation playbook (CRM)');

$step = function ($pb, $key) {
    foreach ($pb['steps'] as $s) if ($s['key'] === $key) return $s;
    return null;
};

// Draft: build & submit is the one to do now.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'DRAFT', 'contract_number'=>'']);
t_eq($step($pb, 'draft')['state'], 'now', 'a draft points to "build & submit"');

// Pending approval: draft done, approval is the live step.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'PENDING_APPROVAL', 'contract_number'=>'']);
t_eq($step($pb, 'draft')['state'],   'done', 'submitting marks the draft step done');
t_eq($step($pb, 'approve')['state'], 'now',  'pending approval makes "get it approved" the live step');

// Rejected: the approval step is a blocker, not just pending.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'REJECTED', 'contract_number'=>'']);
t_eq($step($pb, 'approve')['state'], 'blocked', 'a rejected quote blocks on the approval step');

// Approved: send is now.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'APPROVED', 'contract_number'=>'']);
t_eq($step($pb, 'send')['state'], 'now', 'an approved quote points to "send it"');

// Sent: the client's answer is now.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'SENT', 'contract_number'=>'']);
t_eq($step($pb, 'answer')['state'], 'now', 'a sent quote waits on the client\'s answer');

// Accepted, no contract yet: register the contract is now; order comes after.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'ACCEPTED', 'contract_number'=>'']);
t_eq($step($pb, 'answer')['state'],   'done', 'acceptance marks the answer step done');
t_eq($step($pb, 'contract')['state'], 'now',  'an accepted quote with no contract points to registering it');

// Accepted with a contract but no order raised: raise the order is now.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'ACCEPTED', 'contract_number'=>'C-2026-01']);
t_eq($step($pb, 'contract')['state'], 'done', 'a registered contract marks that step done');
t_eq($step($pb, 'order')['state'],    'now',  'with the contract registered, raising the order is next');

// Lost / expired: the whole thing reads as closed.
$pb = crm_quote_playbook(['id'=>0, 'status'=>'LOST', 'contract_number'=>'']);
t_ok($pb['lost'], 'a lost quote is flagged closed (playbook shows the closed banner, not steps)');
$pb = crm_quote_playbook(['id'=>0, 'status'=>'EXPIRED', 'contract_number'=>'']);
t_ok($pb['lost'], 'an expired quote is flagged closed too');

// Exactly one live step on any open state.
foreach (['DRAFT','PENDING_APPROVAL','APPROVED','SENT','ACCEPTED'] as $s) {
    $pb = crm_quote_playbook(['id'=>0, 'status'=>$s, 'contract_number'=>'']);
    $live = array_filter($pb['steps'], fn($x) => in_array($x['state'], ['now','blocked'], true));
    t_eq(count($live), 1, "exactly one live step in status $s");
}
