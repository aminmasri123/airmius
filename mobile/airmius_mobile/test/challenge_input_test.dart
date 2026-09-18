import 'package:airmius/core/challenge_input.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('challenge values accept decimal commas and dots', () {
    expect(parseChallengeValue(' 20 '), 20);
    expect(parseChallengeValue('2,5'), 2.5);
    expect(parseChallengeValue('2.5'), 2.5);
    expect(parseChallengeValue('0'), 0);
    expect(parseChallengeValue('999999999'), 999999999);
  });

  test('invalid challenge values cannot become a confirmation', () {
    for (final input in [
      '',
      ' ',
      'abc',
      '-1',
      'NaN',
      'Infinity',
      '1000000000',
      '1,2,3',
    ]) {
      expect(parseChallengeValue(input), isNull, reason: input);
    }
  });
}
