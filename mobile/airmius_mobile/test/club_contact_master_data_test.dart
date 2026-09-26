import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/models/club_summary.dart';

void main() {
  test('club model and summary retain server-filtered contact data', () {
    final club = AirmiusClub.fromJson(const {
      'id': 8,
      'name': 'Airmius SC',
      'contact_email': 'verein@example.test',
      'contact_phone': '+49 221 123',
      'website_url': 'https://example.test',
      'contact_details_public': true,
      'contact_persons': [
        {
          'name': 'Alex Beispiel',
          'role': 'Vorstand',
          'email': 'alex@example.test',
          'phone': null,
          'is_public': true,
        },
      ],
    });
    final summary = ClubSummary.fromAirmiusClub(club);

    expect(club.contactDetailsPublic, isTrue);
    expect(summary.contactEmail, 'verein@example.test');
    expect(summary.contactPhone, '+49 221 123');
    expect(summary.websiteUrl, 'https://example.test');
    expect(summary.contactPersons.single['name'], 'Alex Beispiel');
  });

  test('contact editor labels are localized for all supported languages', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubEditor.contactData',
        'clubEditor.contactEmail',
        'clubEditor.contactPublic',
        'clubEditor.contactPersons',
        'clubEditor.contactPersonPublic',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });
}
