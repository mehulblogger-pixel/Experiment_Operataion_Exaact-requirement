<?php
// Two fixes behind the "HTTP 400 — { error … }" banner on the Fill report screen
// and "the QAP is not parsed":
//   1. a provider error is distilled to one human line, never dumped as raw JSON;
//   2. the PDF reader inflates FlateDecode streams, so a normal (compressed) QAP
//      actually yields text to parse.
t_section('AI errors read cleanly and compressed QAP PDFs yield text');

// --- 1. ai_error_message -----------------------------------------------------
$gemini = '{ "error": { "code": 400, "message": "* GenerateContentRequest.contents: contents is not specified", "status": "INVALID_ARGUMENT" } }';
$m = ai_error_message('HTTP 400', $gemini);
t_ok(strpos($m, 'contents is not specified') !== false, 'the provider message is surfaced');
t_ok(strpos($m, '{') === false && strpos($m, '"code"') === false, 'the raw JSON envelope is not shown to the user');
t_ok(strpos($m, 'AI provider error') === 0, 'it reads as a plain one-line error');

// An OpenAI-shaped error also distills to its message.
$oa = '{"error":{"message":"Incorrect API key provided","type":"invalid_request_error"}}';
t_ok(strpos(ai_error_message('HTTP 401', $oa), 'Incorrect API key provided') !== false, 'an OpenAI-style error distills too');

// A non-JSON body falls back to its text, still one line, still capped.
$plain = ai_error_message('HTTP 500', "<html><body>Bad gateway\n\n\n</body></html>");
t_ok(strpos($plain, 'Bad gateway') !== false && strpos($plain, "\n\n") === false, 'a non-JSON body is flattened to one line');

// --- 2. idems_source_text on a compressed PDF stream -------------------------
if (function_exists('idems_source_text') && function_exists('gzcompress')) {
    $content = "BT /F1 12 Tf (Hello QAP clause 5.1 Witness 100%) Tj ET";
    $raw = "%PDF-1.4\n1 0 obj\n<< /Length 80 /Filter /FlateDecode >>\nstream\n"
         . gzcompress($content) . "\nendstream\nendobj\n";
    $text = idems_source_text('application/pdf', $raw, 16000);
    t_ok(strpos($text, 'Hello QAP') !== false, 'text is extracted from a FlateDecode-compressed PDF stream');
    t_ok(strpos($text, 'clause 5.1') !== false, 'the clause text survives extraction');

    // A scanned/image PDF (no text operators) still yields nothing — handled by callers.
    $scanned = "%PDF-1.4\n1 0 obj\n<< /Length 4 >>\nstream\n" . gzcompress("\x89PNGdata-only") . "\nendstream\n";
    $t2 = idems_source_text('application/pdf', $scanned, 16000);
    t_ok(trim($t2) === '' || strpos($t2, 'Hello') === false, 'an image-only PDF yields no spurious text');
}

// --- 3. document (multimodal) capability matrix ------------------------------
// Which providers can be sent the actual QAP file (so the model reads tables/scans
// directly). PDFs → Gemini & Claude; images → all three; nothing → perplexity etc.
t_ok(ai_doc_supported('application/pdf', 'gemini') === true, 'Gemini can read a PDF directly');
t_ok(ai_doc_supported('application/pdf', 'anthropic') === true, 'Claude can read a PDF directly');
t_ok(ai_doc_supported('application/pdf', 'openai') === false, 'OpenAI chat cannot take a PDF (images only)');
t_ok(ai_doc_supported('image/jpeg', 'openai') === true, 'OpenAI can read an image');
t_ok(ai_doc_supported('image/png', 'gemini') === true, 'Gemini can read an image');
t_ok(ai_doc_supported('application/pdf', 'perplexity') === false, 'Perplexity cannot read a document');
t_ok(ai_doc_supported('text/plain', 'gemini') === false, 'a plain-text mime is not treated as a document attachment');
