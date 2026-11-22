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

  test('formats service dates with automatic dots', () {
    expect(formatServiceHourDateDigits('1'), '1');
    expect(formatServiceHourDateDigits('12'), '12.');
    expect(formatServiceHourDateDigits('120'), '12.0');
    expect(formatServiceHourDateDigits('1205'), '12.05.');
    expect(formatServiceHourDateDigits('12052026'), '12.05.2026');
    expect(formatServiceHourDateDigits('12.05.2026'), '12.05.2026');
  });

  test('converts valid display dates and rejects invalid calendar dates', () {
    expect(serviceHourApiDate('12.05.2026'), '2026-05-12');
    expect(serviceHourApiDate('31.02.2026'), isNull);
    expect(serviceHourApiDate('1.5.2026'), isNull);
  });
}
