<?php
// Customer satisfaction (ISO 9001 §9.1.2, roadmap 5.4, open item M6).
t_section('customer satisfaction (M6)');

sat_migrate();

// A client to attach feedback to.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, created_at)
    VALUES (?,?,?,?)")->execute(['Girnar Metals', 'Girnar Metals Pvt Ltd', 1, date('c')]);
$clientId = (int)db()->lastInsertId();

// Aspects master seeded with sensible defaults.
t_ok(count(sat_aspects()) >= 3, 'the satisfaction-aspect master is seeded');

// A request must name a client.
[$bad, $err] = sat_request(['client_id' => 0, 'about' => 'no client']);
t_ok($bad === 0 && $err !== '', 'a request with no client is refused');

// Raise a request.
[$id, $err] = sat_request(['client_id' => $clientId, 'about' => 'TPI at Bharuch', 'report_ref' => 'TIR/26/0123']);
t_ok($id > 0 && $err === '', 'a feedback request is recorded');
$s = sat_get($id);
t_eq($s['status'], 'REQUESTED', 'a new request starts REQUESTED');
t_ok(strncmp($s['survey_code'], 'CSAT-', 5) === 0, 'a readable code is assigned');
t_eq($s['client_name'], 'Girnar Metals', 'the survey resolves its client name');

// An unanswered request does not drag the average down (average is over
// received responses only).
$sum0 = sat_summary();
t_ok($sum0['avg'] === null, 'with no responses yet, the average is empty, not zero');

// Record a strong response.
$err = sat_record($id, ['score' => 5, 'recommend' => 'Y', 'respondent' => 'QA Head',
    'aspect' => ['TIMELINESS' => 5, 'TECHNICAL' => 4], 'comments' => 'Prompt and thorough.']);
t_eq($err, '', 'a response is recorded without error');
$s = sat_get($id);
t_eq($s['status'], 'RECEIVED', 'a recorded response moves to RECEIVED');
t_eq((int)$s['score'], 5, 'the overall score is stored');
t_eq($s['recommend'], 'Y', 'the recommend flag is stored');
t_eq((int)$s['followup_needed'], 0, 'a high score raises no follow-up');
$asp = sat_aspects_of($s);
t_ok(($asp['TIMELINESS'] ?? 0) === 5 && ($asp['TECHNICAL'] ?? 0) === 4, 'per-aspect ratings are stored');

// A score is clamped to the scale, never beyond it.
[$id2] = sat_request(['client_id' => $clientId, 'about' => 'clamp test']);
sat_record($id2, ['score' => 99]);
t_ok((int)sat_get($id2)['score'] === sat_scale(), 'an out-of-range score is clamped to the scale');

// A low response raises a follow-up, and closing it needs a note.
[$id3] = sat_request(['client_id' => $clientId, 'about' => 'unhappy job']);
sat_record($id3, ['score' => 1, 'recommend' => 'N', 'comments' => 'Report was late.']);
$s3 = sat_get($id3);
t_eq((int)$s3['followup_needed'], 1, 'a low score raises a follow-up');
$err = sat_followup_close($id3, 'Called the client, reissued the report, raised NCR-26-0044.');
t_eq($err, '', 'the follow-up can be closed with a note');
t_eq((int)sat_get($id3)['followup_done'], 1, 'the follow-up is marked done');

// Declining is its own terminal state.
[$id4] = sat_request(['client_id' => $clientId, 'about' => 'no reply']);
sat_decline($id4, 'No response after two asks.');
t_eq(sat_get($id4)['status'], 'DECLINED', 'a survey can be marked declined');

// Summary metrics: response rate counts answered-of-asked; average is over
// received only.
$sum = sat_summary();
t_ok($sum['received'] === 3, 'the summary counts three responses');
t_ok($sum['avg'] !== null && $sum['avg'] > 0, 'the average score is computed over responses');
t_ok($sum['response_rate'] !== null, 'a response rate is computed');
t_ok($sum['followup'] === 0, 'the closed follow-up no longer counts as open');

// The feature switch turns the whole thing off.
setting_set('satisfaction_on', '0');
t_ok(!sat_enabled() && !sat_can_view(), 'switching the feature off closes the register');
setting_set('satisfaction_on', '1');
t_ok(sat_enabled(), 'switching it back on restores it');
