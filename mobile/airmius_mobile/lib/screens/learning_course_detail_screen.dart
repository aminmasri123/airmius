import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_external_url.dart';
import '../core/learning_experience_copy.dart';
import '../widgets/learning_course_cover.dart';
import '../widgets/airmius_inline_video.dart';
import '../widgets/learning_participation_confirmation.dart';
import 'lesson_detail_screen.dart';
import 'marketplace_screen.dart';

class LearningCourseDetailScreen extends StatefulWidget {
  const LearningCourseDetailScreen({super.key, required this.courseId});
  final int courseId;
  @override
  State<LearningCourseDetailScreen> createState() =>
      _LearningCourseDetailScreenState();
}

class _LearningCourseDetailScreenState
    extends State<LearningCourseDetailScreen> {
  Future<JsonMap>? _future;
  bool _busy = false;
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.learningCourse(widget.courseId);
  }

  void _reload() {
    final future = _client.learningCourse(widget.courseId);
    setState(() {
      _future = future;
    });
  }

  Future<void> _action(JsonMap data) async {
    final course = _map(data['course']);
    if (course['can_resume'] == true) {
      if (!await confirmLearningParticipation(
            context,
            '${course['title']}',
            'resume',
          ) ||
          !mounted) {
        return;
      }
      setState(() => _busy = true);
      try {
        await _client.updateLearningParticipation(
          course['enrollment_id'] as int,
          'resume',
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
      return;
    }
    if (data['can_use_learning_room'] == true) {
      final lessons = _maps(
        data['sections'],
      ).expand((s) => _maps(s['lessons']));
      final next = lessons
          .where((l) => l['completed'] != true && l['locked'] != true)
          .firstOrNull;
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => LessonDetailScreen(
            courseId: widget.courseId,
            initialTitle: '${course['title']}',
            initialLessonId: next?['id'] as int?,
          ),
        ),
      );
      if (mounted) _reload();
      return;
    }
    if (course['is_free'] != true) {
      final product = course['purchase_product_id'];
      if (product is! int) return;
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => MarketplaceScreen(
            initialProductId: product,
            initialProductTitle: '${course['title']}',
          ),
        ),
      );
      if (mounted) _reload();
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(learningText(context, 'enroll')),
        content: Text('${course['title']}'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(AirmiusScope.of(context).t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(learningText(context, 'enroll')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.enrollLearningCourse(widget.courseId);
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
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(learningText(context, 'overview'))),
    body: FutureBuilder<JsonMap>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(
            child: TextButton(
              onPressed: _reload,
              child: Text(AirmiusScope.of(context).t('learning.retry')),
            ),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final data = _map(snapshot.data!['data']);
        final course = _map(data['course']);
        final access = data['can_use_learning_room'] == true;
        final free = course['is_free'] == true;
        final lessons = _maps(
          data['sections'],
        ).expand((s) => _maps(s['lessons'])).toList();
        final previews = lessons
            .where((l) => l['is_preview'] == true && l['locked'] != true)
            .toList();
        final price = free
            ? learningText(context, 'free')
            : NumberFormat.simpleCurrency(
                locale: Localizations.localeOf(context).toLanguageTag(),
                name: '${course['currency'] ?? 'EUR'}',
              ).format(((course['price_cents'] as num?) ?? 0) / 100);
        return Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  LearningCourseCover(
                    url: course['cover_image'] as String?,
                    title: '${course['title']}',
                  ),
                  const SizedBox(height: 16),
                  Text(
                    '${course['title']}',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text('${course['subtitle'] ?? ''}'),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 12,
                    runSpacing: 8,
                    children: [
                      Text(
                        price,
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      Text(
                        '${course['estimated_minutes'] ?? 0} ${learningText(context, 'minutes')}',
                      ),
                      Text(
                        '${lessons.length} ${learningText(context, lessons.length == 1 ? 'lesson' : 'lessons')}',
                      ),
                      Text('${course['language'] ?? ''}'.toUpperCase()),
                      Text(
                        AirmiusScope.of(
                          context,
                        ).t('learning.level.${course['level']}'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '${learningText(context, 'instructor')}: ${_map(course['tutor'])['name'] ?? ''}',
                  ),
                  if ((course['reviews_count'] as num? ?? 0) > 0)
                    Text(
                      '★ ${course['average_rating']} · ${course['reviews_count']}',
                    ),
                  const SizedBox(height: 16),
                  Text('${course['description'] ?? ''}'),
                  for (final entry in {
                    'learning_goals': 'goals',
                    'requirements': 'requirements',
                    'target_groups': 'audience',
                  }.entries)
                    if ((course[entry.key] as List? ?? []).isNotEmpty) ...[
                      const SizedBox(height: 20),
                      Text(
                        learningText(context, entry.value),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      for (final value in course[entry.key] as List)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text('• $value'),
                        ),
                    ],
                  const SizedBox(height: 20),
                  Text(
                    learningText(context, 'preview'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  if (previews.isEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(learningText(context, 'no_preview')),
                    ),
                  for (final lesson in previews)
                    ExpansionTile(
                      title: Text('${lesson['title']}'),
                      subtitle: Text(learningText(context, 'preview_hint')),
                      children: [
                        if (lesson['video_url'] != null)
                          AirmiusInlineVideo(
                            url: '${lesson['video_url']}',
                            title: '${lesson['title']}',
                          ),
                        Padding(
                          padding: const EdgeInsets.all(12),
                          child: Text(
                            '${lesson['content'] ?? ''}'.replaceAll(
                              RegExp(r'<[^>]*>'),
                              '',
                            ),
                          ),
                        ),
                        for (final file in _maps(lesson['attachments']))
                          TextButton(
                            onPressed: () {
                              final uri = safeExternalHttpUrl('${file['url']}');
                              if (uri != null) {
                                launchUrl(
                                  uri,
                                  mode: LaunchMode.externalApplication,
                                );
                              }
                            },
                            child: Text(
                              '${file['name'] ?? file['title'] ?? file['url']}',
                            ),
                          ),
                      ],
                    ),
                  const SizedBox(height: 20),
                  Text(
                    learningText(context, 'curriculum'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  for (final section in _maps(data['sections']))
                    ExpansionTile(
                      title: Text('${section['title']}'),
                      children: [
                        for (final lesson in _maps(section['lessons']))
                          ListTile(
                            leading: Icon(
                              lesson['locked'] == true
                                  ? Icons.lock_outline
                                  : Icons.play_circle_outline,
                            ),
                            title: Text('${lesson['title']}'),
                            subtitle: Text(
                              '${lesson['duration_minutes'] ?? 0} ${learningText(context, 'minutes')}',
                            ),
                            trailing: lesson['is_preview'] == true
                                ? const Icon(Icons.visibility_outlined)
                                : null,
                          ),
                      ],
                    ),
                  for (final faq in _maps(course['faq_items']))
                    ExpansionTile(
                      title: Text('${faq['question']}'),
                      children: [
                        Padding(
                          padding: const EdgeInsets.all(12),
                          child: Text('${faq['answer']}'),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (course['access_revoked'] == true)
                      Text(
                        AirmiusScope.of(context).t('learning.accessRevoked'),
                      ),
                    if (!access &&
                        course['can_resume'] != true &&
                        !free &&
                        course['purchase_product_id'] == null)
                      Text(learningText(context, 'unavailable')),
                    FilledButton(
                      onPressed:
                          _busy ||
                              course['access_revoked'] == true ||
                              (!access &&
                                  course['can_resume'] != true &&
                                  !free &&
                                  course['purchase_product_id'] == null)
                          ? null
                          : () => _action(data),
                      child: Text(
                        course['access_revoked'] == true
                            ? AirmiusScope.of(
                                context,
                              ).t('learning.accessRevokedShort')
                            : access
                            ? learningText(context, 'continue')
                            : course['can_resume'] == true
                            ? learningText(context, 'resume')
                            : free
                            ? learningText(context, 'enroll')
                            : '${learningText(context, 'buy')} · $price',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    ),
  );
}

JsonMap _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
List<JsonMap> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
    : [];
