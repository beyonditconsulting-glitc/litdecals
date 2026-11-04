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
        self.collection = None
        self.collections = []
        self.findrc_links = []
        self.active_link = None
        self.catalog_links = []
        self.product_links = []
        self.header_ads = []
        self.header_ad_images = []
        self.header_logos = []
        self.new_products = 0
        self.visible_new_products = 0
        self.product_cycle = False
    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        classes = attributes.get('class', '').split()
        if tag == 'section' and 'litrd-products' in classes and attributes.get('data-cycle-size') == '4':
            self.product_cycle = True
        if tag == 'article' and 'litrd-product' in classes:
            self.new_products += 1
            if 'hidden' not in attributes:
                self.visible_new_products += 1
        if tag == 'a' and 'litrd-header-ad' in classes:
            self.header_ads.append(attributes)
        if tag == 'a' and 'litrd-blue-header-logo' in classes:
            self.header_logos.append(attributes)
        if tag == 'img' and 'litrd-header-ad-image' in classes:
            self.header_ad_images.append(attributes)
        if tag == 'a':
            self.active_link = {'href': attributes.get('href', ''), 'text': []}
        if tag == 'a' and attributes.get('href') == 'https://findrc.parts/':
            self.findrc_links.append(attributes)
        if tag == 'a' and 'litrd-collection' in attributes.get('class', '').split():
            self.collection = {'href': attributes.get('href', ''), 'images': []}
        if tag == 'img' and self.collection is not None:
            self.collection['images'].append(attributes)
        if self.tag is None and 'site-title' in dict(attrs).get('class', '').split():
            self.tag = tag
            self.text = []
    def handle_data(self, data):
        if self.active_link is not None:
            self.active_link['text'].append(data)
        if self.tag:
            self.text.append(data)
    def handle_endtag(self, tag):
        if tag == 'a' and self.active_link is not None:
            text = ' '.join(''.join(self.active_link['text']).split())
            if '/product/' in self.active_link['href']:
                self.product_links.append({'text': text, 'href': self.active_link['href']})
            if text.startswith('Browse all products') or text.startswith('View the full catalog'):
                self.catalog_links.append({'text': text, 'href': self.active_link['href']})
            self.active_link = None
        if tag == 'a' and self.collection is not None:
            self.collections.append(self.collection)
            self.collection = None
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
        if not parser.titles and parser.header_logos and parser.header_ads and parser.header_ad_images:
            if parser.header_ads[0].get('href') != 'https://findrc.parts/':
                sys.exit('Header advertisement destination mismatch.')
            image_url = parser.header_ad_images[0].get('src', '')
            if image_url != ORIGIN + '?litrd_header_banner=basher-v1':
                sys.exit('Header advertisement image endpoint mismatch.')
            banner_bytes = request(image_url, wordpress_auth=False)
            approved_banner = Path(__file__).resolve().parent.parent / 'design/findrc-parts-basher.webp'
            if hashlib.sha256(banner_bytes).digest() != hashlib.sha256(approved_banner.read_bytes()).digest():
                sys.exit('Published banner differs from the selected artwork.')
            print('SUCCESS: Header text removed; original logo and approved linked FINDRC.PARTS banner are published.')
            if not parser.product_cycle or not parser.new_products or parser.visible_new_products != min(4, parser.new_products):
                sys.exit('Four-product new arrivals layout verification failed.')
            print('SUCCESS: New arrivals displays four products initially with cycling enabled.')
            for label in ('Browse all products', 'View the full catalog'):
                if not any(link['text'].startswith(label) and link['href'] == ORIGIN + '?post_type=product&s=' for link in parser.catalog_links):
                    sys.exit('Catalog destination verification failed: ' + label)
            catalog = request(ORIGIN + '?post_type=product&s=&litdecals_verify=' + revision, wordpress_auth=False).decode('utf-8', 'replace')
            if 'woocommerce-loop-product__title' not in catalog:
                sys.exit('The catalog destination did not return a WooCommerce product listing.')
            mafia_html = request(ORIGIN + 'arrma-mafia-shop/?litdecals_verify=' + revision, wordpress_auth=False).decode('utf-8', 'replace')
            mafia_page = SiteTitle()
            mafia_page.feed(mafia_html)
            if not mafia_page.product_links:
                sys.exit('Mafia page did not return any product links.')
            mafia_hrefs = {link['href'] for link in mafia_page.product_links
                           if 'mafia' in (link['text'] + ' ' + link['href']).lower()}
            unrelated = [link for link in mafia_page.product_links if link['href'] not in mafia_hrefs]
            if unrelated:
                sys.exit('The Mafia page still contains unrelated product links: ' + json.dumps(unrelated[:8]))
            for title in ('RC Lipo Battery Savers', 'Lit Decals Mystery Box', '1:1 Car Door Battery Power Sticker'):
                if title in mafia_html:
                    sys.exit('An unrelated example remains on the Mafia page.')
            print('SUCCESS: Mafia page contains only Mafia product links; unrelated example products are absent.')
            print('SUCCESS: Both catalog buttons stay at the sandbox root and open the full product listing.')
            if not any('aria-haspopup' not in link for link in parser.findrc_links):
                sys.exit('Direct FINDRC.PARTS navigation link could not be confirmed.')
            print('SUCCESS: FINDRC.PARTS links directly to https://findrc.parts/.')
            mafia = [slide for slide in parser.collections if '/arrma-mafia-shop/' in slide['href']]
            stickers = [slide for slide in parser.collections if '/product-category/stickers-2/' in slide['href']]
            if not any(image.get('data-litrd-image-source') == 'arrma-mafia-hoodie' and image.get('src')
                       for slide in mafia for image in slide['images']):
                sys.exit('Published Mafia slider image could not be confirmed.')
            if not any(image.get('src') == ORIGIN + 'wp-content/uploads/2025/03/Sticker-Sheets-5.jpg'
                       for slide in stickers for image in slide['images']):
                sys.exit('Published Stickers slider image could not be confirmed.')
            print('SUCCESS: Mafia slide uses the hoodie product image; Stickers slide uses Sticker-Sheets-5.jpg.')
            for asset in ('storefront.css', 'storefront.js'):
                served = request(ORIGIN + 'wp-content/plugins/litdecals-storefront/' + asset + '?verify=' + revision, wordpress_auth=False)
                if hashlib.sha256(served).hexdigest() != expected[asset]:
                    sys.exit('Published asset readback mismatch: ' + asset)
            print('SUCCESS: All four file checksums verified; sandbox preview enabled; approved header banner verified; served CSS and JavaScript match the published source.')
            return
        time.sleep(2)
    sys.exit('Files deployed and enabled, but the visible header could not be confirmed. Check the page cache.')

if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, ValueError) as error:
        sys.exit(str(error))
