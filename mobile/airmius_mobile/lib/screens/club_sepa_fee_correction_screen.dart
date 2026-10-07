import 'dart:math';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/sepa_fee_correction_labels.dart';

List<AirmiusJson> feeCorrections(AirmiusJson result) =>
    (result['fee_corrections'] as List? ?? []).cast<AirmiusJson>().toList()
      ..sort((a, b) => (a['revision'] as int).compareTo(b['revision'] as int));

int currentFeeCents(AirmiusJson result) {
  final history = feeCorrections(result);
  return history.isEmpty
      ? (num.parse('${(result['fee_entry'] as AirmiusJson)['amount']}') * 100)
            .round()
      : (history.last['amount_cents'] as num).toInt();
}

class SepaFeeCorrectionSummary extends StatelessWidget {
  const SepaFeeCorrectionSummary({super.key, required this.result});
  final AirmiusJson result;
  @override
  Widget build(BuildContext context) {
    final language = AirmiusScope.of(context).language.locale.languageCode;
    final labels =
        sepaFeeCorrectionLabels[language] ?? sepaFeeCorrectionLabels['en']!;
    String money(num cents) => NumberFormat.simpleCurrency(
      locale: language,
      name: 'EUR',
    ).format(cents / 100);
    final history = feeCorrections(result);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('${labels['current']}: ${money(currentFeeCents(result))}'),
        if (history.isNotEmpty)
          ExpansionTile(
            title: Text(labels['history']!),
            children: [
              for (final entry in history)
                ListTile(
                  title: Text(
                    '#${entry['revision']} · ${entry['booked_on'].toString().split('T').first} · ${money(entry['previous_amount_cents'] as num)} → ${money(entry['amount_cents'] as num)}',
                  ),
                  subtitle: Text('${entry['reference']}\n${entry['reason']}'),
                ),
            ],
          ),
      ],
    );
  }
}

class ClubSepaFeeCorrectionScreen extends StatefulWidget {
  const ClubSepaFeeCorrectionScreen({
    super.key,
    required this.client,
    required this.clubId,
    required this.batchId,
    required this.itemId,
    required this.result,
  });
  final AirmiusApiClient client;
  final int clubId, batchId, itemId;
  final AirmiusJson result;
  @override
  State<ClubSepaFeeCorrectionScreen> createState() =>
      _ClubSepaFeeCorrectionScreenState();
}

class _ClubSepaFeeCorrectionScreenState
    extends State<ClubSepaFeeCorrectionScreen> {
  final _amount = TextEditingController(),
      _date = TextEditingController(),
      _reference = TextEditingController(),
      _reason = TextEditingController();
  bool _busy = false, _confirmed = false;
  String? _error;
  AirmiusJson? _request;
  String c(String key) =>
      (sepaFeeCorrectionLabels[AirmiusScope.of(
        context,
      ).language.locale.languageCode] ??
      sepaFeeCorrectionLabels['en']!)[key]!;
  @override
  void initState() {
    super.initState();
    _amount.text = (currentFeeCents(widget.result) / 100).toStringAsFixed(2);
  }

  void changed(String _) {
    if (_confirmed) setState(() => _confirmed = false);
  }

  String requestId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  Future<void> save() async {
    if (_busy || !_confirmed) return;
    if (_request == null) {
      try {
        final amount = _amount.text.trim().replaceAll(',', '.');
        final date = formatAirmiusApiDate(parseAirmiusDate(_date.text));
        final history = feeCorrections(widget.result);
        final earliest =
            (history.isEmpty
                    ? (widget.result['fee_entry'] as AirmiusJson)['booked_on']
                    : history.last['booked_on'])
                .toString()
                .split('T')
                .first;
        if (!RegExp(r'^\d{1,6}(?:\.\d{1,2})?$').hasMatch(amount) ||
            (double.parse(amount) * 100).round() ==
                currentFeeCents(widget.result) ||
            DateFormat('yyyy-MM-dd').format(
                  DateFormat('yyyy-MM-dd').parseStrict(date ?? ''),
                ) !=
                date ||
            date!.compareTo(earliest) < 0 ||
            _reference.text.trim().isEmpty ||
            _reason.text.trim().isEmpty ||
            _reference.text.length > 180 ||
            _reason.text.length > 2000) {
          throw const FormatException();
        }
        _request = Map<String, dynamic>.unmodifiable({
          'confirmed': true,
          'request_id': requestId(),
          'expected_revision': history.isEmpty ? 0 : history.last['revision'],
          'amount_cents': (double.parse(amount) * 100).round(),
          'booked_on': date,
          'reference': _reference.text.trim(),
          'reason': _reason.text.trim(),
        });
      } catch (_) {
        setState(() => _error = c('error'));
        return;
      }
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.client.recordClubSepaResult(
        widget.clubId,
        widget.batchId,
        widget.itemId,
        'fee-corrections',
        _request!,
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e is AirmiusApiException ? e.userMessage : c('error');
          _confirmed = false;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    for (final controller in [_amount, _date, _reference, _reason]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_busy,
    child: Scaffold(
      appBar: AppBar(title: Text(c('title'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SepaFeeCorrectionSummary(result: widget.result),
          Text(c('help')),
          if (_busy) const LinearProgressIndicator(),
          for (final field in [
            (_amount, 'amount'),
            (_date, 'date'),
            (_reference, 'reference'),
            (_reason, 'reason'),
          ])
            TextField(
              controller: field.$1,
              enabled: !_busy && _request == null,
              onChanged: changed,
              keyboardType: field.$2 == 'amount'
                  ? const TextInputType.numberWithOptions(decimal: true)
                  : field.$2 == 'date'
                  ? TextInputType.datetime
                  : TextInputType.text,
              maxLength: field.$2 == 'reference'
                  ? 180
                  : field.$2 == 'reason'
                  ? 2000
                  : null,
              maxLines: field.$2 == 'reason' ? 3 : 1,
              decoration: InputDecoration(
                labelText: c(field.$2),
                hintText: field.$2 == 'date' ? 'DD.MM.YYYY' : null,
              ),
              inputFormatters: field.$2 == 'date'
                  ? const [AirmiusDateInputFormatter()]
                  : null,
            ),
          if (_request != null && !_busy) Text(c('uncertain')),
          if (_error != null)
            Semantics(
              liveRegion: true,
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          CheckboxListTile(
            value: _confirmed,
            onChanged: _busy
                ? null
                : (value) => setState(() => _confirmed = value == true),
            title: Text(c('confirm')),
          ),
          FilledButton(
            onPressed: _busy || !_confirmed ? null : save,
            child: Text(c(_request == null ? 'save' : 'retry')),
          ),
          if (_request != null)
            OutlinedButton(
              onPressed: _busy ? null : () => Navigator.of(context).pop(true),
              child: Text(c('reload')),
            ),
        ],
      ),
    ),
  );
}
