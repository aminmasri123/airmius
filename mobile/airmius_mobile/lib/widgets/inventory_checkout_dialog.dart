import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';

class InventoryCheckoutDialog extends StatefulWidget {
  const InventoryCheckoutDialog({
    super.key,
    required this.name,
    this.scheduled = false,
    this.initialRequest,
    this.canAssignBorrower = false,
    this.registeredBorrowers = const [],
    this.externalBorrowers = const [],
  });

  final String name;
  final bool scheduled;
  final Map<String, dynamic>? initialRequest;
  final bool canAssignBorrower;
  final List<Map<String, dynamic>> registeredBorrowers;
  final List<Map<String, dynamic>> externalBorrowers;

  @override
  State<InventoryCheckoutDialog> createState() => _InventoryCheckoutDialogState();
}

class _InventoryCheckoutDialogState extends State<InventoryCheckoutDialog> {
  late final TextEditingController _quantity;
  final _guestName = TextEditingController();
  final _guestEmail = TextEditingController();
  final _guestPhone = TextEditingController();
  final _collateralDescription = TextEditingController();
  final _collateralAmount = TextEditingController();
  late bool _scheduled;
  late DateTime _start;
  late DateTime _end;
  String _borrowerKind = 'registered';
  int? _registeredBorrowerId;
  int? _externalBorrowerId;
  bool _hasCollateral = false;
  String _collateralType = 'item';

  @override
  void initState() {
    super.initState();
    final request = widget.initialRequest;
    _quantity = TextEditingController(text: '${request?['quantity'] ?? 1}');
    final parsedStart = DateTime.tryParse('${request?['starts_at'] ?? ''}');
    final parsedEnd = DateTime.tryParse('${request?['due_at'] ?? ''}');
    _scheduled = widget.scheduled || parsedStart != null || parsedEnd != null;
    _start = parsedStart?.toLocal() ?? DateTime.now().add(const Duration(hours: 1));
    _end = parsedEnd?.toLocal() ?? _start.add(const Duration(hours: 1));
    _registeredBorrowerId = _firstId(widget.registeredBorrowers);
    _externalBorrowerId = _firstId(widget.externalBorrowers);
  }

  int? _firstId(List<Map<String, dynamic>> rows) =>
      rows.isEmpty ? null : int.tryParse('${rows.first['id']}');

  @override
  void dispose() {
    _quantity.dispose();
    _guestName.dispose();
    _guestEmail.dispose();
    _guestPhone.dispose();
    _collateralDescription.dispose();
    _collateralAmount.dispose();
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
    final time = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(value));
    if (time == null || !mounted) return;
    setState(() {
      final selected = DateTime(date.year, date.month, date.day, time.hour, time.minute);
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

  int? _eurosToCents(String value) {
    final amount = double.tryParse(value.trim().replaceAll(',', '.'));
    return amount == null ? null : (amount * 100).round();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final amount = int.tryParse(_quantity.text.trim());
    final isNew = widget.initialRequest == null;
    final borrowerValid = !isNew ||
        !widget.canAssignBorrower ||
        (_borrowerKind == 'registered' && _registeredBorrowerId != null) ||
        (_borrowerKind == 'external' && _externalBorrowerId != null) ||
        (_borrowerKind == 'guest' && _guestName.text.trim().isNotEmpty);
    final collateralValid = !_hasCollateral ||
        (_collateralType == 'cash'
            ? (_eurosToCents(_collateralAmount.text) ?? 0) > 0
            : _collateralDescription.text.trim().isNotEmpty);
    final valid = amount != null &&
        amount > 0 &&
        borrowerValid &&
        collateralValid &&
        (!_scheduled || (_start.isAfter(DateTime.now()) && _end.isAfter(_start)));

    return AlertDialog(
      title: Text(t('inventory.checkoutItem').replaceAll('{name}', widget.name)),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (isNew && widget.canAssignBorrower) ...[
                Text(t('inventory.borrowerSection'), style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ChoiceChip(
                      label: Text(t('inventory.borrowerRegistered')),
                      avatar: const Icon(Icons.person_outline, size: 18),
                      selected: _borrowerKind == 'registered',
                      onSelected: (_) => setState(() => _borrowerKind = 'registered'),
                    ),
                    ChoiceChip(
                      label: Text(t('inventory.borrowerExternal')),
                      avatar: const Icon(Icons.contacts_outlined, size: 18),
                      selected: _borrowerKind == 'external',
                      onSelected: (_) => setState(() => _borrowerKind = 'external'),
                    ),
                    ChoiceChip(
                      label: Text(t('inventory.borrowerGuest')),
                      avatar: const Icon(Icons.person_add_alt, size: 18),
                      selected: _borrowerKind == 'guest',
                      onSelected: (_) => setState(() => _borrowerKind = 'guest'),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                if (_borrowerKind == 'registered')
                  DropdownMenu<int>(
                    initialSelection: _registeredBorrowerId,
                    enableFilter: true,
                    requestFocusOnTap: true,
                    expandedInsets: EdgeInsets.zero,
                    label: Text(t('inventory.selectRegisteredBorrower')),
                    dropdownMenuEntries: [for (final person in widget.registeredBorrowers) DropdownMenuEntry(value: int.tryParse('${person['id']}') ?? 0, label: '${person['name'] ?? person['email'] ?? ''}')],
                    onSelected: (value) => setState(() => _registeredBorrowerId = value),
                  ),
                if (_borrowerKind == 'external')
                  DropdownMenu<int>(
                    initialSelection: _externalBorrowerId,
                    enableFilter: true,
                    requestFocusOnTap: true,
                    expandedInsets: EdgeInsets.zero,
                    label: Text(t('inventory.selectExternalBorrower')),
                    dropdownMenuEntries: [for (final person in widget.externalBorrowers) DropdownMenuEntry(value: int.tryParse('${person['id']}') ?? 0, label: '${person['name'] ?? person['email'] ?? ''}')],
                    onSelected: (value) => setState(() => _externalBorrowerId = value),
                  ),
                if (_borrowerKind == 'guest') ...[
                  TextField(controller: _guestName, decoration: InputDecoration(labelText: t('inventory.guestName')), onChanged: (_) => setState(() {})),
                  TextField(controller: _guestEmail, keyboardType: TextInputType.emailAddress, decoration: InputDecoration(labelText: t('inventory.guestEmail'))),
                  TextField(controller: _guestPhone, keyboardType: TextInputType.phone, decoration: InputDecoration(labelText: t('inventory.guestPhone'))),
                ],
                const SizedBox(height: 20),
              ],
              Text(t('inventory.loanDetailsSection'), style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              TextField(controller: _quantity, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: t('inventory.quantity')), onChanged: (_) => setState(() {})),
              SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(t('membership.choosePeriod')), value: _scheduled, onChanged: (value) => setState(() => _scheduled = value)),
              if (_scheduled) ...[
                ListTile(contentPadding: EdgeInsets.zero, title: Text(t('membership.access.startsAt')), subtitle: Text(_label(_start)), trailing: const Icon(Icons.event), onTap: () => _pick(true)),
                ListTile(contentPadding: EdgeInsets.zero, title: Text(t('membership.access.endsAt')), subtitle: Text(_label(_end)), trailing: const Icon(Icons.event), onTap: () => _pick(false)),
              ],
              if (isNew && widget.canAssignBorrower) ...[
                const Divider(height: 28),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('inventory.collateralSection')),
                  subtitle: Text(t('inventory.collateralOptionalHint')),
                  value: _hasCollateral,
                  onChanged: (value) => setState(() => _hasCollateral = value),
                ),
                if (_hasCollateral) ...[
                  DropdownButtonFormField<String>(
                    initialValue: _collateralType,
                    decoration: InputDecoration(labelText: t('inventory.collateralType')),
                    items: [
                      DropdownMenuItem(value: 'item', child: Text(t('inventory.collateralItem'))),
                      DropdownMenuItem(value: 'cash', child: Text(t('inventory.collateralCash'))),
                      DropdownMenuItem(value: 'document', child: Text(t('inventory.collateralDocument'))),
                      DropdownMenuItem(value: 'other', child: Text(t('inventory.collateralOther'))),
                    ],
                    onChanged: (value) => setState(() => _collateralType = value ?? 'item'),
                  ),
                  const SizedBox(height: 8),
                  if (_collateralType == 'cash')
                    TextField(controller: _collateralAmount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: t('inventory.collateralAmount')), onChanged: (_) => setState(() {}))
                  else
                    TextField(controller: _collateralDescription, maxLength: 255, decoration: InputDecoration(labelText: t('inventory.collateralDescription'), helperText: _collateralType == 'document' ? t('inventory.collateralPrivacyHint') : null), onChanged: (_) => setState(() {})),
                ],
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(t('inventory.cancel'))),
        FilledButton(
          onPressed: !valid
              ? null
              : () => Navigator.pop(context, <String, dynamic>{
                    'quantity': amount,
                    if (_scheduled) 'starts_at': _start.toUtc().toIso8601String(),
                    if (_scheduled) 'due_at': _end.toUtc().toIso8601String(),
                    if (isNew && widget.canAssignBorrower) 'borrower_kind': _borrowerKind,
                    if (isNew && widget.canAssignBorrower && _borrowerKind == 'registered') 'borrower_id': _registeredBorrowerId,
                    if (isNew && widget.canAssignBorrower && _borrowerKind == 'external') 'external_member_id': _externalBorrowerId,
                    if (isNew && widget.canAssignBorrower && _borrowerKind == 'guest') 'external_renter_name': _guestName.text.trim(),
                    if (isNew && widget.canAssignBorrower && _borrowerKind == 'guest' && _guestEmail.text.trim().isNotEmpty) 'external_renter_email': _guestEmail.text.trim(),
                    if (isNew && widget.canAssignBorrower && _borrowerKind == 'guest' && _guestPhone.text.trim().isNotEmpty) 'external_renter_phone': _guestPhone.text.trim(),
                    if (isNew && widget.canAssignBorrower && _hasCollateral) 'collateral_type': _collateralType,
                    if (isNew && widget.canAssignBorrower && _hasCollateral && _collateralType == 'cash') 'collateral_amount_cents': _eurosToCents(_collateralAmount.text),
                    if (isNew && widget.canAssignBorrower && _hasCollateral && _collateralType != 'cash') 'collateral_description': _collateralDescription.text.trim(),
                  }),
          child: Text(isNew ? t('inventory.checkout') : t('inventory.updateRequest')),
        ),
      ],
    );
  }
}
