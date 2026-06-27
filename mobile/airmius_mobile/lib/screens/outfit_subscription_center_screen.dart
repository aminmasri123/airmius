import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_status_screen.dart';
import 'ui_action_result_screen.dart';
import 'outfit_delivery_detail_screen.dart';
import 'outfit_operations_screen.dart';

class OutfitSubscriptionCenterScreen extends StatefulWidget {
  const OutfitSubscriptionCenterScreen({super.key});

  @override
  State<OutfitSubscriptionCenterScreen> createState() => _OutfitSubscriptionCenterScreenState();
}

class _OutfitSubscriptionCenterScreenState extends State<OutfitSubscriptionCenterScreen> {
  String _size = 'M';
  String _style = 'Sportlich';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Outfit-Abos', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Outfit-Abos',
        subtitle: 'Style-Profil, Plaene, Lieferungen, Pause und Probleme',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(
            gradient: true,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Eyebrow('Style-Profil'),
              const SizedBox(height: 8),
              const Text('Sportkleidung als Abo mit Groessen, Stil, Lieferungen und Support-Faellen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                value: _size,
                dropdownColor: AirmiusColors.cardSoft,
                decoration: const InputDecoration(labelText: 'Groesse'),
                items: const ['XS', 'S', 'M', 'L', 'XL'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                onChanged: (value) => setState(() => _size = value ?? _size),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: _style,
                dropdownColor: AirmiusColors.cardSoft,
                decoration: const InputDecoration(labelText: 'Stil'),
                items: const ['Sportlich', 'Schlicht', 'Vereinsfarben', 'Performance'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                onChanged: (value) => setState(() => _style = value ?? _style),
              ),
            ]),
          ),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '1', label: 'Aktiv')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Lieferungen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '0', label: 'Probleme'))]),
          const SizedBox(height: 14),
          const _OutfitCard(title: 'Performance Paket', body: 'Monatliches Outfit für Training und Verein', status: 'Aktiv', icon: Icons.checkroom_outlined),
          const SizedBox(height: 12),
          const _OutfitCard(title: 'Lieferung Juni', body: 'Versand vorbereitet - Adresse bestätigt', status: 'Versand', icon: Icons.local_shipping_outlined),
          const SizedBox(height: 12),
          const _OutfitCard(title: 'Lieferproblem melden', body: 'Groesse, Qualitaet, Versand oder Rückgabe melden', status: 'Support', icon: Icons.report_problem_outlined),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Abo-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Pausieren', icon: Icons.pause_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Outfit-Abo pausieren', body: 'Lieferpause, naechster Versand und Nutzerhinweis vorbereiten.', status: 'Pause', icon: Icons.pause_circle_outline)))),
              AirmiusButton(label: 'Fortsetzen', icon: Icons.play_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Outfit-Abo fortsetzen', body: 'Pausiertes Abo reaktivieren und naechste Lieferung vorbereiten.', status: 'Aktiv', icon: Icons.play_circle_outline)))),
              AirmiusButton(label: 'Checkout Erfolg', icon: Icons.check_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Outfit', status: 'Success', amount: '39,90 EUR')))),
              AirmiusButton(label: 'Checkout Abbruch', icon: Icons.cancel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Outfit', status: 'Cancel', amount: '39,90 EUR')))),
              AirmiusButton(label: 'Outfit Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OutfitOperationsScreen()))),
              AirmiusButton(label: 'Kündigen', icon: Icons.cancel_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Outfit-Abo kündigen', body: 'Kündigung, Frist, Warnung und Support-Hinweis vorbereiten.', status: 'Kündigung', icon: Icons.cancel_outlined)))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _OutfitCard extends StatelessWidget {
  const _OutfitCard({required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OutfitDeliveryDetailScreen(title: title, body: body, status: status, icon: icon))),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: AirmiusColors.blue),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)), const SizedBox(height: 8), StatusPill(status)])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}
