import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/screens/challenge_people_picker.dart';

void main() {
  testWidgets('100 people are lazy rendered and selection survives searching', (tester) async {
    final initial = {1};
    await tester.pumpWidget(MaterialApp(home: ChallengePeoplePicker(
      people: List.generate(100, (index) => {'id': index + 1, 'name': 'Person ${index + 1}'}),
      selected: initial, copy: (de, en, fr, ar) => de,
    )));
    expect(find.byType(CheckboxListTile).evaluate().length, lessThan(100));
    await tester.enterText(find.byType(TextField), 'Person 99');
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey(99)));
    await tester.pump();
    expect(find.text('Auswahl übernehmen (2)'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'Person 1');
    await tester.pump();
    final tile = tester.widget<CheckboxListTile>(find.byKey(const ValueKey(1)));
    expect(tile.value, isTrue);
    expect(initial, {1}, reason: 'The original form must not change before confirmation.');
  });
}
