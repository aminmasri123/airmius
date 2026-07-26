import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LocalizationRtlFormatParitySuiteScreen extends StatefulWidget {
  const LocalizationRtlFormatParitySuiteScreen({super.key});

  @override
  State<LocalizationRtlFormatParitySuiteScreen> createState() =>
      _LocalizationRtlFormatParitySuiteScreenState();
}

class _LocalizationRtlFormatParitySuiteScreenState
    extends State<LocalizationRtlFormatParitySuiteScreen> {
  String _locale = 'DE';
  String _format = 'Datum';
  bool _rtlPreview = false;
  bool _fallbackKeys = true;
  bool _apiLocaleSync = true;

  static const _locales = ['DE', 'EN', 'FR', 'AR'];
  static const _formats = ['Datum', 'Währung', 'Einheiten', 'Fehler', 'Legal'];

  static const _items = <_LocaleItem>[
    _LocaleItem(
      format: 'Datum',
      title: 'Datum und Zeit',
      body:
          'Geburtsdatum, Eventzeiten, Zahlungsfaelligkeit, Chatzeit und Trainingslogs brauchen locale-spezifische Darstellung.',
      exampleDe: '05.06.2026 · 18:30',
      exampleEn: '06/05/2026 · 6:30 PM',
      exampleFr: '05/06/2026 · 18:30',
      exampleAr: '05-06-2026 · 18:30',
      icon: Icons.calendar_today_outlined,
      color: AirmiusColors.blue,
    ),
    _LocaleItem(
      format: 'Währung',
      title: 'Währung und Zahlungen',
      body:
          'Mitgliedsbeiträge, Rechnungen, Checkout, Banktransfer, Mahnungen und Rabatte brauchen klare lokale Formate.',
      exampleDe: '42,00 EUR',
      exampleEn: 'EUR 42.00',
      exampleFr: '42,00 EUR',
      exampleAr: '42.00 EUR',
      icon: Icons.payments_outlined,
      color: AirmiusColors.green,
    ),
    _LocaleItem(
      format: 'Einheiten',
      title: 'Sport- und Trainingseinheiten',
      body:
          'Distanz, Dauer, Gewicht, Wiederholungen, Puls, Wasser und Ernährung müssen mehrsprachig und eindeutig bleiben.',
      exampleDe: '5,2 km · 45 Min.',
      exampleEn: '5.2 km · 45 min',
      exampleFr: '5,2 km · 45 min',
      exampleAr: '5.2 كم · 45 دقيقة',
      icon: Icons.fitness_center_outlined,
      color: AirmiusColors.amber,
    ),
    _LocaleItem(
      format: 'Fehler',
      title: 'Formular- und API-Fehler',
      body:
          'Pflichtfelder, Validierung, API-Fehler, Rate Limit, Unauthorized und Uploadfehler brauchen klare lokalisierte Texte.',
      exampleDe: 'Dieses Feld ist erforderlich.',
      exampleEn: 'This field is required.',
      exampleFr: 'Ce champ est obligatoire.',
      exampleAr: 'هذا الحقل مطلوب.',
      icon: Icons.error_outline,
      color: AirmiusColors.red,
    ),
    _LocaleItem(
      format: 'Legal',
      title: 'Rechtliche Texte',
      body:
          'Datenschutz, AGB, Guardian Consent, SEPA, Widerruf, Clubregeln und Impressum brauchen Sprache, Version und Zustimmung.',
      exampleDe: 'Datenschutz akzeptieren',
      exampleEn: 'Accept privacy policy',
      exampleFr: 'Accepter la confidentialite',
      exampleAr: 'قبول سياسة الخصوصية',
      icon: Icons.gavel_outlined,
      color: AirmiusColors.blue,
    ),
  ];

  List<_LocaleItem> get _visibleItems =>
      _items.where((item) => item.format == _format).toList();

  @override
  Widget build(BuildContext context) {
    final isRtl = _locale == 'AR' || _rtlPreview;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: Directionality(
        textDirection: isRtl ? TextDirection.rtl : TextDirection.ltr,
        child: SafeArea(
          child: PageFrame(
            title: 'Localization RTL Format Parity',
            subtitle: 'Mehrsprachigkeit, RTL, Formate und Fallbacks.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _Hero(
                  locale: _locale,
                  format: _format,
                  rtlPreview: isRtl,
                  apiLocaleSync: _apiLocaleSync,
                ),
                const SizedBox(height: 16),
                _ChoicePanel(
                  title: 'Sprache',
                  items: _locales,
                  active: _locale,
                  color: airmiusAccentColor(context),
                  onChanged: (value) => setState(() {
                    _locale = value;
                    _rtlPreview = value == 'AR';
                  }),
                ),
                const SizedBox(height: 16),
                _ChoicePanel(
                  title: 'Formatbereich',
                  items: _formats,
                  active: _format,
                  color: Theme.of(context).colorScheme.secondary,
                  onChanged: (value) => setState(() => _format = value),
                ),
                const SizedBox(height: 16),
                _RulesPanel(
                  rtlPreview: _rtlPreview,
                  fallbackKeys: _fallbackKeys,
                  apiLocaleSync: _apiLocaleSync,
                  onRtl: (value) => setState(() => _rtlPreview = value),
                  onFallback: (value) => setState(() => _fallbackKeys = value),
                  onApiSync: (value) => setState(() => _apiLocaleSync = value),
                ),
                const SizedBox(height: 16),
                _LocalePreview(
                  locale: _locale,
                  format: _format,
                  rtl: isRtl,
                  fallbackKeys: _fallbackKeys,
                ),
                const SizedBox(height: 16),
                for (final item in _visibleItems) ...[
                  _LocaleItemCard(item: item, locale: _locale),
                  const SizedBox(height: 12),
                ],
                if (_visibleItems.isEmpty)
                  const EmptyPanel(
                    'Keine Locale-Muster für diesen Bereich sichtbar.',
                  ),
                const SizedBox(height: 4),
                _Checklist(
                  onOpen: () => openUiAction(
                    context,
                    title: 'Localization RTL Format Parity',
                    body:
                        'DE, EN, FR, AR, RTL, Datum, Währung, Einheiten, Fehlertexte, Legal-Texte, Fallbacks und API-Locale-Sync sind als mobile UI vorbereitet.',
                    status: 'L10n',
                    icon: Icons.translate_outlined,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.locale,
    required this.format,
    required this.rtlPreview,
    required this.apiLocaleSync,
  });

  final String locale;
  final String format;
  final bool rtlPreview;
  final bool apiLocaleSync;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('LOCALIZATION'),
          const SizedBox(height: 8),
          Text(
            'Mehrsprachigkeit ist mehr als übersetzte Buttons.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter bereitet Sprache, RTL, Datum, Währung, Einheiten, Fehlertexte, Legal-Versionen und API-Locale-Sync als echte App-Zustaende vor.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: locale, label: 'Sprache'),
              _Metric(value: format, label: 'Format'),
              _Metric(value: rtlPreview ? 'RTL' : 'LTR', label: 'Richtung'),
              _Metric(value: apiLocaleSync ? 'API' : 'Lokal', label: 'Sync'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final List<String> items;
  final String active;
  final Color color;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => ChoiceChip(
                  selected: active == item,
                  label: Text(item),
                  onSelected: (_) => onChanged(item),
                  selectedColor: color.withValues(alpha: .24),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: active == item ? color : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: active == item
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _RulesPanel extends StatelessWidget {
  const _RulesPanel({
    required this.rtlPreview,
    required this.fallbackKeys,
    required this.apiLocaleSync,
    required this.onRtl,
    required this.onFallback,
    required this.onApiSync,
  });

  final bool rtlPreview;
  final bool fallbackKeys;
  final bool apiLocaleSync;
  final ValueChanged<bool> onRtl;
  final ValueChanged<bool> onFallback;
  final ValueChanged<bool> onApiSync;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Sprachregeln',
      subtitle: 'Diese Regeln machen mehrsprachige UI später API-sicher.',
      children: [
        _SwitchLine(
          title: 'RTL-Vorschau aktivieren',
          value: rtlPreview,
          onChanged: onRtl,
        ),
        _SwitchLine(
          title: 'Fallback-Keys anzeigen',
          value: fallbackKeys,
          onChanged: onFallback,
        ),
        _SwitchLine(
          title: 'Locale mit Laravel API synchronisieren',
          value: apiLocaleSync,
          onChanged: onApiSync,
        ),
      ],
    );
  }
}

class _LocalePreview extends StatelessWidget {
  const _LocalePreview({
    required this.locale,
    required this.format,
    required this.rtl,
    required this.fallbackKeys,
  });

  final String locale;
  final String format;
  final bool rtl;
  final bool fallbackKeys;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: rtl
          ? Theme.of(context).colorScheme.tertiary
          : airmiusAccentColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                rtl
                    ? Icons.format_textdirection_r_to_l
                    : Icons.format_textdirection_l_to_r,
                color: rtl
                    ? Theme.of(context).colorScheme.tertiary
                    : airmiusAccentColor(context),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  '$locale · $format',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(
                rtl ? 'RTL' : 'LTR',
                color: rtl
                    ? Theme.of(context).colorScheme.tertiary
                    : airmiusAccentColor(context),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            _sampleFor(locale, format),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            fallbackKeys
                ? 'Fallback-Key: release.localization.format.$format'
                : 'Fallback-Key wird ausgeblendet.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.35,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: 'Locale testen',
            icon: Icons.translate_outlined,
            onPressed: () => openUiAction(
              context,
              title: 'Locale Preview',
              body:
                  'Locale $locale, Format $format, Richtung ${rtl ? 'RTL' : 'LTR'} und Fallback $fallbackKeys als mobile UI prüfen.',
              status: 'L10n',
              icon: Icons.translate_outlined,
            ),
          ),
        ],
      ),
    );
  }
}

class _LocaleItemCard extends StatelessWidget {
  const _LocaleItemCard({required this.item, required this.locale});

  final _LocaleItem item;
  final String locale;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, item.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(item.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      item.format,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(locale, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            item.body,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.42,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            item.exampleFor(locale),
            style: TextStyle(
              color: Theme.of(context).colorScheme.secondary,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Format prüfen',
                icon: item.icon,
                onPressed: () => openUiAction(
                  context,
                  title: item.title,
                  body:
                      '${item.title}: ${item.body}\n\nBeispiel $locale: ${item.exampleFor(locale)}',
                  status: item.format,
                  icon: item.icon,
                ),
              ),
              AirmiusButton(
                label: 'Fallback',
                icon: Icons.language_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: '${item.title} Fallback',
                  body:
                      'Fallback, API-Locale, Text-Key, Pluralisierung und RTL-Verhalten für ${item.title}.',
                  status: 'Fallback',
                  icon: Icons.language_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Locale-Paritaet',
      subtitle: 'Was für echte Mehrsprachigkeit vorbereitet ist.',
      children: [
        const _CheckLine(
          'DE, EN, FR und AR werden als UI-Sprachen mit Richtung und Fallbacks behandelt.',
        ),
        const _CheckLine(
          'Datum, Währung, Einheiten, Fehlermeldungen und rechtliche Texte bekommen eigene Formatregeln.',
        ),
        const _CheckLine(
          'RTL wird mit Directionality vorbereitet, nicht nur mit übersetzten Strings.',
        ),
        const _CheckLine(
          'Laravel API kann später Locale, Legal-Versionen und User-Sprache synchronisieren.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Locale-Paritaet markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _LocaleItem {
  const _LocaleItem({
    required this.format,
    required this.title,
    required this.body,
    required this.exampleDe,
    required this.exampleEn,
    required this.exampleFr,
    required this.exampleAr,
    required this.icon,
    required this.color,
  });

  final String format;
  final String title;
  final String body;
  final String exampleDe;
  final String exampleEn;
  final String exampleFr;
  final String exampleAr;
  final IconData icon;
  final Color color;

  String exampleFor(String locale) {
    if (locale == 'EN') return exampleEn;
    if (locale == 'FR') return exampleFr;
    if (locale == 'AR') return exampleAr;
    return exampleDe;
  }
}

String _sampleFor(String locale, String format) {
  if (format == 'Datum') {
    if (locale == 'EN') return 'Training starts on 06/05/2026.';
    if (locale == 'FR') return 'L entrainement commence le 05/06/2026.';
    if (locale == 'AR') return 'يبدأ التدريب في 05-06-2026.';
    return 'Das Training beginnt am 05.06.2026.';
  }
  if (format == 'Währung') {
    if (locale == 'EN') return 'Membership fee: EUR 42.00';
    if (locale == 'FR') return 'Cotisation : 42,00 EUR';
    if (locale == 'AR') return 'رسوم العضوية: 42.00 EUR';
    return 'Mitgliedsbeitrag: 42,00 EUR';
  }
  if (format == 'Einheiten') {
    if (locale == 'AR') return 'المسافة: 5.2 كم';
    return locale == 'EN' ? 'Distance: 5.2 km' : 'Distanz: 5,2 km';
  }
  if (format == 'Fehler') {
    if (locale == 'EN') return 'Please check the required fields.';
    if (locale == 'FR') return 'Veuillez verifier les champs obligatoires.';
    if (locale == 'AR') return 'يرجى التحقق من الحقول المطلوبة.';
    return 'Bitte prüfe die Pflichtfelder.';
  }
  if (locale == 'EN') return 'Privacy policy accepted.';
  if (locale == 'FR') return 'Confidentialite acceptee.';
  if (locale == 'AR') return 'تم قبول سياسة الخصوصية.';
  return 'Datenschutz akzeptiert.';
}
