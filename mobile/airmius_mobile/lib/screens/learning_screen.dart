import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'learning_quality_review_screen.dart';
import 'learning_studio_course_suite_screen.dart';
import 'lesson_detail_screen.dart';
import 'learning_course_detail_screen.dart';
import '../core/learning_experience_copy.dart';
import '../widgets/learning_course_cover.dart';
import '../widgets/learning_participation_confirmation.dart';

class LearningScreen extends StatefulWidget {
  const LearningScreen({super.key, this.initialQuery = ''});

  final String initialQuery;

  @override
  State<LearningScreen> createState() => _LearningScreenState();
}

class _LearningScreenState extends State<LearningScreen> {
  Future<JsonMap>? _future;
  String _section = 'catalog';
  String _category = '', _level = '', _price = '', _language = '';
  String _query = '';
  bool _busy = false;
  String _participationView = 'active';

  Future<void> _participation(JsonMap course, String action) async {
    if (_busy ||
        !await confirmLearningParticipation(
          context,
          _learnText(course['title']),
          action,
        ) ||
        !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await _client.updateLearningParticipation(
        _learnInt(course['enrollment_id']),
        action,
      );
      if (mounted) _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is AirmiusApiException
                  ? error.userMessage
                  : AirmiusScope.of(context).t('learning.actionError'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery;
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<JsonMap> _load() async {
    final response = await _client.learning(language: _language);
    return response;
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _openCourse(
    JsonMap course, {
    bool continueLearning = false,
  }) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => continueLearning
            ? LessonDetailScreen(
                courseId: _learnInt(course['id']),
                initialTitle: _learnText(course['title']),
                initialLessonId:
                    _learnInt(_learnMap(course['next_lesson'])['id']) == 0
                    ? null
                    : _learnInt(_learnMap(course['next_lesson'])['id']),
              )
            : LearningCourseDetailScreen(courseId: _learnInt(course['id'])),
      ),
    );
    if (mounted) _reload();
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
          t('learning.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('studio.open'),
            onPressed: _busy
                ? null
                : () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const LearningStudioCourseSuiteScreen(),
                      ),
                    );
                    if (mounted) _reload();
                  },
            icon: Icon(Icons.school_outlined),
          ),
          if (AirmiusServicesScope.of(
                context,
              ).authState.user?.can('subscriptions.manage') ==
              true)
            IconButton(
              tooltip: 'Course quality review',
              icon: const Icon(Icons.fact_check_outlined),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const LearningQualityReviewScreen(),
                ),
              ),
            ),
          IconButton(
            tooltip: t('learning.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: learningText(
          context,
          _section == 'catalog'
              ? 'intro'
              : _section == 'mine'
              ? 'mine'
              : 'certificates',
        ),
        subtitle: '',
        child: FutureBuilder<JsonMap>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _LearningLoading();
            }
            if (snapshot.hasError) {
              return _LearningError(error: snapshot.error, onRetry: _reload);
            }
            return _content(_learnMap(snapshot.data?['data']));
          },
        ),
      ),
    );
  }

  Widget _content(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final catalog = _learnMaps(data['catalog']);
    final enrollments = _learnMaps(data['enrollments']);
    final certificates = _learnMaps(data['certificates']);
    final courses =
        (_section == 'mine'
                ? enrollments
                      .where(
                        (e) => _participationView == 'hidden'
                            ? e['hidden_at'] != null
                            : e['hidden_at'] == null &&
                                  (_participationView == 'active'
                                      ? e['status'] == 'active'
                                      : _participationView == 'left'
                                      ? e['status'] == 'left'
                                      : ![
                                          'active',
                                          'left',
                                        ].contains(e['status'])),
                      )
                      .map(
                        (e) => {
                          ..._learnMap(e['course']),
                          'next_lesson': e['next_lesson'],
                          'enrollment_id': e['id'],
                          'participation_status': e['status'],
                          'hidden_at': e['hidden_at'],
                        },
                      )
                      .toList()
                : catalog)
            .where((course) {
              final match =
                  [
                    course['title'],
                    course['subtitle'],
                    course['description'],
                    course['category'],
                    _learnMap(course['tutor'])['name'],
                  ].any(
                    (v) => '${v ?? ''}'.toLowerCase().contains(
                      _query.trim().toLowerCase(),
                    ),
                  );
              return match &&
                  (_section != 'catalog' ||
                      ((_category.isEmpty || course['category'] == _category) &&
                          (_level.isEmpty || course['level'] == _level) &&
                          (_price.isEmpty ||
                              (course['is_free'] == true ? 'free' : 'paid') ==
                                  _price)));
            })
            .toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final entry in {
              'catalog': 'discover',
              'mine': 'mine',
              'certificates': 'certificates',
            }.entries)
              ChoiceChip(
                label: Text(learningText(context, entry.value)),
                selected: _section == entry.key,
                onSelected: (_) => setState(() => _section = entry.key),
              ),
          ],
        ),
        const SizedBox(height: 16),
        if (_section != 'certificates') ...[
          if (_section == 'mine') ...[
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final view in ['active', 'left', 'hidden', 'history'])
                  ChoiceChip(
                    label: Text(learningText(context, view)),
                    selected: _participationView == view,
                    onSelected: (_) =>
                        setState(() => _participationView = view),
                  ),
              ],
            ),
            const SizedBox(height: 12),
          ],
          SearchBox(
            hint: learningText(context, 'search'),
            onChanged: (value) => setState(() => _query = value),
          ),
          const SizedBox(height: 14),
          if (_section == 'catalog') ...[
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _filter(
                  'category',
                  _category,
                  catalog
                      .map((c) => '${c['category'] ?? ''}')
                      .where((v) => v.isNotEmpty)
                      .toSet()
                      .toList(),
                  (v) => setState(() => _category = v),
                ),
                _filter('level', _level, [
                  'beginner',
                  'intermediate',
                  'advanced',
                ], (v) => setState(() => _level = v)),
                _filter('price', _price, [
                  'free',
                  'paid',
                ], (v) => setState(() => _price = v)),
                _filter('language', _language, ['de', 'en', 'fr', 'ar'], (v) {
                  setState(() => _language = v);
                  _reload();
                }),
              ],
            ),
            if ([_category, _level, _price, _language].any((v) => v.isNotEmpty))
              TextButton(
                onPressed: () {
                  setState(() {
                    _category = '';
                    _level = '';
                    _price = '';
                    _language = '';
                  });
                  _reload();
                },
                child: Text(learningText(context, 'reset')),
              ),
            const SizedBox(height: 14),
          ],
          if (courses.isEmpty)
            _LearningEmpty(
              icon: _section == 'mine'
                  ? Icons.school_outlined
                  : Icons.explore_outlined,
              title: t(
                _section == 'mine'
                    ? 'learning.emptyMine'
                    : 'learning.emptyCatalog',
              ),
              hint: t(
                _section == 'mine'
                    ? 'learning.emptyMineHint'
                    : 'learning.emptyCatalogHint',
              ),
              action: _section == 'mine'
                  ? AirmiusButton(
                      label: t('learning.browse'),
                      icon: Icons.explore_outlined,
                      onPressed: () => setState(() => _section = 'catalog'),
                    )
                  : null,
            )
          else
            for (var index = 0; index < courses.length; index++) ...[
              _CourseCard(
                course: courses[index],
                busy: _busy,
                onOpen: () => _openCourse(courses[index]),
                onContinue:
                    _section == 'mine' &&
                        courses[index]['participation_status'] == 'active'
                    ? () => _openCourse(courses[index], continueLearning: true)
                    : null,
                onAction: _section == 'mine'
                    ? (action) => _participation(courses[index], action)
                    : null,
              ),
              if (index < courses.length - 1) const SizedBox(height: 12),
            ],
        ] else if (certificates.isEmpty)
          _LearningEmpty(
            icon: Icons.verified_outlined,
            title: t('learning.emptyCertificates'),
            hint: t('learning.emptyCertificatesHint'),
          )
        else
          for (var index = 0; index < certificates.length; index++) ...[
            _CertificateCard(certificate: certificates[index]),
            if (index < certificates.length - 1) const SizedBox(height: 12),
          ],
      ],
    );
  }

  Widget _filter(
    String key,
    String value,
    List<String> values,
    ValueChanged<String> changed,
  ) => SizedBox(
    width: 160,
    child: DropdownButtonFormField<String>(
      key: ValueKey('filter-$key-$value'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: learningText(context, key)),
      items: [
        DropdownMenuItem(value: '', child: Text(learningText(context, 'all'))),
        for (final v in {...values, if (value.isNotEmpty) value})
          DropdownMenuItem(
            value: v,
            child: Text(
              key == 'price'
                  ? learningText(context, v)
                  : key == 'level'
                  ? AirmiusScope.of(context).t('learning.level.$v')
                  : key == 'language'
                  ? AirmiusLanguage.values
                        .firstWhere((l) => l.locale.languageCode == v)
                        .label
                  : v.toUpperCase(),
              overflow: TextOverflow.ellipsis,
            ),
          ),
      ],
      onChanged: (v) => changed(v ?? ''),
    ),
  );
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({
    required this.course,
    required this.busy,
    required this.onOpen,
    this.onContinue,
    this.onAction,
  });
  final JsonMap course;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback? onContinue;
  final ValueChanged<String>? onAction;
  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final progress = _learnInt(course['progress_percent']);
    return AirmiusPanel(
      onTap: onOpen,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (onAction != null)
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: PopupMenuButton<String>(
                tooltip: learningText(context, 'overview'),
                onSelected: busy ? null : onAction,
                itemBuilder: (context) => [
                  if (course['participation_status'] == 'active')
                    PopupMenuItem(
                      value: 'leave',
                      child: Text(learningText(context, 'leave')),
                    ),
                  if (course['participation_status'] == 'left')
                    PopupMenuItem(
                      value: 'resume',
                      child: Text(learningText(context, 'resume')),
                    ),
                  PopupMenuItem(
                    value: course['hidden_at'] != null ? 'show' : 'hide',
                    child: Text(
                      learningText(
                        context,
                        course['hidden_at'] != null ? 'show' : 'hide',
                      ),
                    ),
                  ),
                ],
              ),
            ),
          LearningCourseCover(
            url: course['cover_image'] as String?,
            title: _learnText(course['title']),
          ),
          const SizedBox(height: 12),
          Text(
            _learnText(course['title']),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          Text(
            _learnText(course['subtitle'] ?? course['description']),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 10),
          Text(
            '${learningText(context, 'instructor')}: ${_learnMap(course['tutor'])['name'] ?? ''}',
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 10,
            runSpacing: 8,
            children: [
              Text(t('learning.level.${course['level']}')),
              Text(
                '${course['estimated_minutes'] ?? 0} ${learningText(context, 'minutes')}',
              ),
              Text(
                '${course['lessons_count'] ?? 0} ${learningText(context, _learnInt(course['lessons_count']) == 1 ? 'lesson' : 'lessons')}',
              ),
              Text(
                course['is_free'] == true
                    ? learningText(context, 'free')
                    : _learningPrice(course),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              if (_learnInt(course['reviews_count']) > 0)
                Text(
                  '★ ${course['average_rating']} (${course['reviews_count']})',
                ),
            ],
          ),
          if (onContinue != null) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(value: progress.clamp(0, 100) / 100),
            const SizedBox(height: 6),
            Text('$progress%'),
            if (_learnMap(course['next_lesson']).isNotEmpty)
              Text(
                '${learningText(context, 'next')}: ${_learnMap(course['next_lesson'])['title']}',
              ),
          ],
          const SizedBox(height: 12),
          AirmiusButton(
            label: learningText(
              context,
              onContinue != null ? 'continue' : 'details',
            ),
            icon: onContinue != null ? Icons.play_arrow : Icons.arrow_forward,
            onPressed: busy ? null : (onContinue ?? onOpen),
          ),
        ],
      ),
    );
  }
}

class _CertificateCard extends StatelessWidget {
  const _CertificateCard({required this.certificate});

  final JsonMap certificate;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final code = _learnText(certificate['code']);
    return AirmiusPanel(
      borderColor: Theme.of(
        context,
      ).colorScheme.tertiary.withValues(alpha: .55),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.verified_outlined,
            color: Theme.of(context).colorScheme.tertiary,
            size: 34,
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _learnText(
                    certificate['course_title'],
                    fallback: t('learning.certificate'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(code, style: TextStyle(color: airmiusMutedColor(context))),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    OutlinedButton.icon(
                      onPressed: () async {
                        await Clipboard.setData(ClipboardData(text: code));
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text(t('learning.codeCopied'))),
                          );
                        }
                      },
                      icon: Icon(Icons.copy_outlined),
                      label: Text(t('learning.copyCode')),
                    ),
                    OutlinedButton.icon(
                      onPressed: () => _openVerifyUrl(
                        context,
                        _learnText(certificate['verify_url']),
                      ),
                      icon: Icon(Icons.open_in_new),
                      label: Text(t('learning.verify')),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

Future<void> _openVerifyUrl(BuildContext context, String value) async {
  final t = AirmiusScope.of(context).t;
  final uri = safeExternalHttpUrl(value);
  if (uri == null) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(t('learning.invalidVerifyUrl'))));
    return;
  }
  if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
      context.mounted) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(t('learning.openFailed'))));
  }
}

class _LearningEmpty extends StatelessWidget {
  const _LearningEmpty({
    required this.icon,
    required this.title,
    required this.hint,
    this.action,
  });

  final IconData icon;
  final String title;
  final String hint;
  final Widget? action;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Column(
      children: [
        Icon(icon, color: Theme.of(context).colorScheme.secondary, size: 43),
        const SizedBox(height: 12),
        Text(
          title,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          hint,
          textAlign: TextAlign.center,
          style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
        ),
        if (action != null) ...[const SizedBox(height: 14), action!],
      ],
    ),
  );
}

class _LearningLoading extends StatelessWidget {
  const _LearningLoading();

  @override
  Widget build(BuildContext context) => const AirmiusPanel(
    child: Padding(
      padding: EdgeInsets.all(30),
      child: Center(child: CircularProgressIndicator()),
    ),
  );
}

class _LearningError extends StatelessWidget {
  const _LearningError({required this.error, required this.onRetry});

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
            t('learning.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('learning.retry'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _learnMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _learnMaps(Object? value) => value is List
    ? value.map(_learnMap).where((item) => item.isNotEmpty).toList()
    : const [];

String _learnText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _learnInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

String _learningPrice(JsonMap course) {
  final cents = _learnInt(course['price_cents']);
  final currency = _learnText(course['currency'], fallback: 'EUR');
  return NumberFormat.simpleCurrency(name: currency).format(cents / 100);
}
