import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

/// A notification opens the current server state, never cached request actions.
class CarpoolRideScreen extends StatefulWidget {
  const CarpoolRideScreen({super.key, required this.rideId});
  final int rideId;

  @override
  State<CarpoolRideScreen> createState() => _CarpoolRideScreenState();
}

class _CarpoolRideScreenState extends State<CarpoolRideScreen> {
  Future<AirmiusJson>? _future;
  AirmiusJson? _ride;
  bool _busy = false;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);
  String copy(String key) {
    final language = AirmiusScope.of(context).language.code.toLowerCase();
    return (_copy[language] ?? _copy['en']!)[key]!;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusJson> _load() async {
    final response = await _client.ride(widget.rideId);
    return _ride = Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> _decide(int userId, bool approve) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = approve
          ? await _client.approveRideRequest(widget.rideId, userId)
          : await _client.rejectRideRequest(widget.rideId, userId);
      if (!mounted) return;
      setState(
        () => _ride = Map<String, dynamic>.from(response['data'] as Map),
      );
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            t(approve ? 'rides.requestApproved' : 'rides.requestRejected'),
          ),
        ),
      );
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : t('rides.actionError'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('rides.title')),
      actions: [
        IconButton(
          icon: const Icon(Icons.refresh),
          tooltip: t('common.refresh'),
          onPressed: _busy
              ? null
              : () => setState(() {
                  _error = null;
                  _future = _load();
                }),
        ),
      ],
    ),
    body: FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError || _ride == null) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(copy('unavailable')),
            ),
          );
        }
        final ride = _ride!;
        final requests = (ride['pending_requests'] as List? ?? []).cast<Map>();
        final members = (ride['users'] as List? ?? []).cast<Map>();
        final driver = ride['driver'] as Map?;
        final departure = DateTime.tryParse(
          '${ride['departure_time']}',
        )?.toLocal();
        final date = departure == null
            ? ''
            : MaterialLocalizations.of(context).formatMediumDate(departure);
        final time = departure == null
            ? ''
            : MaterialLocalizations.of(
                context,
              ).formatTimeOfDay(TimeOfDay.fromDateTime(departure));
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${ride['from']} → ${ride['to']}',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  Text('${t('rides.driver')}: ${driver?['name'] ?? ''}'),
                  Text('$date · $time'),
                  Text(
                    t('rides.seatCount')
                        .replaceAll('{used}', '${ride['participants_count']}')
                        .replaceAll('{total}', '${ride['seats']}'),
                  ),
                  if (ride['pickup_private_label'] != null)
                    Text('${ride['pickup_private_label']}'),
                ],
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (ride['is_driver'] == true) ...[
              const SizedBox(height: 20),
              Text(
                '${t('rides.pendingRequests')} (${requests.length})',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              if (requests.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Text(copy('empty')),
                ),
              ...requests.map(
                (request) => Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          '${request['name']}',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        if ('${request['message'] ?? ''}'.isNotEmpty)
                          Text('${request['message']}'),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            AirmiusButton(
                              label: t('rides.approve'),
                              icon: Icons.check,
                              onPressed:
                                  _busy ||
                                      (ride['available_seats'] as num? ?? 0) <=
                                          0
                                  ? null
                                  : () => _decide(
                                      (request['id'] as num).toInt(),
                                      true,
                                    ),
                            ),
                            AirmiusButton(
                              label: t('rides.reject'),
                              icon: Icons.close,
                              onPressed: _busy
                                  ? null
                                  : () => _decide(
                                      (request['id'] as num).toInt(),
                                      false,
                                    ),
                            ),
                          ],
                        ),
                        if ((ride['available_seats'] as num? ?? 0) <= 0)
                          Text(copy('full')),
                      ],
                    ),
                  ),
                ),
              ),
            ],
            if (members.isNotEmpty) ...[
              const SizedBox(height: 20),
              Text(
                t('rides.participants'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              ...members.map(
                (member) => ListTile(
                  leading: const Icon(Icons.person_outline),
                  title: Text('${member['name']}'),
                ),
              ),
            ],
          ],
        );
      },
    ),
  );
}

const _copy = {
  'de': {
    'empty': 'Keine offenen Mitfahranfragen.',
    'unavailable':
        'Diese Fahrt ist nicht mehr verfügbar oder du hast keinen Zugriff. Bitte aktualisiere die Ansicht oder prüfe dein Konto.',
    'full': 'Alle Plätze sind belegt.',
  },
  'en': {
    'empty': 'No pending ride requests.',
    'unavailable':
        'This ride is no longer available or you do not have access. Refresh or check your account.',
    'full': 'All seats are taken.',
  },
  'fr': {
    'empty': 'Aucune demande en attente.',
    'unavailable':
        'Ce trajet est indisponible ou vous n’y avez pas accès. Actualisez ou vérifiez votre compte.',
    'full': 'Toutes les places sont occupées.',
  },
  'ar': {
    'empty': 'لا توجد طلبات ركوب معلقة.',
    'unavailable':
        'هذه الرحلة غير متاحة أو ليس لديك إذن بالوصول. حدّث الصفحة أو تحقق من حسابك.',
    'full': 'جميع المقاعد محجوزة.',
  },
};
