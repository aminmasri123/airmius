import json
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET

for path in ['/robots.txt', '/sitemap.xml', '/blog/rss.xml', '/rss.xml', '/site.webmanifest']:
    try:
        with urllib.request.urlopen('https://airmius.com'+path, timeout=30) as response:
            body = response.read()
            if path.endswith('.xml'):
                ET.fromstring(body)
            elif path.endswith('.webmanifest'):
                json.loads(body)
            print(path, response.status, response.headers.get('Content-Type'), 'bytes=', len(body), 'cookies=', bool(response.headers.get('Set-Cookie')), 'etag=', bool(response.headers.get('ETag')))
            etag = response.headers.get('ETag')
        if etag:
            try:
                urllib.request.urlopen(urllib.request.Request('https://airmius.com'+path, headers={'If-None-Match': etag}), timeout=30)
                raise RuntimeError('Expected 304')
            except urllib.error.HTTPError as conditional:
                assert conditional.code == 304, conditional.code
                print('  conditional: 304')
    except urllib.error.HTTPError as error:
        print(path, error.code)

for locale in ['de', 'en', 'fr', 'ar']:
    for path in ['/blog/rss.xml', '/rss.xml', '/site.webmanifest']:
        with urllib.request.urlopen('https://airmius.com'+path+'?locale='+locale, timeout=30) as response:
            body = response.read()
            assert response.headers.get('Content-Language') == locale
            assert not response.headers.get('Set-Cookie')
            if path.endswith('webmanifest'):
                assert json.loads(body)['lang'] == locale
            else:
                assert ET.fromstring(body).findtext('channel/language').startswith(locale)
    print(locale, 'RSS alias and manifest: OK')

try:
    urllib.request.urlopen('https://airmius.com/', timeout=30)
    raise RuntimeError('Maintenance page unexpectedly disabled')
except urllib.error.HTTPError as error:
    assert error.code == 503, error.code
    print('Homepage: maintenance 503 preserved')
