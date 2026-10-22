# Fill in the test copy through the app's own screens, the way a person would
# (docs/test-book "Start here", SET-01 … SET-10, plus the platform numbers).
import sys, re
sys.path.insert(0, __import__('os').path.join(__import__('os').path.dirname(__file__), '..', 'source'))
from web import *
import setup_guide as G
ADM, TU = [l.strip() for l in open(sys.argv[1])][:2]
log = []
def step(name, res, want=(302,)):
    st, page, loc = res
    ok = st in want
    log.append((name, ok, st, loc))
    print(('ok  ' if ok else 'FAIL'), name, st, loc, ('' if ok else flash(page)[:2]))
    return res

def login(user, pw):
    cj.clear()
    return post('/login', '/login', {'username': user, 'password': pw})

step('admin signs in', login('admin', ADM))
st, p, loc = get('/')
if loc.endswith('/setup') or '/setup' in loc:
    step('first-time setup (SET-02)', post('/setup', '/setup-save', {'admin_pass': ADM, 'app_name': 'TB Inspection Services (TEST)', 'industry': 'inspection',
        'fy_start_month': '4', 'currency_symbol': '₹', 'date_format': 'd M Y', 'grievance_name': 'Test Owner', 'grievance_email': 'grievance@example.test'}))

# Made-up data only: clear the starter client/vendor list a new main site begins with.
step('clear starter clients & vendors', post('/reset-data', '/reset-data', {'groups[]': ['partners'], 'confirm_word': 'DELETE'}))

# SET-01 company details (made up).
step('company profile (SET-01)', post('/company-profile', '/company-profile', {'legal_name': 'TB Inspection Services Pvt Ltd (TEST)', 'brand': 'TB Inspection Services (TEST)',
    'address': '101, Test Tower, Sample Road\nAhmedabad, Gujarat 380015', 'gstin': '', 'pan': '', 'state': 'Gujarat', 'email': 'office@tb-test.example',
    'phone': '+91 00000 00000', 'website': 'https://tb-test.example', 'footer': 'TEST COMPANY — made-up details for testing only', 'report_brand_color': '#1E40AF'}))

# SET-06 the 19 test users, top to bottom.
st, form, _ = get('/user-new')
roles = {t: v for v, t in options(form, 'role')}
teams = options(form, 'team_member_role')
team_val = {'FIELD': teams[0][0], 'COORD': teams[1][0], 'OFFICE': teams[2][0]}
offices = {t.split(' (')[0]: v for v, t in options(form, 'home_office_id') if v.isdigit()}
newinsp = [v for v, t in options(form, 'inspector_id') if t.startswith('➕')][0]
role_label = {'BUSINESS_DIRECTOR': 'Business Director', 'SBU_HEAD': 'Business Unit Head', 'FINANCE': 'Finance', 'QUALITY_HEAD': 'Quality Head',
    'TECHNICAL_HEAD': 'Technical Head', 'MARKETING_MANAGER': 'Marketing Manager', 'BRANCH_MANAGER': 'Branch Manager', 'BRANCH_APP_MANAGER': 'Branch Application Manager',
    'OPERATION_MANAGER': 'Operation Manager', 'ASST_MANAGER': 'Asst. Manager', 'COORDINATOR': 'Coordinator', 'BUSINESS_DEV_MANAGER': 'Business Development Manager',
    'KEY_ACCOUNTS_MANAGER': 'Key Accounts Manager', 'MARKETING_EXECUTIVE': 'Marketing Executive', 'SR_INSPECTOR': 'Senior Inspector', 'INSPECTOR': 'Inspector'}
for u in G.USERS:
    st, form, _ = get('/user-new')
    rt = ''
    if not u['reports'].startswith('—'):
        rt = [v for v, t in options(form, 'reports_to_id') if t.startswith(u['reports'] + ' ·')][0]
    last = u['name'].split(' ', 1)[1]
    d = {'username': u['user'], 'password': TU, 'first_name': 'Test', 'last_name': last,
         'email': u['user'] + '@tb-test.example', 'role': roles[role_label[u['role']]], 'home_office_id': offices[u['home']],
         'department': u['dept'], 'inspector_id': newinsp, 'team_member_role': team_val[u['team']], 'is_active': '1',
         'reports_to_id': rt, 'weekly_working_days': '6', 'daily_hours': '8', 'half_day_hours': '4'}
    if u['scope'] == 'sbu': d['scope_sbus[]'] = ['IND']
    else: d['scope_sbus_all'] = '1'
    if u['role'] in ('INSPECTOR', 'SR_INSPECTOR'):
        d['monthly_ctc'] = '60000' if u['role'] == 'SR_INSPECTOR' else '40000'
        d['is_production'] = '1'
        pos = [v for v, t in options(form, 'position_title') if t == ('Sr. Inspector' if u['role'] == 'SR_INSPECTOR' else 'Inspector')]
        if pos: d['position_title'] = pos[0]
    step('user ' + u['user'], post('/user-new', '/user-new', d))

# SET-07 approval levels (quotation value bands).
for lvl, lo, hi, who in [(1, 0, 500000, 'BRANCH_MANAGER'), (2, 500000, 2500000, 'MARKETING_MANAGER'), (3, 2500000, 0, 'BUSINESS_DIRECTOR')]:
    step('approval level %d' % lvl, post('/quote-approval-rule-new', '/quote-approval-rule-new', {'name': 'Level %d' % lvl, 'level': lvl, 'active': '1',
        'match_type': 'ANY', 'sbu': '', 'min_amount': lo, 'max_amount': hi, 'approver_role': who, 'approver_user_id': ''}))

# SET-08 Set up Sales answers; SET-09 pre-order checklist.
step('Set up Sales answers (SET-08)', post('/sales-setup', '/sales-setup', {'quote_unmatched_approval': 'top', 'quote_self_approval': 'yes', 'quote_approval_mode': 'order',
    'quote_approval_escalate_days': '1', 'quote_manmonth_costing': 'yes', 'preorder_checklist_on': '1', 'preorder_checklist_require': '1'}))
step('pre-order checklist (SET-09)', post('/preorder-checklist', '/preorder-checklist', {'enabled': '1', 'require_all': '1',
    'items': 'Scope and standards understood\nSite access and safety needs known\nA competent inspector is available\nNo conflict of interest\nPayment terms agreed'}))

# SET-10 test customers (made up; the TB prefix finds them later).
CUST = [('TB Refinery Ltd', '1', 'OWNER', 'OIL_GAS', 'PUB_LTD', 'ACTIVE'), ('TB Steel Works Pvt Ltd', '2', 'OWNER', 'STEEL', 'PVT_LTD', 'ACTIVE'),
        ('TB Pharma LLP', '1', 'OWNER', 'CHEMICAL', 'LLP', 'ACTIVE'), ('TB Valves Pvt Ltd', '1', 'MANUFACTURER', 'MANUFACTURING', 'PVT_LTD', 'ACTIVE'),
        ('TB Defaulter Co', '1', 'OWNER', 'OTHER', 'PVT_LTD', 'ACTIVE'), ('TB Paused Co', '1', 'OWNER', 'OTHER', 'PVT_LTD', 'ACTIVE'),
        ('TB Group Parent', '1', 'OWNER', 'MANUFACTURING', 'PVT_LTD', 'ACTIVE'), ('TB Group Child', '1', 'OWNER', 'MANUFACTURING', 'PVT_LTD', 'ACTIVE')]
for name, br, ct, ind, own, stt in CUST:
    step('customer ' + name, post('/partner-new', '/partner-new', {'legal_name': name, 'display_name': name, 'contact_name': 'Test Contact',
        'contact_email': re.sub('[^a-z]', '', name.lower()) + '@tb-test.example', 'contact_mobile': '9000000000', 'home_branch_id': br, 'is_client': '1',
        'client_type': ct, 'industry': ind, 'ownership_type': own, 'status': stt, 'payment_terms': '30 days from invoice', 'credit_days': '30'}))

# Platform numbers: the researched suggestions, plus GST with a made-up GSTIN.
st, form, _ = get('/pricing-usage')
mods = sorted(set(re.findall(r'name="mod_(\w+)_month"', form)))
d = {'action': 'apply_all', 'currency': 'INR', 'seat_month': '499', 'seat_year': '', 'saas_yearly_discount_pct': '20',
     '_has_plat_gst_enabled': '1', 'plat_gst_enabled': '1', 'plat_gst_rate': '18', 'plat_gst_mode': 'exclusive', 'plat_gst_sac': '997331',
     'plat_gstin': sys.argv[2], 'plat_legal_name': 'EXAACT Platform (TEST — made-up GSTIN)', 'plat_address': '1, Test Platform House\nAhmedabad, Gujarat 380001',
     'plat_invoice_prefix': 'TST', 'ai_cap': '100', 'ai_pack_size': '100', 'ai_pack_price': '199'}
for m in mods: d['mod_%s_month' % m] = '999'; d['mod_%s_year' % m] = ''
step('prices, yearly discount, GST (test values)', post('/pricing-usage', '/pricing-usage', d))
step('free trial & self sign-up', post('/companies', '/companies', {'do': 'selfserve_save', 'ss_on': '1', 'ss_trial_days': '30', 'ss_storage_mb': '2048',
    'ss_trial_seats': '10', 'ss_notice_days': '15', 'ss_delete_days': '90', 'ss_autodelete': '1'}))
step('storage allowance', post('/companies', '/companies', {'do': 'storage_save', 'st_base_gb': '10', 'st_login_gb': '2', 'st_pack_gb': '50',
    'st_pack_month': '399', 'st_pack_year': ''}))

fails = [l for l in log if not l[1]]
print('\n%d steps, %d failed' % (len(log), len(fails)))
