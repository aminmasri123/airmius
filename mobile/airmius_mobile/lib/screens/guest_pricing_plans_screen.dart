import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'subscription_center_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestPricingPlansScreen extends StatefulWidget {
  const GuestPricingPlansScreen({super.key});

  @override
  State<GuestPricingPlansScreen> createState() => _GuestPricingPlansScreenState();
}

class _GuestPricingPlansScreenState extends State<GuestPricingPlansScreen> {
  String _audience = 'Vereine';
  bool _monthly = true;
  bool _bankTransfer = true;
  bool _trial = true;
  bool _sponsorOption = true;

  final List<_PricingPlan> _plans = const [
    _PricingPlan(title: 'Starter', audience: 'User', body: 'Basisprofil, Vereine entdecken, Mitgliedsanfrage, Feed und Notifications.', price: '0 EUR', status: 'Free', icon: Icons.person_outline, color: AirmiusColors.green),
    _PricingPlan(title: 'Club Basic', audience: 'Vereine', body: 'Vereinsprofil, Mitglieder, Teams, Anfragen, Dokumente und Beitragsregeln.', price: '29 EUR / Monat', status: 'Club', icon: Icons.apartment_outlined, color: AirmiusColors.blue),
    _PricingPlan(title: 'Club Pro', audience: 'Vereine', body: 'Events, Anwesenheit, Reports, Rollen, Kommunikation und Finanzcockpit.', price: '79 EUR / Monat', status: 'Pro', icon: Icons.verified_outlined, color: AirmiusColors.amber),
    _PricingPlan(title: 'Partner', audience: 'Sponsoren', body: 'Ads, Sponsoring, Kampagnen, Reporting, Marketplace und regionale Sichtbarkeit.', price: 'ab 199 EUR', status: 'Partner', icon: Icons.handshake_outlined, color: AirmiusColors.red),
  ];

  List<_PricingPlan> get _visiblePlans => _plans.where((plan) => _audience == 'Alle' || plan.audience == _audience).toList();

  @override
  Widget build(BuildContext context) {
    final plans = _visiblePlans;

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
                        const PageTitle(title: 'Preise & Plaene', subtitle: 'Public Pricing, Zielgruppen, Abo, Banktransfer, Testphase und Planvergleich.'),
                        const SizedBox(height: 16),
                        _PricingHero(onStart: () => _toast('Plan starten vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Zielgruppe', value: _audience, values: const ['Alle', 'User', 'Vereine', 'Sponsoren'], onChanged: (value) => setState(() => _audience = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Kaufoptionen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Monatlich anzeigen', subtitle: 'Monatliche Preise und Abo-Optionen anzeigen.', value: _monthly, onChanged: (value) => setState(() => _monthly = value)),
                              _SwitchRow(title: 'Banktransfer erlauben', subtitle: 'Überweisung als Checkout- und Subscription-Zahlart vorbereiten.', value: _bankTransfer, onChanged: (value) => setState(() => _bankTransfer = value)),
                              _SwitchRow(title: 'Testphase anzeigen', subtitle: 'Probezeit, Demo und Onboarding für Vereine sichtbar machen.', value: _trial, onChanged: (value) => setState(() => _trial = value)),
                              _SwitchRow(title: 'Sponsorenoption anzeigen', subtitle: 'Partner- und Ads-Pakete mit Public Growth verbinden.', value: _sponsorOption, onChanged: (value) => setState(() => _sponsorOption = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final plan in plans) ...[
                          _PlanCard(plan: plan, onSelect: () => _toast('${plan.title}: Plan auswählen vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (plans.isEmpty) const EmptyPanel('Keine Plaene für diese Zielgruppe gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Checkout & Hilfe',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Abo starten', icon: Icons.rocket_launch_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionCenterScreen()))),
                              AirmiusButton(label: 'Billing', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
                              AirmiusButton(label: 'Frage stellen', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
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

class _PricingHero extends StatelessWidget {
  const _PricingHero({required this.onStart});

  final VoidCallback onStart;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('PRICING'), SizedBox(height: 4), Text('Plaene für Nutzer, Vereine und Partner', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Starten', icon: Icons.rocket_launch_outlined, onPressed: onStart),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Guest-Pricing-Seite wird als mobile Landing-UI abgebildet: Planvergleich, Zielgruppen, Banktransfer, Testphase und Abo-Start.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Plaene')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Zielgruppen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Checkout'))]),
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
        Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.plan, required this.onSelect});

  final _PricingPlan plan;
  final VoidCallback onSelect;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: plan.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: plan.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: plan.color.withValues(alpha: .5))), child: Icon(plan.icon, color: plan.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(plan.status, color: plan.color), const SizedBox(height: 8), Text(plan.price, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(plan.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onSelect, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _PricingPlan {
  const _PricingPlan({required this.title, required this.audience, required this.body, required this.price, required this.status, required this.icon, required this.color});

  final String title;
  final String audience;
  final String body;
  final String price;
  final String status;
  final IconData icon;
  final Color color;
}
