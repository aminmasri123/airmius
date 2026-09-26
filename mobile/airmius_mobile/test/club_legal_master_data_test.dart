import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';

void main() {
  test('club model parses private legal master data returned for managers', () {
    final club = AirmiusClub.fromJson(const {
      'id': 7,
      'name': 'Airmius SC',
      'registry_authority': 'Amtsgericht Köln',
      'registry_number': 'VR 123',
      'federation_affiliations': [
        {'name': 'FVM', 'member_number': '77'},
      ],
      'tax_authority': 'Finanzamt Köln',
      'tax_number': '123/456',
      'vat_id': 'DE123456789',
      'tax_status': 'nonprofit',
      'tax_exemption_valid_until': '2027-12-31',
    });

    expect(club.registryAuthority, 'Amtsgericht Köln');
    expect(club.registryNumber, 'VR 123');
    expect(club.federationAffiliations.single['member_number'], '77');
    expect(club.taxNumber, '123/456');
    expect(club.taxStatus, 'nonprofit');
    expect(club.taxExemptionValidUntil, '2027-12-31');
  });

  test('legal master data labels are localized for all supported languages', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubEditor.legalData',
        'clubEditor.registryAuthority',
        'clubEditor.taxStatus.nonprofit',
        'clubEditor.affiliations',
        'clubEditor.removeAffiliation',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });
}
