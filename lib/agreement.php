<?php
// ============================================================================
//  Installation licence agreement — the click-through contract a self-hosted
//  customer must read and accept BEFORE the software will install.
//
//  It runs at the very first boot, before the database is even configured, so
//  the acceptance is recorded to a file (licence-agreement.json) exactly the
//  way the database settings are — nothing here needs a database. Once accepted
//  the gate never appears again, and the record can be read back in the app as
//  proof of who agreed, when, and to which version.
//
//  The provider's own issuing server (the one that holds the private signing
//  key) is skipped: it is not a customer install. A copy can also be exempted
//  with SKIP_AGREEMENT=1 in the environment for development.
//
//  IMPORTANT: the wording below is a thorough template covering Indian law
//  (Indian Contract Act 1872, Information Technology Act 2000, Digital Personal
//  Data Protection Act 2023, CERT-In directions 2022, Arbitration and
//  Conciliation Act 1996) and common global software-licensing terms. It is a
//  starting draft, not legal advice — have a qualified lawyer review and adapt
//  it before commercial use. Provider name / jurisdiction / contact are taken
//  from the constants below (override with env or Settings).
// ============================================================================

if (!defined('AGREEMENT_VERSION'))     define('AGREEMENT_VERSION', '1.0');
if (!defined('AGREEMENT_LICENSOR'))    define('AGREEMENT_LICENSOR', getenv('AGREEMENT_LICENSOR') ?: 'Mystical Home Decor Products (owner of the brand “MGH AI Apps”)');
if (!defined('AGREEMENT_JURISDICTION'))define('AGREEMENT_JURISDICTION', getenv('AGREEMENT_JURISDICTION') ?: 'Gandhinagar, Gujarat, India');
if (!defined('AGREEMENT_CONTACT'))     define('AGREEMENT_CONTACT', getenv('AGREEMENT_CONTACT') ?: 'legal@mghaiapps.com');

function agreement_file() { return __DIR__ . '/../licence-agreement.json'; }

// Skip on the provider's own issuing server, on a developer opt-out, and — most
// importantly — on any installation that is ALREADY established. The agreement
// is for a NEW install; an existing, set-up system (which was in use before this
// feature existed) must never be dragged behind a fresh acceptance wall. An
// install that has finished first-time setup is treated as established. Reading
// the setting quietly returns nothing before the database exists, so a genuinely
// fresh install is still gated.
function agreement_exempt() {
    $s = getenv('SKIP_AGREEMENT');
    if ($s !== false && (string)$s !== '' && (string)$s !== '0') return true;
    if (function_exists('lk_privkey') && lk_privkey() !== '') return true;
    if (function_exists('setting_get')) {
        try { if ((string)setting_get('setup_done', '') === '1') return true; } catch (Throwable $e) {}
        try { if (trim((string)setting_get('app_name', '')) !== '') return true; } catch (Throwable $e) {}
    }
    return false;
}

function agreement_accepted() {
    if (agreement_exempt()) return true;
    $f = agreement_file();
    if (!is_file($f)) return false;
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) && !empty($j['accepted_at']);
}

function agreement_data() {
    $j = json_decode((string)@file_get_contents(agreement_file()), true);
    return is_array($j) ? $j : [];
}

// The provider's identity in the contract, overridable from Settings once the
// database exists (so the in-app copy can show the customised name) but with a
// safe constant fallback for the pre-database gate.
function agreement_party($which) {
    $map = ['licensor' => AGREEMENT_LICENSOR, 'jurisdiction' => AGREEMENT_JURISDICTION, 'contact' => AGREEMENT_CONTACT];
    $keys = ['licensor' => 'agreement_licensor', 'jurisdiction' => 'agreement_jurisdiction', 'contact' => 'agreement_contact'];
    $def = $map[$which] ?? '';
    if (function_exists('setting_get')) { $v = trim((string)setting_get($keys[$which] ?? '', '')); if ($v !== '') return $v; }
    return $def;
}

// The exhaustive agreement, as an ordered list of [heading, body-html] pairs so
// both the pre-install gate and the in-app record render the same text.
function agreement_sections() {
    $L = e_txt(agreement_party('licensor'));
    $J = e_txt(agreement_party('jurisdiction'));
    $C = e_txt(agreement_party('contact'));
    return [
        ['1. Definitions',
         '<p>“<b>Licensor</b>” means ' . $L . ', the provider of the Software. “<b>Licensee</b>”, “<b>you</b>” or the '
         . '“<b>Organisation</b>” means the legal entity accepting this Agreement. “<b>Software</b>” means this operations, '
         . 'CRM and inspection/testing management application, including all modules, updates and documentation. “<b>Licence '
         . 'Key</b>” means the cryptographically signed authorisation the Licensor issues that fixes the permitted number of '
         . 'Users and the validity period. “<b>User</b>” or “<b>Seat</b>” means one individual account permitted to sign in. '
         . '“<b>Customer Data</b>” means data you enter into or generate within the Software.</p>'],

        ['2. Grant of Licence',
         '<p>Subject to your continuous compliance with this Agreement and payment of the applicable fees, the Licensor grants '
         . 'you a <b>non-exclusive, non-transferable, non-sublicensable, revocable</b> licence to install and use the Software '
         . 'on a server you control, for your internal business purposes only, limited to the number of Users and the term '
         . 'stated in your Licence Key. No rights are granted except those expressly set out here.</p>'],

        ['3. Licence Key, Seats and Activation',
         '<p>The number of Users and the validity period are governed solely by the signed Licence Key issued by the Licensor. '
         . 'You may not exceed the licensed number of active Users. A User account that has left the Organisation may be '
         . 'deactivated and its Seat reassigned; deactivation preserves all records. Expiry of the Licence Key places the '
         . 'Software into a read-only state — your data remains fully readable and exportable, and nothing is deleted.</p>'],

        ['4. Restrictions',
         '<p>You shall not, and shall not permit any person to: (a) exceed the licensed number of Users, or attempt to '
         . 'increase Seats other than through a Licence Key issued by the Licensor; (b) disable, bypass, tamper with, remove '
         . 'or interfere with the licensing, seat-counting or metering controls of the Software, or any Licence Key; '
         . '(c) reverse engineer, decompile or disassemble the Software except to the limited extent such restriction is '
         . 'prohibited by applicable law; (d) copy, resell, rent, lease, sublicense, distribute, host for third parties, or '
         . 'operate a service bureau with the Software; (e) remove or obscure any proprietary notice; or (f) use the Software '
         . 'in violation of any applicable law. Any circumvention of the licensing controls is a material breach and, where '
         . 'wilful, may constitute an offence under Sections 43 and 66 of the Information Technology Act, 2000.</p>'],

        ['5. Fees, Payment and Taxes',
         '<p>Fees are charged <b>per User</b> on a monthly or annual basis as selected, and are exclusive of Goods and Services '
         . 'Tax (GST) and any other applicable taxes, which you shall bear. Except where required by law or expressly agreed '
         . 'in writing, fees are non-refundable. The Licensor may revise pricing on renewal with prior notice.</p>'],

        ['6. Term, Renewal and Expiry',
         '<p>This Agreement takes effect on acceptance and continues for the period of your Licence Key. It renews only on the '
         . 'issue and application of a new Licence Key following payment. On expiry the Software becomes read-only until '
         . 'renewed; a grace period, where stated in the Key, delays this.</p>'],

        ['7. Termination',
         '<p>The Licensor may suspend or terminate this Agreement on written notice if you materially breach it (including any '
         . 'circumvention of licensing controls or non-payment) and fail to cure within fifteen (15) days, or immediately on '
         . 'your insolvency. On termination your right to use the Software ends; Clauses concerning intellectual property, '
         . 'confidentiality, liability, indemnity, data protection and governing law survive. You may export your Customer '
         . 'Data before termination takes effect.</p>'],

        ['8. Intellectual Property',
         '<p>The Software and all intellectual property rights in it are and remain the exclusive property of the Licensor. '
         . 'This Agreement transfers no ownership. All rights not expressly granted are reserved.</p>'],

        ['9. Customer Data and Ownership',
         '<p>As between the parties, <b>you own all Customer Data</b>. The Licensor claims no ownership of it. Because the '
         . 'Software runs on your own server in the self-hosted model, Customer Data resides on infrastructure under your '
         . 'control, and you are responsible for its backup, retention and security in your environment.</p>'],

        ['10. Data Protection and Privacy',
         '<p>Each party shall comply with applicable data-protection law, including the <b>Digital Personal Data Protection '
         . 'Act, 2023</b> and, where applicable, the EU/UK General Data Protection Regulation. In the self-hosted model you '
         . 'act as the Data Fiduciary/Controller for Customer Data and are responsible for lawful processing, notices and '
         . 'data-principal rights; the Licensor acts, if at all, only as a Data Processor for any limited data shared for '
         . 'support or licensing. You shall implement reasonable security safeguards, and shall handle any personal-data '
         . 'breach in accordance with law, including directions issued by the Indian Computer Emergency Response Team '
         . '(CERT-In) under Section 70B(6) of the Information Technology Act, 2000, including the applicable reporting '
         . 'timelines.</p>'],

        ['11. Confidentiality',
         '<p>Each party shall keep confidential the other’s non-public information (including the Software, Licence Keys, and '
         . 'pricing) and use it only to perform this Agreement, for so long as it remains confidential.</p>'],

        ['12. Warranties and Disclaimer',
         '<p>The Licensor warrants that it has the right to grant this licence. <b>Otherwise the Software is provided “as is” '
         . 'and “as available”, without warranty of any kind</b>, whether express, implied or statutory, including any implied '
         . 'warranty of merchantability, fitness for a particular purpose, or non-infringement, to the maximum extent '
         . 'permitted by law. The Licensor does not warrant uninterrupted or error-free operation, particularly where the '
         . 'Software runs on infrastructure you control.</p>'],

        ['13. Limitation of Liability',
         '<p>To the maximum extent permitted by law, neither party shall be liable for any indirect, incidental, special, '
         . 'consequential or punitive damages, or loss of profit, revenue, data or goodwill. The Licensor’s aggregate '
         . 'liability arising out of or relating to this Agreement shall not exceed the total fees paid by you for the '
         . 'Software in the twelve (12) months preceding the event giving rise to the claim. Nothing limits liability that '
         . 'cannot be limited by law.</p>'],

        ['14. Indemnification',
         '<p>You shall indemnify and hold the Licensor harmless against claims arising from your use of the Software in breach '
         . 'of this Agreement or applicable law, or from your Customer Data. The Licensor shall indemnify you against '
         . 'third-party claims that the unmodified Software, used as permitted, infringes their intellectual property rights, '
         . 'subject to the limitation of liability above.</p>'],

        ['15. Support, Updates and Audit',
         '<p>Support and updates are provided at the scope and for the period separately agreed. The Licensor may, on '
         . 'reasonable notice, verify your compliance with the licensed number of Users, including through usage the Software '
         . 'reports back to the Licensor. You agree not to interfere with such reporting.</p>'],

        ['16. Compliance, Export and Anti-Bribery',
         '<p>You shall comply with all applicable laws in your use of the Software, including export-control, sanctions and '
         . 'anti-bribery laws (including the Prevention of Corruption Act, 1988).</p>'],

        ['17. Force Majeure',
         '<p>Neither party is liable for failure or delay caused by events beyond its reasonable control.</p>'],

        ['18. Governing Law and Jurisdiction',
         '<p>This Agreement is governed by the laws of <b>India</b>. Subject to Clause 19, the courts at ' . $J . ' shall have '
         . 'exclusive jurisdiction.</p>'],

        ['19. Dispute Resolution and Arbitration',
         '<p>Any dispute shall first be attempted to be resolved amicably. Failing that, it shall be finally resolved by '
         . 'arbitration by a sole arbitrator under the <b>Arbitration and Conciliation Act, 1996</b>. The seat and venue of '
         . 'arbitration shall be ' . $J . ', and the language shall be English. The award shall be final and binding.</p>'],

        ['20. Electronic Acceptance',
         '<p>You agree that accepting this Agreement electronically — by ticking the boxes below, typing your name as '
         . 'signature and submitting — creates a valid and binding contract, and constitutes your electronic signature. This '
         . 'is recognised under Section 10A of the Information Technology Act, 2000 and the Indian Contract Act, 1872. A '
         . 'record of your acceptance (name, designation, organisation, date, time and network address) is stored as '
         . 'evidence.</p>'],

        ['21. General',
         '<p>This Agreement, with the applicable order and Licence Key, is the entire agreement between the parties and '
         . 'supersedes prior understandings. If any provision is held unenforceable, the rest remains in effect. Failure to '
         . 'enforce a right is not a waiver. You may not assign this Agreement without the Licensor’s consent; the Licensor '
         . 'may assign it to an affiliate or successor. Notices to the Licensor may be sent to ' . $C . '.</p>'],
    ];
}

// A tiny escape that works before the app's own e() is guaranteed loaded.
function e_txt($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// Record acceptance to the file (pre-database) and, if the database is up, mirror
// it into settings so the in-app record and the heartbeat can show it.
function agreement_record(array $in) {
    $rec = [
        'version'      => AGREEMENT_VERSION,
        'licensor'     => agreement_party('licensor'),
        'name'         => substr(trim((string)($in['name'] ?? '')), 0, 150),
        'designation'  => substr(trim((string)($in['designation'] ?? '')), 0, 150),
        'organisation' => substr(trim((string)($in['organisation'] ?? '')), 0, 200),
        'email'        => substr(trim((string)($in['email'] ?? '')), 0, 150),
        'accepted_at'  => date('c'),
        'ip'           => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        'user_agent'   => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        'host'         => substr((string)($_SERVER['HTTP_HOST'] ?? ''), 0, 190),
    ];
    $ok = @file_put_contents(agreement_file(), json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($ok !== false) @chmod(agreement_file(), 0640);
    if (function_exists('setting_set')) { try { setting_set('agreement_accept', json_encode($rec)); } catch (Throwable $e) {} }
    return $ok !== false;
}

// In-app record: the admin can see who accepted the Agreement, when, and read
// the full text that was accepted. Read-only proof, kept for the file.
function ops_agreement($route, $method) {
    ops_require(is_master(), 'Only the administrator can view the licence agreement.');
    $d = agreement_data();
    if (!$d && function_exists('setting_get')) {
        $s = json_decode((string)setting_get('agreement_accept', ''), true);
        if (is_array($s)) $d = $s;
    }
    view('ops/agreement', ['rec' => $d, 'sections' => agreement_sections(), 'version' => AGREEMENT_VERSION]);
}

// The pre-install gate: handle its POST, else render the standalone page. Called
// from index.php before the database-setup step. Always exits when it acts.
function agreement_gate() {
    if (agreement_accepted()) return;   // already agreed, or exempt
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST' && $path === '/agreement-accept') {
        $csrfOk = function_exists('csrf_token') ? (($_POST['_csrf'] ?? '') === ($_SESSION['csrf'] ?? "\0")) : true;
        $name = trim((string)($_POST['name'] ?? ''));
        $org  = trim((string)($_POST['organisation'] ?? ''));
        $desig= trim((string)($_POST['designation'] ?? ''));
        $all  = !empty($_POST['read']) && !empty($_POST['authority']) && !empty($_POST['agree']);
        $err  = '';
        if (!$csrfOk)                 $err = 'The form expired. Please review and submit again.';
        elseif ($name === '' || $org === '' || $desig === '') $err = 'Please fill your name, designation and organisation.';
        elseif (!$all)               $err = 'Please tick all three boxes to confirm you have read and accept the Agreement.';
        if ($err === '' && agreement_record($_POST)) { header('Location: /'); exit; }
        agreement_render($_POST, $err ?: 'The acceptance could not be saved — the app folder may not be writable.');
    }
    agreement_render();
}

// The standalone acceptance page — no database, no app layout (both are exactly
// what is not available yet on a first install). Always exits.
function agreement_render(array $vals = [], $msg = '') {
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    $token = function_exists('csrf_token') ? csrf_token() : '';
    $v = fn($k) => htmlspecialchars((string)($vals[$k] ?? ''), ENT_QUOTES);
    $L = e_txt(agreement_party('licensor'));
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Licence Agreement — ' . $L . '</title>';
    echo '<style>body{font-family:Segoe UI,Arial,sans-serif;color:#2b2b2b;background:#f4f6f9;margin:0}'
       . '.wrap{max-width:820px;margin:28px auto;padding:0 16px}.card{background:#fff;border:1px solid #e2ddd6;border-radius:12px;padding:24px}'
       . 'h1{color:#1e40af;font-size:22px;margin:0 0 4px}h3{font-size:15px;margin:16px 0 4px;color:#111}'
       . '.doc{max-height:46vh;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;padding:16px;background:#fbfbfa;font-size:13.5px;line-height:1.6}'
       . '.doc p{margin:4px 0 10px}label.ck{display:flex;gap:8px;align-items:flex-start;margin:10px 0;font-size:13.5px}'
       . 'input[type=text],input[type=email]{width:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box}'
       . '.lab{display:block;font-size:12px;color:#555;font-weight:600;margin:12px 0 4px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}'
       . '.btn{margin-top:18px;width:100%;padding:12px;background:#1e40af;color:#fff;border:0;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}'
       . '.btn:disabled{background:#9aa7c7;cursor:not-allowed}.err{background:#fdecea;border:1px solid #e6b0aa;color:#922b21;padding:10px 12px;border-radius:8px;font-size:14px;margin:12px 0}'
       . '@media(max-width:620px){.grid{grid-template-columns:1fr}}</style>';
    echo '<div class="wrap"><div class="card">';
    echo '<h1>Software Licence Agreement</h1>';
    echo '<p style="color:#555;font-size:14px;margin:0 0 12px">Before installation continues, the person authorised by your '
       . 'organisation must read this Agreement with <b>' . $L . '</b> and accept it. Installation will not proceed until it is accepted.</p>';
    if ($msg !== '') echo '<div class="err">' . e_txt($msg) . '</div>';
    echo '<div class="doc">';
    foreach (agreement_sections() as $s) { echo '<h3>' . $s[0] . '</h3>' . $s[1]; }
    echo '<p style="color:#888;font-size:12px">Version ' . e_txt(AGREEMENT_VERSION) . '. This document is a template and does '
       . 'not constitute legal advice.</p>';
    echo '</div>';
    echo '<form method="post" action="/agreement-accept">';
    echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    echo '<div class="grid">';
    echo '<div><span class="lab">Your full name (this is your signature)</span><input type="text" name="name" value="' . $v('name') . '" autofocus></div>';
    echo '<div><span class="lab">Your designation</span><input type="text" name="designation" value="' . $v('designation') . '" placeholder="e.g. Director"></div>';
    echo '<div><span class="lab">Organisation (legal name)</span><input type="text" name="organisation" value="' . $v('organisation') . '"></div>';
    echo '<div><span class="lab">Your work e-mail</span><input type="email" name="email" value="' . $v('email') . '"></div>';
    echo '</div>';
    echo '<label class="ck"><input type="checkbox" name="read" id="c1"> I have read and understood this Agreement in full.</label>';
    echo '<label class="ck"><input type="checkbox" name="authority" id="c2"> I am authorised to accept this Agreement on behalf of the Organisation named above.</label>';
    echo '<label class="ck"><input type="checkbox" name="agree" id="c3"> The Organisation agrees to be legally bound by this Agreement.</label>';
    echo '<button class="btn" id="go" type="submit" disabled>Accept and continue installation</button>';
    echo '</form>';
    echo '<script>var b=document.getElementById("go");function u(){b.disabled=!(c1.checked&&c2.checked&&c3.checked'
       . '&&document.querySelector("[name=name]").value.trim()&&document.querySelector("[name=organisation]").value.trim()'
       . '&&document.querySelector("[name=designation]").value.trim());}'
       . 'document.addEventListener("input",u);document.addEventListener("change",u);u();</script>';
    echo '</div></div>';
    exit;
}
