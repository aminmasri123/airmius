import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/training_count_labels.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  for (final entry in {
    AirmiusLanguage.de: [
      '1 Übung · 1 Soll-Satz',
      '1 Übung · 3 Soll-Sätze',
      '2 Übungen · 3 Soll-Sätze',
    ],
    AirmiusLanguage.en: [
      '1 exercise · 1 target set',
      '1 exercise · 3 target sets',
      '2 exercises · 3 target sets',
    ],
    AirmiusLanguage.fr: [
      '1 exercice · 1 série prévue',
      '1 exercice · 3 séries prévues',
      '2 exercices · 3 séries prévues',
    ],
    AirmiusLanguage.ar: [
      '1 تمرين · 1 مجموعة مستهدفة',
      '1 تمرين · 3 مجموعات مستهدفة',
      '2 تمارين · 3 مجموعات مستهدفة',
    ],
  }.entries) {
    test('training counts in ${entry.key.name}', () {
      final scope = AirmiusScope(
        language: entry.key,
        setLanguage: (_) {},
        child: const SizedBox(),
      );
      expect(
        trainingPlanCountLabel(scope.t, exercises: 1, sets: 1),
        entry.value[0],
      );
      expect(
        trainingPlanCountLabel(scope.t, exercises: 1, sets: 3),
        entry.value[1],
      );
      expect(
        trainingPlanCountLabel(scope.t, exercises: 2, sets: 3),
        entry.value[2],
      );
      for (final key in [
        'studio.lessonSingular',
        'trainingHub.itemSingular',
        'studio.status.draft',
      ]) {
        expect(scope.t(key), isNot(key));
      }
    });
  }
}
