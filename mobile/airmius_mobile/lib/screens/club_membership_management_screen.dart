import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'membership_operations_screen.dart';
import 'ui_action_result_screen.dart';

class ClubMembershipManagementScreen extends StatefulWidget {
  const ClubMembershipManagementScreen({super.key});

  @override
  State<ClubMembershipManagementScreen> createState() => _ClubMembershipManagementScreenState();
}

class _ClubMembershipManagementScreenState extends State<ClubMembershipManagementScreen> {
  String _filter = 'Alle';
  String _period = 'Juni 2026';
  int? _selectedClubId;
  Future<_ManagedMembershipData?>? _clubFuture;

  int _parseEuroCents(String value) {
    final normalized = value.replaceAll(' EUR', '').replaceAll('.', '').replaceAll(',', '.').trim();
    return ((double.tryParse(normalized) ?? 0) * 100).round();
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

  String _moneyFromValue(Object? value) {
    if (value is num) return _formatEuroAmount(value.toDouble());
    final raw = '$value'.trim();
    if (raw.isEmpty || raw == 'null') return '0,00 EUR';
    final normalized = raw.replaceAll('EUR', '').replaceAll('€', '').replaceAll('.', '').replaceAll(',', '.').trim();
    final parsed = double.tryParse(normalized);
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
        title: _stringFromJson(invoice, ['title', 'number', 'invoice_number'], fallback: 'Rechnung'),
        person: person,
        amount: _moneyFromValue(invoice['amount'] ?? invoice['amount_due'] ?? invoice['total'] ?? invoice['total_amount']),
        status: status,
        color: paid ? AirmiusColors.green : AirmiusColors.amber,
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
    return _ManagedMembershipData(clubs: managed, selectedClub: ClubSummary.fromAirmiusClub(detail));
  }

  void _reloadClub() {
    setState(() => _clubFuture = _loadManagedClub());
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<_ManagedMembershipData?>(
      future: _clubFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
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
        final activeMembersCount = management?.activeMembersCount ?? members.where((member) => member.type == 'Aktiv').length;
        final linkedPeopleCount = management?.linkedPeopleCount ?? members.length;
        final openInvoicesCount = management?.openInvoicesCount ?? invoices.where((invoice) => invoice.status == 'Offen').length;
        final sepaReadyMembersCount = management?.sepaReadyMembersCount ?? members.where((member) => member.sepa).length;
        final openInvoiceTotal = management == null ? _openTotalFromInvoices(invoices) : _formatEuroAmount(management.openInvoiceAmount);
        final recurringContributionTotal = management == null ? _recurringTotalFromMembers(members) : _formatEuroAmount(management.recurringContributionTotal);

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
            _ClubMembershipHeader(onMembershipOps: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
            const SizedBox(height: 14),
            _ClubMembershipClubSelector(
              clubs: data.clubs,
              selectedClubId: club.id,
              onChanged: (clubId) {
                setState(() {
                  _selectedClubId = clubId;
                  _filter = 'Alle';
                  _clubFuture = _loadManagedClub();
                });
              },
            ),
            const SizedBox(height: 14),
            if (management != null) ...[
              _MembershipRulesAdminPanel(club: club, management: management, onChanged: _reloadClub),
              const SizedBox(height: 14),
            ],
            _MembershipKpiGrid(
              cards: [
                _MembershipKpi(title: 'Aktive Mitglieder', value: '$activeMembersCount', detail: 'von $linkedPeopleCount verknüpften Personen'),
                _MembershipKpi(title: 'Offen', value: openInvoiceTotal, detail: '$openInvoicesCount offene Rechnung(en)'),
                _MembershipKpi(title: 'SEPA bereit', value: '$sepaReadyMembersCount', detail: 'Mandate mit IBAN und Referenz'),
                _MembershipKpi(title: 'Wiederkehrende Beiträge', value: recurringContributionTotal, detail: 'Summe aktiver Beitragssätze'),
              ],
            ),
            const SizedBox(height: 14),
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
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Import & Einladung'),
                  const SizedBox(height: 10),
                  const AirmiusTextField(label: 'E-Mail oder CSV-Hinweis', hint: 'mitglied@example.com oder CSV importieren', icon: Icons.alternate_email),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Einladung senden', icon: Icons.mark_email_read_outlined, onPressed: () => openUiAction(context, title: 'Einladung senden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.mark_email_read_outlined)),
                      AirmiusButton(label: 'CSV Vorlage', icon: Icons.table_view_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'CSV Vorlage', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.table_view_outlined)),
                      AirmiusButton(label: 'Extern anlegen', icon: Icons.person_add_alt_1_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Extern anlegen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.person_add_alt_1_outlined)),
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
                      AirmiusButton(label: 'Zahlung erfassen', icon: Icons.payments_outlined, onPressed: () => openUiAction(context, title: 'Zahlung erfassen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.payments_outlined)),
                      AirmiusButton(label: 'Mahnung vorbereiten', icon: Icons.notification_important_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Mahnung vorbereiten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.notification_important_outlined)),
                    ],
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
          const SizedBox(height: 8),
          Text(
            clubs.length > 1
                ? 'Die Daten darunter gehoeren immer zum hier ausgewaehlten Verein.'
                : 'Du hast aktuell fuer diesen Verein Verwaltungsrechte.',
            style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700),
          ),
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

  Future<void> _saveSettings() async {
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
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveType() async {
    if (_typeName.text.trim().isEmpty) return;
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
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveRule() async {
    if (_ruleName.text.trim().isEmpty || _ruleAmount.text.trim().isEmpty || _ruleValidFrom.text.trim().isEmpty) return;
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
    } catch (error) {
      if (mounted) _showError(error);
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
    var visible = existing.isEmpty ? true : _bool(existing['is_visible']);
    var isRequired = _bool(existing['is_required']);

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
                AirmiusTextField(label: 'Titel', hint: 'z. B. Beitragsordnung', controller: title),
                const SizedBox(height: 10),
                AirmiusTextField(label: 'Link', hint: 'https://...', controller: url),
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
                  if (title.text.trim().isEmpty && url.text.trim().isEmpty) return;
                  Navigator.pop(context, {
                    'id': _string(existing['id'], fallback: 'doc-${DateTime.now().millisecondsSinceEpoch}'),
                    'type': type,
                    'title': title.text.trim(),
                    'url': url.text.trim(),
                    'description': description.text.trim(),
                    'is_visible': visible,
                    'is_required': isRequired,
                    'file_id': existing['file_id'],
                    'file_name': existing['file_name'],
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

  @override
  Widget build(BuildContext context) {
    final membershipTypes = widget.management.membershipTypes;
    final contributionRules = widget.management.contributionRules;
    final applicationFields = widget.management.settings['membership_application_fields'] is List ? (widget.management.settings['membership_application_fields'] as List).whereType<JsonMap>().toList() : const <JsonMap>[];
    final intervals = widget.management.contributionIntervals.isEmpty
        ? const ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once']
        : widget.management.contributionIntervals;

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Beitragsregeln'),
          const SizedBox(height: 8),
          const Text('Online-Anfragen, Mitgliedschaftstypen und Beitragsregeln verwalten.', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _SettingsToggle(title: 'Mitgliedsanfragen erlauben', value: _requestsEnabled, onChanged: (value) => setState(() => _requestsEnabled = value)),
              _SettingsToggle(title: 'Pausen-Anfragen erlauben', value: _pauseRequestsEnabled, onChanged: (value) => setState(() => _pauseRequestsEnabled = value)),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final method in const ['bank_transfer', 'cash', 'sepa_debit'])
                FilterChip(
                  selected: _paymentMethods.contains(method),
                  label: Text(_paymentLabel(method)),
                  onSelected: (selected) => setState(() => selected ? _paymentMethods.add(method) : _paymentMethods.remove(method)),
                  selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _paymentMethods.contains(method) ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _paymentMethods.contains(method) ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
              AirmiusButton(label: 'Einstellungen speichern', icon: Icons.save_outlined, onPressed: _saving ? null : _saveSettings),
            ],
          ),
          if (applicationFields.isNotEmpty) ...[
            const SizedBox(height: 18),
            const Eyebrow('Mitgliedsantrag-Felder'),
            const SizedBox(height: 10),
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
                        value: _fieldModes[_string(field['key'])] ?? _string(field['mode'], fallback: 'off'),
                        dropdownColor: AirmiusColors.cardSoft,
                        decoration: InputDecoration(labelText: _string(field['label'], fallback: _string(field['key'], fallback: 'Feld'))),
                        items: const ['required', 'optional', 'off'].map((mode) => DropdownMenuItem(value: mode, child: Text(_fieldModeLabel(mode)))).toList(),
                        onChanged: (value) {
                          final key = _string(field['key']);
                          if (key.isNotEmpty && value != null) setState(() => _fieldModes[key] = value);
                        },
                      ),
                    ),
                ],
              );
            }),
          ],
          const SizedBox(height: 18),
          const Eyebrow('Dokumente & Bestaetigungen'),
          const SizedBox(height: 8),
          const Text(
            'Verknuepfe Datenschutz, Satzung, Regeln oder Beitragsordnung. Pflichtdokumente muessen Interessenten vor dem Absenden bestaetigen.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 10),
          AirmiusButton(label: 'Dokument hinzufuegen', icon: Icons.add_link_outlined, secondary: true, onPressed: () => _openDocumentDialog()),
          const SizedBox(height: 10),
          if (_documents.isEmpty)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AirmiusColors.bg,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AirmiusColors.border, style: BorderStyle.solid),
              ),
              child: const Text('Noch keine Dokumente verknuepft.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
            )
          else
            for (final entry in _documents.indexed)
              _MembershipDocumentLine(
                document: entry.$2,
                typeLabel: _documentTypeLabel(_string(entry.$2['type'], fallback: 'other')),
                onEdit: () => _openDocumentDialog(index: entry.$1),
                onDelete: () => setState(() => _documents.removeAt(entry.$1)),
              ),
          const SizedBox(height: 18),
          const Eyebrow('Mitgliedschaftstyp'),
          const SizedBox(height: 10),
          AirmiusTextField(label: 'Name', hint: 'z. B. Jugendmitglied', controller: _typeName),
          const SizedBox(height: 10),
          AirmiusTextField(label: 'Slug', hint: 'optional', controller: _typeSlug),
          const SizedBox(height: 10),
          AirmiusTextField(label: 'Beschreibung', hint: 'Beschreibung', controller: _typeDescription, maxLines: 2),
          const SizedBox(height: 8),
          Wrap(spacing: 10, runSpacing: 8, children: [
            _SettingsToggle(title: 'Oeffentlich sichtbar', value: _typePublic, onChanged: (value) => setState(() => _typePublic = value)),
            _SettingsToggle(title: 'Aktiv', value: _typeActive, onChanged: (value) => setState(() => _typeActive = value)),
            AirmiusButton(label: _editingTypeId == null ? 'Typ speichern' : 'Typ aktualisieren', icon: Icons.badge_outlined, onPressed: _saving ? null : _saveType),
          ]),
          if (membershipTypes.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final type in membershipTypes)
                  ActionChip(
                    label: Text(_string(type['name'], fallback: 'Typ')),
                    onPressed: () => _editType(type),
                    avatar: Icon(_bool(type['is_active']) ? Icons.check_circle_outline : Icons.pause_circle_outline, color: AirmiusColors.blue, size: 18),
                    backgroundColor: AirmiusColors.cardSoft,
                    labelStyle: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
                    side: const BorderSide(color: AirmiusColors.border),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 18),
          const Eyebrow('Neue Beitragsregel'),
          const SizedBox(height: 10),
          DropdownButtonFormField<int>(
            value: _ruleTypeId ?? 0,
            dropdownColor: AirmiusColors.cardSoft,
            decoration: const InputDecoration(labelText: 'Mitgliedschaftstyp'),
            items: [
              const DropdownMenuItem<int>(value: 0, child: Text('Alle Typen')),
              for (final type in membershipTypes)
                if (_intOrNull(type['id']) != null) DropdownMenuItem<int>(value: _intOrNull(type['id'])!, child: Text(_string(type['name'], fallback: 'Typ'))),
            ],
            onChanged: (value) => setState(() => _ruleTypeId = value == null || value == 0 ? null : value),
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
            onChanged: (value) => setState(() => _ruleInterval = value ?? _ruleInterval),
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
          const SizedBox(height: 8),
          Wrap(spacing: 10, runSpacing: 8, children: [
            _SettingsToggle(title: 'Regel aktiv', value: _ruleActive, onChanged: (value) => setState(() => _ruleActive = value)),
            AirmiusButton(label: _editingRuleId == null ? 'Regel speichern' : 'Regel aktualisieren', icon: Icons.tune_outlined, onPressed: _saving ? null : _saveRule),
          ]),
          const SizedBox(height: 16),
          const Eyebrow('Historische Beitragsregeln'),
          const SizedBox(height: 10),
          if (contributionRules.isEmpty)
            const Text('Noch keine Beitragsregeln.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))
          else
            for (final rule in contributionRules) _ContributionRuleLine(rule: rule, intervalLabel: _intervalLabel, onEdit: () => _editRule(rule)),
        ],
      ),
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
            style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          const Text(
            'Mitgliederdaten, Beitragssätze, Rechnungen, SEPA, DATEV und Import wie in der Web-App als native Flutter-Ansicht.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 16),
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
  const _MembershipKpi({required this.title, required this.value, required this.detail});

  final String title;
  final String value;
  final String detail;
}

class _MembershipKpiGrid extends StatelessWidget {
  const _MembershipKpiGrid({required this.cards});

  final List<_MembershipKpi> cards;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 720 ? 4 : 2;
        const gap = 10.0;
        final width = (constraints.maxWidth - gap * (columns - 1)) / columns;

        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final card in cards) SizedBox(width: width, child: _MembershipKpiCard(card: card)),
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
    return Container(
      constraints: const BoxConstraints(minHeight: 108),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: AirmiusColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            card.title.toUpperCase(),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: .2),
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
  const _MemberEntry({required this.name, required this.email, required this.type, required this.number, required this.balance, required this.sepa});

  final String name;
  final String email;
  final String type;
  final String number;
  final String balance;
  final bool sepa;
}

class _InvoiceEntry {
  const _InvoiceEntry({required this.title, required this.person, required this.amount, required this.status, required this.color});

  final String title;
  final String person;
  final String amount;
  final String status;
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
