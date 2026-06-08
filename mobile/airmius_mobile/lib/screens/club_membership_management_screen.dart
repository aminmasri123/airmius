import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
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
    _BankEntry(title: 'Ruecklastschrift pruefen', detail: 'SEPA Mandat Junior Mitglied braucht Bestaetigung'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Mitglieder & Beitraege', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Mitglieder & Beitraege',
        subtitle: 'Mitgliederdaten, Rechnungen, Zahlungen, SEPA, DATEV und Import',
        trailing: const StatusPill('Admin'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ZBB Verwaltung'),
                  const SizedBox(height: 10),
                  const Text(
                    'Verein, Mitglieder und Zahlungen in einem nativen Workflow.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Diese Ansicht bildet die Web-App mobil nach: externe Mitglieder erfassen, Mitgliedsnummern vergeben, Rechnungen erstellen, Zahlungen abgleichen und Exporte vorbereiten.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Mitglied importieren', icon: Icons.upload_file_outlined, onPressed: () => openUiAction(context, title: 'Mitglied importieren', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.upload_file_outlined)),
                      AirmiusButton(label: 'Rechnung erstellen', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Rechnung erstellen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.receipt_long_outlined)),
                      AirmiusButton(label: 'SEPA export', icon: Icons.account_balance_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'SEPA export', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.account_balance_outlined)),
                      AirmiusButton(label: 'Membership Ops', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '3', label: 'Mitglieder')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Offene Posten')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '33 EUR', label: 'Monat')),
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
                      AirmiusButton(label: 'Einladung senden', icon: Icons.mark_email_read_outlined, onPressed: () => openUiAction(context, title: 'Einladung senden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.mark_email_read_outlined)),
                      AirmiusButton(label: 'CSV Vorlage', icon: Icons.table_view_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'CSV Vorlage', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.table_view_outlined)),
                      AirmiusButton(label: 'Extern anlegen', icon: Icons.person_add_alt_1_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Extern anlegen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.person_add_alt_1_outlined)),
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
                      AirmiusButton(label: 'Zahlung erfassen', icon: Icons.payments_outlined, onPressed: () => openUiAction(context, title: 'Zahlung erfassen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.payments_outlined)),
                      AirmiusButton(label: 'Mahnung vorbereiten', icon: Icons.notification_important_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Mahnung vorbereiten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.notification_important_outlined)),
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
                      AirmiusButton(label: 'Bankdatei importieren', icon: Icons.cloud_upload_outlined, onPressed: () => openUiAction(context, title: 'Bankdatei importieren', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.cloud_upload_outlined)),
                      AirmiusButton(label: 'Zuordnung bestaetigen', icon: Icons.task_alt_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Zuordnung bestaetigen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.task_alt_outlined)),
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
                  const _ExportTile(icon: Icons.account_balance_wallet_outlined, title: 'SEPA Sammellauf', subtitle: 'Mandate pruefen, Lastschriftlauf vorbereiten und Export erzeugen.', status: 'Bereit'),
                  const _ExportTile(icon: Icons.fact_check_outlined, title: 'DATEV Export', subtitle: 'Rechnungen, Zahlungen und Buchungssaetze fuer Steuerberatung vorbereiten.', status: 'Konfigurierbar'),
                  const _ExportTile(icon: Icons.rule_folder_outlined, title: 'Beitragsregeln', subtitle: 'Monatlich, alle 4 Monate, halbjaehrlich oder jaehrlich pro Mitgliedschaftstyp.', status: 'Aktiv'),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'DATEV export', icon: Icons.ios_share_outlined, onPressed: () => openUiAction(context, title: 'DATEV export', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.ios_share_outlined)),
                      AirmiusButton(label: 'Regeln bearbeiten', icon: Icons.tune_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Regeln bearbeiten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.tune_outlined)),
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
                StatusPill(member.sepa ? 'SEPA Mandat' : 'Ueberweisung', color: member.sepa ? AirmiusColors.green : AirmiusColors.amber),
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
