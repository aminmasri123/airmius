import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class AdminFinanceContractSuiteScreen extends StatefulWidget {
  const AdminFinanceContractSuiteScreen({super.key});

  @override
  State<AdminFinanceContractSuiteScreen> createState() => _AdminFinanceContractSuiteScreenState();
}

class _AdminFinanceContractSuiteScreenState extends State<AdminFinanceContractSuiteScreen> {
  String _filter = 'Alle';
  bool _showInvoices = true;
  bool _showPayments = true;
  bool _showContracts = true;

  final List<_AdminFinanceItem> _items = const [
    _AdminFinanceItem(
      title: 'Admin Rechnungen',
      area: 'Invoices',
      status: 'Offen',
      body: 'Plattform-Rechnungen, Vereine, Anbieter, Faelligkeit, Mahnstatus und Zahlungsabgleich.',
      icon: Icons.receipt_long_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _AdminFinanceItem(
      title: 'Subscription Invoices',
      area: 'Invoices',
      status: 'Abo',
      body: 'Abo-Rechnungen fuer Plaene, Laufzeiten, Steuerhinweise, Rechnungsnummern und Download-Status.',
      icon: Icons.request_quote_outlined,
      color: Color(0xFF2EE59D),
    ),
    _AdminFinanceItem(
      title: 'Payments',
      area: 'Payments',
      status: 'Abgleich',
      body: 'Zahlungseingaenge, Banktransfer, Referenzen, Rueckfragen und manuelle Zahlungsfreigaben.',
      icon: Icons.payments_outlined,
      color: Color(0xFFF8B84E),
    ),
    _AdminFinanceItem(
      title: 'Subscriptions',
      area: 'Payments',
      status: 'Plan',
      body: 'Aktive Plaene, Upgrades, Downgrades, Pausen, Kuendigungen und Kulanzentscheidungen.',
      icon: Icons.workspace_premium_outlined,
      color: Color(0xFFB084FF),
    ),
    _AdminFinanceItem(
      title: 'Provider Costs',
      area: 'Contracts',
      status: 'Kosten',
      body: 'Kosten pro Anbieter, Auszahlung, Marge, Vertragsstatus und interne Freigaben.',
      icon: Icons.price_change_outlined,
      color: Color(0xFFFF6B6B),
    ),
    _AdminFinanceItem(
      title: 'Operating Contracts',
      area: 'Contracts',
      status: 'Vertrag',
      body: 'Betriebsvertraege, Dokumentstatus, Gueltigkeit, Ansprechpartner und Renewal-Hinweise.',
      icon: Icons.gavel_outlined,
      color: Color(0xFF5BA7FF),
    ),
  ];

  List<_AdminFinanceItem> get _visibleItems {
    if (_filter == 'Alle') return _items;
    return _items.where((item) => item.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _Header(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _Hero(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '6', label: 'Admin-Flows')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '2', label: 'Invoices')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '2', label: 'Contracts')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(
                      value: _filter,
                      values: const ['Alle', 'Invoices', 'Payments', 'Contracts'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showInvoices: _showInvoices,
                      showPayments: _showPayments,
                      showContracts: _showContracts,
                      onInvoices: (value) => setState(() => _showInvoices = value),
                      onPayments: (value) => setState(() => _showPayments = value),
                      onContracts: (value) => setState(() => _showContracts = value),
                    ),
                    const SizedBox(height: 14),
                    for (final item in _visibleItems.where(_isVisible)) ...[
                      _AdminFinanceCard(item: item),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onExport: () => openUiAction(
                        context,
                        title: 'Finanzexport',
                        message: 'Die UI fuer Rechnungen, Zahlungen und Vertraege ist bereit; Daten kommen spaeter per API.',
                      ),
                      onApprove: () => openUiAction(
                        context,
                        title: 'Freigabe vorbereiten',
                        message: 'Admin-Freigaben werden spaeter mit Laravel-Rollen und Audit-Logs verbunden.',
                      ),
                      onSupport: () => _openSupport(context),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _isVisible(_AdminFinanceItem item) {
    if (item.area == 'Invoices') return _showInvoices;
    if (item.area == 'Payments') return _showPayments;
    return _showContracts;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _AdminFinanceItem {
  const _AdminFinanceItem({
    required this.title,
    required this.area,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _Header extends StatelessWidget {
  const _Header({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('ADMIN FINANCE', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Rechnungen & Vertraege', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI fuer Admin Invoices, Payments, Subscription Invoices, Subscriptions, Provider Costs und Operating Contracts.',
            style: TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(color: active ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))),
          );
        },
      ),
    );
  }
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({
    required this.showInvoices,
    required this.showPayments,
    required this.showContracts,
    required this.onInvoices,
    required this.onPayments,
    required this.onContracts,
  });

  final bool showInvoices;
  final bool showPayments;
  final bool showContracts;
  final ValueChanged<bool> onInvoices;
  final ValueChanged<bool> onPayments;
  final ValueChanged<bool> onContracts;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Admin-Sichten',
      child: Column(
        children: [
          _SwitchRow(label: 'Rechnungen anzeigen', value: showInvoices, onChanged: onInvoices),
          _SwitchRow(label: 'Zahlungen anzeigen', value: showPayments, onChanged: onPayments),
          _SwitchRow(label: 'Vertraege anzeigen', value: showContracts, onChanged: onContracts),
        ],
      ),
    );
  }
}

class _AdminFinanceCard extends StatelessWidget {
  const _AdminFinanceCard({required this.item});

  final _AdminFinanceItem item;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(color: item.color.withOpacity(.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withOpacity(.45))),
            child: Icon(item.icon, color: item.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(item.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                    _Pill(label: item.status, color: item.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(item.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.onExport, required this.onApprove, required this.onSupport});

  final VoidCallback onExport;
  final VoidCallback onApprove;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.file_download_outlined, label: 'Finanzexport vorbereiten', onTap: onExport),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.verified_outlined, label: 'Admin-Freigabe starten', onTap: onApprove),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF0D131D), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, required this.value, required this.onChanged});

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFF111A27), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(
          children: [
            Icon(icon, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900))),
            const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8)),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.55))),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
