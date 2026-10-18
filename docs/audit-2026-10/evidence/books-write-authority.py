#!/usr/bin/env python3
"""EXAACT audit — who can actually change the books?

Executed against a THROWAWAY copy of a UAT database. Nothing here touches
production. For each role, in isolation:

  1. record a receipt               POST /receipt-new     -> did a row appear?
  2. start a draft invoice          POST /invoice-new      -> did a row appear?
  3. cancel a draft of its own      POST /invoice-cancel   -> did status change?

Each role gets its OWN draft to cancel, created by the master admin first, so
one role's result cannot consume another's target. Issuing is not row-tested:
books_issue() refuses any draft with no lines on business grounds, which would
mask the authority gate. The authority line is read instead and cited.

Reference: docs/02-permission-matrix.md:215 (the intended audience) and
booksui.php:225 / books.php:89-91 (the gates as built).
"""
import json, re, sqlite3, subprocess, sys

SP   = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
DB   = f'{SP}/moneygate2.sqlite'
BASE = 'http://127.0.0.1:8794'
USERS = [('admin','Master Admin'),('aud.director','Business Director'),
         ('aud.branch','Branch Manager'),('aud.manager','Operation Manager'),
         ('uat.coord','Coordinator'),('uat.inspector','Inspector'),
         ('uat.finance','Finance')]
MATRIX = {'Master Admin':'Full','Business Director':'—','Branch Manager':'View',
          'Operation Manager':'—','Coordinator':'View','Inspector':'—','Finance':'Issue'}

def sh(*a): return subprocess.run(a, capture_output=True).stdout.decode('utf-8','replace')
def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h); return m.group(1) if m else ''
def q(sql, args=()):
    c = sqlite3.connect(DB)
    try: return c.execute(sql, args).fetchall()
    except Exception: return []
    finally: c.close()
def login(u):
    jar = f'{SP}/wa_{u}.ck'
    subprocess.run(['rm','-f',jar])
    sh('curl','-s','-b',jar,'-c',jar,'-X','POST','-d',
       f'_csrf={csrf(sh("curl","-s","-c",jar,f"{BASE}/login"))}'
       f'&username={u}&password=Pw12345678&viewport=1280','-o','/dev/null',f'{BASE}/login')
    return jar

def receipt(jar):
    f = sh('curl','-s','-b',jar,f'{BASE}/receipt-new')
    if 'name="_csrf"' not in f: return 'refused (no form)'
    pid = re.search(r'<option value="(\d+)"', f)
    before = len(q('select id from receipts'))
    sh('curl','-s','-b',jar,'-L','-X','POST','-d',
       f'_csrf={csrf(f)}&partner_id={pid.group(1) if pid else 0}&amount=1.00'
       f'&receipt_date=2026-10-18&mode=NEFT&ref_no=AUDIT&note=audit', f'{BASE}/receipt-new')
    return 'CAN RECORD MONEY' if len(q('select id from receipts')) > before else 'refused'

def draft(jar):
    f = sh('curl','-s','-b',jar,f'{BASE}/invoice-new')
    if 'name="_csrf"' not in f: return 'refused (no form)', None
    pid = re.search(r'<option value="(\d+)"', f)
    before = {r[0] for r in q('select id from invoices')}
    sh('curl','-s','-b',jar,'-L','-X','POST','-d',
       f'_csrf={csrf(f)}&partner_id={pid.group(1) if pid else 0}&invoice_date=2026-10-18',
       f'{BASE}/invoice-new')
    new = {r[0] for r in q('select id from invoices')} - before
    return ('CAN RAISE AN INVOICE' if new else 'refused'), (max(new) if new else None)

def cancel(jar, inv):
    if not inv: return 'not tested (no draft of its own)'
    page = sh('curl','-s','-b',jar,f'{BASE}/invoice?id={inv}')
    sh('curl','-s','-b',jar,'-L','-X','POST','-d',
       f'_csrf={csrf(page)}&id={inv}&reason=audit probe', f'{BASE}/invoice-cancel')
    st = q('select status from invoices where id=?', (inv,))
    return 'CAN CANCEL' if st and st[0][0] == 'CANCELLED' else 'refused'

out = {}
print(f"{'role':<20}{'matrix says':<13}{'record a receipt':<20}{'raise an invoice':<24}{'cancel an invoice':<20}")
print('-' * 97)
for u, label in USERS:
    jar = login(u)
    r = receipt(jar)
    d, inv = draft(jar)
    c = cancel(jar, inv)
    out[label] = {'matrix': MATRIX[label], 'receipt': r, 'draft': d, 'cancel': c}
    print(f'{label:<20}{MATRIX[label]:<13}{r:<20}{d:<24}{c:<20}')

json.dump(out, open(f'{SP}/books-write-authority.json','w'), indent=1)
print()
print('Issue / cancel authority as written:  books_can_issue() = books_can_cancel()')
print('  = licence_module_live(invoicing) && (finance.reconcile || is_master())   books.php:90-91')
print('Every other write:  ops_require($canIssue || can("data.credit"))          booksui.php:225')
print('data.credit is labelled "See the inter-office credit figures"             access.php:133')
