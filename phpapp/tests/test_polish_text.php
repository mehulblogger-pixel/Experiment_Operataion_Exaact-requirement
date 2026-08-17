<?php
// Voice dictation is done in the browser (free); the AI "Polish / translate"
// pass turns a rough or regional-language dictation into clean report English.
// It is gated on the company's AI setting exactly like the QAP extractor. The
// test tenant has no AI provider, so here we verify the gate and the contract.
t_section('dictation polish / translate is AI-gated');

t_ok(function_exists('idems_polish_text'), 'the polish helper exists');
t_ok(function_exists('ops_idems_polish_text'), 'the polish endpoint exists');

// With no AI provider configured, it refuses cleanly (never crashes) and points
// the user at the AI settings.
$r = idems_polish_text('body ok no dent gauge green weight ok', 'Observation');
t_ok(is_array($r) && $r['ok'] === false, 'with no AI provider it does not attempt a call');
t_ok(stripos((string)($r['error'] ?? ''), 'ai') !== false, 'and it explains that AI must be enabled');

// The Polish button is only OFFERED when AI is enabled — the browser dictation
// (the free part) is always available. This mirrors the fill screen, which only
// prints the Polish button under ai_enabled().
t_ok(function_exists('ai_enabled') && ai_enabled() === false, 'no AI in the test tenant, so the Polish button would be hidden (dictation still shown)');
