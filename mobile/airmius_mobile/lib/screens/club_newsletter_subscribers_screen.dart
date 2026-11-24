import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class ClubNewsletterSubscribersScreen extends StatefulWidget {
  const ClubNewsletterSubscribersScreen({super.key, required this.clubId});

  final int clubId;

  static String title(BuildContext context) => _copy(context, 'title');

  @override
  State<ClubNewsletterSubscribersScreen> createState() =>
      _ClubNewsletterSubscribersScreenState();
}

class _ClubNewsletterSubscribersScreenState
    extends State<ClubNewsletterSubscribersScreen> {
  Future<Map<String, dynamic>>? _future;
  int _page = 1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() {
    final services = AirmiusServicesScope.of(context);
    return services
        .clientForSession(services.authState.session)
        .clubNewsletterSubscribers(widget.clubId, page: _page);
  }

  void _reload([int? page]) {
    setState(() {
      _page = page ?? _page;
      _future = _load();
    });
  }

  Future<void> _suppressAddress() async {
    final email = TextEditingController();
    String? error;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_copy(context, 'suppress')),
          content: TextField(
            controller: email,
            keyboardType: TextInputType.emailAddress,
            autofocus: true,
            decoration: InputDecoration(
              labelText: _copy(context, 'email'),
              errorText: error,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_copy(context, 'cancel')),
            ),
            FilledButton(
              onPressed: () {
                final value = email.text.trim();
                if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(value)) {
                  setDialogState(() => error = _copy(context, 'invalidEmail'));
                  return;
                }
                Navigator.pop(dialogContext, true);
              },
              child: Text(_copy(context, 'confirmSuppress')),
            ),
          ],
        ),
      ),
    );
    final value = email.text.trim();
    email.dispose();
    if (confirmed != true || !mounted) return;
    try {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .suppressClubNewsletterAddress(widget.clubId, email: value);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_copy(context, 'suppressed'))));
      _reload();
    } catch (exception) {
      if (!mounted) return;
      final message = exception is AirmiusApiException
          ? exception.userMessage
          : _copy(context, 'suppressError');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(ClubNewsletterSubscribersScreen.title(context)),
      actions: [
        IconButton(
          onPressed: _suppressAddress,
          tooltip: _copy(context, 'suppress'),
          icon: const Icon(Icons.block_outlined),
        ),
        IconButton(
          onPressed: _reload,
          tooltip: AirmiusScope.of(context).t('common.refresh'),
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          final error = snapshot.error;
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    error is AirmiusApiException
                        ? error.userMessage
                        : _copy(context, 'error'),
                  ),
                  TextButton(
                    onPressed: _reload,
                    child: Text(AirmiusScope.of(context).t('common.retry')),
                  ),
                ],
              ),
            ),
          );
        }
        final data = snapshot.data ?? {};
        final rows = (data['data'] as List? ?? []).whereType<Map>().toList();
        final failures = (data['failures'] as List? ?? [])
            .whereType<Map>()
            .toList();
        final suppressions = (data['suppressions'] as List? ?? [])
            .whereType<Map>()
            .toList();
        final lastPage = int.tryParse('${data['last_page']}') ?? 1;
        final locale = AirmiusScope.of(context).language.name;
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              '${_copy(context, 'title')} (${data['total'] ?? 0})',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 16),
            if (rows.isEmpty) Text(_copy(context, 'empty')),
            if (rows.isNotEmpty)
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: DataTable(
                  columns: ['name', 'email', 'date', 'status']
                      .map(
                        (key) => DataColumn(label: Text(_copy(context, key))),
                      )
                      .toList(),
                  rows: rows.map((row) {
                    final date = DateTime.tryParse('${row['created_at']}');
                    return DataRow(
                      cells: [
                        DataCell(Text(row['name']?.toString() ?? '–')),
                        DataCell(SelectableText('${row['email']}')),
                        DataCell(
                          Text(
                            date == null
                                ? '–'
                                : _formatNewsletterDate(date, locale),
                          ),
                        ),
                        DataCell(Text(_copy(context, '${row['status']}'))),
                      ],
                    );
                  }).toList(),
                ),
              ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  tooltip: _copy(context, 'previous'),
                  onPressed: _page > 1 ? () => _reload(_page - 1) : null,
                  icon: const Icon(Icons.chevron_left),
                ),
                Text('$_page / $lastPage'),
                IconButton(
                  tooltip: _copy(context, 'next'),
                  onPressed: _page < lastPage ? () => _reload(_page + 1) : null,
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
            const SizedBox(height: 24),
            _DeliverySection(
              title: _copy(context, 'failures'),
              empty: _copy(context, 'noFailures'),
              rows: failures,
              detail: (row) => [
                _copy(context, '${row['bounce_type']}'),
                _formatNewsletterDate(row['bounced_at'], locale),
                if ('${row['failure_reason'] ?? ''}'.trim().isNotEmpty)
                  '${row['failure_reason']}',
              ].where((value) => value.isNotEmpty).join(' · '),
            ),
            const SizedBox(height: 16),
            _DeliverySection(
              title: _copy(context, 'suppressions'),
              empty: _copy(context, 'noSuppressions'),
              intro: _copy(context, 'suppressionsHint'),
              rows: suppressions,
              detail: (row) => [
                _copy(context, '${row['reason']}'),
                _formatNewsletterDate(row['suppressed_at'], locale),
              ].where((value) => value.isNotEmpty).join(' · '),
            ),
          ],
        );
      },
    ),
  );
}

class _DeliverySection extends StatelessWidget {
  const _DeliverySection({
    required this.title,
    required this.empty,
    required this.rows,
    required this.detail,
    this.intro,
  });

  final String title;
  final String empty;
  final String? intro;
  final List<Map> rows;
  final String Function(Map row) detail;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          if (intro != null) ...[
            const SizedBox(height: 4),
            Text(intro!, style: Theme.of(context).textTheme.bodySmall),
          ],
          const SizedBox(height: 10),
          if (rows.isEmpty) Text(empty),
          for (final row in rows)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                title == _copy(context, 'failures')
                    ? Icons.error_outline
                    : Icons.block_outlined,
              ),
              title: SelectableText('${row['email'] ?? '–'}'),
              subtitle: Text(detail(row)),
            ),
        ],
      ),
    ),
  );
}

String _formatNewsletterDate(Object? value, String locale) {
  final parsed = value is DateTime
      ? value
      : DateTime.tryParse('${value ?? ''}');
  if (parsed == null) return '';
  final date = parsed.toLocal();
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  final calendar = locale == 'en'
      ? '$month/$day/${date.year}'
      : '$day.$month.${date.year}';
  return '$calendar $hour:$minute';
}

String _copy(BuildContext context, String key) {
  const labels = {
    'title': [
      'Newsletter-Anmeldungen',
      'Newsletter subscriptions',
      'Inscriptions à la newsletter',
      'اشتراكات النشرة البريدية',
    ],
    'name': ['Name', 'Name', 'Nom', 'الاسم'],
    'email': [
      'E-Mail-Adresse',
      'Email address',
      'Adresse e-mail',
      'البريد الإلكتروني',
    ],
    'date': [
      'Angemeldet am',
      'Signed up on',
      'Date d’inscription',
      'تاريخ الاشتراك',
    ],
    'status': ['Status', 'Status', 'Statut', 'الحالة'],
    'pending': [
      'Bestätigung ausstehend',
      'Awaiting confirmation',
      'Confirmation en attente',
      'بانتظار التأكيد',
    ],
    'confirmed': ['Bestätigt', 'Confirmed', 'Confirmé', 'مؤكد'],
    'unsubscribed': [
      'Abgemeldet',
      'Unsubscribed',
      'Désinscrit',
      'تم إلغاء الاشتراك',
    ],
    'empty': [
      'Noch keine Newsletter-Anmeldungen vorhanden.',
      'No newsletter subscriptions yet.',
      'Aucune inscription à la newsletter.',
      'لا توجد اشتراكات بعد.',
    ],
    'error': [
      'Anmeldungen konnten nicht geladen werden.',
      'Could not load subscriptions.',
      'Impossible de charger les inscriptions.',
      'تعذر تحميل الاشتراكات.',
    ],
    'previous': ['Zurück', 'Previous', 'Précédent', 'السابق'],
    'next': ['Weiter', 'Next', 'Suivant', 'التالي'],
    'suppress': [
      'Adresse sperren',
      'Block address',
      'Bloquer l’adresse',
      'حظر العنوان',
    ],
    'confirmSuppress': ['Sperren', 'Block', 'Bloquer', 'حظر'],
    'cancel': ['Abbrechen', 'Cancel', 'Annuler', 'إلغاء'],
    'invalidEmail': [
      'Gültige E-Mail-Adresse eingeben.',
      'Enter a valid email address.',
      'Saisissez une adresse e-mail valide.',
      'أدخل عنوان بريد إلكتروني صالحًا.',
    ],
    'suppressed': [
      'Adresse wurde gesperrt.',
      'Address was blocked.',
      'L’adresse a été bloquée.',
      'تم حظر العنوان.',
    ],
    'suppressError': [
      'Adresse konnte nicht gesperrt werden.',
      'Address could not be blocked.',
      'Impossible de bloquer l’adresse.',
      'تعذر حظر العنوان.',
    ],
    'failures': [
      'Zustellfehler',
      'Delivery failures',
      'Échecs de livraison',
      'أخطاء التسليم',
    ],
    'noFailures': [
      'Keine Zustellfehler bekannt.',
      'No delivery failures known.',
      'Aucun échec de livraison connu.',
      'لا توجد أخطاء تسليم معروفة.',
    ],
    'suppressions': [
      'Gesperrte Adressen',
      'Blocked addresses',
      'Adresses bloquées',
      'العناوين المحظورة',
    ],
    'suppressionsHint': [
      'Diese Adressen werden beim nächsten Versand ausgeschlossen.',
      'These addresses are excluded from the next send.',
      'Ces adresses seront exclues du prochain envoi.',
      'سيتم استبعاد هذه العناوين من الإرسال التالي.',
    ],
    'noSuppressions': [
      'Keine Adressen gesperrt.',
      'No addresses blocked.',
      'Aucune adresse bloquée.',
      'لا توجد عناوين محظورة.',
    ],
    'hard': [
      'Dauerhaft unzustellbar',
      'Hard bounce',
      'Rejet permanent',
      'تعذر دائم',
    ],
    'soft': [
      'Vorübergehend unzustellbar',
      'Soft bounce',
      'Rejet temporaire',
      'تعذر مؤقت',
    ],
    'complaint': [
      'Spam-Beschwerde',
      'Spam complaint',
      'Plainte pour spam',
      'شكوى بريد مزعج',
    ],
    'unknown': [
      'Unbekannter Fehler',
      'Unknown failure',
      'Erreur inconnue',
      'خطأ غير معروف',
    ],
    'manual': [
      'Manuell gesperrt',
      'Blocked manually',
      'Bloquée manuellement',
      'محظور يدويًا',
    ],
    'unsubscribe': [
      'Abgemeldet',
      'Unsubscribed',
      'Désinscrit',
      'تم إلغاء الاشتراك',
    ],
    'hard_bounce': [
      'Dauerhaft unzustellbar',
      'Hard bounce',
      'Rejet permanent',
      'تعذر دائم',
    ],
  };
  return labels[key]?[AirmiusScope.of(context).language.index] ?? key;
}
