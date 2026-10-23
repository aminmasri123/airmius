import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_date_input.dart';

void main() {
  for (final value in [
    '2016-01-01',
    '2016-01-01T00:00:00.000000Z',
    '2016-01-01T23:30:00-12:00',
    '01.01.2016',
    DateTime(2016, 1, 1),
  ]) {
    test('child birth date $value stays 01.01.2016 without epoch fallback', () {
      final user = AirmiusUser.fromJson(_profile(value));
      expect(user.birthDate, DateTime(2016, 1, 1));
      expect(formatAirmiusDate(user.birthDate), '01.01.2016');
      expect(formatAirmiusApiDate(user.birthDate), '2016-01-01');
    });
  }

  for (final value in [null, '', 'invalid', '2016-02-31', 0]) {
    test('missing or invalid birth date $value is empty rather than 1970', () {
      final user = AirmiusUser.fromJson(_profile(value));
      expect(user.birthDate, isNull);
      expect(formatAirmiusDate(user.birthDate), isEmpty);
      expect(user.isProfileIncomplete, isTrue);
    });
  }

  test('a real birth date in 1970 remains valid', () {
    expect(
      AirmiusUser.fromJson(_profile('1970-01-01')).birthDate,
      DateTime(1970, 1, 1),
    );
  });

  test('valid leap day is preserved', () {
    expect(
      AirmiusUser.fromJson(_profile('2016-02-29')).birthDate,
      DateTime(2016, 2, 29),
    );
  });
}

JsonMap _profile(Object? birthDate) => {
  'id': 7,
  'name': 'Kind Airmius',
  'email': 'child@example.test',
  'first_name': 'Kind',
  'last_name': 'Airmius',
  'role': 'minor_pending_consent',
  'birth_date': birthDate,
  'gender': 'male',
  'country': 'DE',
  'guardian_email': 'parent@example.test',
};
