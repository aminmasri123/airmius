import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'learning_studio_course_suite_screen.dart';
import 'lesson_detail_screen.dart';

class LearningScreen extends StatefulWidget {
  const LearningScreen({super.key, this.initialQuery = ''});

  final String initialQuery;

  @override
  State<LearningScreen> createState() => _LearningScreenState();
}

class _LearningScreenState extends State<LearningScreen> {
  Future<JsonMap>? _future;
  String _section = 'mine';
  String _query = '';
  bool _busy = false;

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
    _future ??= _client.learning();
  }

  void _reload() {
    setState(() {
      _future = _client.learning();
    });
  }

  Future<void> _enroll(JsonMap course) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('learning.enrollTitle')),
        content: Text(
          t(
            'learning.enrollQuestion',
          ).replaceFirst('{course}', _learnText(course['title'])),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('learning.enroll')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.enrollLearningCourse(_learnInt(course['id']));
      if (!mounted) return;
      _toast(t('learning.enrolled'));
      _reload();
      setState(() => _section = 'mine');
    } catch (error) {
      if (mounted) {
        _toast(
          '${t('learning.actionError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openCourse(JsonMap course) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => LessonDetailScreen(
          courseId: _learnInt(course['id']),
          initialTitle: _learnText(course['title']),
        ),
      ),
    );
    if (mounted) _reload();
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
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
          IconButton(
            tooltip: t('learning.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('learning.title'),
        subtitle: t('learning.subtitle'),
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
    final normalized = _query.trim().toLowerCase();
    final courses =
        (_section == 'mine'
                ? enrollments
                      .map((item) => _learnMap(item['course']))
                      .where((item) => item.isNotEmpty)
                      .toList()
                : catalog)
            .where((course) {
              if (normalized.isEmpty) return true;
              return [
                course['title'],
                course['subtitle'],
                course['description'],
                course['category'],
                _learnMap(course['tutor'])['name'],
              ].any(
                (value) => _learnText(value).toLowerCase().contains(normalized),
              );
            })
            .toList();
    final averageProgress = enrollments.isEmpty
        ? 0
        : (enrollments.fold<int>(
                    0,
                    (sum, item) => sum + _learnInt(item['progress_percent']),
                  ) /
                  enrollments.length)
              .round();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('learning.overview')),
              const SizedBox(height: 8),
              Text(
                t('learning.overviewHint'),
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
                      value: '${enrollments.length}',
                      label: t('learning.myCourses'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${certificates.length}',
                      label: t('learning.certificates'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '$averageProgress%',
                      label: t('learning.progress'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(
              value: 'mine',
              icon: Icon(Icons.school_outlined),
              label: Text(t('learning.myCourses')),
            ),
            ButtonSegment(
              value: 'catalog',
              icon: Icon(Icons.explore_outlined),
              label: Text(t('learning.catalog')),
            ),
            ButtonSegment(
              value: 'certificates',
              icon: Icon(Icons.verified_outlined),
              label: Text(t('learning.certificates')),
            ),
          ],
          selected: {_section},
          showSelectedIcon: false,
          onSelectionChanged: (selection) =>
              setState(() => _section = selection.first),
        ),
        const SizedBox(height: 14),
        if (_section != 'certificates') ...[
          SearchBox(
            hint: t('learning.search'),
            onChanged: (value) => setState(() => _query = value),
          ),
          const SizedBox(height: 14),
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
                onEnroll: () => _enroll(courses[index]),
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
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({
    required this.course,
    required this.busy,
    required this.onOpen,
    required this.onEnroll,
  });

  final JsonMap course;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback onEnroll;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final enrolled = _learnBool(course['is_enrolled']);
    final free = _learnBool(course['is_free']);
    final progress = _learnInt(course['progress_percent']);
    final tutor = _learnMap(course['tutor']);
    return AirmiusPanel(
      onTap: enrolled ? onOpen : null,
      borderColor: enrolled
          ? Theme.of(context).colorScheme.secondary.withValues(alpha: .45)
          : airmiusBorderColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.secondary.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Icon(
                  Icons.play_lesson_outlined,
                  color: Theme.of(context).colorScheme.secondary,
                  size: 31,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _learnText(
                        course['title'],
                        fallback: t('learning.course'),
                      ),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      _learnText(
                        course['subtitle'] ?? course['description'],
                        fallback: t('learning.noDescription'),
                      ),
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 9),
                    Wrap(
                      spacing: 7,
                      runSpacing: 7,
                      children: [
                        StatusPill(
                          t(
                            'learning.level.${_learnText(course['level'], fallback: 'beginner')}',
                          ),
                        ),
                        StatusPill(
                          t('learning.lessonCount').replaceFirst(
                            '{count}',
                            '${_learnInt(course['lessons_count'])}',
                          ),
                          color: airmiusAccentColor(context),
                        ),
                        StatusPill(
                          free ? t('learning.free') : _learningPrice(course),
                          color: free
                              ? Theme.of(context).colorScheme.secondary
                              : Theme.of(context).colorScheme.tertiary,
                        ),
                      ],
                    ),
                    if (_learnText(tutor['name']).isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        t(
                          'learning.byTutor',
                        ).replaceFirst('{name}', _learnText(tutor['name'])),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (enrolled) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(99),
                    child: LinearProgressIndicator(
                      value: progress.clamp(0, 100) / 100,
                      minHeight: 10,
                      backgroundColor: airmiusSurfaceSoftColor(context),
                      valueColor: AlwaysStoppedAnimation<Color>(
                        Theme.of(context).colorScheme.secondary,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  '$progress%',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 14),
          AirmiusButton(
            label: enrolled
                ? t('learning.continueCourse')
                : free
                ? t('learning.enroll')
                : t('learning.paidCourse'),
            icon: enrolled
                ? Icons.play_arrow_outlined
                : free
                ? Icons.add_circle_outline
                : Icons.shopping_bag_outlined,
            secondary: !enrolled,
            onPressed: busy
                ? null
                : enrolled
                ? onOpen
                : free
                ? onEnroll
                : () => _paidCourseInfo(context),
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

Future<void> _paidCourseInfo(BuildContext context) {
  final t = AirmiusScope.of(context).t;
  return showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(t('learning.paidTitle')),
      content: Text(t('learning.paidHint')),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('learning.understood')),
        ),
      ],
    ),
  );
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

bool _learnBool(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';

String _learningPrice(JsonMap course) {
  final cents = _learnInt(course['price_cents']);
  final currency = _learnText(course['currency'], fallback: 'EUR');
  return '${(cents / 100).toStringAsFixed(2).replaceAll('.', ',')} $currency';
}
