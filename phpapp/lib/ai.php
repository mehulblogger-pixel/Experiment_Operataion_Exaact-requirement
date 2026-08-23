<?php
// ============================================================================
//  AI providers & models — the administrator stores API keys per provider and
//  picks which model(s) to use. Model lists auto-refresh from each provider's
//  "list models" endpoint (retired models drop off automatically); providers
//  without such an endpoint fall back to a curated known-model list.
//  Keys live in the settings table and are masked in the UI.
// ============================================================================

// Provider registry. `list_url` empty = no live model API (use fallback).
function ai_providers() {
    return [
        'openai' => [
            'label' => 'OpenAI', 'key_hint' => 'sk-…', 'auth' => 'bearer',
            'list_url' => 'https://api.openai.com/v1/models', 'parse' => 'data_id',
            'docs' => 'https://platform.openai.com/api-keys',
            'fallback' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'o3', 'o4-mini', 'gpt-4-turbo'],
        ],
        'anthropic' => [
            'label' => 'Claude (Anthropic)', 'key_hint' => 'sk-ant-…', 'auth' => 'anthropic',
            'list_url' => 'https://api.anthropic.com/v1/models', 'parse' => 'data_id',
            'docs' => 'https://console.anthropic.com/settings/keys',
            'fallback' => ['claude-opus-4-8', 'claude-sonnet-5', 'claude-haiku-4-5-20251001', 'claude-fable-5', 'claude-3-5-sonnet-20241022'],
        ],
        'gemini' => [
            'label' => 'Google Gemini', 'key_hint' => 'AIza…', 'auth' => 'query',
            'list_url' => 'https://generativelanguage.googleapis.com/v1beta/models', 'parse' => 'gemini',
            'docs' => 'https://aistudio.google.com/app/apikey',
            'fallback' => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro'],
        ],
        'perplexity' => [
            'label' => 'Perplexity', 'key_hint' => 'pplx-…', 'auth' => 'bearer',
            'list_url' => '', 'parse' => '',   // no public list endpoint
            'docs' => 'https://www.perplexity.ai/settings/api',
            'fallback' => ['sonar', 'sonar-pro', 'sonar-reasoning', 'sonar-reasoning-pro', 'sonar-deep-research'],
        ],
        'copilot' => [
            'label' => 'GitHub Copilot / Models', 'key_hint' => 'GitHub token (models:read)', 'auth' => 'bearer',
            'list_url' => 'https://models.github.ai/catalog/models', 'parse' => 'copilot',
            'docs' => 'https://github.com/settings/tokens',
            'fallback' => ['gpt-4o', 'gpt-4o-mini', 'o3-mini', 'claude-3.5-sonnet', 'llama-3.3-70b-instruct', 'phi-4'],
        ],
    ];
}
function ai_config() {
    $raw = setting_get('ai_config', '');
    $c = $raw ? json_decode($raw, true) : [];
    return is_array($c) ? $c : [];
}
function ai_provider_cfg($p) {
    $c = ai_config();
    return ($c[$p] ?? []) + ['enabled' => 0, 'key' => '', 'base' => '', 'models' => [], 'active' => [], 'refreshed' => '', 'note' => ''];
}
function ai_save_config($c) { setting_set('ai_config', json_encode($c)); }
function ai_key_mask($key) { $key = (string)$key; $n = strlen($key); return $n === 0 ? '' : '••••••••' . substr($key, -4); }
// A key input is "unchanged" if left blank or still showing the mask.
function ai_key_is_placeholder($v) { $v = trim((string)$v); return $v === '' || strpos($v, '•') !== false; }

// Whether any provider is enabled with a key (used as the AI seam for features).
function ai_enabled() {
    foreach (ai_config() as $p) if (!empty($p['enabled']) && trim((string)($p['key'] ?? '')) !== '') return true;
    return false;
}
// The first enabled provider + its first active model, for feature calls.
function ai_active() {
    foreach (ai_config() as $name => $p) {
        if (empty($p['enabled']) || trim((string)($p['key'] ?? '')) === '') continue;
        $m = $p['active'][0] ?? ($p['models'][0] ?? '');
        if ($m) return ['provider' => $name, 'model' => $m];
    }
    return null;
}

// ---- HTTP (cURL) — proxy/CA aware for hosts that use one; short timeouts ----
function ai_http_get($url, $headers, $timeout = 12) {
    if (!function_exists('curl_init')) return [null, 0, 'cURL not available on this server'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
    if ($proxy) { curl_setopt($ch, CURLOPT_PROXY, $proxy); if (is_file('/root/.ccr/ca-bundle.crt')) curl_setopt($ch, CURLOPT_CAINFO, '/root/.ccr/ca-bundle.crt'); }
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($body === false) return [null, $code, $err ?: 'request failed'];
    return [$body, $code, $code >= 400 ? "HTTP $code" : null];
}
// ---- POST (chat/completions) — used by the AI-assisted documentation features --
function ai_http_post($url, $headers, $payload, $timeout = 45) {
    if (!function_exists('curl_init')) return [null, 0, 'cURL not available on this server'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Type: application/json']),
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
    if ($proxy) { curl_setopt($ch, CURLOPT_PROXY, $proxy); if (is_file('/root/.ccr/ca-bundle.crt')) curl_setopt($ch, CURLOPT_CAINFO, '/root/.ccr/ca-bundle.crt'); }
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($body === false) return [null, $code, $err ?: 'request failed'];
    return [$body, $code, $code >= 400 ? "HTTP $code" : null];
}
// Send a prompt to the active provider/model. Returns [text, error].
// Providers differ in endpoint + payload + response shape; all are handled here so
// features can simply call ai_chat($system, $user).
// Turn a provider's error (transport error + response body) into ONE short, human
// line. Providers return their own JSON envelope ({error:{message,...}}); dumping
// that verbatim on the page is what produced the "HTTP 400 — { error … }" wall.
function ai_error_message($err, $body) {
    $msg = '';
    if ($body) {
        $d = json_decode((string)$body, true);
        if (is_array($d)) {
            $msg = $d['error']['message']            // gemini / openai
                ?? $d['error']['msg']
                ?? (is_string($d['error'] ?? null) ? $d['error'] : '')
                ?? $d['message'] ?? '';
        }
        if ($msg === '') $msg = trim(strip_tags((string)$body));
    }
    $msg = trim(preg_replace('/\s+/', ' ', (string)$msg));
    if ($msg === '') $msg = (string)$err ?: 'The AI provider returned an error.';
    return 'AI provider error: ' . substr($msg, 0, 240);
}
function ai_chat($system, $user, $maxTokens = 1200) {
    $act = ai_active();
    if (!$act) return [null, 'No AI provider is enabled. Add a key under Settings → AI providers.'];
    $p = $act['provider']; $model = $act['model'];
    $cfg = ai_provider_cfg($p); $key = trim((string)$cfg['key']);
    $reg = ai_providers()[$p] ?? null;
    if (!$reg || $key === '') return [null, 'The selected AI provider has no API key.'];
    // Never send an empty prompt. Gemini answers an empty "contents" with a raw
    // 400 ("contents is not specified") — which used to surface to the user as a
    // wall of provider JSON. When there is nothing to work on (e.g. a QAP whose
    // text could not be read), say so plainly and don't call the API at all.
    $system = trim((string)$system); $user = trim((string)$user);
    if ($user === '') return [null, 'There was nothing readable to send to the AI — the source document had no extractable text.'];
    switch ($p) {
        case 'anthropic':
            $url = 'https://api.anthropic.com/v1/messages';
            $headers = ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
            $payload = ['model'=>$model, 'max_tokens'=>$maxTokens, 'system'=>$system, 'messages'=>[['role'=>'user','content'=>$user]]];
            break;
        case 'gemini':
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($key);
            $headers = [];
            $payload = ['systemInstruction'=>['parts'=>[['text'=>$system]]], 'contents'=>[['role'=>'user','parts'=>[['text'=>$user]]]]];
            break;
        case 'perplexity':
            $url = 'https://api.perplexity.ai/chat/completions';
            $headers = ['Authorization: Bearer ' . $key];
            $payload = ['model'=>$model, 'max_tokens'=>$maxTokens, 'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]]];
            break;
        case 'copilot':
            $url = 'https://models.github.ai/inference/chat/completions';
            $headers = ['Authorization: Bearer ' . $key];
            $payload = ['model'=>$model, 'max_tokens'=>$maxTokens, 'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]]];
            break;
        default: // openai + any OpenAI-compatible base URL
            $base = rtrim($cfg['base'] ?: 'https://api.openai.com/v1', '/');
            if (substr($base, -7) === '/models') $base = substr($base, 0, -7);
            $url = $base . '/chat/completions';
            $headers = ['Authorization: Bearer ' . $key];
            $payload = ['model'=>$model, 'max_tokens'=>$maxTokens, 'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]]];
    }
    [$body, $code, $err] = ai_http_post($url, $headers, $payload);
    if ($err) return [null, ai_error_message($err, $body)];
    $d = json_decode($body, true);
    if (!is_array($d)) return [null, 'Unexpected response from the AI provider.'];
    // normalise the reply text across providers
    $text = $d['choices'][0]['message']['content']                      // openai-compatible
        ?? (is_array($d['content'] ?? null) ? implode("\n", array_map(fn($c)=>$c['text'] ?? '', $d['content'])) : null)   // anthropic
        ?? ($d['candidates'][0]['content']['parts'][0]['text'] ?? null) // gemini
        ?? null;
    if ($text === null || trim((string)$text) === '') return [null, 'The AI provider returned no usable text.'];
    return [trim((string)$text), null];
}

// Can this provider read an attached document/image of this MIME directly?
// Vision/document models read a real PDF (tables, and scans via built-in OCR) far
// better than any text we could scrape — this is what makes QAP parsing reliable.
function ai_doc_supported($mime, $provider) {
    $mime = strtolower((string)$mime);
    $isImg = strpos($mime, 'image/') === 0;
    $isPdf = strpos($mime, 'pdf') !== false;
    switch ($provider) {
        case 'gemini':    return $isImg || $isPdf;   // inline_data (images + PDF)
        case 'anthropic': return $isImg || $isPdf;   // image + document blocks
        case 'openai':    return $isImg;             // chat completions: images only
        default:          return false;              // perplexity / copilot / others
    }
}
// True when the active provider can be sent this file directly.
function ai_can_send_doc($mime) {
    $act = ai_active();
    return $act ? ai_doc_supported($mime, $act['provider']) : false;
}
// Like ai_chat(), but ATTACHES documents/images the model reads directly. $files is
// a list of ['mime'=>..., 'data'=>RAW BYTES]. Files the active provider cannot read
// are dropped; if none remain it falls back to the plain-text ai_chat().
function ai_chat_doc($system, $user, $files, $maxTokens = 1500) {
    $act = ai_active();
    if (!$act) return [null, 'No AI provider is enabled. Add a key under Settings → AI providers.'];
    $p = $act['provider']; $model = $act['model'];
    $cfg = ai_provider_cfg($p); $key = trim((string)$cfg['key']);
    if (!isset(ai_providers()[$p]) || $key === '') return [null, 'The selected AI provider has no API key.'];
    $system = trim((string)$system); $user = trim((string)$user);
    if ($user === '') $user = 'Extract the requested information from the attached document.';
    // Keep only files this provider can actually read, and cap the payload so a
    // huge upload cannot stall the request.
    $keep = [];
    foreach ((array)$files as $f) {
        if (empty($f['data']) || !ai_doc_supported($f['mime'] ?? '', $p)) continue;
        if (strlen((string)$f['data']) > 18 * 1024 * 1024) continue;   // ~18 MB raw ceiling
        $keep[] = $f;
    }
    if (!$keep) return ai_chat($system, $user, $maxTokens);   // nothing sendable → text path
    switch ($p) {
        case 'gemini':
            $parts = [];
            foreach ($keep as $f) $parts[] = ['inline_data' => ['mime_type' => $f['mime'], 'data' => base64_encode($f['data'])]];
            $parts[] = ['text' => $user];
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($key);
            $headers = [];
            $payload = ['systemInstruction' => ['parts' => [['text' => $system]]], 'contents' => [['role' => 'user', 'parts' => $parts]]];
            break;
        case 'anthropic':
            $content = [];
            foreach ($keep as $f) {
                $mime = strtolower($f['mime']);
                if (strpos($mime, 'pdf') !== false) $content[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($f['data'])]];
                else $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($f['data'])]];
            }
            $content[] = ['type' => 'text', 'text' => $user];
            $url = 'https://api.anthropic.com/v1/messages';
            $headers = ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
            $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $content]]];
            break;
        case 'openai':
            $content = [['type' => 'text', 'text' => $user]];
            foreach ($keep as $f) $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $f['mime'] . ';base64,' . base64_encode($f['data'])]];
            $base = rtrim($cfg['base'] ?: 'https://api.openai.com/v1', '/');
            if (substr($base, -7) === '/models') $base = substr($base, 0, -7);
            $url = $base . '/chat/completions';
            $headers = ['Authorization: Bearer ' . $key];
            $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $content]]];
            break;
        default:
            return ai_chat($system, $user, $maxTokens);
    }
    [$body, $code, $err] = ai_http_post($url, $headers, $payload, 90);   // documents need a longer window
    if ($err) return [null, ai_error_message($err, $body)];
    $d = json_decode($body, true);
    if (!is_array($d)) return [null, 'Unexpected response from the AI provider.'];
    $text = $d['choices'][0]['message']['content']
        ?? (is_array($d['content'] ?? null) ? implode("\n", array_map(fn($c) => $c['text'] ?? '', $d['content'])) : null)
        ?? ($d['candidates'][0]['content']['parts'][0]['text'] ?? null)
        ?? null;
    if ($text === null || trim((string)$text) === '') return [null, 'The AI provider returned no usable text.'];
    return [trim((string)$text), null];
}
function ai_parse_models($mode, $data) {
    $ids = [];
    if ($mode === 'gemini') { foreach (($data['models'] ?? []) as $m) { $n = $m['name'] ?? ''; if ($n) $ids[] = preg_replace('#^models/#', '', $n); } }
    elseif ($mode === 'copilot') { $list = $data['models'] ?? ($data['data'] ?? (is_array($data) ? $data : [])); foreach ($list as $m) { $id = is_array($m) ? ($m['id'] ?? $m['name'] ?? '') : (string)$m; if ($id) $ids[] = $id; } }
    else { foreach (($data['data'] ?? []) as $m) { $id = $m['id'] ?? ''; if ($id) $ids[] = $id; } }
    return array_values(array_unique(array_filter($ids)));
}
// Refresh a provider's model list. Returns [models[], note]. Retired models drop
// off (we return exactly what the provider lists now); note explains any fallback.
function ai_refresh_models($provider) {
    $reg = ai_providers()[$provider] ?? null;
    if (!$reg) return [[], 'unknown provider'];
    $cfg = ai_provider_cfg($provider); $key = trim((string)$cfg['key']);
    if ($reg['list_url'] === '') return [$reg['fallback'], 'This provider has no live model list — showing known models.'];
    if ($key === '') return [$reg['fallback'], 'Enter and save an API key, then refresh — showing known models.'];
    $url = ($cfg['base'] ?: $reg['list_url']); $headers = ['Accept: application/json'];
    switch ($reg['auth']) {
        case 'bearer': $headers[] = 'Authorization: Bearer ' . $key; break;
        case 'anthropic': $headers[] = 'x-api-key: ' . $key; $headers[] = 'anthropic-version: 2023-06-01'; break;
        case 'query': $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . urlencode($key); break;
    }
    [$body, $code, $err] = ai_http_get($url, $headers);
    if ($err) return [$reg['fallback'], 'Live refresh failed (' . $err . ') — showing known models.'];
    $data = json_decode($body, true);
    $ids = ai_parse_models($reg['parse'], is_array($data) ? $data : []);
    if (!$ids) return [$reg['fallback'], 'No models returned — showing known models.'];
    sort($ids);
    return [$ids, ''];
}

// ---------------------------------------------------------------------------
//  Handler
// ---------------------------------------------------------------------------
function ops_ai_settings($method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can manage AI settings.');
    $providers = ai_providers();
    $cfg = ai_config();
    if ($method === 'POST') {
        if (isset($_POST['refresh'])) {
            $p = $_POST['refresh'];
            if (isset($providers[$p])) {
                $pc = $cfg[$p] ?? [];
                $keyIn = $_POST['key'][$p] ?? '';
                if (!ai_key_is_placeholder($keyIn)) $pc['key'] = trim($keyIn);   // save a just-typed key first
                $cfg[$p] = $pc; ai_save_config($cfg);
                [$models, $note] = ai_refresh_models($p);
                $cfg[$p]['models'] = $models;
                $cfg[$p]['active'] = array_values(array_intersect($cfg[$p]['active'] ?? [], $models));  // drop retired
                $cfg[$p]['refreshed'] = date('c'); $cfg[$p]['note'] = $note;
                ai_save_config($cfg);
                flash($note ?: (count($models) . ' models refreshed for ' . $providers[$p]['label'] . '.'), $note ? 'warning' : 'success');
            }
            redirect('/ai-settings');
        }
        // full save
        foreach ($providers as $p => $reg) {
            $pc = $cfg[$p] ?? ['key' => '', 'models' => [], 'active' => []];
            $pc['enabled'] = !empty($_POST['enabled'][$p]) ? 1 : 0;
            $keyIn = $_POST['key'][$p] ?? '';
            if (!empty($_POST['clearkey'][$p])) $pc['key'] = '';
            elseif (!ai_key_is_placeholder($keyIn)) $pc['key'] = trim($keyIn);
            $pc['base'] = trim($_POST['base'][$p] ?? '');
            $avail = !empty($pc['models']) ? $pc['models'] : $reg['fallback'];
            $pc['active'] = array_values(array_intersect((array)($_POST['active'][$p] ?? []), $avail));
            $cfg[$p] = $pc;
        }
        ai_save_config($cfg);
        flash('AI provider settings saved.');
        redirect('/ai-settings');
    }
    view('ops/ai_settings', ['providers' => $providers, 'cfg' => $cfg]);
}
