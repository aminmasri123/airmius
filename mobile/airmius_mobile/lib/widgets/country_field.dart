import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/airmius_services_scope.dart';

class CountryField extends StatefulWidget {
  const CountryField({
    super.key,
    this.controller,
    this.value,
    this.onChanged,
    required this.label,
    this.required = false,
  });

  final TextEditingController? controller;
  final String? value;
  final ValueChanged<String>? onChanged;
  final String label;
  final bool required;

  @override
  State<CountryField> createState() => _CountryFieldState();
}

class _CountryFieldState extends State<CountryField> {
  static List<Map<String, dynamic>>? _defaults;
  List<Map<String, dynamic>> _rows = [];
  bool _canManage = false;
  bool _loaded = false;

  String get _code =>
      (widget.controller?.text ?? widget.value ?? '').trim().toUpperCase();
  String get _language => Localizations.localeOf(context).languageCode;
  String _text(String de, String en) => _language == 'en' ? en : de;
  String _name(Map<String, dynamic> row) {
    final names = row['names'] as Map;
    return '${names[_language] ?? names['en'] ?? names['de'] ?? row['code']}';
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      _load();
    }
  }

  Future<void> _load() async {
    _defaults ??= (jsonDecode(await rootBundle.loadString('assets/data/countries.json')) as List)
        .cast<Map<String, dynamic>>();
    final rows = _defaults!;
    if (!mounted) return;
    if (_rows.isEmpty) setState(() => _rows = rows);
    try {
      final services = AirmiusServicesScope.of(context);
      final data = await services
          .clientForSession(services.authState.session)
          .countryCatalog();
      if (!mounted) return;
      setState(() {
        _rows = (data['data'] as List).cast<Map<String, dynamic>>();
        _canManage = data['can_manage'] == true;
      });
    } catch (_) {
      /* Keep the complete bundled list if the network is unavailable. */
    }
  }

  Future<void> _choose() async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _CountryPicker(
        rows: _rows,
        code: _code,
        label: widget.label,
        name: _name,
        text: _text,
        canManage: _canManage,
      ),
    );
    if (selected == null || !mounted) return;
    widget.controller?.text = selected;
    widget.onChanged?.call(selected);
    await _load();
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    Widget field() {
      final selected = _rows.where((row) => row['code'] == _code).firstOrNull;
      return FormField<String>(
        key: ValueKey(_code),
        initialValue: _code,
        validator: (value) =>
            widget.required && (value == null || value.isEmpty)
            ? _text('Bitte ein Land auswählen', 'Please select a country')
            : null,
        builder: (state) => InkWell(
          onTap: _rows.isEmpty ? null : _choose,
          borderRadius: BorderRadius.circular(8),
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: widget.label,
              errorText: state.errorText,
              prefixIcon: const Icon(Icons.public_outlined),
              suffixIcon: const Icon(Icons.expand_more),
            ),
            child: Text(
              selected == null
                  ? (_code.isEmpty
                        ? _text('Land auswählen', 'Select country')
                        : _code)
                  : _name(selected),
            ),
          ),
        ),
      );
    }

    return widget.controller == null
        ? field()
        : ValueListenableBuilder(
            valueListenable: widget.controller!,
            builder: (_, _, _) => field(),
          );
  }
}

class _CountryPicker extends StatefulWidget {
  const _CountryPicker({
    required this.rows,
    required this.code,
    required this.label,
    required this.name,
    required this.text,
    required this.canManage,
  });
  final List<Map<String, dynamic>> rows;
  final String code;
  final String label;
  final String Function(Map<String, dynamic>) name;
  final String Function(String, String) text;
  final bool canManage;

  @override
  State<_CountryPicker> createState() => _CountryPickerState();
}

class _CountryPickerState extends State<_CountryPicker> {
  String _query = '';

  Future<void> _add() async {
    final services = AirmiusServicesScope.of(context);
    final code = TextEditingController();
    final de = TextEditingController();
    final en = TextEditingController();
    String? error;
    bool saving = false;
    final selected = await showDialog<String>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text(widget.text('Land ergänzen', 'Add country')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: code,
                  maxLength: 2,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z]')),
                  ],
                  decoration: InputDecoration(
                    labelText: widget.text(
                      'Kürzel (2 Buchstaben)',
                      'Code (2 letters)',
                    ),
                  ),
                ),
                TextField(
                  controller: de,
                  maxLength: 100,
                  decoration: const InputDecoration(
                    labelText: 'Name (Deutsch)',
                  ),
                ),
                TextField(
                  controller: en,
                  maxLength: 100,
                  decoration: const InputDecoration(
                    labelText: 'Name (English)',
                  ),
                ),
                if (error != null)
                  Text(
                    error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.pop(context),
              child: Text(widget.text('Abbrechen', 'Cancel')),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (code.text.length != 2 ||
                          de.text.trim().isEmpty ||
                          en.text.trim().isEmpty) {
                        update(
                          () => error = widget.text(
                            'Bitte alle Felder ausfüllen.',
                            'Please complete all fields.',
                          ),
                        );
                        return;
                      }
                      update(() {
                        saving = true;
                        error = null;
                      });
                      try {
                        await services
                            .clientForSession(services.authState.session)
                            .saveCountry({
                              'code': code.text.toUpperCase(),
                              'names': {
                                'de': de.text.trim(),
                                'en': en.text.trim(),
                              },
                            });
                        if (context.mounted) {
                          Navigator.pop(context, code.text.toUpperCase());
                        }
                      } catch (_) {
                        if (context.mounted) {
                          update(() {
                            saving = false;
                            error = widget.text(
                              'Speichern fehlgeschlagen. Kürzel bereits vorhanden oder Verbindung prüfen.',
                              'Saving failed. Check for an existing code or connection issues.',
                            );
                          });
                        }
                      }
                    },
              child: Text(widget.text('Speichern', 'Save')),
            ),
          ],
        ),
      ),
    );
    // Wait until the dialog animation has released its fields.
    await Future<void>.delayed(const Duration(milliseconds: 300));
    code.dispose();
    de.dispose();
    en.dispose();
    if (selected != null && mounted) Navigator.pop(context, selected);
  }

  @override
  Widget build(BuildContext context) {
    final rows =
        widget.rows
            .where(
              (row) =>
                  '${row['code']} ${(row['names'] as Map).values.join(' ')}'
                      .toLowerCase()
                      .contains(_query.toLowerCase().trim()),
            )
            .toList()
          ..sort((a, b) => widget.name(a).compareTo(widget.name(b)));
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .72,
        child: Column(
          children: [
            ListTile(
              title: Text(widget.label),
              trailing: IconButton(
                tooltip: widget.text('Schließen', 'Close'),
                icon: const Icon(Icons.close),
                onPressed: () => Navigator.pop(context),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.search),
                  labelText: widget.text(
                    'Land oder Kürzel suchen',
                    'Search country or code',
                  ),
                ),
                onChanged: (value) => setState(() => _query = value),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: rows.isEmpty
                  ? Center(
                      child: Text(
                        widget.text('Kein Land gefunden', 'No country found'),
                      ),
                    )
                  : ListView.builder(
                      itemCount: rows.length,
                      itemBuilder: (context, index) => ListTile(
                        title: Text(widget.name(rows[index])),
                        subtitle: Text('${rows[index]['code']}'),
                        trailing: rows[index]['code'] == widget.code
                            ? const Icon(Icons.check)
                            : null,
                        onTap: () =>
                            Navigator.pop(context, '${rows[index]['code']}'),
                      ),
                    ),
            ),
            if (widget.canManage)
              TextButton.icon(
                onPressed: _add,
                icon: const Icon(Icons.add),
                label: Text(widget.text('Land ergänzen', 'Add country')),
              ),
          ],
        ),
      ),
    );
  }
}
