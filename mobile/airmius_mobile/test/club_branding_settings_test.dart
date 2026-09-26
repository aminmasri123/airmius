import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/models/club_summary.dart';

void main() {
  test('club model keeps public palette and private manager configuration', () {
    final club = AirmiusClub.fromJson(const {
      'id': 9,
      'name': 'Airmius SC',
      'brand_primary_color': '#1D4ED8',
      'brand_secondary_color': '#0F172A',
      'brand_accent_color': '#F59E0B',
      'letterhead_settings': {
        'show_logo': true,
        'header': 'Airmius SC',
        'address_line': 'Musterweg 1',
        'footer': 'Vorstand',
      },
      'document_templates': [
        {
          'name': 'Standardbrief',
          'type': 'letter',
          'header': 'Mitteilung',
          'footer': 'Mit sportlichen Grüßen',
          'is_default': true,
        },
      ],
    });
    final summary = ClubSummary.fromAirmiusClub(club);

    expect(summary.brandPrimaryColor, '#1D4ED8');
    expect(summary.brandAccentColor, '#F59E0B');
    expect(club.letterheadSettings['show_logo'], isTrue);
    expect(club.documentTemplates.single['name'], 'Standardbrief');
  });

  test('branding editor and logo labels are localized in every language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubEditor.branding',
        'clubEditor.letterhead',
        'clubEditor.documentTemplates',
        'clubEditor.documentTemplateDefault',
        'clubs.logo.update',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });
}
