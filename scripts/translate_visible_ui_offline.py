import concurrent.futures
import argparse
import json
import os
import re
import time
import urllib.request
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
ROOT = PROJECT / 'resources/js'
PROGRESS = PROJECT / 'storage/app/localization-progress'
PROGRESS.mkdir(parents=True, exist_ok=True)
API = os.environ.get('OLLAMA_API', 'http://127.0.0.1:11435/api/chat')
MODEL = os.environ.get('OLLAMA_MODEL', 'qwen3:4b')
LANG_NAMES = {'en': 'English', 'fr': 'French', 'ar': 'Modern Standard Arabic'}
LANG_INSTRUCTIONS = {
    'en': 'Write idiomatic English only. Do not leave German words in the result.',
    'fr': 'Écris uniquement en français naturel. Ne conserve aucun mot allemand et ne réponds jamais en anglais.',
    'ar': 'اكتب بالعربية الفصحى الحديثة الطبيعية فقط. لا تترك كلمات ألمانية أو إنجليزية إلا الأسماء والعلامات التقنية المذكورة.',
}
LANG_TARGET_EMPHASIS = {
    'en': 'MANDATORY TARGET LANGUAGE: ENGLISH. Example: ["Datenschutz", "Konto löschen"] becomes ["Privacy", "Delete account"].',
    'fr': 'LANGUE CIBLE OBLIGATOIRE : FRANÇAIS. Exemple : ["Privacy", "Delete account"] devient ["Protection des données", "Supprimer le compte"].',
    'ar': 'اللغة الهدف الإلزامية: العربية. مثال: ["Privacy", "Delete account"] تصبح ["الخصوصية", "حذف الحساب"].',
}
PRESERVED_TECHNICAL_TOKENS = {'XP', 'API', 'SEO', 'GPS', 'GPX', 'CSV', 'DATEV', 'SEPA', 'IBAN', 'BIC'}
ARABIC_TO_ASCII_DIGITS = str.maketrans('٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', '01234567890123456789')
LEGAL_IDENTICAL_ALLOWED = {
    'en': {', E-Mail:', 'E-Mail:', 'Cookies', 'Moderation', 'Support'},
    'fr': {', E-Mail:', 'E-Mail:', 'Cookies', 'Support'},
    'ar': {', E-Mail:', 'E-Mail:'},
}
LEGAL_BRANDS = {
    'Airmius', 'Cloudflare', 'Gemini', 'Google', 'GraphHopper', 'Hostinger', 'IONOS',
    'Mapbox', 'Microsoft', 'OpenAI', 'OpenStreetMap', 'OSRM', 'PayPal', 'R2', 'Stripe',
    'openrouteservice',
}
LEGAL_FIXED_TRANSLATIONS = {
    'en': {
        'Ablauf': 'Process',
        'Analyse-, Marketing- oder Tracking-Technologien dürfen nur eingesetzt werden, wenn sie hier konkret benannt werden und eine erforderliche Einwilligung eingeholt wird.': 'Analytics, marketing or tracking technologies may be used only if they are specifically identified here and any required consent has been obtained.',
        'Anbieter sind für Beschreibung, Preisangaben, Lieferbarkeit, Leistungserbringung, Verbraucherinformationen, Gewährleistung, Steuern und sonstige rechtliche Pflichten ihrer Angebote verantwortlich, soweit Airmius nicht selbst ausdrücklich Vertragspartner ist.': 'Providers are responsible for the description, pricing, availability, performance, consumer information, warranties, taxes and other legal obligations relating to their offers unless Airmius is expressly the contracting party.',
        'Angaben nach § 5 DDG': 'Information pursuant to § 5 DDG',
        'Hinweis': 'Note',
        'Kontakt': 'Contact',
        'Moderation': 'Moderation',
        '2. Welche Daten Airmius verarbeitet': '2. What data Airmius processes',
        '5. Minderjährige und Elternzustimmung': '5. Minors and parental consent',
        'Bei IONOS AI Model Hub ist eine OpenAI-kompatible API vorgesehen; Airmius kann diesen Anbieter bevorzugen, wenn europäische bzw. deutsche Datenverarbeitung vertraglich und technisch passend eingerichtet ist.': 'IONOS AI Model Hub provides an OpenAI-compatible API; Airmius may prefer this provider if European or German data processing is configured appropriately in contractual and technical terms.',
        'Bestätige deine Identität mit deinem Passwort. Wenn du Google oder Microsoft zur Anmeldung verwendest, bestätige stattdessen deine Konto-E-Mail-Adresse.': 'Confirm your identity with your password. If you use Google or Microsoft to sign in, confirm your account email address instead.',
        'Kein Zugriff auf dein Konto?': "Can't access your account?",
        'Kontolöschung in der Airmius-App': 'Deleting your account in the Airmius app',
        'Kontolöschung in der Web-Version': 'Deleting your account in the web version',
        'Melde dich bei Airmius an und öffne Profil > Konto löschen.': 'Sign in to Airmius and open Profile > Delete account.',
        'Stand: 25.05.2026': 'Last updated: 25.05.2026',
        'Stand: 25.05.2026. KI-, Karten-, Routing-, Ernährungs- und Trackingfunktionen sind ergänzt. Bitte lege die aktuellen AVV/DPA-Unterlagen der tatsächlich genutzten Anbieter intern ab und lasse die Texte vor Livegang rechtlich final prüfen.': 'Last updated: 25.05.2026. AI, mapping, routing, nutrition and tracking features have been added. Please store the current AVV/DPA documents for the providers actually used internally and have the texts legally reviewed before going live.',
        'Art. 6 Abs. 1 lit. a DSGVO und Art. 8 DSGVO: Einwilligung, insbesondere bei zustimmungspflichtigen Minderjährigen.': 'Art. 6 Abs. 1 lit. a DSGVO and Art. 8 DSGVO: Consent, particularly where consent is required for minors.',
    },
    'fr': {
        'Ablauf': 'Déroulement',
        'Analyse-, Marketing- oder Tracking-Technologien dürfen nur eingesetzt werden, wenn sie hier konkret benannt werden und eine erforderliche Einwilligung eingeholt wird.': 'Les technologies d’analyse, de marketing ou de suivi ne peuvent être utilisées que si elles sont expressément mentionnées ici et si le consentement requis est obtenu.',
        'Anbieter sind für Beschreibung, Preisangaben, Lieferbarkeit, Leistungserbringung, Verbraucherinformationen, Gewährleistung, Steuern und sonstige rechtliche Pflichten ihrer Angebote verantwortlich, soweit Airmius nicht selbst ausdrücklich Vertragspartner ist.': 'Les fournisseurs sont responsables de la description, des prix, de la disponibilité, de l’exécution des prestations, des informations destinées aux consommateurs, de la garantie, des taxes et des autres obligations légales liées à leurs offres, sauf si Airmius est expressément lui-même partie au contrat.',
        'Angaben nach § 5 DDG': 'Informations conformément au § 5 DDG',
        'Hinweis': 'Remarque',
        'Kontakt': 'Contact',
        'Moderation': 'Modération',
        '2. Welche Daten Airmius verarbeitet': '2. Quelles données Airmius traite',
        '5. Minderjährige und Elternzustimmung': '5. Mineurs et consentement parental',
        'Bei IONOS AI Model Hub ist eine OpenAI-kompatible API vorgesehen; Airmius kann diesen Anbieter bevorzugen, wenn europäische bzw. deutsche Datenverarbeitung vertraglich und technisch passend eingerichtet ist.': 'IONOS AI Model Hub prévoit une API compatible avec OpenAI ; Airmius peut privilégier ce fournisseur si le traitement des données européen ou allemand est configuré de manière appropriée sur les plans contractuel et technique.',
        'Bestätige deine Identität mit deinem Passwort. Wenn du Google oder Microsoft zur Anmeldung verwendest, bestätige stattdessen deine Konto-E-Mail-Adresse.': 'Confirmez votre identité avec votre mot de passe. Si vous utilisez Google ou Microsoft pour vous connecter, confirmez plutôt l’adresse e-mail de votre compte.',
        'Kein Zugriff auf dein Konto?': 'Vous n’avez pas accès à votre compte ?',
        'Kontolöschung in der Airmius-App': 'Suppression du compte dans l’application Airmius',
        'Kontolöschung in der Web-Version': 'Suppression du compte dans la version web',
        'Melde dich bei Airmius an und öffne Profil > Konto löschen.': 'Connectez-vous à Airmius et ouvrez Profil > Supprimer le compte.',
        'Stand: 25.05.2026': 'Mise à jour : 25.05.2026',
        'Stand: 25.05.2026. KI-, Karten-, Routing-, Ernährungs- und Trackingfunktionen sind ergänzt. Bitte lege die aktuellen AVV/DPA-Unterlagen der tatsächlich genutzten Anbieter intern ab und lasse die Texte vor Livegang rechtlich final prüfen.': 'Mise à jour : 25.05.2026. Les fonctionnalités d’IA, de cartographie, de routage, de nutrition et de suivi ont été ajoutées. Veuillez archiver en interne les documents AVV/DPA à jour des prestataires effectivement utilisés et faire valider juridiquement les textes avant la mise en production.',
        'Art. 6 Abs. 1 lit. a DSGVO und Art. 8 DSGVO: Einwilligung, insbesondere bei zustimmungspflichtigen Minderjährigen.': 'Art. 6 Abs. 1 lit. a DSGVO et Art. 8 DSGVO : consentement, notamment pour les mineurs soumis à consentement.',
    },
    'ar': {
        'Ablauf': 'العملية',
        'Analyse-, Marketing- oder Tracking-Technologien dürfen nur eingesetzt werden, wenn sie hier konkret benannt werden und eine erforderliche Einwilligung eingeholt wird.': 'لا يجوز استخدام تقنيات التحليل أو التسويق أو التتبع إلا إذا سُمّيت هنا صراحةً وتم الحصول على الموافقة المطلوبة.',
        'Anbieter sind für Beschreibung, Preisangaben, Lieferbarkeit, Leistungserbringung, Verbraucherinformationen, Gewährleistung, Steuern und sonstige rechtliche Pflichten ihrer Angebote verantwortlich, soweit Airmius nicht selbst ausdrücklich Vertragspartner ist.': 'يتحمل مقدمو الخدمات مسؤولية الوصف والأسعار والتوافر وتنفيذ الخدمة ومعلومات المستهلك والضمان والضرائب وسائر الالتزامات القانونية المتعلقة بعروضهم، ما لم تكن Airmius طرفًا متعاقدًا صراحةً.',
        'Angaben nach § 5 DDG': 'المعلومات وفقًا للمادة § 5 DDG',
        'Hinweis': 'ملاحظة',
        'Kontakt': 'التواصل',
        'Moderation': 'الإشراف',
        '2. Welche Daten Airmius verarbeitet': '2. ما البيانات التي تعالجها Airmius',
        '5. Minderjährige und Elternzustimmung': '5. القاصرون وموافقة الوالدين',
        'Bei IONOS AI Model Hub ist eine OpenAI-kompatible API vorgesehen; Airmius kann diesen Anbieter bevorzugen, wenn europäische bzw. deutsche Datenverarbeitung vertraglich und technisch passend eingerichtet ist.': 'في IONOS AI Model Hub، تُوفَّر API متوافقة مع OpenAI؛ ويمكن لـAirmius تفضيل هذا المزوّد إذا كانت معالجة البيانات الأوروبية أو الألمانية مُهيأة تعاقديًا وتقنيًا بالشكل المناسب.',
        'Bestätige deine Identität mit deinem Passwort. Wenn du Google oder Microsoft zur Anmeldung verwendest, bestätige stattdessen deine Konto-E-Mail-Adresse.': 'أكّد هويتك باستخدام كلمة المرور. إذا كنت تستخدم Google أو Microsoft لتسجيل الدخول، فأكّد بدلًا من ذلك عنوان البريد الإلكتروني لحسابك.',
        'Kein Zugriff auf dein Konto?': 'لا يمكنك الوصول إلى حسابك؟',
        'Kontolöschung in der Airmius-App': 'حذف الحساب في تطبيق Airmius',
        'Kontolöschung in der Web-Version': 'حذف الحساب في إصدار الويب',
        'Melde dich bei Airmius an und öffne Profil > Konto löschen.': 'سجّل الدخول إلى Airmius وافتح الملف الشخصي > حذف الحساب.',
        'Stand: 25.05.2026': 'آخر تحديث: 25.05.2026',
        'Stand: 25.05.2026. KI-, Karten-, Routing-, Ernährungs- und Trackingfunktionen sind ergänzt. Bitte lege die aktuellen AVV/DPA-Unterlagen der tatsächlich genutzten Anbieter intern ab und lasse die Texte vor Livegang rechtlich final prüfen.': 'آخر تحديث: 25.05.2026. تمت إضافة وظائف الذكاء الاصطناعي والخرائط وتخطيط المسارات والتغذية والتتبع. يُرجى حفظ مستندات AVV/DPA الحالية الخاصة بمقدمي الخدمات المستخدمين فعليًا داخليًا، وإخضاع النصوص للمراجعة القانونية النهائية قبل الإطلاق.',
        'Art. 6 Abs. 1 lit. a DSGVO und Art. 8 DSGVO: Einwilligung, insbesondere bei zustimmungspflichtigen Minderjährigen.': 'Art. 6 Abs. 1 lit. a DSGVO وArt. 8 DSGVO: الموافقة، ولا سيما للقاصرين الذين تتطلب حالتهم موافقة.',
    },
}


class LegalPartialValidationError(ValueError):
    def __init__(self, valid, invalid, reasons):
        self.valid = valid
        self.invalid = invalid
        super().__init__('; '.join(reasons))


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


def decode_php_single_quoted(value):
    return value[1:-1].replace(r"\'", "'").replace(r'\\', '\\')


def legal_sources():
    path = PROJECT / 'app/Http/Controllers/LegalPageController.php'
    text = path.read_text(encoding='utf-8-sig')
    result = set()

    for match in re.finditer(r"'(?:\\.|[^'\\])*'", text):
        value = re.sub(r'\s+', ' ', decode_php_single_quoted(match.group(0))).strip()
        if not value or not re.search(r'[A-Za-zÀ-ž§]', value):
            continue
        if value.startswith('/') or re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+', value):
            continue
        if re.fullmatch(r'[a-z][a-z0-9_.-]*', value):
            continue
        result.add(value)

    return sorted(result)


def english_legal_references():
    if hasattr(english_legal_references, 'cache'):
        return english_legal_references.cache
    english_progress_path = PROGRESS / 'airmius_legal_translations_en.json'
    english_legal_references.cache = json.loads(english_progress_path.read_text(encoding='utf-8-sig')) if english_progress_path.exists() else {}

    return english_legal_references.cache


def legal_model_input(items, lang):
    if lang == 'en':
        return items

    english_references = english_legal_references()

    return [english_references.get(source, source) for source in items]


def call_model(prompt, schema, legal=False, attempt=0):
    payload = json.dumps({
        'model': MODEL,
        'stream': False,
        'think': False,
        'format': schema,
        'messages': [{'role': 'user', 'content': prompt}],
        'options': {
            'temperature': (0.05 + (attempt * 0.1)) if legal else 0.1,
            'num_ctx': 4096,
        },
    }).encode()
    request = urllib.request.Request(API, data=payload, headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(request, timeout=1200 if legal else 600) as response:
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


def validate_legal_result(items, result, lang):
    identical_allowed = LEGAL_IDENTICAL_ALLOWED[lang]
    english_references = english_legal_references() if lang != 'en' else {}
    for source in items:
        target = result.get(source, '').strip()
        if lang == 'ar':
            target = target.translate(ARABIC_TO_ASCII_DIGITS)
            result[source] = target
        if source.casefold() == target.casefold() and source not in identical_allowed:
            raise ValueError(f'legal translation stayed identical for {source!r}')
        if (
            source not in identical_allowed
            and english_references.get(source, '').casefold() == target.casefold()
        ):
            raise ValueError(f'legal translation stayed English for {source!r}')
        if re.search(r'[äöüß]|\b(?:und|oder|wenn|werden|kann|nicht|deine|dein|einen|einer|eines|soweit|grundsätzlich)\b', target, flags=re.I):
            raise ValueError(f'legal translation still contains German language markers for {source!r}')
        if lang == 'ar' and source not in identical_allowed and not re.search(r'[\u0600-\u06ff]', target):
            raise ValueError(f'Arabic legal translation has no Arabic glyphs for {source!r}')
        if re.findall(r'\d+(?:[.,]\d+)*', source) != re.findall(r'\d+(?:[.,]\d+)*', target):
            raise ValueError(f'legal numbers changed for {source!r}')
        if source.count('§') != target.count('§'):
            raise ValueError(f'legal section signs changed for {source!r}')
        source_paths = re.findall(r'(?<![A-Za-zÀ-ž0-9-])/(?:[a-z0-9-]+/?)+', source, flags=re.I)
        target_paths = re.findall(r'(?<![A-Za-zÀ-ž0-9-])/(?:[a-z0-9-]+/?)+', target, flags=re.I)
        if source_paths != target_paths:
            raise ValueError(f'legal paths changed for {source!r}')
        for brand in LEGAL_BRANDS:
            if brand in source and brand not in target:
                raise ValueError(f'legal brand {brand!r} changed for {source!r}')
    return result


def legal_needs_retranslation(source, target, lang):
    if not isinstance(target, str):
        return True
    if LEGAL_FIXED_TRANSLATIONS[lang].get(source) == target:
        return False
    if source.casefold() == target.casefold() and source not in LEGAL_IDENTICAL_ALLOWED[lang]:
        return True
    if (
        lang != 'en'
        and source not in LEGAL_IDENTICAL_ALLOWED[lang]
        and english_legal_references().get(source, '').casefold() == target.casefold()
    ):
        return True
    return bool(re.search(
        r'[äöüß]|\b(?:und|oder|wenn|werden|kann|nicht|deine|dein|einen|einer|eines|soweit|grundsätzlich)\b',
        target,
        flags=re.I,
    ))


def extract_named_translation_list(value):
    candidates = []

    def visit(node):
        if not isinstance(node, dict):
            return
        for key, child in node.items():
            if (
                key in ('translated_strings', 'translations', 'translated_output', 'output')
                and isinstance(child, list)
                and all(isinstance(item, str) for item in child)
            ):
                candidates.append(child)
            elif isinstance(child, dict):
                visit(child)

    visit(value)
    unique = {json.dumps(candidate, ensure_ascii=False): candidate for candidate in candidates}

    return next(iter(unique.values())) if len(unique) == 1 else None


def ask_model(items, lang, legal=False, attempt=0):
    language = LANG_NAMES[lang]
    if legal:
        schema = {
            'type': 'object',
            'properties': {
                'translated_strings': {
                    'type': 'array',
                    'description': f'Natural {language} translations in the same order as the input.',
                    'items': {
                        'type': 'string',
                        'description': f'One faithful {language} translation; never the source text.',
                    },
                    'minItems': len(items),
                    'maxItems': len(items),
                },
            },
            'required': ['translated_strings'],
            'additionalProperties': False,
        }
        response_instruction = 'Return ONLY one valid JSON object with the required translated_strings array. Include one translated string per input item, in exactly the same order. Do not repeat the source strings.'
    else:
        schema = {
            'type': 'object',
            'properties': {source: {'type': 'string'} for source in items},
            'required': items,
            'additionalProperties': False,
        }
        response_instruction = 'Return ONLY one valid JSON object. Every exact German input string must remain its object key. Its value must be the corresponding translation.'
    content_kind = 'legal and privacy text' if legal else 'UI string'
    model_input = legal_model_input(items, lang) if legal else items
    source_language = 'English' if legal and lang != 'en' else 'German'
    source_truth_rule = '- German is the only source of truth.' if source_language == 'German' else '- Translate the reviewed English working reference; German integrity is validated separately by the pipeline.'
    reference_rule = '''
- Each input item is the reviewed English working reference derived from the authoritative German source. Translate it faithfully into the mandatory target language and never copy the English input. The pipeline validates the result against German separately.''' if legal and lang != 'en' else ''
    legal_rules = '''
- This is a faithful translation draft, not permission to invent, simplify or strengthen legal claims.
- Preserve legal citations, article/section numbers, dates, provider and product names, paths, abbreviations and defined platform terms exactly.
- Translate short headings and labels as well as long paragraphs; never copy a German heading merely because it is short.
- Keep the same level of certainty, obligation and limitation as the German source.''' if legal else ''
    if legal and lang != 'en':
        prompt = f'''Translate every string in INPUT_JSON from English into {language}.
{response_instruction}
{LANG_INSTRUCTIONS[lang]}
Preserve legal citations, article and section numbers, dates, Airmius, provider and product names, paths, abbreviations, HTML entities and defined platform terms exactly.
The following ASCII brands must remain byte-for-byte everywhere they occur; never translate or transliterate them: {', '.join(sorted(LEGAL_BRANDS))}.
Keep every digit byte-for-byte in ASCII 0-9; never replace digits with Arabic-Indic forms.
Do not copy an English sentence. Do not add explanations, markdown or an error object.
INPUT_JSON_BEGIN
{json.dumps(model_input, ensure_ascii=False)}
INPUT_JSON_END'''
    else:
        prompt = f'''You are the professional localization editor for Airmius, a platform for athletes, coaches and sports clubs.
Translate every {source_language} {content_kind} in the JSON array into {language}.
{LANG_TARGET_EMPHASIS[lang]}
This is a translation task, not a request for legal advice. A German sentence copied as output is always wrong unless it is an explicitly preserved technical token.
{response_instruction}
Rules:
{source_truth_rule}
- {LANG_INSTRUCTIONS[lang]}
{reference_rule}
{legal_rules}
- Determine meaning from the sports-platform context. "Bestehen" in a percentage means successful completion/passing, not existence.
- Preserve Airmius, URLs, emails, file paths, HTML entities, numbers, formulas, placeholders, currency codes, units, IBAN, BIC, API, SEO, GPS, GPX, CSV, DATEV, SEPA, Stripe and PayPal.
- Copy every HTML entity such as &middot; byte-for-byte. Never invent or damage an entity.
- Preserve punctuation and ellipses. Translate short fragments naturally because they can surround dynamic values.
- Before answering, silently translate every result back into German and correct it if the meaning differs.
- Never add explanations or markdown.
- For Arabic use clear Modern Standard Arabic suitable for a sports application.
Input:
{json.dumps(model_input, ensure_ascii=False)}'''
    edited = call_model(prompt, schema, legal=legal, attempt=attempt)
    if legal:
        if isinstance(edited, dict):
            named_translations = extract_named_translation_list(edited)
            if named_translations is not None:
                edited = named_translations
        if not isinstance(edited, list) or len(edited) != len(items) or not all(isinstance(value, str) for value in edited):
            preview = json.dumps(edited, ensure_ascii=False)[:500]
            raise ValueError(f'response did not preserve the legal source order and count: {preview}')
        edited = dict(zip(items, edited))
        valid = {}
        invalid = []
        reasons = []
        for source in items:
            try:
                valid.update(validate_legal_result([source], {source: edited[source]}, lang))
            except ValueError as exc:
                invalid.append(source)
                reasons.append(str(exc))
        if invalid:
            raise LegalPartialValidationError(valid, invalid, reasons)
        edited = valid
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
    if legal:
        valid = {}
        invalid = []
        reasons = []
        for source in items:
            try:
                valid.update(validate_result([source], {source: edited[source]}))
            except ValueError as exc:
                invalid.append(source)
                reasons.append(str(exc))
        if invalid:
            raise LegalPartialValidationError(valid, invalid, reasons)

        return valid
    return validate_result(items, edited)


parser = argparse.ArgumentParser()
parser.add_argument('--limit', type=int, default=0, help='Maximum new strings per language (0 means all)')
parser.add_argument('--languages', nargs='+', choices=tuple(LANG_NAMES), default=['en', 'fr', 'ar'])
parser.add_argument('--retranslate-identical', action='store_true', help='Regenerate completed values that still equal German')
parser.add_argument('--source-scope', choices=('visible', 'legal'), default='visible')
parser.add_argument('--legal-batch-size', type=int, default=4, help='Legal strings per local model request (default: 4)')
parser.add_argument('--list-sources', action='store_true', help='Print the source inventory and exit')
args = parser.parse_args()
if args.legal_batch_size < 1 or args.legal_batch_size > 30:
    parser.error('--legal-batch-size must be between 1 and 30')

sources = legal_sources() if args.source_scope == 'legal' else visible_sources()
print(f'{args.source_scope.capitalize()} source strings: {len(sources)}', flush=True)
if args.list_sources:
    print(json.dumps(sources, ensure_ascii=False, indent=2))
    raise SystemExit(0)


def translate_batch(batch, lang):
    fixed = {source: LEGAL_FIXED_TRANSLATIONS[lang][source] for source in batch if source in LEGAL_FIXED_TRANSLATIONS[lang]}
    if fixed:
        remaining = [source for source in batch if source not in fixed]
        return {**fixed, **(translate_batch(remaining, lang) if remaining else {})}

    for attempt in range(2):
        try:
            return ask_model(batch, lang, legal=args.source_scope == 'legal', attempt=attempt)
        except LegalPartialValidationError as exc:
            if exc.valid:
                print(
                    f'{lang}: kept {len(exc.valid)} valid; retrying {len(exc.invalid)} rejected legal item(s)',
                    flush=True,
                )
                return {**exc.valid, **translate_batch(exc.invalid, lang)}
            if len(batch) > 1:
                midpoint = len(batch) // 2
                print(
                    f'{lang}: all legal items rejected; splitting immediately into '
                    f'{midpoint} and {len(batch) - midpoint}',
                    flush=True,
                )
                return {
                    **translate_batch(batch[:midpoint], lang),
                    **translate_batch(batch[midpoint:], lang),
                }
            print(f'{lang}: retry {attempt + 1}: {exc}', flush=True)
            time.sleep(2 * (attempt + 1))
        except Exception as exc:
            print(f'{lang}: retry {attempt + 1}: {exc}', flush=True)
            time.sleep(2 * (attempt + 1))
    if args.source_scope == 'legal' and len(batch) > 1:
        midpoint = len(batch) // 2
        print(f'{lang}: splitting rejected legal batch into {midpoint} and {len(batch) - midpoint}', flush=True)
        return {
            **translate_batch(batch[:midpoint], lang),
            **translate_batch(batch[midpoint:], lang),
        }
    raise RuntimeError(f'Could not translate {lang} batch starting with {batch[0]!r}')


def process_language(lang, parallel_batches=1):
    catalog = json.loads((ROOT / 'lang' / f'{lang}.json').read_text(encoding='utf-8-sig'))
    automatic_catalog = json.loads((ROOT / 'lang' / 'auto' / f'{lang}.json').read_text(encoding='utf-8-sig'))
    legal_catalog_path = PROJECT / 'resources/legal' / f'{lang}.json'
    legal_catalog = json.loads(legal_catalog_path.read_text(encoding='utf-8-sig')) if legal_catalog_path.exists() else {}
    progress_prefix = 'airmius_legal_translations' if args.source_scope == 'legal' else 'airmius_local_translations'
    completed_path = PROGRESS / f'{progress_prefix}_{lang}.json'
    completed = json.loads(completed_path.read_text()) if completed_path.exists() else {}
    existing_sources = legal_catalog.get('messages', {}) if args.source_scope == 'legal' else {
        **catalog,
        **automatic_catalog.get('auto', {}),
    }
    missing = [
        s for s in sources
        if (
            s not in existing_sources
            or (
                args.source_scope == 'legal'
                and args.retranslate_identical
                and legal_needs_retranslation(s, existing_sources.get(s), lang)
            )
        ) and (
            s not in completed
            or (
                args.retranslate_identical
                and (
                    legal_needs_retranslation(s, completed[s], lang)
                    if args.source_scope == 'legal'
                    else completed[s].casefold() == s.casefold()
                )
            )
        )
    ]
    if args.limit:
        missing = missing[:args.limit]
    print(f'{lang}: {len(missing)} remaining', flush=True)
    while missing:
        batch_size = args.legal_batch_size if args.source_scope == 'legal' else (10 if args.retranslate_identical else 30)
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


if args.source_scope == 'legal':
    for language in args.languages:
        process_language(language)
else:
    parallel_languages = [lang for lang in args.languages if lang in ('en', 'fr')]
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures = [pool.submit(process_language, lang) for lang in parallel_languages]
        for future in futures:
            future.result()

    if 'ar' in args.languages:
        process_language('ar', parallel_batches=2)
