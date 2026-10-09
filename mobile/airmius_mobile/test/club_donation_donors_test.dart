import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';

void main() {
  test('donor options keep type-qualified IDs for every donor group', () {
    final management = AirmiusClubManagement.fromJson({
      'can_manage': true,
      'donor_options': [
        for (final type in ['member', 'external_member', 'partner', 'sponsor'])
          {'key': '$type:1', 'type': type, 'id': 1, 'name': '$type donor'},
      ],
    });
    expect(management.donorOptions, hasLength(4));
    expect(
      management.donorOptions.map((option) => option['key']).toSet(),
      hasLength(4),
    );
  });

  test('old servers without donor options remain compatible', () {
    expect(
      AirmiusClubManagement.fromJson({'can_manage': true}).donorOptions,
      isEmpty,
    );
  });

  test(
    'free donor contact details remain available in the payment payload',
    () {
      final management = AirmiusClubManagement.fromJson({
        'can_manage': true,
        'payments': [
          {
            'id': 1,
            'purpose': 'donation',
            'user_id': null,
            'donor_snapshot': {
              'name': 'Guest Donor',
              'email': 'guest@example.org',
            },
          },
        ],
      });
      expect(
        management.payments.single['donor_snapshot']['name'],
        'Guest Donor',
      );
    },
  );
}
