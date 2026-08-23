<?php
// Thread continuity — the field-level companion to the chain strip. The strip
// says whether the next record exists; this says whether the data reached it.
// Pure over the array chain_from() returns, so every case here is hand-built.
t_section('thread continuity (does the data carry?)');

$cell = function ($cont, $field, $stage) {
    foreach ($cont['rows'] as $r) if ($r['key'] === $field) return $r['cells'][$stage];
    return null;
};
$state = fn($cont, $field, $stage) => ($cell($cont, $field, $stage)['state'] ?? '??');

// A clean thread: one client, carried by id the whole way.
$clean = [
    'QUOTE'   => [['id' => 1, 'client_id' => 7, 'client_name' => 'Acme Ltd', 'contract_number' => 'C-1', 'sbu' => 'IND']],
    'CALL'    => [['id' => 2, 'client_id' => 7, 'client_name' => 'Acme Ltd', 'contract_number' => 'C-1', 'sbu' => 'IND']],
    'INVOICE' => [['id' => 3, 'partner_id' => 7, 'partner_name' => 'Acme Ltd', 'contract_number' => 'C-1', 'sbu' => 'IND']],
];
$c = chain_continuity($clean);
t_eq($state($c, 'client', 'QUOTE'),   'origin',  'the first record holding a field is its origin, not a break');
t_eq($state($c, 'client', 'CALL'),    'carried', 'the same client id on the order counts as carried');
t_eq($state($c, 'client', 'INVOICE'), 'carried', 'partner_id on the invoice is the same field under another name');
t_eq($c['breaks'], 0, 'a thread that carries everything reports no breaks');

// The hop that drops a field. This is the one that produces re-typing.
$dropped = $clean;
$dropped['CALL'][0]['contract_number'] = '';
$c = chain_continuity($dropped);
t_eq($state($c, 'contract', 'CALL'), 'missing', 'an empty column with a value upstream is a dropped field');
t_ok($c['breaks'] >= 1, 'a dropped field counts as a break');

// Two records that both hold a value and disagree — the silent one, because
// nothing looks empty on either screen.
$retyped = $clean;
$retyped['INVOICE'][0]['contract_number'] = 'C-9';
$c = chain_continuity($retyped);
t_eq($state($c, 'contract', 'INVOICE'), 'differs', 'a value that disagrees with upstream is flagged, not passed');

// A name re-spelled against the same id is still the same partner.
$respelled = $clean;
$respelled['CALL'][0]['client_name'] = 'ACME LIMITED';
t_eq($state(chain_continuity($respelled), 'client', 'CALL'), 'carried',
     'the foreign key wins over the spelling of the name');

// The job has no client column at all: it points at the order, so the field
// cannot be re-typed there. That is a good state, not a missing one.
$withJob = $clean;
$withJob['JOB'] = [['id' => 4, 'call_id' => 2, 'sbu' => 'IND']];
$c = chain_continuity($withJob);
t_eq($state($c, 'client', 'JOB'), 'link', 'a field held only by the parent link reads as inherited, not missing');

// Money is checked for presence, never for equality — a quoted total and a
// billed total are allowed to differ.
$money = [
    'QUOTE'   => [['id' => 1, 'total_amount' => 10000]],
    'INVOICE' => [['id' => 2, 'total' => 7670]],
];
$c = chain_continuity($money);
t_eq($state($c, 'money', 'INVOICE'), 'carried', 'a different invoice total is not a continuity break');
$c = chain_continuity(['QUOTE' => [['id' => 1, 'total_amount' => 10000]], 'INVOICE' => [['id' => 2, 'total' => 0]]]);
t_eq($state($c, 'money', 'INVOICE'), 'missing', 'an invoice with no value at all still is');

// Stages with no record are the strip's business, not this screen's.
t_eq($state(chain_continuity($clean), 'client', 'JOB'), 'absent',
     'a stage with no record reports absent rather than inventing a break');

// Nothing upstream and nothing here is not a fault.
t_eq($state(chain_continuity([
    'QUOTE' => [['id' => 1, 'client_id' => 7]],
    'CALL'  => [['id' => 2, 'client_id' => 7]],
]), 'po', 'CALL'), 'blank', 'a field no record ever held is blank, not broken');

// The reader must never write.
t_nothrow('the matrix reads a chain that is entirely empty without throwing',
    fn() => chain_continuity([]));

// A field with several candidate columns is a fallback chain, not a join. The
// job's reporting_frequency defaults to NOREPORT while the order's is empty, so
// concatenating the two columns made identical deliverables compare as a
// re-type on every single job.
$fallback = [
    'CALL' => [['id' => 1, 'deliverables' => 'IRN + photos', 'reporting_frequency' => '']],
    'JOB'  => [['id' => 2, 'deliverables' => 'IRN + photos', 'reporting_frequency' => 'NOREPORT']],
];
t_eq($state(chain_continuity($fallback), 'deliverables', 'JOB'), 'carried',
     'a default on a secondary column does not fake a re-type on the primary one');

// The fallback still works when the primary column is the empty one.
$secondary = [
    'CALL' => [['id' => 1, 'deliverables' => '', 'reporting_frequency' => 'WEEKLY']],
    'JOB'  => [['id' => 2, 'deliverables' => '', 'reporting_frequency' => 'WEEKLY']],
];
t_eq($state(chain_continuity($secondary), 'deliverables', 'JOB'), 'carried',
     'the second column carries the value when the first is empty on both');
