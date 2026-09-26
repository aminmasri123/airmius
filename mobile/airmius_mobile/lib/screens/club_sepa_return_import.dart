import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:http/http.dart' as http;
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/sepa_return_import_client.dart';
import '../core/sepa_return_labels.dart';

class ClubSepaReturnImport extends StatefulWidget {
  const ClubSepaReturnImport({
    super.key,
    required this.client,
    required this.clubId,
    required this.batchId,
    this.uploadTransport,
  });
  final AirmiusApiClient client;
  final int clubId, batchId;
  final http.Client? uploadTransport;
  @override
  State<ClubSepaReturnImport> createState() => _ClubSepaReturnImportState();
}

class _ClubSepaReturnImportState extends State<ClubSepaReturnImport> {
  Uint8List? _bytes;
  String? _name, _error;
  AirmiusJson? _report;
  bool _busy = false, _confirmed = false, _unlinked = false;
  List<String>? _columns;
  String? _format;
  final Map<String, int> _mapping = {};
  bool _ignoreConfirmed = false;
  static const _fields = [
    'end_to_end_id',
    'booking_date',
    'amount',
    'currency',
    'reference',
    'reason',
    'iban',
  ];
  List<int> get _ignored => [
    for (var i = 0; i < (_columns?.length ?? 0); i++)
      if (!_mapping.containsValue(i)) i,
  ];
  bool get _mappingReady =>
      _columns == null ||
      (_fields.take(6).every(_mapping.containsKey) &&
          (_ignored.isEmpty || _ignoreConfirmed));
  late final _upload = SepaReturnImportClient(
    widget.client,
    transport: widget.uploadTransport,
  );
  String get language => AirmiusScope.of(context).language.locale.languageCode;
  String c(String key) =>
      (sepaReturnLabels[language] ?? sepaReturnLabels['en']!)[key] ?? key;
  void _clearPreview() {
    _report = null;
    _confirmed = false;
    _unlinked = false;
  }

  Future<void> _pick() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['csv', 'xml'],
        withData: true,
      );
      if (!mounted || result == null) return;
      final file = result.files.single;
      setState(() {
        _clearPreview();
        _columns = null;
        _format = null;
        _mapping.clear();
        _ignoreConfirmed = false;
        _bytes = null;
        _name = null;
        if (file.bytes == null ||
            file.size == 0 ||
            file.size > 2 * 1024 * 1024) {
          _error = c('help');
        } else {
          _bytes = Uint8List.fromList(file.bytes!);
          _name = file.name;
        }
      });
    } catch (_) {
      if (mounted) setState(() => _error = c('error'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _readColumns() async {
    if (_busy || _bytes == null) return;
    setState(() {
      _busy = true;
      _error = null;
      _clearPreview();
    });
    try {
      final result = await _upload.send(
        clubId: widget.clubId,
        batchId: widget.batchId,
        bytes: _bytes!,
        filename: _name ?? 'returns.csv',
        columnsOnly: true,
      );
      if (!mounted) return;
      setState(() {
        _format = result['data']['format']?.toString() ?? 'csv';
        _columns = _format == 'csv'
            ? (result['data']['columns'] as List).cast<String>()
            : null;
        _mapping.clear();
        _ignoreConfirmed = false;
        for (final field in _fields) {
          final index = _columns!.indexOf(field);
          if (index >= 0) _mapping[field] = index;
        }
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

  Future<void> _send(bool importing) async {
    if (_busy || _bytes == null) return;
    if (!importing && !_mappingReady) return;
    if (importing &&
        (_report?['can_import'] != true ||
            !_confirmed ||
            ((_report?['unlinked_count'] as num? ?? 0) > 0 && !_unlinked))) {
      return;
    }
    final token = importing ? _report!['preview_token'] as String : null;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await _upload.send(
        clubId: widget.clubId,
        batchId: widget.batchId,
        bytes: _bytes!,
        filename: _name ?? 'returns.csv',
        previewToken: token,
        confirmUnlinked: _unlinked,
        mapping: !importing && _columns != null ? _mapping : null,
        ignoredColumns: !importing ? _ignored : const [],
      );
      if (!mounted) return;
      if (importing) {
        Navigator.of(context).pop(true);
        return;
      }
      setState(() {
        _clearPreview();
        _report = response['data'] as AirmiusJson;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _clearPreview();
          _error = e is AirmiusApiException ? e.userMessage : c('error');
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _upload.close();
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
          const SelectableText(
            'end_to_end_id;booking_date;amount;currency;reference;reason',
          ),
          OutlinedButton(
            onPressed: _busy ? null : _pick,
            child: Text(c('file')),
          ),
          if (_name != null) Text(_name!),
          if (_format != null) Text('${c('detectedFormat')}: $_format'),
          if (_busy) const LinearProgressIndicator(),
          if (_error != null)
            Semantics(
              liveRegion: true,
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          OutlinedButton(
            onPressed: _busy || _bytes == null ? null : _readColumns,
            child: Text(c('mapColumns')),
          ),
          if (_columns != null) ...[
            for (final field in _fields)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: DropdownButtonFormField<int>(
                  key: ValueKey('mapping-$field-${_mapping[field]}'),
                  initialValue: _mapping[field] ?? -1,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: c('field_$field')),
                  items: [
                    DropdownMenuItem(value: -1, child: Text(c('notMapped'))),
                    for (var i = 0; i < _columns!.length; i++)
                      DropdownMenuItem(
                        value: i,
                        child: Text(
                          '${i + 1} · ${_columns![i]}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                  onChanged: _busy
                      ? null
                      : (index) => setState(() {
                          if (index == null || index < 0) {
                            _mapping.remove(field);
                          } else {
                            _mapping[field] = index;
                          }
                          _ignoreConfirmed = false;
                          _clearPreview();
                        }),
                ),
              ),
            if (_ignored.isNotEmpty)
              CheckboxListTile(
                value: _ignoreConfirmed,
                title: Text(
                  '${c('ignoreConfirm')}: ${_ignored.map((i) => _columns![i]).join(', ')}',
                ),
                onChanged: _busy
                    ? null
                    : (value) => setState(() {
                        _ignoreConfirmed = value == true;
                        _clearPreview();
                      }),
              ),
          ],
          FilledButton(
            onPressed: _busy || _bytes == null || !_mappingReady
                ? null
                : () => _send(false),
            child: Text(c('preview')),
          ),
          if (_report != null) ...[
            if ((_report!['ignored_columns'] as List? ?? []).isNotEmpty)
              Text(
                '${c('ignoredColumns')}: ${(_report!['ignored_columns'] as List).join(', ')}',
              ),
            for (final row in (_report!['rows'] as List).cast<AirmiusJson>())
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${row['row']} · ${row['end_to_end_id']} · ${row['booking_date'] ?? '—'}',
                      ),
                      Text(
                        '${row['amount_cents'] == null ? '—' : NumberFormat.decimalPattern(language).format((row['amount_cents'] as num) / 100)} ${row['currency']}',
                      ),
                      Text('${row['reference']} · ${row['reason']}'),
                      Text(
                        '${c(row['status'] as String)} · ${c(row['has_linked_receipt'] == true ? 'linked' : 'unlinked')}',
                      ),
                      for (final error in row['errors'] as List)
                        Text(
                          c(error as String),
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            if ((_report!['unlinked_count'] as num) > 0)
              CheckboxListTile(
                value: _unlinked,
                onChanged: _busy
                    ? null
                    : (value) => setState(() => _unlinked = value == true),
                title: Text(c('unlinkedConfirm')),
              ),
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
                      _report!['can_import'] != true ||
                      !_confirmed ||
                      ((_report!['unlinked_count'] as num) > 0 && !_unlinked)
                  ? null
                  : () => _send(true),
              child: Text(c('apply')),
            ),
          ],
        ],
      ),
    ),
  );
}
