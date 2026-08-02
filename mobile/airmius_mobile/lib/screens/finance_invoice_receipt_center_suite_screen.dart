import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FinanceInvoiceReceiptCenterSuiteScreen extends StatefulWidget {
  const FinanceInvoiceReceiptCenterSuiteScreen({super.key});

  @override
  State<FinanceInvoiceReceiptCenterSuiteScreen> createState() =>
      _FinanceInvoiceReceiptCenterSuiteScreenState();
}

class _FinanceInvoiceReceiptCenterSuiteScreenState
    extends State<FinanceInvoiceReceiptCenterSuiteScreen> {
  String filter = 'Offen';
  bool showReceipts = true;
  bool allowRetry = true;
  bool showRefunds = true;
  bool notifyOnDue = true;

  @override
  Widget build(BuildContext context) {
    final invoices = [
      const _InvoiceRow(
        title: 'Jahresbeitrag ZBB',
        status: 'Offen',
        amount: '120 EUR',
        body:
            'Fällig am 01.07.2026. Zahlungsart: Überweisung. Beitragsordnung ist verknüpft.',
        color: AirmiusColors.blue,
      ),
      const _InvoiceRow(
        title: 'Jugendbeitrag U18',
        status: 'Bezahlt',
        amount: '60 EUR',
        body:
            'Quittung verfügbar. Guardian-Kontakt und SEPA-Status werden später per API geladen.',
        color: AirmiusColors.green,
      ),
      const _InvoiceRow(
        title: 'Korrektur Beitragsgruppe',
        status: 'Rückerstattung',
        amount: '20 EUR',
        body:
            'Rückerstattung wegen Beitragswechsel. Verein und Mitglied sehen Verlauf und Status.',
        color: AirmiusColors.amber,
      ),
    ];

    final filtered = invoices
        .where((invoice) => filter == 'Alle' || invoice.status == filter)
        .toList();

    return PageFrame(
      title: 'Rechnungen & Quittungen',
      subtitle: 'Beiträge, Zahlstatus und Rückerstattung',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('FINANCE CENTER'),
                const SizedBox(height: 8),
                const Text(
                  'Mitglieder und Vereine brauchen eine mobile Finanzübersicht: offene Beiträge, Rechnungen, Quittungen, Zahlungsstatus, Mahnhinweise und Rückerstattungen.',
                  style: TextStyle(
                    color: AirmiusColors.text,
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Belege'),
                    Metric(value: '180', label: 'EUR'),
                    Metric(value: 'PDF', label: 'Quittung'),
                    Metric(value: 'API', label: 'Finance'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('FILTER'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Alle', label: Text('Alle')),
                    ButtonSegment(value: 'Offen', label: Text('Offen')),
                    ButtonSegment(value: 'Bezahlt', label: Text('Bezahlt')),
                    ButtonSegment(
                      value: 'Rückerstattung',
                      label: Text('Refund'),
                    ),
                  ],
                  selected: {filter},
                  onSelectionChanged: (value) =>
                      setState(() => filter = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('OPTIONEN'),
                const SizedBox(height: 8),
                _FinanceSwitch(
                  title: 'Quittungen anzeigen',
                  value: showReceipts,
                  color: AirmiusColors.green,
                  onChanged: (value) => setState(() => showReceipts = value),
                ),
                _FinanceSwitch(
                  title: 'Zahlung erneut versuchen',
                  value: allowRetry,
                  color: AirmiusColors.blue,
                  onChanged: (value) => setState(() => allowRetry = value),
                ),
                _FinanceSwitch(
                  title: 'Rückerstattungen anzeigen',
                  value: showRefunds,
                  color: AirmiusColors.amber,
                  onChanged: (value) => setState(() => showRefunds = value),
                ),
                _FinanceSwitch(
                  title: 'Fälligkeit erinnern',
                  value: notifyOnDue,
                  color: AirmiusColors.pink,
                  onChanged: (value) => setState(() => notifyOnDue = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (filtered.isEmpty)
            const EmptyPanel('Keine Belege für diesen Filter.')
          else
            for (final invoice in filtered) ...[
              _InvoiceCard(invoice: invoice),
              const SizedBox(height: 12),
            ],
        ],
      ),
    );
  }
}

class _InvoiceRow {
  const _InvoiceRow({
    required this.title,
    required this.status,
    required this.amount,
    required this.body,
    required this.color,
  });

  final String title;
  final String status;
  final String amount;
  final String body;
  final Color color;
}

class _FinanceSwitch extends StatelessWidget {
  const _FinanceSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(
        title,
        style: const TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w900,
        ),
      ),
      value: value,
      activeThumbColor: color,
      onChanged: onChanged,
    );
  }
}

class _InvoiceCard extends StatelessWidget {
  const _InvoiceCard({required this.invoice});

  final _InvoiceRow invoice;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(
                icon: Icons.receipt_long_outlined,
                color: invoice.color,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            invoice.title,
                            style: const TextStyle(
                              color: AirmiusColors.text,
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        StatusPill(invoice.status, color: invoice.color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      invoice.amount,
                      style: const TextStyle(
                        color: AirmiusColors.blue,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      invoice.body,
                      style: const TextStyle(
                        color: AirmiusColors.muted,
                        height: 1.42,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Quittung',
                icon: Icons.picture_as_pdf_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Quittung anzeigen',
                  body:
                      'Diese UI bereitet PDF-Quittungen, Rechnungsdetails und Zahlungsstatus für die spätere Laravel-Finance-API vor.',
                  status: 'UI vorbereitet',
                  icon: Icons.picture_as_pdf_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Zahlen',
                icon: Icons.payments_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Zahlung starten',
                  body:
                      'Zahlungsart, offener Betrag, SEPA, Überweisung, Barzahlung und Retry-Status werden später per API gesteuert.',
                  status: 'UI vorbereitet',
                  icon: Icons.payments_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Verlauf',
                icon: Icons.timeline_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Finanzverlauf',
                  body:
                      'Der Verlauf zeigt später Rechnungen, Zahlungen, Mahnungen, Rückerstattungen und Vereinsentscheidungen.',
                  status: 'UI vorbereitet',
                  icon: Icons.timeline_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
