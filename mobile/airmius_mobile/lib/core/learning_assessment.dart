import 'dart:convert';
import 'package:flutter/widgets.dart';
import 'airmius_api_models.dart';
import 'airmius_l10n.dart';
import 'learning_assessment_copy.dart';

const assessmentTypes = [
  'short_text',
  'long_text',
  'multiple_choice',
  'single_choice',
  'ordering',
];
var _assessmentKey = 0;
String assessmentText(BuildContext context, String key) =>
    (learningAssessmentCopy[AirmiusScope.of(context).language.name] ??
        learningAssessmentCopy['en']!)[key] ??
    key;
List<JsonMap> assessmentMaps(Object? value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : [];
JsonMap newAssessmentOption() => {
  'id': 'option_${DateTime.now().microsecondsSinceEpoch}_${_assessmentKey++}',
  'text': '',
  'image_url': null,
};
JsonMap newAssessmentQuestion({String type = 'single_choice'}) => {
  'id': null,
  'editor_key': 'q_${_assessmentKey++}',
  'question': '',
  'question_type': type,
  'image_url': null,
  'options': [newAssessmentOption(), newAssessmentOption()],
  'correct_options': <String>[],
  'explanation': '',
};

List<JsonMap> editableAssessmentQuestions(Object? value) =>
    assessmentMaps(jsonDecode(jsonEncode(value ?? []))).map((q) {
      final raw = q['options'] is List ? q['options'] as List : [];
      final oldCorrect = (q['correct_options'] as List? ?? [])
          .map((e) => '$e')
          .toList();
      final options = raw.indexed
          .map(
            (entry) => entry.$2 is Map
                ? Map<String, dynamic>.from(entry.$2 as Map)
                : {
                    'id': 'option_${entry.$1}',
                    'text': '${entry.$2}',
                    'image_url': null,
                  },
          )
          .toList();
      return {
        ...q,
        'editor_key': 'q_${_assessmentKey++}',
        'question_type': q['question_type'] ?? 'multiple_choice',
        'options': options,
        'correct_options': oldCorrect.map((v) {
          final index = raw.indexWhere((o) => o is String && o == v);
          return index >= 0 ? 'option_$index' : v;
        }).toList(),
      };
    }).toList();

String? assessmentEditorError(List<JsonMap> questions, bool solutions) {
  if (solutions && questions.isEmpty) return 'answer_required';
  for (final q in questions) {
    if ('${q['question'] ?? ''}'.trim().isEmpty &&
        '${q['image_url'] ?? ''}'.trim().isEmpty) {
      return 'text_or_image';
    }
    final type = q['question_type'];
    final correct = q['correct_options'] as List? ?? [];
    if (['single_choice', 'multiple_choice', 'ordering'].contains(type)) {
      final options = assessmentMaps(q['options']);
      if (options.length < 2) return 'two_options';
      for (final option in options) {
        if ('${option['text'] ?? ''}'.trim().isEmpty &&
            '${option['image_url'] ?? ''}'.trim().isEmpty) {
          return 'text_or_image';
        }
      }
      if (solutions && type != 'ordering' && correct.isEmpty) {
        return 'correct_required';
      }
      if (solutions && type == 'single_choice' && correct.length != 1) {
        return 'one_correct';
      }
    } else if (solutions &&
        type == 'short_text' &&
        correct.where((e) => '$e'.trim().isNotEmpty).isEmpty) {
      return 'correct_required';
    }
  }
  return null;
}

bool assessmentAnswersComplete(List<JsonMap> questions, JsonMap answers) =>
    questions.every((q) {
      final answer = answers['${q['id']}'];
      return ['short_text', 'long_text'].contains(q['question_type'])
          ? answer is String && answer.trim().isNotEmpty
          : answer is List && answer.isNotEmpty;
    });
