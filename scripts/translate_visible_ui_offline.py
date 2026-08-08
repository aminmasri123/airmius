import concurrent.futures
import argparse
import json
import re
import time
import urllib.request
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
ROOT = PROJECT / 'resources/js'
PROGRESS = PROJECT / 'storage/app/localization-progress'
PROGRESS.mkdir(parents=True, exist_ok=True)
API = 'http://127.0.0.1:11435/api/chat'
MODEL = 'qwen3:4b'
LANG_NAMES = {'en': 'English', 'fr': 'French', 'ar': 'Modern Standard Arabic'}
LANG_INSTRUCTIONS = {
    'en': 'Write idiomatic English only. Do not leave German words in the result.',
    'fr': 'Écris uniquement en français naturel. Ne conserve aucun mot allemand et ne réponds jamais en anglais.',
    'ar': 'اكتب بالعربية الفصحى الحديثة الطبيعية فقط. لا تترك كلمات ألمانية أو إنجليزية إلا الأسماء والعلامات التقنية المذكورة.',
}
PRESERVED_TECHNICAL_TOKENS = {'XP', 'API', 'SEO', 'GPS', 'GPX', 'CSV', 'DATEV', 'SEPA', 'IBAN', 'BIC'}


def visible_sources():
    result = set()
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
            result.add(value)
    return sorted(result)


def call_model(prompt, schema):
    payload = json.dumps({
        'model': MODEL,
        'stream': False,
        'think': False,
        'format': schema,
        'messages': [{'role': 'user', 'content': prompt}],
        'options': {'temperature': 0.1, 'num_ctx': 4096},
    }).encode()
    request = urllib.request.Request(API, data=payload, headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(request, timeout=600) as response:
        outer = json.loads(response.read().decode())
    return json.loads(outer['message']['content'])


def validate_result(items, result, strict_tokens=True):
    if not isinstance(result, dict) or any(source not in result or not isinstance(result[source], str) or not result[source].strip() for source in items):
        raise ValueError('response did not preserve every exact German source key')
    for source in items:
        without_entities = re.sub(r'&[A-Za-z]+;', '', source)
        if not re.search(r'[A-Za-zÀ-ž]', without_entities):
            result[source] = source
        if source.strip(' :+-') in PRESERVED_TECHNICAL_TOKENS:
            result[source] = source
        if re.findall(r'&[A-Za-z]+;', source) != re.findall(r'&[A-Za-z]+;', result[source]):
            raise ValueError(f'HTML entities changed for {source!r}')
        for token in re.findall(r'(?<![A-Za-z])[A-Z][A-Z0-9]{1,}(?![A-Za-z])', source):
            if strict_tokens and token in PRESERVED_TECHNICAL_TOKENS and token not in result[source]:
                raise ValueError(f'technical token {token!r} changed for {source!r}')
    return {source: result[source].strip() for source in items}


def ask_model(items, lang):
    language = LANG_NAMES[lang]
    schema = {
        'type': 'object',
        'properties': {source: {'type': 'string'} for source in items},
        'required': items,
        'additionalProperties': False,
    }
    prompt = f'''You are the professional localization editor for Airmius, a platform for athletes, coaches and sports clubs.
Translate every German UI string in the JSON array into {language}.
Return ONLY one valid JSON object. Every exact German input string must remain its object key. Its value must be the corresponding translation.
Rules:
- German is the only source of truth.
- {LANG_INSTRUCTIONS[lang]}
- Determine meaning from the sports-platform context. "Bestehen" in a percentage means successful completion/passing, not existence.
- Preserve Airmius, URLs, emails, file paths, HTML entities, numbers, formulas, placeholders, currency codes, units, IBAN, BIC, API, SEO, GPS, GPX, CSV, DATEV, SEPA, Stripe and PayPal.
- Copy every HTML entity such as &middot; byte-for-byte. Never invent or damage an entity.
- Preserve punctuation and ellipses. Translate short fragments naturally because they can surround dynamic values.
- Before answering, silently translate every result back into German and correct it if the meaning differs.
- Never add explanations or markdown.
- For Arabic use clear Modern Standard Arabic suitable for a sports application.
Input:
{json.dumps(items, ensure_ascii=False)}'''
    edited = call_model(prompt, schema)
    if lang == 'ar':
        for source in items:
            if 'XP' in source and 'XP' not in edited.get(source, ''):
                edited[source] = re.sub(r'(?:نقاط\s+)?(?:الخبرة|التفاعل|تفاعل)', 'XP', edited[source], count=1)
            if re.fullmatch(r'[+\-\s]*XP', source):
                edited[source] = source
    if lang == 'fr':
        replacements = {
            '- Dernière rappel:': '- Dernier rappel:',
            '-Abos à assigner': '- Affecter des abonnements',
        }
        for source in items:
            edited[source] = replacements.get(edited.get(source, ''), edited.get(source, ''))
    return validate_result(items, edited)


parser = argparse.ArgumentParser()
parser.add_argument('--limit', type=int, default=0, help='Maximum new strings per language (0 means all)')
parser.add_argument('--languages', nargs='+', choices=tuple(LANG_NAMES), default=['en', 'fr', 'ar'])
parser.add_argument('--retranslate-identical', action='store_true', help='Regenerate completed values that still equal German')
args = parser.parse_args()

sources = visible_sources()
print(f'Visible source strings: {len(sources)}', flush=True)


def translate_batch(batch, lang):
    for attempt in range(4):
        try:
            return ask_model(batch, lang)
        except Exception as exc:
            print(f'{lang}: retry {attempt + 1}: {exc}', flush=True)
            time.sleep(2 * (attempt + 1))
    raise RuntimeError(f'Could not translate {lang} batch starting with {batch[0]!r}')


def process_language(lang, parallel_batches=1):
    catalog = json.loads((ROOT / 'lang' / f'{lang}.json').read_text(encoding='utf-8-sig'))
    automatic_catalog = json.loads((ROOT / 'lang' / 'auto' / f'{lang}.json').read_text(encoding='utf-8-sig'))
    completed_path = PROGRESS / f'airmius_local_translations_{lang}.json'
    completed = json.loads(completed_path.read_text()) if completed_path.exists() else {}
    missing = [
        s for s in sources
        if s not in automatic_catalog.get('auto', {}) and s not in catalog and (
            s not in completed
            or (args.retranslate_identical and completed[s].casefold() == s.casefold())
        )
    ]
    if args.limit:
        missing = missing[:args.limit]
    print(f'{lang}: {len(missing)} remaining', flush=True)
    while missing:
        batch_size = 10 if args.retranslate_identical else 30
        batches = [missing[offset:offset + batch_size] for offset in range(0, min(len(missing), batch_size * parallel_batches), batch_size)]
        if len(batches) == 1:
            results = [translate_batch(batches[0], lang)]
        else:
            with concurrent.futures.ThreadPoolExecutor(max_workers=parallel_batches) as batch_pool:
                results = list(batch_pool.map(lambda batch: translate_batch(batch, lang), batches))
        for translated in results:
            completed.update(translated)
        completed_path.write_text(json.dumps(completed, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        missing = missing[sum(len(batch) for batch in batches):]
        print(f'{lang}: saved {len(completed)}, remaining {len(missing)}', flush=True)


parallel_languages = [lang for lang in args.languages if lang in ('en', 'fr')]
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    futures = [pool.submit(process_language, lang) for lang in parallel_languages]
    for future in futures:
        future.result()

if 'ar' in args.languages:
    process_language('ar', parallel_batches=2)
