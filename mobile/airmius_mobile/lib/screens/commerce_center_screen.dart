import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ad_campaign_screen.dart';
import 'commerce_detail_screen.dart';
import 'commerce_operations_screen.dart';

class CommerceCenterScreen extends StatefulWidget {
  const CommerceCenterScreen({super.key});

  @override
  State<CommerceCenterScreen> createState() => _CommerceCenterScreenState();
}

class _CommerceCenterScreenState extends State<CommerceCenterScreen> {
  String _tab = 'Orders';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Commerce', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Commerce',
        subtitle: 'Produkte, Bestellungen, Coupons, Inventar, Payouts und Qualitaetsfreigabe',
        trailing: const StatusPill('Provider'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Shop-Verwaltung'),
            const SizedBox(height: 8),
            const Text('Marketplace von der Anbieter-Seite verwalten.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Native UI für Produktqualitaet, Bestellungen, Payouts, Coupons, Varianten und Lagerbestand.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Orders', 'Produkte', 'Inventar', 'Payouts', 'Coupons', 'Anbieter', 'Ads', 'Retouren'].map((item) => ChoiceChip(
              selected: _tab == item,
              label: Text(item),
              onSelected: (_) => setState(() => _tab = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _tab == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _tab == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '12', label: 'Orders')), SizedBox(width: 10), Expanded(child: MetricCard(value: '36', label: 'Produkte')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Payouts'))]),
          const SizedBox(height: 14),
          _CommerceLine(icon: Icons.receipt_long_outlined, title: 'Bestellung #A-1024', body: 'Teamshirt, bezahlt, Versand wird vorbereitet.', status: 'Bezahlt', color: AirmiusColors.green, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Bestellung #A-1024', status: 'Bezahlt')))),
          const SizedBox(height: 12),
          _CommerceLine(icon: Icons.inventory_2_outlined, title: 'Airmius Teamshirt', body: 'Varianten, Bestand, Preis und Fulfillment prüfen.', status: 'Qualitaet ok', color: AirmiusColors.blue, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Airmius Teamshirt', status: 'Qualitaet ok')))),
          const SizedBox(height: 12),
          _CommerceLine(icon: Icons.payments_outlined, title: 'Payout Juni', body: 'Auszahlung für Vereinsanbieter vorbereiten und dokumentieren.', status: 'Offen', color: AirmiusColors.amber, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Payout Juni', status: 'Offen')))),
          const SizedBox(height: 12),
          _CommerceLine(icon: Icons.assignment_return_outlined, title: 'Rückgabe #R-88', body: 'Retourenentscheidung, Erstattung und Anbieterantwort prüfen.', status: 'Review', color: AirmiusColors.amber, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Rückgabe #R-88', status: 'Review')))),
          const SizedBox(height: 12),
          _CommerceLine(icon: Icons.campaign_outlined, title: 'Marketplace Kampagne', body: 'Hero-Banner, Sale-Kachel, Klicks und Conversion vorbereiten.', status: 'Ads', color: AirmiusColors.blue, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdCampaignScreen(title: 'Marketplace Kampagne', status: 'Ads')))),
          const SizedBox(height: 12),
          _CommerceLine(icon: Icons.storefront_outlined, title: 'Anbieterprofil', body: 'Seller-Antrag, Profil, Standort, Steuer- und Versanddaten.', status: 'Provider', color: AirmiusColors.green, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Anbieterprofil', status: 'Provider')))),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Produkt anlegen', icon: Icons.add_box_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Produkt anlegen', status: 'Entwurf')))),
              AirmiusButton(label: 'Coupon erstellen', icon: Icons.local_offer_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Coupon erstellen', status: 'Coupon')))),
              AirmiusButton(label: 'Payout prüfen', icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceDetailScreen(title: 'Payout prüfen', status: 'Offen')))),
              AirmiusButton(label: 'Kampagne erstellen', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdCampaignScreen(title: 'Kampagne erstellen', status: 'Planung')))),
              AirmiusButton(label: 'Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceOperationsScreen()))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _CommerceLine extends StatelessWidget {
  const _CommerceLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 28),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      const Icon(Icons.chevron_right, color: AirmiusColors.muted),
    ]));
  }
}
