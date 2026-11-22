import 'package:airmius/screens/club_service_hours_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('accepts contribution periods from current and older API responses', () {
    final periods = serviceHourContributionPeriods([
      {'id': 1, 'name': 'Beitragsjahr ohne Typ'},
      {'id': 2, 'type': 'contribution', 'name': 'Beitragsjahr 26/27'},
      {'id': 3, 'type': 'business', 'name': 'Geschäftsjahr 2026'},
    ]);

    expect(periods.map((period) => period['id']), [1, 2]);
  });

  test('handles an empty or malformed periods response', () {
    expect(serviceHourContributionPeriods(null), isEmpty);
    expect(serviceHourContributionPeriods({'data': []}), isEmpty);
  });
}
