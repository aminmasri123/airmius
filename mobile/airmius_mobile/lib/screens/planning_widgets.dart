import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/planning_labels.dart';

List<AirmiusJson> planningRows(dynamic value) => value is List
    ? value.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
    : [];
AirmiusJson planningData(AirmiusJson value) {
  if (value['queued'] == true || value['data'] == null) {
    throw const FormatException('Missing persisted planning response');
  }
  return Map<String, dynamic>.from(value['data'] as Map);
}

int? planningId(dynamic value) => value is int ? value : int.tryParse('$value');
String planningText(BuildContext context, String key) =>
    planningLabel(AirmiusScope.of(context).language, key);
DateTime? planningDate(dynamic value) {
  if (value is DateTime) return value;
  final text = '${value ?? ''}'.trim();
  if (text.isEmpty) return null;
  final iso = DateTime.tryParse(text);
  if (iso != null) return iso;
  for (final format in [
    DateFormat('dd.MM.yyyy HH:mm'),
    DateFormat('dd.MM.yyyy'),
  ]) {
    try {
      return format.parseStrict(text);
    } on FormatException {
      // Try the next supported display format.
    }
  }
  return null;
}

String planningDay(dynamic value) => planningDate(value) == null
    ? ''
    : DateFormat('dd.MM.yyyy').format(planningDate(value)!);
String planningTime(dynamic value) => planningDate(value) == null
    ? ''
    : DateFormat('dd.MM.yyyy HH:mm').format(planningDate(value)!.toLocal());
String planningApiDay(dynamic value) => planningDate(value) == null
    ? ''
    : DateFormat('yyyy-MM-dd').format(planningDate(value)!);
String planningApiTime(dynamic value) => planningDate(value) == null
    ? ''
    : DateFormat('yyyy-MM-dd HH:mm').format(planningDate(value)!.toLocal());
String planningError(BuildContext context, Object error) {
  if (error is AirmiusApiException) {
    if (error.statusCode == 401) return planningText(context, 'unauthorized');
    if (error.statusCode == 403 || error.statusCode == 404) {
      return planningText(context, 'forbidden');
    }
    if (error.statusCode == 409) return planningText(context, 'conflict');
    if (error.statusCode == 422) {
      try {
        final json = jsonDecode(error.body) as Map;
        if (json['errors'] is Map) {
          return (json['errors'] as Map).entries
              .map((e) => '${e.key}: ${(e.value as List).join(' ')}')
              .join('\n');
        }
        if (json['message'] is String) return json['message'] as String;
      } catch (_) {}
    }
  }
  return planningText(context, 'error');
}

class PlanningError extends StatelessWidget {
  const PlanningError(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 12),
    child: Text(
      text,
      style: TextStyle(color: Theme.of(context).colorScheme.error),
    ),
  );
}

Future<bool> planningDelete(
  BuildContext context,
  Future<dynamic> Function() remove,
) async {
  final confirm = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(planningText(context, 'confirmDelete')),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: Text(planningText(context, 'cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, true),
          child: Text(planningText(context, 'delete')),
        ),
      ],
    ),
  );
  if (confirm != true) return false;
  await remove();
  return true;
}

class PlanningEditor extends StatefulWidget {
  const PlanningEditor({
    super.key,
    required this.title,
    required this.initial,
    required this.fields,
    required this.save,
    this.validate,
  });
  final String title;
  final AirmiusJson initial;
  final List<Widget> Function(PlanningEditorState) fields;
  final Future<dynamic> Function(AirmiusJson) save;
  final String? Function(AirmiusJson)? validate;
  @override
  State<PlanningEditor> createState() => PlanningEditorState();
}

class PlanningEditorState extends State<PlanningEditor> {
  late final AirmiusJson values =
      jsonDecode(jsonEncode(widget.initial)) as AirmiusJson;
  final form = GlobalKey<FormState>();
  bool busy = false, blocked = false;
  String? error;
  String t(String key) => planningText(context, key);
  void change(String key, dynamic value) => setState(() => values[key] = value);
  void update(VoidCallback action) => setState(action);
  Widget text(
    String key, {
    String? label,
    bool required = false,
    int max = 200,
    int lines = 1,
    AirmiusJson? target,
  }) {
    final data = target ?? values;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        key: ValueKey('${identityHashCode(data)}:$key'),
        initialValue: '${data[key] ?? ''}',
        enabled: !busy,
        maxLength: max,
        maxLines: lines,
        decoration: InputDecoration(labelText: label ?? t(key)),
        onChanged: (v) => data[key] = v.trim(),
        validator: (v) =>
            required && (v?.trim().isEmpty ?? true) ? t('required') : null,
      ),
    );
  }

  Widget number(
    String key, {
    bool required = true,
    int min = 0,
    int max = 100000000,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextFormField(
      initialValue: '${values[key] ?? ''}',
      enabled: !busy,
      decoration: InputDecoration(labelText: t(key)),
      keyboardType: TextInputType.number,
      onChanged: (v) => values[key] = int.tryParse(v),
      validator: (v) {
        if (!required && (v?.isEmpty ?? true)) return null;
        final n = int.tryParse(v ?? '');
        return n == null || n < min || n > max ? t('invalidNumber') : null;
      },
    ),
  );
  Widget date(
    String key, {
    bool time = false,
    bool required = true,
    AirmiusJson? target,
  }) {
    final data = target ?? values;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        key: ValueKey('${identityHashCode(data)}:$key:${data[key]}'),
        initialValue: time ? planningTime(data[key]) : planningDay(data[key]),
        readOnly: true,
        enabled: !busy,
        decoration: InputDecoration(
          labelText: t(key),
          suffixIcon: IconButton(
            tooltip: t(key),
            onPressed: busy
                ? null
                : () async {
                    final current = planningDate(data[key]) ?? DateTime.now();
                    final day = await showDatePicker(
                      context: context,
                      initialDate: current,
                      firstDate: DateTime(1900),
                      lastDate: DateTime(2200),
                    );
                    if (!mounted || day == null) return;
                    var chosen = day;
                    if (time) {
                      final clock = await showTimePicker(
                        context: context,
                        initialTime: TimeOfDay.fromDateTime(current),
                      );
                      if (!mounted || clock == null) return;
                      chosen = DateTime(
                        day.year,
                        day.month,
                        day.day,
                        clock.hour,
                        clock.minute,
                      );
                    }
                    setState(
                      () => data[key] = time
                          ? planningApiTime(chosen)
                          : planningApiDay(chosen),
                    );
                  },
            icon: const Icon(Icons.calendar_month_outlined),
          ),
        ),
        validator: (v) => (v?.isEmpty ?? true) && !required
            ? null
            : planningDate(v) == null
            ? t('invalidDate')
            : null,
      ),
    );
  }

  Widget options(
    String key,
    List<AirmiusJson> rows, {
    String? label,
    bool nullable = false,
    String nameKey = 'name',
    VoidCallback? after,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: DropdownButtonFormField<dynamic>(
      key: ValueKey('$key:${values[key]}'),
      initialValue: rows.any((r) => r['id'] == values[key])
          ? values[key]
          : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: label ?? t(key)),
      items: [
        if (nullable) DropdownMenuItem(value: null, child: Text(t('none'))),
        for (final row in rows)
          DropdownMenuItem(
            value: row['id'],
            child: Text('${row[nameKey]}', overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: busy
          ? null
          : (v) {
              change(key, v);
              after?.call();
            },
      validator: (v) => !nullable && v == null ? t('required') : null,
    ),
  );
  Widget status(List<String> statuses, {String key = 'status'}) =>
      options(key, [
        for (final s in statuses) {'id': s, 'name': t(s)},
      ], label: t(key));
  Widget check(String key, String label) => CheckboxListTile(
    contentPadding: EdgeInsets.zero,
    title: Text(label),
    value: values[key] == true,
    onChanged: busy ? null : (v) => change(key, v),
  );
  Future<void> submit() async {
    if (busy || blocked || !form.currentState!.validate()) return;
    final invalid = widget.validate?.call(values);
    if (invalid != null) {
      setState(() => error = invalid);
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await widget.save(values);
      if (result is Map &&
          (result['queued'] == true || result['data'] == null)) {
        throw const FormatException('Unknown save result');
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      final code = e is AirmiusApiException ? e.statusCode : 0;
      setState(() {
        blocked = [0, 401, 403, 404, 409, 503, 599].contains(code);
        error = code == 0 || code == 599
            ? t('uncertain')
            : planningError(context, e);
      });
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !busy,
    child: Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            tooltip: t('save'),
            onPressed: busy || blocked ? null : submit,
            icon: const Icon(Icons.save_outlined),
          ),
        ],
      ),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            ...widget.fields(this),
            if (error != null) PlanningError(error!),
            if (busy) const LinearProgressIndicator(),
            if (blocked)
              TextButton.icon(
                onPressed: () => Navigator.pop(context, true),
                icon: const Icon(Icons.refresh),
                label: Text(t('refresh')),
              ),
            FilledButton.icon(
              onPressed: busy || blocked ? null : submit,
              icon: const Icon(Icons.save_outlined),
              label: Text(t('save')),
            ),
          ],
        ),
      ),
    ),
  );
}
