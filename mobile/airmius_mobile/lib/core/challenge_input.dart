/// Accepts decimal dots and commas, but never silently confirms an empty value.
num? parseChallengeValue(String input) {
  final value = num.tryParse(input.trim().replaceAll(',', '.'));
  if (value == null || !value.isFinite || value < 0 || value > 999999999) {
    return null;
  }
  return value;
}
