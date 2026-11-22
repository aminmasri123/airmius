import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class ClubNewsletterSubscribersScreen extends StatefulWidget {
  const ClubNewsletterSubscribersScreen({super.key, required this.clubId});

  final int clubId;

  static String title(BuildContext context) => _copy(context, 'title');

  @override
  State<ClubNewsletterSubscribersScreen> createState() => _ClubNewsletterSubscribersScreenState();
}

class _ClubNewsletterSubscribersScreenState extends State<ClubNewsletterSubscribersScreen> {
  Future<Map<String, dynamic>>? _future;
  int _page = 1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session)
        .clubNewsletterSubscribers(widget.clubId, page: _page);
  }

  void _reload([int? page]) {
    setState(() {
      _page = page ?? _page;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(ClubNewsletterSubscribersScreen.title(context)),
      actions: [IconButton(onPressed: _reload, tooltip: AirmiusScope.of(context).t('common.refresh'), icon: const Icon(Icons.refresh))],
    ),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          final error = snapshot.error;
          return Center(child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text(error is AirmiusApiException ? error.userMessage : _copy(context, 'error')),
              TextButton(onPressed: _reload, child: Text(AirmiusScope.of(context).t('common.retry'))),
            ]),
          ));
        }
        final data = snapshot.data ?? {};
        final rows = (data['data'] as List? ?? []).whereType<Map>().toList();
        final lastPage = int.tryParse('${data['last_page']}') ?? 1;
        final locale = AirmiusScope.of(context).language.name;
        return ListView(padding: const EdgeInsets.all(16), children: [
          Text('${_copy(context, 'title')} (${data['total'] ?? 0})', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 16),
          if (rows.isEmpty) Text(_copy(context, 'empty')),
          if (rows.isNotEmpty) SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: ['name', 'email', 'date', 'status'].map((key) => DataColumn(label: Text(_copy(context, key)))).toList(),
              rows: rows.map((row) {
                final date = DateTime.tryParse('${row['created_at']}');
                return DataRow(cells: [
                  DataCell(Text(row['name']?.toString() ?? '–')),
                  DataCell(SelectableText('${row['email']}')),
                  DataCell(Text(date == null ? '–' : DateFormat.yMd(locale).add_Hm().format(date.toLocal()))),
                  DataCell(Text(_copy(context, '${row['status']}'))),
                ]);
              }).toList(),
            ),
          ),
          const SizedBox(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            IconButton(tooltip: _copy(context, 'previous'), onPressed: _page > 1 ? () => _reload(_page - 1) : null, icon: const Icon(Icons.chevron_left)),
            Text('$_page / $lastPage'),
            IconButton(tooltip: _copy(context, 'next'), onPressed: _page < lastPage ? () => _reload(_page + 1) : null, icon: const Icon(Icons.chevron_right)),
          ]),
        ]);
      },
    ),
  );
}

String _copy(BuildContext context, String key) {
  const labels = {
    'title': ['Newsletter-Anmeldungen', 'Newsletter subscriptions', 'Inscriptions à la newsletter', 'اشتراكات النشرة البريدية'],
    'name': ['Name', 'Name', 'Nom', 'الاسم'],
    'email': ['E-Mail-Adresse', 'Email address', 'Adresse e-mail', 'البريد الإلكتروني'],
    'date': ['Angemeldet am', 'Signed up on', 'Date d’inscription', 'تاريخ الاشتراك'],
    'status': ['Status', 'Status', 'Statut', 'الحالة'],
    'pending': ['Bestätigung ausstehend', 'Awaiting confirmation', 'Confirmation en attente', 'بانتظار التأكيد'],
    'confirmed': ['Bestätigt', 'Confirmed', 'Confirmé', 'مؤكد'],
    'unsubscribed': ['Abgemeldet', 'Unsubscribed', 'Désinscrit', 'تم إلغاء الاشتراك'],
    'empty': ['Noch keine Newsletter-Anmeldungen vorhanden.', 'No newsletter subscriptions yet.', 'Aucune inscription à la newsletter.', 'لا توجد اشتراكات بعد.'],
    'error': ['Anmeldungen konnten nicht geladen werden.', 'Could not load subscriptions.', 'Impossible de charger les inscriptions.', 'تعذر تحميل الاشتراكات.'],
    'previous': ['Zurück', 'Previous', 'Précédent', 'السابق'],
    'next': ['Weiter', 'Next', 'Suivant', 'التالي'],
  };
  return labels[key]?[AirmiusScope.of(context).language.index] ?? key;
}
