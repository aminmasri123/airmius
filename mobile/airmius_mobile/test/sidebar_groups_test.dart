import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/models/module_definition.dart';
import 'package:airmius/models/sidebar_groups.dart';

void main() {
  test('every module belongs to exactly one sidebar group', () {
    final titles = sidebarGroups.values.expand((items) => items).toList();
    expect(titles.toSet().length, titles.length);
    expect(titles.toSet(), appModules.map((module) => module.title).toSet());
  });

  test('training and account destinations have explicit stable groups', () {
    expect(sidebarGroups['sport'], contains('Trainingsplanung'));
    expect(sidebarGroups['account'], containsAll(['Abos & Rechnungen', 'Einstellungen']));
    expect(sidebarGroups['management'], containsAll(['Admin', 'Vereins-Cockpit', 'Trainer-Cockpit']));
    for (final language in ['de', 'en', 'fr', 'ar']) {
      for (final group in sidebarGroups.keys) {
        expect(sidebarGroupLabel(group, language), isNotEmpty);
      }
    }
  });
}
