import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import 'chat_detail_screen.dart';
import '../widgets/content_report_dialog.dart';
import '../widgets/airmius_widgets.dart';
import 'teams_center_screen.dart';

class SportMatchingScreen extends StatefulWidget {
  const SportMatchingScreen({super.key});

  @override
  State<SportMatchingScreen> createState() => _SportMatchingScreenState();
}

class _SportMatchingScreenState extends State<SportMatchingScreen> {
  final _cityController = TextEditingController();
  final _sportController = TextEditingController();
  Future<JsonMap>? _future;
  JsonMap _response = <String, dynamic>{};
  String _mode = 'partner';
  int? _sportId;
  int _radiusKm = 25;
  String _skillFilter = 'all';
  bool _swipeView = false;
  double? _searchLatitude;
  double? _searchLongitude;
  int _page = 1;
  bool _hasMore = false;
  bool _loadingMore = false;
  double _swipeOffset = 0;
  final Set<int> _swipedMatchingIds = <int>{};
  bool _busy = false;

  bool get _hasCustomFilters =>
      _cityController.text.trim().isNotEmpty ||
      _sportController.text.trim().isNotEmpty ||
      _sportId != null ||
      _radiusKm != 25 ||
      _skillFilter != 'all' ||
      _searchLatitude != null;

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
    final locality = [
      value(matching['postal_code']),
      value(matching['city']),
    ].where((part) => part.isNotEmpty).join(' ');
    final country = value(matching['country_code']);
    final cityAndCountry = [
      locality,
      country,
    ].where((part) => part.isNotEmpty).join(', ');

    final parts = [
      locationName,
      address,
      cityAndCountry,
    ].where((part) => part.isNotEmpty).toList();

    return parts.isEmpty
        ? _c('Ort offen', 'Location open', 'Lieu à définir', 'المكان مفتوح')
        : parts.join(' · ');
  }

  String _teamSizeLabel(JsonMap matching) {
    final own = _int(matching['own_team_size']) > 0
        ? _int(matching['own_team_size'])
        : _int(matching['team_size']);
    final opponent = _int(matching['team_size']);
    final minimum = matching['opponent_size_type'] == 'minimum';
    return minimum
        ? '$own vs. ${_c('mind.', 'min.', 'min.', 'حد أدنى')} $opponent'
        : '$own vs. $opponent';
  }

  String _skillAndDistance(JsonMap matching) {
    final skill = _skillLabel('${matching['skill_level'] ?? 'all'}');
    final distance = matching['distance_km'];
    return distance == null ? skill : '$skill · $distance km';
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

  Future<JsonMap> _load() async {
    final response = await _client.sportMatchings(
      mode: _mode,
      page: 1,
      city: _cityController.text,
      sportId: _sportId,
      radiusKm: _radiusKm,
      skillLevel: _skillFilter,
      latitude: _searchLatitude,
      longitude: _searchLongitude,
    );
    _response = response;
    _page = 1;
    _hasMore = _hasNextPage(response);
    return response;
  }

  bool _hasNextPage(JsonMap response) {
    final links = response['links'];
    if (links is JsonMap) return links['next'] != null;
    final meta = response['meta'];
    if (meta is JsonMap) {
      return _int(meta['current_page']) < _int(meta['last_page']);
    }
    return false;
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final next = await _client.sportMatchings(
        mode: _mode,
        page: _page + 1,
        city: _cityController.text,
        sportId: _sportId,
        radiusKm: _radiusKm,
        skillLevel: _skillFilter,
        latitude: _searchLatitude,
        longitude: _searchLongitude,
      );
      if (!mounted) return;
      setState(() {
        _response['data'] = [
          ..._maps(_response['data']),
          ..._maps(next['data']),
        ];
        _page++;
        _hasMore = _hasNextPage(next);
      });
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _c(
                'Weitere Angebote konnten nicht geladen werden.',
                'Could not load more offers.',
                'Impossible de charger plus d’offres.',
                'تعذر تحميل المزيد من العروض.',
              ),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

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
              IconButton(
                tooltip: AirmiusScope.of(context).t('common.refresh'),
                onPressed: _reload,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          body: FutureBuilder<JsonMap>(
            future: _future,
            builder: (context, snapshot) {
              final data = snapshot.data == null ? null : _response;
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

  String _attendanceStatusLabel(Object? status) => switch ('$status') {
    'confirmed' => _c(
      'Teilnahme bestätigt',
      'Attendance confirmed',
      'Participation confirmée',
      'تم تأكيد الحضور',
    ),
    'checked_in' => _c('Angekommen', 'Checked in', 'Arrivé', 'تم تسجيل الوصول'),
    'cancelled' => _c('Abgesagt', 'Cancelled', 'Annulé', 'ملغى'),
    'no_show' => _c(
      'Nicht erschienen',
      'No-show reported',
      'Absent signalé',
      'تم الإبلاغ عن عدم الحضور',
    ),
    _ => _c(
      'Noch nicht bestätigt',
      'Not confirmed yet',
      'Pas encore confirmé',
      'لم يتم التأكيد بعد',
    ),
  };

  Color _attendanceStatusColor(Object? status) => switch ('$status') {
    'confirmed' => airmiusAccentColor(context),
    'checked_in' => Colors.green,
    'cancelled' => airmiusMutedColor(context),
    'no_show' => Colors.red,
    _ => Colors.orange,
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
    if (_swipeView) {
      final tracked = matchings
          .where(
            (matching) =>
                (matching['mine'] == true ||
                    matching['my_application'] == 'accepted') &&
                matching['attendance'] is JsonMap,
          )
          .toList();
      final pending = matchings
          .where((matching) => matching['my_application'] == 'pending')
          .toList();
      return Column(
        children: [
          _swipeDeck(discoverable, teams),
          if (_hasMore) _moreButton(),
          if (pending.isNotEmpty) ...[
            const SizedBox(height: 16),
            ...pending.map(
              (matching) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _card(matching, teams),
              ),
            ),
          ],
          if (tracked.isNotEmpty) ...[
            const SizedBox(height: 16),
            _attendanceOverview(tracked),
          ],
        ],
      );
    }
    if (matchings.isEmpty) {
      return AirmiusPanel(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Text(
            _hasCustomFilters
                ? _c(
                    'Für deine Auswahl gibt es noch keine Angebote. Passe die Suche an oder erstelle selbst eines.',
                    'No offers match your selection yet. Adjust the search or create one yourself.',
                    'Aucune offre ne correspond à votre sélection. Modifiez la recherche ou créez une offre.',
                    'لا توجد عروض تطابق اختيارك بعد. عدّل البحث أو أنشئ عرضًا.',
                  )
                : _c(
                    'Hier sind noch keine Angebote zu sehen. Erstelle das erste Angebot.',
                    'There are no offers here yet. Create the first one.',
                    'Il n’y a pas encore d’offres ici. Créez la première.',
                    'لا توجد عروض هنا بعد. أنشئ العرض الأول.',
                  ),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    return Column(
      children: [
        ...matchings.map(
          (matching) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _card(matching, teams),
          ),
        ),
        if (_hasMore) _moreButton(),
      ],
    );
  }

  Widget _moreButton() => Padding(
    padding: const EdgeInsets.only(top: 10, bottom: 12),
    child: OutlinedButton.icon(
      onPressed: _loadingMore ? null : _loadMore,
      icon: _loadingMore
          ? const SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : const Icon(Icons.expand_more),
      label: Text(
        _c(
          'Weitere Angebote laden',
          'Load more offers',
          'Charger plus d’offres',
          'تحميل المزيد من العروض',
        ),
      ),
    ),
  );

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
            _swipedMatchingIds.isNotEmpty && _maps(_response['data']).isNotEmpty
                ? _c(
                    'Alles angesehen',
                    'All caught up',
                    'Tout est vu',
                    'تمت مشاهدة الكل',
                  )
                : _hasCustomFilters
                ? _c(
                    'Keine passenden Angebote',
                    'No matching offers',
                    'Aucune offre correspondante',
                    'لا توجد عروض مطابقة',
                  )
                : _c(
                    'Noch keine Angebote',
                    'No offers yet',
                    'Pas encore d’offres',
                    'لا توجد عروض بعد',
                  ),
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            _swipedMatchingIds.isNotEmpty && _maps(_response['data']).isNotEmpty
                ? _c(
                    'Zeige übersprungene Angebote erneut an.',
                    'Show skipped offers again.',
                    'Affichez à nouveau les offres ignorées.',
                    'اعرض العروض المتخطاة مرة أخرى.',
                  )
                : _hasCustomFilters
                ? _c(
                    'Passe Sportart, Ort oder Umkreis an.',
                    'Adjust the sport, city or radius.',
                    'Modifiez le sport, la ville ou le rayon.',
                    'عدّل الرياضة أو المدينة أو النطاق.',
                  )
                : _c(
                    'Erstelle das erste Angebot.',
                    'Create the first offer.',
                    'Créez la première offre.',
                    'أنشئ العرض الأول.',
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

  Widget _attendanceOverview(List<JsonMap> matchings) => AirmiusPanel(
    children: [
      Row(
        children: [
          Icon(Icons.event_available, color: airmiusAccentColor(context)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _c('Meine Termine', 'My sessions', 'Mes séances', 'مواعيدي'),
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
          ),
          Flexible(
            child: Text(
              _c(
                'Teilnahme im Blick',
                'Keep attendance up to date',
                'Suivez votre participation',
                'تابع حضورك',
              ),
              textAlign: TextAlign.end,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        ],
      ),
      const SizedBox(height: 14),
      ...matchings.map(
        (matching) => Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: _attendancePanel(matching),
        ),
      ),
    ],
  );

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
                    ? _teamSizeLabel(matching)
                    : '${matching['participants_needed']} ${_c('gesucht', 'wanted', 'recherchés', 'مطلوب')}',
              ),
              _swipeDetail(Icons.speed_outlined, _skillAndDistance(matching)),
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
    Map<String, int>? selectedTeam;
    if (interested && matching['mode'] == 'team') {
      selectedTeam = await _chooseTeam(teams, matching);
      if (selectedTeam == null) return;
    }

    setState(() => _swipeOffset = interested ? 520 : -520);
    await Future<void>.delayed(const Duration(milliseconds: 220));
    if (!mounted) return;
    final matchingId = _int(matching['id']);
    setState(() => _swipeOffset = 0);
    if (interested) {
      await _run(
        () => _client.applyForSportMatching(
          matchingId,
          teamId: selectedTeam?['team_id'],
          teamSize: selectedTeam?['team_size'],
        ),
        refresh: false,
        onSuccess: (response) => _markApplicationSent(matching, response),
      );
    } else {
      final dismissed = await _run(
        () => _client.dismissSportMatching(matchingId),
        refresh: false,
      );
      if (!mounted || !dismissed) return;
      setState(() => _swipedMatchingIds.add(matchingId));
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
      refresh: false,
    );
  }

  Future<Map<String, int>?> _chooseTeam(
    List<JsonMap> teams,
    JsonMap matching,
  ) async {
    final sizeController = TextEditingController();
    int? teamId;
    final result = await showModalBottomSheet<Map<String, int>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              8,
              20,
              20 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: teams.isEmpty
                ? Text(
                    _c(
                      'Du bist keinem Team zugeordnet.',
                      'You are not assigned to a team.',
                      'Vous n’êtes affecté à aucune équipe.',
                      'لست منضمًا إلى أي فريق.',
                    ),
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        _c(
                          'Mit Team bewerben',
                          'Apply with a team',
                          'Postuler avec une équipe',
                          'تقدم بفريق',
                        ),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<int>(
                        decoration: InputDecoration(
                          labelText: _c(
                            'Dein Team',
                            'Your team',
                            'Votre équipe',
                            'فريقك',
                          ),
                        ),
                        items: teams
                            .map(
                              (team) => DropdownMenuItem(
                                value: _int(team['id']),
                                child: Text('${team['name']}'),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setSheetState(() => teamId = value),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: sizeController,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: _c(
                            'Wie viele Spieler habt ihr?',
                            'How many players do you have?',
                            'Combien de joueurs avez-vous ?',
                            'كم لاعبًا لديكم؟',
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(_teamSizeLabel(matching)),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: () {
                          final size = int.tryParse(sizeController.text);
                          if (teamId == null || size == null || size < 1) {
                            ScaffoldMessenger.of(sheetContext).showSnackBar(
                              SnackBar(
                                content: Text(
                                  _c(
                                    'Team und Spielerzahl angeben.',
                                    'Enter team and player count.',
                                    'Indiquez l’équipe et le nombre de joueurs.',
                                    'أدخل الفريق وعدد اللاعبين.',
                                  ),
                                ),
                              ),
                            );
                            return;
                          }
                          Navigator.pop(sheetContext, {
                            'team_id': teamId!,
                            'team_size': size,
                          });
                        },
                        child: Text(
                          _c(
                            'Interesse senden',
                            'Send interest',
                            'Envoyer la demande',
                            'إرسال الاهتمام',
                          ),
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
    sizeController.dispose();
    return result;
  }

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
              Text(_matchingLocationLabel(matching)),
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
      refresh: false,
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
        value.contains('soccer')) {
      return Icons.sports_soccer;
    }
    if (value.contains('basket')) {
      return Icons.sports_basketball;
    }
    if (value.contains('tennis') || value.contains('padel')) {
      return Icons.sports_tennis;
    }
    if (value.contains('swim') || value.contains('schwimm')) {
      return Icons.pool_outlined;
    }
    if (value.contains('bike') ||
        value.contains('rad') ||
        value.contains('cycling')) {
      return Icons.directions_bike;
    }
    if (value.contains('hike') || value.contains('wandern')) {
      return Icons.hiking;
    }
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
                    ? _teamSizeLabel(matching)
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
        Text('🎯 ${_skillAndDistance(matching)}'),
        if (team != null) Text('👥 ${team['name']}'),
        if (matching['attendance'] is JsonMap) ...[
          const SizedBox(height: 14),
          _attendancePanel(matching),
        ],
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
          if (matching['my_application'] == 'pending')
            OutlinedButton.icon(
              onPressed: _busy
                  ? null
                  : () async {
                      final withdrawn = await _run(
                        () =>
                            _client.withdrawSportMatching(_int(matching['id'])),
                        refresh: false,
                      );
                      if (withdrawn && mounted) {
                        setState(() => matching['my_application'] = null);
                      }
                    },
              icon: const Icon(Icons.undo),
              label: Text(
                _c(
                  'Anfrage zurückziehen',
                  'Withdraw request',
                  'Retirer la demande',
                  'سحب الطلب',
                ),
              ),
            ),
        ],
      ],
    );
  }

  Widget _attendancePanel(JsonMap matching) {
    final attendance = matching['attendance'] is JsonMap
        ? matching['attendance'] as JsonMap
        : const <String, dynamic>{};
    final status = '${attendance['status'] ?? 'pending'}';
    final color = _attendanceStatusColor(status);
    final startsAt = DateTime.tryParse('${matching['starts_at'] ?? ''}');
    final canCancel = status == 'pending' || status == 'confirmed';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.event_available, color: color),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _c('Teilnahme', 'Attendance', 'Participation', 'الحضور'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      _attendanceStatusLabel(status),
                      style: TextStyle(
                        color: color,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (startsAt != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        _formatMatchingDateTime(context, startsAt.toLocal()),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (status == 'pending') ...[
            const SizedBox(height: 8),
            Text(
              _c(
                'Bestätige kurz, ob du dabei bist. So weiß die andere Person, worauf sie sich verlassen kann.',
                'Confirm whether you are coming so the other person can rely on your answer.',
                'Confirmez votre présence pour que l’autre personne puisse s’organiser.',
                'أكد حضورك حتى يتمكن الطرف الآخر من التخطيط.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          if (status == 'no_show') ...[
            const SizedBox(height: 8),
            Text(
              _c(
                'Diese Teilnahme wurde als nicht erschienen gemeldet.',
                'This attendance was reported as a no-show.',
                'Cette participation a été signalée comme absence.',
                'تم الإبلاغ عن عدم الحضور.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          if (status == 'pending' ||
              status == 'cancelled' ||
              status == 'confirmed') ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (status == 'pending' || status == 'cancelled')
                  FilledButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _updateAttendance(matching, 'confirm'),
                    icon: const Icon(Icons.check, size: 18),
                    label: Text(
                      _c('Ich komme', 'I’m coming', 'Je viens', 'سآتي'),
                    ),
                  ),
                if (status == 'confirmed')
                  FilledButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _updateAttendance(matching, 'check_in'),
                    icon: const Icon(Icons.login, size: 18),
                    label: Text(
                      _c('Angekommen', 'I’m here', 'Je suis arrivé', 'وصلت'),
                    ),
                  ),
                if (canCancel)
                  OutlinedButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _updateAttendance(matching, 'cancel'),
                    icon: const Icon(Icons.close, size: 18),
                    label: Text(_c('Absagen', 'Cancel', 'Annuler', 'إلغاء')),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _applicationRow(JsonMap matching, JsonMap application) {
    final user = application['user'] is JsonMap
        ? application['user'] as JsonMap
        : const <String, dynamic>{};
    final team = application['team'] is JsonMap
        ? application['team'] as JsonMap
        : null;
    final attendance = application['attendance'] is JsonMap
        ? application['attendance'] as JsonMap
        : null;
    final attendanceStatus = '${attendance?['status'] ?? ''}';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text('${team?['name'] ?? user['name'] ?? ''}'),
          subtitle: Text(
            [
              if (team != null && application['team_size'] != null)
                '${application['team_size']} ${_c('Spieler', 'players', 'joueurs', 'لاعبين')}',
              if ('${application['message'] ?? ''}'.trim().isNotEmpty)
                '${application['message']}',
            ].join(' · '),
          ),
          trailing: application['status'] == 'pending'
              ? Wrap(
                  children: [
                    IconButton(
                      tooltip: _c('Annehmen', 'Accept', 'Accepter', 'قبول'),
                      onPressed: _busy
                          ? null
                          : () => _decide(matching, application, 'accepted'),
                      icon: const Icon(Icons.check, color: Colors.green),
                    ),
                    IconButton(
                      tooltip: _c('Ablehnen', 'Decline', 'Refuser', 'رفض'),
                      onPressed: _busy
                          ? null
                          : () => _decide(matching, application, 'declined'),
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
        ),
        if (application['status'] == 'accepted' && attendance != null) ...[
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: _attendanceStatusColor(
                attendanceStatus,
              ).withValues(alpha: 0.07),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _attendanceStatusLabel(attendanceStatus),
                    style: TextStyle(
                      color: _attendanceStatusColor(attendanceStatus),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (attendanceStatus == 'confirmed')
                  TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _reportNoShow(matching, _int(user['id'])),
                    icon: const Icon(Icons.flag_outlined, size: 17),
                    label: Text(
                      _c('Nicht erschienen', 'No-show', 'Absent', 'لم يحضر'),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ],
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
    Map<String, int>? selectedTeam;
    if (matching['mode'] == 'team') {
      selectedTeam = await _chooseTeam(teams, matching);
      if (selectedTeam == null) return;
    }
    await _run(
      () => _client.applyForSportMatching(
        _int(matching['id']),
        teamId: selectedTeam?['team_id'],
        teamSize: selectedTeam?['team_size'],
      ),
      refresh: false,
      onSuccess: (response) => _markApplicationSent(matching, response),
    );
  }

  void _markApplicationSent(JsonMap matching, JsonMap response) {
    final application = response['data'] is JsonMap
        ? response['data'] as JsonMap
        : const <String, dynamic>{};
    setState(() {
      matching['my_application'] = application['status'] ?? 'pending';
      final count = _int(matching['applications_count']);
      matching['applications_count'] = count + 1;
    });
  }

  Future<void> _decide(JsonMap matching, JsonMap application, String status) =>
      _run(
        () => _client.decideSportMatchingApplication(
          _int(matching['id']),
          _int(application['id']),
          status,
        ),
        onSuccess: (response) {
          final updated = response['data'] is JsonMap
              ? response['data'] as JsonMap
              : const <String, dynamic>{};
          final applications = _maps(matching['applications']);
          final index = applications.indexWhere(
            (item) => _int(item['id']) == _int(application['id']),
          );
          if (index >= 0) {
            setState(() {
              applications[index] = <String, dynamic>{
                ...applications[index],
                ...updated,
              };
              matching['applications'] = applications;
              if (status == 'accepted') {
                final acceptedCount = applications
                    .where((item) => item['status'] == 'accepted')
                    .length;
                matching['accepted_count'] = acceptedCount;
              }
            });
          }
          final conversationId = _int(response['conversation_id']);
          if (status != 'accepted' || conversationId <= 0 || !mounted) return;
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ChatDetailScreen(
                conversationId: conversationId,
                title: _c(
                  'Sport-Match',
                  'Sport match',
                  'Match sportif',
                  'تطابق رياضي',
                ),
                kind: 'direct',
              ),
            ),
          );
        },
        refresh: false,
      );

  Future<void> _updateAttendance(JsonMap matching, String action) async {
    if (action == 'cancel') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(
            _c(
              'Teilnahme absagen?',
              'Cancel attendance?',
              'Annuler la participation ?',
              'إلغاء الحضور؟',
            ),
          ),
          content: Text(
            _c(
              'Die andere Person wird über deine Absage informiert.',
              'The other person will be notified about your cancellation.',
              'L’autre personne sera informée de votre annulation.',
              'سيتم إبلاغ الطرف الآخر بإلغائك.',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_c('Zurück', 'Back', 'Retour', 'رجوع')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(_c('Absagen', 'Cancel', 'Annuler', 'إلغاء')),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }

    await _run(
      () => _client.updateSportMatchingAttendance(_int(matching['id']), action),
      refresh: false,
      onSuccess: (response) {
        final attendance = response['data'];
        if (attendance is JsonMap) {
          setState(() => matching['attendance'] = attendance);
        }
      },
    );
  }

  Future<void> _reportNoShow(JsonMap matching, int targetUserId) async {
    if (targetUserId <= 0) return;
    final reasonController = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          _c(
            'Nicht erschienen melden?',
            'Report a no-show?',
            'Signaler une absence ?',
            'الإبلاغ عن عدم الحضور؟',
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              _c(
                'Melde dies nur, wenn der Termin vorbei ist und die Person nicht erschienen ist.',
                'Only report this after the session has ended and the person did not show up.',
                'Signalez-le uniquement après la séance si la personne ne s’est pas présentée.',
                'أبلغ عن ذلك فقط بعد انتهاء الموعد إذا لم يحضر الشخص.',
              ),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: reasonController,
              maxLines: 3,
              maxLength: 500,
              decoration: InputDecoration(
                labelText: _c(
                  'Grund (optional)',
                  'Reason (optional)',
                  'Motif (facultatif)',
                  'السبب (اختياري)',
                ),
                hintText: _c(
                  'z. B. keine Absage erhalten',
                  'e.g. no cancellation received',
                  'ex. aucune annulation reçue',
                  'مثال: لم يصل إلغاء',
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(_c('Abbrechen', 'Cancel', 'Annuler', 'إلغاء')),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.pop(dialogContext, reasonController.text.trim()),
            child: Text(_c('Melden', 'Report', 'Signaler', 'إبلاغ')),
          ),
        ],
      ),
    );
    reasonController.dispose();
    if (reason == null || !mounted) return;
    await _run(
      () => _client.reportSportMatchingNoShow(
        _int(matching['id']),
        targetUserId,
        reason: reason,
      ),
    );
  }

  Future<bool> _run(
    Future<JsonMap> Function() action, {
    void Function(JsonMap response)? onSuccess,
    bool refresh = true,
  }) async {
    if (_busy) return false;
    setState(() => _busy = true);
    try {
      final response = await action();
      if (mounted) {
        if (refresh) _reload();
        onSuccess?.call(response);
      }
      return true;
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
      return false;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openCreate() async {
    final createMode = await showModalBottomSheet<String>(
      context: context,
      useSafeArea: true,
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              _c(
                'Wen suchst du?',
                'Who are you looking for?',
                'Qui cherches-tu ?',
                'عن من تبحث؟',
              ),
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            ListTile(
              leading: const Icon(Icons.person_outline),
              title: Text(
                _c(
                  'Sportpartner',
                  'Sport partner',
                  'Partenaire sportif',
                  'شريك رياضي',
                ),
              ),
              subtitle: Text(
                _c(
                  'Eine Person für gemeinsames Training',
                  'One person to train with',
                  'Une personne pour s’entraîner ensemble',
                  'شخص واحد للتدرب معاً',
                ),
              ),
              onTap: () => Navigator.pop(sheetContext, 'partner'),
            ),
            ListTile(
              leading: const Icon(Icons.groups_outlined),
              title: Text(
                _c(
                  'Team oder Gegner',
                  'Team or opponent',
                  'Équipe ou adversaire',
                  'فريق أو منافس',
                ),
              ),
              subtitle: Text(
                _c(
                  'Ein anderes Team zum Spielen',
                  'Another team to play against',
                  'Une autre équipe à affronter',
                  'فريق آخر للعب ضده',
                ),
              ),
              onTap: () => Navigator.pop(sheetContext, 'team'),
            ),
          ],
        ),
      ),
    );
    if (createMode == null || !mounted) return;
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
        mode: createMode,
        sports: _maps(meta['sports']),
        teams: _maps(meta['teams']),
        defaultCountryCode: AirmiusServicesScope.of(
          context,
        ).authState.user?.country,
      ),
    );
    if (payload != null) {
      await _run(
        () => _client.createSportMatching(payload),
        refresh: false,
        onSuccess: (response) {
          final matching = response['data'];
          if (matching is! JsonMap) return;
          setState(() {
            final matchings = _maps(_response['data']);
            matchings.insert(0, matching);
            _response['data'] = matchings;
          });
        },
      );
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
    var searchLatitude = _searchLatitude;
    var searchLongitude = _searchLongitude;

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
                            onChanged: (_) => setSheetState(() {
                              searchLatitude = null;
                              searchLongitude = null;
                            }),
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
                          OutlinedButton.icon(
                            onPressed: () async {
                              try {
                                if (!await Geolocator.isLocationServiceEnabled()) {
                                  throw StateError('location off');
                                }
                                var permission =
                                    await Geolocator.checkPermission();
                                if (permission == LocationPermission.denied) {
                                  permission =
                                      await Geolocator.requestPermission();
                                }
                                if (permission == LocationPermission.denied ||
                                    permission ==
                                        LocationPermission.deniedForever) {
                                  throw StateError('location denied');
                                }
                                final position =
                                    await Geolocator.getCurrentPosition(
                                      locationSettings: const LocationSettings(
                                        accuracy: LocationAccuracy.medium,
                                        timeLimit: Duration(seconds: 15),
                                      ),
                                    );
                                if (!sheetContext.mounted) return;
                                cityController.clear();
                                setSheetState(() {
                                  searchLatitude = position.latitude;
                                  searchLongitude = position.longitude;
                                });
                              } catch (_) {
                                if (sheetContext.mounted) {
                                  ScaffoldMessenger.of(
                                    sheetContext,
                                  ).showSnackBar(
                                    SnackBar(
                                      content: Text(
                                        _c(
                                          'Standort nicht verfügbar. Gib einen Ort ein.',
                                          'Location unavailable. Enter a city.',
                                          'Position indisponible. Saisis une ville.',
                                          'الموقع غير متاح. أدخل مدينة.',
                                        ),
                                      ),
                                    ),
                                  );
                                }
                              }
                            },
                            icon: Icon(
                              searchLatitude == null
                                  ? Icons.my_location
                                  : Icons.check_circle_outline,
                            ),
                            label: Text(
                              searchLatitude == null
                                  ? _c(
                                      'Aktuellen Standort verwenden',
                                      'Use current location',
                                      'Utiliser ma position',
                                      'استخدم موقعي',
                                    )
                                  : _c(
                                      'Standort für Entfernungssuche gewählt',
                                      'Location selected for distance search',
                                      'Position choisie pour la recherche',
                                      'تم اختيار الموقع للبحث بالمسافة',
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
                          if (searchLatitude != null)
                            _configurationSectionLabel(
                              context,
                              _c('Umkreis', 'Radius', 'Rayon', 'النطاق'),
                              Icons.radar_outlined,
                            ),
                          if (searchLatitude != null)
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
                          if (searchLatitude == null)
                            Text(
                              _c(
                                'Mit Stadt/PLZ wird nach dem Ortsnamen gesucht. Für einen echten km-Umkreis wähle deinen aktuellen Standort.',
                                'City search uses the place name. Select your current location for a real km radius.',
                                'La ville utilise le nom du lieu. Choisis ta position pour un vrai rayon en km.',
                                'البحث بالمدينة يستخدم اسم المكان. اختر موقعك لنطاق فعلي بالكيلومترات.',
                              ),
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
                                searchLatitude = null;
                                searchLongitude = null;
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
                                latitude: searchLatitude,
                                longitude: searchLongitude,
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
      _searchLatitude = filters.latitude;
      _searchLongitude = filters.longitude;
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
          label: Text(_c('Erstellen', 'Create', 'Créer', 'إنشاء')),
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
  final _teamSize = TextEditingController();
  final _ownTeamSize = TextEditingController();
  String _opponentSizeType = 'exact';
  bool _showDetails = false;
  double? _latitude;
  double? _longitude;
  int? _sportId;
  int? _teamId;
  int _radiusKm = 25;
  String _skillLevel = 'all';
  late String _countryCode;
  DateTime _startsAt = DateTime.now().add(const Duration(days: 1));
  DateTime? _endsAt;

  Future<void> _useCurrentMeetingPoint() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw StateError('location off');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw StateError('location denied');
      }
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 15),
        ),
      );
      if (!mounted) return;
      setState(() {
        _latitude = position.latitude;
        _longitude = position.longitude;
      });
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _copy(
                'Standort nicht verfügbar. Du kannst den Ort weiterhin manuell eingeben.',
                'Location unavailable. You can still enter the city.',
                'Position indisponible. Saisis la ville.',
                'الموقع غير متاح. يمكنك إدخال المدينة.',
              ),
            ),
          ),
        );
      }
    }
  }

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
      _teamSize,
      _ownTeamSize,
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
        if (widget.mode == 'team' && widget.teams.isEmpty) ...[
          Text(
            _copy(
              'Für eine Team-Herausforderung brauchst du zuerst ein Team.',
              'You need to join a team before creating a team challenge.',
              'Rejoignez d’abord une équipe pour créer un défi entre équipes.',
              'يجب الانضمام إلى فريق أولاً لإنشاء تحدٍ بين الفرق.',
            ),
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          Text(
            _copy(
              'Auf der Teams-Seite kannst du ein Team suchen oder deine Einladungen öffnen.',
              'On the Teams page, you can find a team or open your invitations.',
              'Sur la page Équipes, vous pouvez chercher une équipe ou consulter vos invitations.',
              'في صفحة الفرق، يمكنك البحث عن فريق أو فتح دعواتك.',
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: () => Navigator.of(context).pushReplacement(
              MaterialPageRoute<void>(
                builder: (_) => const TeamsCenterScreen(),
              ),
            ),
            icon: const Icon(Icons.groups_outlined),
            label: Text(
              _copy(
                'Teams öffnen',
                'Open teams',
                'Ouvrir les équipes',
                'افتح الفرق',
              ),
            ),
          ),
        ] else ...[
          Text(
            widget.mode == 'team'
                ? _copy(
                    'Teamgegner finden',
                    'Find an opposing team',
                    'Trouver une équipe adverse',
                    'ابحث عن فريق منافس',
                  )
                : _copy(
                    'Sportpartner finden',
                    'Find a sport partner',
                    'Trouver un partenaire sportif',
                    'ابحث عن شريك رياضي',
                  ),
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
          TextButton.icon(
            onPressed: () => setState(() => _showDetails = !_showDetails),
            icon: Icon(_showDetails ? Icons.expand_less : Icons.tune),
            label: Text(
              _copy(
                'Optionale Details',
                'Optional details',
                'Détails facultatifs',
                'تفاصيل اختيارية',
              ),
            ),
          ),
          const SizedBox(height: 14),
          if (widget.mode == 'team') ...[
            DropdownButtonFormField<int>(
              decoration: InputDecoration(
                labelText: _copy(
                  'Dein Team',
                  'Your team',
                  'Votre équipe',
                  'فريقك',
                ),
              ),
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
          if (_showDetails)
            TextField(
              controller: _title,
              decoration: InputDecoration(
                labelText: _copy(
                  'Titel (optional)',
                  'Title (optional)',
                  'Titre (facultatif)',
                  'العنوان (اختياري)',
                ),
              ),
            ),
          if (_showDetails) const SizedBox(height: 10),
          TextField(
            controller: _location,
            onChanged: (_) => setState(() {
              _latitude = null;
              _longitude = null;
            }),
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
          OutlinedButton.icon(
            onPressed: _useCurrentMeetingPoint,
            icon: Icon(
              _latitude == null
                  ? Icons.my_location
                  : Icons.check_circle_outline,
            ),
            label: Text(
              _latitude == null
                  ? _copy(
                      'Aktuellen Standort als Treffpunkt nutzen',
                      'Use current location as meeting point',
                      'Utiliser ma position comme rendez-vous',
                      'استخدم موقعي الحالي كنقطة لقاء',
                    )
                  : _copy(
                      'Treffpunkt mit Standort gespeichert',
                      'Meeting point location selected',
                      'Position du rendez-vous sélectionnée',
                      'تم اختيار موقع اللقاء',
                    ),
            ),
          ),
          const SizedBox(height: 10),
          if (_showDetails)
            TextField(
              controller: _postalCode,
              keyboardType: TextInputType.streetAddress,
              decoration: InputDecoration(
                labelText: _copy(
                  'PLZ (optional)',
                  'Postcode (optional)',
                  'Code postal (facultatif)',
                  'الرمز البريدي (اختياري)',
                ),
                hintText: '14000',
                prefixIcon: const Icon(Icons.local_post_office_outlined),
              ),
            ),
          if (_showDetails) const SizedBox(height: 10),
          if (_showDetails)
            TextField(
              controller: _locationName,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(
                labelText: _copy(
                  'Treffpunkt (optional)',
                  'Meeting point (optional)',
                  'Lieu de rendez-vous (facultatif)',
                  'نقطة اللقاء (اختياري)',
                ),
                hintText: _copy(
                  'z. B. Stadtpark',
                  'e.g. city park',
                  'ex. parc municipal',
                  'مثل الحديقة العامة',
                ),
                prefixIcon: const Icon(Icons.place_outlined),
              ),
            ),
          if (_showDetails) const SizedBox(height: 10),
          if (_showDetails)
            TextField(
              controller: _address,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(
                labelText: _copy(
                  'Adresse (optional)',
                  'Address (optional)',
                  'Adresse (facultatif)',
                  'العنوان (اختياري)',
                ),
                hintText: _copy(
                  'Straße und Hausnummer',
                  'Street and number',
                  'Rue et numéro',
                  'الشارع والرقم',
                ),
                prefixIcon: const Icon(Icons.signpost_outlined),
              ),
            ),
          if (_showDetails) const SizedBox(height: 10),
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
          if (widget.mode == 'team') ...[
            TextField(
              controller: _ownTeamSize,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: _copy(
                  'Eigene Teamgröße',
                  'Your team size',
                  'Taille de votre équipe',
                  'حجم فريقك',
                ),
              ),
            ),
            const SizedBox(height: 10),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(
                  value: 'exact',
                  label: Text(
                    _copy('Genau', 'Exactly', 'Exactement', 'بالضبط'),
                  ),
                ),
                ButtonSegment(
                  value: 'minimum',
                  label: Text(
                    _copy('Mindestens', 'At least', 'Au moins', 'على الأقل'),
                  ),
                ),
              ],
              selected: {_opponentSizeType},
              onSelectionChanged: (value) =>
                  setState(() => _opponentSizeType = value.first),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _teamSize,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: _opponentSizeType == 'minimum'
                    ? _copy(
                        'Gegner: mindestens Personen',
                        'Opponent: at least',
                        'Adversaire : au moins',
                        'الخصم: على الأقل',
                      )
                    : _copy(
                        'Gegner: genau Personen',
                        'Opponent: exactly',
                        'Adversaire : exactement',
                        'الخصم: بالضبط',
                      ),
              ),
            ),
            const SizedBox(height: 10),
          ] else ...[
            Text(
              _copy(
                'Du suchst genau eine Person zum gemeinsamen Sport.',
                'You are looking for one person to exercise with.',
                'Tu cherches une personne pour faire du sport.',
                'تبحث عن شخص واحد لممارسة الرياضة معه.',
              ),
            ),
            const SizedBox(height: 10),
          ],
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(
              _copy(
                'Datum und Uhrzeit',
                'Date and time',
                'Date et heure',
                'التاريخ والوقت',
              ),
            ),
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
          if (_showDetails)
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
          if (_showDetails) const SizedBox(height: 4),
          if (_showDetails)
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
          if (_showDetails)
            Slider(
              value: _radiusKm.toDouble(),
              min: 5,
              max: 500,
              divisions: 99,
              label: '$_radiusKm km',
              onChanged: (value) => setState(() => _radiusKm = value.round()),
            ),
          if (_showDetails)
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
                  DropdownMenuItem(
                    value: level,
                    child: Text(_skillLabel(level)),
                  ),
              ],
              onChanged: (value) {
                if (value != null) setState(() => _skillLevel = value);
              },
            ),
          if (_showDetails) const SizedBox(height: 10),
          if (_showDetails)
            TextField(
              controller: _description,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: _copy(
                  'Beschreibung',
                  'Description',
                  'Description',
                  'الوصف',
                ),
              ),
            ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: _submit,
            child: Text(_copy('Veröffentlichen', 'Publish', 'Publier', 'نشر')),
          ),
        ],
      ],
    ),
  );

  void _submit() {
    final opponentSize = int.tryParse(_teamSize.text);
    final ownSize = int.tryParse(_ownTeamSize.text);
    if (_sportId == null ||
        _location.text.trim().isEmpty ||
        !_startsAt.isAfter(DateTime.now()) ||
        (widget.mode == 'team' &&
            (_teamId == null ||
                opponentSize == null ||
                opponentSize < 1 ||
                opponentSize > 500 ||
                ownSize == null ||
                ownSize < 1 ||
                ownSize > 500))) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _copy(
              'Bitte Sportart, Ort, zukünftigen Termin und gültige Teamgrößen angeben.',
              'Enter a sport, city, future date and valid team sizes.',
              'Saisissez un sport, une ville, une date future et des tailles d’équipe valides.',
              'أدخل الرياضة والمدينة وموعدًا قادمًا وأحجام الفرق الصحيحة.',
            ),
          ),
        ),
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
      if (_latitude != null && _longitude != null) ...{
        'latitude': _latitude,
        'longitude': _longitude,
      },
      'radius_km': _radiusKm,
      'starts_at': _startsAt.toUtc().toIso8601String(),
      if (_endsAt != null) 'ends_at': _endsAt!.toUtc().toIso8601String(),
      'participants_needed': 1,
      'team_size': widget.mode == 'team' ? int.tryParse(_teamSize.text) : null,
      'own_team_size': widget.mode == 'team'
          ? int.tryParse(_ownTeamSize.text)
          : null,
      'opponent_size_type': widget.mode == 'team' ? _opponentSizeType : 'exact',
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
    required this.latitude,
    required this.longitude,
  });

  final String mode;
  final bool swipeView;
  final String city;
  final String sportName;
  final int? sportId;
  final int radiusKm;
  final String skillLevel;
  final double? latitude;
  final double? longitude;
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
