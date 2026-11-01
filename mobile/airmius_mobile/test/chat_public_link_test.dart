import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_deep_links.dart';

void main() {
  test('numeric and UUID conversation links resolve to chat', () {
    final resolver = AirmiusDeepLinkResolver();
    const uuid = '12345678-1234-4234-8234-123456789abc';
    for (final prefix in ['https://airmius.com/conversations/', 'airmius://conversations/']) {
      final legacy = resolver.resolve('${prefix}221');
      expect(legacy.type, AirmiusDeepLinkTargetType.chat);
      expect(legacy.id, 221);
      final current = resolver.resolve('$prefix$uuid');
      expect(current.type, AirmiusDeepLinkTargetType.chat);
      expect(current.query['public_id'], uuid);
    }
  });
}
