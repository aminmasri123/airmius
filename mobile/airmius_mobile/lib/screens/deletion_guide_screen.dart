import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

enum DeletionGuideKind { account, club }

class DeletionGuideScreen extends StatefulWidget {
  const DeletionGuideScreen({super.key, required this.kind});

  final DeletionGuideKind kind;

  @override
  State<DeletionGuideScreen> createState() => _DeletionGuideScreenState();
}

class _DeletionGuideScreenState extends State<DeletionGuideScreen> {
  Future<_GuideContent?>? _content;
  Future<_GuideContent?>? _bundledContent;
  String? _contentKey;

  static const _sources = <(String, String)>[
    (
      'DSGVO / GDPR',
      'https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX:32016R0679',
    ),
    ('§ 147 AO', 'https://www.gesetze-im-internet.de/ao_1977/__147.html'),
    ('§ 257 HGB', 'https://www.gesetze-im-internet.de/hgb/__257.html'),
    ('§ 14b UStG', 'https://www.gesetze-im-internet.de/ustg_1980/__14b.html'),
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _refreshContent();
  }

  @override
  void didUpdateWidget(covariant DeletionGuideScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.kind != widget.kind) _refreshContent();
  }

  void _refreshContent() {
    final locale = AirmiusScope.of(context).language.locale.languageCode;
    final origin = AirmiusServicesScope.of(
      context,
    ).environment.apiBaseUrl.trim();
    final key = '$origin|$locale|${widget.kind.name}';
    if (_contentKey != key) {
      _contentKey = key;
      _bundledContent = _loadBundledContent(
        DefaultAssetBundle.of(context),
        locale,
      );
      _content = _loadContent(origin, locale);
    }
  }

  Future<_GuideContent?> _loadBundledContent(
    AssetBundle bundle,
    String locale,
  ) async {
    try {
      final asset = 'assets/legal/deletion/${widget.kind.name}_$locale.json';
      return _parseContent(jsonDecode(await bundle.loadString(asset)), locale);
    } catch (_) {
      return null;
    }
  }

  Future<_GuideContent?> _loadContent(String origin, String locale) async {
    final base = safeExternalHttpUrl(origin, httpsOnly: false);
    if (base == null) return null;
    final path = widget.kind == DeletionGuideKind.club
        ? '/verein-loeschen'
        : '/konto-loeschen';
    final uri = base.replace(
      path: path,
      queryParameters: {'locale': locale, 'format': 'json'},
      fragment: null,
    );
    try {
      final response = await http.get(uri).timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return null;
      return _parseContent(jsonDecode(response.body), locale);
    } catch (_) {
      return null;
    }
  }

  _GuideContent? _parseContent(dynamic data, String locale) {
    if (data is! Map<String, dynamic> ||
        data['contentLocale'] != locale ||
        data['translationComplete'] != true ||
        data['title'] is! String ||
        (data['title'] as String).trim().isEmpty ||
        data['sections'] is! List ||
        (data['sections'] as List).length <
            (widget.kind == DeletionGuideKind.club ? 7 : 8)) {
      return null;
    }
    final sections = <(String, List<String>)>[];
    for (final item in data['sections'] as List) {
      if (item is! Map || item['title'] is! String || item['body'] is! List) {
        return null;
      }
      final body = (item['body'] as List).whereType<String>().toList();
      if (body.isEmpty || body.length != (item['body'] as List).length) {
        return null;
      }
      sections.add((item['title'] as String, body));
    }
    return _GuideContent(
      title: data['title'] as String,
      sections: sections,
      note: data['note'] as String?,
    );
  }

  void _retry() {
    final locale = AirmiusScope.of(context).language.locale.languageCode;
    final origin = AirmiusServicesScope.of(
      context,
    ).environment.apiBaseUrl.trim();
    setState(() {
      _content = _loadContent(origin, locale);
    });
  }

  @override
  Widget build(BuildContext context) {
    final language = AirmiusScope.of(context).language;
    final club = widget.kind == DeletionGuideKind.club;
    final fallbackTitle = switch ((language, club)) {
      (AirmiusLanguage.de, true) => 'Verein löschen',
      (AirmiusLanguage.de, false) => 'Konto löschen',
      (AirmiusLanguage.en, true) => 'Delete club',
      (AirmiusLanguage.en, false) => 'Delete account',
      (AirmiusLanguage.fr, true) => 'Supprimer un club',
      (AirmiusLanguage.fr, false) => 'Supprimer le compte',
      (AirmiusLanguage.ar, true) => 'حذف النادي',
      (AirmiusLanguage.ar, false) => 'حذف الحساب',
    };
    final otherGuideLabel = switch ((language, club)) {
      (AirmiusLanguage.de, true) => 'Informationen zur Kontolöschung',
      (AirmiusLanguage.de, false) => 'Informationen zur Vereinslöschung',
      (AirmiusLanguage.en, true) => 'About deleting an account',
      (AirmiusLanguage.en, false) => 'About deleting a club',
      (AirmiusLanguage.fr, true) => 'Informations sur la suppression du compte',
      (AirmiusLanguage.fr, false) =>
        "Informations sur la suppression d'un club",
      (AirmiusLanguage.ar, true) => 'معلومات عن حذف الحساب',
      (AirmiusLanguage.ar, false) => 'معلومات عن حذف النادي',
    };
    final contactLabel = switch (language) {
      AirmiusLanguage.de => 'Kontakt für Löschanfragen',
      AirmiusLanguage.en => 'Contact us about deletion',
      AirmiusLanguage.fr => 'Contacter le support pour une suppression',
      AirmiusLanguage.ar => 'التواصل بشأن طلب الحذف',
    };
    final webLabel = switch (language) {
      AirmiusLanguage.de => 'Aktuelle Webfassung öffnen',
      AirmiusLanguage.en => 'Open current web guide',
      AirmiusLanguage.fr => 'Ouvrir le guide sur le Web',
      AirmiusLanguage.ar => 'فتح الدليل الحالي على الويب',
    };
    final webUrl = club
        ? 'https://airmius.com/verein-loeschen'
        : 'https://airmius.com/konto-loeschen';
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text(fallbackTitle)),
      body: SafeArea(
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: FutureBuilder<_GuideContent?>(
              key: ValueKey(_contentKey),
              future: _content,
              builder: (context, snapshot) {
                return FutureBuilder<_GuideContent?>(
                  future: _bundledContent,
                  builder: (context, bundledSnapshot) {
                    final bundledContent = bundledSnapshot.data;
                    final content = snapshot.data ?? bundledContent;
                    if (content == null &&
                        bundledSnapshot.connectionState !=
                            ConnectionState.done) {
                      return Center(
                        child: Text(switch (AirmiusScope.of(context).language) {
                          AirmiusLanguage.de => 'Dokument wird geladen',
                          AirmiusLanguage.en => 'Loading document',
                          AirmiusLanguage.fr => 'Chargement du document',
                          AirmiusLanguage.ar => 'جارٍ تحميل المستند',
                        }),
                      );
                    }
                    final unavailable =
                        snapshot.connectionState == ConnectionState.done &&
                        snapshot.data == null &&
                        bundledSnapshot.connectionState == ConnectionState.done;
                    final sections =
                        content?.sections ?? <(String, List<String>)>[];
                    return ListView(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 24,
                      ),
                      children: [
                        Text(
                          content?.title ?? fallbackTitle,
                          style: Theme.of(context).textTheme.headlineMedium,
                        ),
                        const SizedBox(height: 8),
                        Text(switch (language) {
                          AirmiusLanguage.de =>
                            'Diese Information ist öffentlich. Das Lesen löst keine Löschung aus.',
                          AirmiusLanguage.en =>
                            'This information is public. Reading it does not start deletion.',
                          AirmiusLanguage.fr =>
                            'Ces informations sont publiques. Leur lecture ne lance aucune suppression.',
                          AirmiusLanguage.ar =>
                            'هذه المعلومات متاحة للجميع. قراءتها لا تبدأ عملية الحذف.',
                        }, style: TextStyle(color: scheme.onSurfaceVariant)),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: TextButton.icon(
                            onPressed: () =>
                                Navigator.of(context).pushReplacement(
                                  MaterialPageRoute<void>(
                                    builder: (_) => DeletionGuideScreen(
                                      kind: club
                                          ? DeletionGuideKind.account
                                          : DeletionGuideKind.club,
                                    ),
                                  ),
                                ),
                            icon: const Icon(Icons.description_outlined),
                            label: Text(otherGuideLabel),
                          ),
                        ),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: TextButton.icon(
                            onPressed: () => launchUrl(
                              Uri.parse(webUrl),
                              mode: LaunchMode.externalApplication,
                            ),
                            icon: const Icon(Icons.open_in_browser_outlined),
                            label: Text(webLabel),
                          ),
                        ),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: TextButton.icon(
                            onPressed: () => launchUrl(
                              Uri.parse(
                                'https://airmius.com/kontakt-und-melden',
                              ),
                              mode: LaunchMode.externalApplication,
                            ),
                            icon: const Icon(Icons.support_agent_outlined),
                            label: Text(contactLabel),
                          ),
                        ),
                        if (snapshot.connectionState == ConnectionState.waiting)
                          const Padding(
                            padding: EdgeInsets.only(top: 12),
                            child: LinearProgressIndicator(),
                          ),
                        if (unavailable) ...[
                          const SizedBox(height: 16),
                          Text(switch ((language, bundledContent != null)) {
                            (AirmiusLanguage.de, true) =>
                              'Die aktuelle Fassung ist gerade nicht erreichbar. Du siehst die vollständige, in der App gespeicherte Fassung; sie kann inzwischen veraltet sein.',
                            (AirmiusLanguage.en, true) =>
                              'The current version is unavailable. This complete copy is bundled with the app and may be outdated.',
                            (AirmiusLanguage.fr, true) =>
                              "La version actuelle est indisponible. Cette copie complète est intégrée à l'application et peut être obsolète.",
                            (AirmiusLanguage.ar, true) =>
                              'النسخة الحالية غير متاحة. هذه نسخة كاملة مرفقة بالتطبيق وقد تكون قديمة.',
                            (AirmiusLanguage.de, false) =>
                              'Die Anleitung konnte nicht geladen werden. Öffne die öffentliche Webfassung oder kontaktiere den Datenschutzkontakt.',
                            (AirmiusLanguage.en, false) =>
                              'The guide could not be loaded. Open the public web guide or contact the privacy team.',
                            (AirmiusLanguage.fr, false) =>
                              'Le guide n’a pas pu être chargé. Ouvrez la version publique sur le Web ou contactez le service de protection des données.',
                            (AirmiusLanguage.ar, false) =>
                              'تعذر تحميل الدليل. افتح النسخة العامة على الويب أو تواصل مع مسؤول حماية البيانات.',
                          }, style: TextStyle(color: scheme.error)),
                          Align(
                            alignment: AlignmentDirectional.centerStart,
                            child: TextButton.icon(
                              onPressed: _retry,
                              icon: const Icon(Icons.refresh),
                              label: Text(switch (language) {
                                AirmiusLanguage.de => 'Erneut laden',
                                AirmiusLanguage.en => 'Try again',
                                AirmiusLanguage.fr => 'Réessayer',
                                AirmiusLanguage.ar => 'إعادة المحاولة',
                              }),
                            ),
                          ),
                        ],
                        const SizedBox(height: 24),
                        for (final section in sections) ...[
                          Text(
                            section.$1,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 8),
                          for (final paragraph in section.$2) ...[
                            SelectableText(paragraph),
                            const SizedBox(height: 8),
                          ],
                          const SizedBox(height: 24),
                        ],
                        if (content != null) ...[
                          Text(switch (language) {
                            AirmiusLanguage.de => 'Rechtsquellen',
                            AirmiusLanguage.en => 'Legal sources',
                            AirmiusLanguage.fr => 'Sources juridiques',
                            AirmiusLanguage.ar => 'المصادر القانونية',
                          }, style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          for (final source in _sources)
                            Align(
                              alignment: AlignmentDirectional.centerStart,
                              child: TextButton.icon(
                                onPressed: () => launchUrl(
                                  Uri.parse(source.$2),
                                  mode: LaunchMode.externalApplication,
                                ),
                                icon: const Icon(Icons.open_in_new, size: 18),
                                label: Text(source.$1),
                              ),
                            ),
                          const SizedBox(height: 12),
                          Text(
                            content.note ??
                                switch (language) {
                                  AirmiusLanguage.de =>
                                    'Stand: 15.10.2026. Die konkrete Aufbewahrung und Auftragsverarbeitung müssen im Einzelfall rechtlich und steuerlich geprüft werden.',
                                  AirmiusLanguage.en =>
                                    'Updated 15 October 2026. Retention and processing duties require legal and tax review in each case.',
                                  AirmiusLanguage.fr =>
                                    'Mise à jour : 15 octobre 2026. Les obligations de conservation et de traitement doivent être examinées au cas par cas.',
                                  AirmiusLanguage.ar =>
                                    'آخر تحديث: 15 أكتوبر 2026. يجب مراجعة التزامات الاحتفاظ بالبيانات ومعالجتها لكل حالة على حدة.',
                                },
                            style: TextStyle(
                              color: scheme.onSurfaceVariant,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ],
                    );
                  },
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

class _GuideContent {
  const _GuideContent({required this.title, required this.sections, this.note});

  final String title;
  final List<(String, List<String>)> sections;
  final String? note;
}
