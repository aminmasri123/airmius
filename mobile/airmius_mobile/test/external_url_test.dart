import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_external_url.dart';
import 'package:airmius/widgets/airmius_widgets.dart';

void main() {
  test('accepts HTTPS URLs without embedded credentials', () {
    expect(safeExternalHttpUrl('https://pay.example.test/checkout'), isNotNull);
    expect(
      safeExternalHttpUrl('https://user:secret@pay.example.test/checkout'),
      isNull,
    );
  });

  test('rejects custom schemes and malformed hosts', () {
    expect(safeExternalHttpUrl('javascript:alert(1)'), isNull);
    expect(safeExternalHttpUrl('file:///tmp/report.pdf'), isNull);
    expect(safeExternalHttpUrl('/relative/path'), isNull);
  });

  test('allows HTTP only when explicitly requested for public websites', () {
    expect(
      safeExternalHttpUrl('http://club.example.test', httpsOnly: false),
      isNotNull,
    );
    expect(safeExternalHttpUrl('http://club.example.test'), isNull);
  });

  test('normalizes image URLs without accepting custom absolute schemes', () {
    expect(
      resolveAirmiusImageUrl('https://cdn.example.test/avatar.webp'),
      'https://cdn.example.test/avatar.webp',
    );
    expect(resolveAirmiusImageUrl('javascript:alert(1)'), isNull);
  });
}
