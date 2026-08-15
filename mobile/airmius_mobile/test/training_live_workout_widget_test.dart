import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/screens/training_plans_logs_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('live workout fits a phone viewport without layout errors', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: MaterialApp(
          theme: AirmiusTheme.dark(AirmiusThemePalette.dark),
          home: RepaintBoundary(
            key: const Key('live-workout-preview'),
            child: buildTrainingLiveWorkoutPreview(),
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Ganzkörper Kraft'), findsOneWidget);
    expect(find.text('Kniebeuge'), findsOneWidget);
    expect(find.text('Satz 1'), findsOneWidget);
    expect(find.text('Satz abschließen'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await expectLater(
      find.byKey(const Key('live-workout-preview')),
      matchesGoldenFile('goldens/live_workout_phone.png'),
    );
  });
}
