import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'commerce_operations_screen.dart';
import 'guest_marketplace_buyer_screen.dart';
import 'support_helpdesk_screen.dart';

class AdminCommerceCenterScreen extends StatefulWidget {
  const AdminCommerceCenterScreen({super.key});

  @override
  State<AdminCommerceCenterScreen> createState() => _AdminCommerceCenterScreenState();
}

class _AdminCommerceCenterScreenState extends State<AdminCommerceCenterScreen> {
  String _section = 'Bestellungen';
  bool _showOrders = true;
  bool _showProducts = true;
  bool _showProviders = true;
  bool _showBankTransfers = true;
  bool _showRefunds = true;

  final List<_CommerceAdminItem> _items = const [
    _CommerceAdminItem(title: 'Offene Bestellung', area: 'Bestellungen', body: 'Marketplace-Bestellung mit Zahlung, Rechnung, Banktransfer und Supportstatus.', status: 'Offen', meta: '129 EUR', icon: Icons.receipt_long_outlined, color: AirmiusColors.blue),
    _CommerceAdminItem(title: 'Produkt pruefen', area: 'Produkte', body: 'Produktdaten, Preis, Sichtbarkeit, Anbieter, Medien und Freigabe pruefen.', status: 'Review', meta: 'Provider', icon: Icons.inventory_2_outlined, color: AirmiusColors.green),
    _CommerceAdminItem(title: 'Provider Anfrage', area: 'Provider', body: 'Anbieterprofil, Verifizierung, Produkte, Auszahlung und Kontaktfreigabe.', status: 'Neu', meta: 'Partner', icon: Icons.storefront_outlined, color: AirmiusColors.amber),
    _CommerceAdminItem(title: 'Banktransfer zuordnen', area: 'Banktransfer', body: 'Ueberweisung, Referenz, Betrag, Rechnung und manuelle Zuordnung.', status: 'Pruefen', meta: '89 EUR', icon: Icons.account_balance_outlined, color: AirmiusColors.blueDeep),
    _CommerceAdminItem(title: 'Refund Fall', area: 'Refunds', body: 'Rueckerstattung, Storno, Supportticket, Zahlungsstatus und Auditnotiz.', status: 'Sensibel', meta: 'Refund', icon: Icons.undo_outlined, color: AirmiusColors.red),
  ];

  List<_CommerceAdminItem> get _visibleItems => _items.where((item) => _section == 'Alle' || item.area == _section).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Admin Commerce', subtitle: 'Bestellungen, Produkte, Provider, Banktransfer, Refunds, Freigaben und Marketplace-Betrieb.'),
                        const SizedBox(height: 16),
                        _CommerceHero(onExport: () => _toast('Commerce-Export vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Bereich', value: _section, values: const ['Alle', 'Bestellungen', 'Produkte', 'Provider', 'Banktransfer', 'Refunds'], onChanged: (value) => setState(() => _section = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Commerce-Filter',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Bestellungen anzeigen', subtitle: 'Orders, Status, Zahlung, Rechnung und Support.', value: _showOrders, onChanged: (value) => setState(() => _showOrders = value)),
                              _SwitchRow(title: 'Produkte anzeigen', subtitle: 'Produktfreigabe, Preis, Medien und Sichtbarkeit.', value: _showProducts, onChanged: (value) => setState(() => _showProducts = value)),
                              _SwitchRow(title: 'Provider anzeigen', subtitle: 'Anbieter, Verifizierung, Auszahlung und Kontakt.', value: _showProviders, onChanged: (value) => setState(() => _showProviders = value)),
                              _SwitchRow(title: 'Banktransfer anzeigen', subtitle: 'Ueberweisung, Referenz und manuelle Zuordnung.', value: _showBankTransfers, onChanged: (value) => setState(() => _showBankTransfers = value)),
                              _SwitchRow(title: 'Refunds anzeigen', subtitle: 'Rueckerstattung, Storno und Auditnotiz.', value: _showRefunds, onChanged: (value) => setState(() => _showRefunds = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _CommerceCard(item: item, onOpen: () => _toast('${item.title}: Admin-Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine Commerce-Eintraege fuer diesen Bereich gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Freigeben', icon: Icons.verified_outlined, onPressed: () => _toast('Commerce-Freigabe vorbereitet')),
                              AirmiusButton(label: 'Commerce Ops', icon: Icons.shopping_bag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceOperationsScreen()))),
                              AirmiusButton(label: 'Marketplace', icon: Icons.storefront_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestMarketplaceBuyerScreen()))),
                              AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _CommerceHero extends StatelessWidget {
  const _CommerceHero({required this.onExport});

  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF12243A), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('ADMIN COMMERCE'), SizedBox(height: 4), Text('Marketplace-Betrieb steuern', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Export', icon: Icons.download_outlined, onPressed: onExport),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Das Admin-Commerce-Webmodul wird als mobile UI abgebildet: Bestellungen, Produkte, Provider, Banktransfer, Refunds und Freigaben.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Bereiche')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Refund'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _CommerceCard extends StatelessWidget {
  const _CommerceCard({required this.item, required this.onOpen});

  final _CommerceAdminItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.meta, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _CommerceAdminItem {
  const _CommerceAdminItem({required this.title, required this.area, required this.body, required this.status, required this.meta, required this.icon, required this.color});

  final String title;
  final String area;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
