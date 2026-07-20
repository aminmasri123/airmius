import argparse
import json
import re
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
ROOT = PROJECT / 'resources/js'
LOCALES = ('de', 'en', 'fr', 'ar')


def visible_sources():
    sources = {}
    files = list((ROOT / 'Pages').rglob('*.vue')) + list((ROOT / 'Components').rglob('*.vue')) + list((ROOT / 'Layouts').rglob('*.vue'))
    for path in files:
        text = path.read_text(encoding='utf-8-sig')
        template = re.search(r'<template\b[^>]*>(.*?)</template>', text, re.S)
        if not template:
            continue
        body = template.group(1)
        static_body = re.sub(r'\{\{.*?\}\}', '', body, flags=re.S)
        candidates = re.findall(r'>([^<>{}]+)<', static_body)
        candidates += [m.group(2) for m in re.finditer(r'(?<![:\w-])(placeholder|title|aria-label|alt)="([^"{}]+)"', body)]
        for candidate in candidates:
            value = re.sub(r'\s+', ' ', candidate).strip()
            if not value or not re.search(r'[A-Za-zÀ-ž]', value):
                continue
            if value.startswith(('http:', 'https:', 'mailto:')):
                continue
            if re.fullmatch(r'[\w$]+(?:\.[\w$?]+)+', value) or re.search(r"\b(?:t|\$t|mt)\('", value):
                continue
            sources.setdefault(value, set()).add(str(path.relative_to(PROJECT)))
    return sources


parser = argparse.ArgumentParser()
parser.add_argument('--fail-on-missing', action='store_true')
args = parser.parse_args()
catalogs = {locale: json.loads((ROOT / 'lang' / f'{locale}.json').read_text(encoding='utf-8-sig')) for locale in LOCALES}
sources = visible_sources()
missing = {}

for locale in ('en', 'fr', 'ar'):
    auto = catalogs[locale].get('auto', {})
    missing[locale] = [
        {'source': source, 'paths': sorted(paths)}
        for source, paths in sources.items()
        if source not in auto and source not in catalogs[locale]
    ]

report = {
    'source_locale': 'de',
    'visible_source_count': len(sources),
    'missing_counts': {locale: len(items) for locale, items in missing.items()},
    'missing': missing,
}
print(json.dumps(report, ensure_ascii=False, indent=2))

if args.fail_on_missing and any(missing.values()):
    raise SystemExit(1)
