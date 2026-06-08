import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'club_contribution_rules_screen.dart';
import 'club_member_directory_screen.dart';
import 'notification_chat_operations_screen.dart';

class ClubFinanceCockpitScreen extends StatefulWidget {
  const ClubFinanceCockpitScreen({super.key, this.initialTab = 'Offen'});

  final String initialTab;

  @override
  State<ClubFinanceCockpitScreen> createState() => _ClubFinanceCockpitScreenState();
}

class _ClubFinanceCockpitScreenState extends State<ClubFinanceCockpitScreen> {
  late String _tab = widget.initialTab;
  bool _showBankTransfers = true;
  bool _showSepa = true;
  bool _showReminders = true;
  bool _showExports = true;

  @override
  Widget build(BuildContext context) {
    final entries = _entries.where((entry) => _tab == 'Alle' || entry.status == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Vereinsfinanzen', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Vereinsfinanzen',
        subtitle: 'Beitraege, Rechnungen, Zahlungen, Bankabgleich, SEPA, Mahnungen und Exporte',
        trailing: const StatusPill('Finance', color: AirmiusColors.amber),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const AirmiusLogo(),
            const SizedBox(height: 14),
            const Text('Vereinsbeitraege brauchen ein eigenes mobiles Cockpit.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
            const SizedBox(height: 8),
            const Text('Diese UI verbindet Beitragsregeln, Mitglieder, Rechnungen, Zahlungen, Banktransfer, SEPA, Mahnungen, DATEV und Export. Laravel liefert spaeter echte Posten, Status und Belege.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '2.840 EUR', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '18', label: 'Posten')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'SEPA', label: 'Naechster Lauf'))]),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.amber.withValues(alpha: .22), backgroundColor: AirmiusColors.cardSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.amber : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          _FinanceControls(showBankTransfers: _showBankTransfers, showSepa: _showSepa, showReminders: _showReminders, showExports: _showExports, onBank: (value) => setState(() => _showBankTransfers = value), onSepa: (value) => setState(() => _showSepa = value), onReminders: (value) => setState(() => _showReminders = value), onExports: (value) => setState(() => _showExports = value)),
          const SizedBox(height: 16),
          for (final entry in entries) ...[
            if ((_showBankTransfers || entry.method != 'Ueberweisung') && (_showSepa || entry.method != 'SEPA')) _FinanceEntryCard(entry: entry, showReminders: _showReminders, showExports: _showExports),
            if ((_showBankTransfers || entry.method != 'Ueberweisung') && (_showSepa || entry.method != 'SEPA')) const SizedBox(height: 12),
          ],
          if (entries.isEmpty) const EmptyPanel('Keine Finanzposten gefunden.'),
          _FinanceWorkflowPanel(tab: _tab),
        ]),
      ),
    );
  }
}

class _FinanceControls extends StatelessWidget {
  const _FinanceControls({required this.showBankTransfers, required this.showSepa, required this.showReminders, required this.showExports, required this.onBank, required this.onSepa, required this.onReminders, required this.onExports});

  final bool showBankTransfers;
  final bool showSepa;
  final bool showReminders;
  final bool showExports;
  final ValueChanged<bool> onBank;
  final ValueChanged<bool> onSepa;
  final ValueChanged<bool> onReminders;
  final ValueChanged<bool> onExports;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    const Eyebrow('Finanz-Filter'),
    const SizedBox(height: 8),
    const Text('Diese Schalter bilden Web-Tabellenfilter mobil ab. Spaeter kommen Zeitraum, Mitgliedschaftstyp, Zahlungsanbieter, Belegstatus und Rollenrechte dazu.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
    const SizedBox(height: 10),
    _FinanceSwitch(icon: Icons.account_balance_outlined, title: 'Banktransfer anzeigen', body: 'Manuelle Ueberweisungen mit Verwendungszweck und Abgleichstatus zeigen.', value: showBankTransfers, onChanged: onBank, color: AirmiusColors.blue),
    _FinanceSwitch(icon: Icons.fact_check_outlined, title: 'SEPA anzeigen', body: 'Mandate, naechster Lauf, fehlende Mandate und SEPA-Status sichtbar machen.', value: showSepa, onChanged: onSepa, color: AirmiusColors.green),
    _FinanceSwitch(icon: Icons.notifications_active_outlined, title: 'Mahnungen anzeigen', body: 'Zahlungserinnerungen, Eskalation und Push/E-Mail-Hinweise vorbereiten.', value: showReminders, onChanged: onReminders, color: AirmiusColors.amber),
    _FinanceSwitch(icon: Icons.file_download_outlined, title: 'Exporte anzeigen', body: 'PDF, CSV, DATEV, SEPA-Datei und Monatsabschluss-Aktionen zeigen.', value: showExports, onChanged: onExports, color: AirmiusColors.green),
  ]));
}

class _FinanceEntryCard extends StatelessWidget {
  const _FinanceEntryCard({required this.entry, required this.showReminders, required this.showExports});

  final _FinanceEntry entry;
  final bool showReminders;
  final bool showExports;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: entry.color.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 50, height: 50, decoration: BoxDecoration(color: entry.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: entry.color.withValues(alpha: .45))), child: Icon(entry.icon, color: entry.color)),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(entry.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 5),
        Text(entry.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(entry.status, color: entry.color), StatusPill(entry.method), StatusPill(entry.amount, color: entry.color)]),
      ])),
    ]),
    const SizedBox(height: 12),
    _FinanceMeta(entry: entry),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: 'Pruefen', icon: Icons.fact_check_outlined, onPressed: () => openUiAction(context, title: '${entry.title} pruefen', body: 'Finanzposten, Mitglied, Rechnung, Zahlmethode, Beleg, Bankabgleich und Audit anzeigen.', status: entry.status, icon: Icons.fact_check_outlined)),
      AirmiusButton(label: 'Als bezahlt', icon: Icons.check_circle_outline, secondary: true, onPressed: entry.status == 'Bezahlt' ? null : () => openUiAction(context, title: 'Zahlung markieren', body: '${entry.title} als bezahlt markieren, Mitglied informieren, Rechnung aktualisieren und Audit-Eintrag erzeugen.', status: 'Bezahlt', icon: Icons.check_circle_outline)),
      if (showReminders) AirmiusButton(label: 'Mahnung', icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Push')))),
      if (showExports) AirmiusButton(label: 'Export', icon: Icons.file_download_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Export vorbereiten', body: 'PDF, CSV, DATEV oder SEPA-Datei fuer diesen Finanzposten vorbereiten.', status: 'Export', icon: Icons.file_download_outlined)),
    ]),
  ]));
}

class _FinanceMeta extends StatelessWidget {
  const _FinanceMeta({required this.entry});

  final _FinanceEntry entry;

  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    _MetaLine(label: 'Mitglied', value: entry.member),
    _MetaLine(label: 'Faellig', value: entry.due),
    _MetaLine(label: 'Rechnung', value: entry.invoice),
    _MetaLine(label: 'Abgleich', value: entry.reconciliation),
  ]));
}

class _MetaLine extends StatelessWidget {
  const _MetaLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [SizedBox(width: 96, child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))), Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)))]));
}

class _FinanceWorkflowPanel extends StatelessWidget {
  const _FinanceWorkflowPanel({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    const Eyebrow('Finanz-Workflow'),
    const SizedBox(height: 8),
    Text('Aktueller Filter: $tab. Spaeter verbindet Laravel diese UI mit Rechnungen, Zahlungen, SEPA-Mandaten, Bankabgleich, Mahnungen, Exporten und DATEV.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: 'Billing Ops', icon: Icons.receipt_long_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
      AirmiusButton(label: 'Mitglieder', icon: Icons.people_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
      AirmiusButton(label: 'Beitragsregeln', icon: Icons.payments_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()))),
      AirmiusButton(label: 'Monatsabschluss', icon: Icons.event_available_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Monatsabschluss vorbereiten', body: 'Offene Posten, bezahlte Rechnungen, Bankabgleich, SEPA, DATEV und Export fuer den Vereinsmonat zusammenstellen.', status: 'Abschluss', icon: Icons.event_available_outlined)),
    ]),
  ]));
}

class _FinanceSwitch extends StatelessWidget {
  const _FinanceSwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(value: value, onChanged: onChanged, activeColor: color, contentPadding: EdgeInsets.zero, secondary: Icon(icon, color: color), title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)));
}

class _FinanceEntry {
  const _FinanceEntry({required this.status, required this.title, required this.member, required this.body, required this.method, required this.amount, required this.due, required this.invoice, required this.reconciliation, required this.icon, required this.color});

  final String status;
  final String title;
  final String member;
  final String body;
  final String method;
  final String amount;
  final String due;
  final String invoice;
  final String reconciliation;
  final IconData icon;
  final Color color;
}

const _tabs = ['Alle', 'Offen', 'Bezahlt', 'Mahnung', 'SEPA', 'Export'];

const _entries = <_FinanceEntry>[
  _FinanceEntry(status: 'Offen', title: 'Jahresbeitrag 2026', member: 'ZBB Konto', body: 'Jaehrlicher Mitgliedsbeitrag per Ueberweisung, Verwendungszweck fehlt noch im Bankabgleich.', method: 'Ueberweisung', amount: '120 EUR', due: '15.06.2026', invoice: 'INV-2026-0042', reconciliation: 'Nicht gefunden', icon: Icons.account_balance_outlined, color: AirmiusColors.amber),
  _FinanceEntry(status: 'SEPA', title: 'SEPA Lauf Juni', member: 'Mina Becker', body: 'Jugendbeitrag mit Guardian Consent und gueltigem SEPA-Mandat.', method: 'SEPA', amount: '12 EUR', due: '01.06.2026', invoice: 'SEPA-2026-06', reconciliation: 'Mandat OK', icon: Icons.fact_check_outlined, color: AirmiusColors.blue),
  _FinanceEntry(status: 'Mahnung', title: 'Offener Monatsbeitrag', member: 'Jonas Weber', body: 'Monatsbeitrag ueberfaellig, erste Zahlungserinnerung vorbereitet.', method: 'Ueberweisung', amount: '25 EUR', due: '01.06.2026', invoice: 'INV-2026-0038', reconciliation: '7 Tage ueberfaellig', icon: Icons.notifications_active_outlined, color: AirmiusColors.red),
  _FinanceEntry(status: 'Bezahlt', title: 'Barzahlung Aufnahme', member: 'Ali Hassan', body: 'Aufnahmegebuehr wurde bar bezahlt und durch Admin bestaetigt.', method: 'Bar', amount: '15 EUR', due: 'Heute', invoice: 'REC-2026-0012', reconciliation: 'Admin bestaetigt', icon: Icons.payments_outlined, color: AirmiusColors.green),
  _FinanceEntry(status: 'Export', title: 'DATEV Monatsdaten', member: 'Verein ZBB', body: 'Monatsabschluss mit Rechnungen, Zahlungen, offenen Posten und Providerkosten.', method: 'DATEV', amount: 'Juni', due: '30.06.2026', invoice: 'EXPORT-2026-06', reconciliation: 'Bereit', icon: Icons.file_download_outlined, color: AirmiusColors.green),
];
