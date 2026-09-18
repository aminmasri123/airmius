import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_l10n.dart';

void main() {
  for (final language in AirmiusLanguage.values) {
    test('progress module matches destination title in ${language.name}', () {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      expect(scope.copy('Altersfreigaben'), scope.t('maturity.title'));
      expect(scope.copy('Altersfreigaben'), isNot('Altersfreigaben'));
    });
  }
}
