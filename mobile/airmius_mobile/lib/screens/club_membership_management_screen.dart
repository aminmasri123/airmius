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
  Future<ClubSummary?>? _clubFuture;

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

  Future<ClubSummary?> _loadManagedClub() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    final managed = page.items.map(ClubSummary.fromAirmiusClub).where((club) => club.canManage).toList();
    if (managed.isEmpty) return null;
    final lag = managed.where((club) => club.name.toLowerCase().contains('lag saar')).toList();
    final selected = lag.isNotEmpty ? lag.first : managed.first;
    final detail = await services.repositories.clubs.club(selected.id);
    return ClubSummary.fromAirmiusClub(detail);
  }

  void _reloadClub() {
    setState(() => _clubFuture = _loadManagedClub());
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<ClubSummary?>(
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

        final club = snapshot.data!;
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
                      AirmiusButton(label: 'Regeln bearbeiten', icon: Icons.tune_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Regeln bearbeiten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.tune_outlined)),
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
