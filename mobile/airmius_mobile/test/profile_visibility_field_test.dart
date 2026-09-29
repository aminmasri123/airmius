import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/profile_visibility_field.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('profile visibility offers and retains all three choices', (tester) async {
    String value = 'public';
    await tester.pumpWidget(MaterialApp(home: AirmiusScope(
      language: AirmiusLanguage.de, setLanguage: (_) {},
      child: Scaffold(body: StatefulBuilder(builder: (context, update) =>
        ProfileVisibilityField(value: value, onChanged: (next) => update(() => value = next)))),
    )));
    for (final option in {'Nur Freunde': 'friends', 'Privat': 'private', 'Öffentlich': 'public'}.entries) {
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text(option.key).last);
      await tester.pumpAndSettle();
      expect(value, option.value);
      expect(tester.takeException(), isNull);
    }
  });
}
