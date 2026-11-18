import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/ride_information_panel.dart';
import '../widgets/ride_pricing.dart';
import '../core/ride_pricing_copy.dart';
import 'carpool_center_screen.dart';

String _rideCopy(BuildContext context, String key) =>
    (_copy[AirmiusScope.of(context).language.code.toLowerCase()] ??
    _copy['en']!)[key]!;

class CarpoolCancelledScreen extends StatelessWidget {
  const CarpoolCancelledScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(AirmiusScope.of(context).t('rides.title'))),
    body: _RideNotice(
      title: _rideCopy(context, 'cancelledTitle'),
      message: _rideCopy(context, 'cancelled'),
      icon: Icons.event_busy_outlined,
    ),
  );
}

class _RideNotice extends StatelessWidget {
  const _RideNotice({
    required this.title,
    required this.message,
    this.icon = Icons.info_outline,
  });
  final String title, message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(20),
    children: [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(icon, size: 40),
            const SizedBox(height: 16),
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Text(message),
            const SizedBox(height: 20),
            AirmiusButton(
              label: _rideCopy(context, 'openRides'),
              icon: Icons.directions_car_outlined,
              onPressed: () => Navigator.of(context).pushReplacement(
                MaterialPageRoute(builder: (_) => const CarpoolCenterScreen()),
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

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

  Future<void> _reviewPrice() async {
    if (_busy || _ride == null) return;
    final decision = await showRidePricingReview(
      context,
      _ride!,
      changed: true,
    );
    if (!mounted || decision == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (decision == 'decline') {
        await _client.leaveRide(widget.rideId);
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const CarpoolCenterScreen()),
        );
      } else {
        final response = await _client.confirmRidePrice(
          widget.rideId,
          (ridePricing(_ride!)['version'] as num).toInt(),
        );
        if (mounted) {
          setState(
            () => _ride = Map<String, dynamic>.from(response['data'] as Map),
          );
        }
      }
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
          final error = snapshot.error;
          final status = error is AirmiusApiException ? error.statusCode : 0;
          return _RideNotice(
            title: copy(status == 404 ? 'missingTitle' : 'loadTitle'),
            message: copy(switch (status) {
              404 => 'missing',
              401 => 'signedOut',
              403 => 'forbidden',
              0 => 'offline',
              _ => 'server',
            }),
          );
        }
        final ride = _ride!;
        final requests = (ride['pending_requests'] as List? ?? []).cast<Map>();
        final members = (ride['users'] as List? ?? []).cast<Map>();
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            RideInformationPanel(ride: ride),
            if (ridePricing(ride)['requires_confirmation'] == true)
              AirmiusButton(
                label: ridePricingText(context, 'confirm'),
                icon: Icons.price_check,
                onPressed: _busy ? null : _reviewPrice,
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
                              key: ValueKey('approve-${request['id']}'),
                              label: t('rides.approve'),
                              icon: Icons.check,
                              onPressed:
                                  _busy ||
                                      request['price_confirmation_required'] ==
                                          true ||
                                      (ride['available_seats'] as num? ?? 0) <=
                                          0
                                  ? null
                                  : () => _decide(
                                      (request['id'] as num).toInt(),
                                      true,
                                    ),
                            ),
                            AirmiusButton(
                              key: ValueKey('reject-${request['id']}'),
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
                        if (request['price_confirmation_required'] == true)
                          Text(
                            ridePricingText(context, 'awaiting_confirmation'),
                          ),
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
    "cancelledTitle": "Fahrt wurde abgesagt",
    "cancelled":
        "Der Fahrer hat diese Fahrt gelöscht. Sie findet nicht statt. Du musst nichts weiter bestätigen.",
    "openRides": "Andere Fahrgemeinschaften finden",
    "missingTitle": "Fahrt nicht mehr vorhanden",
    "missing":
        "Diese Fahrt ist nicht mehr vorhanden. Sie wurde möglicherweise vom Fahrer abgesagt. Du kannst nach einer anderen Fahrgemeinschaft suchen.",
    "loadTitle": "Fahrt konnte nicht geladen werden",
    "signedOut": "Bitte melde dich erneut an, um die Fahrt zu öffnen.",
    "forbidden": "Du hast mit diesem Konto keinen Zugriff auf diese Fahrt.",
    "offline":
        "Keine Verbindung zum Server. Bitte prüfe deine Internetverbindung und versuche es erneut.",
    "server":
        "Die Fahrt konnte wegen eines Serverfehlers nicht geladen werden. Bitte versuche es später erneut.",
    'empty': 'Keine offenen Mitfahranfragen.',
    'unavailable':
        'Diese Fahrt ist nicht mehr verfügbar oder du hast keinen Zugriff. Bitte aktualisiere die Ansicht oder prüfe dein Konto.',
    'full': 'Alle Plätze sind belegt.',
  },
  'en': {
    "cancelledTitle": "Ride cancelled",
    "cancelled":
        "The driver deleted this ride. It will not take place. You do not need to confirm anything.",
    "openRides": "Find other rides",
    "missingTitle": "Ride no longer exists",
    "missing":
        "This ride no longer exists. The driver may have cancelled it. You can search for another ride.",
    "loadTitle": "Could not load ride",
    "signedOut": "Please sign in again to open this ride.",
    "forbidden": "This account does not have access to this ride.",
    "offline":
        "Cannot connect to the server. Check your internet connection and try again.",
    "server":
        "A server error prevented this ride from loading. Please try again later.",
    'empty': 'No pending ride requests.',
    'unavailable':
        'This ride is no longer available or you do not have access. Refresh or check your account.',
    'full': 'All seats are taken.',
  },
  'fr': {
    "cancelledTitle": "Trajet annulé",
    "cancelled":
        "Le conducteur a supprimé ce trajet. Il n’aura pas lieu. Aucune confirmation n’est nécessaire.",
    "openRides": "Trouver d’autres trajets",
    "missingTitle": "Ce trajet n’existe plus",
    "missing":
        "Ce trajet n’existe plus. Le conducteur l’a peut-être annulé. Vous pouvez rechercher un autre trajet.",
    "loadTitle": "Impossible de charger le trajet",
    "signedOut": "Reconnectez-vous pour ouvrir ce trajet.",
    "forbidden": "Ce compte n’a pas accès à ce trajet.",
    "offline":
        "Connexion au serveur impossible. Vérifiez votre connexion et réessayez.",
    "server":
        "Une erreur du serveur empêche le chargement. Réessayez plus tard.",
    'empty': 'Aucune demande en attente.',
    'unavailable':
        'Ce trajet est indisponible ou vous n’y avez pas accès. Actualisez ou vérifiez votre compte.',
    'full': 'Toutes les places sont occupées.',
  },
  'ar': {
    "cancelledTitle": "تم إلغاء الرحلة",
    "cancelled": "حذف السائق هذه الرحلة ولن تتم. لا تحتاج إلى تأكيد أي شيء.",
    "openRides": "البحث عن رحلات أخرى",
    "missingTitle": "الرحلة لم تعد موجودة",
    "missing":
        "هذه الرحلة لم تعد موجودة. ربما ألغاها السائق. يمكنك البحث عن رحلة أخرى.",
    "loadTitle": "تعذر تحميل الرحلة",
    "signedOut": "يرجى تسجيل الدخول مجددًا لفتح هذه الرحلة.",
    "forbidden": "هذا الحساب لا يملك صلاحية الوصول إلى هذه الرحلة.",
    "offline": "تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت وحاول مجددًا.",
    "server": "تعذر تحميل الرحلة بسبب خطأ في الخادم. حاول لاحقًا.",
    'empty': 'لا توجد طلبات ركوب معلقة.',
    'unavailable':
        'هذه الرحلة غير متاحة أو ليس لديك إذن بالوصول. حدّث الصفحة أو تحقق من حسابك.',
    'full': 'جميع المقاعد محجوزة.',
  },
};
