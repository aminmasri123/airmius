import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_status_screen.dart';

class BillingOperationsScreen extends StatefulWidget {
  const BillingOperationsScreen({super.key});

  @override
  State<BillingOperationsScreen> createState() => _BillingOperationsScreenState();
}

class _BillingOperationsScreenState extends State<BillingOperationsScreen> {
  String _tab = 'Zahlungen';
  bool _notify = true;
  bool _datev = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Billing Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Billing Operations',
        subtitle: 'Zahlungen, Invoices, Banktransfer, Providerkosten, Downloads und Admin-Status',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Admin-Billing'),
                  const SizedBox(height: 8),
                  const Text('Mobile Verwaltungsoberflaeche für klassische Rechnungen, Subscription-Invoices, Banktransfer, Providerkosten und Zahlungsstatus.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Zahlungen', 'Invoices', 'Abos', 'Provider', 'Alle'])
                        ChoiceChip(
                          selected: _tab == tab,
                          label: Text(tab),
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '4', label: 'Invoices')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'DATEV', label: 'Export'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Buchhaltungsregeln'),
                  SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Benachrichtigung senden', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('User, Verein oder Anbieter nach Zahlungsstatus informieren.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _datev, onChanged: (value) => setState(() => _datev = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('DATEV/Audit vorbereiten', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Statuswechsel und Rechnungsdaten für Buchhaltung vormerken.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _BillingOperationCard(item: item),
              const SizedBox(height: 12),
            ],
            const SizedBox(height: 2),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(label: 'Checkout Erfolg testen', icon: Icons.check_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription Admin', status: 'Success', amount: '49,00 EUR')))),
                AirmiusButton(label: 'Banktransfer testen', icon: Icons.account_balance_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription Admin', status: 'Banktransfer', amount: '49,00 EUR')))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _BillingOperationCard extends StatelessWidget {
  const _BillingOperationCard({required this.item});

  final _BillingOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 10),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.tab), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
                    AirmiusButton(label: 'Audit anzeigen', icon: Icons.history_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Billing Audit', body: 'Audit, Statuswechsel, Bearbeiter, Beleg und Providerreferenz für ${item.title} anzeigen.', status: 'Audit', icon: Icons.history_outlined)),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _BillingOperation item) {
    void action() => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Billing-Aktion verändert Zahlstatus, Abo oder Rechnung. Später wird sie mit Audit protokolliert.', item.action, action);
      return;
    }
    action();
  }
}

class _BillingOperation {
  const _BillingOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _BillingOperation(tab: 'Zahlungen', title: 'Admin-Zahlung erfassen', body: 'Manuelle Zahlung mit Betrag, Methode, Referenz und Rechnung verbinden.', status: 'Payment', icon: Icons.payments_outlined, action: 'Zahlung speichern'),
  _BillingOperation(tab: 'Zahlungen', title: 'Banktransfer als bezahlt markieren', body: 'Subscription-Checkout oder Invoice nach Überweisung ausgleichen.', status: 'Mark paid', icon: Icons.account_balance_outlined, action: 'Als bezahlt markieren'),
  _BillingOperation(tab: 'Zahlungen', title: 'Zahlung löschen', body: 'Fehlerhafte Zahlung entfernen und Audit-Hinweis hinterlegen.', status: 'Delete', icon: Icons.delete_outline, action: 'Zahlung löschen', danger: true),
  _BillingOperation(tab: 'Invoices', title: 'Rechnung erstellen', body: 'Admin-Invoice mit Empfaenger, Positionen, Steuer, Faelligkeit und PDF vorbereiten.', status: 'Invoice', icon: Icons.receipt_long_outlined, action: 'Rechnung erstellen'),
  _BillingOperation(tab: 'Invoices', title: 'Rechnungsstatus aktualisieren', body: 'Offen, bezahlt, überfaellig, storniert oder archiviert setzen.', status: 'Status', icon: Icons.edit_note_outlined, action: 'Status setzen'),
  _BillingOperation(tab: 'Invoices', title: 'Rechnung downloaden', body: 'PDF für Subscription- oder Admin-Rechnung laden und teilen.', status: 'PDF', icon: Icons.picture_as_pdf_outlined, action: 'PDF laden'),
  _BillingOperation(tab: 'Invoices', title: 'Rechnung entfernen', body: 'Fehlerhafte Admin-Rechnung mit Beleggrund löschen.', status: 'Delete', icon: Icons.delete_forever_outlined, action: 'Rechnung löschen', danger: true),
  _BillingOperation(tab: 'Abos', title: 'Club-Abo zuweisen', body: 'Verein einem Plan zuordnen, Laufzeit und Zahlungsart setzen.', status: 'Club', icon: Icons.apartment_outlined, action: 'Club-Abo speichern'),
  _BillingOperation(tab: 'Abos', title: 'User-Abo zuweisen', body: 'User-Plan, Preis, Trial und Verlängerung verwalten.', status: 'User', icon: Icons.person_outline, action: 'User-Abo speichern'),
  _BillingOperation(tab: 'Abos', title: 'Abo erneuern', body: 'Club- oder User-Subscription verlaengern und Rechnung vorbereiten.', status: 'Renew', icon: Icons.autorenew_outlined, action: 'Abo erneuern'),
  _BillingOperation(tab: 'Abos', title: 'Abo kündigen', body: 'Kündigung vormerken, Zugang prüfen und Abschlussdatum setzen.', status: 'Cancel', icon: Icons.cancel_outlined, action: 'Abo kündigen', danger: true),
  _BillingOperation(tab: 'Provider', title: 'Providerkosten erfassen', body: 'Stripe, PayPal, Storage, Mail oder externe Kosten dokumentieren.', status: 'Costs', icon: Icons.account_balance_wallet_outlined, action: 'Kosten speichern'),
  _BillingOperation(tab: 'Provider', title: 'Provider-Referenz prüfen', body: 'Transaktions-ID, Checkout-ID, Webhook und Payment-Provider zuordnen.', status: 'Provider', icon: Icons.manage_search_outlined, action: 'Referenz prüfen'),
];
