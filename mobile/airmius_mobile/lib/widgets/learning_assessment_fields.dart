import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/learning_assessment.dart';

class AssessmentImage extends StatelessWidget {
  const AssessmentImage({super.key, this.url});
  final Object? url;
  @override
  Widget build(BuildContext context) => url == null || '$url'.isEmpty
      ? const SizedBox.shrink()
      : Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Image.network(
            '$url',
            height: 130,
            fit: BoxFit.contain,
            semanticLabel: assessmentText(context, 'image_only'),
            errorBuilder: (_, _, _) =>
                const Icon(Icons.broken_image_outlined),
          ),
        );
}

class LearningAssessmentEditorFields extends StatefulWidget {
  const LearningAssessmentEditorFields({
    super.key,
    required this.questions,
    required this.solutions,
    required this.uploadImage,
    required this.onChanged,
    required this.onBusy,
  });
  final List<JsonMap> questions;
  final bool solutions;
  final Future<String> Function(PlatformFile) uploadImage;
  final VoidCallback onChanged;
  final ValueChanged<bool> onBusy;
  @override
  State<LearningAssessmentEditorFields> createState() =>
      _LearningAssessmentEditorFieldsState();
}

class _LearningAssessmentEditorFieldsState
    extends State<LearningAssessmentEditorFields> {
  bool _busy = false;
  String? _error;
  void _change(VoidCallback mutation) {
    setState(mutation);
    widget.onChanged();
  }

  Future<void> _image(JsonMap target) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    widget.onBusy(true);
    try {
      final file = (await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        withReadStream: true,
      ))?.files.single;
      if (file == null || !mounted) return;
      final url = await widget.uploadImage(file);
      if (mounted) _change(() => target['image_url'] = url);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : assessmentText(context, 'text_or_image'),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        widget.onBusy(false);
      }
    }
  }

  Widget _imageField(JsonMap target, String key) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      TextFormField(
        key: ValueKey('$key-${target['image_url']}'),
        initialValue: '${target['image_url'] ?? ''}',
        decoration: InputDecoration(
          labelText: assessmentText(context, 'image'),
        ),
        onChanged: (value) =>
            target['image_url'] = value.trim().isEmpty ? null : value.trim(),
      ),
      const SizedBox(height: 8),
      OutlinedButton.icon(
        onPressed: _busy ? null : () => _image(target),
        icon: const Icon(Icons.add_photo_alternate_outlined),
        label: Text(assessmentText(context, 'upload_image')),
      ),
      AssessmentImage(url: target['image_url']),
    ],
  );
  @override
  Widget build(BuildContext context) {
    String t(String key) => assessmentText(context, key);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_busy) ...[Text(t('uploading')), const LinearProgressIndicator()],
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        for (var i = 0; i < widget.questions.length; i++)
          _question(widget.questions[i], i),
        OutlinedButton.icon(
          key: const ValueKey('assessment-add-question'),
          onPressed: _busy || widget.questions.length >= 50
              ? null
              : () => _change(
                  () => widget.questions.add(
                    newAssessmentQuestion(
                      type: widget.solutions ? 'single_choice' : 'long_text',
                    ),
                  ),
                ),
          icon: const Icon(Icons.add),
          label: Text(t('add_question')),
        ),
      ],
    );
  }

  Widget _question(JsonMap q, int index) {
    String t(String key) => assessmentText(context, key);
    final options = (q['options'] as List? ?? []).cast<JsonMap>();
    final correct = (q['correct_options'] as List? ?? [])
        .map((e) => '$e')
        .toList();
    return Card(
      key: ValueKey(q['editor_key']),
      margin: const EdgeInsets.only(bottom: 20),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${t('question')} ${index + 1}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                IconButton(
                  tooltip: t('up'),
                  onPressed: _busy || index == 0
                      ? null
                      : () => _change(() {
                          final value = widget.questions.removeAt(index);
                          widget.questions.insert(index - 1, value);
                        }),
                  icon: const Icon(Icons.arrow_upward),
                ),
                IconButton(
                  tooltip: t('remove_question'),
                  onPressed: _busy
                      ? null
                      : () => _change(() => widget.questions.removeAt(index)),
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              initialValue: '${q['question'] ?? ''}',
              maxLines: 3,
              minLines: 1,
              decoration: InputDecoration(labelText: t('question')),
              onChanged: (v) => q['question'] = v,
            ),
            const SizedBox(height: 16),
            _imageField(q, 'question-${q['editor_key']}'),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              key: ValueKey('type-${q['editor_key']}'),
              initialValue: q['question_type'] as String,
              isExpanded: true,
              decoration: InputDecoration(labelText: t('question_type')),
              items: assessmentTypes
                  .map(
                    (type) => DropdownMenuItem(
                      value: type,
                      child: Text(t(type), overflow: TextOverflow.ellipsis),
                    ),
                  )
                  .toList(),
              onChanged: _busy
                  ? null
                  : (value) => _change(() {
                      q['question_type'] = value;
                      q['correct_options'] = <String>[];
                      if ([
                            'single_choice',
                            'multiple_choice',
                            'ordering',
                          ].contains(value) &&
                          options.length < 2) {
                        q['options'] = [
                          newAssessmentOption(),
                          newAssessmentOption(),
                        ];
                      }
                    }),
            ),
            const SizedBox(height: 16),
            if ([
              'single_choice',
              'multiple_choice',
              'ordering',
            ].contains(q['question_type'])) ...[
              if (q['question_type'] == 'ordering') Text(t('ordering_hint')),
              for (var j = 0; j < options.length; j++)
                Container(
                  key: ValueKey(options[j]['id']),
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: Theme.of(context).colorScheme.outlineVariant,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        initialValue: '${options[j]['text'] ?? ''}',
                        decoration: InputDecoration(
                          labelText: '${t('option')} ${j + 1}',
                        ),
                        onChanged: (v) => options[j]['text'] = v,
                      ),
                      const SizedBox(height: 12),
                      _imageField(
                        options[j],
                        '${q['editor_key']}-${options[j]['id']}',
                      ),
                      if (widget.solutions && q['question_type'] != 'ordering')
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(t('correct')),
                          value: correct.contains(options[j]['id']),
                          onChanged: _busy
                              ? null
                              : (value) => _change(() {
                                  final id = '${options[j]['id']}';
                                  q['correct_options'] = value == true
                                      ? (q['question_type'] == 'single_choice'
                                            ? [id]
                                            : [...correct, id])
                                      : correct.where((v) => v != id).toList();
                                }),
                        ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          if (q['question_type'] == 'ordering')
                            IconButton(
                              tooltip: t('up'),
                              onPressed: _busy || j == 0
                                  ? null
                                  : () => _change(() {
                                      final value = options.removeAt(j);
                                      options.insert(j - 1, value);
                                      q['options'] = options;
                                    }),
                              icon: const Icon(Icons.arrow_upward),
                            ),
                          IconButton(
                            tooltip: t('remove_option'),
                            onPressed: _busy
                                ? null
                                : () => _change(() {
                                    final id = options[j]['id'];
                                    options.removeAt(j);
                                    q['options'] = options;
                                    q['correct_options'] = correct
                                        .where((v) => v != id)
                                        .toList();
                                  }),
                            icon: const Icon(Icons.close),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              OutlinedButton(
                onPressed: _busy || options.length >= 20
                    ? null
                    : () => _change(() {
                        options.add(newAssessmentOption());
                        q['options'] = options;
                      }),
                child: Text(t('add_option')),
              ),
            ],
            if (widget.solutions && q['question_type'] == 'short_text')
              TextFormField(
                key: ValueKey('accepted-${q['editor_key']}'),
                initialValue: correct.join('\n'),
                minLines: 2,
                maxLines: 5,
                decoration: InputDecoration(labelText: t('accepted_answers')),
                onChanged: (value) => q['correct_options'] = value
                    .split('\n')
                    .where((v) => v.trim().isNotEmpty)
                    .toList(),
              ),
            if (q['question_type'] == 'long_text')
              Text(t(widget.solutions ? 'manual_hint' : 'assignment_manual')),
            const SizedBox(height: 16),
            if (widget.solutions)
              TextFormField(
                initialValue: '${q['explanation'] ?? ''}',
                decoration: InputDecoration(labelText: t('explanation')),
                maxLines: 3,
                minLines: 1,
                onChanged: (value) => q['explanation'] = value,
              ),
          ],
        ),
      ),
    );
  }
}

class LearningAssessmentAnswerFields extends StatefulWidget {
  const LearningAssessmentAnswerFields({
    super.key,
    required this.questions,
    required this.answers,
  });
  final List<JsonMap> questions;
  final JsonMap answers;
  @override
  State<LearningAssessmentAnswerFields> createState() =>
      _LearningAssessmentAnswerFieldsState();
}

class _LearningAssessmentAnswerFieldsState
    extends State<LearningAssessmentAnswerFields> {
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [for (final q in widget.questions) _question(q)],
  );
  Widget _question(JsonMap q) {
    final id = '${q['id']}';
    final type = '${q['question_type'] ?? 'multiple_choice'}';
    final raw = q['options'] as List? ?? [];
    final options = raw
        .map(
          (o) => o is Map
              ? Map<String, dynamic>.from(o)
              : <String, dynamic>{'id': '$o', 'text': '$o'},
        )
        .toList();
    if (type == 'ordering') {
      widget.answers.putIfAbsent(
        id,
        () => options.map((o) => '${o['id']}').toList(),
      );
    }
    final selected = widget.answers[id] is List
        ? (widget.answers[id] as List).map((e) => '$e').toList()
        : <String>[];
    Widget label(JsonMap option) => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if ('${option['text'] ?? ''}'.isNotEmpty) Text('${option['text']}'),
        AssessmentImage(url: option['image_url']),
      ],
    );
    return Padding(
      key: ValueKey('answer-$id'),
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if ('${q['question'] ?? ''}'.isNotEmpty)
            Text(
              '${q['question']}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
          AssessmentImage(url: q['image_url']),
          const SizedBox(height: 12),
          if (['short_text', 'long_text'].contains(type))
            TextFormField(
              initialValue: widget.answers[id] as String? ?? '',
              minLines: type == 'long_text' ? 4 : 1,
              maxLines: type == 'long_text' ? 10 : 1,
              maxLength: type == 'long_text' ? 10000 : 1000,
              decoration: InputDecoration(
                labelText: assessmentText(context, 'answer_placeholder'),
              ),
              onChanged: (v) => widget.answers[id] = v,
            )
          else if (type == 'ordering') ...[
            for (var i = 0; i < selected.length; i++)
              Row(
                children: [
                  Text('${i + 1}.'),
                  const SizedBox(width: 8),
                  Expanded(
                    child: label(
                      options.firstWhere((o) => '${o['id']}' == selected[i]),
                    ),
                  ),
                  IconButton(
                    tooltip: assessmentText(context, 'up'),
                    onPressed: i == 0
                        ? null
                        : () => setState(() {
                            final value = selected.removeAt(i);
                            selected.insert(i - 1, value);
                            widget.answers[id] = selected;
                          }),
                    icon: const Icon(Icons.arrow_upward),
                  ),
                  IconButton(
                    tooltip: assessmentText(context, 'down'),
                    onPressed: i == selected.length - 1
                        ? null
                        : () => setState(() {
                            final value = selected.removeAt(i);
                            selected.insert(i + 1, value);
                            widget.answers[id] = selected;
                          }),
                    icon: const Icon(Icons.arrow_downward),
                  ),
                ],
              ),
          ] else
            for (final option in options)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: label(option),
                value: selected.contains('${option['id']}'),
                onChanged: (value) => setState(() {
                  final key = '${option['id']}';
                  widget.answers[id] = value == true
                      ? (type == 'single_choice' ? [key] : [...selected, key])
                      : selected.where((v) => v != key).toList();
                }),
              ),
        ],
      ),
    );
  }
}

class LearningAssessmentAnswersSummary extends StatelessWidget {
  const LearningAssessmentAnswersSummary({
    super.key,
    required this.questions,
    required this.answers,
  });
  final List<JsonMap> questions;
  final JsonMap answers;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final q in questions)
        Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                '${q['question'] ?? ''}',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              AssessmentImage(url: q['image_url']),
              if (answers['${q['id']}'] is List) ...[
                for (final id in answers['${q['id']}'] as List) ...[
                  for (final option in assessmentMaps(
                    q['options'],
                  ).where((o) => '${o['id']}' == '$id')) ...[
                    Text('${option['text'] ?? ''}'),
                    AssessmentImage(url: option['image_url']),
                  ],
                ],
              ] else
                Text('${answers['${q['id']}'] ?? ''}'),
            ],
          ),
        ),
    ],
  );
}
