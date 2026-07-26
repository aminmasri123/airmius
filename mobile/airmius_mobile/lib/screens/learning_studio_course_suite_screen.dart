import 'dart:convert';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LearningStudioCourseSuiteScreen extends StatefulWidget {
  const LearningStudioCourseSuiteScreen({super.key});

  @override
  State<LearningStudioCourseSuiteScreen> createState() =>
      _LearningStudioCourseSuiteScreenState();
}

class _LearningStudioCourseSuiteScreenState
    extends State<LearningStudioCourseSuiteScreen> {
  Future<Map<String, dynamic>>? _future;
  int? _selectedCourseId;
  String _section = 'content';
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async =>
      _map((await _client.learningStudio(courseId: _selectedCourseId))['data']);

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  void _selectCourse(int id) {
    setState(() {
      _selectedCourseId = id;
      _future = _load();
      _section = 'content';
    });
  }

  Future<void> _run(
    Future<void> Function() action, {
    required String success,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      _toast(success);
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(
          error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('common.errorDetails'),
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
          t('studio.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('studio.newCourse'),
            onPressed: _busy ? null : _showCourseCreate,
            icon: const Icon(Icons.add_circle_outline),
          ),
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _StudioEmpty(
              icon: Icons.cloud_off_outlined,
              title: t('studio.loadFailed'),
              body: snapshot.error is AirmiusApiException
                  ? (snapshot.error! as AirmiusApiException).userMessage
                  : t('common.errorDetails'),
              action: FilledButton.icon(
                onPressed: _reload,
                icon: const Icon(Icons.refresh),
                label: Text(t('common.retry')),
              ),
            );
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          return RefreshIndicator(
            onRefresh: () async {
              final next = await _load();
              if (mounted) setState(() => _future = Future.value(next));
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 36),
              children: [
                _hero(),
                const SizedBox(height: 14),
                _coursePicker(data),
                if (_busy) ...[
                  const SizedBox(height: 10),
                  const LinearProgressIndicator(minHeight: 3),
                ],
                const SizedBox(height: 14),
                _studio(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero() {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('studio.eyebrow')),
          const SizedBox(height: 8),
          Text(
            t('studio.headline'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 23,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            t('studio.subtitle'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.45),
          ),
        ],
      ),
    );
  }

  Widget _coursePicker(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final courses = _maps(data['courses']);
    if (courses.isEmpty) {
      return _StudioEmpty(
        icon: Icons.school_outlined,
        title: t('studio.noCourses'),
        body: t('studio.noCoursesBody'),
        action: AirmiusButton(
          label: t('studio.newCourse'),
          icon: Icons.add_outlined,
          onPressed: _showCourseCreate,
        ),
      );
    }
    final selected = _int(_map(data['selectedCourse'])['id']);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: courses
            .map(
              (course) => Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  selected: selected == _int(course['id']),
                  label: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Text(_text(course['title'])),
                  ),
                  onSelected: (_) => _selectCourse(_int(course['id'])),
                  selectedColor: airmiusAccentColor(
                    context,
                  ).withValues(alpha: 0.22),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: selected == _int(course['id'])
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _studio(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final course = _map(data['selectedCourse']);
    if (course.isEmpty) return const SizedBox.shrink();
    final analytics = _map(course['analytics']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.video_settings_outlined,
                    color: airmiusAccentColor(context),
                    size: 34,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _text(course['title']),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          _text(
                            course['subtitle'],
                            fallback: _text(course['description']),
                          ),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  StatusPill(
                    _status(course['status']),
                    color: course['status'] == 'published'
                        ? AirmiusColors.green
                        : AirmiusColors.amber,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 9,
                runSpacing: 9,
                children: [
                  _StudioMetric(
                    value: '${_maps(course['enrollments']).length}',
                    label: t('studio.students'),
                  ),
                  _StudioMetric(
                    value: '${_int(analytics['average_progress'])}%',
                    label: t('studio.progress'),
                  ),
                  _StudioMetric(
                    value: '${_int(analytics['open_questions'])}',
                    label: t('studio.questions'),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 13),
        _tabs(),
        const SizedBox(height: 13),
        if (_section == 'students')
          _students(course)
        else if (_section == 'questions')
          _questions(course)
        else if (_section == 'settings')
          _settings(course)
        else
          _content(course),
      ],
    );
  }

  Widget _tabs() {
    final t = AirmiusScope.of(context).t;
    final tabs = {
      'content': t('studio.content'),
      'students': t('studio.students'),
      'questions': t('studio.questions'),
      'settings': t('studio.settings'),
    };
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: tabs.entries
          .map(
            (entry) => ChoiceChip(
              selected: _section == entry.key,
              label: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Text(entry.value),
              ),
              onSelected: (_) => setState(() => _section = entry.key),
            ),
          )
          .toList(),
    );
  }

  Widget _content(Map<String, dynamic> course) {
    final t = AirmiusScope.of(context).t;
    final sections = _maps(course['sections']);
    final quizzes = _maps(course['quizzes']);
    final assignments = _maps(course['assignments']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 9,
          runSpacing: 9,
          children: [
            AirmiusButton(
              label: t('studio.addSection'),
              icon: Icons.create_new_folder_outlined,
              secondary: true,
              onPressed: () => _showSection(course),
            ),
            AirmiusButton(
              label: t('studio.addLesson'),
              icon: Icons.playlist_add_outlined,
              onPressed: sections.isEmpty
                  ? null
                  : () => _showLesson(course, sections),
            ),
            AirmiusButton(
              label: t('studio.addQuiz'),
              icon: Icons.quiz_outlined,
              secondary: true,
              onPressed: () => _showQuiz(course),
            ),
            AirmiusButton(
              label: t('studio.addAssignment'),
              icon: Icons.assignment_add,
              secondary: true,
              onPressed: () => _showAssignment(course),
            ),
          ],
        ),
        const SizedBox(height: 13),
        if (sections.isEmpty)
          _StudioEmpty(
            icon: Icons.account_tree_outlined,
            title: t('studio.noSections'),
            body: t('studio.noSectionsBody'),
          )
        else
          ...sections.map(
            (section) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _SectionCard(
                section: section,
                onEditLesson: (lesson) => _showLesson(course, sections, lesson),
                onDeleteLesson: (lesson) =>
                    _confirmDeleteLesson(course, lesson),
                onMoveLesson: (lesson, direction) =>
                    _moveLesson(course, section, lesson, direction),
              ),
            ),
          ),
        if (quizzes.isNotEmpty) ...[
          const SizedBox(height: 3),
          AirmiusPanel(
            title: t('studio.quizzes'),
            child: Column(
              children: quizzes
                  .map(
                    (quiz) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        Icons.quiz_outlined,
                        color: airmiusAccentColor(context),
                      ),
                      title: Text(_text(quiz['title'])),
                      subtitle: Text(
                        '${_int(quiz['pass_percent'])}% · ${_maps(quiz['questions']).length} ${t('studio.questions')}',
                      ),
                      trailing: IconButton(
                        tooltip: t('common.delete'),
                        color: AirmiusColors.red,
                        onPressed: () => _deleteQuiz(course, quiz),
                        icon: const Icon(Icons.delete_outline),
                      ),
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
        if (assignments.isNotEmpty) ...[
          const SizedBox(height: 12),
          AirmiusPanel(
            title: t('studio.assignments'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: assignments
                  .map(
                    (assignment) => _AssignmentStudioCard(
                      assignment: assignment,
                      onGrade: (submission) =>
                          _gradeAssignment(course, submission),
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
      ],
    );
  }

  Widget _students(Map<String, dynamic> course) {
    final t = AirmiusScope.of(context).t;
    final enrollments = _maps(course['enrollments']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusButton(
          label: t('studio.grantAccess'),
          icon: Icons.person_add_alt_outlined,
          onPressed: () => _showEnrollment(course),
        ),
        const SizedBox(height: 13),
        if (enrollments.isEmpty)
          _StudioEmpty(
            icon: Icons.groups_outlined,
            title: t('studio.noStudents'),
            body: t('studio.noStudentsBody'),
          )
        else
          ...enrollments.map(
            (enrollment) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _EnrollmentCard(
                enrollment: enrollment,
                onRevoke: enrollment['status'] == 'cancelled'
                    ? null
                    : () => _revokeEnrollment(course, enrollment),
              ),
            ),
          ),
      ],
    );
  }

  Widget _questions(Map<String, dynamic> course) {
    final t = AirmiusScope.of(context).t;
    final questions = _maps(course['questions']);
    if (questions.isEmpty) {
      return _StudioEmpty(
        icon: Icons.mark_chat_read_outlined,
        title: t('studio.noQuestions'),
        body: t('studio.noQuestionsBody'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: questions
          .map(
            (question) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _QuestionCard(
                question: question,
                onReply: () => _replyQuestion(course, question),
                onStatus: (status) =>
                    _updateQuestionStatus(course, question, status),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _settings(Map<String, dynamic> course) {
    final t = AirmiusScope.of(context).t;
    final checklistData = _map(course['publish_checklist']);
    final checklist = _maps(checklistData['items']);
    final analytics = _map(course['analytics']);
    final coupons = _maps(course['coupons']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 9,
          runSpacing: 9,
          children: [
            _StudioMetric(
              value: '${_int(checklistData['score'])}%',
              label: t('studio.readiness'),
            ),
            _StudioMetric(
              value: '${_int(analytics['sales_count'])}',
              label: t('studio.sales'),
            ),
            _StudioMetric(
              value:
                  '${(_int(analytics['net_revenue_cents']) / 100).toStringAsFixed(2)} €',
              label: t('studio.revenue'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('studio.publishChecklist')),
              const SizedBox(height: 10),
              ...checklist.map(
                (item) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    item['done'] == true
                        ? Icons.check_circle
                        : Icons.radio_button_unchecked,
                    color: item['done'] == true
                        ? AirmiusColors.green
                        : airmiusMutedColor(context),
                  ),
                  title: Text(
                    _text(item['label']),
                    style: TextStyle(color: airmiusTextColor(context)),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 9,
          runSpacing: 9,
          children: [
            AirmiusButton(
              label: t('studio.editCourse'),
              icon: Icons.edit_outlined,
              onPressed: () => _showCourseEdit(course),
            ),
            AirmiusButton(
              label: t('studio.uploadCover'),
              icon: Icons.add_photo_alternate_outlined,
              secondary: true,
              onPressed: _busy ? null : () => _uploadCover(course),
            ),
            AirmiusButton(
              label: t('studio.exportReport'),
              icon: Icons.download_outlined,
              secondary: true,
              onPressed: _busy ? null : () => _downloadReport(course),
            ),
            AirmiusButton(
              label: t('studio.addCoupon'),
              icon: Icons.local_offer_outlined,
              secondary: true,
              onPressed: _busy ? null : () => _showCoupon(course),
            ),
          ],
        ),
        if (coupons.isNotEmpty) ...[
          const SizedBox(height: 12),
          AirmiusPanel(
            title: t('studio.coupons'),
            child: Column(
              children: coupons
                  .map(
                    (coupon) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(
                        Icons.sell_outlined,
                        color: AirmiusColors.green,
                      ),
                      title: Text(_text(coupon['code'])),
                      subtitle: Text(
                        coupon['discount_type'] == 'percent'
                            ? '${_int(coupon['discount_value'])}%'
                            : '${(_int(coupon['discount_value']) / 100).toStringAsFixed(2)} €',
                      ),
                      trailing: StatusPill(
                        coupon['is_active'] == true
                            ? t('studio.active')
                            : t('studio.inactive'),
                      ),
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
      ],
    );
  }

  Future<void> _showCourseCreate() async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _CourseSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(() async {
      final response = await _client.createLearningStudioCourse(payload);
      _selectedCourseId = _int(_map(response['data'])['id']);
    }, success: AirmiusScope.of(context).t('studio.courseCreated'));
  }

  Future<void> _showCourseEdit(Map<String, dynamic> course) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _CourseSheet(course: course),
    );
    if (payload == null || !mounted) return;
    final preserved = _preserveCourseFields(course, payload);
    await _run(
      () async =>
          _client.updateLearningStudioCourse(_int(course['id']), preserved),
      success: AirmiusScope.of(context).t('studio.courseSaved'),
    );
  }

  Map<String, dynamic> _preserveCourseFields(
    Map<String, dynamic> course,
    Map<String, dynamic> changes,
  ) => {
    for (final key in const [
      'title',
      'subtitle',
      'description',
      'category',
      'sport_type',
      'level',
      'language',
      'status',
      'is_public',
      'is_free',
      'price_cents',
    ])
      key: changes[key] ?? course[key],
    for (final key in const [
      'learning_goals_text',
      'requirements_text',
      'target_groups_text',
      'sales_points_text',
      'faq_items_text',
      'tags_text',
      'guarantee_text',
      'certificate_logo_url',
      'certificate_signature_name',
      'certificate_footer_text',
    ])
      key: changes[key] ?? course[key],
    'cover_image': changes['cover_image'] ?? course['cover_image'],
    ...changes,
  };

  Future<void> _showSection(Map<String, dynamic> course) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _SectionSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async =>
          _client.createLearningStudioSection(_int(course['id']), payload),
      success: AirmiusScope.of(context).t('studio.sectionCreated'),
    );
  }

  Future<void> _showLesson(
    Map<String, dynamic> course,
    List<Map<String, dynamic>> sections, [
    Map<String, dynamic>? lesson,
  ]) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _LessonSheet(sections: sections, lesson: lesson),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        if (lesson == null) {
          await _client.createLearningStudioLesson(_int(course['id']), payload);
        } else {
          await _client.updateLearningStudioLesson(
            _int(course['id']),
            _int(lesson['id']),
            payload,
          );
        }
      },
      success: AirmiusScope.of(
        context,
      ).t(lesson == null ? 'studio.lessonCreated' : 'studio.lessonSaved'),
    );
  }

  Future<void> _confirmDeleteLesson(
    Map<String, dynamic> course,
    Map<String, dynamic> lesson,
  ) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('studio.deleteLesson')),
        content: Text(t('studio.deleteLessonBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(
      () async => _client.deleteLearningStudioLesson(
        _int(course['id']),
        _int(lesson['id']),
      ),
      success: t('studio.lessonDeleted'),
    );
  }

  Future<void> _showQuiz(Map<String, dynamic> course) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _QuizSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.createLearningStudioQuiz(_int(course['id']), payload),
      success: AirmiusScope.of(context).t('studio.quizCreated'),
    );
  }

  Future<void> _deleteQuiz(
    Map<String, dynamic> course,
    Map<String, dynamic> quiz,
  ) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('studio.deleteQuiz')),
        content: Text(t('studio.deleteQuizBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(
      () async => _client.deleteLearningStudioQuiz(
        _int(course['id']),
        _int(quiz['id']),
      ),
      success: t('studio.quizDeleted'),
    );
  }

  Future<void> _showAssignment(Map<String, dynamic> course) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _AssignmentSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async =>
          _client.createLearningStudioAssignment(_int(course['id']), payload),
      success: AirmiusScope.of(context).t('studio.assignmentCreated'),
    );
  }

  Future<void> _gradeAssignment(
    Map<String, dynamic> course,
    Map<String, dynamic> submission,
  ) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _GradeSheet(submission: submission),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.gradeLearningStudioAssignment(
        _int(course['id']),
        _int(submission['id']),
        payload,
      ),
      success: AirmiusScope.of(context).t('studio.gradeSaved'),
    );
  }

  Future<void> _showCoupon(Map<String, dynamic> course) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _CouponSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async =>
          _client.createLearningStudioCoupon(_int(course['id']), payload),
      success: AirmiusScope.of(context).t('studio.couponSaved'),
    );
  }

  Future<void> _moveLesson(
    Map<String, dynamic> course,
    Map<String, dynamic> section,
    Map<String, dynamic> lesson,
    int direction,
  ) async {
    final lessons = _maps(section['lessons']);
    final current = lessons.indexWhere(
      (entry) => _int(entry['id']) == _int(lesson['id']),
    );
    final target = current + direction;
    if (current < 0 || target < 0 || target >= lessons.length) return;
    final reordered = [...lessons];
    final moved = reordered.removeAt(current);
    reordered.insert(target, moved);
    await _run(
      () async => _client.reorderLearningStudioLessons(_int(course['id']), [
        for (var index = 0; index < reordered.length; index++)
          {'id': _int(reordered[index]['id']), 'position': index + 1},
      ]),
      success: AirmiusScope.of(context).t('studio.orderSaved'),
    );
  }

  Future<void> _replyQuestion(
    Map<String, dynamic> course,
    Map<String, dynamic> question,
  ) async {
    final t = AirmiusScope.of(context).t;
    final controller = TextEditingController();
    final answer = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('studio.reply')),
        content: TextField(
          controller: controller,
          autofocus: true,
          minLines: 3,
          maxLines: 7,
          decoration: InputDecoration(labelText: t('studio.answer')),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(
              dialogContext,
              controller.text.trim().isEmpty ? null : controller.text.trim(),
            ),
            child: Text(t('studio.sendAnswer')),
          ),
        ],
      ),
    );
    controller.dispose();
    if (answer == null || !mounted) return;
    await _run(
      () async => _client.replyLearningStudioQuestion(
        _int(course['id']),
        _int(question['id']),
        answer,
      ),
      success: t('studio.answerSent'),
    );
  }

  Future<void> _updateQuestionStatus(
    Map<String, dynamic> course,
    Map<String, dynamic> question,
    String status,
  ) async {
    await _run(
      () async => _client.updateLearningStudioQuestion(
        _int(course['id']),
        _int(question['id']),
        status,
      ),
      success: AirmiusScope.of(context).t('studio.questionUpdated'),
    );
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
      'Accept': 'application/json',
      'X-Airmius-Locale': services.environment.locale,
      if (services.authState.session?.token.isNotEmpty == true)
        'Authorization': 'Bearer ${services.authState.session!.token}',
    };
  }

  Future<void> _uploadCover(Map<String, dynamic> course) async {
    final t = AirmiusScope.of(context).t;
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final file = result?.files.single;
    if (file == null || !mounted) return;
    setState(() => _busy = true);
    final path =
        '/api/v1/learning-studio/courses/${_int(course['id'])}/uploads';
    try {
      final request = http.MultipartRequest('POST', _apiUri(path))
        ..headers.addAll(_apiHeaders())
        ..fields['purpose'] = 'cover';
      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'file',
            file.bytes!,
            filename: file.name,
          ),
        );
      } else if (file.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'file',
            file.path!,
            filename: file.name,
          ),
        );
      } else {
        throw StateError(t('studio.fileUnreadable'));
      }
      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
      }
      final uploaded = _map(jsonDecode(response.body));
      await _client.updateLearningStudioCourse(
        _int(course['id']),
        _preserveCourseFields(course, {'cover_image': uploaded['url']}),
      );
      if (!mounted) return;
      _toast(t('studio.coverUploaded'));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(
          error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _downloadReport(Map<String, dynamic> course) async {
    final t = AirmiusScope.of(context).t;
    final path =
        '/api/v1/learning-studio/courses/${_int(course['id'])}/report.csv';
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
        dialogTitle: t('studio.exportReport'),
        fileName: 'learning-report-${_int(course['id'])}.csv',
        type: FileType.custom,
        allowedExtensions: const ['csv'],
        bytes: Uint8List.fromList(response.bodyBytes),
      );
      if (mounted && saved != null) _toast(t('studio.reportSaved'));
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(
          error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showEnrollment(Map<String, dynamic> course) async {
    final email = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _EnrollmentSheet(),
    );
    if (email == null || !mounted) return;
    await _run(
      () async =>
          _client.grantLearningStudioEnrollment(_int(course['id']), email),
      success: AirmiusScope.of(context).t('studio.accessGranted'),
    );
  }

  Future<void> _revokeEnrollment(
    Map<String, dynamic> course,
    Map<String, dynamic> enrollment,
  ) async {
    await _run(
      () async => _client.revokeLearningStudioEnrollment(
        _int(course['id']),
        _int(enrollment['id']),
      ),
      success: AirmiusScope.of(context).t('studio.accessRevoked'),
    );
  }
}

class _StudioMetric extends StatelessWidget {
  const _StudioMetric({required this.value, required this.label});
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 105, minHeight: 65),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: airmiusSurfaceSoftColor(context),
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: airmiusBorderColor(context)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 20,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(label, style: TextStyle(color: airmiusMutedColor(context))),
      ],
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.section,
    required this.onEditLesson,
    required this.onDeleteLesson,
    required this.onMoveLesson,
  });
  final Map<String, dynamic> section;
  final ValueChanged<Map<String, dynamic>> onEditLesson;
  final ValueChanged<Map<String, dynamic>> onDeleteLesson;
  final void Function(Map<String, dynamic> lesson, int direction) onMoveLesson;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final lessons = _maps(section['lessons']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.folder_copy_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  _text(section['title']),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill('${lessons.length} ${t('studio.lessons')}'),
            ],
          ),
          if (_text(section['description']).isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              _text(section['description']),
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
          const SizedBox(height: 10),
          if (lessons.isEmpty)
            Text(
              t('studio.emptySection'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          else
            ...lessons.indexed.map((entry) {
              final index = entry.$1;
              final lesson = entry.$2;
              return Container(
                margin: const EdgeInsets.only(top: 7),
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(
                  color: airmiusSurfaceSoftColor(context),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: airmiusBorderColor(context)),
                ),
                child: Row(
                  children: [
                    Icon(
                      lesson['type'] == 'video'
                          ? Icons.play_circle_outline
                          : Icons.menu_book_outlined,
                      color: airmiusAccentColor(context),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _text(lesson['title']),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            '${_int(lesson['duration_minutes'])} ${t('studio.minutes')}',
                            style: TextStyle(color: airmiusMutedColor(context)),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: t('studio.moveUp'),
                      onPressed: index == 0
                          ? null
                          : () => onMoveLesson(lesson, -1),
                      icon: const Icon(Icons.arrow_upward_outlined),
                    ),
                    IconButton(
                      tooltip: t('studio.moveDown'),
                      onPressed: index == lessons.length - 1
                          ? null
                          : () => onMoveLesson(lesson, 1),
                      icon: const Icon(Icons.arrow_downward_outlined),
                    ),
                    IconButton(
                      tooltip: t('common.edit'),
                      onPressed: () => onEditLesson(lesson),
                      icon: const Icon(Icons.edit_outlined),
                    ),
                    IconButton(
                      tooltip: t('common.delete'),
                      onPressed: () => onDeleteLesson(lesson),
                      color: AirmiusColors.red,
                      icon: const Icon(Icons.delete_outline),
                    ),
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.question,
    required this.onReply,
    required this.onStatus,
  });

  final Map<String, dynamic> question;
  final VoidCallback onReply;
  final ValueChanged<String> onStatus;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final user = _map(question['user']);
    final replies = _maps(question['replies']);
    final status = _text(question['status'], fallback: 'open');
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.question_answer_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(question['lesson_title']),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      _text(user['name'], fallback: t('studio.student')),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
              StatusPill(_status(status)),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            _text(question['body']),
            style: TextStyle(color: airmiusTextColor(context), height: 1.4),
          ),
          if (replies.isNotEmpty) ...[
            const SizedBox(height: 10),
            ...replies.map(
              (reply) => Container(
                margin: const EdgeInsets.only(top: 6),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: airmiusSurfaceSoftColor(context),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: airmiusBorderColor(context)),
                ),
                child: Text(
                  _text(reply['body']),
                  style: TextStyle(color: airmiusTextColor(context)),
                ),
              ),
            ),
          ],
          const SizedBox(height: 11),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: t('studio.reply'),
                icon: Icons.reply_outlined,
                onPressed: onReply,
              ),
              if (status != 'resolved')
                AirmiusButton(
                  label: t('studio.resolve'),
                  icon: Icons.task_alt_outlined,
                  secondary: true,
                  onPressed: () => onStatus('resolved'),
                )
              else
                AirmiusButton(
                  label: t('studio.reopen'),
                  icon: Icons.refresh_outlined,
                  secondary: true,
                  onPressed: () => onStatus('open'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AssignmentStudioCard extends StatelessWidget {
  const _AssignmentStudioCard({
    required this.assignment,
    required this.onGrade,
  });

  final Map<String, dynamic> assignment;
  final ValueChanged<Map<String, dynamic>> onGrade;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final submissions = _maps(assignment['submissions']);
    return ExpansionTile(
      tilePadding: EdgeInsets.zero,
      childrenPadding: EdgeInsets.zero,
      leading: Icon(
        Icons.assignment_outlined,
        color: airmiusAccentColor(context),
      ),
      title: Text(
        _text(assignment['title']),
        style: const TextStyle(fontWeight: FontWeight.w900),
      ),
      subtitle: Text(
        '${_int(assignment['points'])} ${t('studio.points')} · ${submissions.length} ${t('studio.submissions')}',
      ),
      children: submissions.isEmpty
          ? [
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  t('studio.noSubmissions'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
            ]
          : submissions.map((submission) {
              final user = _map(submission['user']);
              return ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(_text(user['name'], fallback: t('studio.student'))),
                subtitle: Text(
                  '${_status(submission['status'])}'
                  '${submission['score'] == null ? '' : ' · ${_int(submission['score'])}/${_int(assignment['points'])}'}',
                ),
                trailing: TextButton.icon(
                  onPressed: () => onGrade(submission),
                  icon: const Icon(Icons.grading_outlined),
                  label: Text(t('studio.grade')),
                ),
              );
            }).toList(),
    );
  }
}

class _EnrollmentCard extends StatelessWidget {
  const _EnrollmentCard({required this.enrollment, this.onRevoke});
  final Map<String, dynamic> enrollment;
  final VoidCallback? onRevoke;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final user = _map(enrollment['user']);
    return AirmiusPanel(
      child: Row(
        children: [
          const CircleAvatar(child: Icon(Icons.person_outline)),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(user['name'], fallback: t('studio.student')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  _text(user['email']),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_int(enrollment['progress_percent'])}% · ${_status(enrollment['status'])}',
                  style: TextStyle(color: airmiusAccentColor(context)),
                ),
              ],
            ),
          ),
          if (onRevoke != null)
            IconButton(
              tooltip: t('studio.revokeAccess'),
              onPressed: onRevoke,
              color: AirmiusColors.red,
              icon: const Icon(Icons.person_remove_outlined),
            ),
        ],
      ),
    );
  }
}

class _QuizSheet extends StatefulWidget {
  const _QuizSheet();

  @override
  State<_QuizSheet> createState() => _QuizSheetState();
}

class _QuizSheetState extends State<_QuizSheet> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _pass = TextEditingController(text: '70');
  final _question = TextEditingController();
  final _options = TextEditingController();
  final _correct = TextEditingController();
  final _explanation = TextEditingController();

  @override
  void dispose() {
    for (final controller in [
      _title,
      _description,
      _pass,
      _question,
      _options,
      _correct,
      _explanation,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('studio.addQuiz'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _title,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(labelText: t('studio.quizTitle')),
          ),
          TextField(
            controller: _description,
            maxLines: 2,
            decoration: InputDecoration(labelText: t('studio.description')),
          ),
          TextField(
            controller: _pass,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t('studio.passPercent')),
          ),
          const Divider(height: 28),
          TextField(
            controller: _question,
            maxLines: 2,
            decoration: InputDecoration(labelText: t('studio.question')),
          ),
          TextField(
            controller: _options,
            maxLines: 4,
            decoration: InputDecoration(
              labelText: t('studio.options'),
              helperText: t('studio.onePerLine'),
            ),
          ),
          TextField(
            controller: _correct,
            maxLines: 2,
            decoration: InputDecoration(
              labelText: t('studio.correctAnswers'),
              helperText: t('studio.onePerLine'),
            ),
          ),
          TextField(
            controller: _explanation,
            maxLines: 2,
            decoration: InputDecoration(labelText: t('studio.explanation')),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _title.text.trim().isEmpty
                ? null
                : () => Navigator.pop(context, {
                    'title': _title.text.trim(),
                    'description': _nullable(_description.text),
                    'pass_percent': int.tryParse(_pass.text) ?? 70,
                    'question': _nullable(_question.text),
                    'options_text': _nullable(_options.text),
                    'correct_options_text': _nullable(_correct.text),
                    'explanation': _nullable(_explanation.text),
                  }),
            icon: const Icon(Icons.quiz_outlined),
            label: Text(t('common.save')),
          ),
        ],
      ),
    );
  }
}

class _AssignmentSheet extends StatefulWidget {
  const _AssignmentSheet();

  @override
  State<_AssignmentSheet> createState() => _AssignmentSheetState();
}

class _AssignmentSheetState extends State<_AssignmentSheet> {
  final _title = TextEditingController();
  final _instructions = TextEditingController();
  final _points = TextEditingController(text: '100');
  final _dueDays = TextEditingController();
  bool _required = true;

  @override
  void dispose() {
    _title.dispose();
    _instructions.dispose();
    _points.dispose();
    _dueDays.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('studio.addAssignment'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _title,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(labelText: t('studio.assignmentTitle')),
          ),
          TextField(
            controller: _instructions,
            minLines: 3,
            maxLines: 7,
            decoration: InputDecoration(labelText: t('studio.instructions')),
          ),
          TextField(
            controller: _points,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t('studio.points')),
          ),
          TextField(
            controller: _dueDays,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t('studio.dueDays')),
          ),
          SwitchListTile(
            value: _required,
            contentPadding: EdgeInsets.zero,
            title: Text(t('studio.requiredAssignment')),
            onChanged: (value) => setState(() => _required = value),
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _title.text.trim().isEmpty
                ? null
                : () => Navigator.pop(context, {
                    'title': _title.text.trim(),
                    'instructions': _nullable(_instructions.text),
                    'points': int.tryParse(_points.text) ?? 100,
                    'due_after_days': int.tryParse(_dueDays.text),
                    'is_required': _required,
                  }),
            icon: const Icon(Icons.assignment_add),
            label: Text(t('common.save')),
          ),
        ],
      ),
    );
  }
}

class _GradeSheet extends StatefulWidget {
  const _GradeSheet({required this.submission});
  final Map<String, dynamic> submission;

  @override
  State<_GradeSheet> createState() => _GradeSheetState();
}

class _GradeSheetState extends State<_GradeSheet> {
  late final TextEditingController _score;
  late final TextEditingController _feedback;
  String _status = 'passed';

  @override
  void initState() {
    super.initState();
    _score = TextEditingController(text: '${widget.submission['score'] ?? ''}');
    _feedback = TextEditingController(
      text: _text(widget.submission['feedback']),
    );
    final current = _text(widget.submission['status']);
    if (['passed', 'needs_revision', 'rejected'].contains(current)) {
      _status = current;
    }
  }

  @override
  void dispose() {
    _score.dispose();
    _feedback.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('studio.grade'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_text(widget.submission['body']).isNotEmpty)
            AirmiusPanel(child: Text(_text(widget.submission['body']))),
          DropdownButtonFormField<String>(
            initialValue: _status,
            decoration: InputDecoration(labelText: t('studio.status')),
            items: ['passed', 'needs_revision', 'rejected']
                .map(
                  (status) => DropdownMenuItem(
                    value: status,
                    child: Text(t('studio.grade.$status')),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _status = value ?? _status),
          ),
          TextField(
            controller: _score,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t('studio.points')),
          ),
          TextField(
            controller: _feedback,
            minLines: 3,
            maxLines: 7,
            decoration: InputDecoration(labelText: t('studio.feedback')),
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, {
              'status': _status,
              'score': int.tryParse(_score.text),
              'feedback': _nullable(_feedback.text),
            }),
            icon: const Icon(Icons.grading_outlined),
            label: Text(t('common.save')),
          ),
        ],
      ),
    );
  }
}

class _CouponSheet extends StatefulWidget {
  const _CouponSheet();

  @override
  State<_CouponSheet> createState() => _CouponSheetState();
}

class _CouponSheetState extends State<_CouponSheet> {
  final _code = TextEditingController();
  final _value = TextEditingController(text: '10');
  final _maxRedemptions = TextEditingController();
  String _type = 'percent';
  bool _active = true;

  @override
  void dispose() {
    _code.dispose();
    _value.dispose();
    _maxRedemptions.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('studio.addCoupon'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _code,
            textCapitalization: TextCapitalization.characters,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(labelText: t('studio.couponCode')),
          ),
          DropdownButtonFormField<String>(
            initialValue: _type,
            decoration: InputDecoration(labelText: t('studio.discountType')),
            items: ['percent', 'fixed']
                .map(
                  (type) => DropdownMenuItem(
                    value: type,
                    child: Text(t('studio.discount.$type')),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _type = value ?? _type),
          ),
          TextField(
            controller: _value,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t('studio.discountValue')),
          ),
          TextField(
            controller: _maxRedemptions,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t('studio.maxRedemptions')),
          ),
          SwitchListTile(
            value: _active,
            contentPadding: EdgeInsets.zero,
            title: Text(t('studio.active')),
            onChanged: (value) => setState(() => _active = value),
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _code.text.trim().isEmpty
                ? null
                : () => Navigator.pop(context, {
                    'code': _code.text.trim().toUpperCase(),
                    'discount_type': _type,
                    'discount_value': _type == 'fixed'
                        ? _euro(_value.text)
                        : int.tryParse(_value.text) ?? 10,
                    'max_redemptions': int.tryParse(_maxRedemptions.text),
                    'is_active': _active,
                  }),
            icon: const Icon(Icons.local_offer_outlined),
            label: Text(t('common.save')),
          ),
        ],
      ),
    );
  }
}

class _CourseSheet extends StatefulWidget {
  const _CourseSheet({this.course});
  final Map<String, dynamic>? course;

  @override
  State<_CourseSheet> createState() => _CourseSheetState();
}

class _CourseSheetState extends State<_CourseSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _subtitle;
  late final TextEditingController _description;
  late final TextEditingController _sport;
  late final TextEditingController _price;
  late final TextEditingController _learningGoals;
  late final TextEditingController _requirements;
  late final TextEditingController _targetGroups;
  late final TextEditingController _salesPoints;
  late final TextEditingController _faqItems;
  late final TextEditingController _guarantee;
  late final TextEditingController _tags;
  late final TextEditingController _certificateLogo;
  late final TextEditingController _certificateSignature;
  late final TextEditingController _certificateFooter;
  String _category = 'training';
  String _level = 'beginner';
  String _language = 'de';
  String _status = 'draft';
  bool _public = false;
  bool _free = true;

  @override
  void initState() {
    super.initState();
    final c = widget.course ?? const <String, dynamic>{};
    _title = TextEditingController(text: _text(c['title']));
    _subtitle = TextEditingController(text: _text(c['subtitle']));
    _description = TextEditingController(text: _text(c['description']));
    _sport = TextEditingController(text: _text(c['sport_type']));
    _learningGoals = TextEditingController(
      text: _text(c['learning_goals_text']),
    );
    _requirements = TextEditingController(text: _text(c['requirements_text']));
    _targetGroups = TextEditingController(text: _text(c['target_groups_text']));
    _salesPoints = TextEditingController(text: _text(c['sales_points_text']));
    _faqItems = TextEditingController(text: _text(c['faq_items_text']));
    _guarantee = TextEditingController(text: _text(c['guarantee_text']));
    _tags = TextEditingController(text: _text(c['tags_text']));
    _certificateLogo = TextEditingController(
      text: _text(c['certificate_logo_url']),
    );
    _certificateSignature = TextEditingController(
      text: _text(c['certificate_signature_name']),
    );
    _certificateFooter = TextEditingController(
      text: _text(c['certificate_footer_text']),
    );
    _price = TextEditingController(
      text: c['price_cents'] == null
          ? '0.00'
          : (_int(c['price_cents']) / 100).toStringAsFixed(2),
    );
    _category = _text(c['category'], fallback: 'training');
    _level = _text(c['level'], fallback: 'beginner');
    _language = _text(c['language'], fallback: 'de');
    _status = _text(c['status'], fallback: 'draft');
    _public = c['is_public'] == true;
    _free = c.isEmpty || c['is_free'] == true;
  }

  @override
  void dispose() {
    for (final c in [
      _title,
      _subtitle,
      _description,
      _sport,
      _price,
      _learningGoals,
      _requirements,
      _targetGroups,
      _salesPoints,
      _faqItems,
      _guarantee,
      _tags,
      _certificateLogo,
      _certificateSignature,
      _certificateFooter,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t(
        widget.course == null ? 'studio.newCourse' : 'studio.editCourse',
      ),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _title,
              decoration: InputDecoration(labelText: t('studio.courseTitle')),
              validator: _required,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _subtitle,
              decoration: InputDecoration(labelText: t('studio.subtitleLabel')),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _description,
              decoration: InputDecoration(labelText: t('studio.description')),
              minLines: 3,
              maxLines: 6,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _category,
              decoration: InputDecoration(labelText: t('studio.category')),
              items:
                  [
                        'training',
                        'nutrition',
                        'mindset',
                        'tactics',
                        'rehab',
                        'coaching',
                        'club_management',
                      ]
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('studio.category.$value')),
                        ),
                      )
                      .toList(),
              onChanged: (value) =>
                  setState(() => _category = value ?? 'training'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _level,
              decoration: InputDecoration(labelText: t('studio.level')),
              items: ['beginner', 'intermediate', 'advanced', 'pro']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('studio.level.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _level = value ?? 'beginner'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _sport,
              decoration: InputDecoration(labelText: t('studio.sport')),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _language,
              decoration: InputDecoration(labelText: t('studio.language')),
              items: ['de', 'en', 'fr', 'ar']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('studio.language.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _language = value ?? _language),
            ),
            if (widget.course != null) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration: InputDecoration(labelText: t('studio.status')),
                items: ['draft', 'review', 'published', 'archived']
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('studio.status.$value')),
                      ),
                    )
                    .toList(),
                onChanged: (value) =>
                    setState(() => _status = value ?? 'draft'),
              ),
            ],
            const SizedBox(height: 8),
            SwitchListTile(
              value: _public,
              contentPadding: EdgeInsets.zero,
              title: Text(t('studio.publicCourse')),
              onChanged: (value) => setState(() => _public = value),
            ),
            SwitchListTile(
              value: _free,
              contentPadding: EdgeInsets.zero,
              title: Text(t('studio.freeCourse')),
              onChanged: (value) => setState(() => _free = value),
            ),
            if (!_free)
              TextFormField(
                controller: _price,
                decoration: InputDecoration(
                  labelText: t('studio.price'),
                  suffixText: 'EUR',
                ),
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
              ),
            const SizedBox(height: 12),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: const EdgeInsets.only(bottom: 8),
              leading: const Icon(Icons.school_outlined),
              title: Text(
                t('studio.didactics'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Text(t('studio.didacticsHint')),
              children: [
                TextField(
                  controller: _learningGoals,
                  minLines: 3,
                  maxLines: 7,
                  decoration: InputDecoration(
                    labelText: t('studio.learningGoals'),
                    helperText: t('studio.oneItemPerLine'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _requirements,
                  minLines: 3,
                  maxLines: 7,
                  decoration: InputDecoration(
                    labelText: t('studio.requirements'),
                    helperText: t('studio.oneItemPerLine'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _targetGroups,
                  minLines: 3,
                  maxLines: 7,
                  decoration: InputDecoration(
                    labelText: t('studio.targetGroups'),
                    helperText: t('studio.oneItemPerLine'),
                  ),
                ),
              ],
            ),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: const EdgeInsets.only(bottom: 8),
              leading: const Icon(Icons.storefront_outlined),
              title: Text(
                t('studio.marketing'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Text(t('studio.marketingHint')),
              children: [
                TextField(
                  controller: _salesPoints,
                  minLines: 3,
                  maxLines: 7,
                  decoration: InputDecoration(
                    labelText: t('studio.salesPoints'),
                    helperText: t('studio.oneItemPerLine'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _faqItems,
                  minLines: 3,
                  maxLines: 8,
                  decoration: InputDecoration(
                    labelText: t('studio.faqItems'),
                    helperText: t('studio.faqHint'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _guarantee,
                  minLines: 2,
                  maxLines: 6,
                  decoration: InputDecoration(labelText: t('studio.guarantee')),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _tags,
                  decoration: InputDecoration(
                    labelText: t('studio.tags'),
                    helperText: t('studio.tagsHint'),
                  ),
                ),
              ],
            ),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: const EdgeInsets.only(bottom: 8),
              leading: const Icon(Icons.workspace_premium_outlined),
              title: Text(
                t('studio.certificateDesign'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Text(t('studio.certificateDesignHint')),
              children: [
                TextField(
                  controller: _certificateLogo,
                  keyboardType: TextInputType.url,
                  decoration: InputDecoration(
                    labelText: t('studio.certificateLogo'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _certificateSignature,
                  decoration: InputDecoration(
                    labelText: t('studio.certificateSignature'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _certificateFooter,
                  minLines: 2,
                  maxLines: 5,
                  decoration: InputDecoration(
                    labelText: t('studio.certificateFooter'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () {
                if (!(_form.currentState?.validate() ?? false)) return;
                Navigator.pop(context, {
                  'title': _title.text.trim(),
                  'subtitle': _nullable(_subtitle.text),
                  'description': _nullable(_description.text),
                  'category': _category,
                  'sport_type': _nullable(_sport.text),
                  'level': _level,
                  'language': _language,
                  'status': _status,
                  'is_public': _public,
                  'is_free': _free,
                  'price_cents': _free ? 0 : _euro(_price.text),
                  'learning_goals_text': _learningGoals.text.trim(),
                  'requirements_text': _requirements.text.trim(),
                  'target_groups_text': _targetGroups.text.trim(),
                  'sales_points_text': _salesPoints.text.trim(),
                  'faq_items_text': _faqItems.text.trim(),
                  'guarantee_text': _nullable(_guarantee.text),
                  'tags_text': _tags.text.trim(),
                  'certificate_logo_url': _nullable(_certificateLogo.text),
                  'certificate_signature_name': _nullable(
                    _certificateSignature.text,
                  ),
                  'certificate_footer_text': _nullable(_certificateFooter.text),
                });
              },
              icon: const Icon(Icons.save_outlined),
              label: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
  }

  String? _required(String? value) => value == null || value.trim().isEmpty
      ? AirmiusScope.of(context).t('studio.required')
      : null;
}

class _SectionSheet extends StatefulWidget {
  const _SectionSheet();

  @override
  State<_SectionSheet> createState() => _SectionSheetState();
}

class _SectionSheetState extends State<_SectionSheet> {
  final _title = TextEditingController();
  final _description = TextEditingController();

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('studio.addSection'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _title,
            decoration: InputDecoration(labelText: t('studio.sectionTitle')),
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            decoration: InputDecoration(labelText: t('studio.description')),
            minLines: 2,
            maxLines: 4,
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _title.text.trim().isEmpty
                ? null
                : () => Navigator.pop(context, {
                    'title': _title.text.trim(),
                    'description': _nullable(_description.text),
                  }),
            icon: const Icon(Icons.add_outlined),
            label: Text(t('studio.addSection')),
          ),
        ],
      ),
    );
  }
}

class _LessonSheet extends StatefulWidget {
  const _LessonSheet({required this.sections, this.lesson});
  final List<Map<String, dynamic>> sections;
  final Map<String, dynamic>? lesson;

  @override
  State<_LessonSheet> createState() => _LessonSheetState();
}

class _LessonSheetState extends State<_LessonSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _summary;
  late final TextEditingController _content;
  late final TextEditingController _video;
  late final TextEditingController _duration;
  late final TextEditingController _attachments;
  late final TextEditingController _unlockAfterDays;
  late int _sectionId;
  String _type = 'lesson';
  bool _preview = false;

  @override
  void initState() {
    super.initState();
    final l = widget.lesson ?? const <String, dynamic>{};
    _title = TextEditingController(text: _text(l['title']));
    _summary = TextEditingController(text: _text(l['summary']));
    _content = TextEditingController(text: _text(l['content']));
    _video = TextEditingController(text: _text(l['video_url']));
    _duration = TextEditingController(text: '${_int(l['duration_minutes'])}');
    _attachments = TextEditingController(
      text: _attachmentLines(l['attachments']),
    );
    _unlockAfterDays = TextEditingController(
      text: '${_int(l['unlock_after_days'])}',
    );
    _sectionId = _int(
      l['learning_course_section_id'] ?? widget.sections.first['id'],
    );
    _type = _text(l['type'], fallback: 'lesson');
    _preview = l['is_preview'] == true;
  }

  @override
  void dispose() {
    for (final c in [
      _title,
      _summary,
      _content,
      _video,
      _duration,
      _attachments,
      _unlockAfterDays,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t(
        widget.lesson == null ? 'studio.addLesson' : 'studio.editLesson',
      ),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<int>(
              initialValue: _sectionId,
              decoration: InputDecoration(labelText: t('studio.section')),
              items: widget.sections
                  .map(
                    (section) => DropdownMenuItem(
                      value: _int(section['id']),
                      child: Text(_text(section['title'])),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _sectionId = value ?? _sectionId),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _title,
              decoration: InputDecoration(labelText: t('studio.lessonTitle')),
              validator: (value) => value == null || value.trim().isEmpty
                  ? t('studio.required')
                  : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: t('studio.lessonType')),
              items:
                  ['lesson', 'video', 'exercise', 'assignment', 'live_session']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('studio.type.$value')),
                        ),
                      )
                      .toList(),
              onChanged: (value) => setState(() => _type = value ?? 'lesson'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _summary,
              decoration: InputDecoration(labelText: t('studio.summary')),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _content,
              decoration: InputDecoration(labelText: t('studio.lessonContent')),
              minLines: 5,
              maxLines: 12,
            ),
            if (_type == 'video') ...[
              const SizedBox(height: 12),
              TextField(
                controller: _video,
                decoration: InputDecoration(labelText: t('studio.videoUrl')),
                keyboardType: TextInputType.url,
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              controller: _duration,
              decoration: InputDecoration(labelText: t('studio.duration')),
              keyboardType: TextInputType.number,
            ),
            SwitchListTile(
              value: _preview,
              contentPadding: EdgeInsets.zero,
              title: Text(t('studio.previewLesson')),
              onChanged: (value) => setState(() => _preview = value),
            ),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: const EdgeInsets.only(bottom: 8),
              leading: const Icon(Icons.tune_outlined),
              title: Text(
                t('studio.lessonAvailability'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Text(t('studio.lessonAvailabilityHint')),
              children: [
                TextField(
                  controller: _unlockAfterDays,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: t('studio.unlockAfterDays'),
                    helperText: t('studio.unlockAfterDaysHint'),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _attachments,
                  minLines: 3,
                  maxLines: 7,
                  keyboardType: TextInputType.url,
                  decoration: InputDecoration(
                    labelText: t('studio.attachments'),
                    helperText: t('studio.attachmentsHint'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () {
                if (!(_form.currentState?.validate() ?? false)) return;
                Navigator.pop(context, {
                  'learning_course_section_id': _sectionId,
                  'title': _title.text.trim(),
                  'type': _type,
                  'summary': _nullable(_summary.text),
                  'content': _nullable(_content.text),
                  'video_url': _nullable(_video.text),
                  'duration_minutes': int.tryParse(_duration.text) ?? 0,
                  'is_preview': _preview,
                  'unlock_after_days': int.tryParse(_unlockAfterDays.text) ?? 0,
                  'attachments_text': _attachments.text.trim(),
                });
              },
              icon: const Icon(Icons.save_outlined),
              label: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
  }
}

class _EnrollmentSheet extends StatefulWidget {
  const _EnrollmentSheet();

  @override
  State<_EnrollmentSheet> createState() => _EnrollmentSheetState();
}

class _EnrollmentSheetState extends State<_EnrollmentSheet> {
  final _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('studio.grantAccess'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _email,
            decoration: InputDecoration(labelText: t('studio.studentEmail')),
            keyboardType: TextInputType.emailAddress,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _email.text.contains('@')
                ? () => Navigator.pop(context, _email.text.trim())
                : null,
            icon: const Icon(Icons.person_add_alt_outlined),
            label: Text(t('studio.grantAccess')),
          ),
        ],
      ),
    );
  }
}

class _StudioEmpty extends StatelessWidget {
  const _StudioEmpty({
    required this.icon,
    required this.title,
    required this.body,
    this.action,
  });
  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Column(
        children: [
          Icon(icon, color: airmiusMutedColor(context), size: 44),
          const SizedBox(height: 10),
          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            textAlign: TextAlign.center,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          if (action != null) ...[const SizedBox(height: 12), action!],
        ],
      ),
    ),
  );
}

class _Sheet extends StatelessWidget {
  const _Sheet({required this.title, required this.child});
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => DraggableScrollableSheet(
    expand: false,
    initialChildSize: 0.92,
    minChildSize: 0.55,
    maxChildSize: 0.98,
    builder: (context, controller) => Material(
      color: airmiusSurfaceColor(context),
      child: ListView(
        controller: controller,
        padding: EdgeInsets.fromLTRB(
          18,
          14,
          18,
          24 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        children: [
          Center(
            child: Container(
              width: 46,
              height: 5,
              decoration: BoxDecoration(
                color: airmiusBorderColor(context),
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 23,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close),
              ),
            ],
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    ),
  );
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

int _euro(String value) =>
    ((double.tryParse(value.replaceAll(',', '.')) ?? 0) * 100).round();

String _status(Object? value) => _text(value, fallback: '–')
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (part) => part.isEmpty
          ? part
          : '${part.substring(0, 1).toUpperCase()}${part.substring(1)}',
    )
    .join(' ');

String? _nullable(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _attachmentLines(Object? value) {
  if (value is! List) return '';
  return value
      .map((item) {
        if (item is Map) {
          return _text(item['url'] ?? item['path']);
        }
        return _text(item);
      })
      .where((item) => item.isNotEmpty)
      .join('\n');
}
