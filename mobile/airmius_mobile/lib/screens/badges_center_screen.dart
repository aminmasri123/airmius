import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class BadgesCenterScreen extends StatefulWidget {
  const BadgesCenterScreen({super.key});

  @override
  State<BadgesCenterScreen> createState() => _BadgesCenterScreenState();
}

class _BadgesCenterScreenState extends State<BadgesCenterScreen> {
  Future<List<JsonMap>>? _awardsFuture;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _awardsFuture ??= _load();
  }

  Future<List<JsonMap>> _load() async {
    final response = await _client.badges();
    return _badgeMaps(response['data']);
  }

  void _reload() {
    setState(() {
      _awardsFuture = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('badges.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('badges.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('badges.title'),
        subtitle: t('badges.subtitle'),
        child: FutureBuilder<List<JsonMap>>(
          future: _awardsFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _BadgeLoading();
            }
            if (snapshot.hasError) {
              return _BadgeError(error: snapshot.error, onRetry: _reload);
            }
            return _BadgeContent(awards: snapshot.data ?? const []);
          },
        ),
      ),
    );
  }
}

class _BadgeContent extends StatelessWidget {
  const _BadgeContent({required this.awards});

  final List<JsonMap> awards;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final xp = awards.fold<int>(
      0,
      (sum, award) => sum + _badgeInt(_badgeMap(award['meta'])['xp']),
    );
    final actorTypes = awards
        .map((award) => _badgeText(_badgeMap(award['badge'])['actor_type']))
        .where((type) => type.isNotEmpty)
        .toSet()
        .length;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('badges.achievements')),
              const SizedBox(height: 8),
              Text(
                t('badges.overviewHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: MetricCard(
                      value: '${awards.length}',
                      label: t('badges.received'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '$xp',
                      label: t('badges.xpAtAwards'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '$actorTypes',
                      label: t('badges.areas'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        if (awards.isEmpty)
          AirmiusPanel(
            child: Column(
              children: [
                Icon(
                  Icons.workspace_premium_outlined,
                  size: 42,
                  color: Theme.of(context).colorScheme.tertiary,
                ),
                const SizedBox(height: 12),
                Text(
                  t('badges.empty'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  t('badges.emptyHint'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          )
        else
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 760
                  ? 3
                  : constraints.maxWidth >= 480
                  ? 2
                  : 1;
              return GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: awards.length,
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                  childAspectRatio: columns == 1 ? 2.35 : 1.15,
                ),
                itemBuilder: (context, index) =>
                    _AwardCard(award: awards[index]),
              );
            },
          ),
      ],
    );
  }
}

class _AwardCard extends StatelessWidget {
  const _AwardCard({required this.award});

  final JsonMap award;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final badge = _badgeMap(award['badge']);
    final meta = _badgeMap(award['meta']);
    final name = _badgeText(badge['name'], fallback: t('badges.badge'));
    final description = _badgeText(
      badge['description'],
      fallback: t('badges.noDescription'),
    );
    final actorType = _badgeText(badge['actor_type']);
    final xp = _badgeInt(meta['xp']);
    return AirmiusPanel(
      onTap: () => _showAward(context, award),
      borderColor: Theme.of(
        context,
      ).colorScheme.tertiary.withValues(alpha: .45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.tertiary.withValues(alpha: .15),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  Icons.workspace_premium_outlined,
                  color: Theme.of(context).colorScheme.tertiary,
                  size: 29,
                ),
              ),
              const Spacer(),
              if (xp > 0)
                StatusPill(
                  '+$xp XP',
                  color: Theme.of(context).colorScheme.secondary,
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Expanded(
            child: Text(
              description,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              if (actorType.isNotEmpty)
                Expanded(
                  child: Text(
                    actorType,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusAccentColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                )
              else
                const Spacer(),
              Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
            ],
          ),
        ],
      ),
    );
  }
}

Future<void> _showAward(BuildContext context, JsonMap award) {
  final t = AirmiusScope.of(context).t;
  final badge = _badgeMap(award['badge']);
  final meta = _badgeMap(award['meta']);
  final reason = _badgeText(
    award['reason'] ?? badge['trigger'],
    fallback: t('badges.notSpecified'),
  );
  final actorType = _badgeText(
    badge['actor_type'],
    fallback: t('badges.notSpecified'),
  );
  final awardedAt = _badgeDate(award['awarded_at']);
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    backgroundColor: airmiusSurfaceColor(context),
    builder: (context) => SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(
              Icons.workspace_premium_outlined,
              size: 58,
              color: Theme.of(context).colorScheme.tertiary,
            ),
            const SizedBox(height: 12),
            Text(
              _badgeText(badge['name'], fallback: t('badges.badge')),
              textAlign: TextAlign.center,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 24,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _badgeText(
                badge['description'],
                fallback: t('badges.noDescription'),
              ),
              textAlign: TextAlign.center,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
            const SizedBox(height: 20),
            _AwardDetail(label: t('badges.type'), value: actorType),
            _AwardDetail(label: t('badges.reason'), value: reason),
            if (_badgeInt(meta['xp']) > 0)
              _AwardDetail(
                label: t('badges.xp'),
                value: '${_badgeInt(meta['xp'])}',
              ),
            if (_badgeInt(meta['level']) > 0)
              _AwardDetail(
                label: t('badges.level'),
                value: '${_badgeInt(meta['level'])}',
              ),
            if (awardedAt.isNotEmpty)
              _AwardDetail(label: t('badges.receivedOn'), value: awardedAt),
            const SizedBox(height: 14),
            AirmiusButton(
              label: t('badges.close'),
              icon: Icons.close,
              secondary: true,
              onPressed: () => Navigator.pop(context),
            ),
          ],
        ),
      ),
    ),
  );
}

class _AwardDetail extends StatelessWidget {
  const _AwardDetail({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 118,
            child: Text(
              label,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BadgeLoading extends StatelessWidget {
  const _BadgeLoading();

  @override
  Widget build(BuildContext context) => const AirmiusPanel(
    child: Padding(
      padding: EdgeInsets.all(30),
      child: Center(child: CircularProgressIndicator()),
    ),
  );
}

class _BadgeError extends StatelessWidget {
  const _BadgeError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error as AirmiusApiException).userMessage
        : t('common.errorDetails');
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('badges.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('badges.retry'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _badgeMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _badgeMaps(Object? value) => value is List
    ? value.map(_badgeMap).where((item) => item.isNotEmpty).toList()
    : const [];

String _badgeText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _badgeInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

String _badgeDate(Object? value) {
  final date = DateTime.tryParse('$value')?.toLocal();
  if (date == null) return '';
  return '${date.day.toString().padLeft(2, '0')}.'
      '${date.month.toString().padLeft(2, '0')}.${date.year}';
}
