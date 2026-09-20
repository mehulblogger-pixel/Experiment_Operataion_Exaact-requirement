<?php
// ============================================================================
//  CONNECT — Organisation accounts & entitlements  (Phase B0, additive)
//
//  The hook for "one universal app for every organisation": an organisation
//  account carries an org_type, and the org_type maps to a module bundle drawn
//  from the EXISTING product packages (TPIA / STAFFING / RECRUITMENT / ENTERPRISE)
//  — so a TPIA org gets the full ops platform and a manpower agency gets the
//  marketplace, all from machinery EXAACT already has.
//
//  B0 is additive: a cx_organisations registry + read helpers + an admin screen.
//  It does NOT change any existing gate yet — wiring per-org gating for external
//  orgs is B2 (needs the topology decision + a security review). This slice makes
//  the org-account model real and provisionable. See docs/connect/04-phase-b-design.md.
// ============================================================================

/** All product-module keys (mirror of licence.php PRODUCT_MODULES order). */
function connect_all_modules() { return ['operations', 'admin', 'sales', 'reporting', 'money', 'hr']; }

/**
 * The organisation types and the package each maps to. 'package' references an
 * existing PRODUCT_PACKAGES key ('' = a portal-only audience, not a full package).
 */
function connect_org_types() {
    return [
        'TPIA'               => ['label' => 'TPIA / inspection body',      'package' => 'TPIA'],
        'MANPOWER_AGENCY'    => ['label' => 'Manpower / staffing agency',  'package' => 'STAFFING'],
        'RECRUITMENT_AGENCY' => ['label' => 'Recruitment agency',          'package' => 'RECRUITMENT'],
        'ENTERPRISE'         => ['label' => 'Enterprise (everything)',      'package' => 'ENTERPRISE'],
        'COMPANY'            => ['label' => 'Company / client (hire only)', 'package' => ''],
        'FREELANCER'         => ['label' => 'Individual freelancer',        'package' => ''],
    ];
}

/**
 * Derive a sensible primary org_type from the business capabilities a company
 * ticked, so a newcomer never has to decode a jargon radio — they just say what
 * they do and we set them up right. Additive: used only when no explicit type is
 * chosen; an explicit choice always wins (full back-compat). Falls back to COMPANY.
 */
function connect_org_type_from_caps(array $codes) {
    $codes = array_values(array_filter(array_map('strval', $codes)));
    if (!$codes) return 'COMPANY';
    $cat = function_exists('connect_cap_catalog') ? connect_cap_catalog() : [];
    $g = [];
    foreach ($codes as $c) if (isset($cat[$c])) $g[$cat[$c]['group']] = true;
    $insp   = isset($g['Inspection & Technical Services']);
    $supply = isset($g['Resource Supply']);
    $recr   = isset($g['Recruitment']);
    $proj   = isset($g['Project Services']);
    if ($supply && ($insp || $recr || $proj)) return 'ENTERPRISE';        // does work AND supplies people
    if ($supply)                              return 'MANPOWER_AGENCY';    // supplies people
    if ($recr && !$insp && !$proj)            return 'RECRUITMENT_AGENCY'; // places people
    if ($insp || $proj)                       return 'TPIA';              // runs operations/inspection
    return 'COMPANY';
}

/**
 * The module bundle an org type is entitled to. Full-package types get
 * (all modules − the package's 'off' list) plus the marketplace ('connect');
 * a company gets the marketplace only; a freelancer gets self-service ('pro').
 */
function connect_org_type_modules($orgType) {
    $types = connect_org_types();
    $t = $types[strtoupper((string)$orgType)] ?? null;
    if (!$t) return [];
    if (strtoupper((string)$orgType) === 'COMPANY')    return ['connect'];
    if (strtoupper((string)$orgType) === 'FREELANCER') return ['pro'];
    $pkg = (string)$t['package'];
    if ($pkg === '') return ['connect'];
    $off = (defined('PRODUCT_PACKAGES') && isset(PRODUCT_PACKAGES[$pkg])) ? (array)(PRODUCT_PACKAGES[$pkg]['off'] ?? []) : [];
    $mods = array_values(array_diff(connect_all_modules(), $off));
    $mods[] = 'connect'; // every organisation participates in the shared marketplace
    return $mods;
}

function connect_org_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_organisations (
        id $pk, name VARCHAR(200) DEFAULT '', org_type VARCHAR(30) DEFAULT 'COMPANY',
        package_key VARCHAR(20) DEFAULT '', party_id INT DEFAULT 0,
        status VARCHAR(16) DEFAULT 'ACTIVE', notes VARCHAR(300) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // B1 — self-service onboarding captures the applying contact. Additive.
    if (function_exists('ensure_column')) {
        ensure_column('cx_organisations', 'contact_name',  "VARCHAR(150) DEFAULT ''");
        ensure_column('cx_organisations', 'contact_email', "VARCHAR(200) DEFAULT ''");
        ensure_column('cx_organisations', 'contact_mobile', "VARCHAR(40) DEFAULT ''");
        ensure_column('cx_organisations', 'approved_by',   "VARCHAR(150) DEFAULT ''");
        ensure_column('cx_organisations', 'approved_at',   "VARCHAR(30) DEFAULT ''");
    }
}

// ===========================================================================
//  ACCESS REQUESTS — THE CONTROLLED WAY IN WHEN THE ORGANISATION IS ALREADY OURS
//  Phase 6 · Batch 3 corrective (Q26 · A3 · R4)
//
//  Someone signs up for a company we already work with. Two things must both be
//  true, and before this they could not be:
//
//    · They must not be told. "That company already exists" turns the public
//      sign-up form into a lookup service for who our customers are — and the
//      audit showed it worked 200 times out of 200, by response size and timing
//      alone, without even reading the words.
//    · They must not simply be let in. An anonymous stranger who types a
//      customer's name must never end up holding that customer's organisation.
//
//  So the request is RECORDED and nothing is granted. No account, no
//  organisation, no permission, no ownership, no merge. A human decides, and the
//  way they grant access is the invitation that already exists and already asks
//  for its own authority — this queue starts no new road into a customer's data.
//
//  What the applicant sees is exactly what a brand-new company sees. The
//  difference is carried by e-mail, to the address they gave, which only its
//  owner can read.
// ===========================================================================
function connect_access_request_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return;
    try { if (db()->inTransaction()) return; } catch (Throwable $e) {}
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_access_requests (
        id $pk, partner_id INT NULL, org_name VARCHAR(200) DEFAULT '',
        contact_name VARCHAR(150) DEFAULT '', email VARCHAR(200) DEFAULT '',
        mobile VARCHAR(40) DEFAULT '', matched_by VARCHAR(20) DEFAULT '',
        confidence VARCHAR(12) DEFAULT '', status VARCHAR(16) DEFAULT 'PENDING',
        handled_by VARCHAR(150) DEFAULT '', handled_at VARCHAR(30) DEFAULT '',
        note VARCHAR(400) DEFAULT '', ip VARCHAR(60) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    $doneAt = db_epoch();
}

/**
 * Record that somebody asked for access to an organisation we already hold.
 *
 * `$partnerId` is the SURVIVING organisation (R4) — never the retired record the
 * identifier happened to sit on, or staff would be sent to a company that no
 * longer trades. It is stored for the person who will handle the request and is
 * never returned to the applicant.
 */
function connect_access_request_add($partnerId, array $in, $matchedBy = '', $confidence = '') {
    connect_access_request_migrate();
    try {
        db()->prepare("INSERT INTO cx_access_requests
            (partner_id,org_name,contact_name,email,mobile,matched_by,confidence,status,ip,created_at)
            VALUES (?,?,?,?,?,?,?,'PENDING',?,?)")
            ->execute([(int)$partnerId ?: null,
                       substr(trim((string)($in['name'] ?? '')), 0, 200),
                       substr(trim((string)($in['contact_name'] ?? '')), 0, 150),
                       email_key($in['contact_email'] ?? ''),
                       substr(trim((string)($in['contact_mobile'] ?? '')), 0, 40),
                       substr((string)$matchedBy, 0, 20), substr((string)$confidence, 0, 12),
                       function_exists('client_ip') ? client_ip() : '', date('c')]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) { return 0; }
}

/** Access requests waiting for a person to deal with. Staff only — see the screen. */
function connect_access_requests_all($status = 'PENDING') {
    connect_access_request_migrate();
    try {
        $sql = "SELECT r.*, COALESCE(NULLIF(p.display_name,''), p.legal_name) AS partner_name, p.code AS partner_code
                  FROM cx_access_requests r LEFT JOIN business_partners p ON p.id = r.partner_id";
        $a = [];
        if ($status !== '') { $sql .= " WHERE r.status = ?"; $a[] = $status; }
        return ops_all($sql . " ORDER BY r.id DESC LIMIT 200", $a) ?: [];
    } catch (Throwable $e) { return []; }
}

/** How many are waiting — for the admin tile. */
function connect_access_requests_count() { return count(connect_access_requests_all('PENDING')); }

/**
 * A person deals with a request. This CLOSES the request and nothing else: it
 * grants no access by itself. Access is given by inviting the person through the
 * portal invitation that already exists, which asks for its own authority and
 * writes its own trail. Deliberately not automatic — Q26.
 */
function connect_access_request_close($id, $status, $note = '') {
    connect_access_request_migrate();
    $status = in_array($status, ['APPROVED', 'REJECTED'], true) ? $status : 'REJECTED';
    try {
        db()->prepare("UPDATE cx_access_requests SET status=?, handled_by=?, handled_at=?, note=?
                        WHERE id=? AND status='PENDING'")
            ->execute([$status, function_exists('user_name') ? user_name(current_user()) : '',
                       date('c'), substr(trim((string)$note), 0, 400), (int)$id]);
        return true;
    } catch (Throwable $e) { return false; }
}

/**
 * B1 — an organisation applies for itself (public onboarding). Lands as PENDING
 * for a platform admin to approve. Returns the id, or 0 on bad input.
 */
function connect_org_apply(array $in) {
    connect_org_migrate();
    $name = trim((string)($in['name'] ?? '')); $orgType = strtoupper((string)($in['org_type'] ?? ''));
    $email = strtolower(trim((string)($in['contact_email'] ?? '')));
    if ($name === '' || !isset(connect_org_types()[$orgType])) return 0;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 0;
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,status,contact_name,contact_email,contact_mobile,created_at)
                   VALUES (?,?,?, 'PENDING', ?,?,?,?)")
        ->execute([$name, $orgType, $pkg, trim((string)($in['contact_name'] ?? '')), $email, trim((string)($in['contact_mobile'] ?? '')), date('c')]);
    return (int)db()->lastInsertId();
}

/**
 * B1 (self-service) — an organisation registers ITSELF and gets a WORKING login
 * immediately (auto-approve, verify later). Creates: a business-partner party, an
 * ACTIVE cx_organisations record, and a client-portal login the person can sign
 * in with right away. Returns [ok, msg, ['email'=>.., 'login_url'=>..]].
 *
 *   COMPANY / TPIA / ENTERPRISE  → party is a client (can hire)
 *   MANPOWER_AGENCY / RECRUITMENT_AGENCY → party is a subcontractor (supplies people)
 * Every type gets a marketplace portal login (post work, review vouchers); the
 * full operations workspace for a TPIA/enterprise stays a controlled step.
 */
//  Is everything this route writes to actually there? Asked WITHOUT touching the
//  schema, so it is safe to ask from anywhere, including inside somebody else's
//  transaction. `$caps` widens it to the capability table, which only a sign-up
//  that ticked something needs.
function connect_org_schema_ready($caps = false) {
    $need = ['business_partners', 'cx_organisations', 'client_users'];
    if ($caps) $need[] = 'cx_org_capabilities';
    foreach ($need as $t) {
        try { ops_val("SELECT COUNT(*) FROM $t"); } catch (Throwable $e) { return false; }
    }
    return true;
}

//  SCHEMA PREPARATION THAT CANNOT DAMAGE A TRANSACTION IT DID NOT OPEN.
//
//  Taking the schema steps before this route's own transaction fixed half the
//  problem. The other half: when a CALLER already has a transaction open, those
//  same steps run inside IT — and MariaDB commits implicitly on the first DDL.
//  The caller's transaction would end here, unannounced, and this function would
//  then believe it owned the one it had just destroyed.
//
//  So the rule is simple and absolute: **no schema work inside a transaction we
//  did not open.** When we own the connection we prepare normally. When we do
//  not, we prepare NOTHING and merely report whether what we need is already
//  there — which it is in every normal run, because boot prepares it.
//
//  Returns '' when the route may proceed, or the reason it may not.
function connect_org_prepare_schema($caps = false) {
    static $warmedAt = -1;
    $borrowed = false;
    try { $borrowed = db()->inTransaction(); } catch (Throwable $e) { $borrowed = false; }

    if (!$borrowed) {
        //  We own the connection, so this is the place to do it — and we prepare
        //  EVERYTHING this route can reach, not only the tables it writes
        //  itself. The audit trail and the capability table are reached through
        //  other people's functions, each with its own run-once marker that
        //  would otherwise fire later, at the worst possible moment.
        connect_org_migrate();
        if (function_exists('portal_migrate'))      portal_migrate();
        if (function_exists('connect_cap_migrate')) connect_cap_migrate();
        if (function_exists('act_migrate'))         act_migrate();
        $warmedAt = db_epoch();
        return '';
    }

    //  BORROWED — not one statement of DDL from here, no-op kind included.
    //
    //  The two migrations this route reaches through other people's functions
    //  (the audit trail, the capability table) carry the same rule at their own
    //  door, so nothing downstream can fire DDL inside this transaction either.
    //  What is left to establish is simply whether what we need is already
    //  there. If it is, proceed and write not one schema statement. If it is
    //  not, refuse — before a single row is written, so the caller's
    //  transaction is exactly as they left it and the decision stays theirs.
    if (connect_org_schema_ready($caps)) return '';
    return 'SCHEMA_NOT_READY';
}

function connect_org_register(array $in) {
    $name    = trim((string)($in['name'] ?? ''));
    $orgType = strtoupper((string)($in['org_type'] ?? ''));
    $email   = email_key($in['contact_email'] ?? '');
    $person  = trim((string)($in['contact_name'] ?? '')) ?: $name;
    $pass    = (string)($in['password'] ?? '');
    // Capabilities lead the flow now: a company simply ticks what it does, and we
    // set up the right primary type from that. An explicit org_type still wins (so
    // deep links and the old form keep working); otherwise we derive it, and a bare
    // sign-up with nothing ticked lands as a plain hire-only COMPANY.
    $capsIn = array_values(array_filter(array_map('strval', (array)($in['caps'] ?? []))));
    if (!isset(connect_org_types()[$orgType]) || $orgType === 'FREELANCER') $orgType = connect_org_type_from_caps($capsIn);
    if ($name === '') return [false, 'Please give your organisation a name.', null];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Enter a valid work e-mail.', null];
    if (strlen($pass) < 8) return [false, 'Choose a password of at least 8 characters.', null];
    //  These three refusals are about the FORM THE PERSON JUST FILLED IN. They
    //  describe their own typing back to them and reveal nothing about anybody
    //  else, so they stay — a sign-up that cannot say "that e-mail is not valid"
    //  is a worse product for no security gain.
    $isAgency = in_array($orgType, ['MANPOWER_AGENCY', 'RECRUITMENT_AGENCY'], true);

    //  A3 — hash the password HERE, before anything is decided.
    //
    //  Hashing is far and away the most expensive thing this route does, and it
    //  used to happen only on the path that created an account. That left a
    //  21ms answer for "this company is already ours" beside a 284ms answer for
    //  "it is not" — a clock is all anybody needed to read our customer list.
    //  Paying the same cost before the question is asked is not padding; it is
    //  simply doing the work in an order that does not answer a question we
    //  refuse to answer.
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    //  Prepare the schema — or, inside somebody else's transaction, prove it is
    //  already prepared and touch nothing. Before the first query, because the
    //  very next line reads a table this route owns.
    //
    //  Refusing here is safe for a borrowed transaction in a way that throwing
    //  would not be: NOTHING has been written yet, so the caller's transaction
    //  is exactly as it was and the caller decides what to do next. The re-throw
    //  contract below governs a failure AFTER the writes begin, and is unchanged.
    if (connect_org_prepare_schema($capsIn !== []) !== '')
        return [false, 'We could not complete your registration just now. Nothing has been saved. '
                     . 'Please try again in a moment.', null];

    // A login is one per e-mail across the client-portal world.
    //  The answer is the same one everybody gets. Telling a stranger "that
    //  address is already registered" lets them test addresses one by one to
    //  find out who banks with us; the person who actually owns the address is
    //  told, by e-mail, which only they can read.
    if ((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(TRIM(email))=? AND is_active=1", [$email]) > 0) {
        connect_join_mail($email, 'account-exists', $person, $name);
        portal_security_event('JOIN_BLOCKED', 'Public sign-up for an address that already has an account.');
        return connect_join_neutral($email, $isAgency);
    }

    // ------------------------------------------------------------------------
    //  PHASE 6 · BATCH 3 — DUPLICATE ORGANISATION PROTECTION (F1 · Q20 · Q21).
    //
    //  This route is PUBLIC and unauthenticated, and it used to create a
    //  business partner and a marketplace organisation with no check at all: a
    //  company that is already a customer could be registered again by anyone
    //  who knew its name. The detector the business already owns was simply
    //  never called from here.
    //
    //  Owner decision Q21: an anonymous visitor must not take ownership of an
    //  existing organisation, AND must not be told that it exists. So the reply
    //  is NEUTRAL — the same sentence whatever matched — and it names no
    //  organisation, no code, no identifier and no account.
    //
    //  EXACT (GSTIN / PAN / TAN, or the same legal name) → refuse, create nothing.
    //  POSSIBLE                                          → see below.
    //  NONE                                              → register normally.
    // ------------------------------------------------------------------------
    if (function_exists('find_duplicate_partner')) {
        $hit = find_duplicate_partner($name, (string)($in['gstin'] ?? ''), (string)($in['pan'] ?? ''), (string)($in['tan'] ?? ''), 0);
        if ($hit) {
            //  A NAME-only match is POSSIBLE, not proof — but on a public route
            //  there is nobody to ask, and letting it through is how a shadow
            //  organisation lands beside a real customer. Both confidences stop
            //  here; the difference is that an EXACT match could never be
            //  anything else, while a POSSIBLE one is a genuinely distinct
            //  company often enough that the message must invite contact rather
            //  than accuse. Staff paths treat the two differently — see
            //  partner_find_or_problem().
            //  A4 · Q27 — where this is written down.
            //
            //  It used to go to the matched organisation's ACTIVITY trail: the
            //  feed their own dashboard shows, which displays the latest eight
            //  entries. Ten anonymous posts therefore pushed every real entry
            //  off a paying customer's screen. A stranger with no account could
            //  decide what a customer saw about their own business.
            //
            //  The evidence is worth keeping and the place was wrong. It goes to
            //  the portal access trail, which staff read and no customer screen
            //  does. The organisation's id is recorded for the person who will
            //  handle it, and is never sent back to whoever typed the form.
            //
            //  R4 — the request names the SURVIVING organisation. `row` has
            //  already been resolved through any merge, so nobody is sent to a
            //  record that was retired years ago.
            $liveId = (int)($hit['row']['id'] ?? 0);
            connect_access_request_add($liveId, $in, (string)$hit['by'], (string)($hit['confidence'] ?? ''));
            portal_security_event('JOIN_BLOCKED',
                'Public sign-up matched an existing organisation — ' . (string)($hit['confidence'] ?? '')
                . ' by ' . (string)$hit['by'] . '. Access request raised; nothing was created.', $liveId);
            connect_join_mail($email, 'access-requested', $person, $name);
            //  The same answer a brand-new company gets. No name, no code, no
            //  identifier, no status, no confidence, no id — and, because the
            //  caller returns TRUE, the same page of the same size as a success.
            return connect_join_neutral($email, $isAgency);
        }
    }

    $now = date('c');

    // ------------------------------------------------------------------------
    //  ONE TRANSACTION (F3). Three tables were written unguarded, so a failure
    //  after the first left an organisation nobody could sign in to and nothing
    //  reported it. Batch 2's contract applies: if a caller already opened a
    //  transaction we JOIN it and may neither commit nor roll it back, so a
    //  failure is re-thrown and the caller unwinds.
    // ------------------------------------------------------------------------
    $own = true;
    try { $own = !db()->inTransaction(); } catch (Throwable $e) { $own = true; }
    $tx = false;
    if ($own) { try { $tx = (bool)db()->beginTransaction(); } catch (Throwable $e) { $tx = false; } }
    $partyId = 0; $acctTaken = false;
    try {
        // 1) Party
        //  An identifier the company GAVE US is kept. It was being read for the
        //  duplicate check and then thrown away, so the organisation that had
        //  just proved who it was could be duplicated afterwards by anyone
        //  typing a slightly different name — the one check that cannot be
        //  argued with was left with nothing to match against.
        $pCols = ['legal_name', 'display_name', 'is_client', 'is_vendor', 'is_subcontractor', 'status', 'created_at'];
        $pVals = [$name, $name, $isAgency ? 0 : 1, 0, $isAgency ? 1 : 0, 'ACTIVE', $now];
        foreach (['gstin' => clean_gstin((string)($in['gstin'] ?? '')),
                  'pan'   => strtoupper(trim((string)($in['pan'] ?? '')))] as $c => $v) {
            if ($v !== '' && (!function_exists('column_exists') || column_exists('business_partners', $c))) {
                $pCols[] = $c; $pVals[] = $v;
            }
        }
        db()->prepare("INSERT INTO business_partners (" . implode(',', $pCols) . ") VALUES ("
                      . implode(',', array_fill(0, count($pCols), '?')) . ")")->execute($pVals);
        $partyId = (int)db()->lastInsertId();
        if ($partyId <= 0) throw new RuntimeException('the organisation could not be created');

        // 2) ACTIVE organisation (auto-approved — verify later)
        $pkg = connect_org_types()[$orgType]['package'];
        db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,contact_name,contact_email,contact_mobile,approved_by,approved_at,created_at)
                       VALUES (?,?,?,?, 'ACTIVE', ?,?,?, 'self-service', ?, ?)")
            ->execute([$name, $orgType, $pkg, $partyId, $person, $email, trim((string)($in['contact_mobile'] ?? '')), $now, $now]);

        // 3) A working client-portal login (blank perms = full marketplace access).
        //    The database derives the uniqueness key itself, so this race is
        //    settled there; the check at the top is only the friendly message.
        //  The account conflict is translated HERE, at the write that can cause
        //  it — not in the catch below. Asking a generic "was that a duplicate?"
        //  of any failure in this whole transaction tells somebody whose
        //  ORGANISATION collided that their e-mail is already registered, which
        //  is simply untrue and sends them to a sign-in page that will not have
        //  them. Every unique index reports the same SQLSTATE, so only the
        //  statement itself knows which rule was hit.
        try {
            db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at)
                           VALUES (?,?,?,?,1,0,'', 'self-service', ?)")
                ->execute([$partyId, $email, $person, $hash, $now]);
        } catch (Throwable $e) {
            if (function_exists('portal_acct_is_duplicate') && portal_acct_is_duplicate($e)) $acctTaken = true;
            throw $e;
        }

        // 4) Multi-capability onboarding — persist the business capabilities the
        //    company ticked (additive; the single org_type above stays the primary
        //    audience). Optional: none ticked → behaves exactly as before.
        if (function_exists('connect_org_cap_bulk_set')) {
            $caps = array_values(array_filter(array_map('strval', (array)($in['caps'] ?? []))));
            if ($caps) connect_org_cap_bulk_set($partyId, $caps, 'self-service');
        }
        if ($own && $tx) db()->commit();
    } catch (Throwable $e) {
        if ($tx) { try { db()->rollBack(); } catch (Throwable $e2) {} }
        if (!$own) throw $e;                       // the caller owns the unwind
        //  A SYSTEM failure is not a duplicate. Saying "your organisation may
        //  already work with us" to somebody whose registration hit a database
        //  error sends them down a claim path that does not apply, and hides a
        //  fault nobody then investigates. Different fact, different sentence.
        //  Losing the account race is the same fact as the check at the top, so
        //  it gets the same neutral answer. A genuine system failure is a
        //  different fact and says so — it happens to new and existing
        //  organisations alike, so it tells a stranger nothing.
        if ($acctTaken) {
            connect_join_mail($email, 'account-exists', $person, $name);
            return connect_join_neutral($email, $isAgency);
        }
        return [false, 'We could not complete your registration just now. Nothing has been saved. '
                     . 'Please try again in a moment.', null];
    }

    //  AUDIT — outside the transaction. A failed observation is never a failed
    //  transaction (invariant I41): the registration stands whatever this does.
    if (function_exists('act_log')) {
        try { act_log('PARTNER', $partyId, 'SYSTEM', 'Organisation registered through public sign-up (' . $orgType . ')'); }
        catch (Throwable $e) {}
    }

    connect_join_mail($email, 'welcome', $person, $name);
    return connect_join_neutral($email, $isAgency);
}

//  THE ONE ANSWER THE PUBLIC FORM GIVES.
//
//  Every outcome a stranger can steer — account created, address already in use,
//  organisation already ours — ends here, with the same words, the same fields
//  and the same page. What differs is the e-mail, which goes to the address they
//  typed and which only its owner can read. `login_url` is derived from what THEY
//  asked to be, never from anything we know, so it carries nothing back.
const CONNECT_JOIN_NEUTRAL_MSG = 'Thanks — we have your details.';

function connect_join_neutral($email, $isAgency) {
    return [true, CONNECT_JOIN_NEUTRAL_MSG,
            ['email' => $email,
             'login_url' => $isAgency ? '/portal/login' : '/portal/login?for=hire',
             'is_agency' => $isAgency]];
}

/** The part of the answer that only the address's owner gets to read. */
function connect_join_mail($email, $kind, $person, $orgName) {
    if (!function_exists('ops_mail')) return;
    $app  = function_exists('app_name') ? app_name() : 'the portal';
    $hi   = 'Hello ' . ($person !== '' ? $person : 'there') . ",\n\n";
    $bye  = "\n\nThank you,\n" . $app;
    if ($kind === 'welcome') {
        $sub = 'Your ' . $app . ' account is ready';
        $msg = $hi . 'Your account for ' . $orgName . " is set up and you can sign in now with the e-mail address "
             . "and password you chose." . $bye;
    } elseif ($kind === 'access-requested') {
        $sub = 'About your ' . $app . ' sign-up';
        $msg = $hi . 'Thanks for signing up. We could not finish setting you up online, so we have passed your '
             . "request to the team who look after this account. Somebody will be in touch.\n\n"
             . "If you were expecting to be added by a colleague, ask them to send you an invitation." . $bye;
    } else {
        $sub = 'About your ' . $app . ' sign-up';
        $msg = $hi . 'Somebody just tried to sign up using this e-mail address, which already has an account. '
             . "If that was you, simply sign in — or use \"forgotten password\" if you need a new one.\n\n"
             . "If it was not you, you do not need to do anything; nothing has changed." . $bye;
    }
    try { ops_mail($email, $sub, $msg, '', 'join'); } catch (Throwable $e) {}
}

/** A platform admin approves a pending organisation → ACTIVE. */
function connect_org_approve($id) {
    connect_org_migrate();
    db()->prepare("UPDATE cx_organisations SET status='ACTIVE', approved_by=?, approved_at=? WHERE id=? AND status='PENDING'")
        ->execute([function_exists('user_name') ? user_name(current_user()) : '', date('c'), (int)$id]);
    return true;
}

function connect_org_pending_count() {
    connect_org_migrate();
    try { return (int)ops_val("SELECT COUNT(*) FROM cx_organisations WHERE status='PENDING'"); } catch (Throwable $e) { return 0; }
}

/** The ONE public front door (dispatched before require_login). One page where a
 *  professional, a company or an agency creates an account or signs in. Always exits. */
function connect_front_route($method) {
    // Public marketplace door — only where the Connect add-on is entitled (both cloud
    // and a licence that bought it). A private operations copy without it has no door.
    if (function_exists('install_marketplace_enabled') ? !install_marketplace_enabled()
        : (function_exists('connect_enabled') && !connect_enabled())) {
        if (function_exists('current_user') && !current_user()) redirect('/login');
        http_response_code(404); echo 'Not available.'; exit;
    }
    require __DIR__ . '/../views/ops/connect_front.php';
    exit;
}

/** The public onboarding page (dispatched before require_login). Always exits. */
function connect_org_join_route($method) {
    if (function_exists('install_marketplace_enabled') ? !install_marketplace_enabled()
        : (function_exists('connect_enabled') && !connect_enabled())) {
        if (function_exists('current_user') && !current_user()) redirect('/login');
        http_response_code(404); echo 'Not available.'; exit;
    }
    connect_org_migrate();
    $done = false; $err = ''; $acct = null;
    if ($method === 'POST') {
        [$ok, $msg, $acct] = connect_org_register($_POST);
        if ($ok) $done = true; else $err = $msg;
    }
    $GLOBALS['__join_done'] = $done; $GLOBALS['__join_err'] = $err; $GLOBALS['__join_acct'] = $acct;
    $GLOBALS['__join_types'] = connect_org_types();
    // Multi-capability onboarding: a company declares the full mix of what it does.
    $GLOBALS['__join_cap_catalog'] = function_exists('connect_cap_catalog') ? connect_cap_catalog() : [];
    $GLOBALS['__join_cap_groups']  = function_exists('connect_cap_groups')  ? connect_cap_groups()  : [];
    $GLOBALS['__join_caps_posted'] = array_map('strval', (array)($_POST['caps'] ?? []));
    require __DIR__ . '/../views/ops/connect_join.php';
    exit;
}

/** Register an organisation. Returns its id, or 0 on bad input. */
function connect_org_add($name, $orgType, array $in = []) {
    connect_org_migrate();
    $name = trim((string)$name); $orgType = strtoupper((string)$orgType);
    if ($name === '' || !isset(connect_org_types()[$orgType])) return 0;
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,notes,created_by,created_at)
                   VALUES (?,?,?,?, 'ACTIVE', ?, ?, ?)")
        ->execute([$name, $orgType, $pkg, (int)($in['party_id'] ?? 0), trim((string)($in['notes'] ?? '')),
                   function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
    return (int)db()->lastInsertId();
}

function connect_org_get($id) { connect_org_migrate(); return ops_one("SELECT * FROM cx_organisations WHERE id=?", [(int)$id]) ?: null; }
function connect_org_list() { connect_org_migrate(); return ops_all("SELECT * FROM cx_organisations ORDER BY name, id") ?: []; }

/** Change an organisation's type (and re-derive its package). */
function connect_org_set_type($id, $orgType) {
    connect_org_migrate();
    $orgType = strtoupper((string)$orgType);
    if (!isset(connect_org_types()[$orgType])) return false;
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("UPDATE cx_organisations SET org_type=?, package_key=? WHERE id=?")->execute([$orgType, $pkg, (int)$id]);
    return true;
}

/** Whether an organisation's bundle includes a module (the future gate helper). */
function connect_org_can_module($orgId, $moduleKey) {
    $o = connect_org_get($orgId);
    if (!$o) return false;
    return in_array((string)$moduleKey, connect_org_type_modules((string)$o['org_type']), true);
}

/** Master-only admin screen: register organisations and see their entitlements. */

/**
 * The queue a person works through (Q26).
 *
 * Authorisation is the SAME gate that already guards the organisations screen
 * next door — this queue is about organisations, and no new permission was
 * invented for it (§16). Looking at the queue is all this screen does; giving
 * somebody access is still the portal invitation, which asks for its own
 * authority and writes its own trail. There is deliberately no "approve and let
 * them in" button: approving here records a decision, it does not hand over an
 * organisation.
 */
function ops_connect_access_requests($method) {
    ops_require(function_exists('is_master') && is_master(),
                'Only a master admin can see access requests.');
    connect_access_request_migrate();
    if ($method === 'POST') {
        $id  = (int)($_POST['id'] ?? 0);
        $act = (string)($_POST['action'] ?? '');
        if ($id > 0 && ($act === 'APPROVED' || $act === 'REJECTED')) {
            connect_access_request_close($id, $act, (string)($_POST['note'] ?? ''));
            flash($act === 'APPROVED'
                ? 'Noted. Invite them from the client record to actually give them access.'
                : 'Request closed.');
        } else {
            flash('Nothing to do.', 'error');
        }
        redirect('/access-requests');
    }
    view('ops/connect_access_requests', [
        'rows'   => connect_access_requests_all('PENDING'),
        'closed' => connect_access_requests_all('APPROVED'),
    ]);
    return true;
}

function ops_connect_orgs($method) {
    ops_require(function_exists('is_master') && is_master(), 'Only a master admin can manage organisations.');
    connect_org_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? 'add');
        if ($act === 'add') {
            connect_org_add((string)($_POST['name'] ?? ''), (string)($_POST['org_type'] ?? 'COMPANY'), $_POST)
                ? flash('Organisation registered.') : flash('Give the organisation a name and a type.', 'error');
        } elseif ($act === 'set_type') {
            connect_org_set_type((int)($_POST['id'] ?? 0), (string)($_POST['org_type'] ?? '')) ? flash('Organisation updated.') : flash('Unknown type.', 'error');
        } elseif ($act === 'approve') {
            connect_org_approve((int)($_POST['id'] ?? 0)); flash('Organisation approved.');
        }
        redirect('/connect-orgs');
    }
    view('ops/connect_orgs', ['orgs' => connect_org_list(), 'types' => connect_org_types()]);
    return true;
}
