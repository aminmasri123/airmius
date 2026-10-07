// ignore_for_file: unused_element
// ignore_for_file: deprecated_member_use

import 'dart:async';
import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../widgets/country_field.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import '../core/airmius_api_client.dart';
import '../core/sepa_fee_labels.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/sepa_batch_labels.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'club_request_inbox_screen.dart';
import 'club_access_management_screen.dart';
import 'club_sepa_batches_screen.dart';

class ClubMembershipManagementScreen extends StatefulWidget {
  const ClubMembershipManagementScreen({
    super.key,
    this.initialClubId,
    this.initialSection = 'members',
  });

  final int? initialClubId;
  final String initialSection;

  @override
  State<ClubMembershipManagementScreen> createState() =>
      _ClubMembershipManagementScreenState();
}

class _ClubMembershipManagementScreenState
    extends State<ClubMembershipManagementScreen> {
  String _filter = 'Alle';
  String _memberQuery = '';
  String _period = 'all';
  String _section = 'members';
  final TextEditingController _memberQueryController = TextEditingController();
  int? _selectedClubId;
  Future<_ManagedMembershipData?>? _clubFuture;
  _ManagedMembershipData? _currentData;
  final _inviteNameController = TextEditingController();
  final _inviteEmailController = TextEditingController();
  final _invitePhoneController = TextEditingController();
  final _inviteStreetController = TextEditingController();
  final _inviteHouseNumberController = TextEditingController();
  final _invitePostalCodeController = TextEditingController();
  final _inviteCityController = TextEditingController();
  final _inviteCountryController = TextEditingController();
  final _inviteMemberNumberController = TextEditingController();
  final _inviteContributionController = TextEditingController();
  final _inviteNextInvoiceController = TextEditingController();
  final _inviteIbanController = TextEditingController();
  final _inviteBicController = TextEditingController();
  final _inviteMandateController = TextEditingController();
  final _inviteMandateDateController = TextEditingController();
  final _inviteJoinedOnController = TextEditingController();
  final _inviteMembershipEndsOnController = TextEditingController();
  final _inviteNotesController = TextEditingController();
  String _inviteRole = 'member';
  String _inviteStatus = 'active';
  String _inviteContributionInterval = 'none';
  int _inviteContributionPayerId = 0;
  int? _inviteMembershipTypeId;
  bool _inviteSepaActive = false;
  bool _sendingInvitation = false;
  bool _savedViewsLoaded = false;
  bool _savedViewsLoading = false;
  bool _bulkActionRunning = false;
  final Set<String> _selectedMemberKeys = <String>{};
  List<AirmiusSavedView> _memberSavedViews = const [];
  List<AirmiusSavedView> _invoiceSavedViews = const [];

  String _tr(String key) => AirmiusScope.of(context).t(key);

  String get _pageTitle => _section == 'payments'
      ? _tr('membership.tab.finance')
      : _tr('membership.title');

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
    'Turniere & Wettkämpfe',
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

  static const _clubRoleKeys = [
    'owner',
    'admin',
    'manager',
    'academy_manager',
    'financial_controller',
    'trainer',
    'member',
  ];

  static const _financeCategoryTranslationKeys = {
    'Miete & Hallenkosten': 'membership.category.rent',
    'Material & Ausrüstung': 'membership.category.equipment',
    'Trikots & Kleidung': 'membership.category.clothing',
    'Trainerhonorare': 'membership.category.coachFees',
    'Schiedsrichter & Gebühren': 'membership.category.refereeFees',
    'Verbandsbeiträge': 'membership.category.associationFees',
    'Versicherungen': 'membership.category.insurance',
    'Reisekosten & Fahrtkosten': 'membership.category.travel',
    'Verpflegung': 'membership.category.catering',
    'Turniere & Wettkämpfe': 'membership.category.competitions',
    'Lizenzen & Software': 'membership.category.software',
    'Marketing & Werbung': 'membership.category.marketing',
    'Büro & Verwaltung': 'membership.category.office',
    'Bankgebühren': 'membership.category.bankFees',
    'Steuern & Abgaben': 'membership.category.taxes',
    'Reparatur & Wartung': 'membership.category.maintenance',
    'Reinigung': 'membership.category.cleaning',
    'Energie & Nebenkosten': 'membership.category.utilities',
    'Telefon & Internet': 'membership.category.telecom',
    'Fortbildung': 'membership.category.training',
    'Veranstaltungskosten': 'membership.category.events',
    'Sonstige Ausgabe': 'membership.category.otherExpense',
    'Mitgliedsbeiträge': 'membership.category.memberFees',
    'Aufnahmegebühren': 'membership.category.admissionFees',
    'Spenden': 'membership.category.donations',
    'Sponsoring': 'membership.category.sponsoring',
    'Zuschüsse & Fördermittel': 'membership.category.grants',
    'Kursgebühren': 'membership.category.courseFees',
    'Event-Einnahmen': 'membership.category.eventIncome',
    'Ticketverkauf': 'membership.category.tickets',
    'Merchandise': 'membership.category.merchandise',
    'Vermietung': 'membership.category.rental',
    'Rückerstattung': 'membership.category.refund',
    'Zinsen': 'membership.category.interest',
    'Sonstige Einnahme': 'membership.category.otherIncome',
  };

  @override
  void initState() {
    super.initState();
    _selectedClubId = widget.initialClubId;
    _section = widget.initialSection;
  }

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
    final total = invoices
        .where(
          (invoice) => [
            'open',
            'overdue',
            'awaiting_transfer',
          ].contains(invoice.statusKey),
        )
        .fold<int>(
          0,
          (sum, invoice) => sum + _parseEuroCents(invoice.outstandingAmount),
        );
    return _formatEuro(total);
  }

  Map<int, String> _openBalancesByMember(List<_InvoiceEntry> invoices) {
    final totals = <int, int>{};
    for (final invoice in invoices) {
      if (invoice.userId <= 0 ||
          ![
            'open',
            'overdue',
            'awaiting_transfer',
          ].contains(invoice.statusKey)) {
        continue;
      }
      totals.update(
        invoice.userId,
        (value) => value + _parseEuroCents(invoice.outstandingAmount),
        ifAbsent: () => _parseEuroCents(invoice.outstandingAmount),
      );
    }
    return totals.map(
      (memberId, cents) => MapEntry(memberId, _formatEuro(cents)),
    );
  }

  List<_MemberEntry> _membersWithInvoiceBalances(
    List<_MemberEntry> members,
    List<_InvoiceEntry> invoices,
  ) {
    final balances = _openBalancesByMember(invoices);
    return members
        .map(
          (member) =>
              member.copyWith(balance: balances[member.id] ?? _formatEuro(0)),
        )
        .toList();
  }

  String _recurringTotalFromMembers(List<_MemberEntry> members) {
    final total = members.fold<double>(0, (sum, member) {
      final interval = _stringFromJson(member.membership, [
        'contribution_interval',
      ], fallback: 'none');
      if (interval == 'none' || interval.isEmpty) return sum;
      return sum +
          (_parseMoneyNumber(member.membership['contribution_amount']) ?? 0);
    });
    return _formatEuroAmount(total);
  }

  String _cashBalanceFromPayments(List<_PaymentEntry> payments) {
    final total = payments
        .where(
          (payment) =>
              payment.statusKey == 'paid' && payment.methodKey == 'cash',
        )
        .fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount));
    return _formatEuro(total);
  }

  String _bankBalanceFromPayments(List<_PaymentEntry> payments) {
    final total = payments
        .where(
          (payment) =>
              payment.statusKey == 'paid' &&
              (payment.methodKey == 'bank_transfer' ||
                  payment.methodKey == 'sepa_debit'),
        )
        .fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount));
    return _formatEuro(total);
  }

  String _totalBalanceFromPayments(List<_PaymentEntry> payments) {
    final total = payments
        .where((payment) => payment.statusKey == 'paid')
        .fold<int>(0, (sum, payment) => sum + _parseEuroCents(payment.amount));
    return _formatEuro(total);
  }

  bool _boolFromAny(Object? value) {
    if (value is bool) return value;
    final normalized = '$value'.toLowerCase();
    return normalized == '1' || normalized == 'true' || normalized == 'yes';
  }

  String _stringFromJson(
    JsonMap json,
    List<String> keys, {
    String fallback = '',
  }) {
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

  int? _intOrNull(Object? value) {
    if (value == null || '$value'.trim().isEmpty || '$value' == 'null') {
      return null;
    }
    return int.tryParse('$value');
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
    if (role == 'youth' || role == 'junior' || status == 'youth') {
      return 'Jugend';
    }
    return 'Aktiv';
  }

  List<_MemberEntry> _membersFromManagement(AirmiusClubManagement? management) {
    final members = management?.members ?? const <AirmiusClubMember>[];
    final linkedEntries = members
        .map(
          (member) => _MemberEntry(
            id: member.id,
            externalId: null,
            isExternal: false,
            duplicateCandidate: null,
            name: member.name,
            email: member.email,
            role: member.role ?? 'member',
            status: member.status ?? 'active',
            membership: {
              ...member.membership,
              'phone': member.phone,
              'country': member.country,
              'street': member.street,
              'house_number': member.houseNumber,
              'postal_code': member.postalCode,
              'city': member.city,
              'athlete_license_number': member.licenseNumber,
              'athlete_license_valid_until': member.licenseValidUntil,
            },
            type: _memberType(member),
            number:
                (member.memberNumber != null && member.memberNumber!.isNotEmpty)
                ? member.memberNumber!
                : 'ID-${member.id}',
            balance: _moneyFromValue(
              member.membership['balance'] ??
                  member.membership['open_balance'] ??
                  0,
            ),
            sepa: _boolFromAny(
              member.membership['sepa_mandate_active'] ??
                  member.membership['sepa_ready'] ??
                  member.membership['has_sepa_mandate'],
            ),
          ),
        )
        .toList();
    final externalEntries = (management?.externalMembers ?? const <JsonMap>[])
        .map((member) {
          final externalId = _intFromAny(member['id']);
          final membership = <String, dynamic>{
            'role': _stringFromJson(member, ['role'], fallback: 'member'),
            'status': _stringFromJson(member, [
              'membership_status',
            ], fallback: 'active'),
            'email': member['email'],
            'name': member['name'],
            'phone': member['phone'],
            'country': member['country'],
            'street': member['street'],
            'house_number': member['house_number'],
            'postal_code': member['postal_code'],
            'city': member['city'],
            'club_membership_type_id': member['club_membership_type_id'],
            'family_group_key': member['family_group_key'],
            'contribution_payer_user_id': member['contribution_payer_user_id'],
            'member_number': member['member_number'],
            'athlete_license_number': member['athlete_license_number'],
            'athlete_license_valid_until':
                member['athlete_license_valid_until'],
            'contribution_amount': member['contribution_amount'],
            'contribution_interval': member['contribution_interval'],
            'contribution_next_invoice_on':
                member['contribution_next_invoice_on'],
            'sepa_iban': member['sepa_iban'],
            'sepa_bic': member['sepa_bic'],
            'sepa_mandate_reference': member['sepa_mandate_reference'],
            'sepa_mandate_signed_on': member['sepa_mandate_signed_on'],
            'sepa_mandate_active': member['sepa_mandate_active'],
            'joined_on': member['joined_on'],
            'membership_ends_on': member['membership_ends_on'],
            'membership_notes': member['membership_notes'],
          };
          return _MemberEntry(
            id: -externalId,
            externalId: externalId,
            isExternal: true,
            duplicateCandidate: member['duplicate_candidate'] is Map
                ? Map<String, dynamic>.from(
                    member['duplicate_candidate'] as Map,
                  )
                : null,
            name: _stringFromJson(member, [
              'name',
              'email',
            ], fallback: 'Externes Mitglied'),
            email: _stringFromJson(member, ['email']),
            role: _stringFromJson(member, ['role'], fallback: 'member'),
            status: _stringFromJson(member, [
              'membership_status',
            ], fallback: 'active'),
            membership: membership,
            type: 'Extern',
            number: _stringFromJson(member, [
              'member_number',
            ], fallback: 'ID-$externalId'),
            balance: _moneyFromValue(
              member['balance'] ?? member['open_balance'] ?? 0,
            ),
            sepa: _boolFromAny(member['sepa_mandate_active']),
          );
        })
        .toList();
    return [...linkedEntries, ...externalEntries];
  }

  String _memberSelectionKey(_MemberEntry member) => member.isExternal
      ? 'external:${member.externalId}'
      : 'member:${member.id}';

  List<_MemberEntry> _selectedMembersFrom(List<_MemberEntry> members) => members
      .where(
        (member) => _selectedMemberKeys.contains(_memberSelectionKey(member)),
      )
      .toList();

  void _toggleMemberSelection(_MemberEntry member, bool selected) {
    final key = _memberSelectionKey(member);
    setState(() {
      if (selected) {
        _selectedMemberKeys.add(key);
      } else {
        _selectedMemberKeys.remove(key);
      }
    });
  }

  void _selectVisibleMembers(List<_MemberEntry> members) {
    setState(() {
      for (final member in members) {
        _selectedMemberKeys.add(_memberSelectionKey(member));
      }
    });
  }

  JsonMap _memberStatusPayload(_MemberEntry member, String status) {
    final membership = member.membership;
    final role = _clubRoleKeys.contains(member.role) ? member.role : 'member';
    final payload = <String, dynamic>{
      'role': role,
      'roles': [role],
      'membership_status': status,
      'club_membership_type_id': _nullableInt(
        membership['club_membership_type_id'],
      ),
      'family_group_key': _emptyToNull(membership['family_group_key']),
      'contribution_payer_user_id': _nullableInt(
        membership['contribution_payer_user_id'],
      ),
      'member_number': _emptyToNull(membership['member_number']),
      'athlete_license_number': _emptyToNull(
        membership['athlete_license_number'],
      ),
      'athlete_license_valid_until': _emptyToNull(
        membership['athlete_license_valid_until'],
      ),
      'contribution_amount': membership['contribution_amount'],
      'contribution_interval':
          _emptyToNull(membership['contribution_interval']) ?? 'none',
      'payment_method': _emptyToNull(membership['payment_method']),
      'contribution_next_invoice_on': _emptyToNull(
        membership['contribution_next_invoice_on'],
      ),
      'sepa_iban': _emptyToNull(membership['sepa_iban']),
      'sepa_bic': _emptyToNull(membership['sepa_bic']),
      'sepa_mandate_reference': _emptyToNull(
        membership['sepa_mandate_reference'],
      ),
      'sepa_mandate_signed_on': _emptyToNull(
        membership['sepa_mandate_signed_on'],
      ),
      'sepa_mandate_active': _boolFromAny(membership['sepa_mandate_active']),
      'joined_on': _emptyToNull(membership['joined_on']),
      'membership_ends_on': _emptyToNull(membership['membership_ends_on']),
      'membership_notes': _emptyToNull(membership['membership_notes']),
    };
    if (member.isExternal) {
      payload.addAll({
        'name': _emptyToNull(membership['name']) ?? member.name,
        'email': member.email,
        'phone': _emptyToNull(membership['phone']),
        'country': _emptyToNull(membership['country'])?.toUpperCase(),
        'street': _emptyToNull(membership['street']),
        'house_number': _emptyToNull(membership['house_number']),
        'postal_code': _emptyToNull(membership['postal_code']),
        'city': _emptyToNull(membership['city']),
      });
    }
    return payload;
  }

  int? _nullableInt(Object? value) {
    final parsed = _intFromAny(value);
    return parsed == 0 ? null : parsed;
  }

  String? _emptyToNull(Object? value) {
    final string = value?.toString().trim() ?? '';
    return string.isEmpty ? null : string;
  }

  List<_InvoiceEntry> _invoicesFromManagement(
    AirmiusClubManagement? management,
  ) {
    final invoices = management?.invoices ?? const <JsonMap>[];
    return invoices.map((invoice) {
      final user = invoice['user'];
      final member = invoice['member'];
      final person = user is JsonMap
          ? _stringFromJson(user, ['name', 'email'], fallback: 'Mitglied')
          : member is JsonMap
          ? _stringFromJson(member, ['name', 'email'], fallback: 'Mitglied')
          : _stringFromJson(invoice, [
              'member_name',
              'user_name',
              'recipient_name',
            ], fallback: 'Mitglied');
      final rawStatus = _stringFromJson(invoice, [
        'status',
        'payment_status',
      ], fallback: 'open').toLowerCase();
      final fallbackStatus = switch (rawStatus) {
        'paid' || 'bezahlt' || 'settled' => _tr('membership.paid'),
        'overdue' => _tr('membership.overdue'),
        'cancelled' => _tr('membership.cancelled'),
        'waived' => _tr('membership.waived'),
        _ => _tr('membership.open'),
      };
      final status = _stringFromJson(invoice, [
        'status_label',
      ], fallback: fallbackStatus);
      final color = switch (rawStatus) {
        'paid' || 'bezahlt' || 'settled' => AirmiusColors.green,
        'overdue' => AirmiusColors.red,
        'cancelled' => airmiusMutedColor(context),
        'waived' => AirmiusColors.green,
        _ => AirmiusColors.amber,
      };
      return _InvoiceEntry(
        id: _intFromAny(invoice['id']),
        userId: _intFromAny(
          invoice['user_id'] ??
              (user is JsonMap ? user['id'] : null) ??
              (member is JsonMap ? member['id'] : null),
        ),
        title: _stringFromJson(invoice, [
          'title',
          'number',
          'invoice_number',
        ], fallback: 'Rechnung'),
        person: person,
        amount: _moneyFromValue(
          invoice['amount'] ??
              invoice['amount_due'] ??
              invoice['total'] ??
              invoice['total_amount'],
        ),
        outstandingAmount: _moneyFromValue(
          invoice['outstanding_amount'] ?? invoice['amount'],
        ),
        overpaidAmount: _moneyFromValue(invoice['overpaid_amount'] ?? 0),
        isPartiallyPaid: invoice['is_partially_paid'] == true,
        hasOverpayment:
            (double.tryParse('${invoice['overpaid_amount']}') ?? 0) > 0,
        statusKey: rawStatus,
        status: status,
        color: color,
        date: DateTime.tryParse(
          _stringFromJson(invoice, [
            'issued_at',
            'created_at',
            'billing_period_start',
          ]),
        ),
      );
    }).toList();
  }

  bool _invoiceMatchesPeriod(_InvoiceEntry invoice, DateTime now) {
    if (_period == 'all') return true;
    final date = invoice.date?.toLocal();
    if (date == null) return false;
    return switch (_period) {
      'month' => date.year == now.year && date.month == now.month,
      'previousMonth' =>
        date.year == DateTime(now.year, now.month - 1).year &&
            date.month == DateTime(now.year, now.month - 1).month,
      'year' => date.year == now.year,
      _ => true,
    };
  }

  List<_PaymentEntry> _paymentsFromManagement(
    AirmiusClubManagement? management,
    List<_MemberEntry> members,
  ) {
    final payments = management?.payments ?? const <JsonMap>[];
    return payments.map((payment) {
      final purpose = _stringFromJson(payment, [
        'purpose',
      ], fallback: 'payment').toLowerCase();
      final method = _stringFromJson(payment, ['method'], fallback: 'manual');
      final notes = _stringFromJson(payment, ['notes'], fallback: '');
      final reference = _stringFromJson(payment, ['reference'], fallback: '');
      final detail = notes.isNotEmpty ? notes : reference;

      return _PaymentEntry(
        id: _intFromAny(payment['id']),
        userId: _intFromAny(payment['user_id']),
        invoiceId: _intFromAny(payment['invoice_id']),
        purpose: purpose,
        statusKey: _stringFromJson(payment, ['status'], fallback: 'paid'),
        title: _paymentPurposeLabel(purpose),
        person: _paymentPersonLabel(payment, members),
        amount: _moneyFromValue(payment['amount']),
        amountInput: _paymentAmountInput(_moneyFromValue(payment['amount'])),
        method: _paymentMethodLabel(method),
        methodKey: method,
        date: _dateLabelFromValue(payment['paid_at'] ?? payment['created_at']),
        paidAtInput: _dateLabelFromValue(
          payment['paid_at'] ?? payment['created_at'],
        ),
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
      return _stringFromJson(invoice, [
        'user_name',
        'member_name',
        'recipient_name',
        'title',
      ], fallback: 'Mitglied');
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
      'prepayment' => _tr('membership.prepayment'),
      'donation' => _tr('membership.donation'),
      'membership_invoice' => _tr('membership.invoicePayment'),
      _ => _tr('membership.payment'),
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
      'prepayment' => airmiusAccentColor(context),
      'donation' => AirmiusColors.green,
      'membership_invoice' => AirmiusColors.amber,
      _ => airmiusAccentColor(context),
    };
  }

  String _financeTypeLabel(String type) {
    return switch (type) {
      'income' => _tr('membership.financeType.income'),
      'expense' => _tr('membership.financeType.expense'),
      _ => type,
    };
  }

  String _financeAccountLabel(String account) {
    return switch (account) {
      'cash' => _tr('membership.financeAccount.cash'),
      'bank' => _tr('membership.financeAccount.bank'),
      _ => account,
    };
  }

  List<String> _financeCategoryOptions(String type, {String? current}) {
    final selected = (current ?? '').trim();
    final base = type == 'income' ? _incomeCategories : _expenseCategories;

    if (selected.isEmpty ||
        base.any((item) => item.toLowerCase() == selected.toLowerCase())) {
      return base;
    }

    return [selected, ...base];
  }

  String _financeCategoryLabel(String category) {
    final key = _financeCategoryTranslationKeys[category];
    return key == null ? category : _tr(key);
  }

  Future<String?> _pickFinanceCategory(
    BuildContext context, {
    required String type,
    required String current,
  }) async {
    final search = TextEditingController();
    final selected = current.trim();

    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          final query = search.text.trim().toLowerCase();
          final options = _financeCategoryOptions(type, current: selected)
              .where(
                (category) =>
                    query.isEmpty || category.toLowerCase().contains(query),
              )
              .toList();

          return AlertDialog(
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              '${_financeTypeLabel(type)} – '
              '${_tr('membership.category')}',
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
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
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                    decoration: InputDecoration(
                      labelText: _tr('membership.searchCategory'),
                      hintText: _tr('membership.categorySearchHint'),
                      prefixIcon: Icon(Icons.search_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxHeight: 320),
                    child: options.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.all(18),
                            child: Text(
                              _tr('membership.noCategoryFound'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          )
                        : ListView.separated(
                            shrinkWrap: true,
                            itemCount: options.length,
                            separatorBuilder: (_, _) => Divider(
                              height: 1,
                              color: airmiusBorderColor(context),
                            ),
                            itemBuilder: (context, index) {
                              final category = options[index];
                              final isSelected =
                                  category.toLowerCase() ==
                                  selected.toLowerCase();

                              return ListTile(
                                dense: true,
                                leading: Icon(
                                  isSelected
                                      ? Icons.check_circle
                                      : Icons.label_outline,
                                  color: isSelected
                                      ? airmiusAccentColor(context)
                                      : airmiusMutedColor(context),
                                ),
                                title: Text(
                                  _financeCategoryLabel(category),
                                  style: TextStyle(
                                    color: isSelected
                                        ? airmiusTextColor(context)
                                        : airmiusMutedColor(context),
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                onTap: () =>
                                    Navigator.pop(dialogContext, category),
                              );
                            },
                          ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.cancel')),
              ),
            ],
          );
        },
      ),
    );

    search.dispose();

    return result;
  }

  Color _financeEntryColor(String type) =>
      type == 'income' ? AirmiusColors.green : AirmiusColors.red;

  IconData _financeEntryIcon(String type) =>
      type == 'income' ? Icons.add_card_outlined : Icons.receipt_long_outlined;

  List<_FinanceEntry> _financeEntriesFromManagement(
    AirmiusClubManagement? management,
  ) {
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
        title: _stringFromJson(entry, [
          'title',
        ], fallback: _financeTypeLabel(type)),
        category: category,
        amount: _moneyFromValue(entry['amount']),
        amountInput: _paymentAmountInput(_moneyFromValue(entry['amount'])),
        date: _dateLabelFromValue(entry['booked_on'] ?? entry['created_at']),
        bookedOnInput: _dateLabelFromValue(
          entry['booked_on'] ?? entry['created_at'],
        ),
        reference: reference,
        description: description,
        detail: detail,
        icon: _financeEntryIcon(type),
        color: _financeEntryColor(type),
      );
    }).toList();
  }

  List<_BankEntry> _bankEntriesFromManagement(
    AirmiusClubManagement? management,
  ) {
    final entries = management?.bankTransactions ?? const <JsonMap>[];
    return entries
        .map(
          (entry) => _BankEntry(
            id: _intFromAny(entry['id']),
            invoiceId: _intFromAny(entry['invoice_id']),
            status: _stringFromJson(entry, ['status'], fallback: 'unmatched'),
            title: _stringFromJson(entry, [
              'debtor_name',
              'counterparty',
              'booking_text',
              'title',
            ], fallback: 'Banktransaktion'),
            detail:
                '${_moneyFromValue(entry['amount'])} - ${_stringFromJson(entry, ['purpose', 'remittance_information', 'description', 'status'], fallback: 'nicht zugeordnet')}',
          ),
        )
        .toList();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubFuture ??= _loadManagedClub();
    if (!_savedViewsLoaded) {
      _savedViewsLoaded = true;
      _loadSavedViews();
    }
  }

  Future<void> _loadSavedViews() async {
    setState(() => _savedViewsLoading = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.search;
      final results = await Future.wait([
        repository.savedViews(workspace: 'members'),
        repository.savedViews(workspace: 'invoices'),
      ]);
      if (mounted) {
        setState(() {
          _memberSavedViews = results[0];
          _invoiceSavedViews = results[1];
        });
      }
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } finally {
      if (mounted) setState(() => _savedViewsLoading = false);
    }
  }

  @override
  void dispose() {
    _memberQueryController.dispose();
    _inviteNameController.dispose();
    _inviteEmailController.dispose();
    _invitePhoneController.dispose();
    _inviteStreetController.dispose();
    _inviteHouseNumberController.dispose();
    _invitePostalCodeController.dispose();
    _inviteCityController.dispose();
    _inviteCountryController.dispose();
    _inviteMemberNumberController.dispose();
    _inviteContributionController.dispose();
    _inviteNextInvoiceController.dispose();
    _inviteIbanController.dispose();
    _inviteBicController.dispose();
    _inviteMandateController.dispose();
    _inviteMandateDateController.dispose();
    _inviteJoinedOnController.dispose();
    _inviteMembershipEndsOnController.dispose();
    _inviteNotesController.dispose();
    super.dispose();
  }

  Future<void> _saveWorkspaceView(String workspace) async {
    final nameController = TextEditingController();
    final name = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_tr('search.savedViews.save')),
        content: TextField(
          controller: nameController,
          autofocus: true,
          maxLength: 80,
          decoration: InputDecoration(labelText: _tr('search.savedViews.name')),
          onSubmitted: (value) => Navigator.pop(dialogContext, value.trim()),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(_tr('common.cancel')),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.pop(dialogContext, nameController.text.trim()),
            child: Text(_tr('common.save')),
          ),
        ],
      ),
    );
    nameController.dispose();
    if (name == null || name.isEmpty || !mounted) return;

    try {
      final configuration = workspace == 'members'
          ? <String, dynamic>{
              'query': _memberQuery.trim(),
              'filters': {'club_id': _selectedClubId, 'member_type': _filter},
            }
          : <String, dynamic>{
              'filters': {'club_id': _selectedClubId, 'period': _period},
            };
      final view = await AirmiusServicesScope.of(context).repositories.search
          .createSavedView(
            workspace: workspace,
            name: name,
            configuration: configuration,
          );
      if (mounted) {
        setState(() {
          if (workspace == 'members') {
            _memberSavedViews = [view, ..._memberSavedViews];
          } else {
            _invoiceSavedViews = [view, ..._invoiceSavedViews];
          }
        });
      }
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    }
  }

  void _applyWorkspaceView(AirmiusSavedView view) {
    final filters = view.configuration['filters'];
    final values = filters is JsonMap ? filters : const <String, dynamic>{};
    final clubId = _intFromAny(values['club_id']);
    final changesClub = clubId > 0 && clubId != _selectedClubId;
    setState(() {
      if (clubId > 0) _selectedClubId = clubId;
      if (view.workspace == 'members') {
        _section = 'members';
        final memberType = values['member_type']?.toString() ?? 'Alle';
        _filter =
            const [
              'Alle',
              'Aktiv',
              'Extern',
              'Jugend',
              'Offen',
            ].contains(memberType)
            ? memberType
            : 'Alle';
        _memberQuery = view.configuration['query']?.toString() ?? '';
        _memberQueryController.text = _memberQuery;
      } else {
        _section = 'payments';
        final period = values['period']?.toString() ?? 'all';
        _period =
            const ['all', 'month', 'previousMonth', 'year'].contains(period)
            ? period
            : 'all';
      }
      if (changesClub) _clubFuture = _loadManagedClub();
    });
  }

  Future<void> _deleteWorkspaceView(AirmiusSavedView view) async {
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.search.deleteSavedView(view.id);
      if (mounted) {
        setState(() {
          _memberSavedViews = _memberSavedViews
              .where((candidate) => candidate.id != view.id)
              .toList();
          _invoiceSavedViews = _invoiceSavedViews
              .where((candidate) => candidate.id != view.id)
              .toList();
        });
      }
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    }
  }

  Widget _savedViewBar(String workspace, List<AirmiusSavedView> views) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: Text(
                _tr('search.savedViews.title'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            IconButton(
              onPressed: () => _saveWorkspaceView(workspace),
              icon: const Icon(Icons.bookmark_add_outlined),
              tooltip: _tr('search.savedViews.save'),
            ),
          ],
        ),
        if (_savedViewsLoading)
          const LinearProgressIndicator()
        else if (views.isEmpty)
          Text(
            _tr('search.savedViews.empty'),
            style: TextStyle(color: airmiusMutedColor(context)),
          )
        else
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: views
                .map(
                  (view) => InputChip(
                    avatar: const Icon(Icons.star, size: 16),
                    label: Text(view.name),
                    onPressed: () => _applyWorkspaceView(view),
                    onDeleted: () => _deleteWorkspaceView(view),
                  ),
                )
                .toList(),
          ),
      ],
    );
  }

  Map<String, dynamic> _invitationPayload({required bool sendInvitation}) => {
    'email': _inviteEmailController.text.trim(),
    'name': _inviteNameController.text.trim(),
    'role': _inviteRole,
    'membership_status': _inviteStatus,
    'club_membership_type_id': _inviteMembershipTypeId,
    'member_number': _inviteMemberNumberController.text.trim(),
    'phone': _invitePhoneController.text.trim().isEmpty
        ? null
        : _invitePhoneController.text.trim(),
    'street': _inviteStreetController.text.trim().isEmpty
        ? null
        : _inviteStreetController.text.trim(),
    'house_number': _inviteHouseNumberController.text.trim().isEmpty
        ? null
        : _inviteHouseNumberController.text.trim(),
    'postal_code': _invitePostalCodeController.text.trim().isEmpty
        ? null
        : _invitePostalCodeController.text.trim(),
    'city': _inviteCityController.text.trim().isEmpty
        ? null
        : _inviteCityController.text.trim(),
    'country': _inviteCountryController.text.trim().isEmpty
        ? null
        : _inviteCountryController.text.trim().toUpperCase(),
    'contribution_amount': _inviteContributionController.text.trim().isEmpty
        ? null
        : double.tryParse(
            _normalizePaymentAmount(_inviteContributionController.text),
          ),
    'contribution_interval': _inviteContributionInterval,
    'contribution_payer_user_id': _inviteContributionPayerId == 0
        ? null
        : _inviteContributionPayerId,
    'contribution_next_invoice_on':
        _inviteNextInvoiceController.text.trim().isEmpty
        ? null
        : _inviteNextInvoiceController.text.trim(),
    'sepa_iban': _inviteIbanController.text.trim().isEmpty
        ? null
        : _inviteIbanController.text.trim(),
    'sepa_bic': _inviteBicController.text.trim().isEmpty
        ? null
        : _inviteBicController.text.trim(),
    'sepa_mandate_reference': _inviteMandateController.text.trim().isEmpty
        ? null
        : _inviteMandateController.text.trim(),
    'sepa_mandate_signed_on': _inviteMandateDateController.text.trim().isEmpty
        ? null
        : _inviteMandateDateController.text.trim(),
    'sepa_mandate_active': _inviteSepaActive,
    'joined_on': _inviteJoinedOnController.text.trim().isEmpty
        ? null
        : _inviteJoinedOnController.text.trim(),
    'membership_ends_on': _inviteMembershipEndsOnController.text.trim().isEmpty
        ? null
        : _inviteMembershipEndsOnController.text.trim(),
    'membership_notes': _inviteNotesController.text.trim().isEmpty
        ? null
        : _inviteNotesController.text.trim(),
    'send_invitation': sendInvitation,
  };

  void _clearInvitationForm() {
    _inviteNameController.clear();
    _inviteEmailController.clear();
    _invitePhoneController.clear();
    _inviteStreetController.clear();
    _inviteHouseNumberController.clear();
    _invitePostalCodeController.clear();
    _inviteCityController.clear();
    _inviteCountryController.clear();
    _inviteMemberNumberController.clear();
    _inviteContributionController.clear();
    _inviteNextInvoiceController.clear();
    _inviteIbanController.clear();
    _inviteBicController.clear();
    _inviteMandateController.clear();
    _inviteMandateDateController.clear();
    _inviteJoinedOnController.clear();
    _inviteMembershipEndsOnController.clear();
    _inviteNotesController.clear();
    setState(() {
      _inviteRole = 'member';
      _inviteStatus = 'active';
      _inviteContributionInterval = 'none';
      _inviteContributionPayerId = 0;
      _inviteMembershipTypeId = null;
      _inviteSepaActive = false;
    });
  }

  Future<_ManagedMembershipData?> _loadManagedClub() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    final managed = page.items
        .map(ClubSummary.fromAirmiusClub)
        .where((club) => club.canAccessMembershipWorkspace)
        .toList();
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
    final data = _ManagedMembershipData(
      clubs: managed,
      selectedClub: ClubSummary.fromAirmiusClub(detail),
    );
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
      clubs: current.clubs
          .map(
            (club) => club.id == updatedClub.id
                ? club.copyWith(management: management)
                : club,
          )
          .toList(),
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
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_tr('membership.emailRequired'))));
      return;
    }

    setState(() => _sendingInvitation = true);
    try {
      final management = await AirmiusServicesScope.of(context)
          .repositories
          .clubs
          .inviteClubMember(club.id, _invitationPayload(sendInvitation: true));

      if (!mounted) return;
      _applyManagement(management);
      _clearInvitationForm();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_tr('membership.invitationSent')),
          action: SnackBarAction(
            label: _tr('membership.tab.members'),
            onPressed: () => setState(() => _section = 'members'),
          ),
        ),
      );
      setState(() => _sendingInvitation = false);
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException
          ? error.userMessage
          : _tr('common.errorDetails');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_tr('membership.invitationFailed')}: $message'),
        ),
      );
      setState(() => _sendingInvitation = false);
    }
  }

  Future<void> _storeExternalMember(ClubSummary club) async {
    if (_sendingInvitation) return;
    final email = _inviteEmailController.text.trim();
    if (email.isEmpty) {
      _toast(_tr('membership.emailRequired'));
      return;
    }

    setState(() => _sendingInvitation = true);
    try {
      final management = await AirmiusServicesScope.of(context)
          .repositories
          .clubs
          .inviteClubMember(club.id, _invitationPayload(sendInvitation: false));
      if (!mounted) return;
      _applyManagement(management);
      _clearInvitationForm();
      _toast(_tr('membership.externalSaved'));
    } catch (error) {
      if (mounted) _toast(_errorText(error));
    } finally {
      if (mounted) setState(() => _sendingInvitation = false);
    }
  }

  Future<void> _runManagementAction(
    Future<AirmiusClubManagement> Function() action, {
    required String success,
  }) async {
    try {
      final management = await action();
      if (!mounted) return;
      _applyManagement(management);
      _toast(success);
    } catch (error) {
      if (mounted) _toast(_errorText(error));
    }
  }

  Future<void> _runBulkAction(
    List<_MemberEntry> members,
    Future<AirmiusClubManagement> Function(_MemberEntry member) action, {
    required String success,
  }) async {
    if (members.isEmpty || _bulkActionRunning) return;
    setState(() => _bulkActionRunning = true);
    var completed = 0;
    var failed = 0;
    AirmiusClubManagement? latestManagement;

    for (final member in members) {
      try {
        latestManagement = await action(member);
        completed += 1;
      } catch (_) {
        failed += 1;
      }
    }

    if (!mounted) return;
    if (latestManagement != null) {
      _applyManagement(latestManagement);
    }
    setState(() {
      _bulkActionRunning = false;
      _selectedMemberKeys.clear();
    });
    final message = failed == 0
        ? success.replaceFirst('{count}', '$completed')
        : '$completed erledigt, $failed fehlgeschlagen.';
    _toast(message);
  }

  Future<void> _bulkInviteMembers(
    ClubSummary club,
    List<_MemberEntry> members,
  ) async {
    final targets = members
        .where((member) => member.isExternal && member.externalId != null)
        .toList();
    if (targets.isEmpty) {
      _toast('Keine externen Mitglieder für Einladungen ausgewählt.');
      return;
    }
    final confirmed = await _confirmBulkAction(
      title: 'Einladungen senden?',
      body: '${targets.length} externe Mitglieder erhalten eine Einladung.',
      confirm: 'Senden',
    );
    if (confirmed != true || !mounted) return;
    final repo = AirmiusServicesScope.of(context).repositories.clubs;
    await _runBulkAction(
      targets,
      (member) => repo.inviteClubExternalMember(club.id, member.externalId!),
      success: '{count} Einladungen gesendet.',
    );
  }

  Future<void> _bulkChangeStatus(
    ClubSummary club,
    List<_MemberEntry> members,
  ) async {
    final status = await _pickBulkStatus();
    if (status == null || !mounted) return;
    final confirmed = await _confirmBulkAction(
      title: 'Status ändern?',
      body:
          '${members.length} Mitglieder werden auf „${_membershipStatusLabel(status)}“ gesetzt.',
      confirm: 'Ändern',
    );
    if (confirmed != true || !mounted) return;
    final repo = AirmiusServicesScope.of(context).repositories.clubs;
    await _runBulkAction(
      members,
      (member) => member.isExternal && member.externalId != null
          ? repo.updateClubExternalMember(
              club.id,
              member.externalId!,
              _memberStatusPayload(member, status),
            )
          : repo.updateClubMember(
              club.id,
              member.id,
              _memberStatusPayload(member, status),
            ),
      success: '{count} Statusänderungen gespeichert.',
    );
  }

  Future<void> _bulkRemoveMembers(
    ClubSummary club,
    List<_MemberEntry> members,
  ) async {
    final confirmed = await _confirmBulkAction(
      title: 'Mitglieder entfernen?',
      body:
          '${members.length} Mitglieder werden aus dem Verein entfernt. Diese Aktion sollte nur mit dokumentiertem Grund erfolgen.',
      confirm: 'Entfernen',
      destructive: true,
    );
    if (confirmed != true || !mounted) return;
    final reason = await _askRemovalReason();
    if (reason == null || !mounted) return;
    final repo = AirmiusServicesScope.of(context).repositories.clubs;
    await _runBulkAction(
      members,
      (member) => member.isExternal && member.externalId != null
          ? repo.removeClubExternalMember(
              club.id,
              member.externalId!,
              reason: reason,
            )
          : repo.removeClubMember(club.id, member.id, reason: reason),
      success: '{count} Mitglieder entfernt.',
    );
  }

  Future<bool?> _confirmBulkAction({
    required String title,
    required String body,
    required String confirm,
    bool destructive = false,
  }) {
    return showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_tr('membership.cancel')),
          ),
          FilledButton(
            style: destructive
                ? FilledButton.styleFrom(backgroundColor: AirmiusColors.red)
                : null,
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(confirm),
          ),
        ],
      ),
    );
  }

  Future<String?> _pickBulkStatus() {
    return showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final status in [
              'active',
              'pending',
              'paused',
              'former',
              'non_member',
            ])
              ListTile(
                leading: Icon(
                  status == 'paused'
                      ? Icons.pause_circle_outline
                      : Icons.verified_user_outlined,
                ),
                title: Text(_membershipStatusLabel(status)),
                onTap: () => Navigator.pop(sheetContext, status),
              ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _errorText(Object error) => error is AirmiusApiException
      ? error.userMessage
      : _tr('common.errorDetails');

  Uri _apiUri(String path) {
    final base = Uri.parse(
      AirmiusServicesScope.of(context).environment.apiBaseUrl,
    );
    final prefix = base.path.endsWith('/') ? base.path : '${base.path}/';
    return base.replace(
      path: '$prefix${path.startsWith('/') ? path.substring(1) : path}',
      query: null,
      fragment: null,
    );
  }

  Map<String, String> _apiHeaders({bool json = false}) {
    final services = AirmiusServicesScope.of(context);
    return {
      'Accept': 'application/json',
      'X-Airmius-Locale': services.environment.locale,
      if (json) 'Content-Type': 'application/json',
      if (services.authState.session?.token.isNotEmpty == true)
        'Authorization': 'Bearer ${services.authState.session!.token}',
    };
  }

  Future<void> _downloadApiFile({
    required String path,
    required String fileName,
    required List<String> extensions,
  }) async {
    try {
      final response = await http.get(_apiUri(path), headers: _apiHeaders());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
      }

      final saved = await FilePicker.platform.saveFile(
        dialogTitle: _tr('membership.saveFile'),
        fileName: fileName,
        type: FileType.custom,
        allowedExtensions: extensions,
        bytes: Uint8List.fromList(response.bodyBytes),
      );
      if (mounted && saved != null) {
        _toast(_tr('membership.fileSaved'));
      }
    } catch (error) {
      if (mounted) _toast(_errorText(error));
    }
  }

  Future<void> _uploadImport(ClubSummary club, {required bool bank}) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: bank ? const ['csv'] : const ['csv', 'xlsx', 'xls'],
      withData: true,
    );
    final file = result?.files.single;
    if (file == null) return;

    Map<String, dynamic>? importMapping;
    var requestInvitationForImportedMembers = false;
    final path = bank
        ? '/api/v1/clubs/${club.id}/bank-transactions/import'
        : '/api/v1/clubs/${club.id}/members/import';
    try {
      if (bank) {
        final preview = await _previewBankImport(club, file);
        if (!mounted || !await _confirmBankImport(preview)) return;
      } else {
        var preview = await _previewMembershipImport(club, file);
        if (preview['needs_mapping'] == true) {
          importMapping = await _chooseMembershipImportMapping(preview);
          if (importMapping == null) return;
          preview = await _previewMembershipImport(
            club,
            file,
            mapping: importMapping,
          );
        }
        if (!mounted) return;
        final decision = await _confirmMembershipImport(preview);
        if (decision == null) return;
        requestInvitationForImportedMembers =
            decision['send_invitation'] == true;
      }
      final request = http.MultipartRequest('POST', _apiUri(path))
        ..headers.addAll(_apiHeaders());
      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'file',
            file.bytes!,
            filename: file.name,
          ),
        );
      } else if (file.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'file',
            file.path!,
            filename: file.name,
          ),
        );
      } else {
        throw StateError(_tr('membership.fileUnreadable'));
      }
      if (importMapping != null) {
        request.fields['mapping'] = jsonEncode(importMapping);
      }
      if (!bank) {
        request.fields['send_invitation'] = requestInvitationForImportedMembers
            ? '1'
            : '0';
      }

      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
      }
      if (!mounted) return;
      final decoded = jsonDecode(response.body);
      final responseData = decoded is Map ? decoded['data'] : null;
      if (responseData is Map) {
        _applyManagement(
          AirmiusClubManagement.fromJson(
            Map<String, dynamic>.from(responseData),
          ),
        );
      }
      _toast(
        bank
            ? _tr('membership.bankImportComplete')
            : _tr('membership.membersImported'),
      );
    } catch (error) {
      if (mounted) _toast(_errorText(error));
    }
  }

  Future<Map<String, dynamic>> _previewMembershipImport(
    ClubSummary club,
    PlatformFile file, {
    Map<String, dynamic>? mapping,
  }) async {
    const pathSuffix = '/members/import-preview';
    final path = '/api/v1/clubs/${club.id}$pathSuffix';
    final request = http.MultipartRequest('POST', _apiUri(path))
      ..headers.addAll(_apiHeaders());
    if (mapping != null) {
      request.fields['mapping'] = jsonEncode(mapping);
    }
    if (file.bytes != null) {
      request.files.add(
        http.MultipartFile.fromBytes('file', file.bytes!, filename: file.name),
      );
    } else if (file.path != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'file',
          file.path!,
          filename: file.name,
        ),
      );
    } else {
      throw StateError(_tr('membership.fileUnreadable'));
    }

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    final decoded = jsonDecode(response.body);
    final data = decoded is Map ? decoded['data'] : null;
    if (data is! Map) {
      throw StateError(_tr('common.errorDetails'));
    }
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> _previewBankImport(
    ClubSummary club,
    PlatformFile file,
  ) async {
    final path = '/api/v1/clubs/${club.id}/bank-transactions/preview';
    final request = http.MultipartRequest('POST', _apiUri(path))
      ..headers.addAll(_apiHeaders());
    if (file.bytes != null) {
      request.files.add(
        http.MultipartFile.fromBytes('file', file.bytes!, filename: file.name),
      );
    } else if (file.path != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'file',
          file.path!,
          filename: file.name,
        ),
      );
    } else {
      throw StateError(_tr('membership.fileUnreadable'));
    }

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    final decoded = jsonDecode(response.body);
    final data = decoded is Map ? decoded['data'] : null;
    if (data is! Map) throw StateError(_tr('common.errorDetails'));
    return Map<String, dynamic>.from(data);
  }

  Future<bool> _confirmBankImport(Map<String, dynamic> preview) async {
    final rawStats = preview['stats'];
    final stats = rawStats is Map ? rawStats : const <String, dynamic>{};
    final importable = '${stats['importable'] ?? 0}';
    final total = '${stats['total'] ?? 0}';
    final invalid = '${stats['invalid'] ?? 0}';
    final duplicates = '${stats['duplicates'] ?? 0}';
    final canImport = preview['can_import'] == true;
    final rawRows = preview['rows'];
    final rows = rawRows is List
        ? rawRows.whereType<Map>().take(5)
        : const <Map>[];
    final rawErrors = preview['errors'];
    final errors = rawErrors is List
        ? rawErrors.whereType<Map>().take(5)
        : const <Map>[];
    final summary = _tr('membership.bankPreviewSummary')
        .replaceFirst('{importable}', importable)
        .replaceFirst('{total}', total)
        .replaceFirst('{invalid}', invalid)
        .replaceFirst('{duplicates}', duplicates);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_tr('membership.bankPreviewTitle')),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 560),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(summary),
                if (rows.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  for (final row in rows)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Text(
                        _tr('membership.bankPreviewRow')
                            .replaceFirst('{row}', '${row['row'] ?? '?'}')
                            .replaceFirst('{amount}', '${row['amount'] ?? '-'}')
                            .replaceFirst(
                              '{assignment}',
                              '${(row['invoice'] as Map?)?['number'] ?? row['status'] ?? '-'}',
                            ),
                      ),
                    ),
                ],
                if (errors.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  for (final error in errors)
                    Text(
                      _tr('membership.importPreviewRow')
                          .replaceFirst('{row}', '${error['row'] ?? '?'}')
                          .replaceFirst('{reason}', '${error['reason'] ?? ''}'),
                      style: const TextStyle(color: AirmiusColors.red),
                    ),
                ],
                if (!canImport) ...[
                  const SizedBox(height: 10),
                  Text(
                    _tr('membership.bankPreviewNoValid'),
                    style: const TextStyle(
                      color: AirmiusColors.red,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_tr('membership.importPreviewCancel')),
          ),
          if (canImport)
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(_tr('membership.importPreviewContinue')),
            ),
        ],
      ),
    );
    return confirmed == true;
  }

  Future<Map<String, dynamic>?> _chooseMembershipImportMapping(
    Map<String, dynamic> preview,
  ) async {
    final rawColumns = preview['columns'];
    final columns = rawColumns is List
        ? rawColumns.map((value) => '$value').toList()
        : const <String>[];
    if (columns.isEmpty) {
      _toast(_tr('membership.importMappingRequired'));
      return null;
    }

    final rawSuggested = preview['mapping'];
    final suggested = rawSuggested is Map
        ? rawSuggested.map(
            (key, value) => MapEntry('$key', int.tryParse('$value') ?? -1),
          )
        : <String, int>{};
    final fields = <Map<String, String>>[
      {'key': 'email', 'label': _tr('membership.importMappingEmail')},
      {'key': 'name', 'label': _tr('membership.importMappingName')},
      {
        'key': 'membership_status',
        'label': _tr('membership.importMappingStatus'),
      },
      {
        'key': 'family_group_key',
        'label': _tr('membership.importMappingFamilyGroup'),
      },
      {
        'key': 'contribution_amount',
        'label': _tr('membership.importMappingAmount'),
      },
      {
        'key': 'contribution_interval',
        'label': _tr('membership.importMappingInterval'),
      },
    ];
    final selected = <String, int>{
      for (final field in fields) field['key']!: suggested[field['key']] ?? -1,
    };
    final mapping = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_tr('membership.importMappingTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(_tr('membership.importMappingBody')),
                const SizedBox(height: 14),
                for (final field in fields) ...[
                  DropdownButtonFormField<int>(
                    initialValue:
                        selected[field['key']]! >= 0 &&
                            selected[field['key']]! < columns.length
                        ? selected[field['key']]
                        : -1,
                    decoration: InputDecoration(labelText: field['label']),
                    items: [
                      DropdownMenuItem(
                        value: -1,
                        child: Text(_tr('membership.importMappingNotMapped')),
                      ),
                      for (var index = 0; index < columns.length; index++)
                        DropdownMenuItem(
                          value: index,
                          child: Text('${index + 1}: ${columns[index]}'),
                        ),
                    ],
                    onChanged: (value) => setDialogState(() {
                      selected[field['key']!] = value ?? -1;
                    }),
                  ),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(_tr('membership.importPreviewCancel')),
            ),
            FilledButton(
              onPressed: selected['email']! < 0
                  ? null
                  : () => Navigator.pop(dialogContext, {
                      for (final entry in selected.entries)
                        if (entry.value >= 0) entry.key: entry.value,
                    }),
              child: Text(_tr('membership.importPreviewContinue')),
            ),
          ],
        ),
      ),
    );
    return mapping;
  }

  Future<Map<String, bool>?> _confirmMembershipImport(
    Map<String, dynamic> preview,
  ) async {
    final total = preview['total_rows']?.toString() ?? '0';
    final valid = preview['valid_rows']?.toString() ?? '0';
    final errors = preview['error_count']?.toString() ?? '0';
    final rawRows = preview['rows'];
    final rows = rawRows is List
        ? rawRows.whereType<Map>().take(8).toList()
        : const <Map>[];
    final errorItems = preview['errors'] is List
        ? (preview['errors'] as List).whereType<Map>().take(5).toList()
        : const <Map>[];
    final summary = _tr('membership.importPreviewSummary')
        .replaceFirst('{valid}', valid)
        .replaceFirst('{total}', total)
        .replaceFirst('{errors}', errors);
    final canImport = preview['can_import'] == true && valid != '0';

    var sendInvitation = false;
    return showDialog<Map<String, bool>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          final text = airmiusTextColor(dialogContext);
          final muted = airmiusMutedColor(dialogContext);
          return AlertDialog(
            title: Text(_tr('membership.importPreviewTitle')),
            content: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(summary, style: TextStyle(color: muted, height: 1.4)),
                    if (rows.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      Text(
                        _tr('membership.importPreviewValidRows'),
                        style: TextStyle(
                          color: text,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      for (final row in rows)
                        Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: airmiusSurfaceSoftColor(dialogContext),
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: airmiusBorderColor(dialogContext),
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: Text(
                                      '${row['name'] ?? _tr('membership.member')}',
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: text,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  StatusPill(
                                    _membershipImportActionLabel(
                                      '${row['action'] ?? ''}',
                                    ),
                                    color:
                                        '${row['action'] ?? ''}' ==
                                            'create_external_member'
                                        ? AirmiusColors.green
                                        : AirmiusColors.amber,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${row['email'] ?? '-'}',
                                style: TextStyle(color: muted),
                              ),
                              const SizedBox(height: 6),
                              Wrap(
                                spacing: 8,
                                runSpacing: 6,
                                children: _membershipImportPreviewDetails(
                                  row,
                                ).map((detail) => StatusPill(detail)).toList(),
                              ),
                            ],
                          ),
                        ),
                    ],
                    if (errorItems.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      Text(
                        _tr('membership.importPreviewErrors'),
                        style: TextStyle(
                          color: text,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 6),
                      for (final error in errorItems)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 5),
                          child: Text(
                            _tr('membership.importPreviewRow')
                                .replaceFirst('{row}', '${error['row'] ?? '?'}')
                                .replaceFirst(
                                  '{reason}',
                                  '${error['reason'] ?? ''}',
                                ),
                            style: TextStyle(color: muted, height: 1.3),
                          ),
                        ),
                    ],
                    if (!canImport) ...[
                      const SizedBox(height: 12),
                      Text(
                        _tr('membership.importPreviewNoValid'),
                        style: TextStyle(
                          color: AirmiusColors.red,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                    if (canImport) ...[
                      const SizedBox(height: 14),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        controlAffinity: ListTileControlAffinity.leading,
                        value: sendInvitation,
                        onChanged: (value) => setDialogState(
                          () => sendInvitation = value ?? false,
                        ),
                        title: Text(
                          _tr('membership.importSendInvitations'),
                          style: TextStyle(
                            color: text,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        subtitle: Text(
                          _tr('membership.importSendInvitationsHint'),
                          style: TextStyle(color: muted, height: 1.3),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.importPreviewCancel')),
              ),
              if (canImport)
                FilledButton(
                  onPressed: () => Navigator.pop(dialogContext, {
                    'send_invitation': sendInvitation,
                  }),
                  child: Text(_tr('membership.importPreviewContinue')),
                ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _createInvoice(
    ClubSummary club,
    List<_MemberEntry> members, {
    _MemberEntry? initialMember,
  }) async {
    if (members.isEmpty) {
      _toast(_tr('membership.noLinkedMember'));
      return;
    }

    DateTime addInvoicePeriod(DateTime start, String interval) {
      final next = switch (interval) {
        'monthly' => DateTime(start.year, start.month + 1, start.day),
        'quarterly' => DateTime(start.year, start.month + 3, start.day),
        'four_monthly' => DateTime(start.year, start.month + 4, start.day),
        'semi_yearly' => DateTime(start.year, start.month + 6, start.day),
        'yearly' => DateTime(start.year + 1, start.month, start.day),
        _ => start,
      };

      return interval == 'none' || interval == 'once'
          ? start
          : next.subtract(const Duration(days: 1));
    }

    int inclusiveDays(DateTime start, DateTime end) =>
        end.difference(start).inDays + 1;

    String ruleDateOnly(Object? value) {
      final text = '$value'.trim();
      if (text.isEmpty || text == 'null') return '';
      return text.length >= 10 ? text.substring(0, 10) : text;
    }

    final contributionRules =
        _currentData?.selectedClub.management?.contributionRules ??
        const <JsonMap>[];

    JsonMap? matchingRuleFor(_MemberEntry member) {
      final typeId = _intOrNull(member.membership['club_membership_type_id']);
      final today = _dateOnly(DateTime.now());
      final matches = contributionRules.where((rule) {
        final validFrom = ruleDateOnly(rule['valid_from']);
        final validUntil = ruleDateOnly(rule['valid_until']);
        final factorKey = _stringFromJson(
          rule,
          ['factor_key'],
          fallback: 'standard',
        );
        final ruleTypeId = _intOrNull(rule['club_membership_type_id']);
        final isBaseRule = factorKey == 'standard' || factorKey == 'base';
        final isActive =
            rule['is_active'] == null || _boolFromAny(rule['is_active']);

        return isActive &&
            isBaseRule &&
            (validFrom.isEmpty || validFrom.compareTo(today) <= 0) &&
            (validUntil.isEmpty || validUntil.compareTo(today) >= 0) &&
            (typeId == null
                ? ruleTypeId == null
                : ruleTypeId == typeId || ruleTypeId == null);
      }).toList()
        ..sort((a, b) {
          final aExact =
              _intOrNull(a['club_membership_type_id']) == typeId ? 1 : 0;
          final bExact =
              _intOrNull(b['club_membership_type_id']) == typeId ? 1 : 0;
          if (aExact != bExact) return bExact.compareTo(aExact);
          final priorityComparison =
              _intFromAny(b['priority']).compareTo(_intFromAny(a['priority']));
          if (priorityComparison != 0) return priorityComparison;

          return ruleDateOnly(
            b['valid_from'],
          ).compareTo(ruleDateOnly(a['valid_from']));
        });

      return matches.isEmpty ? null : matches.first;
    }

    ({String amount, String start, String end, String due, String description})
    invoiceSuggestionFor(_MemberEntry member) {
      final rule = matchingRuleFor(member);
      final rawAmount =
          _stringFromJson(member.membership, ['contribution_amount']).isNotEmpty
          ? _stringFromJson(member.membership, ['contribution_amount'])
          : _stringFromJson(rule ?? const {}, ['amount']);
      final fullAmount =
          double.tryParse(_normalizePaymentAmount(rawAmount)) ?? 0;
      final interval = _stringFromJson(
        member.membership,
        ['contribution_interval'],
        fallback: _stringFromJson(
          rule ?? const {},
          ['billing_interval'],
          fallback: 'none',
        ),
      );
      final periodStart =
          _dateOnlyFromValue(member.membership['contribution_next_invoice_on']) ??
          _dateOnlyFromValue(member.membership['joined_on']) ??
          DateTime.now();
      final start = DateTime(
        periodStart.year,
        periodStart.month,
        periodStart.day,
      );
      final end = addInvoicePeriod(start, interval);
      final joined = _dateOnlyFromValue(member.membership['joined_on']);
      final policy = _stringFromJson(
        rule ?? const {},
        ['proration_policy'],
        fallback: 'prorate_days',
      );
      var invoiceAmount = fullAmount;
      var description = '';

      if (joined != null && joined.isAfter(start) && !joined.isAfter(end)) {
        if (policy == 'next_period') {
          invoiceAmount = 0;
          description = _tr('membership.proration.nextPeriod');
        } else if (policy == 'prorate_days') {
          final periodDays = inclusiveDays(start, end);
          final billableDays = inclusiveDays(joined, end);
          invoiceAmount =
              (fullAmount * 100 * billableDays / periodDays).round() / 100;
          description = _tr('membership.proration.prorateDays');
        } else if (policy == 'full_amount') {
          description = _tr('membership.proration.fullAmount');
        }
      }

      return (
        amount: invoiceAmount.toStringAsFixed(2),
        start: _dateDisplay(start),
        end: _dateDisplay(end),
        due: _dateDisplay(DateTime.now().add(const Duration(days: 14))),
        description: description,
      );
    }

    var memberId = initialMember?.id ?? members.first.id;
    final initialSuggestion = invoiceSuggestionFor(
      initialMember ?? members.first,
    );
    final title = TextEditingController(
      text: _tr('membership.defaultContributionTitle'),
    );
    final amount = TextEditingController(text: initialSuggestion.amount);
    final periodStart = TextEditingController(text: initialSuggestion.start);
    final periodEnd = TextEditingController(text: initialSuggestion.end);
    final dueDate = TextEditingController(text: initialSuggestion.due);
    final description = TextEditingController(
      text: initialSuggestion.description,
    );
    var waived = false;
    var markPaid = false;
    var paymentMethod = 'cash';
    final paidAt = TextEditingController(text: _dateDisplay(DateTime.now()));
    final paymentReference = TextEditingController();
    final paymentNotes = TextEditingController();
    final payload = await showModalBottomSheet<JsonMap>(
      context: context,
      isScrollControlled: true,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: 16 + MediaQuery.of(sheetContext).viewInsets.bottom,
              ),
              child: SizedBox(
                height: MediaQuery.of(sheetContext).size.height * .86,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: airmiusAccentColor(
                              context,
                            ).withValues(alpha: .16),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(
                            Icons.receipt_long_outlined,
                            color: airmiusAccentColor(context),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            _tr('membership.createInvoice'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(sheetContext),
                          icon: Icon(Icons.close),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Expanded(
                      child: ListView(
                        children: [
                          _memberPickerField(
                            context: sheetContext,
                            members: members,
                            selectedMemberId: memberId,
                            onChanged: (value) => setSheetState(() {
                              memberId = value;
                              final selected = members.firstWhere(
                                (entry) => entry.id == value,
                                orElse: () => members.first,
                              );
                              final suggestion = invoiceSuggestionFor(selected);
                              amount.text = suggestion.amount;
                              periodStart.text = suggestion.start;
                              periodEnd.text = suggestion.end;
                              dueDate.text = suggestion.due;
                              description.text = suggestion.description;
                            }),
                          ),
                          const SizedBox(height: 12),
                          AirmiusTextField(
                            label: _tr('membership.invoiceTitle'),
                            controller: title,
                            icon: Icons.title_outlined,
                          ),
                          const SizedBox(height: 12),
                          AirmiusTextField(
                            label: _tr('membership.amountEur'),
                            hint: '0,00',
                            controller: amount,
                            icon: Icons.euro_outlined,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AirmiusColors.green.withValues(alpha: .10),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: AirmiusColors.green.withValues(
                                  alpha: .30,
                                ),
                              ),
                            ),
                            child: SwitchListTile(
                              contentPadding: EdgeInsets.zero,
                              value: waived,
                              onChanged: (value) => setSheetState(() {
                                waived = value;
                                if (value) markPaid = false;
                              }),
                              title: Text(
                                _tr('membership.waiveInvoice'),
                                style: TextStyle(
                                  color: airmiusTextColor(context),
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              subtitle: Text(
                                _tr('membership.waiveInvoiceHint'),
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: AirmiusTextField(
                                  label:
                                      '${_tr('membership.period')} (${_tr('membership.from')})',
                                  hint: _tr('membership.dateHint'),
                                  controller: periodStart,
                                  icon: Icons.date_range_outlined,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: AirmiusTextField(
                                  label:
                                      '${_tr('membership.period')} (${_tr('membership.to')})',
                                  hint: _tr('membership.dateHint'),
                                  controller: periodEnd,
                                  icon: Icons.event_available_outlined,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          AirmiusTextField(
                            label: _tr('membership.dueDate'),
                            hint: _tr('membership.dateHint'),
                            controller: dueDate,
                            icon: Icons.event_outlined,
                          ),
                          const SizedBox(height: 12),
                          AirmiusTextField(
                            label: _tr('membership.descriptionOptional'),
                            controller: description,
                            icon: Icons.notes_outlined,
                            maxLines: 3,
                          ),
                          const SizedBox(height: 16),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: airmiusSurfaceSoftColor(context),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: airmiusBorderColor(context),
                              ),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Material(
                                  type: MaterialType.transparency,
                                  child: SwitchListTile(
                                    contentPadding: EdgeInsets.zero,
                                    title: Text(
                                      _tr('membership.markPaidNow'),
                                      style: TextStyle(
                                        color: airmiusTextColor(context),
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    subtitle: Text(
                                      _tr('membership.markPaidNowHint'),
                                      style: TextStyle(
                                        color: airmiusMutedColor(context),
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    value: markPaid,
                                    onChanged: waived
                                        ? null
                                        : (value) => setSheetState(
                                            () => markPaid = value,
                                          ),
                                  ),
                                ),
                                if (markPaid) ...[
                                  const SizedBox(height: 10),
                                  DropdownButtonFormField<String>(
                                    isExpanded: true,
                                    initialValue: paymentMethod,
                                    dropdownColor: airmiusSurfaceSoftColor(
                                      context,
                                    ),
                                    decoration: InputDecoration(
                                      labelText: _tr(
                                        'membership.paymentMethod',
                                      ),
                                    ),
                                    items:
                                        const [
                                              'cash',
                                              'bank_transfer',
                                              'sepa_debit',
                                              'manual',
                                            ]
                                            .map(
                                              (item) =>
                                                  DropdownMenuItem<String>(
                                                    value: item,
                                                    child: Text(
                                                      _paymentMethodLabel(item),
                                                    ),
                                                  ),
                                            )
                                            .toList(),
                                    onChanged: (value) => setSheetState(
                                      () => paymentMethod =
                                          value ?? paymentMethod,
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  AirmiusTextField(
                                    label: _tr('membership.paidOn'),
                                    hint: _tr('membership.dateHint'),
                                    controller: paidAt,
                                    icon: Icons.today_outlined,
                                  ),
                                  const SizedBox(height: 10),
                                  AirmiusTextField(
                                    label: _tr('membership.reference'),
                                    hint: _tr('membership.optional'),
                                    controller: paymentReference,
                                    icon: Icons.tag_outlined,
                                  ),
                                  const SizedBox(height: 10),
                                  AirmiusTextField(
                                    label: _tr('membership.note'),
                                    hint: _tr('membership.optional'),
                                    controller: paymentNotes,
                                    icon: Icons.sticky_note_2_outlined,
                                    maxLines: 2,
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: () => Navigator.pop(sheetContext, {
                        'member_id': memberId,
                        'title': title.text.trim(),
                        'amount': _normalizePaymentAmount(amount.text),
                        'billing_period_start': _dateInputForApi(
                          periodStart.text,
                        ),
                        'billing_period_end': _dateInputForApi(periodEnd.text),
                        'due_date': dueDate.text.trim(),
                        'description': description.text.trim().isEmpty
                            ? null
                            : description.text.trim(),
                        'waived': waived,
                        'waiver_reason': waived
                            ? (description.text.trim().isEmpty
                                  ? null
                                  : description.text.trim())
                            : null,
                        'mark_paid': markPaid,
                        'payment_method': paymentMethod,
                        'paid_at': _dateInputForApi(paidAt.text),
                        'payment_reference':
                            paymentReference.text.trim().isEmpty
                            ? null
                            : paymentReference.text.trim(),
                        'payment_notes': paymentNotes.text.trim().isEmpty
                            ? null
                            : paymentNotes.text.trim(),
                      }),
                      icon: Icon(Icons.check_outlined),
                      label: Text(_tr('membership.create')),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );

    if (payload != null && mounted) {
      try {
        final repo = AirmiusServicesScope.of(context).repositories.clubs;
        final selectedMember = members.firstWhere(
          (entry) => entry.id == _intFromAny(payload['member_id']),
          orElse: () => members.first,
        );
        final invoicePayload = {
          'title': payload['title'],
          'amount': payload['amount'],
          'billing_period_start': payload['billing_period_start'],
          'billing_period_end': payload['billing_period_end'],
          'due_date': payload['due_date'],
          'description': payload['description'],
          'waived': payload['waived'],
          'waiver_reason': payload['waiver_reason'],
        };
        var management = selectedMember.isExternal
            ? await repo.createClubExternalMemberInvoice(
                club.id,
                selectedMember.externalId!,
                invoicePayload,
              )
            : await repo.createClubMemberInvoice(
                club.id,
                selectedMember.id,
                invoicePayload,
              );

        if (payload['mark_paid'] == true) {
          final invoiceId = _newInvoiceIdFromManagement(
            management,
            memberId: selectedMember.id,
            title: '${payload['title'] ?? ''}',
            amount: '${payload['amount'] ?? ''}',
          );
          if (invoiceId > 0) {
            management = await repo
                .recordMembershipPayment(club.id, invoiceId, {
                  'invoice_id': invoiceId,
                  'amount': payload['amount'],
                  'method': payload['payment_method'],
                  'paid_at': payload['paid_at'],
                  'reference': payload['payment_reference'],
                  'notes': payload['payment_notes'],
                });
          }
        }

        if (!mounted) return;
        _applyManagement(management);
        _toast(_tr('membership.invoiceCreated'));
      } catch (error) {
        if (mounted) _toast(_errorText(error));
      }
    }
    unawaited(
      Future<void>.delayed(const Duration(milliseconds: 350), () {
        title.dispose();
        amount.dispose();
        periodStart.dispose();
        periodEnd.dispose();
        dueDate.dispose();
        description.dispose();
        paidAt.dispose();
        paymentReference.dispose();
        paymentNotes.dispose();
      }),
    );
  }

  Future<void> _runInvoiceBatch(ClubSummary club) async {
    final runDate = TextEditingController(text: _dateDisplay(DateTime.now()));
    final dueDate = TextEditingController(text: _dateDisplay(DateTime.now()));
    final title = TextEditingController(
      text: _tr('membership.defaultContributionTitle'),
    );
    JsonMap? preview;
    var loading = false;
    var creating = false;
    String? error;

    Future<void> loadPreview(StateSetter setSheetState) async {
      if (loading) return;
      setSheetState(() {
        loading = true;
        error = null;
      });
      try {
        final result = await AirmiusServicesScope.of(
          context,
        ).repositories.clubs.previewClubMembershipInvoiceRun(club.id, {
          'run_date': _dateInputForApi(runDate.text),
          'due_date': _dateInputForApi(dueDate.text),
          'title': title.text.trim().isEmpty ? null : title.text.trim(),
        });
        setSheetState(() => preview = result);
      } catch (exception) {
        setSheetState(() => error = _errorText(exception));
      } finally {
        setSheetState(() => loading = false);
      }
    }

    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final rows = (preview?['rows'] is List)
              ? (preview!['rows'] as List).whereType<JsonMap>().toList()
              : const <JsonMap>[];
          final billable = _intFromAny(preview?['billable_count']);

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: 16 + MediaQuery.of(sheetContext).viewInsets.bottom,
              ),
              child: SizedBox(
                height: MediaQuery.of(sheetContext).size.height * .88,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Icon(
                          Icons.playlist_add_check_circle_outlined,
                          color: airmiusAccentColor(context),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _tr('membership.invoiceRun'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(sheetContext, false),
                          icon: const Icon(Icons.close),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Expanded(
                      child: ListView(
                        children: [
                          AirmiusTextField(
                            label: _tr('membership.invoiceRunDate'),
                            hint: _tr('membership.dateHint'),
                            controller: runDate,
                            icon: Icons.event_repeat_outlined,
                          ),
                          const SizedBox(height: 10),
                          AirmiusTextField(
                            label: _tr('membership.dueDate'),
                            hint: _tr('membership.dateHint'),
                            controller: dueDate,
                            icon: Icons.event_available_outlined,
                          ),
                          const SizedBox(height: 10),
                          AirmiusTextField(
                            label: _tr('membership.invoiceTitle'),
                            controller: title,
                            icon: Icons.title_outlined,
                          ),
                          const SizedBox(height: 12),
                          OutlinedButton.icon(
                            onPressed: loading
                                ? null
                                : () => loadPreview(setSheetState),
                            icon: const Icon(Icons.visibility_outlined),
                            label: Text(
                              loading
                                  ? _tr('membership.checking')
                                  : _tr('membership.previewInvoiceRun'),
                            ),
                          ),
                          if (error != null) ...[
                            const SizedBox(height: 10),
                            Text(
                              error!,
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                          if (preview != null) ...[
                            const SizedBox(height: 14),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                StatusPill(
                                  '${_tr('membership.billable')}: $billable',
                                  color: AirmiusColors.green,
                                ),
                                StatusPill(
                                  '${_tr('membership.total')}: ${_moneyFromValue(preview!['total_amount'])}',
                                ),
                                StatusPill(
                                  '${_tr('membership.bankTransfer')}: ${preview!['transfer_count'] ?? 0}',
                                ),
                                StatusPill(
                                  '${_tr('membership.directDebit')}: ${preview!['direct_debit_count'] ?? 0}',
                                ),
                                StatusPill(
                                  '${_tr('membership.skipped')}: ${preview!['skipped_count'] ?? 0}',
                                  color: AirmiusColors.amber,
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            for (final row in rows)
                              _InvoiceRunPreviewTile(
                                row: row,
                                money: _moneyFromValue(row['amount']),
                                period:
                                    '${_dateLabelFromValue(row['billing_period_start'])} - ${_dateLabelFromValue(row['billing_period_end'])}',
                              ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: creating || billable <= 0
                          ? null
                          : () async {
                              setSheetState(() {
                                creating = true;
                                error = null;
                              });
                              try {
                                final management = await AirmiusServicesScope.of(
                                  context,
                                ).repositories.clubs.createClubMembershipInvoiceRun(
                                  club.id,
                                  {
                                    'run_date': _dateInputForApi(runDate.text),
                                    'due_date': _dateInputForApi(dueDate.text),
                                    'title': title.text.trim().isEmpty
                                        ? null
                                        : title.text.trim(),
                                  },
                                );
                                if (!mounted) return;
                                _applyManagement(management);
                                Navigator.pop(sheetContext, true);
                              } catch (exception) {
                                setSheetState(() {
                                  error = _errorText(exception);
                                  creating = false;
                                });
                              }
                            },
                      icon: const Icon(Icons.receipt_long_outlined),
                      label: Text(
                        creating
                            ? _tr('membership.saving')
                            : _tr('membership.createInvoiceRun'),
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

    unawaited(
      Future<void>.delayed(const Duration(milliseconds: 350), () {
        runDate.dispose();
        dueDate.dispose();
        title.dispose();
      }),
    );
    if (created == true && mounted) {
      _toast(_tr('membership.invoiceRunCreated'));
    }
  }

  int _newInvoiceIdFromManagement(
    AirmiusClubManagement management, {
    required int memberId,
    required String title,
    required String amount,
  }) {
    final normalizedAmount = double.tryParse(_normalizePaymentAmount(amount));
    final candidates =
        management.invoices.where((invoice) {
          final invoiceUserId = _intFromAny(invoice['user_id']);
          final invoiceMemberId = _intFromAny(invoice['membership_user_id']);
          final invoiceExternalMemberId = _intFromAny(
            invoice['club_external_member_id'],
          );
          final invoiceTitle = _stringFromJson(invoice, ['title']);
          final invoiceAmount = double.tryParse(
            _normalizePaymentAmount('${invoice['amount'] ?? ''}'),
          );

          return (invoiceUserId == memberId ||
                  invoiceMemberId == memberId ||
                  -invoiceExternalMemberId == memberId) &&
              invoiceTitle == title &&
              (normalizedAmount == null ||
                  invoiceAmount == null ||
                  (invoiceAmount - normalizedAmount).abs() < .01);
        }).toList()..sort(
          (a, b) => _intFromAny(b['id']).compareTo(_intFromAny(a['id'])),
        );

    return candidates.isEmpty ? 0 : _intFromAny(candidates.first['id']);
  }

  Future<void> _invoiceActions(
    ClubSummary club,
    _InvoiceEntry invoice,
    List<_InvoiceEntry> invoices,
  ) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: Icon(Icons.notification_important_outlined),
              title: Text(_tr('membership.sendPaymentReminder')),
              enabled: ![
                'paid',
                'cancelled',
                'waived',
              ].contains(invoice.statusKey),
              onTap: () => Navigator.pop(context, 'reminder'),
            ),
            ListTile(
              leading: Icon(Icons.task_alt_outlined),
              title: Text(_tr('membership.recordPayment')),
              enabled: ![
                'paid',
                'cancelled',
                'waived',
              ].contains(invoice.statusKey),
              onTap: () => Navigator.pop(context, 'payment'),
            ),
            ListTile(
              leading: Icon(Icons.volunteer_activism_outlined),
              title: Text(_tr('membership.waiveInvoice')),
              subtitle: Text(_tr('membership.waiveExistingInvoiceHint')),
              enabled: ![
                'paid',
                'cancelled',
                'waived',
              ].contains(invoice.statusKey),
              onTap: () => Navigator.pop(context, 'waived'),
            ),
            ListTile(
              leading: Icon(Icons.cancel_outlined),
              title: Text(_tr('membership.cancelInvoice')),
              onTap: () => Navigator.pop(context, 'cancelled'),
            ),
          ],
        ),
      ),
    );
    if (!mounted || action == null) return;
    if (action == 'payment') {
      await _recordPayment(club, invoices, initialInvoiceId: invoice.id);
      return;
    }
    final repo = AirmiusServicesScope.of(context).repositories.clubs;
    await _runManagementAction(
      () => action == 'reminder'
          ? repo.sendClubInvoiceReminder(club.id, invoice.id)
          : repo.updateClubInvoiceStatus(club.id, invoice.id, action),
      success: action == 'reminder'
          ? _tr('membership.reminderSent')
          : _tr('membership.invoiceStatusUpdated'),
    );
  }

  Future<void> _memberActions(
    ClubSummary club,
    _MemberEntry member,
    List<_MemberEntry> members,
  ) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: AirmiusAvatar(member.name),
              title: Text(member.name),
              subtitle: Text(
                '${member.number} · '
                '${member.sepa ? _tr('membership.document.sepa') : _tr('membership.payment.bankTransfer')} · '
                '${member.balance}',
              ),
            ),
            const Divider(height: 1),
            ListTile(
              leading: Icon(Icons.edit_outlined),
              title: Text(_tr('membership.editMemberData')),
              onTap: () => Navigator.pop(context, 'edit'),
            ),
            ListTile(
              leading: Icon(Icons.history_outlined),
              title: Text(_tr('membership.timeline.title')),
              onTap: () => Navigator.pop(context, 'timeline'),
            ),
            if (!member.isExternal &&
                member.role != 'owner' &&
                (club.management?.canEditMemberPermissions ?? false))
              ListTile(
                leading: Icon(Icons.admin_panel_settings_outlined),
                title: Text(_tr('membership.access.title')),
                onTap: () => Navigator.pop(context, 'access'),
              ),
            if (member.isExternal &&
                _intOrNull(member.duplicateCandidate?['user_id']) != null)
              ListTile(
                leading: Icon(Icons.merge_outlined),
                title: Text(_tr('membership.duplicate.review')),
                subtitle: Text(
                  _tr('membership.duplicate.detected').replaceAll(
                    '{name}',
                    _stringFromJson(member.duplicateCandidate!, [
                      'name',
                    ], fallback: '-'),
                  ),
                ),
                onTap: () => Navigator.pop(context, 'merge'),
              ),
            if (member.isExternal && member.duplicateCandidate == null)
              ListTile(
                leading: Icon(Icons.mark_email_read_outlined),
                title: Text(_tr('membership.sendInvitation')),
                onTap: () => Navigator.pop(context, 'invite'),
              ),
            if (member.isExternal)
              ListTile(
                leading: Icon(Icons.person_remove_outlined),
                title: Text(_tr('membership.removeFromClub')),
                textColor: AirmiusColors.red,
                iconColor: AirmiusColors.red,
                onTap: () => Navigator.pop(context, 'remove'),
              ),
            if (!member.isExternal) ...[
              ListTile(
                leading: Icon(Icons.pin_outlined),
                title: Text(_tr('membership.generateMemberNumber')),
                onTap: () => Navigator.pop(context, 'number'),
              ),
              ListTile(
                leading: Icon(Icons.receipt_long_outlined),
                title: Text(_tr('membership.createInvoice')),
                onTap: () => Navigator.pop(context, 'invoice'),
              ),
              ListTile(
                leading: Icon(Icons.person_remove_outlined),
                title: Text(_tr('membership.removeFromClub')),
                textColor: AirmiusColors.red,
                iconColor: AirmiusColors.red,
                onTap: () => Navigator.pop(context, 'remove'),
              ),
            ],
          ],
        ),
      ),
    );
    if (!mounted || action == null) return;
    final repo = AirmiusServicesScope.of(context).repositories.clubs;
    if (action == 'invite' && member.externalId != null) {
      await _runManagementAction(
        () => repo.inviteClubExternalMember(club.id, member.externalId!),
        success: _tr('membership.invitationSent'),
      );
    } else if (action == 'merge' && member.externalId != null) {
      await _mergeDuplicate(club, member);
    } else if (action == 'timeline') {
      await _showMemberTimeline(club, member);
    } else if (action == 'number') {
      await _runManagementAction(
        () => repo.generateClubMemberNumber(club.id, member.id),
        success: _tr('membership.memberNumberGenerated'),
      );
    } else if (action == 'invoice') {
      await _createInvoice(
        club,
        members.where((entry) => !entry.isExternal).toList(),
        initialMember: member,
      );
    } else if (action == 'edit') {
      await _editMember(club, member);
    } else if (action == 'access') {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ClubAccessManagementScreen(
            clubId: club.id,
            clubName: club.name,
            memberId: member.id,
            memberName: member.name,
          ),
        ),
      );
    } else if (action == 'remove') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(_tr('membership.removeMemberQuestion')),
          content: Text(
            '${member.name} ${_tr('membership.removeMemberBodyAfter')}',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(_tr('membership.remove')),
            ),
          ],
        ),
      );
      if (confirmed == true && mounted) {
        final reason = await _askRemovalReason();
        if (!mounted || reason == null) return;

        await _runManagementAction(
          () => member.isExternal && member.externalId != null
              ? repo.removeClubExternalMember(
                  club.id,
                  member.externalId!,
                  reason: reason,
                )
              : repo.removeClubMember(club.id, member.id, reason: reason),
          success: _tr('membership.memberRemoved'),
        );
      }
    }
  }

  Future<void> _showMemberTimeline(
    ClubSummary club,
    _MemberEntry member,
  ) async {
    final subjectType = member.isExternal ? 'external_member' : 'member';
    final subjectId = member.isExternal ? member.externalId : member.id;
    if (subjectId == null) return;
    final entries =
        (_currentData?.selectedClub.management?.memberTimelineEntries ??
                const <JsonMap>[])
            .where(
              (entry) =>
                  entry['subject_type'] == subjectType &&
                  _intOrNull(entry['subject_id']) == subjectId,
            )
            .toList();

    final action = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_tr('membership.timeline.title')),
        content: SizedBox(
          width: 520,
          child: entries.isEmpty
              ? Text(_tr('membership.timeline.empty'))
              : ListView.separated(
                  shrinkWrap: true,
                  itemCount: entries.length,
                  separatorBuilder: (_, _) => const Divider(),
                  itemBuilder: (context, index) {
                    final entry = entries[index];
                    final type = _stringFromJson(entry, ['type']);
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(_stringFromJson(entry, ['title'])),
                      subtitle: Text(
                        '${_stringFromJson(entry, ['occurred_on'])} · ${_tr('membership.timeline.type.$type')}'
                        '${_stringFromJson(entry, ['description']).isEmpty ? '' : '\n${_stringFromJson(entry, ['description'])}'}',
                      ),
                      trailing:
                          const ['honor', 'anniversary', 'note'].contains(type)
                          ? IconButton(
                              tooltip: _tr('membership.timeline.delete'),
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () async {
                                final entryId = _intOrNull(entry['id']);
                                if (entryId == null) return;
                                Navigator.pop(dialogContext);
                                try {
                                  await AirmiusServicesScope.of(context)
                                      .repositories
                                      .clubs
                                      .deleteClubMemberTimelineEntry(
                                        club.id,
                                        entryId,
                                      );
                                  if (!mounted) return;
                                  _toast(_tr('membership.timeline.deleted'));
                                  _reloadClub();
                                } on Object catch (error) {
                                  if (mounted) _toast(_errorText(error));
                                }
                              },
                            )
                          : null,
                    );
                  },
                ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(_tr('membership.close')),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(dialogContext, 'add'),
            icon: const Icon(Icons.add),
            label: Text(_tr('membership.timeline.add')),
          ),
        ],
      ),
    );
    if (action == 'add' && mounted) {
      await _addMemberTimelineEntry(club, member, subjectType, subjectId);
    }
  }

  Future<void> _addMemberTimelineEntry(
    ClubSummary club,
    _MemberEntry member,
    String subjectType,
    int subjectId,
  ) async {
    var type = 'honor';
    final title = TextEditingController();
    final description = TextEditingController();
    final occurredOn = TextEditingController(
      text: _membershipDateDisplay(DateTime.now()),
    );
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('${member.name} · ${_tr('membership.timeline.add')}'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: InputDecoration(
                    labelText: _tr('membership.timeline.type'),
                  ),
                  items: const ['honor', 'anniversary', 'note']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(_tr('membership.timeline.type.$value')),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => type = value ?? type),
                ),
                TextField(
                  controller: occurredOn,
                  keyboardType: TextInputType.datetime,
                  decoration: InputDecoration(
                    labelText: _tr('membership.timeline.date'),
                    hintText: _tr('membership.dateHint'),
                  ),
                  inputFormatters: _membershipDateInputFormatters,
                ),
                TextField(
                  controller: title,
                  onChanged: (_) => setDialogState(() {}),
                  decoration: InputDecoration(
                    labelText: _tr('membership.timeline.entryTitle'),
                  ),
                ),
                TextField(
                  controller: description,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: _tr('membership.timeline.description'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: title.text.trim().isEmpty
                  ? null
                  : () => Navigator.pop(dialogContext, true),
              child: Text(_tr('membership.save')),
            ),
          ],
        ),
      ),
    );
    if (confirmed != true || !mounted) return;

    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.createClubMemberTimelineEntry(club.id, {
        'subject_type': subjectType,
        'subject_id': subjectId,
        'type': type,
        'title': title.text.trim(),
        'description': description.text.trim().isEmpty
            ? null
            : description.text.trim(),
        'occurred_on': occurredOn.text.trim(),
      });
      _toast(_tr('membership.timeline.saved'));
      _reloadClub();
    } on Object catch (error) {
      if (!mounted) return;
      _toast(_errorText(error));
    }
  }

  Future<void> _mergeDuplicate(ClubSummary club, _MemberEntry member) async {
    final candidate = member.duplicateCandidate;
    final targetUserId = _intOrNull(candidate?['user_id']);
    if (candidate == null ||
        targetUserId == null ||
        member.externalId == null) {
      return;
    }

    final email = TextEditingController();
    var resolution = 'keep_registered';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_tr('membership.duplicate.title')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${member.name} → ${_stringFromJson(candidate, ['name'], fallback: '-')}',
                ),
                const SizedBox(height: 12),
                RadioListTile<String>(
                  value: 'keep_registered',
                  groupValue: resolution,
                  title: Text(_tr('membership.duplicate.keepRegistered')),
                  subtitle: Text(
                    _tr('membership.duplicate.keepRegisteredHint'),
                  ),
                  onChanged: (value) =>
                      setDialogState(() => resolution = value ?? resolution),
                ),
                RadioListTile<String>(
                  value: 'use_external',
                  groupValue: resolution,
                  title: Text(_tr('membership.duplicate.useExternal')),
                  subtitle: Text(_tr('membership.duplicate.useExternalHint')),
                  onChanged: (value) =>
                      setDialogState(() => resolution = value ?? resolution),
                ),
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: InputDecoration(
                    labelText: _tr('membership.duplicate.confirmEmail'),
                    hintText: member.email,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(_tr('membership.duplicate.mergeNow')),
            ),
          ],
        ),
      ),
    );
    if (confirmed != true || !mounted) return;

    await _runManagementAction(
      () => AirmiusServicesScope.of(context).repositories.clubs
          .mergeClubExternalMember(club.id, member.externalId!, targetUserId, {
            'resolution': resolution,
            'confirm_email': email.text.trim(),
          }),
      success: _tr('membership.duplicate.merged'),
    );
  }

  Future<String?> _askRemovalReason() async {
    final controller = TextEditingController();
    String? errorText;

    final reason = await showDialog<String?>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) => AlertDialog(
          title: Text(_tr('membership.removeReasonTitle')),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(_tr('membership.removeReasonBody')),
              const SizedBox(height: 16),
              TextField(
                controller: controller,
                autofocus: true,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: _tr('membership.removeReason'),
                  errorText: errorText,
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: () {
                final value = controller.text.trim();
                if (value.isEmpty) {
                  setDialogState(
                    () => errorText = _tr('membership.removeReasonRequired'),
                  );
                  return;
                }
                if (value.length < 3) {
                  setDialogState(
                    () => errorText = _tr('membership.removeReasonMin'),
                  );
                  return;
                }
                Navigator.pop(dialogContext, value);
              },
              child: Text(_tr('membership.remove')),
            ),
          ],
        ),
      ),
    );

    controller.dispose();
    return reason;
  }

  Future<void> _editMemberPermissions(
    ClubSummary club,
    _MemberEntry member,
  ) async {
    try {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      final data = await repository.clubMemberPermissions(club.id, member.id);
      if (!mounted) return;

      final catalog = data['catalog'] is List
          ? (data['catalog'] as List).whereType<JsonMap>().toList()
          : <JsonMap>[];
      final effective = data['effective'] is JsonMap
          ? Map<String, dynamic>.from(data['effective'] as JsonMap)
          : <String, dynamic>{};
      final defaults = data['defaults'] is List
          ? (data['defaults'] as List).whereType<String>().toSet()
          : <String>{};
      final initialOverrides = data['overrides'] is JsonMap
          ? Map<String, dynamic>.from(data['overrides'] as JsonMap)
          : <String, dynamic>{};
      final overrides = Map<String, dynamic>.from(initialOverrides);
      final removed = <String>{};

      final payload = await showDialog<JsonMap>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: airmiusSurfaceColor(context),
              title: Text(_tr('membership.permissionsTitle')),
              content: SizedBox(
                width: 460,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _tr('membership.permissionsBody'),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                      const SizedBox(height: 8),
                      for (final item in catalog)
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            '${item['label'] ?? item['key']}',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          subtitle: overrides.containsKey(item['key'])
                              ? Text(_tr('membership.permissionsCustom'))
                              : Text(_tr('membership.permissionsDefault')),
                          value: effective[item['key']] == true,
                          onChanged: (value) {
                            final key = '${item['key']}';
                            final next = value == true;
                            setDialogState(() {
                              effective[key] = next;
                              if (next == defaults.contains(key)) {
                                overrides.remove(key);
                                removed.add(key);
                              } else {
                                overrides[key] = next;
                                removed.remove(key);
                              }
                            });
                          },
                        ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  child: Text(_tr('membership.cancel')),
                ),
                FilledButton.icon(
                  onPressed: () => Navigator.pop(dialogContext, {
                    'permissions': <String, dynamic>{
                      ...overrides,
                      for (final key in removed) key: null,
                    },
                  }),
                  icon: const Icon(Icons.save_outlined),
                  label: Text(_tr('membership.permissionsSave')),
                ),
              ],
            );
          },
        ),
      );
      if (payload == null || !mounted) return;

      await repository.updateClubMemberPermissions(club.id, member.id, payload);
      if (!mounted) return;
      _reloadClub();
      _toast(_tr('membership.permissionsUpdated'));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('membership.permissionsFailed')}: ${_errorText(error)}',
          ),
        ),
      );
    }
  }

  Future<void> _editMember(ClubSummary club, _MemberEntry member) async {
    var role = member.role;
    var status = member.status;
    var membershipTypeId = _intOrNull(
      member.membership['club_membership_type_id'],
    );
    final membershipTypes =
        _currentData?.selectedClub.management?.membershipTypes ??
        const <JsonMap>[];
    final contributionRules =
        _currentData?.selectedClub.management?.contributionRules ??
        const <JsonMap>[];
    var interval = _stringFromJson(member.membership, [
      'contribution_interval',
    ], fallback: 'none');
    var sepaActive = _boolFromAny(member.membership['sepa_mandate_active']);
    var contributionPayerUserId =
        _intOrNull(member.membership['contribution_payer_user_id']) ?? 0;
    final payerOptions = _membersFromManagement(
      _currentData?.selectedClub.management,
    ).where((entry) => !entry.isExternal).toList();
    final number = TextEditingController(
      text: _stringFromJson(member.membership, ['member_number']),
    );
    final externalName = TextEditingController(text: member.name);
    final externalEmail = TextEditingController(text: member.email);
    final phone = TextEditingController(
      text: _stringFromJson(member.membership, ['phone']),
    );
    final country = TextEditingController(
      text: _stringFromJson(member.membership, ['country']),
    );
    final street = TextEditingController(
      text: _stringFromJson(member.membership, ['street']),
    );
    final houseNumber = TextEditingController(
      text: _stringFromJson(member.membership, ['house_number']),
    );
    final postalCode = TextEditingController(
      text: _stringFromJson(member.membership, ['postal_code']),
    );
    final city = TextEditingController(
      text: _stringFromJson(member.membership, ['city']),
    );
    final licenseNumber = TextEditingController(
      text: _stringFromJson(member.membership, ['athlete_license_number']),
    );
    final licenseValidUntil = TextEditingController(
      text: _membershipDateDisplay(
        member.membership['athlete_license_valid_until'],
      ),
    );
    final amount = TextEditingController(
      text: _stringFromJson(member.membership, ['contribution_amount']),
    );
    final nextInvoice = TextEditingController(
      text: _membershipDateDisplay(
        member.membership['contribution_next_invoice_on'],
      ),
    );
    final joinedOn = TextEditingController(
      text: _membershipDateDisplay(member.membership['joined_on']),
    );
    final membershipEndsOn = TextEditingController(
      text: _membershipDateDisplay(member.membership['membership_ends_on']),
    );
    final iban = TextEditingController(
      text: _stringFromJson(member.membership, ['sepa_iban']),
    );
    final bic = TextEditingController(
      text: _stringFromJson(member.membership, ['sepa_bic']),
    );
    final mandate = TextEditingController(
      text: _stringFromJson(member.membership, ['sepa_mandate_reference']),
    );
    final mandateDate = TextEditingController(
      text: _membershipDateDisplay(member.membership['sepa_mandate_signed_on']),
    );
    final familyGroup = TextEditingController(
      text: _stringFromJson(member.membership, ['family_group_key']),
    );
    final notes = TextEditingController(
      text: _stringFromJson(member.membership, ['membership_notes']),
    );
    Widget fieldGap() => const SizedBox(height: 14);
    String dateOnly(Object? value) {
      final text = '$value'.trim();
      if (text.isEmpty || text == 'null') return '';
      return text.length >= 10 ? text.substring(0, 10) : text;
    }

    bool ruleIsCurrent(JsonMap rule) {
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day)
          .toIso8601String()
          .substring(0, 10);
      final validFrom = dateOnly(rule['valid_from']);
      final validUntil = dateOnly(rule['valid_until']);
      final factorKey = _stringFromJson(
        rule,
        ['factor_key'],
        fallback: 'standard',
      );

      return _boolFromAny(rule['is_active']) &&
          (validFrom.isEmpty || validFrom.compareTo(today) <= 0) &&
          (validUntil.isEmpty || validUntil.compareTo(today) >= 0) &&
          factorKey == 'standard';
    }

    JsonMap? matchingContributionRuleForType(int? typeId) {
      final matches = contributionRules.where(ruleIsCurrent).where((rule) {
        final ruleTypeId = _intOrNull(rule['club_membership_type_id']);

        if (typeId == null) return ruleTypeId == null;

        return ruleTypeId == typeId || ruleTypeId == null;
      }).toList()
        ..sort((a, b) {
          final aExact =
              _intOrNull(a['club_membership_type_id']) == typeId ? 1 : 0;
          final bExact =
              _intOrNull(b['club_membership_type_id']) == typeId ? 1 : 0;

          if (aExact != bExact) return bExact.compareTo(aExact);

          final priorityComparison =
              _intFromAny(b['priority']).compareTo(_intFromAny(a['priority']));

          if (priorityComparison != 0) return priorityComparison;

          return dateOnly(b['valid_from']).compareTo(dateOnly(a['valid_from']));
        });

      return matches.isEmpty ? null : matches.first;
    }

    String contributionRulePreview(int? typeId) {
      final rule = matchingContributionRuleForType(typeId);

      if (rule == null) return '';

      final amountText = _stringFromJson(rule, ['amount']);
      final intervalText = _memberIntervalLabel(
        _stringFromJson(rule, ['billing_interval'], fallback: 'none'),
      );

      return '${_tr('membership.ruleWillApply')}: $amountText EUR - $intervalText';
    }

    void applyContributionRule(int? typeId) {
      final rule = matchingContributionRuleForType(typeId);

      if (rule == null) return;

      amount.text = _stringFromJson(rule, ['amount']);
      interval = _stringFromJson(rule, ['billing_interval'], fallback: 'none');
    }

    final confirmed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (pageContext) => StatefulBuilder(
          builder: (context, setDialogState) => Scaffold(
            appBar: AppBar(
              title: Text('${member.name} ${_tr('membership.editAfter')}'),
            ),
            body: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: DefaultTabController(
              length: 3,
              child: Column(
                children: [
                  TabBar(
                    isScrollable: true,
                    tabAlignment: TabAlignment.start,
                    tabs: [
                      Tab(text: _tr('membership.member')),
                      Tab(text: _tr('membership.contribution')),
                      Tab(text: _tr('membership.payment')),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Expanded(
                    child: TabBarView(
                      children: [
                        ListView(
                          padding: EdgeInsets.zero,
                          children: [
                            if (member.isExternal) ...[
                              TextField(
                                controller: externalName,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.name'),
                                ),
                              ),
                              fieldGap(),
                              TextField(
                                controller: externalEmail,
                                keyboardType: TextInputType.emailAddress,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.email'),
                                ),
                              ),
                              fieldGap(),
                              TextField(
                                controller: phone,
                                keyboardType: TextInputType.phone,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.phone'),
                                ),
                              ),
                              fieldGap(),
                            ],
                            DropdownButtonFormField<String>(
                              initialValue: _clubRoleKeys.contains(role)
                                  ? role
                                  : 'member',
                              isExpanded: true,
                              decoration: InputDecoration(
                                labelText: _tr('membership.role'),
                              ),
                              items: [
                                for (final roleKey in _clubRoleKeys)
                                  DropdownMenuItem(
                                    value: roleKey,
                                    child: Text(
                                      _tr('membership.role.$roleKey'),
                                    ),
                                  ),
                              ],
                              onChanged: (value) =>
                                  setDialogState(() => role = value ?? role),
                            ),
                            fieldGap(),
                            DropdownButtonFormField<String>(
                              initialValue: status,
                              isExpanded: true,
                              decoration: InputDecoration(
                                labelText: _tr('membership.status'),
                              ),
                              items: [
                                DropdownMenuItem(
                                  value: 'active',
                                  child: Text(_tr('membership.status.active')),
                                ),
                                DropdownMenuItem(
                                  value: 'non_member',
                                  child: Text(
                                    _tr('membership.status.nonMember'),
                                  ),
                                ),
                                DropdownMenuItem(
                                  value: 'pending',
                                  child: Text(_tr('membership.status.pending')),
                                ),
                                DropdownMenuItem(
                                  value: 'paused',
                                  child: Text(_tr('membership.status.paused')),
                                ),
                                DropdownMenuItem(
                                  value: 'former',
                                  child: Text(_tr('membership.status.former')),
                                ),
                              ],
                              onChanged: (value) => setDialogState(
                                () => status = value ?? status,
                              ),
                            ),
                            fieldGap(),
                            DropdownButtonFormField<int?>(
                              initialValue: membershipTypeId,
                              isExpanded: true,
                              decoration: InputDecoration(
                                labelText: _tr('membership.membershipType'),
                                helperText: contributionRulePreview(
                                  membershipTypeId,
                                ).isEmpty
                                    ? null
                                    : contributionRulePreview(
                                        membershipTypeId,
                                      ),
                              ),
                              items: [
                                DropdownMenuItem<int?>(
                                  value: null,
                                  child: Text(
                                    _tr('membership.noMembershipType'),
                                  ),
                                ),
                                for (final type in membershipTypes)
                                  DropdownMenuItem<int?>(
                                    value: _intOrNull(type['id']),
                                    child: Text(
                                      _stringFromJson(type, ['name']),
                                    ),
                                  ),
                              ],
                              onChanged: (value) => setDialogState(() {
                                membershipTypeId = value;
                                applyContributionRule(value);
                              }),
                            ),
                            fieldGap(),
                            TextField(
                              controller: number,
                              decoration: InputDecoration(
                                labelText: _tr('membership.memberNumber'),
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: licenseNumber,
                              decoration: InputDecoration(
                                labelText: _tr('membership.licenseNumber'),
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: licenseValidUntil,
                              decoration: InputDecoration(
                                labelText: _tr('membership.licenseValidUntil'),
                                hintText: _tr('membership.dateHint'),
                              ),
                              keyboardType: TextInputType.datetime,
                              inputFormatters: _membershipDateInputFormatters,
                            ),
                            fieldGap(),
                            TextField(
                              controller: joinedOn,
                              keyboardType: TextInputType.datetime,
                              decoration: InputDecoration(
                                labelText: _tr('membership.joinedOn'),
                                hintText: _tr('membership.dateHint'),
                              ),
                              inputFormatters: _membershipDateInputFormatters,
                            ),
                            fieldGap(),
                            TextField(
                              controller: membershipEndsOn,
                              keyboardType: TextInputType.datetime,
                              decoration: InputDecoration(
                                labelText: _tr('membership.membershipEndsOn'),
                                hintText: _tr('membership.dateHint'),
                              ),
                              inputFormatters: _membershipDateInputFormatters,
                            ),
                            fieldGap(),
                            TextField(
                              controller: notes,
                              maxLines: 3,
                              decoration: InputDecoration(
                                labelText: _tr('membership.notes'),
                              ),
                            ),
                          ],
                        ),
                        ListView(
                          padding: EdgeInsets.zero,
                          children: [
                            TextField(
                              controller: familyGroup,
                              decoration: InputDecoration(
                                labelText: _tr('membership.familyGroupKey'),
                                helperText: _tr(
                                  'membership.familyGroupKeyHint',
                                ),
                              ),
                              textCapitalization: TextCapitalization.none,
                            ),
                            fieldGap(),
                            DropdownButtonFormField<int>(
                              initialValue: contributionPayerUserId,
                              isExpanded: true,
                              decoration: InputDecoration(
                                labelText: _tr('membership.contributionPayer'),
                                helperText: _tr(
                                  'membership.contributionPayerHint',
                                ),
                              ),
                              items: [
                                DropdownMenuItem<int>(
                                  value: 0,
                                  child: Text(_tr('membership.paysSelf')),
                                ),
                                for (final payer in payerOptions)
                                  DropdownMenuItem<int>(
                                    value: payer.id,
                                    child: Text(
                                      '${payer.name} · ${payer.email}',
                                    ),
                                  ),
                              ],
                              onChanged: (value) => setDialogState(
                                () => contributionPayerUserId = value ?? 0,
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: amount,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                  ),
                              decoration: InputDecoration(
                                labelText: _tr('membership.contributionEur'),
                              ),
                            ),
                            fieldGap(),
                            DropdownButtonFormField<String>(
                              key: ValueKey('member-interval-$interval'),
                              initialValue: interval,
                              isExpanded: true,
                              decoration: InputDecoration(
                                labelText: _tr('membership.interval'),
                              ),
                              items: [
                                DropdownMenuItem(
                                  value: 'none',
                                  child: Text(_tr('membership.interval.none')),
                                ),
                                DropdownMenuItem(
                                  value: 'monthly',
                                  child: Text(
                                    _tr('membership.interval.monthly'),
                                  ),
                                ),
                                DropdownMenuItem(
                                  value: 'quarterly',
                                  child: Text(
                                    _tr('membership.interval.quarterly'),
                                  ),
                                ),
                                DropdownMenuItem(
                                  value: 'four_monthly',
                                  child: Text(
                                    _tr('membership.interval.fourMonthly'),
                                  ),
                                ),
                                DropdownMenuItem(
                                  value: 'semi_yearly',
                                  child: Text(
                                    _tr('membership.interval.semiYearly'),
                                  ),
                                ),
                                DropdownMenuItem(
                                  value: 'yearly',
                                  child: Text(
                                    _tr('membership.interval.yearly'),
                                  ),
                                ),
                                DropdownMenuItem(
                                  value: 'once',
                                  child: Text(_tr('membership.interval.once')),
                                ),
                              ],
                              onChanged: (value) => setDialogState(
                                () => interval = value ?? interval,
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: nextInvoice,
                              keyboardType: TextInputType.datetime,
                              decoration: InputDecoration(
                                labelText: _tr('membership.nextInvoiceDate'),
                                hintText: _tr('membership.dateHint'),
                              ),
                              inputFormatters: _membershipDateInputFormatters,
                            ),
                          ],
                        ),
                        ListView(
                          padding: EdgeInsets.zero,
                          children: [
                            SwitchListTile(
                              contentPadding: EdgeInsets.zero,
                              value: sepaActive,
                              title: Text(_tr('membership.sepaMandateActive')),
                              onChanged: (value) =>
                                  setDialogState(() => sepaActive = value),
                            ),
                            fieldGap(),
                            TextField(
                              controller: iban,
                              decoration: InputDecoration(
                                labelText: _tr('membership.iban'),
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: bic,
                              decoration: InputDecoration(
                                labelText: _tr('membership.bic'),
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: mandate,
                              decoration: InputDecoration(
                                labelText: _tr('membership.mandateReference'),
                              ),
                            ),
                            fieldGap(),
                            TextField(
                              controller: mandateDate,
                              keyboardType: TextInputType.datetime,
                              decoration: InputDecoration(
                                labelText: _tr('membership.mandateDate'),
                                hintText: _tr('membership.dateHint'),
                              ),
                              inputFormatters: _membershipDateInputFormatters,
                            ),
                            if (member.isExternal) ...[
                              fieldGap(),
                              TextField(
                                controller: street,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.street'),
                                ),
                              ),
                              fieldGap(),
                              TextField(
                                controller: houseNumber,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.houseNumber'),
                                ),
                              ),
                              fieldGap(),
                              TextField(
                                controller: postalCode,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.postalCode'),
                                ),
                              ),
                              fieldGap(),
                              TextField(
                                controller: city,
                                decoration: InputDecoration(
                                  labelText: _tr('membership.city'),
                                ),
                              ),
                              fieldGap(),
                              CountryField(
                                controller: country,
                                label: _tr('clubs.wizard.country'),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
                ),
              ),
            ),
            bottomNavigationBar: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: () => Navigator.pop(pageContext, false),
                        child: Text(_tr('membership.cancel')),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: () => Navigator.pop(pageContext, true),
                        child: Text(_tr('membership.save')),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
    if (confirmed == true && mounted) {
      final payload = <String, dynamic>{
        'role': role,
        'roles': [role],
        'membership_status': status,
        'club_membership_type_id': membershipTypeId,
        'member_number': number.text.trim().isEmpty ? null : number.text.trim(),
        'athlete_license_number': licenseNumber.text.trim().isEmpty
            ? null
            : licenseNumber.text.trim(),
        'athlete_license_valid_until': licenseValidUntil.text.trim().isEmpty
            ? null
            : _membershipDateApi(licenseValidUntil.text),
        'family_group_key': familyGroup.text.trim().isEmpty
            ? null
            : familyGroup.text.trim(),
        'contribution_payer_user_id':
            contributionPayerUserId == 0 || contributionPayerUserId == member.id
            ? null
            : contributionPayerUserId,
        'contribution_amount': amount.text.trim().isEmpty
            ? null
            : _normalizePaymentAmount(amount.text),
        'contribution_interval': interval,
        'contribution_next_invoice_on': nextInvoice.text.trim().isEmpty
            ? null
            : _membershipDateApi(nextInvoice.text),
        'sepa_iban': iban.text.trim().isEmpty ? null : iban.text.trim(),
        'sepa_bic': bic.text.trim().isEmpty ? null : bic.text.trim(),
        'sepa_mandate_reference': mandate.text.trim().isEmpty
            ? null
            : mandate.text.trim(),
        'sepa_mandate_signed_on': mandateDate.text.trim().isEmpty
            ? null
            : _membershipDateApi(mandateDate.text),
        'sepa_mandate_active': sepaActive,
        'joined_on': joinedOn.text.trim().isEmpty
            ? null
            : _membershipDateApi(joinedOn.text),
        'membership_ends_on': membershipEndsOn.text.trim().isEmpty
            ? null
            : _membershipDateApi(membershipEndsOn.text),
        'membership_notes': notes.text.trim().isEmpty
            ? null
            : notes.text.trim(),
      };
      if (member.isExternal) {
        payload.addAll({
          'name': externalName.text.trim().isEmpty
              ? null
              : externalName.text.trim(),
          'email': externalEmail.text.trim(),
          'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
          'country': country.text.trim().isEmpty
              ? null
              : country.text.trim().toUpperCase(),
          'street': street.text.trim().isEmpty ? null : street.text.trim(),
          'house_number': houseNumber.text.trim().isEmpty
              ? null
              : houseNumber.text.trim(),
          'postal_code': postalCode.text.trim().isEmpty
              ? null
              : postalCode.text.trim(),
          'city': city.text.trim().isEmpty ? null : city.text.trim(),
        });
      }
      await _runManagementAction(
        () => member.isExternal && member.externalId != null
            ? AirmiusServicesScope.of(
                context,
              ).repositories.clubs.updateClubExternalMember(
                club.id,
                member.externalId!,
                payload,
              )
            : AirmiusServicesScope.of(context).repositories.clubs
                  .updateClubMember(club.id, member.id, payload),
        success: _tr('membership.memberDataSaved'),
      );
    }
    for (final controller in [
      externalName,
      externalEmail,
      phone,
      country,
      street,
      houseNumber,
      postalCode,
      city,
      number,
      licenseNumber,
      licenseValidUntil,
      amount,
      nextInvoice,
      joinedOn,
      membershipEndsOn,
      iban,
      bic,
      mandate,
      mandateDate,
      familyGroup,
      notes,
    ]) {
      controller.dispose();
    }
  }

  Future<void> _configureAccounting(
    ClubSummary club, {
    required bool sepa,
    required JsonMap settings,
  }) async {
    final first = TextEditingController(
      text: _stringFromJson(settings, [
        sepa ? 'sepa_creditor_id' : 'datev_consultant_number',
      ]),
    );
    final second = TextEditingController(
      text: _stringFromJson(settings, [
        sepa ? 'sepa_account_holder' : 'datev_client_number',
      ]),
    );
    final third = TextEditingController(
      text: _stringFromJson(settings, [
        sepa ? 'sepa_iban' : 'datev_revenue_account',
      ]),
    );
    final fourth = TextEditingController(
      text: _stringFromJson(settings, [
        sepa ? 'sepa_bic' : 'datev_bank_account',
      ]),
    );
    final feeAccount = TextEditingController(
      text: _stringFromJson(settings, ['datev_fee_account']),
    );
    final feeLabels =
        sepaFeeLabels[AirmiusScope.of(context).language.locale.languageCode] ??
        sepaFeeLabels['en']!;
    final labels = sepa
        ? [
            _tr('membership.creditorId'),
            _tr('membership.accountHolder'),
            _tr('membership.clubIban'),
            _tr('membership.bic'),
          ]
        : [
            _tr('membership.consultantNumber'),
            _tr('membership.clientNumber'),
            _tr('membership.revenueAccount'),
            _tr('membership.bankAccount'),
          ];
    final dialog = DialogRoute<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          sepa
              ? _tr('membership.configureSepa')
              : _tr('membership.configureDatev'),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (final pair in [
                (first, labels[0]),
                (second, labels[1]),
                (third, labels[2]),
                (fourth, labels[3]),
                if (!sepa) (feeAccount, feeLabels['exportAccount']!),
              ])
                TextField(
                  controller: pair.$1,
                  decoration: InputDecoration(labelText: pair.$2),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_tr('membership.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_tr('membership.save')),
          ),
        ],
      ),
    );
    final confirmed = await Navigator.of(
      context,
      rootNavigator: true,
    ).push(dialog);
    await dialog.completed;
    if (confirmed == true && mounted) {
      final repo = AirmiusServicesScope.of(context).repositories.clubs;
      await _runManagementAction(
        () => sepa
            ? repo.updateClubSepaSettings(club.id, {
                'sepa_creditor_id': first.text.trim(),
                'sepa_account_holder': second.text.trim(),
                'sepa_iban': third.text.trim(),
                'sepa_bic': fourth.text.trim(),
              })
            : repo.updateClubDatevSettings(club.id, {
                'datev_consultant_number': first.text.trim(),
                'datev_client_number': second.text.trim(),
                'datev_revenue_account': third.text.trim(),
                'datev_bank_account': fourth.text.trim(),
                'datev_fee_account': feeAccount.text.trim(),
              }),
        success:
            '${sepa ? 'SEPA' : 'DATEV'} ${_tr('membership.settingsSavedAfter')}',
      );
    }
    first.dispose();
    second.dispose();
    third.dispose();
    fourth.dispose();
    feeAccount.dispose();
  }

  String _paymentMethodLabel(String method) {
    return switch (method) {
      'cash' => _tr('membership.payment.cash'),
      'bank_transfer' => _tr('membership.payment.bankTransfer'),
      'sepa_debit' => _tr('membership.payment.sepaDebit'),
      'manual' => _tr('membership.payment.manual'),
      _ => method,
    };
  }

  String _memberIntervalLabel(String value) {
    return switch (value) {
      'none' => _tr('membership.interval.none'),
      'monthly' => _tr('membership.interval.monthly'),
      'quarterly' => _tr('membership.interval.quarterly'),
      'four_monthly' => _tr('membership.interval.fourMonthly'),
      'semi_yearly' => _tr('membership.interval.semiYearly'),
      'yearly' => _tr('membership.interval.yearly'),
      'once' => _tr('membership.interval.once'),
      _ => value,
    };
  }

  String _membershipStatusLabel(String value) {
    return switch (value) {
      'active' => _tr('membership.status.active'),
      'pending' => _tr('membership.status.pending'),
      'paused' => _tr('membership.status.paused'),
      'former' => _tr('membership.status.former'),
      'non_member' => _tr('membership.status.nonMember'),
      _ => value,
    };
  }

  String _membershipImportActionLabel(String value) {
    return switch (value) {
      'create_external_member' => _tr('membership.importAction.create'),
      'update_external_member' => _tr('membership.importAction.update'),
      'link_existing_user' => _tr('membership.importAction.link'),
      _ => _tr('membership.importAction.import'),
    };
  }

  List<String> _membershipImportPreviewDetails(Map row) {
    const hiddenKeys = {
      'row',
      'name',
      'email',
      'action',
      'existing_user',
      'existing_external',
    };
    final details = <String>[];
    for (final entry in row.entries) {
      final key = '${entry.key}';
      final value = entry.value;
      if (hiddenKeys.contains(key) ||
          value == null ||
          '$value'.trim().isEmpty) {
        continue;
      }
      details.add(
        '${_membershipImportFieldLabel(key)}: ${_membershipImportValueLabel(key, value)}',
      );
    }
    return details;
  }

  String _membershipImportFieldLabel(String key) {
    return switch (key) {
      'membership_status' => _tr('membership.status'),
      'family_group_key' => _tr('membership.familyGroupKey'),
      'contribution_amount' => _tr('membership.contributionEurShort'),
      'contribution_interval' => _tr('membership.interval'),
      _ =>
        key
            .replaceAll('_', ' ')
            .split(' ')
            .where((part) => part.isNotEmpty)
            .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
            .join(' '),
    };
  }

  String _membershipImportValueLabel(String key, Object value) {
    return switch (key) {
      'membership_status' => _membershipStatusLabel('$value'),
      'contribution_interval' => _memberIntervalLabel('$value'),
      'contribution_amount' => '$value EUR',
      _ => '$value',
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

  String _dateOnly(DateTime value) =>
      '${value.year}-${_twoDigits(value.month)}-${_twoDigits(value.day)}';

  String _dateDisplay(DateTime value) =>
      '${_twoDigits(value.day)}.${_twoDigits(value.month)}.${value.year}';

  DateTime? _dateOnlyFromValue(Object? value) {
    if (value == null) return null;
    final normalized = _dateInputForApi('$value');
    if (normalized == null || normalized.isEmpty) return null;
    final parsed = DateTime.tryParse(normalized);
    if (parsed == null) return null;
    return DateTime(parsed.year, parsed.month, parsed.day);
  }

  int? _daysUntil(Object? value) {
    final target = _dateOnlyFromValue(value);
    if (target == null) return null;
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    return target.difference(today).inDays;
  }

  String _dueLabel(int? days) {
    if (days == null) return _tr('membership.schedule.noDate');
    if (days < 0) {
      return _tr(
        'membership.schedule.overdue',
      ).replaceAll('{days}', '${days.abs()}');
    }
    if (days == 0) return _tr('membership.schedule.today');
    if (days <= 14) {
      return _tr('membership.schedule.inDays').replaceAll('{days}', '$days');
    }
    return _tr('membership.schedule.later');
  }

  Color _dueColor(int? days) {
    if (days == null) return airmiusMutedColor(context);
    if (days < 0) return AirmiusColors.red;
    if (days <= 14) return AirmiusColors.amber;
    return AirmiusColors.green;
  }

  List<_PaymentScheduleEntry> _paymentScheduleFromMembers(
    List<_MemberEntry> members,
  ) {
    final schedule = members
        .where((member) {
          final interval = _stringFromJson(member.membership, [
            'contribution_interval',
          ], fallback: 'none');
          return !member.isExternal &&
              member.status == 'active' &&
              interval != 'none' &&
              interval.isNotEmpty;
        })
        .map((member) {
          final dueDate = _dateOnlyFromValue(
            member.membership['contribution_next_invoice_on'],
          );
          final days = _daysUntil(
            member.membership['contribution_next_invoice_on'],
          );
          return _PaymentScheduleEntry(
            member: member,
            dueDate: dueDate,
            days: days,
            amount: _moneyFromValue(member.membership['contribution_amount']),
            interval: _stringFromJson(member.membership, [
              'contribution_interval',
            ], fallback: 'none'),
            paymentMethod: _stringFromJson(member.membership, [
              'payment_method',
            ], fallback: ''),
          );
        })
        .toList();
    schedule.sort((a, b) {
      final byDate = (a.days ?? 999999).compareTo(b.days ?? 999999);
      if (byDate != 0) return byDate;
      return a.member.name.compareTo(b.member.name);
    });
    return schedule;
  }

  String? _dateInputForApi(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return null;

    final german = RegExp(
      r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$',
    ).firstMatch(trimmed);
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

  String _dateRangeLabel(DateTimeRange range) =>
      '${_dateDisplay(range.start)} ${_tr('membership.to')} '
      '${_dateDisplay(range.end)}';

  String _memberPickerLabel(_MemberEntry member) {
    return member.email.trim().isEmpty
        ? member.name
        : '${member.name} - ${member.email}';
  }

  _MemberEntry _memberById(List<_MemberEntry> members, int memberId) {
    return members.firstWhere(
      (member) => member.id == memberId,
      orElse: () => members.first,
    );
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
        decoration: InputDecoration(
          labelText: _tr('membership.member'),
          prefixIcon: Icon(Icons.search_outlined),
          suffixIcon: Icon(Icons.expand_more),
        ),
        child: Text(
          _memberPickerLabel(selected),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }

  Future<_MemberEntry?> _pickMember(
    BuildContext context,
    List<_MemberEntry> members,
    int selectedMemberId,
  ) async {
    final search = TextEditingController();
    try {
      return await showModalBottomSheet<_MemberEntry>(
        context: context,
        isScrollControlled: true,
        backgroundColor: airmiusSurfaceColor(context),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        builder: (sheetContext) => StatefulBuilder(
          builder: (context, setSheetState) {
            final query = search.text.trim().toLowerCase();
            final filtered = query.isEmpty
                ? members
                : members.where((member) {
                    final haystack =
                        '${member.name} ${member.email} ${member.number}'
                            .toLowerCase();
                    return haystack.contains(query);
                  }).toList();

            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 16,
                  top: 16,
                  bottom: 16 + MediaQuery.of(sheetContext).viewInsets.bottom,
                ),
                child: SizedBox(
                  height: MediaQuery.of(sheetContext).size.height * .72,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        _tr('membership.chooseMember'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: _tr('membership.search'),
                        hint: _tr('membership.memberSearchHint'),
                        icon: Icons.search_outlined,
                        controller: search,
                        onChanged: (_) => setSheetState(() {}),
                      ),
                      const SizedBox(height: 12),
                      Expanded(
                        child: filtered.isEmpty
                            ? Center(
                                child: Text(
                                  _tr('membership.noMemberFound'),
                                  style: TextStyle(
                                    color: airmiusMutedColor(context),
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              )
                            : ListView.separated(
                                itemCount: filtered.length,
                                separatorBuilder: (_, _) =>
                                    const SizedBox(height: 8),
                                itemBuilder: (context, index) {
                                  final member = filtered[index];
                                  final selected =
                                      member.id == selectedMemberId;
                                  return ListTile(
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(14),
                                      side: BorderSide(
                                        color: selected
                                            ? airmiusAccentColor(context)
                                            : airmiusBorderColor(context),
                                      ),
                                    ),
                                    tileColor: selected
                                        ? airmiusAccentColor(
                                            context,
                                          ).withValues(alpha: .18)
                                        : airmiusSurfaceSoftColor(context),
                                    leading: CircleAvatar(
                                      backgroundColor: selected
                                          ? airmiusAccentColor(context)
                                          : airmiusInputColor(context),
                                      foregroundColor: Colors.white,
                                      child: Text(
                                        member.name.isEmpty
                                            ? '?'
                                            : member.name
                                                  .substring(0, 1)
                                                  .toUpperCase(),
                                      ),
                                    ),
                                    title: Text(
                                      member.name,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: airmiusTextColor(context),
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    subtitle: Text(
                                      [member.email, member.number]
                                          .where(
                                            (value) => value.trim().isNotEmpty,
                                          )
                                          .join(' - '),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: airmiusMutedColor(context),
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    trailing: selected
                                        ? Icon(
                                            Icons.check_circle,
                                            color: airmiusAccentColor(context),
                                          )
                                        : null,
                                    onTap: () =>
                                        Navigator.pop(sheetContext, member),
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

  Widget _invoicePickerField({
    required BuildContext context,
    required List<_InvoiceEntry> invoices,
    required int selectedInvoiceId,
    required ValueChanged<_InvoiceEntry> onChanged,
  }) {
    final selected = invoices.firstWhere(
      (invoice) => invoice.id == selectedInvoiceId,
      orElse: () => invoices.first,
    );
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: () async {
        final picked = await _pickInvoice(context, invoices, selectedInvoiceId);
        if (picked != null) onChanged(picked);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: _tr('membership.invoice'),
          prefixIcon: Icon(Icons.receipt_long_outlined),
          suffixIcon: Icon(Icons.expand_more),
        ),
        child: _InvoicePickerSummary(invoice: selected),
      ),
    );
  }

  Future<_InvoiceEntry?> _pickInvoice(
    BuildContext context,
    List<_InvoiceEntry> invoices,
    int selectedInvoiceId,
  ) async {
    final search = TextEditingController();
    try {
      return await showModalBottomSheet<_InvoiceEntry>(
        context: context,
        isScrollControlled: true,
        backgroundColor: airmiusSurfaceColor(context),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        builder: (sheetContext) => StatefulBuilder(
          builder: (context, setSheetState) {
            final query = search.text.trim().toLowerCase();
            final filtered = query.isEmpty
                ? invoices
                : invoices.where((invoice) {
                    final haystack =
                        '${invoice.title} ${invoice.person} ${invoice.amount} ${invoice.outstandingAmount} ${invoice.status}'
                            .toLowerCase();
                    return haystack.contains(query);
                  }).toList();

            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 16,
                  top: 16,
                  bottom: 16 + MediaQuery.of(sheetContext).viewInsets.bottom,
                ),
                child: SizedBox(
                  height: MediaQuery.of(sheetContext).size.height * .76,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        _tr('membership.chooseInvoice'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: _tr('membership.search'),
                        hint: _tr('membership.invoiceSearchHint'),
                        icon: Icons.search_outlined,
                        controller: search,
                        onChanged: (_) => setSheetState(() {}),
                      ),
                      const SizedBox(height: 12),
                      Expanded(
                        child: filtered.isEmpty
                            ? Center(
                                child: Text(
                                  _tr('membership.noInvoicesLoaded'),
                                  style: TextStyle(
                                    color: airmiusMutedColor(context),
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              )
                            : ListView.separated(
                                itemCount: filtered.length,
                                separatorBuilder: (_, _) =>
                                    const SizedBox(height: 10),
                                itemBuilder: (context, index) {
                                  final invoice = filtered[index];
                                  return _InvoiceChoiceCard(
                                    invoice: invoice,
                                    selected: invoice.id == selectedInvoiceId,
                                    onTap: () =>
                                        Navigator.pop(sheetContext, invoice),
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

  Future<void> _recordPayment(
    ClubSummary club,
    List<_InvoiceEntry> invoices, {
    int? initialInvoiceId,
  }) async {
    final openInvoices = invoices
        .where(
          (invoice) =>
              invoice.id > 0 &&
              !['paid', 'cancelled', 'waived'].contains(invoice.statusKey),
        )
        .toList();
    if (openInvoices.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_tr('membership.noOpenInvoice'))));
      return;
    }

    var selectedInvoiceId =
        openInvoices.any((invoice) => invoice.id == initialInvoiceId)
        ? initialInvoiceId!
        : openInvoices.first.id;
    final initiallySelected = openInvoices.firstWhere(
      (invoice) => invoice.id == selectedInvoiceId,
    );
    var method = 'cash';
    final amount = TextEditingController(
      text: _paymentAmountInput(initiallySelected.outstandingAmount),
    );
    final paidAt = TextEditingController(text: _dateDisplay(DateTime.now()));
    final reference = TextEditingController();
    final notes = TextEditingController();

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              _tr('membership.recordPayment'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _invoicePickerField(
                    context: dialogContext,
                    invoices: openInvoices,
                    selectedInvoiceId: selectedInvoiceId,
                    onChanged: (selected) {
                      setDialogState(() {
                        selectedInvoiceId = selected.id;
                        amount.text = _paymentAmountInput(
                          selected.outstandingAmount,
                        );
                      });
                    },
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.amountEur'),
                    hint: '0,00',
                    controller: amount,
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: method,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.paymentMethod'),
                    ),
                    items: const ['cash', 'bank_transfer']
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item,
                            child: Text(_paymentMethodLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => method = value ?? method),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.paidOn'),
                    hint: _tr('membership.dateHint'),
                    controller: paidAt,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.reference'),
                    hint: _tr('membership.optional'),
                    controller: reference,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.note'),
                    hint: _tr('membership.optional'),
                    controller: notes,
                    maxLines: 2,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.cancel')),
              ),
              FilledButton.icon(
                onPressed: () => Navigator.pop(dialogContext, {
                  'invoice_id': selectedInvoiceId,
                  'amount': amount.text.trim().isEmpty
                      ? null
                      : _normalizePaymentAmount(amount.text),
                  'method': method,
                  'paid_at': _dateInputForApi(paidAt.text),
                  'reference': reference.text.trim().isEmpty
                      ? null
                      : reference.text.trim(),
                  'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
                }),
                icon: Icon(Icons.payments_outlined),
                label: Text(_tr('membership.save')),
              ),
            ],
          );
        },
      ),
    );

    unawaited(
      Future<void>.delayed(const Duration(milliseconds: 350), () {
        amount.dispose();
        paidAt.dispose();
        reference.dispose();
        notes.dispose();
      }),
    );

    if (payload == null) return;
    if (!mounted) return;

    try {
      final invoiceId = _intFromAny(payload['invoice_id']);
      final management = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.recordMembershipPayment(club.id, invoiceId, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('membership.paymentRecordedBefore')} '
            '${_paymentMethodLabel('${payload['method']}')} '
            '${_tr('membership.recordedAfter')}',
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      final message = _errorText(error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_tr('membership.paymentRecordFailed')}: $message'),
        ),
      );
    }
  }

  Future<void> _recordDonation(
    ClubSummary club,
    List<_MemberEntry> members,
  ) async {
    final availableMembers = members.where((member) => member.id > 0).toList();
    if (availableMembers.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_tr('membership.noDonationMembers'))),
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
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              _tr('membership.recordDonation'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _memberPickerField(
                    context: dialogContext,
                    members: availableMembers,
                    selectedMemberId: selectedMemberId,
                    onChanged: (value) =>
                        setDialogState(() => selectedMemberId = value),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.amountEur'),
                    hint: '0,00',
                    controller: amount,
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: method,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.paymentMethod'),
                    ),
                    items: const ['cash', 'bank_transfer']
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item,
                            child: Text(_paymentMethodLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => method = value ?? method),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.receivedOn'),
                    hint: _tr('membership.dateHint'),
                    controller: paidAt,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.reference'),
                    hint: _tr('membership.optional'),
                    controller: reference,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.note'),
                    hint: _tr('membership.optional'),
                    controller: notes,
                    maxLines: 2,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.cancel')),
              ),
              FilledButton.icon(
                onPressed: () {
                  if (amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                    'user_id': selectedMemberId,
                    'amount': _normalizePaymentAmount(amount.text),
                    'method': method,
                    'paid_at': _dateInputForApi(paidAt.text),
                    'reference': reference.text.trim().isEmpty
                        ? null
                        : reference.text.trim(),
                    'notes': notes.text.trim().isEmpty
                        ? null
                        : notes.text.trim(),
                  });
                },
                icon: Icon(Icons.volunteer_activism_outlined),
                label: Text(_tr('membership.save')),
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
    if (!mounted) return;

    try {
      final management = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.recordDonation(club.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('membership.donationRecordedBefore')} '
            '${_paymentMethodLabel('${payload['method']}')} '
            '${_tr('membership.recordedAfter')}',
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      final message = _errorText(error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_tr('membership.donationRecordFailed')}: $message'),
        ),
      );
    }
  }

  Future<void> _recordPrepayment(
    ClubSummary club,
    List<_MemberEntry> members,
  ) async {
    final availableMembers = members.where((member) => member.id > 0).toList();
    if (availableMembers.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_tr('membership.noPrepaymentMembers'))),
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
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              _tr('membership.recordPrepayment'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _memberPickerField(
                    context: dialogContext,
                    members: availableMembers,
                    selectedMemberId: selectedMemberId,
                    onChanged: (value) =>
                        setDialogState(() => selectedMemberId = value),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.amountEur'),
                    hint: '120,00',
                    controller: amount,
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: method,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.paymentMethod'),
                    ),
                    items: const ['cash', 'bank_transfer']
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item,
                            child: Text(_paymentMethodLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => method = value ?? method),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.receivedOn'),
                    hint: _tr('membership.dateHint'),
                    controller: paidAt,
                  ),
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
                        helpText: _tr('membership.choosePeriod'),
                        saveText: _tr('membership.apply'),
                      );
                      if (picked != null) {
                        setDialogState(() => coverageRange = picked);
                      }
                    },
                    child: InputDecorator(
                      decoration: InputDecoration(
                        labelText: _tr('membership.appliesTo'),
                        suffixIcon: Icon(Icons.date_range_outlined),
                      ),
                      child: Text(
                        coverageRange == null
                            ? _tr('membership.choosePeriod')
                            : _dateRangeLabel(coverageRange!),
                        style: TextStyle(
                          color: coverageRange == null
                              ? airmiusMutedColor(context)
                              : airmiusTextColor(context),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.reference'),
                    hint: _tr('membership.optional'),
                    controller: reference,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.note'),
                    hint: _tr('membership.optional'),
                    controller: notes,
                    maxLines: 2,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.cancel')),
              ),
              FilledButton.icon(
                onPressed: () {
                  if (amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                    'user_id': selectedMemberId,
                    'amount': _normalizePaymentAmount(amount.text),
                    'method': method,
                    'paid_at': _dateInputForApi(paidAt.text),
                    'coverage_start': coverageRange == null
                        ? null
                        : _dateOnly(coverageRange!.start),
                    'coverage_end': coverageRange == null
                        ? null
                        : _dateOnly(coverageRange!.end),
                    'coverage_note': coverageRange == null
                        ? null
                        : _dateRangeLabel(coverageRange!),
                    'reference': reference.text.trim().isEmpty
                        ? null
                        : reference.text.trim(),
                    'notes': notes.text.trim().isEmpty
                        ? null
                        : notes.text.trim(),
                  });
                },
                icon: Icon(Icons.account_balance_wallet_outlined),
                label: Text(_tr('membership.save')),
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
    if (!mounted) return;

    try {
      final management = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.recordPrepayment(club.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('membership.prepaymentRecordedBefore')} '
            '${_paymentMethodLabel('${payload['method']}')} '
            '${_tr('membership.recordedAfter')}',
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      final message = _errorText(error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('membership.prepaymentRecordFailed')}: $message',
          ),
        ),
      );
    }
  }

  Future<void> _editPayment(
    ClubSummary club,
    _PaymentEntry payment,
    List<_MemberEntry> members,
  ) async {
    if (payment.id <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_tr('membership.paymentMissingId'))),
      );
      return;
    }

    final availableMembers = members.where((member) => member.id > 0).toList();
    final canChangeMember =
        payment.invoiceId <= 0 && availableMembers.isNotEmpty;
    var selectedMemberId = payment.userId;
    if (canChangeMember &&
        !availableMembers.any((member) => member.id == selectedMemberId)) {
      selectedMemberId = availableMembers.first.id;
    }

    const methodOptions = ['cash', 'bank_transfer', 'sepa_debit', 'manual'];
    var method = methodOptions.contains(payment.methodKey)
        ? payment.methodKey
        : 'manual';
    final amount = TextEditingController(text: payment.amountInput);
    final paidAt = TextEditingController(
      text: payment.paidAtInput == '-'
          ? _dateDisplay(DateTime.now())
          : payment.paidAtInput,
    );
    final reference = TextEditingController(text: payment.reference);
    final notes = TextEditingController(text: payment.notes);

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              '${payment.title} ${_tr('membership.editAfter')}',
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (canChangeMember) ...[
                    _memberPickerField(
                      context: dialogContext,
                      members: availableMembers,
                      selectedMemberId: selectedMemberId,
                      onChanged: (value) =>
                          setDialogState(() => selectedMemberId = value),
                    ),
                    const SizedBox(height: 10),
                  ] else ...[
                    InputDecorator(
                      decoration: InputDecoration(
                        labelText: _tr('membership.member'),
                      ),
                      child: Text(
                        payment.person,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                  ],
                  AirmiusTextField(
                    label: _tr('membership.amountEur'),
                    hint: '0,00',
                    controller: amount,
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: method,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.paymentMethod'),
                    ),
                    items: methodOptions
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item,
                            child: Text(_paymentMethodLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => method = value ?? method),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.receivedOn'),
                    hint: _tr('membership.dateHint'),
                    controller: paidAt,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.reference'),
                    hint: _tr('membership.optional'),
                    controller: reference,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.notePeriod'),
                    hint: _tr('membership.optional'),
                    controller: notes,
                    maxLines: 3,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.cancel')),
              ),
              FilledButton.icon(
                onPressed: () {
                  if (amount.text.trim().isEmpty) return;
                  Navigator.pop(dialogContext, {
                    if (canChangeMember) 'user_id': selectedMemberId,
                    'amount': _normalizePaymentAmount(amount.text),
                    'method': method,
                    'paid_at': _dateInputForApi(paidAt.text),
                    'reference': reference.text.trim().isEmpty
                        ? null
                        : reference.text.trim(),
                    'notes': notes.text.trim().isEmpty
                        ? null
                        : notes.text.trim(),
                  });
                },
                icon: Icon(Icons.save_outlined),
                label: Text(_tr('membership.save')),
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
    if (!mounted) return;

    try {
      final management = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.updatePayment(club.id, payment.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_tr('membership.paymentUpdated'))));
    } catch (error) {
      if (!mounted) return;
      final message = _errorText(error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_tr('membership.paymentUpdateFailed')}: $message'),
        ),
      );
    }
  }

  Future<void> _editFinanceEntry(
    ClubSummary club, {
    String initialType = 'expense',
    _FinanceEntry? entry,
  }) async {
    final isEdit = entry != null && entry.id > 0;
    const typeOptions = ['income', 'expense'];
    const accountOptions = ['cash', 'bank'];
    var type = typeOptions.contains(entry?.type) ? entry!.type : initialType;
    var account = accountOptions.contains(entry?.account)
        ? entry!.account
        : 'cash';
    final title = TextEditingController(text: entry?.title ?? '');
    final category = TextEditingController(text: entry?.category ?? '');
    final amount = TextEditingController(text: entry?.amountInput ?? '');
    final bookedOn = TextEditingController(
      text: entry == null || entry.bookedOnInput == '-'
          ? _dateDisplay(DateTime.now())
          : entry.bookedOnInput,
    );
    final reference = TextEditingController(text: entry?.reference ?? '');
    final description = TextEditingController(text: entry?.description ?? '');

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              isEdit
                  ? _tr('membership.editBooking')
                  : '${_financeTypeLabel(type)} '
                        '${_tr('membership.bookAfter')}',
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: type,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.type'),
                    ),
                    items: typeOptions
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item,
                            child: Text(_financeTypeLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setDialogState(() {
                      type = value ?? type;
                      final categoryText = category.text.trim();
                      final options = _financeCategoryOptions(type);
                      if (categoryText.isNotEmpty &&
                          !options.any(
                            (item) =>
                                item.toLowerCase() ==
                                categoryText.toLowerCase(),
                          )) {
                        category.clear();
                      }
                    }),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: account,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.account'),
                    ),
                    items: accountOptions
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item,
                            child: Text(_financeAccountLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => account = value ?? account),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.invoiceTitle'),
                    hint: _tr('membership.bookingTitleHint'),
                    controller: title,
                  ),
                  const SizedBox(height: 10),
                  InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () async {
                      final selected = await _pickFinanceCategory(
                        dialogContext,
                        type: type,
                        current: category.text,
                      );
                      if (selected == null) return;
                      setDialogState(() => category.text = selected);
                    },
                    child: InputDecorator(
                      isEmpty: category.text.trim().isEmpty,
                      decoration: InputDecoration(
                        labelText: _tr('membership.category'),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              category.text.trim().isEmpty
                                  ? _tr('membership.chooseCategory')
                                  : _financeCategoryLabel(category.text.trim()),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: category.text.trim().isEmpty
                                    ? airmiusMutedColor(context)
                                    : airmiusTextColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Icon(
                            Icons.search_outlined,
                            color: airmiusMutedColor(context),
                            size: 20,
                          ),
                          const SizedBox(width: 4),
                          Icon(
                            Icons.arrow_drop_down,
                            color: airmiusMutedColor(context),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.amountEur'),
                    hint: '0,00',
                    controller: amount,
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.date'),
                    hint: _tr('membership.dateHint'),
                    controller: bookedOn,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.reference'),
                    hint: _tr('membership.referenceHint'),
                    controller: reference,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.description'),
                    hint: _tr('membership.optional'),
                    controller: description,
                    maxLines: 3,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_tr('membership.cancel')),
              ),
              FilledButton.icon(
                onPressed: () {
                  if (title.text.trim().isEmpty || amount.text.trim().isEmpty) {
                    return;
                  }
                  Navigator.pop(dialogContext, {
                    'type': type,
                    'account': account,
                    'title': title.text.trim(),
                    'category': category.text.trim().isEmpty
                        ? null
                        : category.text.trim(),
                    'amount': _normalizePaymentAmount(amount.text),
                    'booked_on': _dateInputForApi(bookedOn.text),
                    'reference': reference.text.trim().isEmpty
                        ? null
                        : reference.text.trim(),
                    'description': description.text.trim().isEmpty
                        ? null
                        : description.text.trim(),
                  });
                },
                icon: Icon(Icons.save_outlined),
                label: Text(_tr('membership.save')),
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
    if (!mounted) return;

    try {
      final repositories = AirmiusServicesScope.of(context).repositories.clubs;
      final management = isEdit
          ? await repositories.updateFinanceEntry(club.id, entry.id, payload)
          : await repositories.createFinanceEntry(club.id, payload);
      if (!mounted) return;
      _applyManagement(management);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isEdit
                ? _tr('membership.bookingUpdated')
                : '${_financeTypeLabel('${payload['type']}')} '
                      '${_tr('membership.bookedAfter')}',
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      final message = _errorText(error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_tr('membership.bookingSaveFailed')}: $message'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return FutureBuilder<_ManagedMembershipData?>(
      future: _clubFuture,
      initialData: _currentData,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting &&
            snapshot.data == null) {
          return Scaffold(
            backgroundColor: Theme.of(context).scaffoldBackgroundColor,
            body: PageFrame(
              title: _pageTitle,
              subtitle: t('membership.permissionsChecking'),
              child: AirmiusPanel(
                child: Center(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: CircularProgressIndicator(
                      color: airmiusAccentColor(context),
                    ),
                  ),
                ),
              ),
            ),
          );
        }

        if (snapshot.hasError || snapshot.data == null) {
          return Scaffold(
            backgroundColor: Theme.of(context).scaffoldBackgroundColor,
            appBar: AppBar(
              backgroundColor:
                  Theme.of(context).appBarTheme.backgroundColor ??
                  airmiusSurfaceColor(context),
              surfaceTintColor: Colors.transparent,
              title: Text(
                _pageTitle,
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            body: PageFrame(
              title: t('membership.noPermission'),
              subtitle: t('membership.permissionRoles'),
              child: AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: .45),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      Icons.lock_outline,
                      color: airmiusMutedColor(context),
                      size: 34,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      t('membership.restrictedData'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      snapshot.hasError
                          ? '${t('membership.permissionCheckFailed')}: ${snapshot.error is AirmiusApiException ? (snapshot.error! as AirmiusApiException).userMessage : t('common.errorDetails')}'
                          : t('membership.roleDenied'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('membership.retry'),
                      icon: Icons.refresh_outlined,
                      secondary: true,
                      onPressed: _reloadClub,
                    ),
                  ],
                ),
              ),
            ),
          );
        }

        final data = snapshot.data!;
        final club = data.selectedClub;
        final management = club.management;
        final invoices = _invoicesFromManagement(management);
        final members = _membersWithInvoiceBalances(
          _membersFromManagement(management),
          invoices,
        );
        final query = _memberQuery.trim().toLowerCase();
        final visibleMembers = members.where((member) {
          final matchesFilter =
              _filter == 'Alle' ||
              member.type == _filter ||
              (_filter == 'Offen' && member.balance != '0,00 EUR');
          final matchesQuery =
              query.isEmpty ||
              '${member.name} ${member.email} ${member.number} '
                      '${member.membership['phone'] ?? ''} '
                      '${member.membership['postal_code'] ?? ''} '
                      '${member.membership['city'] ?? ''}'
                  .toLowerCase()
                  .contains(query);
          return matchesFilter && matchesQuery;
        }).toList();
        final selectedMembers = _selectedMembersFrom(members);
        final visibleInvoices = invoices
            .where((invoice) => _invoiceMatchesPeriod(invoice, DateTime.now()))
            .toList();
        final bankEntries = _bankEntriesFromManagement(management);
        final payments = _paymentsFromManagement(management, members);
        final financeEntries = _financeEntriesFromManagement(management);
        final paymentSchedule = _paymentScheduleFromMembers(members);
        final activeMembersCount =
            management?.activeMembersCount ??
            members.where((member) => member.type == 'Aktiv').length;
        final linkedPeopleCount =
            management?.linkedPeopleCount ?? members.length;
        final openInvoicesCount =
            management?.openInvoicesCount ??
            invoices
                .where(
                  (invoice) => [
                    'open',
                    'overdue',
                    'awaiting_transfer',
                  ].contains(invoice.statusKey),
                )
                .length;
        final sepaReadyMembersCount =
            management?.sepaReadyMembersCount ??
            members.where((member) => member.sepa).length;
        final openInvoiceTotal = management == null
            ? _openTotalFromInvoices(invoices)
            : _formatEuroAmount(management.openInvoiceAmount);
        final recurringContributionTotal = management == null
            ? _recurringTotalFromMembers(members)
            : _formatEuroAmount(management.recurringContributionTotal);
        final cashBalance = management == null
            ? _cashBalanceFromPayments(payments)
            : _formatEuroAmount(management.cashBalance);
        final bankBalance = management == null
            ? _bankBalanceFromPayments(payments)
            : _formatEuroAmount(management.bankBalance);
        final totalBalance = management == null
            ? _totalBalanceFromPayments(payments)
            : _formatEuroAmount(management.totalBalance);
        final hasBackendFinancePeriodTotals =
            management?.hasFinancePeriodTotals ?? false;
        final incomePeriodTotal = !hasBackendFinancePeriodTotals
            ? _formatEuro(
                payments
                        .where(
                          (payment) => _isCurrentYearDate(payment.paidAtInput),
                        )
                        .fold<int>(
                          0,
                          (sum, payment) =>
                              sum + _parseEuroCents(payment.amount),
                        ) +
                    financeEntries
                        .where(
                          (entry) =>
                              entry.type == 'income' &&
                              _isCurrentYearDate(entry.bookedOnInput),
                        )
                        .fold<int>(
                          0,
                          (sum, entry) => sum + _parseEuroCents(entry.amount),
                        ),
              )
            : _formatEuroAmount(management!.incomePeriodTotal);
        final expensePeriodTotal = !hasBackendFinancePeriodTotals
            ? _formatEuro(
                financeEntries
                    .where(
                      (entry) =>
                          entry.type == 'expense' &&
                          _isCurrentYearDate(entry.bookedOnInput),
                    )
                    .fold<int>(
                      0,
                      (sum, entry) => sum + _parseEuroCents(entry.amount),
                    ),
              )
            : _formatEuroAmount(management!.expensePeriodTotal);
        final financePeriodLabel = hasBackendFinancePeriodTotals
            ? management!.financePeriodLabel
            : t('membership.thisYear');
        final unassignedBalance = management?.unassignedBalance ?? 0;

        return Scaffold(
          appBar: AppBar(
            backgroundColor:
                Theme.of(context).appBarTheme.backgroundColor ??
                airmiusSurfaceColor(context),
            surfaceTintColor: Colors.transparent,
            title: Text(
              _pageTitle,
              style: TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          body: PageFrame(
            title: _pageTitle,
            subtitle: '${club.name} – ${t('membership.subtitle')}',
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
                      _section = 'members';
                      _clubFuture = _loadManagedClub();
                    });
                  },
                ),
                const SizedBox(height: 14),
                _MembershipSectionTabs(
                  active: _section,
                  hasRules: management?.canManageMembers ?? false,
                  canManageMembers: management?.canManageMembers ?? false,
                  canManageFinance: management?.canManageFinance ?? false,
                  onSelect: (value) {
                    if (value == 'requests') {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              ClubRequestInboxScreen(initialClubId: club.id),
                        ),
                      );
                      return;
                    }
                    setState(() => _section = value);
                  },
                ),
                const SizedBox(height: 14),
                if (_section == 'rules' && management != null) ...[
                  _MembershipRulesAdminPanel(
                    club: club,
                    management: management,
                    onChanged: _reloadClub,
                  ),
                  const SizedBox(height: 14),
                ],
                if (_section == 'rules' && management == null) ...[
                  AirmiusPanel(
                    child: Text(
                      t('membership.rulesNotLoaded'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                if (_section == 'audit' && management != null) ...[
                  _ClubAuditPanel(logs: management.auditLogs),
                  const SizedBox(height: 14),
                ],
                if (_section == 'overview') ...[
                  _MembershipKpiGrid(
                    cards: [
                      _MembershipKpi(
                        title: t('membership.activeMembers'),
                        value: '$activeMembersCount',
                        detail:
                            '${t('membership.of')} $linkedPeopleCount '
                            '${t('membership.linkedPeopleAfter')}',
                      ),
                      _MembershipKpi(
                        title: t('membership.open'),
                        value: openInvoiceTotal,
                        detail:
                            '$openInvoicesCount ${t('membership.openInvoicesAfter')}',
                      ),
                      _MembershipKpi(
                        title: t('membership.sepaReady'),
                        value: '$sepaReadyMembersCount',
                        detail: t('membership.sepaReadyBody'),
                      ),
                      _MembershipKpi(
                        title: t('membership.recurringContributions'),
                        value: recurringContributionTotal,
                        detail: t('membership.recurringContributionsBody'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                ],
                if (_section == 'members') ...[
                  if (management?.canManageMembers ?? false) ...[
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        FilledButton.icon(
                        onPressed: () => setState(() => _section = 'invite'),
                        icon: const Icon(Icons.person_add_alt_1_outlined),
                        label: Text(t('membership.addMember')),
                        ),
                        OutlinedButton.icon(
                          onPressed: () => _uploadImport(club, bank: false),
                          icon: const Icon(Icons.upload_file_outlined),
                          label: Text(t('membership.importFile')),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                  ],
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Eyebrow(t('membership.members'))),
                            StatusPill(t(_memberFilterTranslationKey(_filter))),
                          ],
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _memberQueryController,
                          decoration: InputDecoration(
                            hintText: t('membership.search'),
                            prefixIcon: const Icon(Icons.search_outlined),
                          ),
                          onChanged: (value) =>
                              setState(() => _memberQuery = value),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children:
                              [
                                'Alle',
                                'Aktiv',
                                'Extern',
                                'Jugend',
                                'Offen',
                              ].map((item) {
                                return ChoiceChip(
                                  selected: _filter == item,
                                  label: Text(
                                    t(_memberFilterTranslationKey(item)),
                                  ),
                                  onSelected: (_) =>
                                      setState(() => _filter = item),
                                  selectedColor: airmiusAccentColor(
                                    context,
                                  ).withValues(alpha: 0.22),
                                  backgroundColor: airmiusSurfaceSoftColor(
                                    context,
                                  ),
                                  side: BorderSide(
                                    color: _filter == item
                                        ? airmiusAccentColor(context)
                                        : airmiusBorderColor(context),
                                  ),
                                  labelStyle: TextStyle(
                                    color: _filter == item
                                        ? airmiusAccentColor(context)
                                        : airmiusMutedColor(context),
                                    fontWeight: FontWeight.w900,
                                  ),
                                );
                              }).toList(),
                        ),
                        _savedViewBar('members', _memberSavedViews),
                        const SizedBox(height: 12),
                        if (management?.canManageMembers ?? false) ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: airmiusSurfaceColor(context),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: airmiusBorderColor(context),
                              ),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        selectedMembers.isEmpty
                                            ? 'Sammelaktionen'
                                            : '${selectedMembers.length} ausgewählt',
                                        style: TextStyle(
                                          color: airmiusTextColor(context),
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                    ),
                                    TextButton(
                                      onPressed: visibleMembers.isEmpty
                                          ? null
                                          : () => _selectVisibleMembers(
                                              visibleMembers,
                                            ),
                                      child: const Text('Sichtbare auswählen'),
                                    ),
                                    if (selectedMembers.isNotEmpty)
                                      IconButton(
                                        tooltip: 'Auswahl aufheben',
                                        onPressed: () => setState(
                                          () => _selectedMemberKeys.clear(),
                                        ),
                                        icon: const Icon(Icons.close),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [
                                    OutlinedButton.icon(
                                      onPressed:
                                          selectedMembers.isEmpty ||
                                              _bulkActionRunning
                                          ? null
                                          : () => _bulkInviteMembers(
                                              club,
                                              selectedMembers,
                                            ),
                                      icon: const Icon(
                                        Icons.mark_email_read_outlined,
                                      ),
                                      label: const Text('Einladung senden'),
                                    ),
                                    OutlinedButton.icon(
                                      onPressed:
                                          selectedMembers.isEmpty ||
                                              _bulkActionRunning
                                          ? null
                                          : () => _bulkChangeStatus(
                                              club,
                                              selectedMembers,
                                            ),
                                      icon: const Icon(
                                        Icons.pause_circle_outline,
                                      ),
                                      label: const Text('Status ändern'),
                                    ),
                                    OutlinedButton.icon(
                                      onPressed:
                                          selectedMembers.isEmpty ||
                                              _bulkActionRunning
                                          ? null
                                          : () => _bulkRemoveMembers(
                                              club,
                                              selectedMembers,
                                            ),
                                      icon: const Icon(
                                        Icons.person_remove_outlined,
                                      ),
                                      label: const Text('Entfernen'),
                                    ),
                                  ],
                                ),
                                if (_bulkActionRunning) ...[
                                  const SizedBox(height: 10),
                                  const LinearProgressIndicator(),
                                ],
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        for (final member in visibleMembers)
                          _MemberCard(
                            member: member,
                            onManage: () =>
                                _memberActions(club, member, members),
                            showManage: management?.canManageMembers ?? false,
                            selectable: management?.canManageMembers ?? false,
                            selected: _selectedMemberKeys.contains(
                              _memberSelectionKey(member),
                            ),
                            onSelected: (selected) =>
                                _toggleMemberSelection(member, selected),
                          ),
                        if (visibleMembers.isEmpty) ...[
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Text(
                              t(
                                members.isEmpty
                                    ? 'membership.noMembersLoaded'
                                    : 'membership.noSearchResults',
                              ),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          if (members.isEmpty &&
                              (management?.canManageMembers ?? false))
                            OutlinedButton.icon(
                              onPressed: () =>
                                  setState(() => _section = 'invite'),
                              icon: const Icon(Icons.person_add_alt_1_outlined),
                              label: Text(t('membership.tab.invite')),
                            ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                if (_section == 'schedule') ...[
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Eyebrow(t('membership.schedule.title')),
                            ),
                            StatusPill(
                              '${paymentSchedule.length}',
                              color: airmiusAccentColor(context),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          t('membership.schedule.hint'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 12),
                        for (final entry in paymentSchedule)
                          _PaymentScheduleCard(
                            entry: entry,
                            dueLabel: _dueLabel(entry.days),
                            dueColor: _dueColor(entry.days),
                            dateLabel: entry.dueDate == null
                                ? t('membership.schedule.noDate')
                                : _dateDisplay(entry.dueDate!),
                            intervalLabel: _memberIntervalLabel(entry.interval),
                            paymentMethodLabel: entry.paymentMethod.isEmpty
                                ? t('membership.open')
                                : _paymentMethodLabel(entry.paymentMethod),
                            onCreateInvoice: () =>
                                _createInvoice(club, [entry.member]),
                            onEdit: () =>
                                _memberActions(club, entry.member, members),
                          ),
                        if (paymentSchedule.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Text(
                              t('membership.schedule.empty'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                if (_section == 'invite' &&
                    (management?.canManageMembers ?? false)) ...[
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('membership.addMember')),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          label: t('membership.name'),
                          hint: t('membership.optional'),
                          icon: Icons.badge_outlined,
                          controller: _inviteNameController,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          label: t('membership.email'),
                          hint: 'mitglied@example.com',
                          icon: Icons.alternate_email,
                          controller: _inviteEmailController,
                          keyboardType: TextInputType.emailAddress,
                        ),
                        const SizedBox(height: 10),
                        DefaultTabController(
                          length: 4,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              TabBar(
                                isScrollable: true,
                                tabAlignment: TabAlignment.start,
                                tabs: [
                                  Tab(
                                    icon: const Icon(Icons.groups_2_outlined),
                                    text: t('membership.memberDataTab'),
                                  ),
                                  Tab(
                                    icon: const Icon(Icons.euro_outlined),
                                    text: t('membership.contribution'),
                                  ),
                                  Tab(
                                    icon: const Icon(
                                      Icons.account_balance_outlined,
                                    ),
                                    text: t('membership.payment'),
                                  ),
                                  Tab(
                                    icon: const Icon(Icons.home_outlined),
                                    text: t('membership.address'),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              SizedBox(
                                height: 460,
                                child: TabBarView(
                                  children: [
                                    ListView(
                                      padding: const EdgeInsets.only(top: 12),
                                      children: [
                                        DropdownButtonFormField<String>(
                                          initialValue: _inviteRole,
                                          isExpanded: true,
                                          decoration: InputDecoration(
                                            labelText: t('membership.clubRole'),
                                          ),
                                          items: _clubRoleKeys
                                              .where((role) => role != 'owner')
                                              .map(
                                                (role) => DropdownMenuItem(
                                                  value: role,
                                                  child: Text(
                                                    t('membership.role.$role'),
                                                  ),
                                                ),
                                              )
                                              .toList(),
                                          onChanged: _sendingInvitation
                                              ? null
                                              : (value) => setState(
                                                  () => _inviteRole =
                                                      value ?? 'member',
                                                ),
                                        ),
                                        const SizedBox(height: 10),
                                        DropdownButtonFormField<String>(
                                          initialValue: _inviteStatus,
                                          isExpanded: true,
                                          decoration: InputDecoration(
                                            labelText: t('membership.status'),
                                          ),
                                          items: [
                                            DropdownMenuItem(
                                              value: 'active',
                                              child: Text(
                                                t('membership.status.active'),
                                              ),
                                            ),
                                            DropdownMenuItem(
                                              value: 'pending',
                                              child: Text(
                                                t('membership.status.pending'),
                                              ),
                                            ),
                                            DropdownMenuItem(
                                              value: 'paused',
                                              child: Text(
                                                t('membership.status.paused'),
                                              ),
                                            ),
                                            DropdownMenuItem(
                                              value: 'former',
                                              child: Text(
                                                t('membership.status.former'),
                                              ),
                                            ),
                                          ],
                                          onChanged: _sendingInvitation
                                              ? null
                                              : (value) => setState(
                                                  () => _inviteStatus =
                                                      value ?? 'active',
                                                ),
                                        ),
                                        const SizedBox(height: 10),
                                        DropdownButtonFormField<int?>(
                                          initialValue: _inviteMembershipTypeId,
                                          isExpanded: true,
                                          decoration: InputDecoration(
                                            labelText: t(
                                              'membership.membershipType',
                                            ),
                                          ),
                                          items: [
                                            DropdownMenuItem<int?>(
                                              value: null,
                                              child: Text(
                                                t(
                                                  'membership.noMembershipType',
                                                ),
                                              ),
                                            ),
                                            for (final type
                                                in management
                                                        ?.membershipTypes ??
                                                    const <JsonMap>[])
                                              DropdownMenuItem<int?>(
                                                value: _intOrNull(type['id']),
                                                child: Text(
                                                  _stringFromJson(type, [
                                                    'name',
                                                  ]),
                                                ),
                                              ),
                                          ],
                                          onChanged: _sendingInvitation
                                              ? null
                                              : (value) => setState(
                                                  () =>
                                                      _inviteMembershipTypeId =
                                                          value,
                                                ),
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.memberNumber'),
                                          hint: 'UC21-001',
                                          icon: Icons.numbers_outlined,
                                          controller:
                                              _inviteMemberNumberController,
                                          enabled: !_sendingInvitation,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.phone'),
                                          hint: t('membership.optional'),
                                          icon: Icons.phone_outlined,
                                          controller: _invitePhoneController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.phone,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.joinedOn'),
                                          hint: t('membership.dateHint'),
                                          icon: Icons.event_available_outlined,
                                          controller: _inviteJoinedOnController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.datetime,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t(
                                            'membership.membershipEndsOn',
                                          ),
                                          hint: t('membership.dateHint'),
                                          icon: Icons.event_busy_outlined,
                                          controller:
                                              _inviteMembershipEndsOnController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.datetime,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.notes'),
                                          hint: t('membership.optional'),
                                          icon: Icons.notes_outlined,
                                          controller: _inviteNotesController,
                                          enabled: !_sendingInvitation,
                                          maxLines: 3,
                                        ),
                                      ],
                                    ),
                                    ListView(
                                      padding: const EdgeInsets.only(top: 12),
                                      children: [
                                        DropdownButtonFormField<int>(
                                          initialValue:
                                              _inviteContributionPayerId,
                                          isExpanded: true,
                                          decoration: InputDecoration(
                                            labelText: t(
                                              'membership.contributionPayerShort',
                                            ),
                                            helperText: t(
                                              'membership.contributionPayerHint',
                                            ),
                                          ),
                                          items: [
                                            DropdownMenuItem<int>(
                                              value: 0,
                                              child: Text(
                                                t('membership.paysSelf'),
                                              ),
                                            ),
                                            for (final payer in members.where(
                                              (entry) => !entry.isExternal,
                                            ))
                                              DropdownMenuItem<int>(
                                                value: payer.id,
                                                child: Text(
                                                  '${payer.name} · ${payer.email}',
                                                ),
                                              ),
                                          ],
                                          onChanged: _sendingInvitation
                                              ? null
                                              : (value) => setState(
                                                  () =>
                                                      _inviteContributionPayerId =
                                                          value ?? 0,
                                                ),
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t(
                                            'membership.contributionEurShort',
                                          ),
                                          hint: '19,00',
                                          icon: Icons.euro_outlined,
                                          controller:
                                              _inviteContributionController,
                                          enabled: !_sendingInvitation,
                                          keyboardType:
                                              const TextInputType.numberWithOptions(
                                                decimal: true,
                                              ),
                                        ),
                                        const SizedBox(height: 10),
                                        DropdownButtonFormField<String>(
                                          initialValue:
                                              _inviteContributionInterval,
                                          isExpanded: true,
                                          decoration: InputDecoration(
                                            labelText: t('membership.interval'),
                                          ),
                                          items:
                                              const [
                                                    'none',
                                                    'monthly',
                                                    'quarterly',
                                                    'fourMonthly',
                                                    'semiYearly',
                                                    'yearly',
                                                    'once',
                                                  ]
                                                  .map(
                                                    (
                                                      interval,
                                                    ) => DropdownMenuItem(
                                                      value:
                                                          interval ==
                                                              'semiYearly'
                                                          ? 'semi_yearly'
                                                          : interval ==
                                                                'fourMonthly'
                                                          ? 'four_monthly'
                                                          : interval,
                                                      child: Text(
                                                        t(
                                                          'membership.interval.$interval',
                                                        ),
                                                      ),
                                                    ),
                                                  )
                                                  .toList(),
                                          onChanged: _sendingInvitation
                                              ? null
                                              : (value) => setState(
                                                  () =>
                                                      _inviteContributionInterval =
                                                          value ?? 'none',
                                                ),
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t(
                                            'membership.nextInvoiceDate',
                                          ),
                                          hint: t('membership.dateHint'),
                                          icon: Icons.event_repeat_outlined,
                                          controller:
                                              _inviteNextInvoiceController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.datetime,
                                        ),
                                      ],
                                    ),
                                    ListView(
                                      padding: const EdgeInsets.only(top: 12),
                                      children: [
                                        SwitchListTile(
                                          contentPadding: EdgeInsets.zero,
                                          value: _inviteSepaActive,
                                          title: Text(
                                            t('membership.sepaMandateActive'),
                                          ),
                                          onChanged: _sendingInvitation
                                              ? null
                                              : (value) => setState(
                                                  () =>
                                                      _inviteSepaActive = value,
                                                ),
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.iban'),
                                          hint: t('membership.optional'),
                                          icon: Icons.credit_card_outlined,
                                          controller: _inviteIbanController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.text,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.bic'),
                                          hint: t('membership.optional'),
                                          icon: Icons.account_balance_outlined,
                                          controller: _inviteBicController,
                                          enabled: !_sendingInvitation,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t(
                                            'membership.mandateReference',
                                          ),
                                          hint: t('membership.optional'),
                                          icon: Icons.receipt_long_outlined,
                                          controller: _inviteMandateController,
                                          enabled: !_sendingInvitation,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.mandateDate'),
                                          hint: t('membership.dateHint'),
                                          icon: Icons.event_note_outlined,
                                          controller:
                                              _inviteMandateDateController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.datetime,
                                        ),
                                      ],
                                    ),
                                    ListView(
                                      padding: const EdgeInsets.only(top: 12),
                                      children: [
                                        AirmiusTextField(
                                          label: t('membership.street'),
                                          hint: t('membership.optional'),
                                          icon: Icons.route_outlined,
                                          controller: _inviteStreetController,
                                          enabled: !_sendingInvitation,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.houseNumber'),
                                          hint: t('membership.optional'),
                                          icon: Icons.tag_outlined,
                                          controller:
                                              _inviteHouseNumberController,
                                          enabled: !_sendingInvitation,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.postalCode'),
                                          hint: t('membership.optional'),
                                          icon:
                                              Icons.local_post_office_outlined,
                                          controller:
                                              _invitePostalCodeController,
                                          enabled: !_sendingInvitation,
                                          keyboardType: TextInputType.text,
                                        ),
                                        const SizedBox(height: 10),
                                        AirmiusTextField(
                                          label: t('membership.city'),
                                          hint: t('membership.optional'),
                                          icon: Icons.location_city_outlined,
                                          controller: _inviteCityController,
                                          enabled: !_sendingInvitation,
                                        ),
                                        const SizedBox(height: 10),
                                        CountryField(
                                          controller: _inviteCountryController,
                                          label: t('clubs.wizard.country'),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            AirmiusButton(
                              label: t('membership.createExternal'),
                              icon: Icons.person_add_alt_1_outlined,
                              onPressed: _sendingInvitation
                                  ? null
                                  : () => _storeExternalMember(club),
                            ),
                            AirmiusButton(
                              label: _sendingInvitation
                                  ? t('membership.sending')
                                  : t('membership.sendInvitation'),
                              icon: Icons.mark_email_read_outlined,
                              onPressed: _sendingInvitation
                                  ? null
                                  : () => _sendClubInvitation(club),
                            ),
                          ],
                        ),
                        ExpansionTile(
                          title: Text(t('membership.importInvitation')),
                          tilePadding: EdgeInsets.zero,
                          children: [
                            Wrap(
                              spacing: 10,
                              runSpacing: 10,
                              children: [
                                AirmiusButton(
                                  label: t('membership.importTemplate'),
                                  icon: Icons.table_view_outlined,
                                  secondary: true,
                                  onPressed: () => _downloadApiFile(
                                    path:
                                        '/api/v1/club-members/import-template',
                                    fileName:
                                        'airmius-mitglieder-import-vorlage.xlsx',
                                    extensions: const ['xlsx'],
                                  ),
                                ),
                                AirmiusButton(
                                  label: t('membership.importFile'),
                                  icon: Icons.upload_file_outlined,
                                  secondary: true,
                                  onPressed: () =>
                                      _uploadImport(club, bank: false),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                if (_section == 'payments') ...[
                  AirmiusPanel(
                    child: Row(
                      children: [
                        Icon(
                          openInvoicesCount > 0
                              ? Icons.warning_amber_outlined
                              : Icons.task_alt_outlined,
                          color: openInvoicesCount > 0
                              ? Theme.of(context).colorScheme.error
                              : Theme.of(context).colorScheme.primary,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            openInvoicesCount > 0
                                ? t('membership.openSummary')
                                      .replaceAll(
                                        '{count}',
                                        '$openInvoicesCount',
                                      )
                                      .replaceAll('{amount}', openInvoiceTotal)
                                : t('membership.noCreatedInvoicesOpen'),
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Eyebrow(t('membership.actionNow')),
                  ),
                  AirmiusPanel(
                    child: Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        AirmiusButton(
                          label: t('membership.createInvoice'),
                          icon: Icons.receipt_long_outlined,
                          onPressed: () => _createInvoice(club, members),
                        ),
                        AirmiusButton(
                          label: t('membership.invoiceRun'),
                          icon: Icons.playlist_add_check_circle_outlined,
                          secondary: true,
                          onPressed: () => _runInvoiceBatch(club),
                        ),
                        AirmiusButton(
                          label: t('membership.recordPayment'),
                          icon: Icons.payments_outlined,
                          secondary: true,
                          onPressed: () => _recordPayment(club, invoices),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Eyebrow(t('membership.analyzeManage')),
                  ),
                  Material(
                    color: Theme.of(context).colorScheme.surface,
                    child: ExpansionTile(
                      title: Text(t('membership.treasury')),
                      children: [
                        _MembershipTreasuryKpiGrid(
                          cards: [
                            _MembershipKpi(
                              title: t('membership.cashBalance'),
                              value: cashBalance,
                              detail: t('membership.currentBalance'),
                              icon: Icons.account_balance_wallet_outlined,
                            ),
                            _MembershipKpi(
                              title: t('membership.bankBalance'),
                              value: bankBalance,
                              detail: t('membership.currentBalance'),
                              icon: Icons.account_balance_outlined,
                            ),
                            _MembershipKpi(
                              title: t('membership.total'),
                              value: totalBalance,
                              detail: unassignedBalance > 0
                                  ? '${t('membership.includingManualBefore')} '
                                        '${_formatEuroAmount(unassignedBalance)}'
                                  : t('membership.currentTotal'),
                              icon: Icons.layers_outlined,
                              accent: airmiusAccentColor(context),
                            ),
                            _MembershipKpi(
                              title: t('membership.income'),
                              value: incomePeriodTotal,
                              detail: financePeriodLabel,
                              icon: Icons.call_received_outlined,
                              accent: AirmiusColors.green,
                            ),
                            _MembershipKpi(
                              title: t('membership.expenses'),
                              value: expensePeriodTotal,
                              detail: financePeriodLabel,
                              icon: Icons.call_made_outlined,
                              accent: AirmiusColors.red,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('membership.invoicesPayments')),
                        const SizedBox(height: 10),
                        DropdownButtonFormField<String>(
                          key: ValueKey(_period),
                          isExpanded: true,
                          initialValue: _period,
                          dropdownColor: airmiusSurfaceSoftColor(context),
                          decoration: InputDecoration(
                            labelText: t('membership.invoicePeriod'),
                          ),
                          items: const ['all', 'month', 'previousMonth', 'year']
                              .map(
                                (item) => DropdownMenuItem(
                                  value: item,
                                  child: Text(t('membership.period.$item')),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _period = value ?? _period),
                        ),
                        _savedViewBar('invoices', _invoiceSavedViews),
                        const SizedBox(height: 12),
                        for (final invoice in visibleInvoices)
                          _InvoiceLine(
                            invoice: invoice,
                            onManage: () =>
                                _invoiceActions(club, invoice, invoices),
                          ),
                        if (visibleInvoices.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  invoices.isEmpty
                                      ? t('membership.noInvoicesLoaded')
                                      : t('membership.noInvoicesInPeriod'),
                                  style: TextStyle(
                                    color: airmiusMutedColor(context),
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                if (invoices.isNotEmpty)
                                  TextButton(
                                    onPressed: () =>
                                        setState(() => _period = 'all'),
                                    child: Text(t('membership.period.all')),
                                  ),
                              ],
                            ),
                          ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            PopupMenuButton<String>(
                              tooltip: t('membership.addBooking'),
                              onSelected: (value) {
                                switch (value) {
                                  case 'payment':
                                    _recordPayment(club, invoices);
                                    break;
                                  case 'donation':
                                    _recordDonation(club, members);
                                    break;
                                  case 'prepayment':
                                    _recordPrepayment(club, members);
                                    break;
                                  case 'income':
                                    _editFinanceEntry(
                                      club,
                                      initialType: 'income',
                                    );
                                    break;
                                  case 'expense':
                                    _editFinanceEntry(
                                      club,
                                      initialType: 'expense',
                                    );
                                    break;
                                }
                              },
                              itemBuilder: (_) => [
                                for (final entry in <(String, String)>[
                                  ('payment', 'membership.recordPayment'),
                                  ('donation', 'membership.recordDonation'),
                                  ('prepayment', 'membership.prepayment'),
                                  ('income', 'membership.bookIncome'),
                                  ('expense', 'membership.bookExpense'),
                                ])
                                  PopupMenuItem(
                                    value: entry.$1,
                                    child: Text(t(entry.$2)),
                                  ),
                              ],
                              child: Container(
                                constraints: const BoxConstraints(
                                  maxWidth: 270,
                                ),
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 12,
                                ),
                                decoration: BoxDecoration(
                                  border: Border.all(
                                    color: airmiusAccentColor(context),
                                  ),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.add,
                                      color: airmiusAccentColor(context),
                                    ),
                                    const SizedBox(width: 8),
                                    Flexible(
                                      child: Text(
                                        t('membership.addBooking'),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(
                                          color: airmiusAccentColor(context),
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                    Icon(
                                      Icons.arrow_drop_down,
                                      color: airmiusAccentColor(context),
                                    ),
                                  ],
                                ),
                              ),
                            ),
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
                        Eyebrow(t('membership.cashbook')),
                        const SizedBox(height: 10),
                        for (final entry in financeEntries)
                          _FinanceEntryLine(
                            entry: entry,
                            onEdit: () => _editFinanceEntry(club, entry: entry),
                          ),
                        if (financeEntries.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Text(
                              t('membership.noFinanceEntries'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
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
                        Eyebrow(t('membership.recordedPayments')),
                        const SizedBox(height: 10),
                        for (final payment in payments)
                          _PaymentLine(
                            payment: payment,
                            onEdit: () => _editPayment(club, payment, members),
                          ),
                        if (payments.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Text(
                              t('membership.noPayments'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    borderColor: airmiusAccentColor(
                      context,
                    ).withValues(alpha: 0.55),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('membership.bankReconciliation')),
                        const SizedBox(height: 10),
                        for (final entry in bankEntries)
                          _BankLine(
                            entry: entry,
                            onConfirm:
                                entry.status != 'matched' && entry.invoiceId > 0
                                ? () => _runManagementAction(
                                    () => AirmiusServicesScope.of(context)
                                        .repositories
                                        .clubs
                                        .confirmClubBankTransaction(
                                          club.id,
                                          entry.id,
                                        ),
                                    success: t('membership.bankBooked'),
                                  )
                                : null,
                          ),
                        if (bankEntries.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            child: Text(
                              t('membership.noBankTransactions'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            AirmiusButton(
                              label: t('membership.importBankFile'),
                              icon: Icons.cloud_upload_outlined,
                              onPressed: () => _uploadImport(club, bank: true),
                            ),
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
                        Eyebrow(t('membership.exportsRules')),
                        const SizedBox(height: 12),
                        _ExportTile(
                          icon: Icons.account_balance_wallet_outlined,
                          title: t('membership.sepaBatch'),
                          subtitle: t('membership.sepaBatchBody'),
                          status: t('membership.ready'),
                        ),
                        _ExportTile(
                          icon: Icons.fact_check_outlined,
                          title: t('membership.datevExport'),
                          subtitle: t('membership.datevExportBody'),
                          status: t('membership.configurable'),
                        ),
                        _ExportTile(
                          icon: Icons.rule_folder_outlined,
                          title: t('membership.contributionRules'),
                          subtitle: t('membership.contributionRulesBody'),
                          status: t('membership.active'),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            AirmiusButton(
                              label: t('membership.configureSepa'),
                              icon: Icons.settings_outlined,
                              secondary: true,
                              onPressed: () => _configureAccounting(
                                club,
                                sepa: true,
                                settings:
                                    management?.settings ??
                                    const <String, dynamic>{},
                              ),
                            ),
                            AirmiusButton(
                              label: t('membership.exportSepa'),
                              icon: Icons.account_balance_outlined,
                              onPressed: () => _downloadApiFile(
                                path:
                                    '/api/v1/clubs/${club.id}/membership/sepa-export',
                                fileName:
                                    'airmius-sepa-${club.id}-${_dateOnly(DateTime.now())}.xml',
                                extensions: const ['xml'],
                              ),
                            ),
                            AirmiusButton(
                              label: t('membership.sepaBatch'),
                              icon: Icons.fact_check_outlined,
                              onPressed: () => Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => ClubSepaBatchesScreen(
                                    clubId: club.id,
                                    invoices:
                                        management?.invoices ??
                                        const <AirmiusJson>[],
                                  ),
                                ),
                              ),
                            ),
                            AirmiusButton(
                              label: t('membership.configureDatev'),
                              icon: Icons.tune_outlined,
                              secondary: true,
                              onPressed: () => _configureAccounting(
                                club,
                                sepa: false,
                                settings:
                                    management?.settings ??
                                    const <String, dynamic>{},
                              ),
                            ),
                            AirmiusButton(
                              label: t('membership.exportDatev'),
                              icon: Icons.ios_share_outlined,
                              onPressed: () => _downloadApiFile(
                                path:
                                    '/api/v1/clubs/${club.id}/membership/datev-export',
                                fileName:
                                    'airmius-datev-${club.id}-${_dateOnly(DateTime.now())}.csv',
                                extensions: const ['csv'],
                              ),
                            ),
                            AirmiusButton(
                              label: t('membership.reloadRules'),
                              icon: Icons.refresh_outlined,
                              secondary: true,
                              onPressed: _reloadClub,
                            ),
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
  const _ManagedMembershipData({
    required this.clubs,
    required this.selectedClub,
  });

  final List<ClubSummary> clubs;
  final ClubSummary selectedClub;
}

String _memberFilterTranslationKey(String value) {
  return switch (value) {
    'Aktiv' => 'membership.filter.active',
    'Extern' => 'membership.filter.external',
    'Jugend' => 'membership.filter.youth',
    'Offen' => 'membership.filter.open',
    _ => 'membership.filter.all',
  };
}

class _MembershipSectionTabs extends StatelessWidget {
  const _MembershipSectionTabs({
    required this.active,
    required this.hasRules,
    required this.canManageMembers,
    required this.canManageFinance,
    required this.onSelect,
  });

  final String active;
  final bool hasRules;
  final bool canManageMembers;
  final bool canManageFinance;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final tabs = [
      const _MembershipSectionTabData(
        'members',
        'membership.tab.members',
        Icons.groups_2_outlined,
      ),
      if (canManageFinance)
        const _MembershipSectionTabData(
          'payments',
          'membership.tab.financeShort',
          Icons.receipt_long_outlined,
        ),
      if (canManageFinance)
        const _MembershipSectionTabData(
          'schedule',
          'membership.tab.schedule',
          Icons.event_available_outlined,
        ),
    ];
    final secondaryTabs = [
      if (canManageMembers)
        const _MembershipSectionTabData(
          'requests',
          'clubHub.requests',
          Icons.inbox_outlined,
        ),
      const _MembershipSectionTabData(
        'overview',
        'membership.tab.overview',
        Icons.dashboard_customize_outlined,
      ),
      if (hasRules)
        const _MembershipSectionTabData(
          'rules',
          'membership.tab.rules',
          Icons.tune_outlined,
        ),
      if (canManageFinance)
        const _MembershipSectionTabData(
          'export',
          'membership.tab.export',
          Icons.ios_share_outlined,
        ),
      const _MembershipSectionTabData(
        'audit',
        'membership.tab.audit',
        Icons.history_outlined,
      ),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        LayoutBuilder(
          builder: (context, constraints) => Row(
            children: [
              for (final tab in tabs)
                Padding(
                  padding: EdgeInsetsDirectional.only(
                    end: tab == tabs.last ? 0 : 8,
                  ),
                  child: SizedBox(
                    width:
                        (constraints.maxWidth - 8 * (tabs.length - 1)) /
                        tabs.length,
                    child: _MembershipSectionTab(
                      tab: tab,
                      label: t(tab.labelKey),
                      selected: active == tab.value,
                      onTap: () => onSelect(tab.value),
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final tab in secondaryTabs)
              SizedBox(
                width: _secondaryTabWidth(context),
                child: _MembershipSectionTab(
                  tab: tab,
                  label: t(tab.labelKey),
                  selected: active == tab.value,
                  onTap: () => onSelect(tab.value),
                ),
              ),
          ],
        ),
      ],
    );
  }

  double _secondaryTabWidth(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= 720) return 168;
    if (width >= 420) return (width - 56) / 2;
    return (width - 54) / 2;
  }
}

class _MembershipSectionTabData {
  const _MembershipSectionTabData(this.value, this.labelKey, this.icon);

  final String value;
  final String labelKey;
  final IconData icon;
}

class _ClubAuditPanel extends StatelessWidget {
  const _ClubAuditPanel({required this.logs});

  final List<JsonMap> logs;

  String _label(AirmiusScope scope, JsonMap log) {
    final type = '${log['type'] ?? ''}';
    return switch (type) {
      'club.member.role_updated' => scope.t('membership.audit.roleUpdated'),
      'club.member.updated' => scope.t('membership.audit.memberUpdated'),
      'club.invoice.created' => scope.t('membership.audit.invoiceCreated'),
      'club.invoice.status_updated' => scope.t(
        'membership.audit.invoiceStatusUpdated',
      ),
      'club.invoice.reminder_sent' => scope.t('membership.audit.reminderSent'),
      'club.payment.recorded' => scope.t('membership.audit.paymentRecorded'),
      _ => '${log['label'] ?? type}',
    };
  }

  String _createdAt(BuildContext context, Object? value) {
    final parsed = DateTime.tryParse('$value')?.toLocal();
    if (parsed == null) return '$value';
    final material = MaterialLocalizations.of(context);
    return '${material.formatMediumDate(parsed)} · ${material.formatTimeOfDay(TimeOfDay.fromDateTime(parsed))}';
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('membership.audit.title')),
          const SizedBox(height: 8),
          if (logs.isEmpty)
            Text(
              scope.t('membership.audit.empty'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          else
            for (final log in logs.take(50)) ...[
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  Icons.history_outlined,
                  color: airmiusAccentColor(context),
                ),
                title: Text(
                  _label(scope, log),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(
                  '${(log['actor'] as JsonMap?)?['name'] ?? scope.t('membership.audit.system')} · ${_createdAt(context, log['created_at'])}',
                ),
              ),
              if (log != logs.last) const Divider(height: 1),
            ],
        ],
      ),
    );
  }
}

class _MembershipSectionTab extends StatelessWidget {
  const _MembershipSectionTab({
    required this.tab,
    this.label,
    required this.selected,
    required this.onTap,
  });

  final _MembershipSectionTabData tab;
  final String? label;
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
          color: selected
              ? airmiusAccentColor(context).withValues(alpha: .18)
              : airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: selected
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.max,
          children: [
            Icon(
              tab.icon,
              size: 18,
              color: selected
                  ? airmiusAccentColor(context)
                  : airmiusMutedColor(context),
            ),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label ?? tab.labelKey,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected
                      ? airmiusTextColor(context)
                      : airmiusMutedColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
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
    final t = AirmiusScope.of(context).t;
    var selectedClub = clubs.first;
    for (final club in clubs) {
      if (club.id == selectedClubId) {
        selectedClub = club;
        break;
      }
    }

    if (clubs.length == 1) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: [
            Icon(Icons.apartment_outlined, color: airmiusAccentColor(context)),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                selectedClub.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      );
    }

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Eyebrow(t('membership.chooseClub'))),
              StatusPill('${clubs.length} ${t('membership.manageableAfter')}'),
            ],
          ),
          const SizedBox(height: 10),
          if (clubs.length > 1)
            DropdownButtonFormField<int>(
              initialValue: selectedClubId,
              dropdownColor: airmiusSurfaceSoftColor(context),
              decoration: InputDecoration(
                labelText: t('membership.activeClub'),
                prefixIcon: Icon(Icons.apartment_outlined),
              ),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
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
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Icon(Icons.apartment_outlined, color: airmiusAccentColor(context)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  club.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  [club.sportType, club.city]
                      .where((value) => value != null && value.isNotEmpty)
                      .join(' - '),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

String _membershipDateDisplay(Object? value) {
  final raw = '$value'.trim();
  if (raw.isEmpty || raw == 'null') return '';
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return raw;
  final day = parsed.day.toString().padLeft(2, '0');
  final month = parsed.month.toString().padLeft(2, '0');
  return '$day.$month.${parsed.year}';
}

String _membershipDateApi(String value) {
  final raw = value.trim();
  final match = RegExp(r'^(\d{2})\.(\d{2})\.(\d{4})$').firstMatch(raw);
  if (match == null) return raw;
  return '${match.group(3)}-${match.group(2)}-${match.group(1)}';
}

class _MembershipDateInputFormatter extends TextInputFormatter {
  const _MembershipDateInputFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final rawDigits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final digits = rawDigits.length > 8 ? rawDigits.substring(0, 8) : rawDigits;
    final buffer = StringBuffer();
    for (var index = 0; index < digits.length; index++) {
      if (index == 2 || index == 4) buffer.write('.');
      buffer.write(digits[index]);
    }
    final text = buffer.toString();
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}

const _membershipDateInputFormatters = <TextInputFormatter>[
  _MembershipDateInputFormatter(),
];

class _MembershipRulesAdminPanel extends StatefulWidget {
  const _MembershipRulesAdminPanel({
    required this.club,
    required this.management,
    required this.onChanged,
  });

  final ClubSummary club;
  final AirmiusClubManagement management;
  final VoidCallback onChanged;

  @override
  State<_MembershipRulesAdminPanel> createState() =>
      _MembershipRulesAdminPanelState();
}

class _MembershipRulesAdminPanelState
    extends State<_MembershipRulesAdminPanel> {
  final _typeName = TextEditingController();
  final _typeSlug = TextEditingController();
  final _typeDescription = TextEditingController();
  final _ruleName = TextEditingController();
  final _ruleAmount = TextEditingController();
  final _ruleValidFrom = TextEditingController(
    text: _membershipDateDisplay(DateTime.now()),
  );
  final _ruleValidUntil = TextEditingController();
  final _ruleAgeMin = TextEditingController();
  final _ruleAgeMax = TextEditingController();
  final _ruleFactorValue = TextEditingController();
  final _ruleTaxAccount = TextEditingController();
  final _ruleAccountingAccount = TextEditingController();
  final _ruleNotes = TextEditingController();

  int? _editingTypeId;
  int? _editingRuleId;
  int? _ruleTypeId;
  int? _rulePolicyDocumentId;
  bool _typePublic = true;
  bool _typeActive = true;
  bool _ruleActive = true;
  bool _requestsEnabled = false;
  bool _pauseRequestsEnabled = false;
  bool _saving = false;
  AirmiusClubManagement? _localManagement;
  String _ruleInterval = 'monthly';
  String _ruleProrationPolicy = 'prorate_days';
  String _ruleFactorKey = 'standard';
  String _ruleFactorOperator = 'percent';
  String _rulesTab = 'types';
  Set<String> _paymentMethods = {'bank_transfer', 'cash'};
  Map<String, String> _fieldModes = {};
  Map<String, String> _typeApplicationFields = {};
  List<JsonMap> _documents = [];
  List<JsonMap> _documentTypes = [];
  String _documentSearch = '';
  String _documentTypeFilter = 'all';

  String _tr(String key) => AirmiusScope.of(context).t(key);

  AirmiusClubManagement get _effectiveManagement =>
      _localManagement ?? widget.management;

  List<JsonMap> get _filteredDocuments {
    final query = _documentSearch.trim().toLowerCase();
    return _documents.where((document) {
      final typeMatches =
          _documentTypeFilter == 'all' ||
          _string(document['type'], fallback: 'other') == _documentTypeFilter;
      final searchable = [
        _string(document['title']),
        _string(document['description']),
        _string(document['file_name']),
      ].join(' ').toLowerCase();
      return typeMatches && (query.isEmpty || searchable.contains(query));
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _syncSettings();
  }

  @override
  void didUpdateWidget(covariant _MembershipRulesAdminPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.club.id != widget.club.id ||
        oldWidget.management.settings != widget.management.settings) {
      _localManagement = null;
      _clearForms();
      _syncSettings();
    }
  }

  @override
  void dispose() {
    for (final controller in [
      _typeName,
      _typeSlug,
      _typeDescription,
      _ruleName,
      _ruleAmount,
      _ruleValidFrom,
      _ruleValidUntil,
      _ruleAgeMin,
      _ruleAgeMax,
      _ruleFactorValue,
      _ruleTaxAccount,
      _ruleAccountingAccount,
      _ruleNotes,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _syncSettings() {
    final settings = _effectiveManagement.settings;
    _requestsEnabled = _bool(settings['membership_requests_enabled']);
    _pauseRequestsEnabled = _bool(settings['member_pause_requests_enabled']);
    final methods = settings['membership_payment_methods'];
    _paymentMethods = methods is List
        ? methods.map((item) => '$item').toSet()
        : {'bank_transfer', 'cash'};
    if (_paymentMethods.isEmpty) _paymentMethods = {'bank_transfer', 'cash'};
    final fields = settings['membership_application_fields'];
    _fieldModes = {
      for (final field
          in fields is List ? fields.whereType<JsonMap>() : const <JsonMap>[])
        if (field['key'] != null)
          '${field['key']}': _string(field['mode'], fallback: 'off'),
    };
    final documents = settings['membership_application_documents'];
    _documents = documents is List
        ? documents
              .whereType<JsonMap>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
        : [];
    final documentTypes = settings['membership_application_document_types'];
    _documentTypes = documentTypes is List
        ? documentTypes
              .whereType<JsonMap>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
        : [];
  }

  void _clearForms() {
    _editingTypeId = null;
    _editingRuleId = null;
    _ruleTypeId = null;
    _rulePolicyDocumentId = null;
    _typeName.clear();
    _typeSlug.clear();
    _typeDescription.clear();
    _ruleName.clear();
    _ruleAmount.clear();
    _ruleValidFrom.text = _membershipDateDisplay(DateTime.now());
    _ruleValidUntil.clear();
    _ruleAgeMin.clear();
    _ruleAgeMax.clear();
    _ruleFactorValue.clear();
    _ruleTaxAccount.clear();
    _ruleAccountingAccount.clear();
    _ruleNotes.clear();
    _typePublic = true;
    _typeActive = true;
    _ruleActive = true;
    _ruleInterval = 'monthly';
    _ruleFactorKey = 'standard';
    _ruleFactorOperator = 'percent';
    _typeApplicationFields = {..._fieldModes};
  }

  bool _bool(Object? value) =>
      value == true ||
      '$value'.toLowerCase() == '1' ||
      '$value'.toLowerCase() == 'true';

  int? _intOrNull(Object? value) {
    if (value == null || '$value'.trim().isEmpty || '$value' == 'null') {
      return null;
    }
    return int.tryParse('$value');
  }

  String _string(Object? value, {String fallback = ''}) {
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  JsonMap _typePayload() => {
    'name': _typeName.text.trim(),
    'slug': _typeSlug.text.trim().isEmpty ? null : _typeSlug.text.trim(),
    'description': _typeDescription.text.trim().isEmpty
        ? null
        : _typeDescription.text.trim(),
    'is_public': _typePublic,
    'is_active': _typeActive,
    'application_fields': _typeApplicationFields,
  };

  JsonMap _rulePayload() => {
    'club_membership_type_id': _ruleTypeId,
    'club_policy_document_id': _rulePolicyDocumentId,
    'name': _ruleName.text.trim(),
    'amount': _ruleAmount.text.trim().replaceAll(',', '.'),
    'billing_interval': _ruleInterval,
    'proration_policy': _ruleProrationPolicy,
    'valid_from': _membershipDateApi(_ruleValidFrom.text),
    'valid_until': _ruleValidUntil.text.trim().isEmpty
        ? null
        : _membershipDateApi(_ruleValidUntil.text),
    'age_min': _ruleAgeMin.text.trim().isEmpty
        ? null
        : int.tryParse(_ruleAgeMin.text.trim()),
    'age_max': _ruleAgeMax.text.trim().isEmpty
        ? null
        : int.tryParse(_ruleAgeMax.text.trim()),
    'factor_key': _ruleFactorKey,
    'factor_operator': _ruleFactorKey == 'discount'
        ? _ruleFactorOperator
        : null,
    'factor_value':
        _ruleFactorKey == 'discount' && _ruleFactorValue.text.trim().isNotEmpty
        ? _ruleFactorValue.text.trim().replaceAll(',', '.')
        : null,
    'tax_account': _ruleTaxAccount.text.trim().isEmpty
        ? null
        : _ruleTaxAccount.text.trim(),
    'accounting_account': _ruleAccountingAccount.text.trim().isEmpty
        ? null
        : _ruleAccountingAccount.text.trim(),
    'is_active': _ruleActive,
    'notes': _ruleNotes.text.trim().isEmpty ? null : _ruleNotes.text.trim(),
  };

  void _showError(Object error) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          '${_tr('membership.saveFailed')}: ${error is AirmiusApiException ? error.userMessage : _tr('common.errorDetails')}',
        ),
      ),
    );
  }

  Future<bool> _saveSettings() async {
    setState(() => _saving = true);
    try {
      final management = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.updateMembershipSettings(widget.club.id, {
        'membership_requests_enabled': _requestsEnabled,
        'member_pause_requests_enabled': _pauseRequestsEnabled,
        'membership_payment_methods': _paymentMethods.toList(),
        'membership_application_fields': _fieldModes,
        'membership_application_document_types': _documentTypes,
        'membership_application_documents': _documents,
      });
      if (mounted) {
        setState(() => _localManagement = management);
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

  Future<bool> _saveType() async {
    if (_typeName.text.trim().isEmpty) return false;
    setState(() => _saving = true);
    try {
      final repo = AirmiusServicesScope.of(context).repositories.clubs;
      late final AirmiusClubManagement management;
      if (_editingTypeId == null) {
        management = await repo.createMembershipType(
          widget.club.id,
          _typePayload(),
        );
      } else {
        management = await repo.updateMembershipType(
          widget.club.id,
          _editingTypeId!,
          _typePayload(),
        );
      }
      if (mounted) {
        setState(() {
          _localManagement = management;
          _clearForms();
        });
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
    if (_ruleName.text.trim().isEmpty ||
        _ruleAmount.text.trim().isEmpty ||
        _ruleValidFrom.text.trim().isEmpty) {
      return false;
    }
    setState(() => _saving = true);
    try {
      final repo = AirmiusServicesScope.of(context).repositories.clubs;
      late final AirmiusClubManagement management;
      if (_editingRuleId == null) {
        management = await repo.createContributionRule(
          widget.club.id,
          _rulePayload(),
        );
      } else {
        management = await repo.updateContributionRule(
          widget.club.id,
          _editingRuleId!,
          _rulePayload(),
        );
      }
      if (mounted) {
        setState(() {
          _localManagement = management;
          _clearForms();
        });
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
      _typeApplicationFields = {
        ..._fieldModes,
        if (type['application_fields'] is JsonMap)
          ...(type['application_fields'] as JsonMap).map(
            (key, value) => MapEntry(key, '$value'),
          ),
      };
    });
  }

  void _editRule(JsonMap rule) {
    setState(() {
      _editingRuleId = _intOrNull(rule['id']);
      _ruleTypeId = _intOrNull(rule['club_membership_type_id']);
      _rulePolicyDocumentId = _intOrNull(rule['club_policy_document_id']);
      _ruleName.text = _string(rule['name']);
      _ruleAmount.text = _string(rule['amount']);
      _ruleInterval = _string(rule['billing_interval'], fallback: 'monthly');
      _ruleProrationPolicy = _string(
        rule['proration_policy'],
        fallback: 'prorate_days',
      );
      _ruleValidFrom.text = _membershipDateDisplay(rule['valid_from']);
      if (_ruleValidFrom.text.isEmpty) {
        _ruleValidFrom.text = _membershipDateDisplay(DateTime.now());
      }
      _ruleValidUntil.text = _membershipDateDisplay(rule['valid_until']);
      _ruleAgeMin.text = _string(rule['age_min']);
      _ruleAgeMax.text = _string(rule['age_max']);
      _ruleFactorKey = _string(rule['factor_key'], fallback: 'standard');
      _ruleFactorOperator = _string(
        rule['factor_operator'],
        fallback: 'percent',
      );
      _ruleFactorValue.text = _string(rule['factor_value']);
      _ruleTaxAccount.text = _string(rule['tax_account']);
      _ruleAccountingAccount.text = _string(rule['accounting_account']);
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
      _typeApplicationFields = {..._fieldModes};
    });
  }

  void _solidarityTypeTemplate() {
    _editingTypeId = null;
    _typeName.text = _tr('membership.solidarityTypeName');
    _typeSlug.text = 'solidarische-mitgliedschaft';
    _typeDescription.text = _tr('membership.solidarityTypeDescription');
    _typePublic = true;
    _typeActive = true;
    _typeApplicationFields = {..._fieldModes};
  }

  void _newRuleForm() {
    setState(() {
      _editingRuleId = null;
      _ruleTypeId = null;
      _rulePolicyDocumentId = null;
      _ruleName.clear();
      _ruleAmount.clear();
      _ruleFactorKey = 'standard';
      _ruleFactorOperator = 'percent';
      _ruleFactorValue.clear();
      _ruleTaxAccount.clear();
      _ruleAccountingAccount.clear();
      _ruleInterval = 'monthly';
      _ruleProrationPolicy = 'prorate_days';
      _ruleValidFrom.text = _membershipDateDisplay(DateTime.now());
      _ruleValidUntil.clear();
      _ruleAgeMin.clear();
      _ruleAgeMax.clear();
      _ruleNotes.clear();
      _ruleActive = true;
    });
  }

  String _intervalLabel(String value) {
    return switch (value) {
      'none' => _tr('membership.interval.none'),
      'monthly' => _tr('membership.interval.monthly'),
      'quarterly' => _tr('membership.interval.quarterly'),
      'four_monthly' => _tr('membership.interval.fourMonthly'),
      'semi_yearly' => _tr('membership.interval.semiYearly'),
      'yearly' => _tr('membership.interval.yearly'),
      'once' => _tr('membership.interval.once'),
      _ => value,
    };
  }

  String _paymentLabel(String value) {
    return switch (value) {
      'bank_transfer' => _tr('membership.payment.bankTransfer'),
      'cash' => _tr('membership.payment.cash'),
      'sepa_debit' => _tr('membership.payment.sepaDebit'),
      _ => value,
    };
  }

  String _fieldModeLabel(String value) {
    return switch (value) {
      'required' => _tr('membership.field.required'),
      'optional' => _tr('membership.field.optional'),
      'off' => _tr('membership.field.hidden'),
      _ => value,
    };
  }

  String _applicationFieldLabel(String key) {
    return switch (key) {
      'first_name' => _tr('membership.field.firstName'),
      'last_name' => _tr('membership.field.lastName'),
      'birth_date' => _tr('membership.field.birthDate'),
      'gender' => _tr('membership.field.gender'),
      'nationality' => _tr('membership.field.nationality'),
      'athlete_license_number' => _tr('membership.field.licenseNumber'),
      'email' => _tr('membership.field.email'),
      'phone' => _tr('membership.field.phone'),
      'street' => _tr('membership.field.street'),
      'house_number' => _tr('membership.field.houseNumber'),
      'postal_code' => _tr('membership.field.postalCode'),
      'city' => _tr('membership.field.city'),
      'guardian_email' => _tr('membership.field.guardianEmail'),
      'emergency_phone' => _tr('membership.field.emergencyPhone'),
      'iban' => _tr('membership.field.iban'),
      'bic' => _tr('membership.field.bic'),
      _ => key
          .replaceAll('_', ' ')
          .split(' ')
          .where((part) => part.isNotEmpty)
          .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
          .join(' '),
    };
  }

  String _documentTypeLabel(String value) {
    JsonMap? configured;
    for (final type in _documentTypes) {
      if (_string(type['value']) == value) {
        configured = type;
        break;
      }
    }
    if (configured != null && configured['labels'] is JsonMap) {
      final labels = configured['labels'] as JsonMap;
      final language = switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.en => 'en',
        AirmiusLanguage.fr => 'fr',
        AirmiusLanguage.ar => 'ar',
        AirmiusLanguage.de => 'de',
      };
      final label = _string(labels[language], fallback: _string(labels['de']));
      if (label.isNotEmpty) return label;
    }
    return switch (value) {
      'privacy' => _tr('membership.document.privacy'),
      'statutes' => _tr('membership.document.statutes'),
      'rules' => _tr('membership.document.rules'),
      'fees' => _tr('membership.document.fees'),
      'sepa' => _tr('membership.document.sepa'),
      'other' => _tr('membership.document.other'),
      _ => value,
    };
  }

  Future<void> _openDocumentTypeDialog({int? index}) async {
    final existing = index == null
        ? const <String, dynamic>{}
        : _documentTypes[index];
    final labels = existing['labels'] is JsonMap
        ? existing['labels'] as JsonMap
        : const <String, dynamic>{};
    final value = TextEditingController(
      text: _string(
        existing['value'],
        fallback: 'custom_${DateTime.now().millisecondsSinceEpoch}',
      ),
    );
    final german = TextEditingController(text: _string(labels['de']));
    final english = TextEditingController(text: _string(labels['en']));
    final french = TextEditingController(text: _string(labels['fr']));
    final arabic = TextEditingController(text: _string(labels['ar']));

    final saved = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(
          index == null
              ? 'Dokument-Kategorie hinzufügen'
              : 'Dokument-Kategorie bearbeiten',
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AirmiusTextField(
                label: 'Schlüssel',
                hint: 'z. B. vereinsordnung',
                controller: value,
              ),
              const SizedBox(height: 10),
              AirmiusTextField(
                label: 'Deutsch *',
                hint: 'Bezeichnung auf Deutsch',
                controller: german,
              ),
              const SizedBox(height: 10),
              AirmiusTextField(
                label: 'English',
                hint: 'Label in English',
                controller: english,
              ),
              const SizedBox(height: 10),
              AirmiusTextField(
                label: 'Français',
                hint: 'Libellé en français',
                controller: french,
              ),
              const SizedBox(height: 10),
              AirmiusTextField(
                label: 'العربية',
                hint: 'الاسم بالعربية',
                controller: arabic,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(_tr('membership.cancel')),
          ),
          FilledButton(
            onPressed: () {
              if (german.text.trim().isEmpty || value.text.trim().isEmpty) {
                return;
              }
              Navigator.pop(dialogContext, {
                'value': value.text.trim().toLowerCase().replaceAll(
                  RegExp(r'[^a-z0-9_-]+'),
                  '_',
                ),
                'labels': {
                  'de': german.text.trim(),
                  'en': english.text.trim().isEmpty
                      ? german.text.trim()
                      : english.text.trim(),
                  'fr': french.text.trim().isEmpty
                      ? german.text.trim()
                      : french.text.trim(),
                  'ar': arabic.text.trim().isEmpty
                      ? (english.text.trim().isEmpty
                            ? german.text.trim()
                            : english.text.trim())
                      : arabic.text.trim(),
                },
              });
            },
            child: Text(_tr('membership.save')),
          ),
        ],
      ),
    );

    for (final controller in [value, german, english, french, arabic]) {
      controller.dispose();
    }
    if (saved == null || !mounted) return;
    setState(() {
      if (index == null) {
        _documentTypes.add(saved);
      } else {
        _documentTypes[index] = saved;
      }
    });
  }

  Future<void> _openDocumentDialog({int? index}) async {
    final existing = index == null
        ? const <String, dynamic>{}
        : _documents[index];
    final title = TextEditingController(text: _string(existing['title']));
    final url = TextEditingController(text: _string(existing['url']));
    final description = TextEditingController(
      text: _string(existing['description']),
    );
    var type = _string(existing['type'], fallback: 'privacy');
    var membershipTypeId = _intOrNull(existing['membership_type_id']);
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
            backgroundColor: airmiusSurfaceColor(context),
            title: Text(
              index == null
                  ? _tr('membership.addDocument')
                  : _tr('membership.editDocument'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: type,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.documentType'),
                    ),
                    items:
                        (_documentTypes.isEmpty
                                ? const [
                                    'privacy',
                                    'statutes',
                                    'rules',
                                    'fees',
                                    'sepa',
                                    'other',
                                  ]
                                : _documentTypes
                                      .map((item) => _string(item['value']))
                                      .toList())
                            .map(
                              (item) => DropdownMenuItem(
                                value: item,
                                child: Text(_documentTypeLabel(item)),
                              ),
                            )
                            .toList(),
                    onChanged: (value) =>
                        setDialogState(() => type = value ?? type),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int?>(
                    initialValue: membershipTypeId,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: _tr('membership.appliesToType'),
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(_tr('membership.allTypes')),
                      ),
                      for (final membershipType
                          in widget.management.membershipTypes)
                        DropdownMenuItem<int?>(
                          value: _intOrNull(membershipType['id']),
                          child: Text(_string(membershipType['name'])),
                        ),
                    ],
                    onChanged: (value) =>
                        setDialogState(() => membershipTypeId = value),
                  ),
                  const SizedBox(height: 10),
                  SegmentedButton<String>(
                    segments: [
                      ButtonSegment(
                        value: 'link',
                        icon: Icon(Icons.link_outlined),
                        label: Text(_tr('membership.link')),
                      ),
                      ButtonSegment(
                        value: 'file',
                        icon: Icon(Icons.upload_file_outlined),
                        label: Text(_tr('membership.file')),
                      ),
                    ],
                    selected: {source},
                    onSelectionChanged: (selection) =>
                        setDialogState(() => source = selection.first),
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: _tr('membership.invoiceTitle'),
                    hint: _tr('membership.documentTitleHint'),
                    controller: title,
                  ),
                  const SizedBox(height: 10),
                  if (source == 'link')
                    AirmiusTextField(
                      label: _tr('membership.link'),
                      hint: 'https://...',
                      controller: url,
                    )
                  else
                    _DocumentUploadBox(
                      fileName: uploadedFile == null
                          ? pickedFile?.name
                          : _string(
                              uploadedFile!['display_name'],
                              fallback:
                                  pickedFile?.name ??
                                  _tr('membership.fileUploaded'),
                            ),
                      uploading: uploading,
                      onPick: () async {
                        final result = await FilePicker.platform.pickFiles(
                          withData: true,
                        );
                        final file = result?.files.single;
                        if (file == null) return;
                        setDialogState(() {
                          pickedFile = file;
                          title.text = title.text.trim().isEmpty
                              ? file.name
                              : title.text;
                          uploadedFile = null;
                        });
                      },
                      onUpload: pickedFile == null || uploading
                          ? null
                          : () async {
                              setDialogState(() => uploading = true);
                              try {
                                final uploaded =
                                    await _uploadMembershipDocument(
                                      pickedFile!,
                                    );
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
                  AirmiusTextField(
                    label: _tr('membership.description'),
                    hint: _tr('membership.optional'),
                    controller: description,
                    maxLines: 2,
                  ),
                  const SizedBox(height: 10),
                  _SettingsToggle(
                    title: _tr('membership.visibleInApplication'),
                    value: visible,
                    onChanged: (value) => setDialogState(() => visible = value),
                  ),
                  const SizedBox(height: 8),
                  _SettingsToggle(
                    title: _tr('membership.confirmationRequired'),
                    value: isRequired,
                    onChanged: (value) =>
                        setDialogState(() => isRequired = value),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(_tr('membership.cancel')),
              ),
              FilledButton(
                onPressed: () {
                  if (source == 'file' && uploadedFile == null) return;
                  if (source == 'link' &&
                      title.text.trim().isEmpty &&
                      url.text.trim().isEmpty) {
                    return;
                  }
                  Navigator.pop(context, {
                    'id': _string(
                      existing['id'],
                      fallback: 'doc-${DateTime.now().millisecondsSinceEpoch}',
                    ),
                    'type': type,
                    'membership_type_id': membershipTypeId,
                    'title': title.text.trim(),
                    'url': source == 'file' && uploadedFile != null
                        ? _string(uploadedFile!['url'])
                        : url.text.trim(),
                    'description': description.text.trim(),
                    'is_visible': visible,
                    'is_required': isRequired,
                    'file_id': source == 'file' && uploadedFile != null
                        ? uploadedFile!['id']
                        : null,
                    'file_name': source == 'file' && uploadedFile != null
                        ? _string(
                            uploadedFile!['display_name'],
                            fallback: pickedFile?.name ?? '',
                          )
                        : null,
                  });
                },
                child: Text(_tr('membership.save')),
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
    final path =
        '${base.path.endsWith('/') ? base.path : '${base.path}/'}api/v1/uploads';
    final request = http.MultipartRequest(
      'POST',
      base.replace(path: path, query: null, fragment: null),
    );

    request.headers.addAll({
      'Accept': 'application/json',
      'X-Airmius-Locale': services.environment.locale,
      if (services.authState.session?.token.isNotEmpty == true)
        'Authorization': 'Bearer ${services.authState.session!.token}',
    });
    request.fields['scope'] = 'club';
    request.fields['club_id'] = '${widget.club.id}';

    if (file.bytes != null && file.bytes!.isNotEmpty) {
      request.files.add(
        http.MultipartFile.fromBytes(
          'file',
          file.bytes!,
          filename: file.name,
          contentType: _contentTypeFor(file),
        ),
      );
    } else if (file.path != null && file.path!.trim().isNotEmpty) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'file',
          file.path!,
          filename: file.name,
          contentType: _contentTypeFor(file),
        ),
      );
    } else {
      throw AirmiusApiException(
        statusCode: 0,
        body: _tr('membership.fileUnreadable'),
        path: '/api/v1/uploads',
      );
    }

    final response = await http.Response.fromStream(await request.send());
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: '/api/v1/uploads',
      );
    }

    final decoded = jsonDecode(response.body);
    final json = decoded is JsonMap
        ? decoded
        : <String, dynamic>{'data': decoded};
    final data = json['data'];
    return data is JsonMap ? data : json;
  }

  MediaType _contentTypeFor(PlatformFile file) {
    final extension = (file.extension ?? file.name.split('.').last)
        .toLowerCase();
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
      'docx' => MediaType(
        'application',
        'vnd.openxmlformats-officedocument.wordprocessingml.document',
      ),
      'xls' => MediaType('application', 'vnd.ms-excel'),
      'xlsx' => MediaType(
        'application',
        'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      ),
      _ => MediaType('application', 'octet-stream'),
    };
  }

  Future<void> _openApplicationSettingsSheet(
    List<JsonMap> applicationFields,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          void update(VoidCallback fn) {
            setState(fn);
            setSheetState(() {});
          }

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * .88,
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _tr('membership.applicationFields'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _tr('membership.applicationFieldsBody'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontWeight: FontWeight.w700,
                          height: 1.35,
                        ),
                      ),
                      const SizedBox(height: 16),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          _SettingsToggle(
                            title: _tr('membership.allowRequests'),
                            value: _requestsEnabled,
                            onChanged: (value) =>
                                update(() => _requestsEnabled = value),
                          ),
                          _SettingsToggle(
                            title: _tr('membership.allowPauseRequests'),
                            value: _pauseRequestsEnabled,
                            onChanged: (value) =>
                                update(() => _pauseRequestsEnabled = value),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Eyebrow(_tr('membership.paymentMethods')),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final method in const [
                            'bank_transfer',
                            'cash',
                            'sepa_debit',
                          ])
                            FilterChip(
                              selected: _paymentMethods.contains(method),
                              label: Text(_paymentLabel(method)),
                              onSelected: (selected) => update(
                                () => selected
                                    ? _paymentMethods.add(method)
                                    : _paymentMethods.remove(method),
                              ),
                              selectedColor: airmiusAccentColor(
                                context,
                              ).withValues(alpha: .22),
                              backgroundColor: airmiusSurfaceSoftColor(context),
                              side: BorderSide(
                                color: _paymentMethods.contains(method)
                                    ? airmiusAccentColor(context)
                                    : airmiusBorderColor(context),
                              ),
                              labelStyle: TextStyle(
                                color: _paymentMethods.contains(method)
                                    ? airmiusAccentColor(context)
                                    : airmiusMutedColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                        ],
                      ),
                      if (applicationFields.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        Eyebrow(_tr('membership.applicationFieldList')),
                        const SizedBox(height: 8),
                        LayoutBuilder(
                          builder: (context, constraints) {
                            final twoColumns = constraints.maxWidth >= 620;
                            final fieldWidth = twoColumns
                                ? (constraints.maxWidth - 10) / 2
                                : constraints.maxWidth;
                            return Wrap(
                              spacing: 10,
                              runSpacing: 10,
                              children: [
                                for (final field in applicationFields)
                                  SizedBox(
                                    width: fieldWidth,
                                    child: DropdownButtonFormField<String>(
                                      initialValue: (() {
                                        final current =
                                            _fieldModes[_string(
                                              field['key'],
                                            )] ??
                                            _string(
                                              field['mode'],
                                              fallback: 'off',
                                            );
                                        return const [
                                              'required',
                                              'optional',
                                              'off',
                                            ].contains(current)
                                            ? current
                                            : 'off';
                                      })(),
                                      dropdownColor: airmiusSurfaceSoftColor(
                                        context,
                                      ),
                                      decoration: InputDecoration(
                                        labelText: _string(
                                          field['label'],
                                          fallback: _string(
                                            field['key'],
                                            fallback: _tr('membership.field'),
                                          ),
                                        ),
                                      ),
                                      items:
                                          const ['required', 'optional', 'off']
                                              .map(
                                                (mode) => DropdownMenuItem(
                                                  value: mode,
                                                  child: Text(
                                                    _fieldModeLabel(mode),
                                                  ),
                                                ),
                                              )
                                              .toList(),
                                      onChanged: (value) {
                                        final key = _string(field['key']);
                                        if (key.isNotEmpty && value != null) {
                                          update(
                                            () => _fieldModes[key] = value,
                                          );
                                        }
                                      },
                                    ),
                                  ),
                              ],
                            );
                          },
                        ),
                      ],
                      const SizedBox(height: 18),
                      Row(
                        children: [
                          Expanded(
                            child: TextButton(
                              onPressed: () => Navigator.pop(sheetContext),
                              child: Text(_tr('membership.cancel')),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: FilledButton.icon(
                              onPressed: _saving
                                  ? null
                                  : () async {
                                      final saved = await _saveSettings();
                                      if (saved && sheetContext.mounted) {
                                        Navigator.pop(sheetContext);
                                      }
                                    },
                              icon: Icon(Icons.save_outlined),
                              label: Text(
                                _saving
                                    ? _tr('membership.saving')
                                    : _tr('membership.save'),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
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
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          void update(VoidCallback fn) {
            setState(fn);
            setSheetState(() {});
          }

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * .86,
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _editingTypeId == null
                            ? _tr('membership.createMembershipType')
                            : _tr('membership.editMembershipType'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 14),
                      if (_editingTypeId == null) ...[
                        OutlinedButton.icon(
                          onPressed: () => update(_solidarityTypeTemplate),
                          icon: const Icon(Icons.volunteer_activism_outlined),
                          label: Text(_tr('membership.solidarityTypeName')),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          _tr('membership.solidarityTypeHint'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 14),
                      ],
                      AirmiusTextField(
                        label: _tr('membership.name'),
                        hint: _tr('membership.membershipTypeHint'),
                        controller: _typeName,
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        label: _tr('membership.slug'),
                        hint: _tr('membership.optional'),
                        controller: _typeSlug,
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        label: _tr('membership.description'),
                        hint: _tr('membership.description'),
                        controller: _typeDescription,
                        maxLines: 2,
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 10,
                        runSpacing: 8,
                        children: [
                          _SettingsToggle(
                            title: _tr('membership.publiclyVisible'),
                            value: _typePublic,
                            onChanged: (value) =>
                                update(() => _typePublic = value),
                          ),
                          _SettingsToggle(
                            title: _tr('membership.active'),
                            value: _typeActive,
                            onChanged: (value) =>
                                update(() => _typeActive = value),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      Row(
                        children: [
                          Expanded(
                            child: TextButton(
                              onPressed: () => Navigator.pop(sheetContext),
                              child: Text(_tr('membership.cancel')),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: FilledButton.icon(
                              onPressed: _saving
                                  ? null
                                  : () async {
                                      final saved = await _saveType();
                                      if (saved && sheetContext.mounted) {
                                        Navigator.pop(sheetContext);
                                      }
                                    },
                              icon: Icon(Icons.badge_outlined),
                              label: Text(
                                _saving
                                    ? _tr('membership.saving')
                                    : (_editingTypeId == null
                                          ? _tr('membership.create')
                                          : _tr('membership.update')),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _openRuleSheet({
    JsonMap? rule,
    required List<JsonMap> membershipTypes,
    required List<JsonMap> policyDocuments,
    required List<String> intervals,
  }) async {
    if (rule == null) {
      _newRuleForm();
    } else {
      _editRule(rule);
    }
    final List<JsonMap> ruleTypes =
        _effectiveManagement.contributionRuleTypes.isEmpty
        ? const <JsonMap>[
            {'value': 'standard', 'label': 'Standardbeitrag'},
            {'value': 'base', 'label': 'Grundbeitrag'},
            {'value': 'family', 'label': 'Familienbeitrag'},
            {'value': 'youth', 'label': 'Jugendbeitrag'},
            {'value': 'supporting', 'label': 'Foerderbeitrag'},
            {'value': 'department', 'label': 'Abteilungszuschlag'},
            {'value': 'admission', 'label': 'Aufnahmegebuehr'},
            {'value': 'allocation', 'label': 'Umlage'},
            {'value': 'service', 'label': 'Leistungspaket'},
            {'value': 'special', 'label': 'Sonderbeitrag'},
            {'value': 'discount', 'label': 'Rabatt'},
            {'value': 'sibling_discount', 'label': 'Geschwisterrabatt'},
            {'value': 'reduction', 'label': 'Ermaessigung'},
            {'value': 'exemption', 'label': 'Befreiung'},
          ]
        : _effectiveManagement.contributionRuleTypes;
    final selectedRuleType = ruleTypes.any(
      (type) => _string(type['value']) == _ruleFactorKey,
    )
        ? _ruleFactorKey
        : 'standard';

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          void update(VoidCallback fn) {
            setState(fn);
            setSheetState(() {});
          }

          return SafeArea(
            child: Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * .9,
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _editingRuleId == null
                            ? _tr('membership.createContributionRule')
                            : _tr('membership.editContributionRule'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<int>(
                        initialValue: _ruleTypeId ?? 0,
                        dropdownColor: airmiusSurfaceSoftColor(context),
                        decoration: InputDecoration(
                          labelText: _tr('membership.membershipType'),
                        ),
                        items: [
                          DropdownMenuItem<int>(
                            value: 0,
                            child: Text(_tr('membership.allTypes')),
                          ),
                          for (final type in membershipTypes)
                            if (_intOrNull(type['id']) != null)
                              DropdownMenuItem<int>(
                                value: _intOrNull(type['id'])!,
                                child: Text(
                                  _string(
                                    type['name'],
                                    fallback: _tr('membership.type'),
                                  ),
                                ),
                              ),
                        ],
                        onChanged: (value) => update(
                          () => _ruleTypeId = value == null || value == 0
                              ? null
                              : value,
                        ),
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<int>(
                        initialValue:
                            policyDocuments.any(
                              (document) =>
                                  _intOrNull(document['id']) ==
                                  _rulePolicyDocumentId,
                            )
                            ? _rulePolicyDocumentId
                            : 0,
                        dropdownColor: airmiusSurfaceSoftColor(context),
                        decoration: InputDecoration(
                          labelText: _tr('membership.contributionModelVersion'),
                        ),
                        items: [
                          DropdownMenuItem<int>(
                            value: 0,
                            child: Text(
                              _tr('membership.noContributionModelVersion'),
                            ),
                          ),
                          for (final document in policyDocuments)
                            if (_intOrNull(document['id']) != null)
                              DropdownMenuItem<int>(
                                value: _intOrNull(document['id'])!,
                                child: Text(
                                  '${_string(document['title'])} · '
                                  '${_string(document['version_label'])}',
                                ),
                              ),
                        ],
                        onChanged: (value) => update(
                          () => _rulePolicyDocumentId =
                              value == null || value == 0 ? null : value,
                        ),
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        label: _tr('membership.ruleName'),
                        hint: _tr('membership.ruleName'),
                        controller: _ruleName,
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        label: _tr('membership.contributionEur'),
                        hint: '12.00',
                        controller: _ruleAmount,
                        keyboardType: TextInputType.number,
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        initialValue: selectedRuleType,
                        dropdownColor: airmiusSurfaceSoftColor(context),
                        decoration: InputDecoration(
                          labelText: _tr('membership.ruleFactor'),
                        ),
                        items: [
                          for (final type in ruleTypes)
                            DropdownMenuItem(
                              value: _string(type['value']),
                              child: Text(
                                _string(
                                  type['label'],
                                  fallback: _tr(
                                    'membership.ruleFactor.${_string(type['value'])}',
                                  ),
                                ),
                              ),
                            ),
                        ],
                        onChanged: (value) => update(() {
                          _ruleFactorKey = value ?? 'standard';
                        }),
                      ),
                      if (_ruleFactorKey == 'discount') ...[
                        const SizedBox(height: 10),
                        DropdownButtonFormField<String>(
                          initialValue: _ruleFactorOperator == 'fixed'
                              ? 'fixed'
                              : 'percent',
                          dropdownColor: airmiusSurfaceSoftColor(context),
                          decoration: InputDecoration(
                            labelText: _tr('membership.discountOperator'),
                          ),
                          items: [
                            for (final operator in ['percent', 'fixed'])
                              DropdownMenuItem(
                                value: operator,
                                child: Text(
                                  _tr('membership.discountOperator.$operator'),
                                ),
                              ),
                          ],
                          onChanged: (value) => update(() {
                            _ruleFactorOperator = value ?? 'percent';
                          }),
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          label: _tr('membership.discountValue'),
                          hint: '10',
                          controller: _ruleFactorValue,
                          keyboardType: TextInputType.number,
                        ),
                      ],
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: AirmiusTextField(
                              label: _tr('membership.taxAccount'),
                              hint: 'UST-7',
                              controller: _ruleTaxAccount,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: AirmiusTextField(
                              label: _tr('membership.accountingAccount'),
                              hint: '4000',
                              controller: _ruleAccountingAccount,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        initialValue: intervals.contains(_ruleInterval)
                            ? _ruleInterval
                            : intervals.first,
                        dropdownColor: airmiusSurfaceSoftColor(context),
                        decoration: InputDecoration(
                          labelText: _tr('membership.interval'),
                        ),
                        items: [
                          for (final interval in intervals)
                            DropdownMenuItem(
                              value: interval,
                              child: Text(_intervalLabel(interval)),
                            ),
                        ],
                        onChanged: (value) => update(
                          () => _ruleInterval = value ?? _ruleInterval,
                        ),
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        initialValue: [
                          'prorate_days',
                          'full_amount',
                          'next_period',
                        ].contains(_ruleProrationPolicy)
                            ? _ruleProrationPolicy
                            : 'prorate_days',
                        dropdownColor: airmiusSurfaceSoftColor(context),
                        decoration: InputDecoration(
                          labelText: _tr('membership.entryBilling'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'prorate_days',
                            child: Text(_tr('membership.proration.prorateDays')),
                          ),
                          DropdownMenuItem(
                            value: 'full_amount',
                            child: Text(_tr('membership.proration.fullAmount')),
                          ),
                          DropdownMenuItem(
                            value: 'next_period',
                            child: Text(_tr('membership.proration.nextPeriod')),
                          ),
                        ],
                        onChanged: (value) => update(
                          () => _ruleProrationPolicy =
                              value ?? _ruleProrationPolicy,
                        ),
                      ),
                      const SizedBox(height: 10),
                      LayoutBuilder(
                        builder: (context, constraints) {
                          final twoColumns = constraints.maxWidth >= 520;
                          final fieldWidth = twoColumns
                              ? (constraints.maxWidth - 10) / 2
                              : constraints.maxWidth;
                          return Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              SizedBox(
                                width: fieldWidth,
                                child: AirmiusTextField(
                                  label: _tr('membership.validFrom'),
                                  hint: _tr('membership.isoDateHint'),
                                  controller: _ruleValidFrom,
                                  inputFormatters:
                                      _membershipDateInputFormatters,
                                ),
                              ),
                              SizedBox(
                                width: fieldWidth,
                                child: AirmiusTextField(
                                  label: _tr('membership.validUntil'),
                                  hint: _tr('membership.optional'),
                                  controller: _ruleValidUntil,
                                  inputFormatters:
                                      _membershipDateInputFormatters,
                                ),
                              ),
                              SizedBox(
                                width: fieldWidth,
                                child: AirmiusTextField(
                                  label: _tr('membership.ageFrom'),
                                  hint: _tr('membership.optional'),
                                  controller: _ruleAgeMin,
                                  keyboardType: TextInputType.number,
                                ),
                              ),
                              SizedBox(
                                width: fieldWidth,
                                child: AirmiusTextField(
                                  label: _tr('membership.ageUntil'),
                                  hint: _tr('membership.optional'),
                                  controller: _ruleAgeMax,
                                  keyboardType: TextInputType.number,
                                ),
                              ),
                            ],
                          );
                        },
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        label: _tr('membership.note'),
                        hint: _tr('membership.optional'),
                        controller: _ruleNotes,
                        maxLines: 2,
                      ),
                      const SizedBox(height: 12),
                      _SettingsToggle(
                        title: _tr('membership.ruleActive'),
                        value: _ruleActive,
                        onChanged: (value) => update(() => _ruleActive = value),
                      ),
                      const SizedBox(height: 18),
                      Row(
                        children: [
                          Expanded(
                            child: TextButton(
                              onPressed: () => Navigator.pop(sheetContext),
                              child: Text(_tr('membership.cancel')),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: FilledButton.icon(
                              onPressed: _saving
                                  ? null
                                  : () async {
                                      final saved = await _saveRule();
                                      if (saved && sheetContext.mounted) {
                                        Navigator.pop(sheetContext);
                                      }
                                    },
                              icon: Icon(Icons.tune_outlined),
                              label: Text(
                                _saving
                                    ? _tr('membership.saving')
                                    : (_editingRuleId == null
                                          ? _tr('membership.create')
                                          : _tr('membership.update')),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
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
    final management = _effectiveManagement;
    final membershipTypes = management.membershipTypes;
    final contributionRules = management.contributionRules;
    final contributionPolicyDocuments =
        management.contributionPolicyDocuments;
    final applicationFields =
        management.settings['membership_application_fields'] is List
        ? (management.settings['membership_application_fields'] as List)
              .whereType<JsonMap>()
              .toList()
        : const <JsonMap>[];
    final intervals = management.contributionIntervals.isEmpty
        ? const [
            'none',
            'monthly',
            'quarterly',
            'four_monthly',
            'semi_yearly',
            'yearly',
            'once',
          ]
        : management.contributionIntervals;

    final requiredFields = applicationFields.where((field) {
      final key = _string(field['key']);
      final mode = _fieldModes[key] ?? _string(field['mode'], fallback: 'off');
      return mode == 'required';
    }).length;
    final activeTypes = membershipTypes
        .where((type) => _bool(type['is_active']))
        .length;
    final activeRules = contributionRules
        .where((rule) => _bool(rule['is_active']))
        .length;

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Eyebrow(_tr('membership.contributionRules'))),
              TextButton.icon(
                onPressed: () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.close, size: 18),
                label: const Text('Abbrechen'),
              ),
              StatusPill('$activeRules ${_tr('membership.activeAfter')}'),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final tab in const [
                _MembershipSectionTabData(
                  'types',
                  'membership.rulesTab.types',
                  Icons.badge_outlined,
                ),
                _MembershipSectionTabData(
                  'application',
                  'membership.rulesTab.applicationOptions',
                  Icons.assignment_outlined,
                ),
                _MembershipSectionTabData(
                  'fields',
                  'membership.rulesTab.membershipFields',
                  Icons.fact_check_outlined,
                ),
                _MembershipSectionTabData(
                  'documentTypes',
                  'membership.documentTypes',
                  Icons.category_outlined,
                ),
                _MembershipSectionTabData(
                  'documents',
                  'membership.rulesTab.documents',
                  Icons.description_outlined,
                ),
                _MembershipSectionTabData(
                  'rules',
                  'membership.rulesTab.rules',
                  Icons.tune_outlined,
                ),
                _MembershipSectionTabData(
                  'summary',
                  'membership.summary',
                  Icons.fact_check_outlined,
                ),
              ])
                _MembershipSectionTab(
                  tab: tab,
                  label: _tr(tab.labelKey),
                  selected: _rulesTab == tab.value,
                  onTap: () => setState(() => _rulesTab = tab.value),
                ),
            ],
          ),
          const SizedBox(height: 14),
          if (_rulesTab == 'application') ...[
            Wrap(
              alignment: WrapAlignment.center,
              spacing: 10,
              runSpacing: 10,
              children: [
                _AdminMiniStat(
                  icon: Icons.how_to_reg_outlined,
                  title: _tr('membership.requests'),
                  value: _requestsEnabled
                      ? _tr('membership.on')
                      : _tr('membership.off'),
                ),
                _AdminMiniStat(
                  icon: Icons.pause_circle_outline,
                  title: _tr('membership.pauses'),
                  value: _pauseRequestsEnabled
                      ? _tr('membership.on')
                      : _tr('membership.off'),
                ),
                _AdminMiniStat(
                  icon: Icons.payments_outlined,
                  title: _tr('membership.paymentMethods'),
                  value: '${_paymentMethods.length}',
                ),
                _AdminMiniStat(
                  icon: Icons.fact_check_outlined,
                  title: _tr('membership.requiredFieldsShort'),
                  value: '$requiredFields',
                ),
              ],
            ),
            const SizedBox(height: 12),
            AirmiusButton(
              label: _tr('membership.editApplicationFields'),
              icon: Icons.edit_note_outlined,
              onPressed: () => _openApplicationSettingsSheet(applicationFields),
            ),
          ] else if (_rulesTab == 'fields') ...[
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(_tr('membership.rulesTab.fields')),
                  const SizedBox(height: 6),
                  Text(
                    _tr('membership.typeFieldsHint'),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final type in membershipTypes)
                        ChoiceChip(
                          label: Text(_string(type['name'])),
                          selected: _editingTypeId == _intOrNull(type['id']),
                          onSelected: (_) => _editType(type),
                        ),
                    ],
                  ),
                  if (_editingTypeId == null)
                    Padding(
                      padding: const EdgeInsets.only(top: 14),
                      child: Text(
                        'Wähle zuerst einen Mitgliedschaftstyp aus, dessen Antragsfelder du konfigurieren möchtest.',
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    )
                  else ...[
                    const SizedBox(height: 14),
                    for (final entry in _fieldModes.entries)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: DropdownButtonFormField<String>(
                          initialValue:
                              const [
                                'required',
                                'optional',
                                'off',
                              ].contains(_typeApplicationFields[entry.key])
                              ? _typeApplicationFields[entry.key]
                              : 'off',
                          decoration: InputDecoration(
                            labelText: _applicationFieldLabel(entry.key),
                          ),
                          items: const ['required', 'optional', 'off']
                              .map(
                                (mode) => DropdownMenuItem(
                                  value: mode,
                                  child: Text(_fieldModeLabel(mode)),
                                ),
                              )
                              .toList(),
                          onChanged: (value) {
                            if (value != null) {
                              setState(
                                () => _typeApplicationFields[entry.key] = value,
                              );
                            }
                          },
                        ),
                      ),
                    AirmiusButton(
                      label: _tr('membership.save'),
                      icon: Icons.save_outlined,
                      onPressed: _saving ? null : () => _saveType(),
                    ),
                  ],
                ],
              ),
            ),
          ] else if (_rulesTab == 'documentTypes') ...[
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(_tr('membership.documentTypes')),
                  const SizedBox(height: 6),
                  Text(
                    'Lege Kategorien wie Datenschutz oder Satzung fest. Dateien lädst du im Bereich Dokumente hoch.',
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 12),
                  for (final entry in _documentTypes.indexed)
                    Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: airmiusSurfaceSoftColor(context),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: airmiusBorderColor(context)),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _documentTypeLabel(
                                    _string(entry.$2['value']),
                                  ),
                                  style: TextStyle(
                                    color: airmiusTextColor(context),
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  _string(entry.$2['value']),
                                  style: TextStyle(
                                    color: airmiusMutedColor(context),
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () =>
                                _openDocumentTypeDialog(index: entry.$1),
                            icon: const Icon(Icons.edit_outlined),
                          ),
                          if (!const [
                            'privacy',
                            'statutes',
                            'rules',
                            'fees',
                            'sepa',
                            'other',
                          ].contains(entry.$2['value']))
                            IconButton(
                              onPressed: () => setState(
                                () => _documentTypes.removeAt(entry.$1),
                              ),
                              icon: const Icon(
                                Icons.delete_outline,
                                color: Colors.redAccent,
                              ),
                            ),
                        ],
                      ),
                    ),
                  if (_documentTypes.isEmpty)
                    _EmptyAdminHint(
                      text: 'Noch keine Dokument-Kategorien vorhanden.',
                    ),
                  const SizedBox(height: 4),
                  AirmiusButton(
                    label: 'Dokument-Kategorie hinzufügen',
                    icon: Icons.add,
                    onPressed: () => _openDocumentTypeDialog(),
                  ),
                  const SizedBox(height: 8),
                  AirmiusButton(
                    label: _tr('membership.save'),
                    icon: Icons.save_outlined,
                    onPressed: _saving ? null : () => _saveSettings(),
                  ),
                ],
              ),
            ),
          ] else if (_rulesTab == 'summary') ...[
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(_tr('membership.summary')),
                  const SizedBox(height: 6),
                  Text(
                    'Prüfe deine Einstellungen ein letztes Mal. Mit „Fertig“ werden die Vereinsregeln gespeichert.',
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _AdminMiniStat(
                        icon: Icons.badge_outlined,
                        title: 'Mitgliedschaftstypen',
                        value: '${membershipTypes.length}',
                      ),
                      _AdminMiniStat(
                        icon: Icons.tune_outlined,
                        title: 'Beitragsregeln',
                        value: '$activeRules',
                      ),
                      _AdminMiniStat(
                        icon: Icons.description_outlined,
                        title: 'Dokumente',
                        value: '${_documents.length}',
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  AirmiusButton(
                    label: 'Fertig',
                    icon: Icons.check_circle_outline,
                    onPressed: _saving ? null : () => _saveSettings(),
                  ),
                ],
              ),
            ),
          ] else if (_rulesTab == 'documents') ...[
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${_filteredDocuments.length} / ${_documents.length} '
                    '${_tr('membership.documentsAfter')}',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => _openDocumentDialog(),
                  icon: Icon(Icons.add_link_outlined),
                  color: airmiusAccentColor(context),
                  tooltip: _tr('membership.addDocument'),
                ),
              ],
            ),
            const SizedBox(height: 10),
            TextField(
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                hintText: 'Dokumente durchsuchen ...',
              ),
              onChanged: (value) => setState(() => _documentSearch = value),
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: _documentTypeFilter,
              decoration: const InputDecoration(labelText: 'Dokument-Kategorie'),
              items: const [
                DropdownMenuItem(
                  value: 'all',
                  child: Text('Alle Dokument-Kategorien'),
                ),
                DropdownMenuItem(value: 'privacy', child: Text('Datenschutz')),
                DropdownMenuItem(value: 'statutes', child: Text('Satzung')),
                DropdownMenuItem(value: 'rules', child: Text('Regeln')),
                DropdownMenuItem(value: 'fees', child: Text('Beitragsordnung')),
                DropdownMenuItem(value: 'sepa', child: Text('SEPA')),
                DropdownMenuItem(value: 'other', child: Text('Sonstiges')),
              ],
              onChanged: (value) =>
                  setState(() => _documentTypeFilter = value ?? 'all'),
            ),
            const SizedBox(height: 12),
            if (_filteredDocuments.isEmpty)
              _EmptyAdminHint(text: _tr('membership.noDocuments'))
            else
              for (final document in _filteredDocuments)
                _MembershipDocumentLine(
                  document: document,
                  typeLabel: _documentTypeLabel(
                    _string(document['type'], fallback: 'other'),
                  ),
                  onEdit: () =>
                      _openDocumentDialog(index: _documents.indexOf(document)),
                  onDelete: () => setState(() => _documents.remove(document)),
                ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(
                  label: _tr('membership.addDocument'),
                  icon: Icons.add_link_outlined,
                  secondary: true,
                  onPressed: () => _openDocumentDialog(),
                ),
                AirmiusButton(
                  label: _tr('membership.saveDocuments'),
                  icon: Icons.save_outlined,
                  onPressed: _saving
                      ? null
                      : () {
                          _saveSettings();
                        },
                ),
              ],
            ),
          ] else if (_rulesTab == 'types') ...[
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(_tr('membership.typeSetupFirst')),
                  const SizedBox(height: 6),
                  Text(
                    _tr('membership.typeSetupHint'),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: _tr('membership.createType'),
                        icon: Icons.add_circle_outline,
                        onPressed: () => _openTypeSheet(),
                      ),
                      AirmiusButton(
                        label: _tr('membership.editMembershipType'),
                        icon: Icons.edit_outlined,
                        secondary: true,
                        onPressed: membershipTypes.isEmpty
                            ? null
                            : () => _openTypeSheet(type: membershipTypes.first),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'Beispiele zur Orientierung – werden nicht automatisch gespeichert',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      Chip(label: Text('Jugendmitglied')),
                      Chip(label: Text('Aktives Mitglied')),
                      Chip(label: Text('Probetraining')),
                      Chip(label: Text('Fördermitglied')),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              alignment: WrapAlignment.center,
              spacing: 10,
              runSpacing: 10,
              children: [
                _AdminMiniStat(
                  icon: Icons.badge_outlined,
                  title: _tr('membership.types'),
                  value: '${membershipTypes.length}',
                ),
                _AdminMiniStat(
                  icon: Icons.check_circle_outline,
                  title: _tr('membership.active'),
                  value: '$activeTypes',
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (membershipTypes.isEmpty)
              _EmptyAdminHint(text: _tr('membership.noMembershipTypes'))
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final type in membershipTypes)
                    ActionChip(
                      label: Text(
                        _string(type['name'], fallback: _tr('membership.type')),
                      ),
                      onPressed: () => _openTypeSheet(type: type),
                      avatar: Icon(
                        _bool(type['is_active'])
                            ? Icons.check_circle_outline
                            : Icons.pause_circle_outline,
                        color: airmiusAccentColor(context),
                        size: 18,
                      ),
                      backgroundColor: airmiusSurfaceSoftColor(context),
                      labelStyle: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                      side: BorderSide(color: airmiusBorderColor(context)),
                    ),
                ],
              ),
            const SizedBox(height: 12),
            AirmiusButton(
              label: _tr('membership.createType'),
              icon: Icons.add_circle_outline,
              onPressed: () => _openTypeSheet(),
            ),
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${contributionRules.length} '
                    '${_tr('membership.rulesAfter')}, '
                    '$activeRules ${_tr('membership.activeAfter')}',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => _openRuleSheet(
                    membershipTypes: membershipTypes,
                    policyDocuments: contributionPolicyDocuments,
                    intervals: intervals,
                  ),
                  icon: Icon(Icons.add_circle_outline),
                  color: airmiusAccentColor(context),
                  tooltip: _tr('membership.createRule'),
                ),
              ],
            ),
            const SizedBox(height: 10),
            if (contributionRules.isEmpty)
              _EmptyAdminHint(text: _tr('membership.noContributionRules'))
            else
              for (final rule in contributionRules)
                _ContributionRuleLine(
                  rule: rule,
                  intervalLabel: _intervalLabel,
                  onEdit: () => _openRuleSheet(
                    rule: rule,
                    membershipTypes: membershipTypes,
                    policyDocuments: contributionPolicyDocuments,
                    intervals: intervals,
                  ),
                ),
            const SizedBox(height: 12),
            AirmiusButton(
              label: _tr('membership.createRule'),
              icon: Icons.tune_outlined,
              onPressed: () => _openRuleSheet(
                membershipTypes: membershipTypes,
                policyDocuments: contributionPolicyDocuments,
                intervals: intervals,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _AdminMiniStat extends StatelessWidget {
  const _AdminMiniStat({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 126),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: airmiusAccentColor(context), size: 19),
          const SizedBox(width: 9),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 12,
                  height: 1.15,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ],
      ),
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
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: airmiusMutedColor(context),
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _SettingsToggle extends StatelessWidget {
  const _SettingsToggle({
    required this.title,
    required this.value,
    required this.onChanged,
  });

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
          color: value
              ? airmiusAccentColor(context).withValues(alpha: .12)
              : airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: value
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              value ? Icons.check_box_outlined : Icons.check_box_outline_blank,
              color: value
                  ? airmiusAccentColor(context)
                  : airmiusMutedColor(context),
              size: 20,
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                title,
                style: TextStyle(
                  color: value
                      ? airmiusTextColor(context)
                      : airmiusMutedColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
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
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.upload_file_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  fileName == null || fileName!.trim().isEmpty
                      ? t('membership.noFileSelected')
                      : fileName!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: uploading ? null : onPick,
                icon: Icon(Icons.folder_open_outlined),
                label: Text(t('membership.chooseFile')),
              ),
              FilledButton.icon(
                onPressed: onUpload,
                icon: uploading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(Icons.cloud_upload_outlined),
                label: Text(
                  uploading
                      ? t('membership.uploading')
                      : t('membership.upload'),
                ),
              ),
            ],
          ),
        ],
      ),
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

  bool _bool(Object? value) =>
      value == true || '$value'.toLowerCase() == 'true' || '$value' == '1';

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final title = _text(document['title'], fallback: t('membership.document'));
    final url = _text(document['url']);
    final isRequired = _bool(document['is_required']);
    final visible = _bool(document['is_visible']);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            isRequired
                ? Icons.verified_user_outlined
                : Icons.description_outlined,
            color: isRequired
                ? AirmiusColors.green
                : airmiusAccentColor(context),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$typeLabel – '
                  '${isRequired ? t('membership.field.required') : t('membership.optional')} – '
                  '${visible ? t('membership.visible') : t('membership.hidden')}',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (url.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    url,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusAccentColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
            ),
          ),
          IconButton(
            onPressed: onEdit,
            icon: Icon(Icons.edit_outlined),
            color: airmiusAccentColor(context),
            tooltip: t('membership.edit'),
          ),
          IconButton(
            onPressed: onDelete,
            icon: Icon(Icons.delete_outline),
            color: AirmiusColors.red,
            tooltip: t('membership.remove'),
          ),
        ],
      ),
    );
  }
}

class _ContributionRuleLine extends StatelessWidget {
  const _ContributionRuleLine({
    required this.rule,
    required this.intervalLabel,
    required this.onEdit,
  });

  final JsonMap rule;
  final String Function(String value) intervalLabel;
  final VoidCallback onEdit;

  String _text(Object? value, {String fallback = ''}) {
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final active =
        rule['is_active'] == true ||
        '${rule['is_active']}'.toLowerCase() == 'true' ||
        '${rule['is_active']}' == '1';
    final type = _text(
      rule['membership_type_name'],
      fallback: t('membership.allTypes'),
    );
    final amount = _text(rule['amount'], fallback: '0');
    final interval = intervalLabel(
      _text(rule['billing_interval'], fallback: 'monthly'),
    );
    final validFrom = _membershipDateDisplay(rule['valid_from']);
    final validUntil = _membershipDateDisplay(rule['valid_until']);
    final factorKey = _text(rule['factor_key'], fallback: 'standard');
    final factorLabel = t('membership.ruleFactor.$factorKey');
    final factorValue = _text(rule['factor_value']);
    final policyDocument = rule['policy_document'] is JsonMap
        ? rule['policy_document'] as JsonMap
        : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.tune_outlined,
            color: active ? AirmiusColors.green : airmiusMutedColor(context),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(
                    rule['name'],
                    fallback: t('membership.contributionRule'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$type - $amount EUR - $interval',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (factorKey != 'standard') ...[
                  const SizedBox(height: 2),
                  Text(
                    factorKey == 'discount' && factorValue.isNotEmpty
                        ? '$factorLabel: $factorValue'
                        : factorLabel,
                    style: TextStyle(
                      color: airmiusAccentColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
                const SizedBox(height: 2),
                Text(
                  '${t('membership.validFrom')} ${validFrom.isEmpty ? '-' : validFrom} '
                  '${t('membership.to')} ${validUntil.isEmpty ? t('membership.open') : validUntil}',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (policyDocument != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    '${t('membership.approvedBasis')}: '
                    '${_text(policyDocument['title'])} · '
                    '${_text(policyDocument['version_label'])}',
                    style: TextStyle(
                      color: airmiusAccentColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ],
            ),
          ),
          TextButton.icon(
            onPressed: onEdit,
            icon: Icon(Icons.edit_outlined, size: 18),
            label: Text(t('membership.edit')),
          ),
        ],
      ),
    );
  }
}

class _MembershipKpi {
  const _MembershipKpi({
    required this.title,
    required this.value,
    required this.detail,
    this.icon,
    this.accent,
  });

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
        final textScale = MediaQuery.textScalerOf(context).scale(1);
        final maxColumns = constraints.maxWidth >= 720
            ? 4
            : constraints.maxWidth < 400 && textScale > 1.15
            ? 1
            : 2;
        final columns = cards.length < maxColumns ? cards.length : maxColumns;
        const gap = 10.0;
        final cardHeight = textScale > 1.15 ? 184.0 : 150.0;
        final width = (constraints.maxWidth - gap * (columns - 1)) / columns;

        return Wrap(
          alignment: WrapAlignment.center,
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final card in cards)
              SizedBox(
                width: width,
                height: cardHeight,
                child: _MembershipKpiCard(card: card),
              ),
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
          return SizedBox(
            width: width,
            height: height,
            child: _MembershipKpiCard(card: card),
          );
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
            tile(cards[2], constraints.maxWidth, 154),
            const SizedBox(height: gap),
            Row(
              children: [
                tile(cards[0], (constraints.maxWidth - gap) / 2, 174),
                const SizedBox(width: gap),
                tile(cards[1], (constraints.maxWidth - gap) / 2, 174),
              ],
            ),
            const SizedBox(height: gap),
            Row(
              children: [
                tile(cards[3], (constraints.maxWidth - gap) / 2, 174),
                const SizedBox(width: gap),
                tile(cards[4], (constraints.maxWidth - gap) / 2, 174),
              ],
            ),
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
    final borderColor = accent == null
        ? airmiusBorderColor(context)
        : Color.lerp(airmiusBorderColor(context), accent, 0.55) ??
              airmiusBorderColor(context);
    final backgroundColor = accent == null
        ? airmiusSurfaceColor(context)
        : Color.lerp(airmiusSurfaceColor(context), accent, 0.08) ??
              airmiusSurfaceColor(context);

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
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                    letterSpacing: .2,
                  ),
                ),
              ),
              if (card.icon != null) ...[
                const SizedBox(width: 8),
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: (accent ?? Theme.of(context).colorScheme.outline)
                        .withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(
                      color: (accent ?? Theme.of(context).colorScheme.outline)
                          .withValues(alpha: 0.35),
                    ),
                  ),
                  child: Icon(
                    card.icon,
                    color: accent ?? airmiusMutedColor(context),
                    size: 18,
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 12),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              card.value,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 23,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            card.detail,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 12,
              height: 1.25,
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberEntry {
  const _MemberEntry({
    required this.id,
    required this.externalId,
    required this.isExternal,
    required this.duplicateCandidate,
    required this.name,
    required this.email,
    required this.role,
    required this.status,
    required this.membership,
    required this.type,
    required this.number,
    required this.balance,
    required this.sepa,
  });

  final int id;
  final int? externalId;
  final bool isExternal;
  final JsonMap? duplicateCandidate;
  final String name;
  final String email;
  final String role;
  final String status;
  final JsonMap membership;
  final String type;
  final String number;
  final String balance;
  final bool sepa;

  _MemberEntry copyWith({String? balance}) {
    return _MemberEntry(
      id: id,
      externalId: externalId,
      isExternal: isExternal,
      duplicateCandidate: duplicateCandidate,
      name: name,
      email: email,
      role: role,
      status: status,
      membership: membership,
      type: type,
      number: number,
      balance: balance ?? this.balance,
      sepa: sepa,
    );
  }
}

class _PaymentScheduleEntry {
  const _PaymentScheduleEntry({
    required this.member,
    required this.dueDate,
    required this.days,
    required this.amount,
    required this.interval,
    required this.paymentMethod,
  });

  final _MemberEntry member;
  final DateTime? dueDate;
  final int? days;
  final String amount;
  final String interval;
  final String paymentMethod;
}

class _InvoiceRunPreviewTile extends StatelessWidget {
  const _InvoiceRunPreviewTile({
    required this.row,
    required this.money,
    required this.period,
  });

  final JsonMap row;
  final String money;
  final String period;

  @override
  Widget build(BuildContext context) {
    final ready = row['can_create'] == true;
    final snapshot = row['snapshot'] is Map
        ? Map<String, dynamic>.from(row['snapshot'] as Map)
        : const <String, dynamic>{};
    final fullAmount = '${snapshot['full_amount'] ?? ''}';
    final componentAmount = '${snapshot['component_amount'] ?? ''}';
    final discountAmount = '${snapshot['discount_amount'] ?? ''}';
    final billableDays = snapshot['billable_days'];
    final periodDays = snapshot['period_days'];
    final paymentFlow = '${row['payment_flow'] ?? ''}';
    final status = ready
        ? 'Bereit'
        : ('${row['skip_reason'] ?? ''}' == 'duplicate'
              ? 'Schon vorhanden'
              : ('${row['skip_reason'] ?? ''}' == 'missing_recipient'
                    ? 'Daten fehlen'
                    : 'Übersprungen'));

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            paymentFlow == 'direct_debit'
                ? Icons.account_balance_outlined
                : Icons.mark_email_read_outlined,
            color: ready ? AirmiusColors.green : AirmiusColors.amber,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${row['member_name'] ?? 'Mitglied'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${row['member_email'] ?? '-'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 6),
                Text(
                  '$period · $money',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (snapshot['prorated'] == true &&
                    billableDays != null &&
                    periodDays != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Anteilig: $billableDays von $periodDays Tagen · Vollbetrag $fullAmount EUR',
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                ],
                if (componentAmount.isNotEmpty &&
                    componentAmount != '0.00') ...[
                  const SizedBox(height: 3),
                  Text(
                    'Zuschläge: $componentAmount EUR',
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                ],
                if (discountAmount.isNotEmpty &&
                    discountAmount != '0.00') ...[
                  const SizedBox(height: 3),
                  Text(
                    'Rabatte: -$discountAmount EUR',
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                ],
                if (row['recipient_ok'] == false) ...[
                  const SizedBox(height: 4),
                  Text(
                    paymentFlow == 'direct_debit'
                        ? 'Lastschriftangaben oder Mandat fehlen.'
                        : 'Für den Rechnungsversand fehlt eine E-Mail-Adresse.',
                    style: const TextStyle(
                      color: AirmiusColors.amber,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          StatusPill(
            status,
            color: ready ? AirmiusColors.green : AirmiusColors.amber,
          ),
        ],
      ),
    );
  }
}

class _InvoiceEntry {
  const _InvoiceEntry({
    required this.id,
    required this.userId,
    required this.title,
    required this.person,
    required this.amount,
    required this.outstandingAmount,
    required this.overpaidAmount,
    required this.isPartiallyPaid,
    required this.hasOverpayment,
    required this.statusKey,
    required this.status,
    required this.color,
    required this.date,
  });

  final int id;
  final int userId;
  final String title;
  final String person;
  final String amount;
  final String outstandingAmount;
  final String overpaidAmount;
  final bool isPartiallyPaid;
  final bool hasOverpayment;
  final String statusKey;
  final String status;
  final Color color;
  final DateTime? date;
}

class _PaymentEntry {
  const _PaymentEntry({
    required this.id,
    required this.userId,
    required this.invoiceId,
    required this.purpose,
    this.statusKey = 'paid',
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
  final String statusKey;
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
  const _BankEntry({
    required this.id,
    required this.invoiceId,
    required this.status,
    required this.title,
    required this.detail,
  });

  final int id;
  final int invoiceId;
  final String status;
  final String title;
  final String detail;
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({
    required this.member,
    required this.onManage,
    this.showManage = true,
    this.selectable = false,
    this.selected = false,
    this.onSelected,
  });

  final _MemberEntry member;
  final VoidCallback onManage;
  final bool showManage;
  final bool selectable;
  final bool selected;
  final ValueChanged<bool>? onSelected;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context),
            width: selected ? 2 : 1,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: selectable
              ? () => onSelected?.call(!selected)
              : (showManage ? onManage : null),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  if (selectable) ...[
                    Checkbox(
                      value: selected,
                      onChanged: (value) => onSelected?.call(value ?? false),
                    ),
                    const SizedBox(width: 4),
                  ],
                  AirmiusAvatar(member.name),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          member.name,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          member.email,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (showManage)
                    IconButton(
                      tooltip: t('membership.manageMember'),
                      onPressed: onManage,
                      icon: Icon(Icons.more_vert),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill(
                    t(_memberFilterTranslationKey(member.type)),
                    color: member.type == 'Extern'
                        ? AirmiusColors.amber
                        : airmiusAccentColor(context),
                  ),
                  if (member.duplicateCandidate != null)
                    StatusPill(
                      t(
                        member.duplicateCandidate?['ambiguous'] == true
                            ? 'membership.duplicate.ambiguous'
                            : 'membership.duplicate.review',
                      ),
                      color: AirmiusColors.amber,
                    ),
                  StatusPill(
                    member.balance == '0,00 EUR'
                        ? t('membership.settled')
                        : '${t('membership.open')} ${member.balance}',
                    color: member.balance == '0,00 EUR'
                        ? AirmiusColors.green
                        : AirmiusColors.red,
                  ),
                ],
              ),
              if (showManage) ...[
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        selectable
                            ? 'Antippen zum Auswählen, Menü für Details.'
                            : t('membership.tapForDetails'),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                    if (selectable)
                      TextButton.icon(
                        onPressed: onManage,
                        icon: const Icon(Icons.more_horiz, size: 18),
                        label: const Text('Details'),
                      ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _PaymentScheduleCard extends StatelessWidget {
  const _PaymentScheduleCard({
    required this.entry,
    required this.dueLabel,
    required this.dueColor,
    required this.dateLabel,
    required this.intervalLabel,
    required this.paymentMethodLabel,
    required this.onCreateInvoice,
    required this.onEdit,
  });

  final _PaymentScheduleEntry entry;
  final String dueLabel;
  final Color dueColor;
  final String dateLabel;
  final String intervalLabel;
  final String paymentMethodLabel;
  final VoidCallback onCreateInvoice;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AirmiusAvatar(entry.member.name),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        entry.member.name,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        entry.member.email,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                StatusPill(dueLabel, color: dueColor),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _ScheduleFact(
                  icon: Icons.event_outlined,
                  label: t('membership.schedule.nextPayment'),
                  value: dateLabel,
                ),
                _ScheduleFact(
                  icon: Icons.payments_outlined,
                  label: t('membership.contribution'),
                  value: entry.amount,
                ),
                _ScheduleFact(
                  icon: Icons.repeat_outlined,
                  label: t('membership.interval'),
                  value: intervalLabel,
                ),
                _ScheduleFact(
                  icon: Icons.account_balance_wallet_outlined,
                  label: t('membership.paymentMethod'),
                  value: paymentMethodLabel,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                AirmiusButton(
                  label: t('membership.createInvoice'),
                  icon: Icons.receipt_long_outlined,
                  onPressed: onCreateInvoice,
                ),
                AirmiusButton(
                  label: t('membership.edit'),
                  icon: Icons.edit_outlined,
                  secondary: true,
                  onPressed: onEdit,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ScheduleFact extends StatelessWidget {
  const _ScheduleFact({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 132),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 17, color: airmiusAccentColor(context)),
          const SizedBox(width: 8),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InvoicePickerSummary extends StatelessWidget {
  const _InvoicePickerSummary({required this.invoice});

  final _InvoiceEntry invoice;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          invoice.title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Wrap(
          spacing: 8,
          runSpacing: 4,
          children: [
            Text(
              invoice.person,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
            Text(
              '${t('membership.amountEur')}: ${invoice.amount}',
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _InvoiceChoiceCard extends StatelessWidget {
  const _InvoiceChoiceCard({
    required this.invoice,
    required this.selected,
    required this.onTap,
  });

  final _InvoiceEntry invoice;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected
              ? airmiusAccentColor(context).withValues(alpha: .14)
              : airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    invoice.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                StatusPill(invoice.status, color: invoice.color),
              ],
            ),
            const SizedBox(height: 8),
            _InvoiceMetaLine(
              icon: Icons.person_outline,
              label: t('membership.member'),
              value: invoice.person,
            ),
            _InvoiceMetaLine(
              icon: Icons.euro_outlined,
              label: t('membership.amountEur'),
              value: invoice.amount,
            ),
            _InvoiceMetaLine(
              icon: Icons.account_balance_wallet_outlined,
              label: t('membership.outstandingBalance'),
              value: invoice.outstandingAmount,
            ),
            if (invoice.isPartiallyPaid)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  t('membership.partiallyPaid'),
                  style: TextStyle(
                    color: AirmiusColors.amber,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _InvoiceMetaLine extends StatelessWidget {
  const _InvoiceMetaLine({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: airmiusMutedColor(context)),
          const SizedBox(width: 6),
          SizedBox(
            width: 92,
            child: Text(
              label,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InvoiceLine extends StatelessWidget {
  const _InvoiceLine({required this.invoice, required this.onManage});

  final _InvoiceEntry invoice;
  final VoidCallback onManage;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: airmiusSurfaceSoftColor(context),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Icon(
              Icons.receipt_long_outlined,
              color: airmiusAccentColor(context),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  invoice.title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${invoice.person} - ${invoice.amount}',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (invoice.isPartiallyPaid)
                  Text(
                    '${t('membership.outstandingBalance')}: ${invoice.outstandingAmount}',
                  ),
                if (invoice.hasOverpayment)
                  Text(
                    '${t('membership.overpayment')}: ${invoice.overpaidAmount}',
                  ),
              ],
            ),
          ),
          StatusPill(invoice.status, color: invoice.color),
          IconButton(
            tooltip: t('membership.manageInvoice'),
            onPressed: onManage,
            icon: Icon(Icons.more_vert),
          ),
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
    final t = AirmiusScope.of(context).t;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: payment.color.withValues(alpha: 0.45)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: payment.color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: payment.color.withValues(alpha: 0.45)),
            ),
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
                      child: Text(
                        payment.title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      payment.amount,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(width: 4),
                    IconButton(
                      tooltip: t('membership.edit'),
                      visualDensity: VisualDensity.compact,
                      onPressed: payment.statusKey == 'returned'
                          ? null
                          : onEdit,
                      icon: Icon(
                        Icons.edit_outlined,
                        color: payment.color,
                        size: 20,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  payment.person,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (payment.statusKey == 'returned')
                      StatusPill(
                        (sepaBatchLabels[AirmiusScope.of(
                              context,
                            ).language.locale.languageCode] ??
                            sepaBatchLabels['en']!)['paymentReturned']!,
                        color: AirmiusColors.amber,
                      ),
                    StatusPill(payment.method, color: payment.color),
                    StatusPill(payment.date, color: airmiusMutedColor(context)),
                  ],
                ),
                if (payment.detail.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    payment.detail,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
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
    final t = AirmiusScope.of(context).t;
    final typeLabel = entry.type == 'income'
        ? t('membership.financeType.income')
        : t('membership.financeType.expense');
    final accountLabel = entry.account == 'bank'
        ? t('membership.financeAccount.bank')
        : t('membership.financeAccount.cash');
    final amountPrefix = entry.type == 'income' ? '+' : '-';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: entry.color.withValues(alpha: 0.45)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: entry.color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: entry.color.withValues(alpha: 0.45)),
            ),
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
                      child: Text(
                        entry.title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '$amountPrefix ${entry.amount}',
                      style: TextStyle(
                        color: entry.color,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(width: 4),
                    IconButton(
                      tooltip: t('membership.edit'),
                      visualDensity: VisualDensity.compact,
                      onPressed: onEdit,
                      icon: Icon(
                        Icons.edit_outlined,
                        color: entry.color,
                        size: 20,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(typeLabel, color: entry.color),
                    StatusPill(
                      accountLabel,
                      color: airmiusAccentColor(context),
                    ),
                    StatusPill(entry.date, color: airmiusMutedColor(context)),
                  ],
                ),
                if (entry.detail.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    entry.detail,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
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
  const _BankLine({required this.entry, required this.onConfirm});

  final _BankEntry entry;
  final VoidCallback? onConfirm;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Icon(Icons.sync_alt_outlined, color: AirmiusColors.green),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  entry.title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  entry.detail,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          if (onConfirm != null)
            IconButton(
              tooltip: t('membership.confirmAssignment'),
              onPressed: onConfirm,
              icon: Icon(Icons.task_alt_outlined, color: AirmiusColors.green),
            ),
        ],
      ),
    );
  }
}

class _ExportTile extends StatelessWidget {
  const _ExportTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.status,
  });

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
            decoration: BoxDecoration(
              color: airmiusAccentColor(context).withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Icon(icon, color: airmiusAccentColor(context)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}
