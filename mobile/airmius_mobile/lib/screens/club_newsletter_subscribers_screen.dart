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
  bool _mutating = false;

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

  Future<void> _sendNewsletter() async {
    final subject = TextEditingController();
    final body = TextEditingController();
    String? error;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_copy(context, 'sendNewsletter')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_copy(context, 'sendHint')),
                const SizedBox(height: 12),
                TextField(
                  controller: subject,
                  autofocus: true,
                  maxLength: 180,
                  decoration: InputDecoration(
                    labelText: _copy(context, 'subject'),
                  ),
                ),
                TextField(
                  controller: body,
                  minLines: 4,
                  maxLines: 8,
                  maxLength: 10000,
                  decoration: InputDecoration(
                    labelText: _copy(context, 'message'),
                    errorText: error,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_copy(context, 'cancel')),
            ),
            FilledButton.icon(
              onPressed: () {
                if (subject.text.trim().isEmpty || body.text.trim().isEmpty) {
                  setDialogState(
                    () => error = _copy(context, 'requiredFields'),
                  );
                  return;
                }
                Navigator.pop(dialogContext, true);
              },
              icon: const Icon(Icons.send_outlined),
              label: Text(_copy(context, 'sendNow')),
            ),
          ],
        ),
      ),
    );
    final subjectValue = subject.text.trim();
    final bodyValue = body.text.trim();
    if (confirmed != true || !mounted) return;
    setState(() => _mutating = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .sendClubNewsletter(
            widget.clubId,
            subject: subjectValue,
            body: bodyValue,
            idempotencyKey: 'mobile-${DateTime.now().microsecondsSinceEpoch}',
          );
      if (!mounted) return;
      final data = response['data'] as Map? ?? const {};
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _copy(
              context,
              'sentCount',
            ).replaceAll('{count}', '${data['deliveries_count'] ?? 0}'),
          ),
        ),
      );
      _reload(1);
    } catch (exception) {
      if (!mounted) return;
      final message = exception is AirmiusApiException
          ? exception.userMessage
          : _copy(context, 'sendError');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _markBounce(Map row) async {
    var bounceType = 'hard';
    final reason = TextEditingController(text: 'T26-05 App-Test');
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_copy(context, 'recordFailure')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('${row['email'] ?? ''}'),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: bounceType,
                  decoration: InputDecoration(
                    labelText: _copy(context, 'failureType'),
                  ),
                  items: const ['hard', 'soft', 'complaint', 'unknown']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(_copy(context, value)),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => bounceType = value ?? 'hard'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: reason,
                  maxLength: 1000,
                  decoration: InputDecoration(
                    labelText: _copy(context, 'failureReason'),
                  ),
                ),
                const SizedBox(height: 4),
                Text(_copy(context, 'hardBounceHint')),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_copy(context, 'cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(_copy(context, 'confirmFailure')),
            ),
          ],
        ),
      ),
    );
    final reasonValue = reason.text.trim();
    if (confirmed != true || !mounted) return;
    setState(() => _mutating = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .markClubNewsletterDeliveryBounced(
            widget.clubId,
            row['id'] as int,
            bounceType: bounceType,
            failureReason: reasonValue,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_copy(context, 'failureRecorded'))),
      );
      _reload(1);
    } catch (exception) {
      if (!mounted) return;
      final message = exception is AirmiusApiException
          ? exception.userMessage
          : _copy(context, 'failureError');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
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
          onPressed: _mutating ? null : _sendNewsletter,
          tooltip: _copy(context, 'sendNewsletter'),
          icon: const Icon(Icons.send_outlined),
        ),
        IconButton(
          onPressed: _mutating ? null : _suppressAddress,
          tooltip: _copy(context, 'suppress'),
          icon: const Icon(Icons.block_outlined),
        ),
        IconButton(
          onPressed: _mutating ? null : _reload,
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
        final deliveries = (data['recent_deliveries'] as List? ?? [])
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
              title: _copy(context, 'recentDeliveries'),
              empty: _copy(context, 'noDeliveries'),
              rows: deliveries,
              detail: (row) => [
                _copy(context, '${row['status']}'),
                _formatNewsletterDate(
                  row['bounced_at'] ?? row['sent_at'],
                  locale,
                ),
              ].where((value) => value.isNotEmpty).join(' · '),
              trailing: (row) => row['status'] == 'sent'
                  ? TextButton.icon(
                      onPressed: _mutating ? null : () => _markBounce(row),
                      icon: const Icon(Icons.report_problem_outlined),
                      label: Text(_copy(context, 'recordFailure')),
                    )
                  : null,
            ),
            const SizedBox(height: 16),
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
    this.trailing,
  });

  final String title;
  final String empty;
  final String? intro;
  final List<Map> rows;
  final String Function(Map row) detail;
  final Widget? Function(Map row)? trailing;

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
              trailing: trailing?.call(row),
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
    'sendNewsletter': [
      'Newsletter senden',
      'Send newsletter',
      'Envoyer la newsletter',
      'إرسال النشرة البريدية',
    ],
    'sendHint': [
      'Der Newsletter wird sofort an alle bestätigten, nicht gesperrten Adressen gesendet.',
      'The newsletter is sent immediately to all confirmed, unblocked addresses.',
      'La newsletter est envoyée immédiatement à toutes les adresses confirmées et non bloquées.',
      'تُرسل النشرة فورًا إلى جميع العناوين المؤكدة وغير المحظورة.',
    ],
    'subject': ['Betreff', 'Subject', 'Objet', 'الموضوع'],
    'message': ['Nachricht', 'Message', 'Message', 'الرسالة'],
    'requiredFields': [
      'Betreff und Nachricht sind erforderlich.',
      'Subject and message are required.',
      'L’objet et le message sont obligatoires.',
      'الموضوع والرسالة مطلوبان.',
    ],
    'sendNow': ['Jetzt senden', 'Send now', 'Envoyer maintenant', 'إرسال الآن'],
    'sentCount': [
      '{count} Zustellungen wurden erstellt.',
      '{count} deliveries were created.',
      '{count} envois ont été créés.',
      'تم إنشاء {count} عملية إرسال.',
    ],
    'sendError': [
      'Newsletter konnte nicht gesendet werden.',
      'Could not send the newsletter.',
      'Impossible d’envoyer la newsletter.',
      'تعذر إرسال النشرة البريدية.',
    ],
    'recentDeliveries': [
      'Letzte Zustellungen',
      'Recent deliveries',
      'Derniers envois',
      'عمليات الإرسال الأخيرة',
    ],
    'noDeliveries': [
      'Noch keine Zustellungen vorhanden.',
      'No deliveries yet.',
      'Aucun envoi pour le moment.',
      'لا توجد عمليات إرسال بعد.',
    ],
    'recordFailure': [
      'Zustellfehler erfassen',
      'Record delivery failure',
      'Enregistrer un échec de livraison',
      'تسجيل فشل التسليم',
    ],
    'failureType': [
      'Art des Zustellfehlers',
      'Delivery failure type',
      'Type d’échec de livraison',
      'نوع فشل التسليم',
    ],
    'failureReason': [
      'Fehlerbeschreibung',
      'Failure description',
      'Description de l’échec',
      'وصف الفشل',
    ],
    'hardBounceHint': [
      'Dauerhaft unzustellbar und Spam-Beschwerde sperren die Adresse automatisch. Vorübergehende Fehler tun das nicht.',
      'Hard bounces and spam complaints block the address automatically. Temporary failures do not.',
      'Les rejets permanents et plaintes pour spam bloquent automatiquement l’adresse. Les erreurs temporaires ne la bloquent pas.',
      'التعذر الدائم وشكاوى البريد المزعج يحظران العنوان تلقائيًا، أما الأخطاء المؤقتة فلا تفعل ذلك.',
    ],
    'confirmFailure': [
      'Fehler speichern',
      'Save failure',
      'Enregistrer l’échec',
      'حفظ الفشل',
    ],
    'failureRecorded': [
      'Zustellfehler wurde gespeichert.',
      'Delivery failure was recorded.',
      'L’échec de livraison a été enregistré.',
      'تم حفظ فشل التسليم.',
    ],
    'failureError': [
      'Zustellfehler konnte nicht gespeichert werden.',
      'Could not record the delivery failure.',
      'Impossible d’enregistrer l’échec de livraison.',
      'تعذر حفظ فشل التسليم.',
    ],
    'sent': ['Gesendet', 'Sent', 'Envoyé', 'تم الإرسال'],
    'bounced': ['Unzustellbar', 'Bounced', 'Non distribuable', 'تعذر التسليم'],
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
