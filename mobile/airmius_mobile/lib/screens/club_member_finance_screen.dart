import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'finance_record_detail_screen.dart';

class ClubMemberFinanceScreen extends StatefulWidget {
  const ClubMemberFinanceScreen({super.key});

  @override
  State<ClubMemberFinanceScreen> createState() => _ClubMemberFinanceScreenState();
}

class _ClubMemberFinanceScreenState extends State<ClubMemberFinanceScreen> {
  String _tab = 'Mitglieder';
  late Future<List<AirmiusInvoice>> _invoicesFuture;

  @override
  void initState() {
    super.initState();
    _invoicesFuture = _loadInvoices();
  }

  Future<List<AirmiusInvoice>> _loadInvoices() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.billing.invoices();
    return page.items;
  }

  void _reload() {
    setState(() => _invoicesFuture = _loadInvoices());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Mitglieder & Beitraege', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Mitglieder & Beitraege',
        subtitle: 'Mitglieder, externe Kontakte, Rechnungen, Zahlungen, SEPA und DATEV',
        child: FutureBuilder<List<AirmiusInvoice>>(
          future: _invoicesFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const AirmiusPanel(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('Finanzdaten werden geladen...', style: TextStyle(color: AirmiusColors.muted)),
                ),
              );
            }
            if (snapshot.hasError) {
              return AirmiusPanel(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  const Eyebrow('API Fehler'),
                  const SizedBox(height: 8),
                  Text('${snapshot.error}', style: const TextStyle(color: AirmiusColors.muted)),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Erneut laden', icon: Icons.refresh_outlined, onPressed: _reload),
                ]),
              );
            }

            final invoices = snapshot.data ?? const <AirmiusInvoice>[];
            final openInvoices = invoices.where((invoice) => invoice.status.toLowerCase() != 'paid').length;
            final paidInvoices = invoices.where((invoice) => invoice.status.toLowerCase() == 'paid').length;

            return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Eyebrow('Vereinsverwaltung'),
                const SizedBox(height: 8),
                const Text('Nach Annahme einer Anfrage verwaltet der Verein Mitgliedsnummer, Beitraege, Rechnungen und Zahlungsausgleich.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 14),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final tab in const ['Mitglieder', 'Rechnungen', 'Zahlungen', 'Exporte'])
                    ChoiceChip(
                      selected: _tab == tab,
                      label: Text(tab),
                      onSelected: (_) => setState(() => _tab = tab),
                      selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                      backgroundColor: AirmiusColors.cardSoft,
                      side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                      labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                    ),
                ]),
              ])),
              const SizedBox(height: 14),
              Row(children: [
                const Expanded(child: MetricCard(value: '24', label: 'Mitglieder')),
                const SizedBox(width: 10),
                Expanded(child: MetricCard(value: '$openInvoices', label: 'Offen')),
                const SizedBox(width: 10),
                Expanded(child: MetricCard(value: '$paidInvoices', label: 'Bezahlt')),
              ]),
              const SizedBox(height: 14),
              _content(invoices),
            ]);
          },
        ),
      ),
    );
  }

  Widget _content(List<AirmiusInvoice> invoices) {
    return switch (_tab) {
      'Mitglieder' => _MembersPanel(),
      'Rechnungen' => _InvoicesPanel(invoices: invoices),
      'Zahlungen' => _PaymentsPanel(invoices: invoices),
      'Exporte' => const _ExportsPanel(),
      _ => _MembersPanel(),
    };
  }
}

class _MembersPanel extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return _Stack(children: [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Mitgliederliste'),
        const SizedBox(height: 10),
        for (final member in _members) ...[_FinanceLine(icon: Icons.person_outline, title: member.name, body: member.body, trailing: member.status), const SizedBox(height: 10)],
      ])),
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Import & Einladung'),
        const SizedBox(height: 10),
        const Text('E-Mail-Mitglieder importieren, externe Kontakte einladen und Mitgliedsnummern generieren.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          AirmiusButton(label: 'CSV importieren', icon: Icons.upload_file_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'CSV importieren', body: 'Mitgliederimport, externe Kontakte und Mitgliedsnummern.', trailing: 'Import', icon: Icons.upload_file_outlined)))),
          AirmiusButton(label: 'E-Mail-Mitglied', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'E-Mail-Mitglied', body: 'Externes Mitglied per E-Mail einladen.', trailing: 'Einladung', icon: Icons.mark_email_read_outlined)))),
          AirmiusButton(label: 'Mitgliedsnummer', icon: Icons.numbers_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'Mitgliedsnummer', body: 'Mitgliedsnummern generieren und Regeln pruefen.', trailing: 'Nummer', icon: Icons.numbers_outlined)))),
        ]),
      ])),
    ]);
  }
}

class _InvoicesPanel extends StatelessWidget {
  const _InvoicesPanel({required this.invoices});

  final List<AirmiusInvoice> invoices;

  @override
  Widget build(BuildContext context) {
    return _Stack(children: [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Mitgliedsrechnungen'),
        const SizedBox(height: 10),
        for (final invoice in invoices) ...[
          _FinanceLine(icon: Icons.receipt_long_outlined, title: 'Rechnung ${invoice.number}', body: '${_money(invoice)} - ${_statusLabel(invoice.status)}', trailing: _statusLabel(invoice.status)),
          const SizedBox(height: 10),
        ],
        if (invoices.isEmpty) const Text('Keine Rechnungen vorhanden.', style: TextStyle(color: AirmiusColors.muted)),
      ])),
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Aktionen'),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          AirmiusButton(label: 'Rechnung erstellen', icon: Icons.add_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'Rechnung erstellen', body: 'Neue Mitgliedsrechnung erzeugen.', trailing: 'Entwurf', icon: Icons.add_circle_outline)))),
          AirmiusButton(label: 'Mahnung senden', icon: Icons.notification_important_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'Mahnung senden', body: 'Offene Rechnung erinnern und Frist setzen.', trailing: 'Mahnung', icon: Icons.notification_important_outlined)))),
        ]),
      ])),
    ]);
  }
}

class _PaymentsPanel extends StatelessWidget {
  const _PaymentsPanel({required this.invoices});

  final List<AirmiusInvoice> invoices;

  @override
  Widget build(BuildContext context) {
    final paid = invoices.where((invoice) => invoice.status.toLowerCase() == 'paid').toList();
    final open = invoices.where((invoice) => invoice.status.toLowerCase() != 'paid').toList();
    return _Stack(children: [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Zahlungsstatus'),
        const SizedBox(height: 10),
        for (final invoice in open) ...[
          _FinanceLine(icon: Icons.account_balance_outlined, title: 'Offen ${_money(invoice)}', body: 'Rechnung ${invoice.number} wartet auf Zahlung oder Zuordnung', trailing: 'Offen'),
          const SizedBox(height: 10),
        ],
        for (final invoice in paid) ...[
          _FinanceLine(icon: Icons.payments_outlined, title: 'Bezahlt ${_money(invoice)}', body: 'Rechnung ${invoice.number} wurde ausgeglichen', trailing: 'Bezahlt'),
          const SizedBox(height: 10),
        ],
      ])),
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Zahlungsausgleich'),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          AirmiusButton(label: 'Import Bankdatei', icon: Icons.upload_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'Import Bankdatei', body: 'Banktransaktionen importieren und abgleichen.', trailing: 'Import', icon: Icons.upload_outlined)))),
          AirmiusButton(label: 'Transaktion bestaetigen', icon: Icons.check_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'Transaktion bestaetigen', body: 'Zahlung einem Mitglied oder Rechnung zuordnen.', trailing: 'Match', icon: Icons.check_circle_outline)))),
        ]),
      ])),
    ]);
  }
}

class _ExportsPanel extends StatelessWidget {
  const _ExportsPanel();

  @override
  Widget build(BuildContext context) {
    return _Stack(children: [
      const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('SEPA & DATEV'),
        SizedBox(height: 10),
        _FinanceLine(icon: Icons.sync_alt_outlined, title: 'SEPA-Lastschrift Export', body: 'Faellige Mitgliedsbeitraege als SEPA-Datei vorbereiten', trailing: 'SEPA'),
        SizedBox(height: 10),
        _FinanceLine(icon: Icons.dataset_outlined, title: 'DATEV Export', body: 'Rechnungen und Zahlungen fuer Buchhaltung exportieren', trailing: 'DATEV'),
      ])),
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Export-Aktionen'),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          AirmiusButton(label: 'SEPA exportieren', icon: Icons.file_download_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'SEPA exportieren', body: 'Lastschriftdatei vorbereiten und pruefen.', trailing: 'SEPA', icon: Icons.file_download_outlined)))),
          AirmiusButton(label: 'DATEV exportieren', icon: Icons.file_download_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: 'DATEV exportieren', body: 'Buchhaltungsexport vorbereiten.', trailing: 'DATEV', icon: Icons.file_download_outlined)))),
        ]),
      ])),
    ]);
  }
}

class _FinanceLine extends StatelessWidget {
  const _FinanceLine({required this.icon, required this.title, required this.body, required this.trailing});

  final IconData icon;
  final String title;
  final String body;
  final String trailing;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRecordDetailScreen(title: title, body: body, trailing: trailing, icon: icon))),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
          StatusPill(trailing),
        ]),
      ),
    );
  }
}

class _Stack extends StatelessWidget {
  const _Stack({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      for (var i = 0; i < children.length; i++) ...[children[i], if (i < children.length - 1) const SizedBox(height: 12)],
    ]);
  }
}

class _Member {
  const _Member({required this.name, required this.body, required this.status});

  final String name;
  final String body;
  final String status;
}

String _money(AirmiusInvoice invoice) {
  final euros = invoice.amountCents / 100;
  return '${euros.toStringAsFixed(2).replaceAll('.', ',')} ${invoice.currency}';
}

String _statusLabel(String status) {
  final normalized = status.toLowerCase();
  if (normalized == 'paid') return 'Bezahlt';
  if (normalized == 'overdue') return 'Mahnung';
  if (normalized == 'draft') return 'Entwurf';
  return 'Offen';
}

const _members = [
  _Member(name: 'ZBB Konto', body: 'Mitglied #0001 - Monatsbeitrag - Anfrage angenommen', status: 'Aktiv'),
  _Member(name: 'Externes Mitglied', body: 'Per E-Mail importiert - Einladung offen', status: 'Einladung'),
  _Member(name: 'Junior Mitglied', body: 'Guardian Consent erforderlich', status: 'Pruefen'),
];
