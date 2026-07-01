import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class ClubMembershipManagementScreen extends StatefulWidget {
  const ClubMembershipManagementScreen({super.key});

  @override
  State<ClubMembershipManagementScreen> createState() => _ClubMembershipManagementScreenState();
}

class _ClubMembershipManagementScreenState extends State<ClubMembershipManagementScreen> {
  String _filter = 'Alle';
  String _period = 'Juni 2026';
  String _section = 'overview';
  int? _selectedClubId;
  Future<_ManagedMembershipData?>? _clubFuture;
  _ManagedMembershipData? _currentData;
  final _inviteNameController = TextEditingController();
  final _inviteEmailController = TextEditingController();
  bool _sendingInvitation = false;

  static const _expenseCategories = [
    'Miete & Hallenkosten',
    'Material & Ausrüstung',
    'Trikots & Kleidung',
    'Trainerhonorare',
    'Schiedsrichter & Gebühren',
    'Verbandsbeiträge',
    'Versicherungen',
    'Reisekosten & Fahrtkosten',
    'Verpflegung',
    'Turniere & Wettkaempfe',
    'Lizenzen & Software',
    'Marketing & Werbung',
    'Büro & Verwaltung',
    'Bankgebühren',
    'Steuern & Abgaben',
    'Reparatur & Wartung',
    'Reinigung',
    'Energie & Nebenkosten',
    'Telefon & Internet',
    'Fortbildung',
    'Veranstaltungskosten',
    'Sonstige Ausgabe',
  ];

  static const _incomeCategories = [
    'Mitgliedsbeiträge',
    'Aufnahmegebühren',
    'Spenden',
    'Sponsoring',
    'Zuschüsse & Fördermittel',
    'Kursgebühren',
    'Event-Einnahmen',
    'Ticketverkauf',
    'Merchandise',
    'Vermietung',
    'Rückerstattung',
    'Zinsen',
    'Sonstige Einnahme',
  ];

  int _parseEuroCents(String value) {
    return ((_parseMoneyNumber(value) ?? 0) * 100).round();
  }

  String _formatEuro(int cents) {
    final euros = (cents / 100).toStringAsFixed(2).replaceAll('.', ',');
    return '$euros EUR';
  }

  String _formatEuroAmount(double value) {
    return '${value.toStringAsFixed(2).replaceAll('.', ',')} EUR';
  }

  String _openTotalFromInvoices(List<_InvoiceEntry> invoices) {
    final total = invoices.where((invoice) => invoice.status == 'Offen').fold<int>(0, (sum, invoice) => sum + _parseEuroCents(invoice.amount));
    return _formatEuro(total);
  }

  String _recurringTotalFromMembers(List<_MemberEntry> members) {
    final total = members.fold<int>(0, (sum, member) {
      if (member.type == 'Jugend') return sum + 900;
      if (member.type == 'Aktiv') return sum + 1200;
      return sum;
    });
    return _formatEuro(total);
  }

  String _cashBalanceFromPayments(List<_PaymentEntry> payments) {
    final total = payments.where((payment) => payment.methodKey == 'cash').fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount));
    return _formatEuro(total);
  }

  String _bankBalanceFromPayments(List<_PaymentEntry> payments) {
    final total = payments.where((payment) => payment.methodKey == 'bank_transfer' || payment.methodKey == 'sepa_debit').fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount));
    return _formatEuro(total);
  }

  String _totalBalanceFromPayments(List<_PaymentEntry> payments) {
    final total = payments.fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount));
    return _formatEuro(total);
  }

  bool _boolFromAny(Object? value) {
    if (value is bool) return value;
    final normalized = '$value'.toLowerCase();
    return normalized == '1' || normalized == 'true' || normalized == 'yes';
  }

  String _stringFromJson(JsonMap json, List<String> keys, {String fallback = ''}) {
    for (final key in keys) {
      final value = json[key];
      if (value != null && '$value'.trim().isNotEmpty) return '$value';
    }
    return fallback;
  }

  int _intFromAny(Object? value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  double? _parseMoneyNumber(Object? value) {
    if (value is num) return value.toDouble();

    var raw = '$value'
        .replaceAll('EUR', '')
        .replaceAll('€', '')
        .replaceAll('\u00a0', '')
        .replaceAll(' ', '')
        .trim();
    if (raw.isEmpty || raw == 'null') return null;

    if (raw.contains(',') && raw.contains('.')) {
      raw = raw.replaceAll('.', '').replaceAll(',', '.');
    } else if (raw.contains(',')) {
      raw = raw.replaceAll('.', '').replaceAll(',', '.');
    }

    return double.tryParse(raw);
  }

  String _moneyFromValue(Object? value) {
    final raw = '$value'.trim();
    if (raw.isEmpty || raw == 'null') return '0,00 EUR';
    final parsed = _parseMoneyNumber(value);
    return parsed == null ? raw : _formatEuroAmount(parsed);
  }

  String _memberType(AirmiusClubMember member) {
    final role = (member.role ?? '').toLowerCase();
    final status = (member.status ?? '').toLowerCase();
    if (status == 'pending' || status == 'open') return 'Offen';
    if (role == 'external' || status == 'external') return 'Extern';
    if (role == 'youth' || role == 'junior' || status == 'youth') return 'Jugend';
    return 'Aktiv';
  }

  List<_MemberEntry> _membersFromManagement(AirmiusClubManagement? management) {
    final members = management?.members ?? const <AirmiusClubMember>[];
    return members
        .map(
          (member) => _MemberEntry(
            id: member.id,
            name: member.name,
            email: member.email,
            type: _memberType(member),
            number: (member.memberNumber != null && member.memberNumber!.isNotEmpty) ? member.memberNumber! : 'ID-${member.id}',
            balance: _moneyFromValue(member.membership['balance'] ?? member.membership['open_balance'] ?? member.membership['contribution_amount']),
            sepa: _boolFromAny(member.membership['sepa_mandate_active'] ?? member.membership['sepa_ready'] ?? member.membership['has_sepa_mandate']),
          ),
        )
        .toList();
  }

  List<_InvoiceEntry> _invoicesFromManagement(AirmiusClubManagement? management) {
    final invoices = management?.invoices ?? const <JsonMap>[];
    return invoices.map((invoice) {
      final user = invoice['user'];
      final member = invoice['member'];
      final person = user is JsonMap
          ? _stringFromJson(user, ['name', 'email'], fallback: 'Mitglied')
          : member is JsonMap
              ? _stringFromJson(member, ['name', 'email'], fallback: 'Mitglied')
              : _stringFromJson(invoice, ['member_name', 'user_name', 'recipient_name'], fallback: 'Mitglied');
      final rawStatus = _stringFromJson(invoice, ['status', 'payment_status'], fallback: 'open').toLowerCase();
      final paid = rawStatus == 'paid' || rawStatus == 'bezahlt' || rawStatus == 'settled';
      final status = paid ? 'Bezahlt' : 'Offen';
      return _InvoiceEntry(
        id: _intFromAny(invoice['id']),
        title: _stringFromJson(invoice, ['title', 'number', 'invoice_number'], fallback: 'Rechnung'),
        person: person,
        amount: _moneyFromValue(invoice['amount'] ?? invoice['amount_due'] ?? invoice['total'] ?? invoice['total_amount']),
        status: status,
        color: paid ? AirmiusColors.green : AirmiusColors.amber,
      );
    }).toList();
  }

  List<_PaymentEntry> _paymentsFromManagement(AirmiusClubManagement? management, List<_MemberEntry> members) {
    final payments = management?.payments ?? const <JsonMap>[];
    return payments.map((payment) {
      final purpose = _stringFromJson(payment, ['purpose'], fallback: 'payment').toLowerCase();
      final method = _stringFromJson(payment, ['method'], fallback: 'manual');
      final notes = _stringFromJson(payment, ['notes'], fallback: '');
      final reference = _stringFromJson(payment, ['reference'], fallback: '');
      final detail = notes.isNotEmpty ? notes : reference;

      return _PaymentEntry(
        id: _intFromAny(payment['id']),
        userId: _intFromAny(payment['user_id']),
        invoiceId: _intFromAny(payment['invoice_id']),
        purpose: purpose,
        title: _paymentPurposeLabel(purpose),
        person: _paymentPersonLabel(payment, members),
        amount: _moneyFromValue(payment['amount']),
        amountInput: _paymentAmountInput(_moneyFromValue(payment['amount'])),
        method: _paymentMethodLabel(method),
        methodKey: method,
        date: _dateLabelFromValue(payment['paid_at'] ?? payment['created_at']),
        paidAtInput: _dateLabelFromValue(payment['paid_at'] ?? payment['created_at']),
        reference: reference,
        notes: notes,
        detail: detail,
        icon: _paymentPurposeIcon(purpose),
        color: _paymentPurposeColor(purpose),
      );
    }).toList();
  }

  String _paymentPersonLabel(JsonMap payment, List<_MemberEntry> members) {
    final user = payment['user'];
    if (user is JsonMap) {
      final name = _stringFromJson(user, ['name'], fallback: '');
      final email = _stringFromJson(user, ['email'], fallback: '');
      if (name.isNotEmpty && email.isNotEmpty) return '$name - $email';
      if (name.isNotEmpty) return name;
      if (email.isNotEmpty) return email;
    }

    final userId = _intFromAny(payment['user_id']);
    if (userId > 0) {
      for (final member in members) {
        if (member.id == userId) return _memberPickerLabel(member);
      }
    }

    final invoice = payment['invoice'];
    if (invoice is JsonMap) {
      return _stringFromJson(invoice, ['user_name', 'member_name', 'recipient_name', 'title'], fallback: 'Mitglied');
    }

    return 'Mitglied';
  }

  String _dateLabelFromValue(Object? value) {
    final raw = '$value'.trim();
    if (raw.isEmpty || raw == 'null') return '-';
    final parsed = DateTime.tryParse(raw);
    return parsed == null ? raw : _dateDisplay(parsed.toLocal());
  }

  String _paymentPurposeLabel(String purpose) {
    return switch (purpose) {
      'prepayment' => 'Vorauszahlung',
      'donation' => 'Spende',
      'membership_invoice' => 'Rechnungszahlung',
      _ => 'Zahlung',
    };
  }

  IconData _paymentPurposeIcon(String purpose) {
    return switch (purpose) {
      'prepayment' => Icons.account_balance_wallet_outlined,
      'donation' => Icons.volunteer_activism_outlined,
      'membership_invoice' => Icons.receipt_long_outlined,
      _ => Icons.payments_outlined,
    };
  }

  Color _paymentPurposeColor(String purpose) {
    return switch (purpose) {
      'prepayment' => AirmiusColors.blue,
      'donation' => AirmiusColors.green,
      'membership_invoice' => AirmiusColors.amber,
      _ => AirmiusColors.blue,
    };
  }

  String _financeTypeLabel(String type) {
    return switch (type) {
      'income' => 'Einnahme',
      'expense' => 'Ausgabe',
      _ => type,
    };
  }

  String _financeAccountLabel(String account) {
    return switch (account) {
      'cash' => 'Bar',
      'bank' => 'Bank',
      _ => account,
    };
  }

  List<String> _financeCategoryOptions(String type, {String? current}) {
    final selected = (current ?? '').trim();
    final base = type == 'income' ? _incomeCategories : _expenseCategories;

    if (selected.isEmpty || base.any((item) => item.toLowerCase() == selected.toLowerCase())) {
      return base;
    }

    return [selected, ...base];
  }

  Future<String?> _pickFinanceCategory(BuildContext context, {required String type, required String current}) async {
    final search = TextEditingController();
    final selected = current.trim();

    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          final query = search.text.trim().toLowerCase();
          final options = _financeCategoryOptions(type, current: selected)
              .where((category) => query.isEmpty || category.toLowerCase().contains(query))
              .toList();

          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: Text(
              '${_financeTypeLabel(type)}-Kategorie',
              style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
            ),
            content: SizedBox(
              width: 420,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: search,
                    autofocus: true,
                    onChanged: (_) => setDialogState(() {}),
                    style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
                    decoration: const InputDecoration(
                      labelText: 'Kategorie suchen',
                      hintText: 'z. B. Miete, Material, Spenden',
                      prefixIcon: Icon(Icons.search_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxHeight: 320),
                    child: options.isEmpty
                        ? const Padding(
                            padding: EdgeInsets.all(18),
                            child: Text(
                              'Keine Kategorie gefunden.',
                              style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
                            ),
                          )
                        : ListView.separated(
                            shrinkWrap: true,
                            itemCount: options.length,
                            separatorBuilder: (_, __) => const Divider(height: 1, color: AirmiusColors.border),
                            itemBuilder: (context, index) {
                              final category = options[index];
                              final isSelected = category.toLowerCase() == selected.toLowerCase();

                              return ListTile(
                                dense: true,
                                leading: Icon(
                                  isSelected ? Icons.check_circle : Icons.label_outline,
                                  color: isSelected ? AirmiusColors.blue : AirmiusColors.muted,
                                ),
                                title: Text(
                                  category,
                                  style: TextStyle(
                                    color: isSelected ? AirmiusColors.text : AirmiusColors.muted,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                onTap: () => Navigator.pop(dialogContext, category),
                              );
                            },
                          ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Abbrechen')),
            ],
          );
        },
      ),
    );

    search.dispose();

    return result;
  }

  Color _financeEntryColor(String type) => type == 'income' ? AirmiusColors.green : AirmiusColors.red;

  IconData _financeEntryIcon(String type) => type == 'income' ? Icons.add_card_outlined : Icons.receipt_long_outlined;

  List<_FinanceEntry> _financeEntriesFromManagement(AirmiusClubManagement? management) {
    final entries = management?.financeEntries ?? const <JsonMap>[];
    return entries.map((entry) {
      final type = _stringFromJson(entry, ['type'], fallback: 'expense');
      final account = _stringFromJson(entry, ['account'], fallback: 'cash');
      final reference = _stringFromJson(entry, ['reference'], fallback: '');
      final description = _stringFromJson(entry, ['description'], fallback: '');
      final category = _stringFromJson(entry, ['category'], fallback: '');
      final detail = [
        if (category.isNotEmpty) category,
        if (reference.isNotEmpty) reference,
        if (description.isNotEmpty) description,
      ].join(' - ');

      return _FinanceEntry(
        id: _intFromAny(entry['id']),
        type: type,
        account: account,
        title: _stringFromJson(entry, ['title'], fallback: _financeTypeLabel(type)),
        category: category,
        amount: _moneyFromValue(entry['amount']),
        amountInput: _paymentAmountInput(_moneyFromValue(entry['amount'])),
        date: _dateLabelFromValue(entry['booked_on'] ?? entry['created_at']),
        bookedOnInput: _dateLabelFromValue(entry['booked_on'] ?? entry['created_at']),
        reference: reference,
        description: description,
        detail: detail,
        icon: _financeEntryIcon(type),
        color: _financeEntryColor(type),
      );
    }).toList();
  }

  List<_BankEntry> _bankEntriesFromManagement(AirmiusClubManagement? management) {
    final entries = management?.bankTransactions ?? const <JsonMap>[];
    return entries
        .map(
          (entry) => _BankEntry(
            title: _stringFromJson(entry, ['debtor_name', 'counterparty', 'booking_text', 'title'], fallback: 'Banktransaktion'),
            detail: '${_moneyFromValue(entry['amount'])} - ${_stringFromJson(entry, [
                  'purpose',
                  'remittance_information',
                  'description',
                  'status',
                ], fallback: 'nicht zugeordnet')}',
          ),
        )
        .toList();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubFuture ??= _loadManagedClub();
  }

  @override
  void dispose() {
    _inviteNameController.dispose();
    _inviteEmailController.dispose();
    super.dispose();
  }

  Future<_ManagedMembershipData?> _loadManagedClub() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    final managed = page.items.map(ClubSummary.fromAirmiusClub).where((club) => club.canManage).toList();
    if (managed.isEmpty) return null;
    var selected = managed.first;
    for (final club in managed) {
      if (club.id == _selectedClubId) {
        selected = club;
        break;
      }
    }
    _selectedClubId = selected.id;
    final detail = await services.repositories.clubs.club(selected.id);
    final data = _ManagedMembershipData(clubs: managed, selectedClub: ClubSummary.fromAirmiusClub(detail));
    _currentData = data;
    return data;
  }

  void _reloadClub() {
    setState(() {
      _clubFuture = _loadManagedClub();
    });
  }

  void _applyManagement(AirmiusClubManagement management) {
    final current = _currentData;
    if (current == null) return;

    final updatedClub = current.selectedClub.copyWith(management: management);
    final updatedData = _ManagedMembershipData(
      clubs: current.clubs.map((club) => club.id == updatedClub.id ? club.copyWith(management: management) : club).toList(),
      selectedClub: updatedClub,
    );

    setState(() {
      _currentData = updatedData;
      _clubFuture = Future.value(updatedData);
    });
  }

  Future<void> _sendClubInvitation(ClubSummary club) async {
    if (_sendingInvitation) return;

    final email = _inviteEmailController.text.trim();
    if (email.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Bitte gib eine E-Mail-Adresse ein.')),
      );
      return;
    }

    setState(() => _sendingInvitation = true);
    try {
      await AirmiusServicesScope.of(context).repositories.clubs.inviteClubMember(club.id, {
        'email': email,
        'name': _inviteNameController.text.trim(),
        'send_invitation': true,
        'membership_status': 'active',
      });

      if (!mounted) return;
      _inviteNameController.clear();
      _inviteEmailController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Einladung wurde versendet.')),
      );
      setState(() => _sendingInvitation = false);
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException ? error.userMessage : '$error';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Einladung konnte nicht gesendet werden: $message')),
      );
      setState(() => _sendingInvitation = false);
    }
  }

  String _paymentMethodLabel(String method) {
    return switch (method) {
      'cash' => 'Barzahlung',
      'bank_transfer' => 'Überweisung',
      'sepa_debit' => 'SEPA-Lastschrift',
      'manual' => 'Manuell',
      _ => method,
    };
  }

  String _paymentAmountInput(String amount) {
    return amount.replaceAll('EUR', '').replaceAll('€', '').trim();
  }

  String _normalizePaymentAmount(String value) {
    final trimmed = value.trim();
    if (trimmed.contains(',')) {
      return trimmed.replaceAll('.', '').replaceAll(',', '.');
    }
    return trimmed;
  }

  String _twoDigits(int value) => value.toString().padLeft(2, '0');

  String _dateOnly(DateTime value) => '${value.year}-${_twoDigits(value.month)}-${_twoDigits(value.day)}';

  String _dateDisplay(DateTime value) => '${_twoDigits(value.day)}.${_twoDigits(value.month)}.${value.year}';

  String? _dateInputForApi(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return null;

    final german = RegExp(r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$').firstMatch(trimmed);
    if (german != null) {
      final day = _twoDigits(int.tryParse(german.group(1) ?? '') ?? 0);
      final month = _twoDigits(int.tryParse(german.group(2) ?? '') ?? 0);
      final year = german.group(3) ?? '';
      return '$year-$month-$day';
    }

    final parsed = DateTime.tryParse(trimmed);
    return parsed == null ? trimmed : _dateOnly(parsed);
  }

  bool _isCurrentYearDate(String value) {
    final normalized = _dateInputForApi(value);
    final parsed = normalized == null ? null : DateTime.tryParse(normalized);

    return parsed != null && parsed.year == DateTime.now().year;
  }

  String _dateRangeLabel(DateTimeRange range) => '${_dateDisplay(range.start)} bis ${_dateDisplay(range.end)}';

  String _memberPickerLabel(_MemberEntry member) {
    return member.email.trim().isEmpty ? member.name : '${member.name} - ${member.email}';
  }

  _MemberEntry _memberById(List<_MemberEntry> members, int memberId) {
    return members.firstWhere((member) => member.id == memberId, orElse: () => members.first);
  }

  Widget _memberPickerField({
    required BuildContext context,
    required List<_MemberEntry> members,
    required int selectedMemberId,
    required ValueChanged<int> onChanged,
  }) {
    final selected = _memberById(members, selectedMemberId);
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: () async {
        final picked = await _pickMember(context, members, selectedMemberId);
        if (picked != null) onChanged(picked.id);
      },
      child: InputDecorator(
        decoration: const InputDecoration(
          labelText: 'Mitglied',
          prefixIcon: Icon(Icons.search_outlined),
          suffixIcon: Icon(Icons.expand_more),
        ),
        child: Text(
          _memberPickerLabel(selected),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
        ),
      ),
    );
  }

  Future<_MemberEntry?> _pickMember(BuildContext context, List<_MemberEntry> members, int selectedMemberId) async {
    final search = TextEditingController();
    try {
      return await showModalBottomSheet<_MemberEntry>(
        context: context,
        isScrollControlled: true,
        backgroundColor: AirmiusColors.card,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        builder: (sheetContext) => StatefulBuilder(
          builder: (context, setSheetState) {
            final query = search.text.trim().toLowerCase();
            final filtered = query.isEmpty
                ? members
                : members.where((member) {
                    final haystack = '${member.name} ${member.email} ${member.number}'.toLowerCase();
                    return haystack.contains(query);
                  }).toList();

            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 16 + MediaQuery.of(sheetContext).viewInsets.bottom),
                child: SizedBox(
                  height: MediaQuery.of(sheetContext).size.height * .72,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Text('Mitglied auswählen', style: TextStyle(color: AirmiusColors.text, fontSize: 20, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: 'Suchen',
                        hint: 'Name, E-Mail oder Mitgliedsnummer',
                        icon: Icons.search_outlined,
                        controller: search,
                        onChanged: (_) => setSheetState(() {}),
                      ),
                      const SizedBox(height: 12),
                      Expanded(
                        child: filtered.isEmpty
                            ? const Center(child: Text('Kein Mitglied gefunden.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)))
                            : ListView.separated(
                                itemCount: filtered.length,
                                separatorBuilder: (_, __) => const SizedBox(height: 8),
                                itemBuilder: (context, index) {
                                  final member = filtered[index];
                                  final selected = member.id == selectedMemberId;
                                  return ListTile(
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(14),
                                      side: BorderSide(color: selected ? AirmiusColors.blue : AirmiusColors.border),
                                    ),
                                    tileColor: selected ? AirmiusColors.blue.withValues(alpha: .18) : AirmiusColors.cardSoft,
                                    leading: CircleAvatar(
                                      backgroundColor: selected ? AirmiusColors.blue : AirmiusColors.input,
                                      foregroundColor: Colors.white,
                                      child: Text(member.name.isEmpty ? '?' : member.name.substring(0, 1).toUpperCase()),
                                    ),
                                    title: Text(member.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                                    subtitle: Text(
                                      [member.email, member.number].where((value) => value.trim().isNotEmpty).join(' - '),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700),
                                    ),
                                    trailing: selected ? const Icon(Icons.check_circle, color: AirmiusColors.blue) : null,
                                    onTap: () => Navigator.pop(sheetContext, member),
                                  );
                                },
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      );
    } finally {
      search.dispose();
    }
  }

  Future<void> _recordPayment(ClubSummary club, List<_InvoiceEntry> invoices) async {
    final openInvoices = invoices.where((invoice) => invoice.id > 0 && invoice.status != 'Bezahlt').toList();
    if (openInvoices.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keine offene Rechnung zum Bezahlen gefunden.')),
      );
      return;
    }

    var selectedInvoiceId = openInvoices.first.id;
    var method = 'cash';
    final amount = TextEditingController(text: _paymentAmountInput(openInvoices.first.amount));
    final paidAt = TextEditingController(text: _dateDisplay(DateTime.now()));
    final reference = TextEditingController();
    final notes = TextEditingController();

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: const Text('Zahlung erfassen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                DropdownButtonFormField<int>(
                  value: selectedInvoiceId,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Rechnung'),
                  items: [
                    for (final invoice in openInvoices)
                      DropdownMenuItem<int>(
                        value: invoice.id,
                        child: Text('${invoice.title} - ${invoice.person} - ${invoice.amount}', overflow: TextOverflow.ellipsis),
                      ),
                  ],
                  onChanged: (value) {
                    final nextId = value ?? selectedInvoiceId;
                    final selected = openInvoices.firstWhere((invoice) => invoice.id == nextId, orElse: () => openInvoices.first);
                    setDialogState(() {
                      selectedInvoiceId = nextId;
                      amount.text = _paymentAmountInput(selected.amount);
                    });
                  },
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Betrag EUR', hint: '0,00', controller: amount, keyboardType: TextInputType.number),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: method,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Zahlungsart'),
                  items: const ['cash', 'bank_transfer'].map((item) => DropdownMenuItem<String>(value: item, child: Text(_paymentMethodLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() => method = value ?? method),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Bezahlt am', hint: 'TT.MM.JJJJ', controller: paidAt),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Referenz', hint: 'optional', controller: reference),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Notiz', hint: 'optional', controller: notes, maxLines: 2),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Abbrechen')),
              FilledButton.icon(
                onPressed: () => Navigator.pop(dialogContext, {
                  'invoice_id': selectedInvoiceId,
                  'amount': amount.text.trim().isEmpty ? null : _normalizePaymentAmount(amount.text),
                  'method': method,
                  'paid_at': _dateInputForApi(paidAt.text),
                  'reference': reference.text.trim().isEmpty ? null : reference.text.trim(),
                  'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
                }),
                icon: const Icon(Icons.payments_outlined),
                label: const Text('Speichern'),
              ),
            ],
          );
        },
      ),
    );

    amount.dispose();
    paidAt.dispose();
    reference.dispose();
    notes.dispose();

    if (payload == null) return;

    try {
      final invoiceId = _intFromAny(payload['invoice_id']);
      final management = await AirmiusServicesScope.of(context).repositories.clubs.recordMembershipPayment(club.id, invoiceId, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Zahlung per ${_paymentMethodLabel('${payload['method']}')} erfasst.')),
      );
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException ? error.userMessage : '$error';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Zahlung konnte nicht erfasst werden: $message')),
      );
    }
  }

  Future<void> _recordDonation(ClubSummary club, List<_MemberEntry> members) async {
    final availableMembers = members.where((member) => member.id > 0).toList();
    if (availableMembers.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keine Mitglieder für eine Spende gefunden.')),
      );
      return;
    }

    var selectedMemberId = availableMembers.first.id;
    var method = 'cash';
    final amount = TextEditingController();
    final paidAt = TextEditingController(text: _dateDisplay(DateTime.now()));
    final reference = TextEditingController();
    final notes = TextEditingController();

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: const Text('Spende erfassen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                _memberPickerField(
                  context: dialogContext,
                  members: availableMembers,
                  selectedMemberId: selectedMemberId,
                  onChanged: (value) => setDialogState(() => selectedMemberId = value),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Betrag EUR', hint: '0,00', controller: amount, keyboardType: TextInputType.number),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: method,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Zahlungsart'),
                  items: const ['cash', 'bank_transfer'].map((item) => DropdownMenuItem<String>(value: item, child: Text(_paymentMethodLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() => method = value ?? method),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Erhalten am', hint: 'TT.MM.JJJJ', controller: paidAt),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Referenz', hint: 'optional', controller: reference),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Notiz', hint: 'optional', controller: notes, maxLines: 2),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Abbrechen')),
              FilledButton.icon(
                onPressed: () {
                  if (amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                          'user_id': selectedMemberId,
                          'amount': _normalizePaymentAmount(amount.text),
                          'method': method,
                          'paid_at': _dateInputForApi(paidAt.text),
                          'reference': reference.text.trim().isEmpty ? null : reference.text.trim(),
                          'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
                        });
                },
                icon: const Icon(Icons.volunteer_activism_outlined),
                label: const Text('Speichern'),
              ),
            ],
          );
        },
      ),
    );

    amount.dispose();
    paidAt.dispose();
    reference.dispose();
    notes.dispose();

    if (payload == null) return;

    try {
      final management = await AirmiusServicesScope.of(context).repositories.clubs.recordDonation(club.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Spende per ${_paymentMethodLabel('${payload['method']}')} erfasst.')),
      );
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException ? error.userMessage : '$error';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Spende konnte nicht erfasst werden: $message')),
      );
    }
  }

  Future<void> _recordPrepayment(ClubSummary club, List<_MemberEntry> members) async {
    final availableMembers = members.where((member) => member.id > 0).toList();
    if (availableMembers.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keine Mitglieder für eine Vorauszahlung gefunden.')),
      );
      return;
    }

    var selectedMemberId = availableMembers.first.id;
    var method = 'cash';
    DateTimeRange? coverageRange;
    final amount = TextEditingController();
    final paidAt = TextEditingController(text: _dateDisplay(DateTime.now()));
    final reference = TextEditingController();
    final notes = TextEditingController();

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: const Text('Vorauszahlung erfassen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                _memberPickerField(
                  context: dialogContext,
                  members: availableMembers,
                  selectedMemberId: selectedMemberId,
                  onChanged: (value) => setDialogState(() => selectedMemberId = value),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Betrag EUR', hint: '120,00', controller: amount, keyboardType: TextInputType.number),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: method,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Zahlungsart'),
                  items: const ['cash', 'bank_transfer'].map((item) => DropdownMenuItem<String>(value: item, child: Text(_paymentMethodLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() => method = value ?? method),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Erhalten am', hint: 'TT.MM.JJJJ', controller: paidAt),
                const SizedBox(height: 10),
                InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () async {
                    final now = DateTime.now();
                    final picked = await showDateRangePicker(
                      context: dialogContext,
                      firstDate: DateTime(now.year - 1),
                      lastDate: DateTime(now.year + 5),
                      initialDateRange: coverageRange,
                      helpText: 'Zeitraum auswählen',
                      saveText: 'Übernehmen',
                    );
                    if (picked != null) {
                      setDialogState(() => coverageRange = picked);
                    }
                  },
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Gilt für',
                      suffixIcon: Icon(Icons.date_range_outlined),
                    ),
                    child: Text(
                      coverageRange == null ? 'Zeitraum auswählen' : _dateRangeLabel(coverageRange!),
                      style: TextStyle(color: coverageRange == null ? AirmiusColors.muted : AirmiusColors.text, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Referenz', hint: 'optional', controller: reference),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Notiz', hint: 'optional', controller: notes, maxLines: 2),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Abbrechen')),
              FilledButton.icon(
                onPressed: () {
                  if (amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                    'user_id': selectedMemberId,
                    'amount': _normalizePaymentAmount(amount.text),
                    'method': method,
                    'paid_at': _dateInputForApi(paidAt.text),
                    'coverage_start': coverageRange == null ? null : _dateOnly(coverageRange!.start),
                    'coverage_end': coverageRange == null ? null : _dateOnly(coverageRange!.end),
                    'coverage_note': coverageRange == null ? null : _dateRangeLabel(coverageRange!),
                    'reference': reference.text.trim().isEmpty ? null : reference.text.trim(),
                    'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
                  });
                },
                icon: const Icon(Icons.account_balance_wallet_outlined),
                label: const Text('Speichern'),
              ),
            ],
          );
        },
      ),
    );

    amount.dispose();
    paidAt.dispose();
    reference.dispose();
    notes.dispose();

    if (payload == null) return;

    try {
      final management = await AirmiusServicesScope.of(context).repositories.clubs.recordPrepayment(club.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Vorauszahlung per ${_paymentMethodLabel('${payload['method']}')} erfasst.')),
      );
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException ? error.userMessage : '$error';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Vorauszahlung konnte nicht erfasst werden: $message')),
      );
    }
  }

  Future<void> _editPayment(ClubSummary club, _PaymentEntry payment, List<_MemberEntry> members) async {
    if (payment.id <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Diese Zahlung kann nicht bearbeitet werden, weil keine Zahlungs-ID geladen wurde.')),
      );
      return;
    }

    final availableMembers = members.where((member) => member.id > 0).toList();
    final canChangeMember = payment.invoiceId <= 0 && availableMembers.isNotEmpty;
    var selectedMemberId = payment.userId;
    if (canChangeMember && !availableMembers.any((member) => member.id == selectedMemberId)) {
      selectedMemberId = availableMembers.first.id;
    }

    const methodOptions = ['cash', 'bank_transfer', 'sepa_debit', 'manual'];
    var method = methodOptions.contains(payment.methodKey) ? payment.methodKey : 'manual';
    final amount = TextEditingController(text: payment.amountInput);
    final paidAt = TextEditingController(text: payment.paidAtInput == '-' ? _dateDisplay(DateTime.now()) : payment.paidAtInput);
    final reference = TextEditingController(text: payment.reference);
    final notes = TextEditingController(text: payment.notes);

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: Text('${payment.title} bearbeiten', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                if (canChangeMember) ...[
                  _memberPickerField(
                    context: dialogContext,
                    members: availableMembers,
                    selectedMemberId: selectedMemberId,
                    onChanged: (value) => setDialogState(() => selectedMemberId = value),
                  ),
                  const SizedBox(height: 10),
                ] else ...[
                  InputDecorator(
                    decoration: const InputDecoration(labelText: 'Mitglied'),
                    child: Text(payment.person, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  ),
                  const SizedBox(height: 10),
                ],
                AirmiusTextField(label: 'Betrag EUR', hint: '0,00', controller: amount, keyboardType: TextInputType.number),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: method,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Zahlungsart'),
                  items: methodOptions.map((item) => DropdownMenuItem<String>(value: item, child: Text(_paymentMethodLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() => method = value ?? method),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Erhalten am', hint: 'TT.MM.JJJJ', controller: paidAt),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Referenz', hint: 'optional', controller: reference),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Notiz / Zeitraum', hint: 'optional', controller: notes, maxLines: 3),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Abbrechen')),
              FilledButton.icon(
                onPressed: () {
                  if (amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                    if (canChangeMember) 'user_id': selectedMemberId,
                    'amount': _normalizePaymentAmount(amount.text),
                    'method': method,
                    'paid_at': _dateInputForApi(paidAt.text),
                    'reference': reference.text.trim().isEmpty ? null : reference.text.trim(),
                    'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
                  });
                },
                icon: const Icon(Icons.save_outlined),
                label: const Text('Speichern'),
              ),
            ],
          );
        },
      ),
    );

    amount.dispose();
    paidAt.dispose();
    reference.dispose();
    notes.dispose();

    if (payload == null) return;

    try {
      final management = await AirmiusServicesScope.of(context).repositories.clubs.updatePayment(club.id, payment.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Zahlung wurde aktualisiert.')),
      );
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException ? error.userMessage : '$error';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Zahlung konnte nicht aktualisiert werden: $message')),
      );
    }
  }

  Future<void> _editFinanceEntry(ClubSummary club, {String initialType = 'expense', _FinanceEntry? entry}) async {
    final isEdit = entry != null && entry.id > 0;
    const typeOptions = ['income', 'expense'];
    const accountOptions = ['cash', 'bank'];
    var type = typeOptions.contains(entry?.type) ? entry!.type : initialType;
    var account = accountOptions.contains(entry?.account) ? entry!.account : 'cash';
    final title = TextEditingController(text: entry?.title ?? '');
    final category = TextEditingController(text: entry?.category ?? '');
    final amount = TextEditingController(text: entry?.amountInput ?? '');
    final bookedOn = TextEditingController(text: entry == null || entry.bookedOnInput == '-' ? _dateDisplay(DateTime.now()) : entry.bookedOnInput);
    final reference = TextEditingController(text: entry?.reference ?? '');
    final description = TextEditingController(text: entry?.description ?? '');

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: Text(
              isEdit ? 'Buchung bearbeiten' : '${_financeTypeLabel(type)} buchen',
              style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
            ),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                DropdownButtonFormField<String>(
                  value: type,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Typ'),
                  items: typeOptions.map((item) => DropdownMenuItem<String>(value: item, child: Text(_financeTypeLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() {
                    type = value ?? type;
                    final categoryText = category.text.trim();
                    final options = _financeCategoryOptions(type);
                    if (categoryText.isNotEmpty && !options.any((item) => item.toLowerCase() == categoryText.toLowerCase())) {
                      category.clear();
                    }
                  }),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: account,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Konto'),
                  items: accountOptions.map((item) => DropdownMenuItem<String>(value: item, child: Text(_financeAccountLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() => account = value ?? account),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Titel', hint: 'z. B. Hallenmiete', controller: title),
                const SizedBox(height: 10),
                InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () async {
                    final selected = await _pickFinanceCategory(dialogContext, type: type, current: category.text);
                    if (selected == null) return;
                    setDialogState(() => category.text = selected);
                  },
                  child: InputDecorator(
                    isEmpty: category.text.trim().isEmpty,
                    decoration: const InputDecoration(labelText: 'Kategorie'),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            category.text.trim().isEmpty ? 'Kategorie auswählen' : category.text.trim(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: category.text.trim().isEmpty ? AirmiusColors.muted : AirmiusColors.text,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.search_outlined, color: AirmiusColors.muted, size: 20),
                        const SizedBox(width: 4),
                        const Icon(Icons.arrow_drop_down, color: AirmiusColors.muted),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Betrag EUR', hint: '0,00', controller: amount, keyboardType: TextInputType.number),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Datum', hint: 'TT.MM.JJJJ', controller: bookedOn),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Referenz', hint: 'Belegnummer oder Kontoauszug', controller: reference),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Beschreibung', hint: 'Optional', controller: description, maxLines: 3),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Abbrechen')),
              FilledButton.icon(
                onPressed: () {
                  if (title.text.trim().isEmpty || amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                    'type': type,
                    'account': account,
                    'title': title.text.trim(),
                    'category': category.text.trim().isEmpty ? null : category.text.trim(),
                    'amount': _normalizePaymentAmount(amount.text),
                    'booked_on': _dateInputForApi(bookedOn.text),
                    'reference': reference.text.trim().isEmpty ? null : reference.text.trim(),
                    'description': description.text.trim().isEmpty ? null : description.text.trim(),
                  });
                },
                icon: const Icon(Icons.save_outlined),
                label: const Text('Speichern'),
              ),
            ],
          );
        },
      ),
    );

    title.dispose();
    category.dispose();
    amount.dispose();
    bookedOn.dispose();
    reference.dispose();
    description.dispose();

    if (payload == null) return;

    try {
      final repositories = AirmiusServicesScope.of(context).repositories.clubs;
      final management = isEdit ? await repositories.updateFinanceEntry(club.id, entry!.id, payload) : await repositories.createFinanceEntry(club.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(isEdit ? 'Buchung wurde aktualisiert.' : '${_financeTypeLabel('${payload['type']}')} wurde gebucht.')),
      );
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException ? error.userMessage : '$error';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Buchung konnte nicht gespeichert werden: $message')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<_ManagedMembershipData?>(
      future: _clubFuture,
      initialData: _currentData,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting && snapshot.data == null) {
          return const Scaffold(
            backgroundColor: AirmiusColors.bg,
            body: PageFrame(
              title: 'Mitglieder & Beiträge',
              subtitle: 'Berechtigungen werden geprüft',
              child: AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(18), child: CircularProgressIndicator(color: AirmiusColors.blue)))),
            ),
          );
        }

        if (snapshot.hasError || snapshot.data == null) {
          return Scaffold(
            backgroundColor: AirmiusColors.bg,
            appBar: AppBar(
              backgroundColor: AirmiusColors.header,
              surfaceTintColor: Colors.transparent,
              title: const Text('Mitglieder & Beiträge', style: TextStyle(fontWeight: FontWeight.w900)),
            ),
            body: PageFrame(
              title: 'Keine Berechtigung',
              subtitle: 'Nur Vereinsadmins, Manager oder Finanzrollen dürfen diese Daten sehen',
              child: AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: .45),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Icon(Icons.lock_outline, color: AirmiusColors.muted, size: 34),
                    const SizedBox(height: 12),
                    const Text('Mitglieder, Beiträge und Rechnungen sind Verwaltungsdaten.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(
                      snapshot.hasError ? 'Die Berechtigung konnte nicht geprüft werden: ${snapshot.error}' : 'Deine Rolle ist für diese Verwaltungsseite nicht freigeschaltet.',
                      style: const TextStyle(color: AirmiusColors.muted, height: 1.4),
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(label: 'Erneut prüfen', icon: Icons.refresh_outlined, secondary: true, onPressed: _reloadClub),
                  ],
                ),
              ),
            ),
          );
        }

        final data = snapshot.data!;
        final club = data.selectedClub;
        final management = club.management;
        final members = _membersFromManagement(management);
        final invoices = _invoicesFromManagement(management);
        final bankEntries = _bankEntriesFromManagement(management);
        final payments = _paymentsFromManagement(management, members);
        final financeEntries = _financeEntriesFromManagement(management);
        final activeMembersCount = management?.activeMembersCount ?? members.where((member) => member.type == 'Aktiv').length;
        final linkedPeopleCount = management?.linkedPeopleCount ?? members.length;
        final openInvoicesCount = management?.openInvoicesCount ?? invoices.where((invoice) => invoice.status == 'Offen').length;
        final sepaReadyMembersCount = management?.sepaReadyMembersCount ?? members.where((member) => member.sepa).length;
        final openInvoiceTotal = management == null ? _openTotalFromInvoices(invoices) : _formatEuroAmount(management.openInvoiceAmount);
        final recurringContributionTotal = management == null ? _recurringTotalFromMembers(members) : _formatEuroAmount(management.recurringContributionTotal);
        final cashBalance = management == null ? _cashBalanceFromPayments(payments) : _formatEuroAmount(management.cashBalance);
        final bankBalance = management == null ? _bankBalanceFromPayments(payments) : _formatEuroAmount(management.bankBalance);
        final totalBalance = management == null ? _totalBalanceFromPayments(payments) : _formatEuroAmount(management.totalBalance);
        final hasBackendFinancePeriodTotals = management?.hasFinancePeriodTotals ?? false;
        final incomePeriodTotal = !hasBackendFinancePeriodTotals
            ? _formatEuro(
                payments.where((payment) => _isCurrentYearDate(payment.paidAtInput)).fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount)) +
                    financeEntries.where((entry) => entry.type == 'income' && _isCurrentYearDate(entry.bookedOnInput)).fold<int>(0, (sum, entry) => sum + _parseEuroCents(entry.amount)),
              )
            : _formatEuroAmount(management!.incomePeriodTotal);
        final expensePeriodTotal = !hasBackendFinancePeriodTotals
            ? _formatEuro(financeEntries.where((entry) => entry.type == 'expense' && _isCurrentYearDate(entry.bookedOnInput)).fold<int>(0, (sum, entry) => sum + _parseEuroCents(entry.amount)))
            : _formatEuroAmount(management!.expensePeriodTotal);
        final financePeriodLabel = hasBackendFinancePeriodTotals ? management!.financePeriodLabel : 'Dieses Jahr';
        final unassignedBalance = management?.unassignedBalance ?? 0;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Mitglieder & Beiträge', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Mitglieder & Beiträge',
        subtitle: '${club.name} - Mitgliederdaten, Rechnungen, Zahlungen, SEPA, DATEV und Import',
        trailing: StatusPill(club.name),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _ClubMembershipClubSelector(
              clubs: data.clubs,
              selectedClubId: club.id,
              onChanged: (clubId) {
                setState(() {
                  _selectedClubId = clubId;
                  _filter = 'Alle';
                  _section = 'overview';
                  _clubFuture = _loadManagedClub();
                });
              },
            ),
            const SizedBox(height: 14),
            _MembershipSectionTabs(
              active: _section,
              hasRules: management != null,
              onSelect: (value) => setState(() => _section = value),
            ),
            const SizedBox(height: 14),
            if (_section == 'rules' && management != null) ...[
              _MembershipRulesAdminPanel(club: club, management: management, onChanged: _reloadClub),
              const SizedBox(height: 14),
            ],
            if (_section == 'rules' && management == null) ...[
              const AirmiusPanel(
                child: Text('Beitragsregeln sind fuer diesen Verein nicht geladen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
              ),
              const SizedBox(height: 14),
            ],
            if (_section == 'overview') ...[
              _MembershipKpiGrid(
              cards: [
                _MembershipKpi(title: 'Aktive Mitglieder', value: '$activeMembersCount', detail: 'von $linkedPeopleCount verknüpften Personen'),
                _MembershipKpi(title: 'Offen', value: openInvoiceTotal, detail: '$openInvoicesCount offene Rechnung(en)'),
                _MembershipKpi(title: 'SEPA bereit', value: '$sepaReadyMembersCount', detail: 'Mandate mit IBAN und Referenz'),
                _MembershipKpi(title: 'Wiederkehrende Beiträge', value: recurringContributionTotal, detail: 'Summe aktiver Beitragssätze'),
              ],
            ),
            const SizedBox(height: 14),
            ],
            if (_section == 'members') ...[
              AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('Mitglieder')),
                      StatusPill(_filter),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Aktiv', 'Extern', 'Jugend', 'Offen'].map((item) {
                      return ChoiceChip(
                        selected: _filter == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _filter = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _filter == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _filter == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 12),
                  for (final member in members.where((member) => _filter == 'Alle' || member.type == _filter || (_filter == 'Offen' && member.balance != '0,00 EUR')))
                    _MemberCard(member: member),
                  if (members.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 10),
                      child: Text('Keine Mitglieder aus der Vereinsverwaltung geladen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ),
                ],
              ),
            ),
              const SizedBox(height: 14),
            ],
            if (_section == 'invite') ...[
              AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Import & Einladung'),
                  const SizedBox(height: 10),
                  AirmiusTextField(label: 'Name', hint: 'Optional', icon: Icons.badge_outlined, controller: _inviteNameController),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: 'E-Mail',
                    hint: 'mitglied@example.com',
                    icon: Icons.alternate_email,
                    controller: _inviteEmailController,
                    keyboardType: TextInputType.emailAddress,
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: _sendingInvitation ? 'Wird gesendet...' : 'Einladung senden',
                        icon: Icons.mark_email_read_outlined,
                        onPressed: _sendingInvitation ? null : () => _sendClubInvitation(club),
                      ),
                      AirmiusButton(label: 'CSV Vorlage', icon: Icons.table_view_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'CSV Vorlage', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.table_view_outlined)),
                      AirmiusButton(label: 'Extern anlegen', icon: Icons.person_add_alt_1_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Extern anlegen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.person_add_alt_1_outlined)),
                    ],
                  ),
                ],
              ),
            ),
              const SizedBox(height: 14),
            ],
            if (_section == 'payments') ...[
              const Eyebrow('Vereinskasse'),
              const SizedBox(height: 10),
              _MembershipTreasuryKpiGrid(
                cards: [
                  _MembershipKpi(
                    title: 'Barbestand',
                    value: cashBalance,
                    detail: 'aktueller Bestand',
                    icon: Icons.account_balance_wallet_outlined,
                  ),
                  _MembershipKpi(
                    title: 'Bankbestand',
                    value: bankBalance,
                    detail: 'aktueller Bestand',
                    icon: Icons.account_balance_outlined,
                  ),
                  _MembershipKpi(
                    title: 'Gesamt',
                    value: totalBalance,
                    detail: unassignedBalance > 0 ? 'aktuell inkl. ${_formatEuroAmount(unassignedBalance)} manuell' : 'aktueller Gesamtbestand',
                    icon: Icons.layers_outlined,
                    accent: AirmiusColors.blue,
                  ),
                  _MembershipKpi(
                    title: 'Einnahmen',
                    value: incomePeriodTotal,
                    detail: financePeriodLabel,
                    icon: Icons.call_received_outlined,
                    accent: AirmiusColors.green,
                  ),
                  _MembershipKpi(
                    title: 'Ausgaben',
                    value: expensePeriodTotal,
                    detail: financePeriodLabel,
                    icon: Icons.call_made_outlined,
                    accent: AirmiusColors.red,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('Rechnungen & Zahlungen')),
                      SizedBox(
                        width: 150,
                        child: DropdownButtonFormField<String>(
                          value: _period,
                          dropdownColor: AirmiusColors.cardSoft,
                          decoration: const InputDecoration(labelText: 'Zeitraum'),
                          items: const ['Juni 2026', 'Mai 2026', 'Q2 2026', '2026'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                          onChanged: (value) => setState(() => _period = value ?? _period),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  for (final invoice in invoices) _InvoiceLine(invoice: invoice),
                  if (invoices.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 10),
                      child: Text('Keine Rechnungen fuer diesen Verein geladen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Zahlung erfassen', icon: Icons.payments_outlined, onPressed: () => _recordPayment(club, invoices)),
                      AirmiusButton(label: 'Spende erfassen', icon: Icons.volunteer_activism_outlined, secondary: true, onPressed: () => _recordDonation(club, members)),
                      AirmiusButton(label: 'Vorauszahlung', icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => _recordPrepayment(club, members)),
                      AirmiusButton(label: 'Einnahme buchen', icon: Icons.add_card_outlined, secondary: true, onPressed: () => _editFinanceEntry(club, initialType: 'income')),
                      AirmiusButton(label: 'Ausgabe buchen', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => _editFinanceEntry(club, initialType: 'expense')),
                      AirmiusButton(label: 'Mahnung vorbereiten', icon: Icons.notification_important_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Mahnung vorbereiten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.notification_important_outlined)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: 0.45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('Kassenbuch')),
                      AirmiusButton(label: 'Buchung', icon: Icons.add_outlined, secondary: true, onPressed: () => _editFinanceEntry(club, initialType: 'expense')),
                    ],
                  ),
                  const SizedBox(height: 10),
                  for (final entry in financeEntries) _FinanceEntryLine(entry: entry, onEdit: () => _editFinanceEntry(club, entry: entry)),
                  if (financeEntries.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 10),
                      child: Text('Noch keine freien Einnahmen oder Ausgaben erfasst.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: 0.45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Erfasste Zahlungen'),
                  const SizedBox(height: 10),
                  for (final payment in payments) _PaymentLine(payment: payment, onEdit: () => _editPayment(club, payment, members)),
                  if (payments.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 10),
                      child: Text('Noch keine Zahlung, Spende oder Vorauszahlung erfasst.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: 0.55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Bankabgleich'),
                  const SizedBox(height: 10),
                  for (final entry in bankEntries) _BankLine(entry: entry),
                  if (bankEntries.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 10),
                      child: Text('Keine Banktransaktionen fuer diesen Verein geladen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Bankdatei importieren', icon: Icons.cloud_upload_outlined, onPressed: () => openUiAction(context, title: 'Bankdatei importieren', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.cloud_upload_outlined)),
                      AirmiusButton(label: 'Zuordnung bestätigen', icon: Icons.task_alt_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Zuordnung bestätigen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.task_alt_outlined)),
                    ],
                  ),
                ],
              ),
            ),
              const SizedBox(height: 14),
            ],
            if (_section == 'export') ...[
              AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('SEPA, DATEV & Regeln'),
                  const SizedBox(height: 12),
                  const _ExportTile(icon: Icons.account_balance_wallet_outlined, title: 'SEPA Sammellauf', subtitle: 'Mandate prüfen, Lastschriftlauf vorbereiten und Export erzeugen.', status: 'Bereit'),
                  const _ExportTile(icon: Icons.fact_check_outlined, title: 'DATEV Export', subtitle: 'Rechnungen, Zahlungen und Buchungssätze für Steuerberatung vorbereiten.', status: 'Konfigurierbar'),
                  const _ExportTile(icon: Icons.rule_folder_outlined, title: 'Beitragsregeln', subtitle: 'Monatlich, alle 4 Monate, halbjährlich oder jährlich pro Mitgliedschaftstyp.', status: 'Aktiv'),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'DATEV export', icon: Icons.ios_share_outlined, onPressed: () => openUiAction(context, title: 'DATEV export', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.ios_share_outlined)),
                      AirmiusButton(label: 'Regeln neu laden', icon: Icons.refresh_outlined, secondary: true, onPressed: _reloadClub),
                    ],
                  ),
                ],
              ),
            ),
              const SizedBox(height: 14),
            ],
            const SizedBox(height: 18),
          ],
        ),
      ),
        );
      },
    );
  }
}

class _ManagedMembershipData {
  const _ManagedMembershipData({required this.clubs, required this.selectedClub});

  final List<ClubSummary> clubs;
  final ClubSummary selectedClub;
}

class _MembershipSectionTabs extends StatelessWidget {
  const _MembershipSectionTabs({
    required this.active,
    required this.hasRules,
    required this.onSelect,
  });

  final String active;
  final bool hasRules;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final tabs = [
      const _MembershipSectionTabData('overview', 'Uebersicht', Icons.dashboard_customize_outlined),
      if (hasRules) const _MembershipSectionTabData('rules', 'Regeln', Icons.tune_outlined),
      const _MembershipSectionTabData('members', 'Mitglieder', Icons.groups_2_outlined),
      const _MembershipSectionTabData('invite', 'Einladen', Icons.mark_email_read_outlined),
      const _MembershipSectionTabData('payments', 'Finanzen', Icons.receipt_long_outlined),
      const _MembershipSectionTabData('export', 'Export', Icons.ios_share_outlined),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final preferredColumns = constraints.maxWidth >= 860
            ? tabs.length
            : constraints.maxWidth >= 500
                ? 3
                : 2;
        final columns = preferredColumns > tabs.length ? tabs.length : preferredColumns;
        const gap = 8.0;
        final tabWidth = (constraints.maxWidth - (gap * (columns - 1))) / columns;

        return Wrap(
          alignment: WrapAlignment.center,
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final tab in tabs)
              SizedBox(
                width: tabWidth,
                child: _MembershipSectionTab(
                  tab: tab,
                  selected: active == tab.value,
                  onTap: () => onSelect(tab.value),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _MembershipSectionTabData {
  const _MembershipSectionTabData(this.value, this.label, this.icon);

  final String value;
  final String label;
  final IconData icon;
}

class _MembershipSectionTab extends StatelessWidget {
  const _MembershipSectionTab({required this.tab, required this.selected, required this.onTap});

  final _MembershipSectionTabData tab;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? AirmiusColors.blue.withValues(alpha: .18) : AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: selected ? AirmiusColors.blue : AirmiusColors.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.max,
          children: [
            Icon(tab.icon, size: 18, color: selected ? AirmiusColors.blue : AirmiusColors.muted),
            const SizedBox(width: 7),
            Text(
              tab.label,
              style: TextStyle(color: selected ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
            ),
          ],
        ),
      ),
    );
  }
}

class _ClubMembershipClubSelector extends StatelessWidget {
  const _ClubMembershipClubSelector({
    required this.clubs,
    required this.selectedClubId,
    required this.onChanged,
  });

  final List<ClubSummary> clubs;
  final int selectedClubId;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    var selectedClub = clubs.first;
    for (final club in clubs) {
      if (club.id == selectedClubId) {
        selectedClub = club;
        break;
      }
    }

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(child: Eyebrow('Verein auswaehlen')),
              StatusPill('${clubs.length} verwaltbar'),
            ],
          ),
          const SizedBox(height: 10),
          if (clubs.length > 1)
            DropdownButtonFormField<int>(
              value: selectedClubId,
              dropdownColor: AirmiusColors.cardSoft,
              decoration: const InputDecoration(
                labelText: 'Aktiver Verein',
                prefixIcon: Icon(Icons.apartment_outlined),
              ),
              style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
              items: [
                for (final club in clubs)
                  DropdownMenuItem<int>(
                    value: club.id,
                    child: Text(
                      club.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (value) {
                if (value != null && value != selectedClubId) onChanged(value);
              },
            )
          else
            _SelectedClubLine(club: selectedClub),
        ],
      ),
    );
  }
}

class _SelectedClubLine extends StatelessWidget {
  const _SelectedClubLine({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          const Icon(Icons.apartment_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(club.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 2),
                Text([club.sportType, club.city].where((value) => value != null && value.isNotEmpty).join(' - '), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MembershipRulesAdminPanel extends StatefulWidget {
  const _MembershipRulesAdminPanel({required this.club, required this.management, required this.onChanged});

  final ClubSummary club;
  final AirmiusClubManagement management;
  final VoidCallback onChanged;

  @override
  State<_MembershipRulesAdminPanel> createState() => _MembershipRulesAdminPanelState();
}

class _MembershipRulesAdminPanelState extends State<_MembershipRulesAdminPanel> {
  final _typeName = TextEditingController();
  final _typeSlug = TextEditingController();
  final _typeDescription = TextEditingController();
  final _ruleName = TextEditingController();
  final _ruleAmount = TextEditingController();
  final _ruleValidFrom = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
  final _ruleValidUntil = TextEditingController();
  final _ruleAgeMin = TextEditingController();
  final _ruleAgeMax = TextEditingController();
  final _ruleNotes = TextEditingController();

  int? _editingTypeId;
  int? _editingRuleId;
  int? _ruleTypeId;
  bool _typePublic = true;
  bool _typeActive = true;
  bool _ruleActive = true;
  bool _requestsEnabled = false;
  bool _pauseRequestsEnabled = false;
  bool _saving = false;
  String _ruleInterval = 'monthly';
  String _rulesTab = 'application';
  Set<String> _paymentMethods = {'bank_transfer', 'cash'};
  Map<String, String> _fieldModes = {};
  List<JsonMap> _documents = [];

  @override
  void initState() {
    super.initState();
    _syncSettings();
  }

  @override
  void didUpdateWidget(covariant _MembershipRulesAdminPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.club.id != widget.club.id || oldWidget.management.settings != widget.management.settings) {
      _clearForms();
      _syncSettings();
    }
  }

  @override
  void dispose() {
    for (final controller in [_typeName, _typeSlug, _typeDescription, _ruleName, _ruleAmount, _ruleValidFrom, _ruleValidUntil, _ruleAgeMin, _ruleAgeMax, _ruleNotes]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _syncSettings() {
    final settings = widget.management.settings;
    _requestsEnabled = _bool(settings['membership_requests_enabled']);
    _pauseRequestsEnabled = _bool(settings['member_pause_requests_enabled']);
    final methods = settings['membership_payment_methods'];
    _paymentMethods = methods is List ? methods.map((item) => '$item').toSet() : {'bank_transfer', 'cash'};
    if (_paymentMethods.isEmpty) _paymentMethods = {'bank_transfer', 'cash'};
    final fields = settings['membership_application_fields'];
    _fieldModes = {
      for (final field in fields is List ? fields.whereType<JsonMap>() : const <JsonMap>[])
        if (field['key'] != null) '${field['key']}': _string(field['mode'], fallback: 'off'),
    };
    final documents = settings['membership_application_documents'];
    _documents = documents is List ? documents.whereType<JsonMap>().map((item) => Map<String, dynamic>.from(item)).toList() : [];
  }

  void _clearForms() {
    _editingTypeId = null;
    _editingRuleId = null;
    _ruleTypeId = null;
    _typeName.clear();
    _typeSlug.clear();
    _typeDescription.clear();
    _ruleName.clear();
    _ruleAmount.clear();
    _ruleValidFrom.text = DateTime.now().toIso8601String().substring(0, 10);
    _ruleValidUntil.clear();
    _ruleAgeMin.clear();
    _ruleAgeMax.clear();
    _ruleNotes.clear();
    _typePublic = true;
    _typeActive = true;
    _ruleActive = true;
    _ruleInterval = 'monthly';
  }

  bool _bool(Object? value) => value == true || '$value'.toLowerCase() == '1' || '$value'.toLowerCase() == 'true';

  int? _intOrNull(Object? value) {
    if (value == null || '$value'.trim().isEmpty || '$value' == 'null') return null;
    return int.tryParse('$value');
  }

  String _string(Object? value, {String fallback = ''}) {
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  JsonMap _typePayload() => {
        'name': _typeName.text.trim(),
        'slug': _typeSlug.text.trim().isEmpty ? null : _typeSlug.text.trim(),
        'description': _typeDescription.text.trim().isEmpty ? null : _typeDescription.text.trim(),
        'is_public': _typePublic,
        'is_active': _typeActive,
      };

  JsonMap _rulePayload() => {
        'club_membership_type_id': _ruleTypeId,
        'name': _ruleName.text.trim(),
        'amount': _ruleAmount.text.trim().replaceAll(',', '.'),
        'billing_interval': _ruleInterval,
        'valid_from': _ruleValidFrom.text.trim(),
        'valid_until': _ruleValidUntil.text.trim().isEmpty ? null : _ruleValidUntil.text.trim(),
        'age_min': _ruleAgeMin.text.trim().isEmpty ? null : int.tryParse(_ruleAgeMin.text.trim()),
        'age_max': _ruleAgeMax.text.trim().isEmpty ? null : int.tryParse(_ruleAgeMax.text.trim()),
        'is_active': _ruleActive,
        'notes': _ruleNotes.text.trim().isEmpty ? null : _ruleNotes.text.trim(),
      };

  void _showError(Object error) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Speichern fehlgeschlagen: $error')));
  }

  Future<bool> _saveSettings() async {
    setState(() => _saving = true);
    try {
      await AirmiusServicesScope.of(context).repositories.clubs.updateMembershipSettings(widget.club.id, {
        'membership_requests_enabled': _requestsEnabled,
        'member_pause_requests_enabled': _pauseRequestsEnabled,
        'membership_payment_methods': _paymentMethods.toList(),
        'membership_application_fields': _fieldModes,
        'membership_application_documents': _documents,
      });
      if (mounted) widget.onChanged();
      return true;
    } catch (error) {
      if (mounted) _showError(error);
      return false;
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<bool> _saveType() async {
    if (_typeName.text.trim().isEmpty) return false;
    setState(() => _saving = true);
    try {
      final repo = AirmiusServicesScope.of(context).repositories.clubs;
      if (_editingTypeId == null) {
        await repo.createMembershipType(widget.club.id, _typePayload());
      } else {
        await repo.updateMembershipType(widget.club.id, _editingTypeId!, _typePayload());
      }
      if (mounted) {
        _clearForms();
        widget.onChanged();
      }
      return true;
    } catch (error) {
      if (mounted) _showError(error);
      return false;
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<bool> _saveRule() async {
    if (_ruleName.text.trim().isEmpty || _ruleAmount.text.trim().isEmpty || _ruleValidFrom.text.trim().isEmpty) return false;
    setState(() => _saving = true);
    try {
      final repo = AirmiusServicesScope.of(context).repositories.clubs;
      if (_editingRuleId == null) {
        await repo.createContributionRule(widget.club.id, _rulePayload());
      } else {
        await repo.updateContributionRule(widget.club.id, _editingRuleId!, _rulePayload());
      }
      if (mounted) {
        _clearForms();
        widget.onChanged();
      }
      return true;
    } catch (error) {
      if (mounted) _showError(error);
      return false;
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _editType(JsonMap type) {
    setState(() {
      _editingTypeId = _intOrNull(type['id']);
      _typeName.text = _string(type['name']);
      _typeSlug.text = _string(type['slug']);
      _typeDescription.text = _string(type['description']);
      _typePublic = _bool(type['is_public']);
      _typeActive = _bool(type['is_active']);
    });
  }

  void _editRule(JsonMap rule) {
    setState(() {
      _editingRuleId = _intOrNull(rule['id']);
      _ruleTypeId = _intOrNull(rule['club_membership_type_id']);
      _ruleName.text = _string(rule['name']);
      _ruleAmount.text = _string(rule['amount']);
      _ruleInterval = _string(rule['billing_interval'], fallback: 'monthly');
      _ruleValidFrom.text = _string(rule['valid_from'], fallback: DateTime.now().toIso8601String().substring(0, 10));
      _ruleValidUntil.text = _string(rule['valid_until']);
      _ruleAgeMin.text = _string(rule['age_min']);
      _ruleAgeMax.text = _string(rule['age_max']);
      _ruleNotes.text = _string(rule['notes']);
      _ruleActive = _bool(rule['is_active']);
    });
  }

  void _newTypeForm() {
    setState(() {
      _editingTypeId = null;
      _typeName.clear();
      _typeSlug.clear();
      _typeDescription.clear();
      _typePublic = true;
      _typeActive = true;
    });
  }

  void _newRuleForm() {
    setState(() {
      _editingRuleId = null;
      _ruleTypeId = null;
      _ruleName.clear();
      _ruleAmount.clear();
      _ruleInterval = 'monthly';
      _ruleValidFrom.text = DateTime.now().toIso8601String().substring(0, 10);
      _ruleValidUntil.clear();
      _ruleAgeMin.clear();
      _ruleAgeMax.clear();
      _ruleNotes.clear();
      _ruleActive = true;
    });
  }

  String _intervalLabel(String value) {
    return switch (value) {
      'none' => 'Keine',
      'monthly' => 'Monatlich',
      'quarterly' => 'Quartal',
      'four_monthly' => 'Alle 4 Monate',
      'semi_yearly' => 'Halbjaehrlich',
      'yearly' => 'Jaehrlich',
      'once' => 'Einmalig',
      _ => value,
    };
  }

  String _paymentLabel(String value) {
    return switch (value) {
      'bank_transfer' => 'Ueberweisung',
      'cash' => 'Barzahlung',
      'sepa_debit' => 'SEPA-Lastschrift',
      _ => value,
    };
  }

  String _fieldModeLabel(String value) {
    return switch (value) {
      'required' => 'Pflicht',
      'optional' => 'Optional',
      'off' => 'Aus',
      _ => value,
    };
  }

  String _documentTypeLabel(String value) {
    return switch (value) {
      'privacy' => 'Datenschutz',
      'statutes' => 'Satzung',
      'rules' => 'Regeln',
      'fees' => 'Beitragsordnung',
      'sepa' => 'SEPA-Mandat',
      'other' => 'Sonstiges',
      _ => value,
    };
  }

  Future<void> _openDocumentDialog({int? index}) async {
    final existing = index == null ? const <String, dynamic>{} : _documents[index];
    final title = TextEditingController(text: _string(existing['title']));
    final url = TextEditingController(text: _string(existing['url']));
    final description = TextEditingController(text: _string(existing['description']));
    var type = _string(existing['type'], fallback: 'privacy');
    var source = _string(existing['file_id']).isNotEmpty ? 'file' : 'link';
    var visible = existing.isEmpty ? true : _bool(existing['is_visible']);
    var isRequired = _bool(existing['is_required']);
    var uploading = false;
    PlatformFile? pickedFile;
    JsonMap? uploadedFile = existing['file_id'] == null
        ? null
        : {
            'id': existing['file_id'],
            'display_name': existing['file_name'],
            'url': existing['url'],
          };

    final saved = await showDialog<JsonMap>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            title: Text(index == null ? 'Dokument hinzufuegen' : 'Dokument bearbeiten', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                DropdownButtonFormField<String>(
                  value: type,
                  dropdownColor: AirmiusColors.cardSoft,
                  decoration: const InputDecoration(labelText: 'Dokumenttyp'),
                  items: const ['privacy', 'statutes', 'rules', 'fees', 'sepa', 'other'].map((item) => DropdownMenuItem(value: item, child: Text(_documentTypeLabel(item)))).toList(),
                  onChanged: (value) => setDialogState(() => type = value ?? type),
                ),
                const SizedBox(height: 10),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'link', icon: Icon(Icons.link_outlined), label: Text('Link')),
                    ButtonSegment(value: 'file', icon: Icon(Icons.upload_file_outlined), label: Text('Datei')),
                  ],
                  selected: {source},
                  onSelectionChanged: (selection) => setDialogState(() => source = selection.first),
                ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Titel', hint: 'z. B. Beitragsordnung', controller: title),
                const SizedBox(height: 10),
                if (source == 'link')
                  AirmiusTextField(label: 'Link', hint: 'https://...', controller: url)
                else
                  _DocumentUploadBox(
                    fileName: uploadedFile == null ? pickedFile?.name : _string(uploadedFile!['display_name'], fallback: pickedFile?.name ?? 'Datei hochgeladen'),
                    uploading: uploading,
                    onPick: () async {
                      final result = await FilePicker.platform.pickFiles(withData: true);
                      final file = result?.files.single;
                      if (file == null) return;
                      setDialogState(() {
                        pickedFile = file;
                        title.text = title.text.trim().isEmpty ? file.name : title.text;
                        uploadedFile = null;
                      });
                    },
                    onUpload: pickedFile == null || uploading
                        ? null
                        : () async {
                            setDialogState(() => uploading = true);
                            try {
                              final uploaded = await _uploadMembershipDocument(pickedFile!);
                              setDialogState(() {
                                uploadedFile = uploaded;
                                url.text = _string(uploaded['url']);
                              });
                            } catch (error) {
                              if (mounted) _showError(error);
                            } finally {
                              setDialogState(() => uploading = false);
                            }
                          },
                  ),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Beschreibung', hint: 'optional', controller: description, maxLines: 2),
                const SizedBox(height: 10),
                _SettingsToggle(title: 'Im Antrag sichtbar', value: visible, onChanged: (value) => setDialogState(() => visible = value)),
                const SizedBox(height: 8),
                _SettingsToggle(title: 'Bestaetigung Pflicht', value: isRequired, onChanged: (value) => setDialogState(() => isRequired = value)),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context), child: const Text('Abbrechen')),
              FilledButton(
                onPressed: () {
                  if (source == 'file' && uploadedFile == null) return;
                  if (source == 'link' && title.text.trim().isEmpty && url.text.trim().isEmpty) return;
                  Navigator.pop(context, {
                    'id': _string(existing['id'], fallback: 'doc-${DateTime.now().millisecondsSinceEpoch}'),
                    'type': type,
                    'title': title.text.trim(),
                    'url': source == 'file' && uploadedFile != null ? _string(uploadedFile!['url']) : url.text.trim(),
                    'description': description.text.trim(),
                    'is_visible': visible,
                    'is_required': isRequired,
                    'file_id': source == 'file' && uploadedFile != null ? uploadedFile!['id'] : null,
                    'file_name': source == 'file' && uploadedFile != null ? _string(uploadedFile!['display_name'], fallback: pickedFile?.name ?? '') : null,
                  });
                },
                child: const Text('Speichern'),
              ),
            ],
          );
        },
      ),
    );

    title.dispose();
    url.dispose();
    description.dispose();

    if (saved == null) return;
    setState(() {
      if (index == null) {
        _documents.add(saved);
      } else {
        _documents[index] = saved;
      }
    });
  }

  Future<JsonMap> _uploadMembershipDocument(PlatformFile file) async {
    final services = AirmiusServicesScope.of(context);
    final base = Uri.parse(services.environment.apiBaseUrl);
    final path = '${base.path.endsWith('/') ? base.path : '${base.path}/'}api/v1/uploads';
    final request = http.MultipartRequest('POST', base.replace(path: path, query: null, fragment: null));

    request.headers.addAll({
      'Accept': 'application/json',
      'X-Airmius-Locale': services.environment.locale,
      if (services.authState.session?.token.isNotEmpty == true) 'Authorization': 'Bearer ${services.authState.session!.token}',
    });
    request.fields['scope'] = 'club';
    request.fields['club_id'] = '${widget.club.id}';

    if (file.bytes != null && file.bytes!.isNotEmpty) {
      request.files.add(http.MultipartFile.fromBytes('file', file.bytes!, filename: file.name, contentType: _contentTypeFor(file)));
    } else if (file.path != null && file.path!.trim().isNotEmpty) {
      request.files.add(await http.MultipartFile.fromPath('file', file.path!, filename: file.name, contentType: _contentTypeFor(file)));
    } else {
      throw const AirmiusApiException(statusCode: 0, body: 'Die ausgewaehlte Datei konnte nicht gelesen werden.', path: '/api/v1/uploads');
    }

    final response = await http.Response.fromStream(await request.send());
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(statusCode: response.statusCode, body: response.body, path: '/api/v1/uploads');
    }

    final decoded = jsonDecode(response.body);
    final json = decoded is JsonMap ? decoded : <String, dynamic>{'data': decoded};
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  MediaType _contentTypeFor(PlatformFile file) {
    final extension = (file.extension ?? file.name.split('.').last).toLowerCase();
    return switch (extension) {
      'jpg' || 'jpeg' => MediaType('image', 'jpeg'),
      'png' => MediaType('image', 'png'),
      'webp' => MediaType('image', 'webp'),
      'gif' => MediaType('image', 'gif'),
      'mp4' => MediaType('video', 'mp4'),
      'mov' => MediaType('video', 'quicktime'),
      'webm' => MediaType('video', 'webm'),
      'pdf' => MediaType('application', 'pdf'),
      'doc' => MediaType('application', 'msword'),
      'docx' => MediaType('application', 'vnd.openxmlformats-officedocument.wordprocessingml.document'),
      'xls' => MediaType('application', 'vnd.ms-excel'),
      'xlsx' => MediaType('application', 'vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
      _ => MediaType('application', 'octet-stream'),
    };
  }

  Future<void> _openApplicationSettingsSheet(List<JsonMap> applicationFields) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          void update(VoidCallback fn) {
            setState(fn);
            setSheetState(() {});
          }

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
              child: ConstrainedBox(
                constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * .88),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
                    const Text('Antrag & Felder', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 6),
                    const Text('Online-Anfragen, Zahlarten und sichtbare Felder kompakt bearbeiten.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700, height: 1.35)),
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        _SettingsToggle(title: 'Mitgliedsanfragen erlauben', value: _requestsEnabled, onChanged: (value) => update(() => _requestsEnabled = value)),
                        _SettingsToggle(title: 'Pausen-Anfragen erlauben', value: _pauseRequestsEnabled, onChanged: (value) => update(() => _pauseRequestsEnabled = value)),
                      ],
                    ),
                    const SizedBox(height: 14),
                    const Eyebrow('Zahlarten'),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final method in const ['bank_transfer', 'cash', 'sepa_debit'])
                          FilterChip(
                            selected: _paymentMethods.contains(method),
                            label: Text(_paymentLabel(method)),
                            onSelected: (selected) => update(() => selected ? _paymentMethods.add(method) : _paymentMethods.remove(method)),
                            selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                            backgroundColor: AirmiusColors.cardSoft,
                            side: BorderSide(color: _paymentMethods.contains(method) ? AirmiusColors.blue : AirmiusColors.border),
                            labelStyle: TextStyle(color: _paymentMethods.contains(method) ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                          ),
                      ],
                    ),
                    if (applicationFields.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      const Eyebrow('Mitgliedsantrag-Felder'),
                      const SizedBox(height: 8),
                      LayoutBuilder(builder: (context, constraints) {
                        final twoColumns = constraints.maxWidth >= 620;
                        final fieldWidth = twoColumns ? (constraints.maxWidth - 10) / 2 : constraints.maxWidth;
                        return Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            for (final field in applicationFields)
                              SizedBox(
                                width: fieldWidth,
                                child: DropdownButtonFormField<String>(
                                  value: (() {
                                    final current = _fieldModes[_string(field['key'])] ?? _string(field['mode'], fallback: 'off');
                                    return const ['required', 'optional', 'off'].contains(current) ? current : 'off';
                                  })(),
                                  dropdownColor: AirmiusColors.cardSoft,
                                  decoration: InputDecoration(labelText: _string(field['label'], fallback: _string(field['key'], fallback: 'Feld'))),
                                  items: const ['required', 'optional', 'off'].map((mode) => DropdownMenuItem(value: mode, child: Text(_fieldModeLabel(mode)))).toList(),
                                  onChanged: (value) {
                                    final key = _string(field['key']);
                                    if (key.isNotEmpty && value != null) update(() => _fieldModes[key] = value);
                                  },
                                ),
                              ),
                          ],
                        );
                      }),
                    ],
                    const SizedBox(height: 18),
                    Row(children: [
                      Expanded(child: TextButton(onPressed: () => Navigator.pop(sheetContext), child: const Text('Abbrechen'))),
                      const SizedBox(width: 10),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _saving
                              ? null
                              : () async {
                                  final saved = await _saveSettings();
                                  if (saved && mounted) Navigator.pop(sheetContext);
                                },
                          icon: const Icon(Icons.save_outlined),
                          label: Text(_saving ? 'Speichert...' : 'Speichern'),
                        ),
                      ),
                    ]),
                  ]),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _openTypeSheet({JsonMap? type}) async {
    if (type == null) {
      _newTypeForm();
    } else {
      _editType(type);
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          void update(VoidCallback fn) {
            setState(fn);
            setSheetState(() {});
          }

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
              child: ConstrainedBox(
                constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * .86),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
                    Text(_editingTypeId == null ? 'Mitgliedschaftstyp erstellen' : 'Mitgliedschaftstyp bearbeiten', style: const TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 14),
                    AirmiusTextField(label: 'Name', hint: 'z. B. Jugendmitglied', controller: _typeName),
                    const SizedBox(height: 10),
                    AirmiusTextField(label: 'Slug', hint: 'optional', controller: _typeSlug),
                    const SizedBox(height: 10),
                    AirmiusTextField(label: 'Beschreibung', hint: 'Beschreibung', controller: _typeDescription, maxLines: 2),
                    const SizedBox(height: 12),
                    Wrap(spacing: 10, runSpacing: 8, children: [
                      _SettingsToggle(title: 'Oeffentlich sichtbar', value: _typePublic, onChanged: (value) => update(() => _typePublic = value)),
                      _SettingsToggle(title: 'Aktiv', value: _typeActive, onChanged: (value) => update(() => _typeActive = value)),
                    ]),
                    const SizedBox(height: 18),
                    Row(children: [
                      Expanded(child: TextButton(onPressed: () => Navigator.pop(sheetContext), child: const Text('Abbrechen'))),
                      const SizedBox(width: 10),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _saving
                              ? null
                              : () async {
                                  final saved = await _saveType();
                                  if (saved && mounted) Navigator.pop(sheetContext);
                                },
                          icon: const Icon(Icons.badge_outlined),
                          label: Text(_saving ? 'Speichert...' : (_editingTypeId == null ? 'Erstellen' : 'Aktualisieren')),
                        ),
                      ),
                    ]),
                  ]),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _openRuleSheet({JsonMap? rule, required List<JsonMap> membershipTypes, required List<String> intervals}) async {
    if (rule == null) {
      _newRuleForm();
    } else {
      _editRule(rule);
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          void update(VoidCallback fn) {
            setState(fn);
            setSheetState(() {});
          }

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
              child: ConstrainedBox(
                constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * .9),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
                    Text(_editingRuleId == null ? 'Beitragsregel erstellen' : 'Beitragsregel bearbeiten', style: const TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 14),
                    DropdownButtonFormField<int>(
                      value: _ruleTypeId ?? 0,
                      dropdownColor: AirmiusColors.cardSoft,
                      decoration: const InputDecoration(labelText: 'Mitgliedschaftstyp'),
                      items: [
                        const DropdownMenuItem<int>(value: 0, child: Text('Alle Typen')),
                        for (final type in membershipTypes)
                          if (_intOrNull(type['id']) != null) DropdownMenuItem<int>(value: _intOrNull(type['id'])!, child: Text(_string(type['name'], fallback: 'Typ'))),
                      ],
                      onChanged: (value) => update(() => _ruleTypeId = value == null || value == 0 ? null : value),
                    ),
                    const SizedBox(height: 10),
                    AirmiusTextField(label: 'Regelname', hint: 'Regelname', controller: _ruleName),
                    const SizedBox(height: 10),
                    AirmiusTextField(label: 'Beitrag EUR', hint: '12.00', controller: _ruleAmount, keyboardType: TextInputType.number),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: intervals.contains(_ruleInterval) ? _ruleInterval : intervals.first,
                      dropdownColor: AirmiusColors.cardSoft,
                      decoration: const InputDecoration(labelText: 'Intervall'),
                      items: [for (final interval in intervals) DropdownMenuItem(value: interval, child: Text(_intervalLabel(interval)))],
                      onChanged: (value) => update(() => _ruleInterval = value ?? _ruleInterval),
                    ),
                    const SizedBox(height: 10),
                    LayoutBuilder(builder: (context, constraints) {
                      final twoColumns = constraints.maxWidth >= 520;
                      final fieldWidth = twoColumns ? (constraints.maxWidth - 10) / 2 : constraints.maxWidth;
                      return Wrap(spacing: 10, runSpacing: 10, children: [
                        SizedBox(width: fieldWidth, child: AirmiusTextField(label: 'Gueltig ab', hint: 'YYYY-MM-DD', controller: _ruleValidFrom)),
                        SizedBox(width: fieldWidth, child: AirmiusTextField(label: 'Gueltig bis', hint: 'optional', controller: _ruleValidUntil)),
                        SizedBox(width: fieldWidth, child: AirmiusTextField(label: 'Alter von', hint: 'optional', controller: _ruleAgeMin, keyboardType: TextInputType.number)),
                        SizedBox(width: fieldWidth, child: AirmiusTextField(label: 'Alter bis', hint: 'optional', controller: _ruleAgeMax, keyboardType: TextInputType.number)),
                      ]);
                    }),
                    const SizedBox(height: 10),
                    AirmiusTextField(label: 'Notiz', hint: 'optional', controller: _ruleNotes, maxLines: 2),
                    const SizedBox(height: 12),
                    _SettingsToggle(title: 'Regel aktiv', value: _ruleActive, onChanged: (value) => update(() => _ruleActive = value)),
                    const SizedBox(height: 18),
                    Row(children: [
                      Expanded(child: TextButton(onPressed: () => Navigator.pop(sheetContext), child: const Text('Abbrechen'))),
                      const SizedBox(width: 10),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _saving
                              ? null
                              : () async {
                                  final saved = await _saveRule();
                                  if (saved && mounted) Navigator.pop(sheetContext);
                                },
                          icon: const Icon(Icons.tune_outlined),
                          label: Text(_saving ? 'Speichert...' : (_editingRuleId == null ? 'Erstellen' : 'Aktualisieren')),
                        ),
                      ),
                    ]),
                  ]),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final membershipTypes = widget.management.membershipTypes;
    final contributionRules = widget.management.contributionRules;
    final applicationFields = widget.management.settings['membership_application_fields'] is List ? (widget.management.settings['membership_application_fields'] as List).whereType<JsonMap>().toList() : const <JsonMap>[];
    final intervals = widget.management.contributionIntervals.isEmpty
        ? const ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once']
        : widget.management.contributionIntervals;

    final requiredFields = applicationFields.where((field) {
      final key = _string(field['key']);
      final mode = _fieldModes[key] ?? _string(field['mode'], fallback: 'off');
      return mode == 'required';
    }).length;
    final activeTypes = membershipTypes.where((type) => _bool(type['is_active'])).length;
    final activeRules = contributionRules.where((rule) => _bool(rule['is_active'])).length;
    final requiredDocs = _documents.where((document) => _bool(document['is_required'])).length;

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(child: Eyebrow('Beitragsregeln')),
              StatusPill('$activeRules aktiv'),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final tab in const [
                _MembershipSectionTabData('application', 'Antrag', Icons.assignment_outlined),
                _MembershipSectionTabData('documents', 'Dokumente', Icons.description_outlined),
                _MembershipSectionTabData('types', 'Typen', Icons.badge_outlined),
                _MembershipSectionTabData('rules', 'Regeln', Icons.tune_outlined),
              ])
                _MembershipSectionTab(tab: tab, selected: _rulesTab == tab.value, onTap: () => setState(() => _rulesTab = tab.value)),
            ],
          ),
          const SizedBox(height: 14),
          if (_rulesTab == 'application') ...[
            Wrap(alignment: WrapAlignment.center, spacing: 10, runSpacing: 10, children: [
              _AdminMiniStat(icon: Icons.how_to_reg_outlined, title: 'Anfragen', value: _requestsEnabled ? 'An' : 'Aus'),
              _AdminMiniStat(icon: Icons.pause_circle_outline, title: 'Pausen', value: _pauseRequestsEnabled ? 'An' : 'Aus'),
              _AdminMiniStat(icon: Icons.payments_outlined, title: 'Zahlarten', value: '${_paymentMethods.length}'),
              _AdminMiniStat(icon: Icons.fact_check_outlined, title: 'Pflichtfelder', value: '$requiredFields'),
            ]),
            const SizedBox(height: 12),
            AirmiusButton(label: 'Antrag & Felder bearbeiten', icon: Icons.edit_note_outlined, onPressed: () => _openApplicationSettingsSheet(applicationFields)),
          ] else if (_rulesTab == 'documents') ...[
            Row(children: [
              Expanded(child: Text('${_documents.length} Dokument(e), $requiredDocs Pflicht', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))),
              IconButton(onPressed: () => _openDocumentDialog(), icon: const Icon(Icons.add_link_outlined), color: AirmiusColors.blue, tooltip: 'Dokument hinzufuegen'),
            ]),
            const SizedBox(height: 10),
            if (_documents.isEmpty)
              const _EmptyAdminHint(text: 'Noch keine Dokumente verknuepft.')
            else
              for (final entry in _documents.take(3).indexed)
                _MembershipDocumentLine(
                  document: entry.$2,
                  typeLabel: _documentTypeLabel(_string(entry.$2['type'], fallback: 'other')),
                  onEdit: () => _openDocumentDialog(index: entry.$1),
                  onDelete: () => setState(() => _documents.removeAt(entry.$1)),
                ),
            if (_documents.length > 3) Text('+ ${_documents.length - 3} weitere Dokumente', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Dokument hinzufuegen', icon: Icons.add_link_outlined, secondary: true, onPressed: () => _openDocumentDialog()),
              AirmiusButton(label: 'Dokumente speichern', icon: Icons.save_outlined, onPressed: _saving ? null : () { _saveSettings(); }),
            ]),
          ] else if (_rulesTab == 'types') ...[
            Wrap(alignment: WrapAlignment.center, spacing: 10, runSpacing: 10, children: [
              _AdminMiniStat(icon: Icons.badge_outlined, title: 'Typen', value: '${membershipTypes.length}'),
              _AdminMiniStat(icon: Icons.check_circle_outline, title: 'Aktiv', value: '$activeTypes'),
            ]),
            const SizedBox(height: 12),
            if (membershipTypes.isEmpty)
              const _EmptyAdminHint(text: 'Noch keine Mitgliedschaftstypen.')
            else
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final type in membershipTypes)
                  ActionChip(
                    label: Text(_string(type['name'], fallback: 'Typ')),
                    onPressed: () => _openTypeSheet(type: type),
                    avatar: Icon(_bool(type['is_active']) ? Icons.check_circle_outline : Icons.pause_circle_outline, color: AirmiusColors.blue, size: 18),
                    backgroundColor: AirmiusColors.cardSoft,
                    labelStyle: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
                    side: const BorderSide(color: AirmiusColors.border),
                  ),
              ]),
            const SizedBox(height: 12),
            AirmiusButton(label: 'Typ erstellen', icon: Icons.add_circle_outline, onPressed: () => _openTypeSheet()),
          ] else ...[
            Row(children: [
              Expanded(child: Text('${contributionRules.length} Regel(n), $activeRules aktiv', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))),
              IconButton(onPressed: () => _openRuleSheet(membershipTypes: membershipTypes, intervals: intervals), icon: const Icon(Icons.add_circle_outline), color: AirmiusColors.blue, tooltip: 'Regel erstellen'),
            ]),
            const SizedBox(height: 10),
            if (contributionRules.isEmpty)
              const _EmptyAdminHint(text: 'Noch keine Beitragsregeln.')
            else
              for (final rule in contributionRules.take(4))
                _ContributionRuleLine(
                  rule: rule,
                  intervalLabel: _intervalLabel,
                  onEdit: () => _openRuleSheet(rule: rule, membershipTypes: membershipTypes, intervals: intervals),
                ),
            if (contributionRules.length > 4) Text('+ ${contributionRules.length - 4} weitere Regeln', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            AirmiusButton(label: 'Regel erstellen', icon: Icons.tune_outlined, onPressed: () => _openRuleSheet(membershipTypes: membershipTypes, intervals: intervals)),
          ],
        ],
      ),
    );
  }
}

class _AdminMiniStat extends StatelessWidget {
  const _AdminMiniStat({required this.icon, required this.title, required this.value});

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 126),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: AirmiusColors.blue, size: 19),
        const SizedBox(width: 9),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900)),
        ]),
      ]),
    );
  }
}

class _EmptyAdminHint extends StatelessWidget {
  const _EmptyAdminHint({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AirmiusColors.bg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Text(text, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
    );
  }
}

class _SettingsToggle extends StatelessWidget {
  const _SettingsToggle({required this.title, required this.value, required this.onChanged});

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: () => onChanged(!value),
      child: Container(
        constraints: const BoxConstraints(minWidth: 170),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: value ? AirmiusColors.blue.withValues(alpha: .12) : AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: value ? AirmiusColors.blue : AirmiusColors.border),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(value ? Icons.check_box_outlined : Icons.check_box_outline_blank, color: value ? AirmiusColors.blue : AirmiusColors.muted, size: 20),
          const SizedBox(width: 8),
          Flexible(child: Text(title, style: TextStyle(color: value ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))),
        ]),
      ),
    );
  }
}

class _DocumentUploadBox extends StatelessWidget {
  const _DocumentUploadBox({
    required this.fileName,
    required this.uploading,
    required this.onPick,
    required this.onUpload,
  });

  final String? fileName;
  final bool uploading;
  final VoidCallback onPick;
  final VoidCallback? onUpload;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          const Icon(Icons.upload_file_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              fileName == null || fileName!.trim().isEmpty ? 'Noch keine Datei ausgewaehlt.' : fileName!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
            ),
          ),
        ]),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [
          OutlinedButton.icon(onPressed: uploading ? null : onPick, icon: const Icon(Icons.folder_open_outlined), label: const Text('Datei waehlen')),
          FilledButton.icon(onPressed: onUpload, icon: uploading ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.cloud_upload_outlined), label: Text(uploading ? 'Laedt hoch...' : 'Hochladen')),
        ]),
      ]),
    );
  }
}

class _MembershipDocumentLine extends StatelessWidget {
  const _MembershipDocumentLine({
    required this.document,
    required this.typeLabel,
    required this.onEdit,
    required this.onDelete,
  });

  final JsonMap document;
  final String typeLabel;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  String _text(Object? value, {String fallback = ''}) {
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  bool _bool(Object? value) => value == true || '$value'.toLowerCase() == 'true' || '$value' == '1';

  @override
  Widget build(BuildContext context) {
    final title = _text(document['title'], fallback: 'Dokument');
    final url = _text(document['url']);
    final isRequired = _bool(document['is_required']);
    final visible = _bool(document['is_visible']);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(isRequired ? Icons.verified_user_outlined : Icons.description_outlined, color: isRequired ? AirmiusColors.green : AirmiusColors.blue),
        const SizedBox(width: 10),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Text('$typeLabel - ${isRequired ? 'Pflicht' : 'Optional'} - ${visible ? 'sichtbar' : 'ausgeblendet'}', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
            if (url.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(url, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.blue, fontSize: 12, fontWeight: FontWeight.w700)),
            ],
          ]),
        ),
        IconButton(onPressed: onEdit, icon: const Icon(Icons.edit_outlined), color: AirmiusColors.blue, tooltip: 'Bearbeiten'),
        IconButton(onPressed: onDelete, icon: const Icon(Icons.delete_outline), color: AirmiusColors.red, tooltip: 'Entfernen'),
      ]),
    );
  }
}

class _ContributionRuleLine extends StatelessWidget {
  const _ContributionRuleLine({required this.rule, required this.intervalLabel, required this.onEdit});

  final JsonMap rule;
  final String Function(String value) intervalLabel;
  final VoidCallback onEdit;

  String _text(Object? value, {String fallback = ''}) {
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  @override
  Widget build(BuildContext context) {
    final active = rule['is_active'] == true || '${rule['is_active']}'.toLowerCase() == 'true' || '${rule['is_active']}' == '1';
    final type = _text(rule['membership_type_name'], fallback: 'Alle Typen');
    final amount = _text(rule['amount'], fallback: '0');
    final interval = intervalLabel(_text(rule['billing_interval'], fallback: 'monthly'));
    final validFrom = _text(rule['valid_from'], fallback: '-');
    final validUntil = _text(rule['valid_until'], fallback: 'offen');

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(Icons.tune_outlined, color: active ? AirmiusColors.green : AirmiusColors.muted),
        const SizedBox(width: 10),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(_text(rule['name'], fallback: 'Beitragsregel'), style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Text('$type - $amount EUR - $interval', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
            const SizedBox(height: 2),
            Text('Gilt $validFrom bis $validUntil', style: const TextStyle(color: AirmiusColors.mutedSoft, fontSize: 12, fontWeight: FontWeight.w700)),
          ]),
        ),
        TextButton.icon(onPressed: onEdit, icon: const Icon(Icons.edit_outlined, size: 18), label: const Text('Bearbeiten')),
      ]),
    );
  }
}

class _ClubMembershipHeader extends StatelessWidget {
  const _ClubMembershipHeader({required this.onMembershipOps});

  final VoidCallback onMembershipOps;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('VEREINSBEREICH'),
          const SizedBox(height: 8),
          const Text(
            'Mitglieder & Beiträge',
            style: TextStyle(color: AirmiusColors.text, fontSize: 21, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          const Text(
            'Mitgliederdaten, Beitragssätze, Rechnungen, SEPA, DATEV und Import wie in der Web-App als native Flutter-Ansicht.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Mitglied importieren',
                icon: Icons.upload_file_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Mitglied importieren',
                  body: 'Import-Workflow für bestehende Vereinsmitglieder, CSV und externe Mitglieder.',
                  status: 'Import',
                  icon: Icons.upload_file_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Rechnung erstellen',
                icon: Icons.receipt_long_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Rechnung erstellen',
                  body: 'Native Vorbereitung für Mitgliedsbeitrag, Fälligkeit, Zahlungsstatus und Erinnerung.',
                  status: 'Rechnung',
                  icon: Icons.receipt_long_outlined,
                ),
              ),
              AirmiusButton(
                label: 'SEPA export',
                icon: Icons.account_balance_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'SEPA export',
                  body: 'SEPA-Mandate prüfen und Export für Sammellauf vorbereiten.',
                  status: 'SEPA',
                  icon: Icons.account_balance_outlined,
                ),
              ),
              AirmiusButton(label: 'Membership Ops', icon: Icons.tune_outlined, secondary: true, onPressed: onMembershipOps),
            ],
          ),
        ],
      ),
    );
  }
}

class _MembershipKpi {
  const _MembershipKpi({required this.title, required this.value, required this.detail, this.icon, this.accent});

  final String title;
  final String value;
  final String detail;
  final IconData? icon;
  final Color? accent;
}

class _MembershipKpiGrid extends StatelessWidget {
  const _MembershipKpiGrid({required this.cards});

  final List<_MembershipKpi> cards;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final maxColumns = constraints.maxWidth >= 720 ? 4 : 2;
        final columns = cards.length < maxColumns ? cards.length : maxColumns;
        const gap = 10.0;
        const cardHeight = 150.0;
        final width = (constraints.maxWidth - gap * (columns - 1)) / columns;

        return Wrap(
          alignment: WrapAlignment.center,
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final card in cards) SizedBox(width: width, height: cardHeight, child: _MembershipKpiCard(card: card)),
          ],
        );
      },
    );
  }
}

class _MembershipTreasuryKpiGrid extends StatelessWidget {
  const _MembershipTreasuryKpiGrid({required this.cards});

  final List<_MembershipKpi> cards;

  @override
  Widget build(BuildContext context) {
    if (cards.length < 5) return _MembershipKpiGrid(cards: cards);

    return LayoutBuilder(
      builder: (context, constraints) {
        const gap = 10.0;

        Widget tile(_MembershipKpi card, double width, double height) {
          return SizedBox(width: width, height: height, child: _MembershipKpiCard(card: card));
        }

        if (constraints.maxWidth >= 720) {
          final topWidth = (constraints.maxWidth - gap * 2) / 3;
          final bottomWidth = (constraints.maxWidth - gap) / 2;

          return Column(
            children: [
              Row(
                children: [
                  tile(cards[0], topWidth, 150),
                  const SizedBox(width: gap),
                  tile(cards[1], topWidth, 150),
                  const SizedBox(width: gap),
                  tile(cards[2], topWidth, 150),
                ],
              ),
              const SizedBox(height: gap),
              Row(
                children: [
                  tile(cards[3], bottomWidth, 138),
                  const SizedBox(width: gap),
                  tile(cards[4], bottomWidth, 138),
                ],
              ),
            ],
          );
        }

        if (constraints.maxWidth >= 520) {
          final halfWidth = (constraints.maxWidth - gap) / 2;

          return Column(
            children: [
              Row(
                children: [
                  tile(cards[0], halfWidth, 150),
                  const SizedBox(width: gap),
                  tile(cards[1], halfWidth, 150),
                ],
              ),
              const SizedBox(height: gap),
              tile(cards[2], constraints.maxWidth, 138),
              const SizedBox(height: gap),
              Row(
                children: [
                  tile(cards[3], halfWidth, 138),
                  const SizedBox(width: gap),
                  tile(cards[4], halfWidth, 138),
                ],
              ),
            ],
          );
        }

        return Column(
          children: [
            for (var index = 0; index < cards.length; index++) ...[
              tile(cards[index], constraints.maxWidth, 138),
              if (index != cards.length - 1) const SizedBox(height: gap),
            ],
          ],
        );
      },
    );
  }
}

class _MembershipKpiCard extends StatelessWidget {
  const _MembershipKpiCard({required this.card});

  final _MembershipKpi card;

  @override
  Widget build(BuildContext context) {
    final accent = card.accent;
    final borderColor = accent == null ? AirmiusColors.border : Color.lerp(AirmiusColors.border, accent, 0.55) ?? AirmiusColors.border;
    final backgroundColor = accent == null ? AirmiusColors.card : Color.lerp(AirmiusColors.card, accent, 0.08) ?? AirmiusColors.card;

    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  card.title.toUpperCase(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: .2),
                ),
              ),
              if (card.icon != null) ...[
                const SizedBox(width: 8),
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: (accent ?? AirmiusColors.borderStrong).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(color: (accent ?? AirmiusColors.borderStrong).withValues(alpha: 0.35)),
                  ),
                  child: Icon(card.icon, color: accent ?? AirmiusColors.muted, size: 18),
                ),
              ],
            ],
          ),
          const SizedBox(height: 12),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(card.value, style: const TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
          ),
          const SizedBox(height: 8),
          Text(card.detail, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.25)),
        ],
      ),
    );
  }
}

class _MemberEntry {
  const _MemberEntry({required this.id, required this.name, required this.email, required this.type, required this.number, required this.balance, required this.sepa});

  final int id;
  final String name;
  final String email;
  final String type;
  final String number;
  final String balance;
  final bool sepa;
}

class _InvoiceEntry {
  const _InvoiceEntry({required this.id, required this.title, required this.person, required this.amount, required this.status, required this.color});

  final int id;
  final String title;
  final String person;
  final String amount;
  final String status;
  final Color color;
}

class _PaymentEntry {
  const _PaymentEntry({
    required this.id,
    required this.userId,
    required this.invoiceId,
    required this.purpose,
    required this.title,
    required this.person,
    required this.amount,
    required this.amountInput,
    required this.method,
    required this.methodKey,
    required this.date,
    required this.paidAtInput,
    required this.reference,
    required this.notes,
    required this.detail,
    required this.icon,
    required this.color,
  });

  final int id;
  final int userId;
  final int invoiceId;
  final String purpose;
  final String title;
  final String person;
  final String amount;
  final String amountInput;
  final String method;
  final String methodKey;
  final String date;
  final String paidAtInput;
  final String reference;
  final String notes;
  final String detail;
  final IconData icon;
  final Color color;
}

class _FinanceEntry {
  const _FinanceEntry({
    required this.id,
    required this.type,
    required this.account,
    required this.title,
    required this.category,
    required this.amount,
    required this.amountInput,
    required this.date,
    required this.bookedOnInput,
    required this.reference,
    required this.description,
    required this.detail,
    required this.icon,
    required this.color,
  });

  final int id;
  final String type;
  final String account;
  final String title;
  final String category;
  final String amount;
  final String amountInput;
  final String date;
  final String bookedOnInput;
  final String reference;
  final String description;
  final String detail;
  final IconData icon;
  final Color color;
}

class _BankEntry {
  const _BankEntry({required this.title, required this.detail});

  final String title;
  final String detail;
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({required this.member});

  final _MemberEntry member;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                AirmiusAvatar(member.name),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(member.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 3),
                      Text(member.email, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w600)),
                    ],
                  ),
                ),
                StatusPill(member.type, color: member.type == 'Extern' ? AirmiusColors.amber : AirmiusColors.blue),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusPill(member.number, color: AirmiusColors.muted),
                StatusPill(member.sepa ? 'SEPA Mandat' : 'Überweisung', color: member.sepa ? AirmiusColors.green : AirmiusColors.amber),
                StatusPill(member.balance == '0,00 EUR' ? 'Ausgeglichen' : 'Offen ${member.balance}', color: member.balance == '0,00 EUR' ? AirmiusColors.green : AirmiusColors.red),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _InvoiceLine extends StatelessWidget {
  const _InvoiceLine({required this.invoice});

  final _InvoiceEntry invoice;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
            child: const Icon(Icons.receipt_long_outlined, color: AirmiusColors.blue),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(invoice.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 2),
                Text('${invoice.person} - ${invoice.amount}', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
          StatusPill(invoice.status, color: invoice.color),
        ],
      ),
    );
  }
}

class _PaymentLine extends StatelessWidget {
  const _PaymentLine({required this.payment, required this.onEdit});

  final _PaymentEntry payment;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: payment.color.withValues(alpha: 0.45))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: payment.color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(14), border: Border.all(color: payment.color.withValues(alpha: 0.45))),
            child: Icon(payment.icon, color: payment.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(payment.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    ),
                    const SizedBox(width: 8),
                    Text(payment.amount, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(width: 4),
                    IconButton(
                      tooltip: 'Bearbeiten',
                      visualDensity: VisualDensity.compact,
                      onPressed: onEdit,
                      icon: Icon(Icons.edit_outlined, color: payment.color, size: 20),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(payment.person, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(payment.method, color: payment.color),
                    StatusPill(payment.date, color: AirmiusColors.muted),
                  ],
                ),
                if (payment.detail.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(payment.detail, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FinanceEntryLine extends StatelessWidget {
  const _FinanceEntryLine({required this.entry, required this.onEdit});

  final _FinanceEntry entry;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final typeLabel = entry.type == 'income' ? 'Einnahme' : 'Ausgabe';
    final accountLabel = entry.account == 'bank' ? 'Bank' : 'Bar';
    final amountPrefix = entry.type == 'income' ? '+' : '-';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: entry.color.withValues(alpha: 0.45))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: entry.color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(14), border: Border.all(color: entry.color.withValues(alpha: 0.45))),
            child: Icon(entry.icon, color: entry.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(entry.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    ),
                    const SizedBox(width: 8),
                    Text('$amountPrefix ${entry.amount}', style: TextStyle(color: entry.color, fontWeight: FontWeight.w900)),
                    const SizedBox(width: 4),
                    IconButton(
                      tooltip: 'Bearbeiten',
                      visualDensity: VisualDensity.compact,
                      onPressed: onEdit,
                      icon: Icon(Icons.edit_outlined, color: entry.color, size: 20),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(typeLabel, color: entry.color),
                    StatusPill(accountLabel, color: AirmiusColors.blue),
                    StatusPill(entry.date, color: AirmiusColors.muted),
                  ],
                ),
                if (entry.detail.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(entry.detail, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BankLine extends StatelessWidget {
  const _BankLine({required this.entry});

  final _BankEntry entry;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          const Icon(Icons.sync_alt_outlined, color: AirmiusColors.green),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(entry.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(entry.detail, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ExportTile extends StatelessWidget {
  const _ExportTile({required this.icon, required this.title, required this.subtitle, required this.status});

  final IconData icon;
  final String title;
  final String subtitle;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(15), border: Border.all(color: AirmiusColors.border)),
            child: Icon(icon, color: AirmiusColors.blue),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}
