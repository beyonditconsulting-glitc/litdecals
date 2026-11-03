"""Deploy the four tracked storefront files through the sandbox-only connector."""
import base64
import hashlib
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import sys
import time
import urllib.error
import urllib.request

ORIGIN = 'https://litdecals.com/sandbox-redesign/'
API = ORIGIN + 'wp-json/litdecals-sandbox/v1/'
NAMES = ('litdecals-storefront.php', 'home.php', 'storefront.css', 'storefront.js')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class SiteTitle(HTMLParser):
    def __init__(self):
        super().__init__()
        self.tag = None
        self.titles = []
        self.text = []
    def handle_starttag(self, tag, attrs):
        if self.tag is None and 'site-title' in dict(attrs).get('class', '').split():
            self.tag = tag
            self.text = []
    def handle_data(self, data):
        if self.tag:
            self.text.append(data)
    def handle_endtag(self, tag):
        if tag == self.tag:
            self.titles.append(' '.join(''.join(self.text).split()))
            self.tag = None

def main():
    values = {name: os.environ.get(name, '') for name in ('BASIC_USER', 'BASIC_PASSWORD', 'WP_APP_PASSWORD')}
    if not all(values.values()):
        sys.exit('Missing required repository secret.')
    basic = base64.b64encode((values['BASIC_USER'] + ':' + values['BASIC_PASSWORD']).encode()).decode()
    wordpress = base64.b64encode(('litdecals:' + values['WP_APP_PASSWORD'].strip()).encode()).decode()
    for value in (basic, wordpress):
        print('::add-mask::' + value, flush=True)
    opener = urllib.request.build_opener(NoRedirect())
    def request(url, payload=None, wordpress_auth=True):
        headers = {'Authorization': 'Basic ' + basic, 'Accept': 'application/json',
                   'User-Agent': 'LitDecals-Sandbox-Publisher/1.0', 'Cache-Control': 'no-cache'}
        if wordpress_auth:
            headers['X-LitDecals-Authorization'] = 'Basic ' + wordpress
        body = None
        if payload is not None:
            body = json.dumps(payload).encode()
            headers['Content-Type'] = 'application/json'
        req = urllib.request.Request(url, data=body, headers=headers, method='POST' if body is not None else 'GET')
        try:
            with opener.open(req, timeout=60) as response:
                return response.read(4000000)
        except urllib.error.HTTPError as error:
            raise RuntimeError('Sandbox request failed: HTTP ' + str(error.code)) from None
        except urllib.error.URLError:
            raise RuntimeError('Sandbox network request failed.') from None
    def api(route, payload=None):
        return json.loads(request(API + route, payload))
    status = api('status')
    if status.get('sandbox') is not True or str(status.get('home', '')).rstrip('/') != ORIGIN.rstrip('/'):
        sys.exit('Sandbox identity check failed; deployment refused.')
    root = Path(__file__).resolve().parent.parent / 'storefront'
    files = {}
    total = 0
    for name in NAMES:
        path = root / name
        if not path.is_file() or path.is_symlink():
            sys.exit('Missing or unsafe source file: ' + name)
        content = path.read_bytes()
        total += len(content)
        files[name] = {'base64': base64.b64encode(content).decode(), 'sha256': hashlib.sha256(content).hexdigest()}
    if total > 2000000:
        sys.exit('Deployment exceeds connector limit.')
    result = api('deploy', {'files': files})
    expected = {name: entry['sha256'] for name, entry in files.items()}
    if result.get('deployed') is not True or result.get('sha256') != expected:
        sys.exit('Deployment checksum verification failed; preview was not enabled.')
    enabled = api('preview', {'enabled': True})
    if enabled.get('enabled') is not True:
        sys.exit('Files deployed but sandbox preview did not enable.')
    status = api('status')
    if status.get('redesign_enabled') is not True:
        sys.exit('Sandbox preview readback failed.')
    revision = os.environ.get('GITHUB_SHA', 'check')
    for attempt in range(5):
        parser = SiteTitle()
        html = request(ORIGIN + '?litdecals_verify=' + revision + '-' + str(attempt), wordpress_auth=False).decode('utf-8', 'replace')
        parser.feed(html)
        if parser.titles and all(title == 'Lit Decals' for title in parser.titles):
            print('SUCCESS: All four file checksums verified; sandbox preview enabled; visible header reads Lit Decals.')
            return
        time.sleep(2)
    sys.exit('Files deployed and enabled, but the visible header could not be confirmed. Check the page cache.')

if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, ValueError) as error:
        sys.exit(str(error))
