import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/sepa_fee_labels.dart';

class ClubSepaFeeScreen extends StatefulWidget {
  const ClubSepaFeeScreen({
    super.key,
    required this.client,
    required this.clubId,
    required this.batchId,
    required this.itemId,
  });
  final AirmiusApiClient client;
  final int clubId, batchId, itemId;
  @override
  State<ClubSepaFeeScreen> createState() => _ClubSepaFeeScreenState();
}

class _ClubSepaFeeScreenState extends State<ClubSepaFeeScreen> {
  final _query = TextEditingController(),
      _amount = TextEditingController(),
      _date = TextEditingController(),
      _reference = TextEditingController();
  String? _mode, _error;
  bool _busy = false, _confirmed = false, _searched = false, _more = false;
  int _page = 1;
  List<AirmiusJson> _options = [];
  AirmiusJson? _selected;
  String get language => AirmiusScope.of(context).language.locale.languageCode;
  String c(String key) =>
      (sepaFeeLabels[language] ?? sepaFeeLabels['en']!)[key] ?? key;
  String money(dynamic value) => NumberFormat.simpleCurrency(
    locale: language,
    name: 'EUR',
  ).format(num.parse('$value'));
  String summary(AirmiusJson entry) =>
      '#${entry['id']} · ${money(entry['amount'])} · ${entry['booked_on'].toString().split('T').first} · ${entry['reference']}';
  void changed(String _) {
    if (_confirmed) setState(() => _confirmed = false);
  }

  Future<void> search(int page) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.client.clubSepaFeeOptions(
        widget.clubId,
        widget.batchId,
        widget.itemId,
        query: _query.text,
        page: page,
      );
      if (!mounted) return;
      final data = response['data'] as AirmiusJson;
      setState(() {
        _options = (data['data'] as List).cast<AirmiusJson>();
        _page = data['current_page'] as int;
        _more = data['next_page_url'] != null;
        _searched = true;
      });
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = e is AirmiusApiException ? e.userMessage : c('error'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> save() async {
    if (_busy ||
        !_confirmed ||
        _mode == null ||
        (_mode == 'existing' && _selected == null)) {
      return;
    }
    final amount = _mode == 'existing'
        ? '${_selected!['amount']}'
        : _amount.text.replaceAll(',', '.').trim();
    final date = _mode == 'existing'
        ? _selected!['booked_on'].toString().split('T').first
        : _date.text.trim();
    final reference = _mode == 'existing'
        ? '${_selected!['reference']}'
        : _reference.text.trim();
    try {
      if (!RegExp(r'^\d{1,6}(?:\.\d{1,2})?$').hasMatch(amount) ||
          double.parse(amount) <= 0 ||
          reference.isEmpty) {
        throw const FormatException();
      }
      final parsed = DateFormat('yyyy-MM-dd').parseStrict(date);
      if (DateFormat('yyyy-MM-dd').format(parsed) != date) {
        throw const FormatException();
      }
    } catch (_) {
      setState(() => _error = c('error'));
      return;
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
        'fee',
        {
          'confirmed': true,
          'amount_cents': (double.parse(amount) * 100).round(),
          'booked_on': date,
          'reference': reference,
          if (_mode == 'existing') 'finance_entry_id': _selected!['id'],
        },
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) {
        setState(() {
          _confirmed = false;
          _error = e is AirmiusApiException ? e.userMessage : c('error');
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    for (final controller in [_query, _amount, _date, _reference]) {
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
          Text(c('help')),
          DropdownButtonFormField<String>(
            decoration: InputDecoration(labelText: c('choice')),
            isExpanded: true,
            items: [
              for (final mode in ['existing', 'new'])
                DropdownMenuItem(
                  value: mode,
                  child: Text(c(mode), overflow: TextOverflow.ellipsis),
                ),
            ],
            onChanged: _busy
                ? null
                : (mode) => setState(() {
                    _mode = mode;
                    _selected = null;
                    _confirmed = false;
                    _error = null;
                    _amount.clear();
                    _date.clear();
                    _reference.clear();
                  }),
          ),
          if (_busy) const LinearProgressIndicator(),
          if (_error != null)
            Semantics(
              liveRegion: true,
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          if (_mode == 'existing') ...[
            TextField(
              controller: _query,
              enabled: !_busy,
              maxLength: 180,
              decoration: InputDecoration(labelText: c('reference')),
            ),
            OutlinedButton(
              onPressed: _busy ? null : () => search(1),
              child: Text(c('search')),
            ),
            if (_searched && _options.isEmpty) Text(c('empty')),
            for (final entry in _options)
              OutlinedButton(
                onPressed: _busy
                    ? null
                    : () => setState(() {
                        _selected = entry;
                        _confirmed = false;
                      }),
                child: Text(summary(entry)),
              ),
            if (_searched)
              Wrap(
                spacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  TextButton(
                    onPressed: _busy || _page <= 1
                        ? null
                        : () => search(_page - 1),
                    child: Text(c('previous')),
                  ),
                  Text('$_page'),
                  TextButton(
                    onPressed: _busy || !_more ? null : () => search(_page + 1),
                    child: Text(c('next')),
                  ),
                ],
              ),
            if (_selected != null)
              Text('${c('selected')}: ${summary(_selected!)}'),
          ],
          if (_mode == 'new') ...[
            TextField(
              controller: _amount,
              enabled: !_busy,
              onChanged: changed,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(labelText: c('amount')),
            ),
            TextField(
              controller: _date,
              enabled: !_busy,
              onChanged: changed,
              keyboardType: TextInputType.datetime,
              decoration: InputDecoration(
                labelText: c('date'),
                hintText: 'YYYY-MM-DD',
              ),
            ),
            TextField(
              controller: _reference,
              enabled: !_busy,
              onChanged: changed,
              maxLength: 180,
              decoration: InputDecoration(labelText: c('reference')),
            ),
          ],
          if (_mode != null)
            CheckboxListTile(
              value: _confirmed,
              onChanged: _busy
                  ? null
                  : (value) => setState(() => _confirmed = value == true),
              title: Text(c('confirm')),
            ),
          FilledButton(
            onPressed:
                _busy ||
                    !_confirmed ||
                    _mode == null ||
                    (_mode == 'existing' && _selected == null)
                ? null
                : save,
            child: Text(c(_mode == 'existing' ? 'link' : 'save')),
          ),
        ],
      ),
    ),
  );
}
