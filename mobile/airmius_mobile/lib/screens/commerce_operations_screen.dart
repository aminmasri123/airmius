import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class CommerceOperationsScreen extends StatefulWidget {
  const CommerceOperationsScreen({super.key});

  @override
  State<CommerceOperationsScreen> createState() => _CommerceOperationsScreenState();
}

class _CommerceOperationsScreenState extends State<CommerceOperationsScreen> {
  String _area = 'Orders';
  bool _notifySeller = true;
  bool _audit = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _area == 'Alle' || item.area == _area).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Commerce Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Commerce Operations',
        subtitle: 'Coupons, Addons, Inventar, Versand, Retouren, Payouts, Rechnungen und Website-Anfragen',
        trailing: StatusPill(_area),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Admin-Commerce aus der Web-App'),
                  const SizedBox(height: 8),
                  const Text('Diese Ansicht sammelt die Spezialfunktionen, die im Web unter Admin-Commerce laufen: Produktstatus, Lagerbestand, Coupons, Addons, Versandregeln, Retouren, Website-Anfragen, Payouts und Dokumente.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final area in const ['Alle', 'Orders', 'Produkte', 'Coupons', 'Versand', 'Retouren', 'Payouts', 'Website'])
                        ChoiceChip(
                          selected: _area == area,
                          label: Text(area),
                          onSelected: (_) => setState(() => _area = area),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _area == area ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _area == area ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '12', label: 'Orders')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Reviews')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Payouts'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Ausfuehrungsregeln'),
                  const SizedBox(height: 8),
                  SwitchListTile(value: _notifySeller, onChanged: (value) => setState(() => _notifySeller = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Anbieter informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Statuswechsel und Entscheidungen erzeugen später Benachrichtigungen.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _audit, onChanged: (value) => setState(() => _audit = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Audit verpflichtend', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Admin-Commerce-Aktionen werden nachvollziehbar protokolliert.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _OperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _OperationCard extends StatelessWidget {
  const _OperationCard({required this.item});

  final _CommerceOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
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
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.area), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.primaryLabel, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Details vormerken', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} prüfen', body: '${item.body} Details, Rechte, Benachrichtigung und Audit später über Laravel synchronisieren.', status: item.status, icon: Icons.fact_check_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _CommerceOperation item) {
    void action() => openUiAction(context, title: item.primaryLabel, body: '${item.primaryLabel} für ${item.title}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.primaryLabel}?', 'Diese Commerce-Aktion kann Zahlungen, Rückgaben oder Sichtbarkeit beeinflussen. Sie wird später mit Audit gespeichert.', item.primaryLabel, action);
      return;
    }
    action();
  }
}

class _CommerceOperation {
  const _CommerceOperation({required this.area, required this.title, required this.body, required this.status, required this.icon, required this.primaryLabel, this.danger = false});

  final String area;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String primaryLabel;
  final bool danger;
}

const _items = [
  _CommerceOperation(area: 'Orders', title: 'Order #A-1024 als bezahlt markieren', body: 'Banktransfer oder Admin-Zahlung bestätigen und Rechnung freischalten.', status: 'Mark paid', icon: Icons.payments_outlined, primaryLabel: 'Bezahlt markieren'),
  _CommerceOperation(area: 'Orders', title: 'Order-Dokumente', body: 'Rechnung, Gutschrift und Bestelldokumente für Download vorbereiten.', status: 'Docs', icon: Icons.picture_as_pdf_outlined, primaryLabel: 'Dokumente laden'),
  _CommerceOperation(area: 'Orders', title: 'Problemfall beantworten', body: 'Order-Issue prüfen, Anbieterantwort speichern und User benachrichtigen.', status: 'Issue', icon: Icons.support_agent_outlined, primaryLabel: 'Antwort senden'),
  _CommerceOperation(area: 'Produkte', title: 'Produktstatus setzen', body: 'Produkt sichtbar, gesperrt, Entwurf oder Review markieren.', status: 'Status', icon: Icons.inventory_2_outlined, primaryLabel: 'Status setzen'),
  _CommerceOperation(area: 'Produkte', title: 'Bestand anpassen', body: 'Stock-Korrektur für Varianten mit Audit und Inventarwarnung.', status: 'Stock', icon: Icons.warehouse_outlined, primaryLabel: 'Bestand buchen'),
  _CommerceOperation(area: 'Produkte', title: 'Marketplace Visuals', body: 'Hero-Banner, Kategorie-Kacheln und Public-Shop-Creatives aktualisieren.', status: 'Visuals', icon: Icons.image_outlined, primaryLabel: 'Visuals speichern'),
  _CommerceOperation(area: 'Coupons', title: 'Coupon erstellen', body: 'Rabattcode, Gültigkeit, Produktauswahl und Nutzungsgrenzen speichern.', status: 'Coupon', icon: Icons.local_offer_outlined, primaryLabel: 'Coupon speichern'),
  _CommerceOperation(area: 'Coupons', title: 'Addon erstellen', body: 'Zusatzprodukt, Laufzeit, Preis und Sichtbarkeit für Commerce-Abos definieren.', status: 'Addon', icon: Icons.extension_outlined, primaryLabel: 'Addon speichern'),
  _CommerceOperation(area: 'Versand', title: 'Versandregel speichern', body: 'Standardversand, Abholung, Kostenlos-ab-Warenwert und Lieferland konfigurieren.', status: 'Shipping', icon: Icons.local_shipping_outlined, primaryLabel: 'Versand speichern'),
  _CommerceOperation(area: 'Versand', title: 'Steuersatz speichern', body: 'MwSt.-Satz, Steuerklasse und Land für Produktabrechnung verwalten.', status: 'Tax', icon: Icons.percent_outlined, primaryLabel: 'Steuer speichern'),
  _CommerceOperation(area: 'Retouren', title: 'Retoure entscheiden', body: 'Rückgabe genehmigen, ablehnen, Ersatz oder Erstattung vorbereiten.', status: 'Return', icon: Icons.assignment_return_outlined, primaryLabel: 'Retoure entscheiden', danger: true),
  _CommerceOperation(area: 'Retouren', title: 'Refund starten', body: 'Rückerstattung mit Betrag, Grund, Provider und Gutschrift vormerken.', status: 'Refund', icon: Icons.currency_exchange_outlined, primaryLabel: 'Refund starten', danger: true),
  _CommerceOperation(area: 'Payouts', title: 'Payout erstellen', body: 'Auszahlungsbetrag für Anbieter berechnen und Zahlungsprofil prüfen.', status: 'Payout', icon: Icons.account_balance_wallet_outlined, primaryLabel: 'Payout erstellen'),
  _CommerceOperation(area: 'Payouts', title: 'Payout als bezahlt markieren', body: 'Auszahlung abschließen, Beleg verknuepfen und Anbieter informieren.', status: 'Paid', icon: Icons.task_alt_outlined, primaryLabel: 'Bezahlt setzen'),
  _CommerceOperation(area: 'Payouts', title: 'Payout-Profil aktualisieren', body: 'IBAN, Rechnungsdaten, Anbieteradresse und Steuerdaten verwalten.', status: 'Profile', icon: Icons.account_balance_outlined, primaryLabel: 'Profil speichern'),
  _CommerceOperation(area: 'Website', title: 'Website-Anfrage bearbeiten', body: 'Public-Werbeagentur-Anfrage klassifizieren, Status setzen und Kontakt aufnehmen.', status: 'Lead', icon: Icons.web_outlined, primaryLabel: 'Anfrage aktualisieren'),
  _CommerceOperation(area: 'Website', title: 'Marketplace Provisionen', body: 'Kommissionen, Anbieteranteile und Plattformgebuehren konfigurieren.', status: 'Commission', icon: Icons.tune_outlined, primaryLabel: 'Provision speichern'),
  _CommerceOperation(area: 'Website', title: 'Seller Application', body: 'Verkaeuferbewerbung prüfen, freigeben, ablehnen oder Nachweise anfordern.', status: 'Seller', icon: Icons.fact_check_outlined, primaryLabel: 'Seller prüfen'),
];
