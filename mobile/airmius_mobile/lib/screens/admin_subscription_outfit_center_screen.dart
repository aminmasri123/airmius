import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'outfit_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class AdminSubscriptionOutfitCenterScreen extends StatefulWidget {
  const AdminSubscriptionOutfitCenterScreen({super.key});

  @override
  State<AdminSubscriptionOutfitCenterScreen> createState() =>
      _AdminSubscriptionOutfitCenterState();
}

class _AdminSubscriptionOutfitCenterState
    extends State<AdminSubscriptionOutfitCenterScreen> {
  String _section = 'Abos';
  bool _showActive = true;
  bool _showPaused = true;
  bool _showRenewals = true;
  bool _showDeliveries = true;
  bool _showCancellations = true;

  final List<_SubscriptionItem> _items = const [
    _SubscriptionItem(
      title: 'Club Pro Abo',
      area: 'Abos',
      body: 'Vereinsabo mit Laufzeit, Rechnung, Renewal und Featureumfang.',
      status: 'Aktiv',
      meta: '79 EUR / Monat',
      icon: Icons.autorenew_outlined,
      color: AirmiusColors.blue,
    ),
    _SubscriptionItem(
      title: 'Outfit Subscription',
      area: 'Outfits',
      body:
          'Ausstattung, Größen, Lieferung, Status und Support für Team-Outfits.',
      status: 'Lieferung',
      meta: 'Team U16',
      icon: Icons.checkroom_outlined,
      color: AirmiusColors.green,
    ),
    _SubscriptionItem(
      title: 'Renewal Entscheidung',
      area: 'Renewals',
      body: 'Nächste Verlängerung, Rechnung, Zahlung und Adminentscheidung.',
      status: 'Fällig',
      meta: 'in 14 Tagen',
      icon: Icons.update_outlined,
      color: AirmiusColors.amber,
    ),
    _SubscriptionItem(
      title: 'Pausiertes Abo',
      area: 'Pausiert',
      body: 'Pause, Grund, Reaktivierung, Laufzeit und Benachrichtigung.',
      status: 'Pausiert',
      meta: 'Support',
      icon: Icons.pause_circle_outline,
      color: AirmiusColors.blueDeep,
    ),
    _SubscriptionItem(
      title: 'Kündigung prüfen',
      area: 'Kündigungen',
      body:
          'Kündigungsgrund, Datenexport, Rechnung, Refund und Retention-Hinweis.',
      status: 'Prüfen',
      meta: 'Risiko',
      icon: Icons.cancel_outlined,
      color: AirmiusColors.red,
    ),
  ];

  List<_SubscriptionItem> get _visibleItems => _items
      .where((item) => _section == 'Alle' || item.area == _section)
      .toList();

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
                        const PageTitle(
                          title: 'Admin Subscriptions & Outfits',
                          subtitle:
                              'Abos, OutfitSubscriptions, Laufzeiten, Renewals, Lieferungen, Pausen und Kündigungen.',
                        ),
                        const SizedBox(height: 16),
                        _SubscriptionHero(
                          onExport: () => _toast('Abo-Export vorbereitet'),
                        ),
                        const SizedBox(height: 16),
                        _ChoicePanel(
                          title: 'Bereich',
                          value: _section,
                          values: const [
                            'Alle',
                            'Abos',
                            'Outfits',
                            'Renewals',
                            'Pausiert',
                            'Kündigungen',
                          ],
                          onChanged: (value) =>
                              setState(() => _section = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Abo-Filter',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Aktive anzeigen',
                                subtitle:
                                    'Aktive Subscriptions mit Status und Rechnung.',
                                value: _showActive,
                                onChanged: (value) =>
                                    setState(() => _showActive = value),
                              ),
                              _SwitchRow(
                                title: 'Pausierte anzeigen',
                                subtitle:
                                    'Pausen, Gründe und Reaktivierung sichtbar machen.',
                                value: _showPaused,
                                onChanged: (value) =>
                                    setState(() => _showPaused = value),
                              ),
                              _SwitchRow(
                                title: 'Renewals anzeigen',
                                subtitle:
                                    'Verlängerungen, Fristen und Zahlungsstatus anzeigen.',
                                value: _showRenewals,
                                onChanged: (value) =>
                                    setState(() => _showRenewals = value),
                              ),
                              _SwitchRow(
                                title: 'Outfit-Lieferungen anzeigen',
                                subtitle:
                                    'Größen, Versand, Status und Support für Outfit-Abos.',
                                value: _showDeliveries,
                                onChanged: (value) =>
                                    setState(() => _showDeliveries = value),
                              ),
                              _SwitchRow(
                                title: 'Kündigungen anzeigen',
                                subtitle:
                                    'Kündigung, Retention, Refund und Datenexport vorbereiten.',
                                value: _showCancellations,
                                onChanged: (value) =>
                                    setState(() => _showCancellations = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _SubscriptionCard(
                            item: item,
                            onOpen: () =>
                                _toast('${item.title}: Abo-Detail vorbereitet'),
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty)
                          const EmptyPanel(
                            'Keine Abos für diesen Bereich gefunden.',
                          ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Renewal prüfen',
                                icon: Icons.update_outlined,
                                onPressed: () =>
                                    _toast('Renewal prüfen vorbereitet'),
                              ),
                              AirmiusButton(
                                label: 'Billing',
                                icon: Icons.receipt_long_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => BillingOperationsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Outfit Ops',
                                icon: Icons.checkroom_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => OutfitOperationsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Support',
                                icon: Icons.support_agent_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => SupportHelpdeskScreen(),
                                  ),
                                ),
                              ),
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
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SubscriptionHero extends StatelessWidget {
  const _SubscriptionHero({required this.onExport});

  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF10243B), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
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
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('ADMIN SUBSCRIPTIONS'),
                    SizedBox(height: 4),
                    Text(
                      'Abos und Outfits steuern',
                      style: TextStyle(
                        color: AirmiusColors.text,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: 'Export',
                icon: Icons.download_outlined,
                onPressed: onExport,
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Die Admin-Subscriptions- und OutfitSubscriptions-Webmodule werden als mobile UI abgebildet: Status, Laufzeit, Renewal, Lieferung, Pause und Kündigung.',
            style: TextStyle(
              color: AirmiusColors.muted,
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(
                child: MetricCard(value: '5', label: 'Bereiche'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '2', label: 'Fällig'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '1', label: 'Outfit'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.value,
    required this.values,
    required this.onChanged,
  });

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
              labelStyle: TextStyle(
                color: value == item ? AirmiusColors.text : AirmiusColors.muted,
                fontWeight: FontWeight.w900,
              ),
              side: BorderSide(
                color: value == item
                    ? AirmiusColors.blue
                    : AirmiusColors.border,
              ),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeThumbColor: AirmiusColors.blue,
          ),
        ],
      ),
    );
  }
}

class _SubscriptionCard extends StatelessWidget {
  const _SubscriptionCard({required this.item, required this.onOpen});

  final _SubscriptionItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: item.color.withValues(alpha: .5)),
            ),
            child: Icon(item.icon, color: item.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(item.status, color: item.color),
                const SizedBox(height: 8),
                Text(
                  item.meta,
                  style: const TextStyle(
                    color: AirmiusColors.blue,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  item.body,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ),
        ],
      ),
    );
  }
}

class _SubscriptionItem {
  const _SubscriptionItem({
    required this.title,
    required this.area,
    required this.body,
    required this.status,
    required this.meta,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
