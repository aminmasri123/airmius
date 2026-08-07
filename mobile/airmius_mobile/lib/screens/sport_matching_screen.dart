import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../widgets/content_report_dialog.dart';
import '../widgets/airmius_widgets.dart';

class SportMatchingScreen extends StatefulWidget {
  const SportMatchingScreen({super.key});

  @override
  State<SportMatchingScreen> createState() => _SportMatchingScreenState();
}

class _SportMatchingScreenState extends State<SportMatchingScreen> {
  final _cityController = TextEditingController();
  final _sportController = TextEditingController();
  Future<JsonMap>? _future;
  String _mode = 'partner';
  int? _sportId;
  int _radiusKm = 25;
  String _skillFilter = 'all';
  bool _swipeView = true;
  double _swipeOffset = 0;
  final Set<int> _swipedMatchingIds = <int>{};
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String _c(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  String _matchingLocationLabel(JsonMap matching) {
    String value(dynamic raw) => raw == null ? '' : '$raw'.trim();

    final locationName = value(matching['location_name']);
    final address = value(matching['address']);
    final locality = [value(matching['postal_code']), value(matching['city'])]
        .where((part) => part.isNotEmpty)
        .join(' ');
    final country = value(matching['country_code']);
    final cityAndCountry = [locality, country]
        .where((part) => part.isNotEmpty)
        .join(', ');

    final parts = [locationName, address, cityAndCountry]
        .where((part) => part.isNotEmpty)
        .toList();

    return parts.isEmpty
        ? _c('Ort offen', 'Location open', 'Lieu à définir', 'المكان مفتوح')
        : parts.join(' · ');
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  @override
  void dispose() {
    _cityController.dispose();
    _sportController.dispose();
    super.dispose();
  }

  Future<JsonMap> _load() => _client.sportMatchings(
    mode: _mode,
    city: _cityController.text,
    sportId: _sportId,
    radiusKm: _radiusKm,
    skillLevel: _skillFilter,
  );

  void _reload({bool resetSwipe = true}) {
    setState(() {
      if (resetSwipe) {
        _swipeOffset = 0;
      }
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final globalThemeScope = AirmiusThemeModeScope.of(context);
    final isDark =
        globalThemeScope.mode == ThemeMode.dark ||
        (globalThemeScope.mode == ThemeMode.system &&
            Theme.of(context).brightness == Brightness.dark);
    final matchingTheme = isDark
        ? AirmiusTheme.dark(AirmiusThemePalette.air)
        : AirmiusTheme.light(AirmiusThemePalette.air);

    return Theme(
      data: matchingTheme,
      child: AirmiusThemeModeScope(
        mode: globalThemeScope.mode,
        setMode: globalThemeScope.setMode,
        palette: AirmiusThemePalette.air,
        setPalette: globalThemeScope.setPalette,
        child: Scaffold(
          appBar: AppBar(
            title: Text(
              _c(
                'Sport-Matching',
                'Sport matching',
                'Matching sportif',
                'مطابقة رياضية',
              ),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            actions: [
              IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
            ],
          ),
          body: FutureBuilder<JsonMap>(
            future: _future,
            builder: (context, snapshot) {
              final data = snapshot.data;
              final meta = data?['meta'] is JsonMap
                  ? data!['meta'] as JsonMap
                  : const <String, dynamic>{};
              final sports = _maps(meta['sports']);
              return PageFrame(
                title: _c(
                  'Gemeinsam Sport machen',
                  'Play sports together',
                  'Faire du sport ensemble',
                  'مارس الرياضة مع الآخرين',
                ),
                subtitle: _c(
                  'Finde Personen oder ein gegnerisches Team – unabhängig von der Sportart.',
                  'Find people or an opposing team, for any sport.',
                  'Trouvez des partenaires ou une équipe adverse, pour tous les sports.',
                  'ابحث عن شركاء أو فريق منافس لأي رياضة.',
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 16),
                    _topActions(sports),
                    const SizedBox(height: 16),
                    if (snapshot.connectionState == ConnectionState.waiting)
                      const Center(child: CircularProgressIndicator())
                    else if (snapshot.hasError)
                      _error(snapshot.error)
                    else
                      _matchingList(data ?? const {}, meta),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  String _skillLabel(String level) => switch (level) {
    'beginner' => _c('Anfänger', 'Beginner', 'Débutant', 'مبتدئ'),
    'recreational' => _c('Freizeit', 'Recreational', 'Loisir', 'ترفيهي'),
    'advanced' => _c('Fortgeschritten', 'Advanced', 'Avancé', 'متقدم'),
    'competitive' => _c('Wettkampf', 'Competitive', 'Compétition', 'تنافسي'),
    _ => _c('Alle Niveaus', 'All levels', 'Tous les niveaux', 'كل المستويات'),
  };

  String _applicationStatusLabel(Object? status) => switch ('$status') {
    'pending' => _c(
      'Anfrage ausstehend',
      'Request pending',
      'Demande en attente',
      'الطلب قيد الانتظار',
    ),
    'accepted' => _c(
      'Anfrage angenommen',
      'Request accepted',
      'Demande acceptée',
      'تم قبول الطلب',
    ),
    'declined' => _c(
      'Anfrage abgelehnt',
      'Request declined',
      'Demande refusée',
      'تم رفض الطلب',
    ),
    _ => '$status',
  };

  Widget _matchingList(JsonMap response, JsonMap meta) {
    final matchings = _maps(response['data']);
    final teams = _maps(meta['teams']);
    final discoverable = matchings
        .where(
          (matching) =>
              matching['mine'] != true &&
              matching['my_application'] == null &&
              !_swipedMatchingIds.contains(_int(matching['id'])),
        )
        .toList();
    if (_swipeView) return _swipeDeck(discoverable, teams);
    if (matchings.isEmpty) {
      return AirmiusPanel(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Text(
            _c(
              'Noch keine passenden Suchen. Erstelle die erste.',
              'No matching searches yet. Create the first one.',
              'Aucune recherche. Créez la première.',
              'لا توجد طلبات مطابقة بعد. أنشئ الأول.',
            ),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    return Column(
      children: matchings
          .map(
            (matching) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _card(matching, teams),
            ),
          )
          .toList(),
    );
  }

  Widget _swipeDeck(List<JsonMap> matchings, List<JsonMap> teams) {
    if (matchings.isEmpty) {
      return AirmiusPanel(
        children: [
          Icon(
            Icons.check_circle_outline,
            size: 48,
            color: airmiusAccentColor(context),
          ),
          const SizedBox(height: 12),
          Text(
            _c(
              'Du bist auf dem neuesten Stand.',
              'You are all caught up.',
              'Vous êtes à jour.',
              'لقد اطلعت على كل العروض.',
            ),
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            _c(
              'Ändere Sportart, Ort oder Umkreis, um neue Angebote zu entdecken.',
              'Change the sport, city or radius to discover more offers.',
              'Modifiez le sport, la ville ou le rayon pour découvrir d’autres offres.',
              'غيّر الرياضة أو المدينة أو النطاق لاكتشاف عروض أخرى.',
            ),
            textAlign: TextAlign.center,
          ),
          if (_swipedMatchingIds.isNotEmpty) ...[
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: () => setState(() {
                _swipedMatchingIds.clear();
              }),
              icon: const Icon(Icons.undo),
              label: Text(
                _c(
                  'Übersprungene wieder anzeigen',
                  'Show skipped again',
                  'Afficher à nouveau les offres ignorées',
                  'إظهار العروض التي تم تخطيها',
                ),
              ),
            ),
          ],
        ],
      );
    }

    final current = matchings.first;
    final next = matchings.length > 1 ? matchings[1] : null;
    return Column(
      children: [
        Text(
          _c(
            'Wische nach links oder rechts',
            'Swipe left or right',
            'Glissez à gauche ou à droite',
            'اسحب يمينًا أو يسارًا',
          ),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 555,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              if (next != null)
                Positioned.fill(
                  top: 12,
                  left: 8,
                  right: 8,
                  child: Transform.scale(
                    scale: 0.965,
                    child: Opacity(
                      opacity: 0.72,
                      child: _swipeCard(next, teams, isBackground: true),
                    ),
                  ),
                ),
              Positioned.fill(
                child: GestureDetector(
                  onPanUpdate: (details) => setState(() {
                    _swipeOffset += details.delta.dx;
                  }),
                  onPanEnd: (_) {
                    if (_swipeOffset.abs() >= 110) {
                      _resolveSwipe(current, teams, _swipeOffset > 0);
                    } else {
                      setState(() => _swipeOffset = 0);
                    }
                  },
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 220),
                    curve: Curves.easeOutCubic,
                    transform: Matrix4.identity()
                      ..translateByDouble(_swipeOffset, 0, 0, 1)
                      ..rotateZ(_swipeOffset / 700),
                    transformAlignment: Alignment.center,
                    child: Stack(
                      children: [
                        _swipeCard(current, teams),
                        if (_swipeOffset > 24)
                          Positioned(
                            top: 24,
                            left: 20,
                            child: _swipeStamp(
                              _c(
                                'INTERESSE',
                                'INTERESTED',
                                'INTÉRESSÉ',
                                'مهتم',
                              ),
                              Colors.green,
                              -0.12,
                            ),
                          ),
                        if (_swipeOffset < -24)
                          Positioned(
                            top: 24,
                            right: 20,
                            child: _swipeStamp(
                              _c(
                                'NICHT JETZT',
                                'NOT NOW',
                                'PAS MAINTENANT',
                                'ليس الآن',
                              ),
                              Colors.red,
                              0.12,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _swipeActionButton(
              icon: Icons.undo,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              onPressed: _swipedMatchingIds.isEmpty || _busy
                  ? null
                  : _undoSwipe,
              tooltip: _c('Zurück', 'Undo', 'Annuler', 'تراجع'),
              size: 52,
            ),
            const SizedBox(width: 12),
            _swipeActionButton(
              icon: Icons.close,
              color: Colors.red,
              onPressed: _busy
                  ? null
                  : () => _resolveSwipe(current, teams, false),
              tooltip: _c(
                'Nicht jetzt',
                'Not now',
                'Pas maintenant',
                'ليس الآن',
              ),
              size: 64,
            ),
            const SizedBox(width: 12),
            _swipeActionButton(
              icon: Icons.check,
              color: Colors.green,
              onPressed: _busy
                  ? null
                  : () => _resolveSwipe(current, teams, true),
              tooltip: _c(
                'Interesse senden',
                'Send interest',
                'Envoyer un intérêt',
                'إرسال اهتمام',
              ),
              size: 72,
            ),
            const SizedBox(width: 12),
            _swipeActionButton(
              icon: Icons.info_outline,
              color: airmiusAccentColor(context),
              onPressed: () => _showSwipeDetails(current),
              tooltip: _c('Details', 'Details', 'Détails', 'التفاصيل'),
              size: 52,
            ),
          ],
        ),
      ],
    );
  }

  Widget _swipeCard(
    JsonMap matching,
    List<JsonMap> teams, {
    bool isBackground = false,
  }) {
    final sport = matching['sport'] is JsonMap
        ? matching['sport'] as JsonMap
        : const <String, dynamic>{};
    final team = matching['team'] is JsonMap
        ? matching['team'] as JsonMap
        : null;
    final startsAt = DateTime.tryParse('${matching['starts_at'] ?? ''}');
    final owner = matching['owner'] is JsonMap
        ? matching['owner'] as JsonMap
        : const <String, dynamic>{};
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      children: [
        Container(
          height: 170,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                airmiusAccentColor(context),
                airmiusAccentColor(context).withValues(alpha: 0.46),
                Colors.teal.withValues(alpha: 0.72),
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          ),
          child: Stack(
            children: [
              Positioned(
                right: -18,
                top: -38,
                child: Container(
                  width: 150,
                  height: 150,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.16),
                      width: 18,
                    ),
                  ),
                ),
              ),
              Center(
                child: Icon(
                  _sportIcon(sport),
                  size: 74,
                  color: Colors.white.withValues(alpha: 0.94),
                ),
              ),
              Positioned(
                left: 16,
                top: 16,
                child: _swipeBadge(
                  matching['mode'] == 'team'
                      ? _c(
                          'Team-Herausforderung',
                          'Team challenge',
                          'Défi d’équipe',
                          'تحدي فريق',
                        )
                      : _c(
                          'Sportpartner',
                          'Sport partner',
                          'Partenaire sportif',
                          'شريك رياضي',
                        ),
                ),
              ),
              Positioned(
                right: 16,
                top: 16,
                child: _swipeBadge(
                  '${sport['name'] ?? _c('Sport', 'Sport', 'Sport', 'رياضة')}',
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                '${matching['title'] ?? ''}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if ('${matching['description'] ?? ''}'.trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  '${matching['description']}',
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              const SizedBox(height: 12),
              _swipeDetail(
                Icons.calendar_today_outlined,
                startsAt == null
                    ? _c(
                        'Termin offen',
                        'Date open',
                        'Date à définir',
                        'الموعد مفتوح',
                      )
                    : _formatMatchingDateTime(context, startsAt.toLocal()),
              ),
              _swipeDetail(
                Icons.location_on_outlined,
                _matchingLocationLabel(matching),
              ),
              _swipeDetail(
                Icons.groups_outlined,
                matching['mode'] == 'team'
                    ? '${matching['team_size']} vs. ${matching['team_size']}'
                    : '${matching['participants_needed']} ${_c('gesucht', 'wanted', 'recherchés', 'مطلوب')}',
              ),
              _swipeDetail(
                Icons.speed_outlined,
                '${matching['skill_level'] ?? 'all'} · ${matching['radius_km'] ?? 25} km',
              ),
              const Divider(height: 22),
              Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: airmiusAccentColor(
                      context,
                    ).withValues(alpha: 0.14),
                    child: Text(
                      _initials('${owner['name'] ?? ''}'),
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      '${owner['name'] ?? _c('Sport-Community', 'Sports community', 'Communauté sportive', 'مجتمع الرياضة')}${team == null ? '' : ' · ${team['name']}'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
              if (!isBackground && matching['mode'] == 'team') ...[
                const SizedBox(height: 8),
                Text(
                  _c(
                    'Dein Team wählst du beim Annehmen.',
                    'Choose your team when accepting.',
                    'Choisissez votre équipe en acceptant.',
                    'اختر فريقك عند القبول.',
                  ),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _swipeDetail(IconData icon, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(
      children: [
        Icon(icon, size: 18, color: airmiusAccentColor(context)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(value, maxLines: 1, overflow: TextOverflow.ellipsis),
        ),
      ],
    ),
  );

  Widget _swipeBadge(String label) => DecoratedBox(
    decoration: BoxDecoration(
      color: Colors.black.withValues(alpha: 0.2),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    ),
  );

  Widget _swipeStamp(String label, Color color, double angle) =>
      Transform.rotate(
        angle: angle,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            border: Border.all(color: color, width: 2),
            borderRadius: BorderRadius.circular(8),
            color: color.withValues(alpha: 0.12),
          ),
          child: Text(
            label,
            style: TextStyle(color: color, fontWeight: FontWeight.w900),
          ),
        ),
      );

  Widget _swipeActionButton({
    required IconData icon,
    required Color color,
    required VoidCallback? onPressed,
    required String tooltip,
    required double size,
  }) => Tooltip(
    message: tooltip,
    child: SizedBox(
      width: size,
      height: size,
      child: IconButton.filled(
        onPressed: onPressed,
        icon: Icon(icon, color: color, size: size * 0.42),
        style: IconButton.styleFrom(
          backgroundColor: color.withValues(alpha: 0.12),
          side: BorderSide(color: color.withValues(alpha: 0.42)),
        ),
      ),
    ),
  );

  Future<void> _resolveSwipe(
    JsonMap matching,
    List<JsonMap> teams,
    bool interested,
  ) async {
    if (_busy) return;
    int? teamId;
    if (interested && matching['mode'] == 'team') {
      teamId = await _chooseTeam(teams);
      if (teamId == null) return;
    }

    setState(() => _swipeOffset = interested ? 520 : -520);
    await Future<void>.delayed(const Duration(milliseconds: 220));
    if (!mounted) return;
    final matchingId = _int(matching['id']);
    setState(() {
      _swipedMatchingIds.add(matchingId);
      _swipeOffset = 0;
    });
    if (interested) {
      await _run(
        () => _client.applyForSportMatching(matchingId, teamId: teamId),
      );
    } else {
      await _run(() => _client.dismissSportMatching(matchingId));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _c(
              'Angebot übersprungen.',
              'Offer skipped.',
              'Offre ignorée.',
              'تم تخطي العرض.',
            ),
          ),
        ),
      );
    }
  }

  Future<void> _undoSwipe() async {
    if (_busy || _swipedMatchingIds.isEmpty) return;
    final matchingId = _swipedMatchingIds.last;
    setState(() => _swipedMatchingIds.remove(matchingId));
    await _run(
      () => _client.dismissSportMatching(matchingId, dismissed: false),
    );
  }

  Future<int?> _chooseTeam(List<JsonMap> teams) => showModalBottomSheet<int>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: teams.isEmpty
          ? Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                _c(
                  'Du bist keinem Team zugeordnet.',
                  'You are not assigned to a team.',
                  'Vous n’êtes affecté à aucune équipe.',
                  'لست منضمًا إلى أي فريق.',
                ),
                textAlign: TextAlign.center,
              ),
            )
          : ListView(
              shrinkWrap: true,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 6, 20, 12),
                  child: Text(
                    _c(
                      'Team auswählen',
                      'Choose a team',
                      'Choisir une équipe',
                      'اختر فريقًا',
                    ),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                ...teams.map(
                  (team) => ListTile(
                    leading: const Icon(Icons.groups_outlined),
                    title: Text('${team['name']}'),
                    onTap: () => Navigator.pop(context, _int(team['id'])),
                  ),
                ),
              ],
            ),
    ),
  );

  void _showSwipeDetails(JsonMap matching) {
    final owner = matching['owner'] is JsonMap
        ? matching['owner'] as JsonMap
        : const <String, dynamic>{};
    final ownerId = _int(owner['id']);
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                '${matching['title'] ?? ''}',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '${matching['description'] ?? _c('Keine zusätzliche Beschreibung.', 'No additional description.', 'Aucune description supplémentaire.', 'لا يوجد وصف إضافي.')}',
              ),
              const SizedBox(height: 12),
              Text(
                _matchingLocationLabel(matching),
              ),
              if (ownerId > 0) ...[
                const SizedBox(height: 16),
                OutlinedButton.icon(
                  onPressed: () {
                    Navigator.pop(context);
                    _reportMatchingOwner(ownerId);
                  },
                  icon: const Icon(Icons.flag_outlined),
                  label: Text(
                    _c(
                      'Angebot melden',
                      'Report offer',
                      'Signaler l’offre',
                      'الإبلاغ عن العرض',
                    ),
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: () {
                    Navigator.pop(context);
                    _blockMatchingOwner(ownerId);
                  },
                  icon: const Icon(Icons.block_outlined),
                  label: Text(
                    _c(
                      'Person blockieren',
                      'Block person',
                      'Bloquer la personne',
                      'حظر الشخص',
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _reportMatchingOwner(int ownerId) async {
    final report = await showContentReportDialog(
      context,
      title: _c(
        'Sport-Angebot melden',
        'Report sport offer',
        'Signaler l’offre sportive',
        'الإبلاغ عن العرض الرياضي',
      ),
    );
    if (report == null || !mounted) return;
    await _run(
      () => _client.reportContent(
        type: 'user',
        id: ownerId,
        reason: report.reason,
        details: report.details,
      ),
    );
  }

  Future<void> _blockMatchingOwner(int ownerId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          _c(
            'Person blockieren?',
            'Block this person?',
            'Bloquer cette personne ?',
            'حظر هذا الشخص؟',
          ),
        ),
        content: Text(
          _c(
            'Weitere Angebote dieser Person werden ausgeblendet.',
            'Further offers from this person will be hidden.',
            'Les prochaines offres de cette personne seront masquées.',
            'سيتم إخفاء العروض القادمة من هذا الشخص.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_c('Abbrechen', 'Cancel', 'Annuler', 'إلغاء')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(_c('Blockieren', 'Block', 'Bloquer', 'حظر')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() => _client.blockUser(ownerId));
  }

  String _initials(String name) => name
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2)
      .map((part) => part[0])
      .join()
      .toUpperCase();

  IconData _sportIcon(JsonMap sport) {
    final value = '${sport['slug'] ?? ''} ${sport['name'] ?? ''}'.toLowerCase();
    if (value.contains('football') ||
        value.contains('fußball') ||
        value.contains('soccer'))
      return Icons.sports_soccer;
    if (value.contains('basket')) return Icons.sports_basketball;
    if (value.contains('tennis') || value.contains('padel'))
      return Icons.sports_tennis;
    if (value.contains('swim') || value.contains('schwimm'))
      return Icons.pool_outlined;
    if (value.contains('bike') ||
        value.contains('rad') ||
        value.contains('cycling'))
      return Icons.directions_bike;
    if (value.contains('hike') || value.contains('wandern'))
      return Icons.hiking;
    return Icons.directions_run;
  }

  Widget _card(JsonMap matching, List<JsonMap> teams) {
    final sport = matching['sport'] is JsonMap
        ? matching['sport'] as JsonMap
        : const <String, dynamic>{};
    final team = matching['team'] is JsonMap
        ? matching['team'] as JsonMap
        : null;
    final mine = matching['mine'] == true;
    final applications = _maps(matching['applications']);
    final startsAt = DateTime.tryParse('${matching['starts_at'] ?? ''}');
    return AirmiusPanel(
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            Chip(label: Text('${sport['name'] ?? ''}')),
            Chip(
              label: Text(
                matching['mode'] == 'team'
                    ? '${matching['team_size']} vs. ${matching['team_size']}'
                    : '${matching['participants_needed']} ${_c('gesucht', 'wanted', 'recherchés', 'مطلوب')}',
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Text(
          '${matching['title'] ?? ''}',
          style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
        ),
        if ('${matching['description'] ?? ''}'.trim().isNotEmpty) ...[
          const SizedBox(height: 6),
          Text('${matching['description']}'),
        ],
        const SizedBox(height: 10),
        Text('📍 ${_matchingLocationLabel(matching)}'),
        Text(
          '🗓 ${startsAt == null ? '' : _formatMatchingDateTime(context, startsAt.toLocal())}',
        ),
        Text('🎯 ${matching['skill_level']} · ${matching['radius_km']} km'),
        if (team != null) Text('👥 ${team['name']}'),
        if (mine && applications.isNotEmpty) ...[
          const Divider(height: 24),
          ...applications.map(
            (application) => _applicationRow(matching, application),
          ),
        ] else if (!mine && matching['my_application'] == null) ...[
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _busy ? null : () => _apply(matching, teams),
            icon: const Icon(Icons.waving_hand_outlined),
            label: Text(
              _c(
                'Interesse senden',
                'Send interest',
                'Participer',
                'إرسال اهتمام',
              ),
            ),
          ),
        ] else if (!mine) ...[
          const SizedBox(height: 10),
          Text(
            _applicationStatusLabel(matching['my_application']),
            style: TextStyle(
              color: airmiusAccentColor(context),
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ],
    );
  }

  Widget _applicationRow(JsonMap matching, JsonMap application) {
    final user = application['user'] is JsonMap
        ? application['user'] as JsonMap
        : const <String, dynamic>{};
    final team = application['team'] is JsonMap
        ? application['team'] as JsonMap
        : null;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text('${team?['name'] ?? user['name'] ?? ''}'),
      subtitle: Text('${application['message'] ?? ''}'),
      trailing: application['status'] == 'pending'
          ? Wrap(
              children: [
                IconButton(
                  onPressed: () => _decide(matching, application, 'accepted'),
                  icon: const Icon(Icons.check, color: Colors.green),
                ),
                IconButton(
                  onPressed: () => _decide(matching, application, 'declined'),
                  icon: const Icon(Icons.close, color: Colors.red),
                ),
              ],
            )
          : Text(
              _applicationStatusLabel(application['status']),
              style: TextStyle(
                color: application['status'] == 'accepted'
                    ? Colors.green
                    : airmiusMutedColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
    );
  }

  Widget _error(Object? error) {
    final message = error is AirmiusApiException
        ? error.statusCode >= 500
              ? _c(
                  'Sport-Matching konnte gerade nicht geladen werden. Bitte versuche es erneut.',
                  'Sport matching could not be loaded right now. Please try again.',
                  'Le matching sportif ne peut pas être chargé actuellement. Réessayez.',
                  'تعذر تحميل المطابقة الرياضية حاليًا. يرجى المحاولة مرة أخرى.',
                )
              : error.userMessage
        : _c(
            'Bitte prüfe deine Verbindung und versuche es erneut.',
            'Check your connection and try again.',
            'Vérifiez votre connexion puis réessayez.',
            'تحقق من اتصالك ثم حاول مرة أخرى.',
          );
    return AirmiusPanel(
      children: [
        Icon(
          Icons.cloud_off_outlined,
          color: Theme.of(context).colorScheme.error,
          size: 34,
        ),
        const SizedBox(height: 10),
        Text(
          message,
          style: const TextStyle(fontWeight: FontWeight.w800, height: 1.35),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _reload,
          icon: const Icon(Icons.refresh),
          label: Text(
            _c('Erneut versuchen', 'Try again', 'Réessayer', 'إعادة المحاولة'),
          ),
        ),
      ],
    );
  }

  Future<void> _apply(JsonMap matching, List<JsonMap> teams) async {
    int? teamId;
    if (matching['mode'] == 'team') {
      teamId = await showModalBottomSheet<int>(
        context: context,
        builder: (context) => SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: teams
                .map(
                  (team) => ListTile(
                    title: Text('${team['name']}'),
                    onTap: () => Navigator.pop(context, _int(team['id'])),
                  ),
                )
                .toList(),
          ),
        ),
      );
      if (teamId == null) return;
    }
    await _run(
      () => _client.applyForSportMatching(_int(matching['id']), teamId: teamId),
    );
  }

  Future<void> _decide(JsonMap matching, JsonMap application, String status) =>
      _run(
        () => _client.decideSportMatchingApplication(
          _int(matching['id']),
          _int(application['id']),
          status,
        ),
      );

  Future<void> _run(Future<JsonMap> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) _reload();
    } catch (error) {
      if (mounted) {
        final message = error is AirmiusApiException
            ? error.userMessage
            : _c(
                'Die Aktion konnte nicht abgeschlossen werden.',
                'The action could not be completed.',
                'L’action n’a pas pu être terminée.',
                'تعذر إكمال الإجراء.',
              );
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openCreate() async {
    JsonMap? response;
    try {
      response = await _future;
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _c(
                'Sportarten und Teams konnten nicht geladen werden. Bitte versuche es erneut.',
                'Sports and teams could not be loaded. Please try again.',
                'Les sports et les équipes n’ont pas pu être chargés. Réessayez.',
                'تعذر تحميل الرياضات والفرق. يرجى المحاولة مرة أخرى.',
              ),
            ),
          ),
        );
      }
      return;
    }
    if (!mounted || response == null) return;
    final meta = response['meta'] is JsonMap
        ? response['meta'] as JsonMap
        : const <String, dynamic>{};
    final payload = await showModalBottomSheet<JsonMap>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _CreateMatchingSheet(
        mode: _mode,
        sports: _maps(meta['sports']),
        teams: _maps(meta['teams']),
        defaultCountryCode: AirmiusServicesScope.of(
          context,
        ).authState.user?.country,
      ),
    );
    if (payload != null) {
      await _run(() => _client.createSportMatching(payload));
    }
  }

  Future<void> _openConfiguration(List<JsonMap> sports) async {
    final cityController = TextEditingController(text: _cityController.text);
    final sportController = TextEditingController(text: _sportController.text);
    var mode = _mode;
    var swipeView = _swipeView;
    var sportId = _sportId;
    var radiusKm = _radiusKm;
    var skillFilter = _skillFilter;

    final filters = await showModalBottomSheet<_SportMatchingFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          return FractionallySizedBox(
            heightFactor: 0.9,
            child: Material(
              color: airmiusSurfaceColor(context),
              child: Padding(
                padding: EdgeInsets.fromLTRB(
                  20,
                  4,
                  20,
                  20 + MediaQuery.viewInsetsOf(context).bottom,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            _c(
                              'Suche konfigurieren',
                              'Configure search',
                              'Configurer la recherche',
                              'تهيئة البحث',
                            ),
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        IconButton(
                          tooltip: _c('Schließen', 'Close', 'Fermer', 'إغلاق'),
                          onPressed: () => Navigator.pop(sheetContext),
                          icon: const Icon(Icons.close),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _c(
                        'Verfeinere deine Ergebnisse. Die Suche wird erst nach dem Übernehmen aktualisiert.',
                        'Refine your results. The search updates only after you apply the changes.',
                        'Affinez vos résultats. La recherche est mise à jour après validation.',
                        'حسّن نتائجك. يتم تحديث البحث بعد تطبيق التغييرات فقط.',
                      ),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 18),
                    Expanded(
                      child: ListView(
                        padding: EdgeInsets.zero,
                        children: [
                          _configurationSectionLabel(
                            context,
                            _c(
                              'Suchmodus',
                              'Search mode',
                              'Mode de recherche',
                              'وضع البحث',
                            ),
                            Icons.swap_horiz,
                          ),
                          SegmentedButton<String>(
                            segments: [
                              ButtonSegment(
                                value: 'partner',
                                icon: const Icon(Icons.directions_run),
                                label: Text(
                                  _c(
                                    'Sportpartner',
                                    'Partners',
                                    'Partenaires',
                                    'شركاء',
                                  ),
                                ),
                              ),
                              ButtonSegment(
                                value: 'team',
                                icon: const Icon(Icons.groups_2_outlined),
                                label: Text(
                                  _c('Teamgegner', 'Teams', 'Équipes', 'فرق'),
                                ),
                              ),
                            ],
                            selected: {mode},
                            onSelectionChanged: (values) =>
                                setSheetState(() => mode = values.first),
                          ),
                          const SizedBox(height: 18),
                          _configurationSectionLabel(
                            context,
                            _c('Ansicht', 'View', 'Affichage', 'العرض'),
                            Icons.dashboard_outlined,
                          ),
                          SegmentedButton<bool>(
                            segments: [
                              ButtonSegment(
                                value: true,
                                icon: const Icon(Icons.bolt_outlined),
                                label: Text(
                                  _c(
                                    'Entdecken',
                                    'Discover',
                                    'Découvrir',
                                    'اكتشف',
                                  ),
                                ),
                              ),
                              ButtonSegment(
                                value: false,
                                icon: const Icon(Icons.view_list_outlined),
                                label: Text(
                                  _c('Liste', 'List', 'Liste', 'القائمة'),
                                ),
                              ),
                            ],
                            selected: {swipeView},
                            onSelectionChanged: (values) =>
                                setSheetState(() => swipeView = values.first),
                          ),
                          const SizedBox(height: 18),
                          TextField(
                            controller: cityController,
                            textCapitalization: TextCapitalization.words,
                            textInputAction: TextInputAction.search,
                            decoration: InputDecoration(
                              prefixIcon: const Icon(
                                Icons.location_on_outlined,
                              ),
                              labelText: _c(
                                'Ort',
                                'Location',
                                'Lieu',
                                'الموقع',
                              ),
                              hintText: _c(
                                'z. B. Kenitra',
                                'e.g. Kenitra',
                                'ex. Kénitra',
                                'مثال: القنيطرة',
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          if (sports.isNotEmpty)
                            _MatchingSportAutocomplete(
                              controller: sportController,
                              sports: sports,
                              hintText: _c(
                                'Sportart',
                                'Sport',
                                'Sport',
                                'الرياضة',
                              ),
                              allSportsLabel: _c(
                                'Alle Sportarten',
                                'All sports',
                                'Tous les sports',
                                'كل الرياضات',
                              ),
                              onTextChanged: () => setSheetState(() {
                                sportId = null;
                              }),
                              onSelected: (sport) => setSheetState(() {
                                sportId = sport == null
                                    ? null
                                    : _int(sport['id']);
                              }),
                            ),
                          const SizedBox(height: 18),
                          _configurationSectionLabel(
                            context,
                            _c('Umkreis', 'Radius', 'Rayon', 'النطاق'),
                            Icons.radar_outlined,
                          ),
                          AirmiusPanel(
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      _c(
                                        'Angebote in deiner Nähe',
                                        'Offers near you',
                                        'Offres près de vous',
                                        'العروض القريبة منك',
                                      ),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    '$radiusKm km',
                                    style: TextStyle(
                                      color: airmiusAccentColor(context),
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ],
                              ),
                              Slider(
                                value: radiusKm.toDouble(),
                                min: 5,
                                max: 500,
                                divisions: 99,
                                label: '$radiusKm km',
                                onChanged: (value) => setSheetState(
                                  () => radiusKm = value.round(),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 18),
                          _configurationSectionLabel(
                            context,
                            _c(
                              'Leistungsniveau',
                              'Skill level',
                              'Niveau',
                              'المستوى',
                            ),
                            Icons.speed_outlined,
                          ),
                          DropdownButtonFormField<String>(
                            initialValue: skillFilter,
                            isExpanded: true,
                            decoration: InputDecoration(
                              prefixIcon: const Icon(Icons.speed_outlined),
                              labelText: _c(
                                'Niveau auswählen',
                                'Choose level',
                                'Choisir le niveau',
                                'اختر المستوى',
                              ),
                            ),
                            items: [
                              for (final level in const [
                                'all',
                                'beginner',
                                'recreational',
                                'advanced',
                                'competitive',
                              ])
                                DropdownMenuItem(
                                  value: level,
                                  child: Text(_skillLabel(level)),
                                ),
                            ],
                            onChanged: (value) => setSheetState(() {
                              if (value != null) skillFilter = value;
                            }),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () {
                              cityController.clear();
                              sportController.clear();
                              setSheetState(() {
                                sportId = null;
                                radiusKm = 25;
                                skillFilter = 'all';
                              });
                            },
                            child: Text(
                              _c(
                                'Zurücksetzen',
                                'Reset',
                                'Réinitialiser',
                                'إعادة ضبط',
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          flex: 2,
                          child: FilledButton.icon(
                            onPressed: () => Navigator.pop(
                              sheetContext,
                              _SportMatchingFilters(
                                mode: mode,
                                swipeView: swipeView,
                                city: cityController.text.trim(),
                                sportName: sportController.text.trim(),
                                sportId: sportId,
                                radiusKm: radiusKm,
                                skillLevel: skillFilter,
                              ),
                            ),
                            icon: const Icon(Icons.check),
                            label: Text(
                              _c('Übernehmen', 'Apply', 'Appliquer', 'تطبيق'),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );

    cityController.dispose();
    sportController.dispose();
    if (filters == null || !mounted) return;
    setState(() {
      _mode = filters.mode;
      _swipeView = filters.swipeView;
      _swipeOffset = 0;
      _cityController.text = filters.city;
      _sportController.text = filters.sportName;
      _sportId = filters.sportId;
      _radiusKm = filters.radiusKm;
      _skillFilter = filters.skillLevel;
    });
    _reload();
  }

  Widget _topActions(List<JsonMap> sports) => Row(
    children: [
      Expanded(
        child: OutlinedButton.icon(
          onPressed: _busy ? null : () => _openConfiguration(sports),
          icon: const Icon(Icons.tune),
          label: Text(_c('Konfigurieren', 'Configure', 'Configurer', 'تهيئة')),
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: FilledButton.icon(
          onPressed: _busy ? null : _openCreate,
          icon: const Icon(Icons.add),
          label: Text(
            _c('Erstellen', 'Create', 'Créer', 'إنشاء'),
          ),
        ),
      ),
    ],
  );

  Widget _configurationSectionLabel(
    BuildContext context,
    String label,
    IconData icon,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      children: [
        Icon(icon, size: 19, color: airmiusAccentColor(context)),
        const SizedBox(width: 8),
        Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      ],
    ),
  );

  static List<JsonMap> _maps(Object? value) =>
      value is List ? value.whereType<JsonMap>().toList() : const [];

  static int _int(Object? value) => int.tryParse('$value') ?? 0;
}

class _CreateMatchingSheet extends StatefulWidget {
  const _CreateMatchingSheet({
    required this.mode,
    required this.sports,
    required this.teams,
    this.defaultCountryCode,
  });

  final String mode;
  final List<JsonMap> sports;
  final List<JsonMap> teams;
  final String? defaultCountryCode;

  @override
  State<_CreateMatchingSheet> createState() => _CreateMatchingSheetState();
}

class _CreateMatchingSheetState extends State<_CreateMatchingSheet> {
  final _title = TextEditingController();
  final _location = TextEditingController();
  final _postalCode = TextEditingController();
  final _locationName = TextEditingController();
  final _address = TextEditingController();
  final _sport = TextEditingController();
  final _description = TextEditingController();
  final _count = TextEditingController(text: '1');
  final _teamSize = TextEditingController();
  int? _sportId;
  int? _teamId;
  int _radiusKm = 25;
  String _skillLevel = 'all';
  late String _countryCode;
  DateTime _startsAt = DateTime.now().add(const Duration(days: 1));
  DateTime? _endsAt;

  @override
  void initState() {
    super.initState();
    final requested = widget.defaultCountryCode?.trim().toUpperCase();
    _countryCode =
        _matchingCountries.any((country) => country.code == requested)
        ? requested!
        : 'DE';
  }

  @override
  void dispose() {
    for (final controller in [
      _title,
      _location,
      _postalCode,
      _locationName,
      _address,
      _sport,
      _description,
      _count,
      _teamSize,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      16,
      12,
      16,
      16 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: ListView(
      children: [
        Text(
          widget.mode == 'team' ? 'Teamgegner finden' : 'Sportpartner finden',
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 16),
        _MatchingSportAutocomplete(
          controller: _sport,
          sports: widget.sports,
          hintText: _copy(
            'Wunschsport suchen',
            'Search for a sport',
            'Rechercher un sport',
            'ابحث عن الرياضة المطلوبة',
          ),
          onTextChanged: () => setState(() => _sportId = null),
          onSelected: (sport) => setState(
            () => _sportId = sport == null ? null : _int(sport['id']),
          ),
        ),
        const SizedBox(height: 14),
        if (widget.mode == 'team') ...[
          DropdownButtonFormField<int>(
            decoration: const InputDecoration(labelText: 'Dein Team'),
            items: widget.teams
                .map(
                  (team) => DropdownMenuItem(
                    value: int.tryParse('${team['id']}') ?? 0,
                    child: Text('${team['name']}'),
                  ),
                )
                .toList(),
            onChanged: (value) => _teamId = value,
          ),
          const SizedBox(height: 14),
        ],
        TextField(
          controller: _title,
          decoration: const InputDecoration(labelText: 'Titel (optional)'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _location,
          textCapitalization: TextCapitalization.words,
          decoration: InputDecoration(
            labelText: _copy('Stadt / Ort', 'City', 'Ville', 'المدينة'),
            hintText: _copy(
              'z. B. Kenitra',
              'e.g. Kenitra',
              'ex. Kénitra',
              'مثال: القنيطرة',
            ),
            prefixIcon: const Icon(Icons.location_on_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _postalCode,
          keyboardType: TextInputType.streetAddress,
          decoration: const InputDecoration(
            labelText: 'PLZ (optional)',
            hintText: 'z. B. 14000',
            prefixIcon: Icon(Icons.local_post_office_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _locationName,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Sportstätte / Treffpunkt (optional)',
            hintText: 'z. B. Stadtpark oder Court 2',
            prefixIcon: Icon(Icons.place_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _address,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Adresse (optional)',
            hintText: 'Straße und Hausnummer',
            prefixIcon: Icon(Icons.signpost_outlined),
          ),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          initialValue: _countryCode,
          isExpanded: true,
          dropdownColor: airmiusSurfaceColor(context),
          decoration: InputDecoration(
            labelText: _copy('Land', 'Country', 'Pays', 'الدولة'),
            prefixIcon: const Icon(Icons.public_outlined),
          ),
          items: [
            for (final country in _matchingCountries)
              DropdownMenuItem(
                value: country.code,
                child: Text(country.label(AirmiusScope.of(context).language)),
              ),
          ],
          onChanged: (value) {
            if (value != null) setState(() => _countryCode = value);
          },
        ),
        const SizedBox(height: 10),
        TextField(
          controller: widget.mode == 'team' ? _teamSize : _count,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: widget.mode == 'team'
                ? 'Personen pro Team'
                : 'Gesuchte Personen',
          ),
        ),
        const SizedBox(height: 10),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Datum und Uhrzeit'),
          subtitle: Text(_formatMatchingDateTime(context, _startsAt)),
          trailing: const Icon(Icons.schedule),
          onTap: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: _startsAt,
              firstDate: DateTime.now(),
              lastDate: DateTime.now().add(const Duration(days: 730)),
            );
            if (date != null) {
              if (!context.mounted) return;
              final time = await showTimePicker(
                context: context,
                initialTime: TimeOfDay.fromDateTime(_startsAt),
              );
              if (time == null) return;
              setState(
                () => _startsAt = DateTime(
                  date.year,
                  date.month,
                  date.day,
                  time.hour,
                  time.minute,
                ),
              );
            }
          },
        ),
        const SizedBox(height: 4),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(
            _copy(
              'Ende (optional)',
              'End (optional)',
              'Fin (facultatif)',
              'النهاية (اختياري)',
            ),
          ),
          subtitle: Text(
            _endsAt == null
                ? _copy(
                    'Keine Endzeit',
                    'No end time',
                    'Aucune heure de fin',
                    'لا يوجد وقت نهاية',
                  )
                : _formatMatchingDateTime(context, _endsAt!),
          ),
          trailing: const Icon(Icons.event_available_outlined),
          onTap: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: _endsAt ?? _startsAt,
              firstDate: _startsAt,
              lastDate: DateTime.now().add(const Duration(days: 730)),
            );
            if (date == null || !context.mounted) return;
            final time = await showTimePicker(
              context: context,
              initialTime: TimeOfDay.fromDateTime(_endsAt ?? _startsAt),
            );
            if (time == null) return;
            final value = DateTime(
              date.year,
              date.month,
              date.day,
              time.hour,
              time.minute,
            );
            if (!value.isAfter(_startsAt)) {
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      _copy(
                        'Das Ende muss nach dem Beginn liegen.',
                        'The end must be after the start.',
                        'La fin doit être après le début.',
                        'يجب أن تكون النهاية بعد البداية.',
                      ),
                    ),
                  ),
                );
              }
              return;
            }
            setState(() => _endsAt = value);
          },
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Icon(Icons.radar_outlined, color: airmiusAccentColor(context)),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                _copy('Umkreis', 'Radius', 'Rayon', 'النطاق'),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            Text(
              '$_radiusKm km',
              style: TextStyle(
                color: airmiusAccentColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        Slider(
          value: _radiusKm.toDouble(),
          min: 5,
          max: 500,
          divisions: 99,
          label: '$_radiusKm km',
          onChanged: (value) => setState(() => _radiusKm = value.round()),
        ),
        DropdownButtonFormField<String>(
          initialValue: _skillLevel,
          isExpanded: true,
          dropdownColor: airmiusSurfaceColor(context),
          decoration: InputDecoration(
            labelText: _copy('Niveau', 'Skill level', 'Niveau', 'المستوى'),
            prefixIcon: const Icon(Icons.speed_outlined),
          ),
          items: [
            for (final level in const [
              'all',
              'beginner',
              'recreational',
              'advanced',
              'competitive',
            ])
              DropdownMenuItem(value: level, child: Text(_skillLabel(level))),
          ],
          onChanged: (value) {
            if (value != null) setState(() => _skillLevel = value);
          },
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _description,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Beschreibung'),
        ),
        const SizedBox(height: 18),
        FilledButton(onPressed: _submit, child: const Text('Veröffentlichen')),
      ],
    ),
  );

  void _submit() {
    if (_sportId == null ||
        _location.text.trim().isEmpty ||
        (widget.mode == 'team' &&
            (_teamId == null || _teamSize.text.isEmpty))) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Bitte alle Pflichtfelder ausfüllen.')),
      );
      return;
    }
    Navigator.pop(context, <String, dynamic>{
      'mode': widget.mode,
      'sport_id': _sportId,
      'team_id': widget.mode == 'team' ? _teamId : null,
      if (_title.text.trim().isNotEmpty) 'title': _title.text.trim(),
      'description': _description.text.trim(),
      'city': _location.text.trim(),
      'postal_code': _postalCode.text.trim(),
      'location_name': _locationName.text.trim(),
      'address': _address.text.trim(),
      'country_code': _countryCode,
      'radius_km': _radiusKm,
      'starts_at': _startsAt.toUtc().toIso8601String(),
      if (_endsAt != null) 'ends_at': _endsAt!.toUtc().toIso8601String(),
      'participants_needed': widget.mode == 'team'
          ? 1
          : int.tryParse(_count.text) ?? 1,
      'team_size': widget.mode == 'team' ? int.tryParse(_teamSize.text) : null,
      'skill_level': _skillLevel,
    });
  }

  String _copy(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  String _skillLabel(String level) => switch (level) {
    'beginner' => _copy('Anfänger', 'Beginner', 'Débutant', 'مبتدئ'),
    'recreational' => _copy('Freizeit', 'Recreational', 'Loisir', 'ترفيهي'),
    'advanced' => _copy('Fortgeschritten', 'Advanced', 'Avancé', 'متقدم'),
    'competitive' => _copy('Wettkampf', 'Competitive', 'Compétition', 'تنافسي'),
    _ => _copy(
      'Alle Niveaus',
      'All levels',
      'Tous les niveaux',
      'كل المستويات',
    ),
  };

  static int _int(Object? value) => int.tryParse('$value') ?? 0;
}

String _formatMatchingDateTime(BuildContext context, DateTime value) {
  final localizations = MaterialLocalizations.of(context);
  return '${localizations.formatMediumDate(value)} · ${localizations.formatTimeOfDay(TimeOfDay.fromDateTime(value), alwaysUse24HourFormat: true)} Uhr';
}

class _SportMatchingFilters {
  const _SportMatchingFilters({
    required this.mode,
    required this.swipeView,
    required this.city,
    required this.sportName,
    required this.sportId,
    required this.radiusKm,
    required this.skillLevel,
  });

  final String mode;
  final bool swipeView;
  final String city;
  final String sportName;
  final int? sportId;
  final int radiusKm;
  final String skillLevel;
}

class _MatchingSportAutocomplete extends StatelessWidget {
  const _MatchingSportAutocomplete({
    required this.controller,
    required this.sports,
    required this.hintText,
    required this.onSelected,
    this.allSportsLabel,
    this.onTextChanged,
  });

  final TextEditingController controller;
  final List<JsonMap> sports;
  final String hintText;
  final String? allSportsLabel;
  final ValueChanged<JsonMap?> onSelected;
  final VoidCallback? onTextChanged;

  @override
  Widget build(BuildContext context) {
    return Autocomplete<JsonMap>(
      displayStringForOption: (sport) => '${sport['name'] ?? ''}',
      optionsBuilder: (value) {
        final query = value.text.trim().toLowerCase();
        if (query.isEmpty) return sports.take(12);
        return sports
            .where((sport) {
              final name = '${sport['name'] ?? ''}'.toLowerCase();
              final slug = '${sport['slug'] ?? ''}'.toLowerCase();
              return name.contains(query) || slug.contains(query);
            })
            .take(12);
      },
      onSelected: (sport) {
        controller.text = '${sport['name'] ?? ''}';
        onSelected(sport);
      },
      fieldViewBuilder: (context, textController, focusNode, onFieldSubmitted) {
        if (textController.text.isEmpty && controller.text.isNotEmpty) {
          textController.text = controller.text;
        }
        return TextField(
          controller: textController,
          focusNode: focusNode,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            labelText: hintText,
            prefixIcon: const Icon(Icons.sports_outlined),
            suffixIcon: textController.text.isEmpty
                ? const Icon(Icons.search)
                : IconButton(
                    tooltip: allSportsLabel,
                    onPressed: () {
                      textController.clear();
                      controller.clear();
                      onSelected(null);
                    },
                    icon: const Icon(Icons.clear),
                  ),
          ),
          onChanged: (value) {
            controller.text = value;
            onTextChanged?.call();
          },
        );
      },
      optionsViewBuilder: (context, onOptionSelected, options) {
        final items = options.toList(growable: false);
        final width = (MediaQuery.sizeOf(context).width - 32)
            .clamp(220.0, 560.0)
            .toDouble();
        return Align(
          alignment: AlignmentDirectional.topStart,
          child: Material(
            color: Colors.transparent,
            child: Container(
              width: width,
              margin: const EdgeInsets.only(top: 6),
              constraints: const BoxConstraints(maxHeight: 300),
              decoration: BoxDecoration(
                color: airmiusSurfaceColor(context),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: airmiusBorderColor(context)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.24),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: items.isEmpty
                  ? const SizedBox.shrink()
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      shrinkWrap: true,
                      itemCount: items.length,
                      separatorBuilder: (_, _) => Divider(
                        height: 1,
                        color: airmiusBorderColor(context),
                      ),
                      itemBuilder: (context, index) {
                        final sport = items[index];
                        return Material(
                          color: Colors.transparent,
                          child: ListTile(
                            leading: const Icon(Icons.sports_outlined),
                            title: Text(
                              '${sport['name'] ?? ''}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            onTap: () => onOptionSelected(sport),
                          ),
                        );
                      },
                    ),
            ),
          ),
        );
      },
    );
  }
}

const _matchingCountries = <_MatchingCountry>[
  _MatchingCountry('DE', 'Deutschland', 'Germany', 'Allemagne', 'ألمانيا'),
  _MatchingCountry('MA', 'Marokko', 'Morocco', 'Maroc', 'المغرب'),
  _MatchingCountry('AT', 'Österreich', 'Austria', 'Autriche', 'النمسا'),
  _MatchingCountry('CH', 'Schweiz', 'Switzerland', 'Suisse', 'سويسرا'),
  _MatchingCountry('FR', 'Frankreich', 'France', 'France', 'فرنسا'),
  _MatchingCountry('BE', 'Belgien', 'Belgium', 'Belgique', 'بلجيكا'),
  _MatchingCountry('NL', 'Niederlande', 'Netherlands', 'Pays-Bas', 'هولندا'),
  _MatchingCountry('LU', 'Luxemburg', 'Luxembourg', 'Luxembourg', 'لوكسمبورغ'),
  _MatchingCountry('ES', 'Spanien', 'Spain', 'Espagne', 'إسبانيا'),
  _MatchingCountry('PT', 'Portugal', 'Portugal', 'Portugal', 'البرتغال'),
  _MatchingCountry('IT', 'Italien', 'Italy', 'Italie', 'إيطاليا'),
  _MatchingCountry(
    'GB',
    'Großbritannien',
    'United Kingdom',
    'Royaume-Uni',
    'المملكة المتحدة',
  ),
  _MatchingCountry('IE', 'Irland', 'Ireland', 'Irlande', 'أيرلندا'),
  _MatchingCountry('DK', 'Dänemark', 'Denmark', 'Danemark', 'الدنمارك'),
  _MatchingCountry('SE', 'Schweden', 'Sweden', 'Suède', 'السويد'),
  _MatchingCountry('NO', 'Norwegen', 'Norway', 'Norvège', 'النرويج'),
  _MatchingCountry('PL', 'Polen', 'Poland', 'Pologne', 'بولندا'),
  _MatchingCountry('CZ', 'Tschechien', 'Czechia', 'Tchéquie', 'التشيك'),
  _MatchingCountry('TR', 'Türkei', 'Türkiye', 'Turquie', 'تركيا'),
  _MatchingCountry(
    'US',
    'USA',
    'United States',
    'États-Unis',
    'الولايات المتحدة',
  ),
  _MatchingCountry('CA', 'Kanada', 'Canada', 'Canada', 'كندا'),
];

class _MatchingCountry {
  const _MatchingCountry(this.code, this.de, this.en, this.fr, this.ar);

  final String code;
  final String de;
  final String en;
  final String fr;
  final String ar;

  String label(AirmiusLanguage language) => switch (language) {
    AirmiusLanguage.de => de,
    AirmiusLanguage.en => en,
    AirmiusLanguage.fr => fr,
    AirmiusLanguage.ar => ar,
  };
}
