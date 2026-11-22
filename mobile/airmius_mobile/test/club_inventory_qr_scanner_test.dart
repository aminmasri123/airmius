import 'package:airmius/screens/club_inventory_qr_scanner_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('inventory scanner accepts the signed Airmius payload format', () {
    expect(
      isAirmiusInventoryQrPayload(
        'airmius-inventory:eyJ2IjoxLCJjIjoxLCJpIjoyLCJuIjoidGVzdCJ9',
      ),
      isTrue,
    );
  });

  test('inventory scanner distinguishes unrelated and malformed codes', () {
    expect(
      isAirmiusInventoryQrPayload(
        '550e8400-e29b-41d4-a716-446655440000',
      ),
      isFalse,
    );
    expect(isAirmiusInventoryQrPayload('4006381333931'), isFalse);
    expect(isAirmiusInventoryQrPayload('https://example.org/item/1'), isFalse);
    expect(isAirmiusInventoryQrPayload('airmius-inventory:short'), isFalse);
    expect(isAirmiusInventoryQrPayload(''), isFalse);
  });
}
