# Tiny browser for driving the app's own forms (test copy build only).
import urllib.request, urllib.parse, http.cookiejar, re, html, sys, json
BASE = 'http://127.0.0.1:8899'
cj = http.cookiejar.CookieJar()
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedir)
def req(path, data=None):
    body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    r = urllib.request.Request(BASE + path, data=body)
    try:
        resp = op.open(r); return resp.status, resp.read().decode('utf-8', 'replace'), ''
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), e.headers.get('Location', '')
def get(path): return req(path)
def csrf(page):
    m = re.search(r'name="_csrf" value="([^"]+)"', page); return m.group(1) if m else ''
def post(form_path, action, data):
    st, page, loc = get(form_path)
    d = dict(data); d['_csrf'] = csrf(page)
    return req(action, d)
def flash(page):
    return [html.unescape(re.sub('<[^>]+>', '', m)).strip() for m in re.findall(r'class="(?:msg|flash)[^"]*"[^>]*>(.*?)</div>', page, re.S)]
def options(page, name):
    m = re.search(r'<select[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</select>', page, re.S)
    if not m: return []
    return [(v, html.unescape(re.sub('<[^>]+>', '', t)).strip()) for v, t in re.findall(r'<option[^>]*value="([^"]*)"[^>]*>(.*?)</option>', m.group(1), re.S)]
def checkboxes(page, name):
    return re.findall(r'<input[^>]*name="' + re.escape(name) + r'"[^>]*value="([^"]*)"[^>]*>\s*([^<]*)', page)
