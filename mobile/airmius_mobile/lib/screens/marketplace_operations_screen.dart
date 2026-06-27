import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MarketplaceOperationsScreen extends StatefulWidget {
  const MarketplaceOperationsScreen({super.key, this.initialTab = 'Shop'});

  final String initialTab;

  @override
  State<MarketplaceOperationsScreen> createState() => _MarketplaceOperationsScreenState();
}

class _MarketplaceOperationsScreenState extends State<MarketplaceOperationsScreen> {
  String _tab = 'Shop';
  bool _guestCheckout = true;
  bool _bankTransfer = false;
  bool _returnWindow = true;

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final items = _tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Marketplace Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Marketplace Ops',
        subtitle: 'Shop, Produktdetail, Warenkorb, Checkout, Orders, Retouren und Anbieter',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Shop & Checkout'),
            const SizedBox(height: 8),
            const Text('Die Marketplace-Webflows werden als native App-Ablaufe abgebildet: Produkt entdecken, kaufen, bezahlen, Bestellung verfolgen, Retoure starten und Anbieter kontaktieren.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _guestCheckout, onChanged: (value) => setState(() => _guestCheckout = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Gast-Checkout erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Public Checkout mit Token-Rückseiten vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _bankTransfer, onChanged: (value) => setState(() => _bankTransfer = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Banktransfer', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Überweisung statt Providerzahlung anzeigen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _returnWindow, onChanged: (value) => setState(() => _returnWindow = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Retoure möglich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Rückgabegrund, Fotos und Supportstatus erfassen.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.blue.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          Row(children: const [Expanded(child: MetricCard(value: '36', label: 'Produkte')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Orders')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Cart'))]),
          const SizedBox(height: 16),
          for (final item in items) ...[_MarketplaceOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _MarketplaceOperationCard extends StatelessWidget {
  const _MarketplaceOperationCard({required this.item});
  final _MarketplaceOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: item.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)), const SizedBox(height: 5), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
      StatusPill(item.tab, color: item.color),
    ]),
    const SizedBox(height: 12),
    Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Text('${item.method} ${item.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontSize: 12, fontWeight: FontWeight.w900))),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, onPressed: () => openUiAction(context, title: item.title, body: '${item.body}\n\nEndpoint: ${item.method} ${item.endpoint}', status: item.tab, icon: item.icon)),
      AirmiusButton(label: 'Shop-Kontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Produkt, Anbieter, Variante, Cart, Order, Payment, Fulfillment, Retoure, Token und Audit anzeigen.', status: 'Kontext', icon: Icons.manage_search_outlined)),
    ]),
  ]));
}

class _MarketplaceOperation {
  const _MarketplaceOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
  final bool danger;
}

const _tabs = ['Shop', 'Cart', 'Checkout', 'Orders', 'Retouren', 'Anbieter', 'Alle'];

final _operations = <_MarketplaceOperation>[
  _MarketplaceOperation(tab: 'Shop', title: 'Public Marketplace laden', body: 'Gast-Shop mit Produkten, Kategorien, Top-Inhalten und Ads laden.', method: 'GET', endpoint: ApiContract.publicMarketplace, icon: Icons.storefront_outlined, action: 'Shop laden', color: AirmiusColors.blue),
  _MarketplaceOperation(tab: 'Shop', title: 'Mobile Produkte laden', body: 'Authentifizierte Produktliste mit Filtern, Preisen und Verfuegbarkeit laden.', method: 'GET', endpoint: ApiContract.commerceProducts, icon: Icons.inventory_2_outlined, action: 'Produkte', color: AirmiusColors.blue),
  _MarketplaceOperation(tab: 'Shop', title: 'Produktdetail laden', body: 'Produktdetail mit Varianten, Anbieter, Bestand und Bewertungen laden.', method: 'GET', endpoint: ApiContract.publicMarketplaceProduct(1), icon: Icons.shopping_bag_outlined, action: 'Detail', color: AirmiusColors.blue),
  _MarketplaceOperation(tab: 'Cart', title: 'In Warenkorb legen', body: 'Produkt, Variante, Menge und Preis in mobilen Warenkorb übernehmen.', method: 'POST', endpoint: ApiContract.marketplaceCart, icon: Icons.add_shopping_cart_outlined, action: 'Hinzufuegen', color: AirmiusColors.green),
  _MarketplaceOperation(tab: 'Cart', title: 'Warenkorb aktualisieren', body: 'Mengen, Varianten, Gutschein und Versandoptionen aktualisieren.', method: 'PATCH', endpoint: ApiContract.marketplaceCartItem(1), icon: Icons.shopping_cart_outlined, action: 'Aktualisieren', color: AirmiusColors.amber),
  _MarketplaceOperation(tab: 'Cart', title: 'Warenkorb leeren', body: 'Alle Cart-Positionen entfernen und Checkout abbrechen.', method: 'DELETE', endpoint: ApiContract.marketplaceCart, icon: Icons.remove_shopping_cart_outlined, action: 'Leeren', color: AirmiusColors.red, danger: true),
  _MarketplaceOperation(tab: 'Checkout', title: 'Produkt-Checkout starten', body: 'Public Produktcheckout mit Adresse, Zahlung und Token anlegen.', method: 'POST', endpoint: ApiContract.publicMarketplaceProductCheckout(1), icon: Icons.lock_outline, action: 'Checkout', color: AirmiusColors.green),
  _MarketplaceOperation(tab: 'Checkout', title: 'Checkout Success', body: 'Erfolgseite für Commerce-Order darstellen und Bestellung laden.', method: 'GET', endpoint: ApiContract.commerceCheckoutSuccess(1), icon: Icons.check_circle_outline, action: 'Success', color: AirmiusColors.green),
  _MarketplaceOperation(tab: 'Checkout', title: 'Checkout Cancel', body: 'Abbruchseite mit Retry, Warenkorb und Supportpfad darstellen.', method: 'GET', endpoint: ApiContract.commerceCheckoutCancel(1), icon: Icons.cancel_outlined, action: 'Cancel', color: AirmiusColors.red),
  _MarketplaceOperation(tab: 'Checkout', title: 'Banktransfer anzeigen', body: 'Überweisungsdaten, Verwendungszweck und Zahlungsfrist anzeigen.', method: 'GET', endpoint: ApiContract.commerceCheckoutBankTransfer(1), icon: Icons.account_balance_outlined, action: 'Banktransfer', color: AirmiusColors.amber),
  _MarketplaceOperation(tab: 'Orders', title: 'Bestellungen laden', body: 'Eigene Orders, Versandstatus, Rechnung und Problemstatus laden.', method: 'GET', endpoint: ApiContract.commerceOrders, icon: Icons.receipt_long_outlined, action: 'Orders', color: AirmiusColors.blue),
  _MarketplaceOperation(tab: 'Orders', title: 'Bestelldetail laden', body: 'Orderpositionen, Versand, Rechnung, Support und Retoure anzeigen.', method: 'GET', endpoint: ApiContract.commerceOrder(1), icon: Icons.manage_search_outlined, action: 'Detail', color: AirmiusColors.blue),
  _MarketplaceOperation(tab: 'Orders', title: 'Bestellproblem melden', body: 'Issue mit Nachricht, Fotos, Anbieterantwort und Status starten.', method: 'PUT', endpoint: ApiContract.commerceOrderIssue(1), icon: Icons.report_outlined, action: 'Problem', color: AirmiusColors.red, danger: true),
  _MarketplaceOperation(tab: 'Retouren', title: 'Gast-Retoure erstellen', body: 'Return-Request für Gastbestellung mit Token, Grund und Fotos einreichen.', method: 'POST', endpoint: ApiContract.publicGuestReturn(1, 'token'), icon: Icons.assignment_return_outlined, action: 'Retoure', color: AirmiusColors.amber),
  _MarketplaceOperation(tab: 'Retouren', title: 'Retoure anzeigen', body: 'Return-Status, Rücksendung, Refund und Anbieterkommentar laden.', method: 'GET', endpoint: ApiContract.marketplaceReturn(1), icon: Icons.undo_outlined, action: 'Status', color: AirmiusColors.amber),
  _MarketplaceOperation(tab: 'Anbieter', title: 'Providerprofil laden', body: 'Public Anbieterprofil mit Produkten, Kontakt, Standort und Bewertung laden.', method: 'GET', endpoint: ApiContract.publicMarketplaceProvider('club', 1), icon: Icons.store_mall_directory_outlined, action: 'Provider', color: AirmiusColors.green),
  _MarketplaceOperation(tab: 'Anbieter', title: 'Seller-Bewerbung senden', body: 'Anbieterbewerbung mit Nachweisen, Rollenwunsch und Kontakt einreichen.', method: 'POST', endpoint: ApiContract.marketplaceSellerApplication, icon: Icons.fact_check_outlined, action: 'Bewerben', color: AirmiusColors.green),
];
