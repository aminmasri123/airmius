import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';

class InventoryCheckoutDialog extends StatefulWidget {
  const InventoryCheckoutDialog({
    super.key,
    required this.name,
    this.scheduled = false,
    this.initialRequest,
  });
  final String name;
  final bool scheduled;
  final Map<String, dynamic>? initialRequest;

  @override
  State<InventoryCheckoutDialog> createState() =>
      _InventoryCheckoutDialogState();
}

class _InventoryCheckoutDialogState extends State<InventoryCheckoutDialog> {
  late final TextEditingController _quantity;
  late bool _scheduled;
  late DateTime _start;
  late DateTime _end;

  @override
  void initState() {
    super.initState();
    final request = widget.initialRequest;
    _quantity = TextEditingController(text: '${request?['quantity'] ?? 1}');
    final parsedStart = DateTime.tryParse('${request?['starts_at'] ?? ''}');
    final parsedEnd = DateTime.tryParse('${request?['due_at'] ?? ''}');
    _scheduled = widget.scheduled || parsedStart != null || parsedEnd != null;
    _start =
        parsedStart?.toLocal() ?? DateTime.now().add(const Duration(hours: 1));
    _end = parsedEnd?.toLocal() ?? _start.add(const Duration(hours: 1));
  }

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _pick(bool start) async {
    final value = start ? _start : _end;
    final date = await showDatePicker(
      context: context,
      initialDate: value,
      firstDate: DateUtils.dateOnly(DateTime.now()),
      lastDate: DateTime(DateTime.now().year + 5, 12, 31),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(value),
    );
    if (time == null || !mounted) return;
    setState(() {
      final selected = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
      if (start) {
        _start = selected;
        if (!_end.isAfter(_start)) _end = _start.add(const Duration(hours: 1));
      } else {
        _end = selected;
      }
    });
  }

  String _label(DateTime value) {
    final locale = MaterialLocalizations.of(context);
    return '${locale.formatCompactDate(value)} ${locale.formatTimeOfDay(TimeOfDay.fromDateTime(value))}';
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final amount = int.tryParse(_quantity.text.trim());
    final valid =
        amount != null &&
        amount > 0 &&
        (!_scheduled ||
            (_start.isAfter(DateTime.now()) && _end.isAfter(_start)));
    return AlertDialog(
      title: Text(
        t('inventory.checkoutItem').replaceAll('{name}', widget.name),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _quantity,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: t('inventory.quantity')),
              onChanged: (_) => setState(() {}),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(t('membership.choosePeriod')),
              value: _scheduled,
              onChanged: (value) => setState(() => _scheduled = value),
            ),
            if (_scheduled) ...[
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(t('membership.access.startsAt')),
                subtitle: Text(_label(_start)),
                trailing: const Icon(Icons.event),
                onTap: () => _pick(true),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(t('membership.access.endsAt')),
                subtitle: Text(_label(_end)),
                trailing: const Icon(Icons.event),
                onTap: () => _pick(false),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('inventory.cancel')),
        ),
        FilledButton(
          onPressed: !valid
              ? null
              : () => Navigator.pop(context, <String, dynamic>{
                  'quantity': amount,
                  if (_scheduled) 'starts_at': _start.toUtc().toIso8601String(),
                  if (_scheduled) 'due_at': _end.toUtc().toIso8601String(),
                }),
          child: Text(
            widget.initialRequest == null
                ? t('inventory.checkout')
                : t('inventory.updateRequest'),
          ),
        ),
      ],
    );
  }
}
