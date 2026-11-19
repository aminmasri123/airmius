import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/learning_assessment.dart';
import '../widgets/learning_assessment_fields.dart';

class LearningAssessmentEditorScreen extends StatefulWidget {
  const LearningAssessmentEditorScreen({
    super.key,
    required this.assignment,
    required this.onSave,
    required this.uploadImage,
    this.initial,
  });
  final bool assignment;
  final JsonMap? initial;
  final Future<void> Function(JsonMap) onSave;
  final Future<String> Function(PlatformFile) uploadImage;
  @override
  State<LearningAssessmentEditorScreen> createState() =>
      _LearningAssessmentEditorScreenState();
}

class _LearningAssessmentEditorScreenState
    extends State<LearningAssessmentEditorScreen> {
  final _form = GlobalKey<FormState>();
  late final _title = TextEditingController(
    text: '${widget.initial?['title'] ?? ''}',
  );
  late final _description = TextEditingController(
    text:
        '${widget.initial?[widget.assignment ? 'instructions' : 'description'] ?? ''}',
  );
  late final _number = TextEditingController(
    text:
        '${widget.initial?[widget.assignment ? 'points' : 'pass_percent'] ?? (widget.assignment ? 100 : 70)}',
  );
  late final _days = TextEditingController(
    text: '${widget.initial?['due_after_days'] ?? ''}',
  );
  late final List<JsonMap> _questions = widget.initial == null
      ? [
          newAssessmentQuestion(
            type: widget.assignment ? 'long_text' : 'single_choice',
          ),
        ]
      : editableAssessmentQuestions(widget.initial?['questions']);
  late bool _required = widget.initial?['is_required'] != false;
  bool _saving = false, _uploading = false;
  String? _error;
  @override
  void dispose() {
    for (final c in [_title, _description, _number, _days]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || _uploading || !(_form.currentState?.validate() ?? false)) {
      return;
    }
    final problem = assessmentEditorError(_questions, !widget.assignment);
    if (problem != null) {
      setState(() => _error = assessmentText(context, problem));
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave({
        'title': _title.text.trim(),
        'learning_lesson_id': widget.initial?['learning_lesson_id'],
        if (widget.assignment) ...{
          'instructions': _description.text.trim(),
          'points': int.parse(_number.text),
          'due_after_days': int.tryParse(_days.text),
          'is_required': _required,
        } else ...{
          'description': _description.text.trim(),
          'pass_percent': int.parse(_number.text),
        },
        'questions': _questions,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : assessmentText(context, 'changed'),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    String t(String key) => assessmentText(context, key);
    return PopScope(
      canPop: !_saving && !_uploading,
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            '${t(widget.assignment ? 'assignment' : 'quiz')} · ${t(widget.initial == null ? 'new' : 'edit')}',
          ),
        ),
        body: AbsorbPointer(
          absorbing: _saving,
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    key: const ValueKey('assessment-title'),
                    controller: _title,
                    decoration: InputDecoration(labelText: t('title')),
                    maxLength: 255,
                    validator: (v) => v == null || v.trim().isEmpty
                        ? t('answer_required')
                        : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _description,
                    minLines: 2,
                    maxLines: 6,
                    decoration: InputDecoration(
                      labelText: t(
                        widget.assignment ? 'instructions' : 'description',
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _number,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: t(
                        widget.assignment ? 'points' : 'pass_percent',
                      ),
                    ),
                    validator: (v) {
                      final n = int.tryParse(v ?? '');
                      return n == null ||
                              n < 1 ||
                              n > (widget.assignment ? 10000 : 100)
                          ? t('invalid_answer')
                          : null;
                    },
                  ),
                  if (widget.assignment) ...[
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _days,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: t('days')),
                      validator: (v) {
                        if (v == null || v.isEmpty) return null;
                        final n = int.tryParse(v);
                        return n == null || n < 0 || n > 3650
                            ? t('invalid_answer')
                            : null;
                      },
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(t('required_task')),
                      value: _required,
                      onChanged: (v) => setState(() => _required = v),
                    ),
                  ],
                  const SizedBox(height: 24),
                  LearningAssessmentEditorFields(
                    questions: _questions,
                    solutions: !widget.assignment,
                    uploadImage: widget.uploadImage,
                    onChanged: () => setState(() {}),
                    onBusy: (v) => setState(() => _uploading = v),
                  ),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        _error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              12,
              16,
              12 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: FilledButton.icon(
              key: const ValueKey('assessment-save'),
              onPressed: _saving || _uploading ? null : _save,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.save_outlined),
              label: Text(t('save')),
            ),
          ),
        ),
      ),
    );
  }
}

class LearningQuizReviewScreen extends StatefulWidget {
  const LearningQuizReviewScreen({
    super.key,
    required this.attempt,
    required this.onSave,
  });
  final JsonMap attempt;
  final Future<void> Function(JsonMap) onSave;
  @override
  State<LearningQuizReviewScreen> createState() =>
      _LearningQuizReviewScreenState();
}

class _LearningQuizReviewScreenState extends State<LearningQuizReviewScreen> {
  final _feedback = TextEditingController();
  final _grades = <String, bool>{};
  bool _busy = false;
  String? _error;
  @override
  void dispose() {
    _feedback.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    String t(String key) => assessmentText(context, key);
    final questions = assessmentMaps(widget.attempt['question_snapshot']);
    final answers = Map<String, dynamic>.from(
      widget.attempt['answers'] as Map? ?? {},
    );
    final manual = questions
        .where((q) => q['question_type'] == 'long_text')
        .toList();
    return Scaffold(
      appBar: AppBar(title: Text(t('review'))),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              '${(widget.attempt['user'] as Map?)?['name'] ?? ''}',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 16),
            LearningAssessmentAnswersSummary(
              questions: questions,
              answers: answers,
            ),
            for (final q in manual)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('${q['question'] ?? ''}'),
                    RadioGroup<bool>(
                      groupValue: _grades['${q['id']}'],
                      onChanged: _busy
                          ? (_) {}
                          : (v) => setState(() => _grades['${q['id']}'] = v!),
                      child: Column(
                        children: [
                          RadioListTile(value: true, title: Text(t('correct'))),
                          RadioListTile(
                            value: false,
                            title: Text(t('incorrect')),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            TextField(
              controller: _feedback,
              minLines: 2,
              maxLines: 6,
              decoration: InputDecoration(labelText: t('feedback')),
            ),
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            12,
            16,
            12 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: FilledButton(
            onPressed:
                _busy || manual.any((q) => !_grades.containsKey('${q['id']}'))
                ? null
                : () async {
                    setState(() => _busy = true);
                    try {
                      await widget.onSave({
                        'manual_grades': _grades,
                        'feedback': _feedback.text.trim(),
                      });
                      if (mounted) Navigator.pop(this.context, true);
                    } catch (error) {
                      if (mounted) {
                        setState(
                          () => _error = error is AirmiusApiException
                              ? error.userMessage
                              : t('changed'),
                        );
                      }
                    } finally {
                      if (mounted) setState(() => _busy = false);
                    }
                  },
            child: Text(t('grade')),
          ),
        ),
      ),
    );
  }
}
