import 'package:flutter/material.dart';

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
  Future<bool>? _accessFuture;

  final List<_MemberEntry> _members = const [
    _MemberEntry(name: 'ZBB Konto', email: 'zbb.bop.it@gmail.com', type: 'Aktiv', number: 'ZBB-0001', balance: '0,00 EUR', sepa: true),
    _MemberEntry(name: 'Amir Masri', email: 'amir@example.com', type: 'Extern', number: 'EXT-0002', balance: '12,00 EUR', sepa: false),
    _MemberEntry(name: 'Junior Mitglied', email: 'eltern@example.com', type: 'Jugend', number: 'ZBB-0003', balance: '0,00 EUR', sepa: true),
  ];

  final List<_InvoiceEntry> _invoices = const [
    _InvoiceEntry(title: 'Mitgliedsbeitrag Juni', person: 'ZBB Konto', amount: '12,00 EUR', status: 'Bezahlt', color: AirmiusColors.green),
    _InvoiceEntry(title: 'Mitgliedsbeitrag Juni', person: 'Amir Masri', amount: '12,00 EUR', status: 'Offen', color: AirmiusColors.amber),
    _InvoiceEntry(title: 'SEPA Sammellauf 06/2026', person: 'Junior Mitglied', amount: '9,00 EUR', status: 'Vorgemerkt', color: AirmiusColors.blue),
  ];

  final List<_BankEntry> _bankEntries = const [
    _BankEntry(title: 'Banktransaktion erkannt', detail: '12,00 EUR von Amir Masri - Zuordnung vorgeschlagen'),
    _BankEntry(title: 'Rücklastschrift prüfen', detail: 'SEPA Mandat Junior Mitglied braucht Bestätigung'),
  ];

  int get _activeMembersCount => _members.where((member) => member.type != 'Extern').length;
  int get _sepaReadyMembersCount => _members.where((member) => member.sepa).length;
  int get _openInvoicesCount => _invoices.where((invoice) => invoice.status == 'Offen').length;

  String get _openInvoiceTotal {
    final total = _invoices.where((invoice) => invoice.status == 'Offen').fold<int>(0, (sum, invoice) => sum + _parseEuroCents(invoice.amount));
    return _formatEuro(total);
  }

  String get _recurringContributionTotal {
    final total = _members.fold<int>(0, (sum, member) => sum + (member.type == 'Jugend' ? 900 : member.type == 'Aktiv' ? 1200 : 0));
    return _formatEuro(total);
  }

  int _parseEuroCents(String value) {
    final normalized = value.replaceAll(' EUR', '').replaceAll('.', '').replaceAll(',', '.').trim();
    return ((double.tryParse(normalized) ?? 0) * 100).round();
  }

  String _formatEuro(int cents) {
    final euros = (cents / 100).toStringAsFixed(2).replaceAll('.', ',');
    return '$euros EUR';
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _accessFuture ??= _canManageAnyClub();
  }

  Future<bool> _canManageAnyClub() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    return page.items.map(ClubSummary.fromAirmiusClub).any((club) => club.canManage);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<bool>(
      future: _accessFuture,
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

        if (snapshot.hasError || snapshot.data != true) {
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
                    AirmiusButton(label: 'Erneut prüfen', icon: Icons.refresh_outlined, secondary: true, onPressed: () => setState(() => _accessFuture = _canManageAnyClub())),
                  ],
                ),
              ),
            ),
          );
        }

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Mitglieder & Beiträge', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Mitglieder & Beiträge',
        subtitle: 'Mitgliederdaten, Rechnungen, Zahlungen, SEPA, DATEV und Import',
        trailing: const StatusPill('Admin'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _ClubMembershipHeader(onMembershipOps: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
            const SizedBox(height: 14),
            _MembershipKpiGrid(
              cards: [
                _MembershipKpi(title: 'Aktive Mitglieder', value: '$_activeMembersCount', detail: 'von ${_members.length} verknüpften Personen'),
                _MembershipKpi(title: 'Offen', value: _openInvoiceTotal, detail: '$_openInvoicesCount offene Rechnung(en)'),
                _MembershipKpi(title: 'SEPA bereit', value: '$_sepaReadyMembersCount', detail: 'Mandate mit IBAN und Referenz'),
                _MembershipKpi(title: 'Wiederkehrende Beiträge', value: _recurringContributionTotal, detail: 'Summe aktiver Beitragssätze'),
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
                  for (final member in _members.where((member) => _filter == 'Alle' || member.type == _filter || (_filter == 'Offen' && member.balance != '0,00 EUR')))
                    _MemberCard(member: member),
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
                  for (final invoice in _invoices) _InvoiceLine(invoice: invoice),
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
                  for (final entry in _bankEntries) _BankLine(entry: entry),
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
