import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class CommerceDetailScreen extends StatefulWidget {
  const CommerceDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<CommerceDetailScreen> createState() => _CommerceDetailScreenState();
}

class _CommerceDetailScreenState extends State<CommerceDetailScreen> {
  String _fulfillment = 'Versand vorbereiten';
  String _couponType = 'Prozent';
  bool _qualityApproved = true;
  bool _inventoryWarning = true;
  bool _payoutReady = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Commerce', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Produkt, Bestellung, Coupon, Inventar, Fulfillment und Payout',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Produkt / Order'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Titel', hint: 'Teamshirt, Kurs, Gutschein oder Order', icon: Icons.inventory_2_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Beschreibung', hint: 'Produktdetails, Lieferhinweis oder interne Notiz', icon: Icons.notes_outlined, maxLines: 3),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '36', label: 'Bestand')), SizedBox(width: 10), Expanded(child: MetricCard(value: '12', label: 'Orders')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Payouts'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Fulfillment & Status'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _fulfillment,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Order-Status'),
              items: const ['Bezahlt', 'Versand vorbereiten', 'Versendet', 'Rückgabe', 'Storniert'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _fulfillment = value ?? _fulfillment),
            ),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Tracking / Referenz', hint: 'Sendungsnummer, Abholcode oder Provider-ID', icon: Icons.local_shipping_outlined),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Produktqualitaet & Inventar'),
            const SizedBox(height: 8),
            SwitchListTile(value: _qualityApproved, onChanged: (value) => setState(() => _qualityApproved = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Qualitaetsfreigabe erteilt', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Produkt darf im Marketplace sichtbar sein.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _inventoryWarning, onChanged: (value) => setState(() => _inventoryWarning = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Inventarwarnung aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Warnung bei niedrigem Bestand oder fehlenden Varianten.', style: TextStyle(color: AirmiusColors.muted))),
            const Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('S'), StatusPill('M'), StatusPill('L'), StatusPill('XL')]),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Coupon'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _couponType,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Coupon-Typ'),
              items: const ['Prozent', 'Fixbetrag', 'Kostenloser Versand', 'Vereinsrabatt'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _couponType = value ?? _couponType),
            ),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Coupon-Code', hint: 'ZBB10', icon: Icons.local_offer_outlined),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Anbieterprofil & Standorte'),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Anbietername', hint: 'Airmius Shop oder Vereinsanbieter', icon: Icons.storefront_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Kontakt & Standort', hint: 'E-Mail, Stadt, Abholung oder Versandlager', icon: Icons.location_on_outlined),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Ads & Marketplace Visuals'),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Kampagnenziel', hint: 'Hero-Banner, Sale-Kachel, Marketplace-Karte', icon: Icons.campaign_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Creative / Bild-URL', hint: 'Upload oder URL später über API', icon: Icons.image_outlined),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Rückgabe & Problemfall'),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Rückgabegrund', hint: 'Groesse, Defekt, Falschlieferung oder sonstiger Grund', icon: Icons.assignment_return_outlined),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Anbieterantwort', hint: 'Antwort, Erstattung, Ersatzlieferung oder Ablehnung', icon: Icons.reply_outlined, maxLines: 3),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: const [StatusPill('Return'), StatusPill('Refund offen'), StatusPill('Support')]),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Steuern & Versand'),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Steuersatz', hint: '19%, 7% oder steuerfrei', icon: Icons.percent_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Versandregel', hint: 'Standard, Abholung, kostenlos ab Warenwert', icon: Icons.local_shipping_outlined),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Payout'),
            const SizedBox(height: 8),
            SwitchListTile(value: _payoutReady, onChanged: (value) => setState(() => _payoutReady = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Auszahlung freigeben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Payout wird mit Rechnung, Provider und Verein verknuepft.', style: TextStyle(color: AirmiusColors.muted))),
            const Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Provider'), StatusPill('Rechnung offen'), StatusPill('DATEV bereit')]),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Commerce speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Commerce speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
        ]),
      ),
    );
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}
