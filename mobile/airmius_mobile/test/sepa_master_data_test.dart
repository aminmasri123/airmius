import 'package:airmius/core/sepa_master_data.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('IBAN checks normalize input and enforce length and checksum', () {
    for (final iban in [
      'de89 3704 0044 0532 0130 00',
      'FR1420041010050500013M02606',
      'GB82WEST12345698765432',
    ]) {
      expect(SepaMasterData.validIban(iban), isTrue, reason: iban);
    }
    for (final iban in [
      '',
      'DE',
      'DE89370400440532013001',
      'DE893704004405320130000',
      'ZZ89370400440532013000',
    ]) {
      expect(SepaMasterData.validIban(iban), isFalse, reason: iban);
    }
  });
  test('BIC is optional but supplied values must have 8 or 11 characters', () {
    expect(SepaMasterData.bankError(iban: '', bic: ''), isNull);
    expect(SepaMasterData.validBic('cobadeff'), isTrue);
    expect(SepaMasterData.validBic('COBADEFFXXX'), isTrue);
    expect(SepaMasterData.bankError(iban: '', bic: 'INVALID'), 'bic');
  });
  test('creditor checksum ignores the business code', () {
    expect(SepaMasterData.validCreditorId('de98 zzz09999999999'), isTrue);
    expect(SepaMasterData.validCreditorId('DE98ABC09999999999'), isTrue);
    expect(SepaMasterData.validCreditorId('DE97ZZZ09999999999'), isFalse);
    expect(SepaMasterData.validCreditorId('DE98ZZZ9999999999'), isFalse);
  });
  test('active mandate needs complete data and a real nonfuture date', () {
    String? validate({
      bool active = true,
      String iban = 'DE89370400440532013000',
      String reference = 'MANDATE-1',
      String date = '2026-11-28',
    }) => SepaMasterData.mandateError(
      iban: iban,
      bic: '',
      reference: reference,
      signedOn: date,
      active: active,
      today: DateTime(2026, 11, 28),
    );
    expect(validate(), isNull);
    expect(validate(iban: ''), 'required');
    expect(validate(reference: ''), 'required');
    expect(validate(date: ''), 'required');
    expect(validate(active: false, iban: '', reference: '', date: ''), isNull);
    expect(validate(date: '2026-11-29'), 'date');
    expect(validate(date: '2026-02-30'), 'date');
    expect(validate(reference: 'A' * 36), 'reference');
    expect(validate(reference: '<Mandat>'), 'reference');
  });
}
