import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LessonDetailScreen extends StatefulWidget {
  const LessonDetailScreen({
    super.key,
    required this.courseId,
    required this.initialTitle,
  });

  final int courseId;
  final String initialTitle;

  @override
  State<LessonDetailScreen> createState() => _LessonDetailScreenState();
}

class _LessonDetailScreenState extends State<LessonDetailScreen> {
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
    setState(() {
      _future = _client.learningCourse(widget.courseId);
    });
  }

  Future<void> _complete(JsonMap lesson) async {
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      await _client.completeLearningLesson(
        widget.courseId,
        _lessonInt(lesson['id']),
      );
      if (!mounted) return;
      _toast(t('learning.lessonCompleted'));
      _reload();
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

  Future<void> _addText(JsonMap lesson, {required bool comment}) async {
    final t = AirmiusScope.of(context).t;
    final controller = TextEditingController();
    final value = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t(comment ? 'learning.askQuestion' : 'learning.addNote')),
        content: TextField(
          controller: controller,
          autofocus: true,
          minLines: 3,
          maxLines: 8,
          maxLength: comment ? 3000 : 5000,
          decoration: InputDecoration(
            hintText: t(
              comment ? 'learning.questionHint' : 'learning.noteHint',
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () {
              final text = controller.text.trim();
              if (text.isNotEmpty) Navigator.pop(context, text);
            },
            child: Text(t('save')),
          ),
        ],
      ),
    );
    controller.dispose();
    if (value == null || !mounted) return;
    setState(() => _busy = true);
    try {
      if (comment) {
        await _client.addLearningComment(
          widget.courseId,
          _lessonInt(lesson['id']),
          value,
        );
      } else {
        await _client.addLearningNote(
          widget.courseId,
          _lessonInt(lesson['id']),
          value,
        );
      }
      if (!mounted) return;
      _toast(t(comment ? 'learning.questionSaved' : 'learning.noteSaved'));
      _reload();
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

  Future<void> _submitQuiz(JsonMap quiz) async {
    final answers = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _QuizDialog(quiz: quiz),
    );
    if (answers == null || !mounted) return;
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      final response = await _client.submitLearningQuiz(
        widget.courseId,
        _lessonInt(quiz['id']),
        answers,
      );
      if (!mounted) return;
      final attempt = _lessonMap(response['data']);
      _toast(
        _lessonBool(attempt['passed'])
            ? t('learning.quizPassed')
            : t('learning.quizRetry'),
      );
      _reload();
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

  Future<void> _submitAssignment(JsonMap assignment) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _AssignmentDialog(assignment: assignment),
    );
    if (payload == null || !mounted) return;
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      await _client.submitLearningAssignment(
        widget.courseId,
        _lessonInt(assignment['id']),
        body: _lessonText(payload['body']),
        attachmentUrl: _lessonText(payload['attachment_url']),
      );
      if (!mounted) return;
      _toast(t('learning.assignmentSubmitted'));
      _reload();
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

  Future<void> _review() async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => const _ReviewDialog(),
    );
    if (payload == null || !mounted) return;
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      await _client.reviewLearningCourse(
        widget.courseId,
        rating: _lessonInt(payload['rating']),
        body: _lessonText(payload['body']),
      );
      if (!mounted) return;
      _toast(t('learning.reviewSaved'));
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

  Uri _apiUri(String path) {
    final base = Uri.parse(
      AirmiusServicesScope.of(context).environment.apiBaseUrl,
    );
    final prefix = base.path.endsWith('/') ? base.path : '${base.path}/';
    return base.replace(
      path: '$prefix${path.startsWith('/') ? path.substring(1) : path}',
      query: null,
      fragment: null,
    );
  }

  Map<String, String> _apiHeaders() {
    final services = AirmiusServicesScope.of(context);
    return {
      'Accept': 'application/pdf',
      'X-Airmius-Locale': services.environment.locale,
      if (services.authState.session?.token.isNotEmpty == true)
        'Authorization': 'Bearer ${services.authState.session!.token}',
    };
  }

  Future<void> _downloadCertificate(JsonMap certificate) async {
    final t = AirmiusScope.of(context).t;
    final id = _lessonInt(certificate['id']);
    final code = _lessonText(certificate['code'], fallback: 'certificate');
    final path = '/api/v1/learning/certificates/$id/download';
    setState(() => _busy = true);
    try {
      final response = await http.get(_apiUri(path), headers: _apiHeaders());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
      }
      final saved = await FilePicker.platform.saveFile(
        dialogTitle: t('learning.downloadCertificate'),
        fileName: '$code.pdf',
        type: FileType.custom,
        allowedExtensions: const ['pdf'],
        bytes: Uint8List.fromList(response.bodyBytes),
      );
      if (mounted && saved != null) {
        _toast(t('learning.certificateSaved'));
      }
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
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          widget.initialTitle,
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('learning.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: widget.initialTitle,
        subtitle: t('learning.roomSubtitle'),
        child: FutureBuilder<JsonMap>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _LessonLoading();
            }
            if (snapshot.hasError) {
              return _LessonError(error: snapshot.error, onRetry: _reload);
            }
            return _content(_lessonMap(snapshot.data?['data']));
          },
        ),
      ),
    );
  }

  Widget _content(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final course = _lessonMap(data['course']);
    final enrollment = _lessonMap(data['enrollment']);
    final sections = _lessonMaps(data['sections']);
    final quizzes = _lessonMaps(data['quizzes']);
    final assignments = _lessonMaps(data['assignments']);
    final completion = _lessonMap(data['completion_requirements']);
    final progress = _lessonInt(enrollment['progress_percent']);
    final certificate = _lessonMap(enrollment['certificate']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('learning.learningRoom')),
              const SizedBox(height: 8),
              Text(
                _lessonText(
                  course['subtitle'] ?? course['description'],
                  fallback: t('learning.noDescription'),
                ),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(99),
                      child: LinearProgressIndicator(
                        value: progress.clamp(0, 100) / 100,
                        minHeight: 11,
                        backgroundColor: airmiusSurfaceSoftColor(context),
                        valueColor: const AlwaysStoppedAnimation<Color>(
                          AirmiusColors.green,
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
              if (completion.isNotEmpty) ...[
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _requirementPill(
                      context,
                      'learning.lessons',
                      _lessonMap(completion['lessons']),
                    ),
                    _requirementPill(
                      context,
                      'learning.quizzes',
                      _lessonMap(completion['quizzes']),
                    ),
                    _requirementPill(
                      context,
                      'learning.assignments',
                      _lessonMap(completion['assignments']),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        Text(
          t('learning.courseContent'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 19,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 10),
        if (sections.isEmpty)
          _LessonEmpty(
            icon: Icons.menu_book_outlined,
            title: t('learning.noLessons'),
            hint: t('learning.noLessonsHint'),
          )
        else
          for (
            var sectionIndex = 0;
            sectionIndex < sections.length;
            sectionIndex++
          ) ...[
            _SectionCard(
              section: sections[sectionIndex],
              busy: _busy,
              onComplete: _complete,
              onNote: (lesson) => _addText(lesson, comment: false),
              onComment: (lesson) => _addText(lesson, comment: true),
            ),
            if (sectionIndex < sections.length - 1) const SizedBox(height: 12),
          ],
        if (quizzes.isNotEmpty) ...[
          const SizedBox(height: 18),
          Text(
            t('learning.quizzes'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          for (var index = 0; index < quizzes.length; index++) ...[
            _QuizCard(
              quiz: quizzes[index],
              busy: _busy,
              onStart: () => _submitQuiz(quizzes[index]),
            ),
            if (index < quizzes.length - 1) const SizedBox(height: 10),
          ],
        ],
        if (assignments.isNotEmpty) ...[
          const SizedBox(height: 18),
          Text(
            t('learning.assignments'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          for (var index = 0; index < assignments.length; index++) ...[
            _AssignmentCard(
              assignment: assignments[index],
              busy: _busy,
              onSubmit: () => _submitAssignment(assignments[index]),
            ),
            if (index < assignments.length - 1) const SizedBox(height: 10),
          ],
        ],
        const SizedBox(height: 18),
        if (certificate.isNotEmpty)
          AirmiusPanel(
            borderColor: AirmiusColors.amber.withValues(alpha: .55),
            child: Row(
              children: [
                Icon(
                  Icons.verified_outlined,
                  color: AirmiusColors.amber,
                  size: 34,
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        t('learning.certificateReady'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _lessonText(certificate['code']),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: t('learning.downloadCertificate'),
                  onPressed: _busy
                      ? null
                      : () => _downloadCertificate(certificate),
                  icon: Icon(Icons.download_outlined),
                ),
              ],
            ),
          )
        else
          AirmiusButton(
            label: t('learning.reviewCourse'),
            icon: Icons.star_outline,
            secondary: true,
            onPressed: _busy ? null : _review,
          ),
      ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.section,
    required this.busy,
    required this.onComplete,
    required this.onNote,
    required this.onComment,
  });

  final JsonMap section;
  final bool busy;
  final ValueChanged<JsonMap> onComplete;
  final ValueChanged<JsonMap> onNote;
  final ValueChanged<JsonMap> onComment;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final lessons = _lessonMaps(section['lessons']);
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      child: Material(
        color: Colors.transparent,
        child: ExpansionTile(
          initiallyExpanded: true,
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          title: Text(
            _lessonText(section['title'], fallback: t('learning.section')),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          subtitle: Text(
            t(
              'learning.lessonCount',
            ).replaceFirst('{count}', '${lessons.length}'),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          children: [
            for (var index = 0; index < lessons.length; index++) ...[
              _LessonCard(
                lesson: lessons[index],
                busy: busy,
                onComplete: () => onComplete(lessons[index]),
                onNote: () => onNote(lessons[index]),
                onComment: () => onComment(lessons[index]),
              ),
              if (index < lessons.length - 1) const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

class _LessonCard extends StatelessWidget {
  const _LessonCard({
    required this.lesson,
    required this.busy,
    required this.onComplete,
    required this.onNote,
    required this.onComment,
  });

  final JsonMap lesson;
  final bool busy;
  final VoidCallback onComplete;
  final VoidCallback onNote;
  final VoidCallback onComment;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final locked = _lessonBool(lesson['locked']);
    final completed = _lessonBool(lesson['completed']);
    final notes = _lessonMaps(lesson['notes']);
    final comments = _lessonMaps(lesson['comments']);
    return Container(
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: completed
              ? AirmiusColors.green.withValues(alpha: .55)
              : airmiusBorderColor(context),
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: ExpansionTile(
          enabled: !locked,
          leading: Icon(
            locked
                ? Icons.lock_outline
                : completed
                ? Icons.check_circle_outline
                : Icons.play_circle_outline,
            color: locked
                ? airmiusMutedColor(context)
                : completed
                ? AirmiusColors.green
                : airmiusAccentColor(context),
          ),
          title: Text(
            _lessonText(lesson['title'], fallback: t('learning.lesson')),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          subtitle: Text(
            locked
                ? t('learning.locked')
                : t('learning.minutes').replaceFirst(
                    '{count}',
                    '${_lessonInt(lesson['duration_minutes'])}',
                  ),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          children: [
            if (_lessonText(lesson['summary']).isNotEmpty)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  _lessonText(lesson['summary']),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.4,
                  ),
                ),
              ),
            if (_lessonText(lesson['content']).isNotEmpty) ...[
              const SizedBox(height: 12),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  _plainLearningText(_lessonText(lesson['content'])),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.55,
                  ),
                ),
              ),
            ],
            if (notes.isNotEmpty) ...[
              const SizedBox(height: 12),
              _SmallLearningList(
                title: t('learning.myNotes'),
                items: notes,
                icon: Icons.note_outlined,
              ),
            ],
            if (comments.isNotEmpty) ...[
              const SizedBox(height: 12),
              _SmallLearningList(
                title: t('learning.questions'),
                items: comments,
                icon: Icons.forum_outlined,
              ),
            ],
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (!completed)
                  FilledButton.icon(
                    onPressed: busy ? null : onComplete,
                    icon: Icon(Icons.check_circle_outline),
                    label: Text(t('learning.markComplete')),
                  )
                else
                  StatusPill(
                    t('learning.completed'),
                    color: AirmiusColors.green,
                  ),
                OutlinedButton.icon(
                  onPressed: busy ? null : onNote,
                  icon: Icon(Icons.note_add_outlined),
                  label: Text(t('learning.addNote')),
                ),
                OutlinedButton.icon(
                  onPressed: busy ? null : onComment,
                  icon: Icon(Icons.question_answer_outlined),
                  label: Text(t('learning.askQuestion')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SmallLearningList extends StatelessWidget {
  const _SmallLearningList({
    required this.title,
    required this.items,
    required this.icon,
  });

  final String title;
  final List<JsonMap> items;
  final IconData icon;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 8),
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 7),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon, size: 18, color: airmiusAccentColor(context)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _lessonText(item['body']),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class _QuizCard extends StatelessWidget {
  const _QuizCard({
    required this.quiz,
    required this.busy,
    required this.onStart,
  });

  final JsonMap quiz;
  final bool busy;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final locked = _lessonBool(quiz['locked']);
    final attempt = _lessonMap(quiz['attempt']);
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(
            locked ? Icons.lock_outline : Icons.quiz_outlined,
            color: locked
                ? airmiusMutedColor(context)
                : airmiusAccentColor(context),
            size: 29,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _lessonText(quiz['title'], fallback: t('learning.quiz')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (attempt.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  StatusPill(
                    t('learning.quizScore').replaceFirst(
                      '{score}',
                      '${_lessonInt(attempt['score_percent'])}',
                    ),
                    color: _lessonBool(attempt['passed'])
                        ? AirmiusColors.green
                        : AirmiusColors.amber,
                  ),
                ],
              ],
            ),
          ),
          IconButton.filledTonal(
            tooltip: t('learning.startQuiz'),
            onPressed: locked || busy ? null : onStart,
            icon: Icon(Icons.play_arrow),
          ),
        ],
      ),
    );
  }
}

class _AssignmentCard extends StatelessWidget {
  const _AssignmentCard({
    required this.assignment,
    required this.busy,
    required this.onSubmit,
  });

  final JsonMap assignment;
  final bool busy;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final locked = _lessonBool(assignment['locked']);
    final submission = _lessonMap(assignment['submission']);
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(
            locked ? Icons.lock_outline : Icons.assignment_outlined,
            color: locked ? airmiusMutedColor(context) : AirmiusColors.green,
            size: 29,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _lessonText(
                    assignment['title'],
                    fallback: t('learning.assignment'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (submission.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  StatusPill(
                    t(
                      'learning.submission.${_lessonText(submission['status'], fallback: 'submitted')}',
                    ),
                    color: AirmiusColors.green,
                  ),
                ],
              ],
            ),
          ),
          IconButton.filledTonal(
            tooltip: t('learning.submitAssignment'),
            onPressed: locked || busy ? null : onSubmit,
            icon: Icon(Icons.upload_file_outlined),
          ),
        ],
      ),
    );
  }
}

class _QuizDialog extends StatefulWidget {
  const _QuizDialog({required this.quiz});

  final JsonMap quiz;

  @override
  State<_QuizDialog> createState() => _QuizDialogState();
}

class _QuizDialogState extends State<_QuizDialog> {
  final Map<String, String> _answers = {};

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final questions = _lessonMaps(widget.quiz['questions']);
    return AlertDialog(
      title: Text(
        _lessonText(widget.quiz['title'], fallback: t('learning.quiz')),
      ),
      content: SizedBox(
        width: 620,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final question in questions) ...[
                Text(
                  _lessonText(question['question']),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                RadioGroup<String>(
                  groupValue: _answers['${_lessonInt(question['id'])}'],
                  onChanged: (value) => setState(
                    () => _answers['${_lessonInt(question['id'])}'] = value!,
                  ),
                  child: Column(
                    children: [
                      for (final option in _lessonStrings(question['options']))
                        RadioListTile<String>(
                          value: option,
                          title: Text(option),
                          contentPadding: EdgeInsets.zero,
                        ),
                    ],
                  ),
                ),
                Divider(height: 24),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton(
          onPressed: questions.isNotEmpty && _answers.length == questions.length
              ? () => Navigator.pop(context, JsonMap.from(_answers))
              : null,
          child: Text(t('learning.submitQuiz')),
        ),
      ],
    );
  }
}

class _AssignmentDialog extends StatefulWidget {
  const _AssignmentDialog({required this.assignment});

  final JsonMap assignment;

  @override
  State<_AssignmentDialog> createState() => _AssignmentDialogState();
}

class _AssignmentDialogState extends State<_AssignmentDialog> {
  final _body = TextEditingController();
  final _url = TextEditingController();

  @override
  void dispose() {
    _body.dispose();
    _url.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        _lessonText(
          widget.assignment['title'],
          fallback: t('learning.assignment'),
        ),
      ),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _lessonText(
                  widget.assignment['instructions'],
                  fallback: t('learning.noInstructions'),
                ),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _body,
                minLines: 4,
                maxLines: 10,
                maxLength: 10000,
                decoration: InputDecoration(
                  labelText: t('learning.answerText'),
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _url,
                keyboardType: TextInputType.url,
                decoration: InputDecoration(
                  labelText: t('learning.attachmentLink'),
                  hintText: 'https://',
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_body.text.trim().isEmpty && _url.text.trim().isEmpty) return;
            Navigator.pop(context, {
              'body': _body.text.trim(),
              'attachment_url': _url.text.trim(),
            });
          },
          child: Text(t('learning.submitAssignment')),
        ),
      ],
    );
  }
}

class _ReviewDialog extends StatefulWidget {
  const _ReviewDialog();

  @override
  State<_ReviewDialog> createState() => _ReviewDialogState();
}

class _ReviewDialogState extends State<_ReviewDialog> {
  int _rating = 5;
  final _body = TextEditingController();

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('learning.reviewCourse')),
      content: SizedBox(
        width: 460,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var value = 1; value <= 5; value++)
                  IconButton(
                    tooltip: '$value',
                    onPressed: () => setState(() => _rating = value),
                    icon: Icon(
                      value <= _rating ? Icons.star : Icons.star_border,
                      color: AirmiusColors.amber,
                    ),
                  ),
              ],
            ),
            TextField(
              controller: _body,
              minLines: 3,
              maxLines: 6,
              maxLength: 1500,
              decoration: InputDecoration(labelText: t('learning.reviewText')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'rating': _rating,
            'body': _body.text.trim(),
          }),
          child: Text(t('save')),
        ),
      ],
    );
  }
}

class _LessonLoading extends StatelessWidget {
  const _LessonLoading();

  @override
  Widget build(BuildContext context) => const AirmiusPanel(
    child: Padding(
      padding: EdgeInsets.all(30),
      child: Center(child: CircularProgressIndicator()),
    ),
  );
}

class _LessonError extends StatelessWidget {
  const _LessonError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: AirmiusColors.red.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('learning.courseLoadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            error is AirmiusApiException
                ? (error as AirmiusApiException).userMessage
                : t('common.errorDetails'),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
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

class _LessonEmpty extends StatelessWidget {
  const _LessonEmpty({
    required this.icon,
    required this.title,
    required this.hint,
  });

  final IconData icon;
  final String title;
  final String hint;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Column(
      children: [
        Icon(icon, color: airmiusAccentColor(context), size: 42),
        const SizedBox(height: 10),
        Text(
          title,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          hint,
          textAlign: TextAlign.center,
          style: TextStyle(color: airmiusMutedColor(context)),
        ),
      ],
    ),
  );
}

Widget _requirementPill(BuildContext context, String key, JsonMap requirement) {
  final t = AirmiusScope.of(context).t;
  return StatusPill(
    '${t(key)} ${_lessonInt(requirement['completed'])}/${_lessonInt(requirement['total'])}',
    color:
        _lessonInt(requirement['completed']) >= _lessonInt(requirement['total'])
        ? AirmiusColors.green
        : airmiusAccentColor(context),
  );
}

JsonMap _lessonMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _lessonMaps(Object? value) => value is List
    ? value.map(_lessonMap).where((item) => item.isNotEmpty).toList()
    : const [];

List<String> _lessonStrings(Object? value) => value is List
    ? value.map((item) => '$item').where((item) => item.isNotEmpty).toList()
    : const [];

String _lessonText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _lessonInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

bool _lessonBool(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';

String _plainLearningText(String value) => value
    .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
    .replaceAll(RegExp(r'</p\s*>', caseSensitive: false), '\n\n')
    .replaceAll(RegExp(r'<[^>]+>'), '')
    .replaceAll('&nbsp;', ' ')
    .replaceAll('&amp;', '&')
    .replaceAll('&lt;', '<')
    .replaceAll('&gt;', '>')
    .trim();
