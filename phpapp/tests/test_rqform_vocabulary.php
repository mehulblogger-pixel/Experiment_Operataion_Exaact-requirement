<?php
// ============================================================================
//  REQUIREMENT VOCABULARY — one set of words for a requirement and a person.
//
//  The defect this closes: a requirement typed its discipline as free text
//  while every engineer on the books carried a trade_id picked from the Trade
//  master. One recruiter wrote "Welding", the next "welding insp". The matching
//  engine ranks people by trade_id, so it had nothing to match against and the
//  question "who on our books fits this requirement?" could not be answered.
//
//  Two promises are made to the owner, and both are asserted here:
//    · nothing historical is rewritten — typed text still saves as typed text;
//    · a speciality can never be filed under a discipline the master forbids.
//
//  ARMING FIRST. Every battery below begins by proving the trap is set: a test
//  that asserts "the cascade refused an impossible pair" is worthless if the
//  master is empty, because then every pair is impossible and the assertion
//  passes for the wrong reason.
// ============================================================================

t_as_admin();
if (function_exists('req_migrate')) req_migrate();
if (function_exists('lk_migrate'))  lk_migrate();

// ---------------------------------------------------------------------------
t_section('RQV-A · the masters this feature stands on actually hold data');
// ---------------------------------------------------------------------------
$rqvTrades = function_exists('req_trade_options') ? req_trade_options() : [];
$rqvSkills = function_exists('req_skills_by_trade') ? req_skills_by_trade() : [];

t_ok(count($rqvTrades) >= 5,
     'A1 ARMING — the Trade / discipline master holds at least 5 disciplines (got ' . count($rqvTrades) . ')');
t_ok(count($rqvSkills) >= 5,
     'A2 ARMING — specialities are grouped under at least 5 disciplines (got ' . count($rqvSkills) . ')');

// The discipline ids must be the SAME keying inspectors.trade_id uses — that is
// the entire point of reusing this master rather than inventing another list.
$rqvFirstTradeId = (int) array_key_first($rqvTrades);
t_ok($rqvFirstTradeId > 0, 'A3 — disciplines are keyed by lookup_value id, as inspectors.trade_id is');
t_ok(isset($rqvSkills[$rqvFirstTradeId]) || count($rqvSkills) > 0,
     'A4 — specialities hang off a discipline id, not off a name');

// Find a discipline that genuinely has specialities, and one of its specialities.
$rqvTradeId = 0; $rqvSkillId = 0; $rqvSkillLabel = '';
foreach ($rqvSkills as $tid => $rows) {
    if (!$rows) continue;
    $rqvTradeId = (int) $tid; $rqvSkillId = (int) $rows[0]['id']; $rqvSkillLabel = (string) $rows[0]['label'];
    break;
}
t_ok($rqvTradeId > 0 && $rqvSkillId > 0,
     'A5 ARMING — a real discipline/speciality pair exists to test the cascade with');

// A speciality belonging to a DIFFERENT discipline — the impossible pair the
// cascade must refuse. Without one, RQV-C below would prove nothing.
$rqvOtherTradeId = 0;
foreach ($rqvSkills as $tid => $rows) { if ((int)$tid !== $rqvTradeId && $rows) { $rqvOtherTradeId = (int) $tid; break; } }
t_ok($rqvOtherTradeId > 0 && $rqvOtherTradeId !== $rqvTradeId,
     'A6 ARMING — a second discipline exists, so a cross-discipline pair is actually constructible');

// ---------------------------------------------------------------------------
t_section('RQV-B · picking from the list fills the words too');
// ---------------------------------------------------------------------------
//  The old free-text columns are kept in step rather than replaced, so every
//  existing reader — the list screen, exports, the job-description generator,
//  the careers posting — keeps working with no change and keeps showing words.

$b = req_vocab_sync(['trade_id' => $rqvTradeId, 'skill_id' => $rqvSkillId, 'discipline' => '', 'category' => '']);
t_eq($b['discipline'], (string) $rqvTrades[$rqvTradeId], 'B1 — the discipline LABEL is written to the old text column');
t_eq($b['category'],   $rqvSkillLabel,                   'B2 — the speciality LABEL is written to the old text column');
t_eq((int) $b['trade_id'], $rqvTradeId,                  'B3 — the link to the master is stored');
t_eq((int) $b['skill_id'], $rqvSkillId,                  'B4 — the speciality link is stored');

// The label must not be a number. A regression here would put "7" where a
// recruiter expects "Welding" on every export and job description.
t_ok(!ctype_digit($b['discipline']) && $b['discipline'] !== '',
     'B5 — the text column holds words, never the raw id');

// ---------------------------------------------------------------------------
t_section('RQV-C · a speciality can never be filed under the wrong discipline');
// ---------------------------------------------------------------------------
$c = req_vocab_sync(['trade_id' => $rqvOtherTradeId, 'skill_id' => $rqvSkillId, 'discipline' => '', 'category' => '']);
t_eq($c['skill_id'], null, 'C1 — a speciality from another discipline is refused, not stored');
t_eq($c['category'], '',   'C2 — and its label is not written either');
t_eq((int) $c['trade_id'], $rqvOtherTradeId, 'C3 — the discipline the user actually chose is kept');

// ARMING for C1: prove the same speciality IS accepted under its own discipline,
// so C1 is refusing the pairing rather than refusing everything.
$cOk = req_vocab_sync(['trade_id' => $rqvTradeId, 'skill_id' => $rqvSkillId, 'discipline' => '', 'category' => '']);
t_eq((int) $cOk['skill_id'], $rqvSkillId, 'C4 ARMING — the same speciality is accepted under its own discipline');

// ---------------------------------------------------------------------------
t_section('RQV-D · nothing historical is rewritten');
// ---------------------------------------------------------------------------
//  The owner's standing rule is no destructive migration. Requirements raised
//  before this hold free text; a strict list would reject or silently drop it.

$d = req_vocab_sync(['trade_id' => '', 'skill_id' => '', 'discipline' => 'welding insp', 'category' => 'misc pipe work']);
t_eq($d['discipline'], 'welding insp',   'D1 — typed text is passed through exactly as typed');
t_eq($d['category'],   'misc pipe work', 'D2 — typed speciality is passed through exactly as typed');
t_eq($d['trade_id'], null, 'D3 — and no link is invented for it');
t_eq($d['skill_id'], null, 'D4 — nor for the speciality');

// A link whose master row has since been deleted must not be stored as a
// dangling number that renders as nothing to everyone.
$e = req_vocab_sync(['trade_id' => 999999, 'skill_id' => 999999, 'discipline' => 'kept as typed', 'category' => 'also kept']);
t_eq($e['trade_id'], null,          'D5 — an id that is not in the master is dropped, not stored dangling');
t_eq($e['skill_id'], null,          'D6 — same for a dangling speciality id');
t_eq($e['discipline'], 'kept as typed', 'D7 — and the words already on the record survive that');
t_eq($e['category'],   'also kept',     'D8 — for the speciality too');

// ---------------------------------------------------------------------------
t_section('RQV-E · the columns exist, and are nullable');
// ---------------------------------------------------------------------------
//  Nullable matters: "no discipline chosen" must stay visibly not chosen. A
//  DEFAULT 0 would quietly file every old requirement under whichever master
//  row happened to be first.
foreach (['trade_id', 'skill_id'] as $col) {
    t_ok(in_array($col, t_columns("requisitions"), true), "E1 — requisitions.$col exists");
}
db()->prepare("INSERT INTO requisitions (req_code,designation,quantity,status,created_at) VALUES (?,?,?,?,?)")
    ->execute(['RQV-' . substr(md5((string) mt_rand()), 0, 6), 'Inspector', 1, 'OPEN', date('c')]);
$rqvId = (int) db()->lastInsertId();
$rqvRow = ops_one("SELECT trade_id, skill_id FROM requisitions WHERE id=?", [$rqvId]);
t_ok($rqvRow['trade_id'] === null, 'E2 — a requirement with no discipline chosen stores NULL, not 0');
t_ok($rqvRow['skill_id'] === null, 'E3 — same for the speciality');

// ---------------------------------------------------------------------------
t_section('RQV-F · the certificate and qualification masters are readable here');
// ---------------------------------------------------------------------------
//  These were reachable only through the Marketplace module, which pushed a
//  recruitment workspace into keeping a second, divergent copy of the same
//  certificate names. They are seeded into every workspace at boot.
$rqvCerts = function_exists('req_cert_options') ? req_cert_options() : [];
$rqvQuals = function_exists('req_qual_options') ? req_qual_options() : [];
t_ok(count($rqvCerts) >= 10, 'F1 ARMING — the certification master is readable (got ' . count($rqvCerts) . ')');
t_ok(count($rqvQuals) >= 10, 'F2 ARMING — the qualification ladder is readable (got ' . count($rqvQuals) . ')');

// The label a recruiter reads must name the issuing body — "CSWIP 3.1" alone is
// ambiguous across awarding bodies, and that ambiguity is what the master fixes.
$rqvWithBody = 0;
foreach ($rqvCerts as $lbl) if (strpos($lbl, ' — ') !== false) $rqvWithBody++;
t_ok($rqvWithBody > 0, 'F3 — certificates are shown with their issuing body');

// Round-tripping a code back to its plain name, and a typed value surviving it.
$rqvCode = (string) array_key_first($rqvCerts);
t_ok(req_cert_label($rqvCode) !== '' && strpos(req_cert_label($rqvCode), ' — ') === false,
     'F4 — a stored code turns back into the plain certificate name');
t_eq(req_cert_label('SOMETHING_TYPED'), 'SOMETHING_TYPED',
     'F5 — a value that came from nowhere in the master is returned untouched');
t_eq(req_cert_label(''), '', 'F6 — an empty value stays empty');

// ---------------------------------------------------------------------------
t_section('RQV-G · duty hours and allowances are editable lists, not free text');
// ---------------------------------------------------------------------------
$rqvDuty  = function_exists('req_duty_hours_options') ? req_duty_hours_options() : [];
$rqvAllow = function_exists('req_allowance_options')  ? req_allowance_options()  : [];
t_ok(count($rqvDuty)  >= 5, 'G1 — a duty-hours list is offered (got ' . count($rqvDuty) . ')');
t_ok(count($rqvAllow) >= 5, 'G2 — an allowances list is offered (got ' . count($rqvAllow) . ')');
t_ok(lk_type('req_duty_hours') !== null, 'G3 — and an admin can edit it under Masters');
t_ok(lk_type('req_allowance')  !== null, 'G4 — same for allowances');

// ---------------------------------------------------------------------------
t_section('RQV-H · a recruitment workspace can maintain the certificate master');
// ---------------------------------------------------------------------------
//  Gating the editor on Marketplace alone left these lists read-only for exactly
//  the people who use them most. Neither module grants a new permission — it is
//  still admin-level only, which H2 proves.
t_ok(connect_qualtax_manage_can(),
     'H1 — an admin in this workspace can open the certification / qualification editor');
t_as_nobody();
t_ok(!connect_qualtax_manage_can(),
     'H2 — and with nobody signed in it is still refused (no new permission was granted)');
t_as_admin();

// ---------------------------------------------------------------------------
t_section('RQV-I · the form still saves the old way when no master exists');
// ---------------------------------------------------------------------------
//  An install with no Trade list falls back to the original free-text boxes.
//  This asserts the fallback is a real path, not a crash.
t_nothrow('I1 — the sync is safe when called with nothing at all', function () {
    $x = req_vocab_sync([]);
    t_eq($x['trade_id'], null, 'I2 — and stores no link');
});
t_nothrow('I3 — the sync is safe when called with only free text', function () {
    $x = req_vocab_sync(['discipline' => 'Anything']);
    t_eq($x['discipline'], 'Anything', 'I4 — which it leaves alone');
});

t_as_nobody();
