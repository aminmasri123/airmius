import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'system_admin_operations_screen.dart';

class AdminProviderContractsScreen extends StatefulWidget {
  const AdminProviderContractsScreen({super.key});

  @override
  State<AdminProviderContractsScreen> createState() => _AdminProviderContractsScreenState();
}

class _AdminProviderContractsScreenState extends State<AdminProviderContractsScreen> {
  String _view = 'Kosten';
  bool _showContracts = true;
  bool _showProviderCosts = true;
  bool _showRenewals = true;
  bool _showRisks = true;

  final List<_ProviderItem> _items = const [
    _ProviderItem(title: 'Mail Provider', body: 'SMTP, Transaktionsmails, Zustellstatus und monatliche Versandkosten.', status: 'Aktiv', amount: '39 EUR / Monat', icon: Icons.mark_email_read_outlined, color: AirmiusColors.blue),
    _ProviderItem(title: 'Payment Provider', body: 'Zahlungsgebuehren, Banktransfer, SEPA-Hinweise und Providerabrechnung.', status: 'Prüfen', amount: '2.9% + Gebuehr', icon: Icons.payments_outlined, color: AirmiusColors.green),
    _ProviderItem(title: 'Storage & Dateien', body: 'Dateimanager, Uploads, Dokumente, Backups und Speicherlimit.', status: 'Aktiv', amount: '120 GB', icon: Icons.cloud_outlined, color: AirmiusColors.amber),
    _ProviderItem(title: 'Operating Contract', body: 'Betriebsvertrag, SLA, Supportfenster, Laufzeit und Kündigungsfrist.', status: 'Vertrag', amount: '12 Monate', icon: Icons.assignment_outlined, color: AirmiusColors.blueDeep),
    _ProviderItem(title: 'Security Monitoring', body: 'Logs, Warnungen, Moderation, Datenschutz und Incident-Prozesse.', status: 'Sensibel', amount: '24/7', icon: Icons.security_outlined, color: AirmiusColors.red),
  ];

  @override
  Widget build(BuildContext context) {
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
                        const PageTitle(title: 'Providerkosten & Betriebsverträge', subtitle: 'Kosten, Provider, SLA, Laufzeiten, Risiken, Verlängerungen und Plattformbetrieb.'),
                        const SizedBox(height: 16),
                        _ContractsHero(onExport: () => _toast('Kostenexport vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Ansicht', value: _view, values: const ['Kosten', 'Verträge', 'Risiken', 'Renewals'], onChanged: (value) => setState(() => _view = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Filter',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Betriebsverträge anzeigen', subtitle: 'SLA, Laufzeit, Kündigung und Verantwortliche.', value: _showContracts, onChanged: (value) => setState(() => _showContracts = value)),
                              _SwitchRow(title: 'Providerkosten anzeigen', subtitle: 'Monatliche Kosten, Volumen, Gebuehren und Kostenstellen.', value: _showProviderCosts, onChanged: (value) => setState(() => _showProviderCosts = value)),
                              _SwitchRow(title: 'Verlaengerungen anzeigen', subtitle: 'Renewals, Fristen und naechste Entscheidung.', value: _showRenewals, onChanged: (value) => setState(() => _showRenewals = value)),
                              _SwitchRow(title: 'Risiken anzeigen', subtitle: 'Sicherheits-, Datenschutz-, Kosten- und Betriebsrisiken.', value: _showRisks, onChanged: (value) => setState(() => _showRisks = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _ProviderCard(item: item, onOpen: () => _toast('${item.title}: Vertragsdetail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Export', icon: Icons.download_outlined, onPressed: () => _toast('Kostenexport vorbereitet')),
                              AirmiusButton(label: 'Billing', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
                              AirmiusButton(label: 'System Admin', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
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

class _ContractsHero extends StatelessWidget {
  const _ContractsHero({required this.onExport});

  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('PLATTFORMBETRIEB'), SizedBox(height: 4), Text('Kosten und Verträge im Blick', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Export', icon: Icons.download_outlined, onPressed: onExport),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Admin-UI für die Webmodule OperatingContracts und ProviderCosts: Kosten, Provider, Laufzeiten, SLA und Risiken werden mobil vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Provider')), SizedBox(width: 10), Expanded(child: MetricCard(value: '12', label: 'Monate')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Risiken'))]),
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

class _ProviderCard extends StatelessWidget {
  const _ProviderCard({required this.item, required this.onOpen});

  final _ProviderItem item;
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
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.amount, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _ProviderItem {
  const _ProviderItem({required this.title, required this.body, required this.status, required this.amount, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String amount;
  final IconData icon;
  final Color color;
}
