import 'package:flutter/services.dart';

/// Formats typed dates as `dd.MM.yyyy` while the user is entering them.
class AirmiusDateInputFormatter extends TextInputFormatter {
  const AirmiusDateInputFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final limited = digits.length > 8 ? digits.substring(0, 8) : digits;
    final output = StringBuffer();

    for (var index = 0; index < limited.length; index++) {
      if (index == 2 || index == 4) output.write('.');
      output.write(limited[index]);
    }

    final text = output.toString();
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
      composing: TextRange.empty,
    );
  }
}

DateTime? parseAirmiusDate(String? value) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;

  final displayMatch = RegExp(r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$')
      .firstMatch(text);
  if (displayMatch != null) {
    final day = int.parse(displayMatch.group(1)!);
    final month = int.parse(displayMatch.group(2)!);
    final year = int.parse(displayMatch.group(3)!);
    final date = DateTime(year, month, day);
    if (date.year == year && date.month == month && date.day == day) {
      return date;
    }
    return null;
  }

  final iso = DateTime.tryParse(text);
  if (iso == null) return null;
  return DateTime(iso.year, iso.month, iso.day);
}

String formatAirmiusDate(DateTime? date) {
  if (date == null) return '';
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');
  return '$day.$month.${date.year.toString().padLeft(4, '0')}';
}

String? formatAirmiusApiDate(DateTime? date) {
  if (date == null) return null;
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year.toString().padLeft(4, '0')}-$month-$day';
}
