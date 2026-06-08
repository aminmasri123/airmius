import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SubscriptionEntitlementFeatureGateSuiteScreen extends StatefulWidget {
  const SubscriptionEntitlementFeatureGateSuiteScreen({super.key});

  @override
  State<SubscriptionEntitlementFeatureGateSuiteScreen> createState() => _SubscriptionEntitlementFeatureGateSuiteScreenState();
}

class _SubscriptionEntitlementFeatureGateSuiteScreenState extends State<SubscriptionEntitlementFeatureGateSuiteScreen> {
  String _plan = 'Verein Pro';
  bool _teamModule = true;
  bool _financeModule = true;
  bool _analyticsModule = true;
  bool _sponsorModule = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Abos & Feature Gates', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Subscription Entitlement Feature Gates',
        subtitle: 'Mobile UI fuer Tarife, Vereinslimits, Rollenrechte, Modulzugriff, Upgrade-Hinweise und API-ready Entitlements.',
        trailing: const StatusPill('Entitlements', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('PLATFORM ACCESS'),
                  const SizedBox(height: 8),
                  const Text(
                    'Jeder Plan zeigt nur, was wirklich freigeschaltet ist.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die Flutter-App bereitet Feature-Gates so vor, dass Vereine, Mitglieder, Sponsoren und Admins spaeter klare Limits, Upgrades und gesperrte Module sehen.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Free', 'Verein Pro', 'Verein Plus', 'Enterprise'].map((item) {
                      return ChoiceChip(
                        selected: _plan == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _plan = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _plan == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _plan == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '4', label: 'Plaene')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '12', label: 'Gates')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'API', label: 'Sync')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('AKTIVER PLAN')), StatusPill(_plan, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _LimitCard(title: 'Mitgliederlimit', value: _plan == 'Free' ? '25' : _plan == 'Verein Pro' ? '250' : 'Unbegrenzt', body: 'Wird fuer Mitgliederverwaltung, Einladungen und Import/Export angezeigt.'),
                  const SizedBox(height: 10),
                  _LimitCard(title: 'Teams', value: _plan == 'Free' ? '2' : _plan == 'Verein Pro' ? '12' : 'Unbegrenzt', body: 'Steuert Teamverwaltung, Kader, Rollen und Teamdateien.'),
                  const SizedBox(height: 10),
                  _LimitCard(title: 'Speicher', value: _plan == 'Free' ? '1 GB' : _plan == 'Verein Pro' ? '25 GB' : '100 GB+', body: 'Dateimanager, Uploads, Dokumente und Medienanhaenge nutzen dieses Limit.'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('FEATURE GATES'),
                  const SizedBox(height: 12),
                  _GateToggle(
                    icon: Icons.groups_2_outlined,
                    title: 'Teams & Rollen',
                    body: 'Teamverwaltung, Trainerrollen, Captains, Join-Requests und Teamdateien.',
                    enabled: _teamModule,
                    onChanged: (value) => setState(() => _teamModule = value),
                  ),
                  _GateToggle(
                    icon: Icons.account_balance_wallet_outlined,
                    title: 'Vereinsfinanzen',
                    body: 'Beitraege, Rechnungen, Zahlungsstatus, Mahnungen, SEPA und Quittungen.',
                    enabled: _financeModule,
                    onChanged: (value) => setState(() => _financeModule = value),
                  ),
                  _GateToggle(
                    icon: Icons.insights_outlined,
                    title: 'Analytics & Reports',
                    body: 'Mitgliederentwicklung, Beitragsstatus, Events, Support, Ads und Exporte.',
                    enabled: _analyticsModule,
                    onChanged: (value) => setState(() => _analyticsModule = value),
                  ),
                  _GateToggle(
                    icon: Icons.campaign_outlined,
                    title: 'Sponsoren & Ads',
                    body: 'Sponsorprofile, Kampagnen, Placements, Budget, Reporting und Club-Targeting.',
                    enabled: _sponsorModule,
                    onChanged: (value) => setState(() => _sponsorModule = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('UPGRADE HINWEIS'),
                  const SizedBox(height: 8),
                  const Text('Feature-Locks sollen freundlich sein: Nutzer sehen, warum etwas gesperrt ist, welcher Plan es freischaltet und welche Daten erhalten bleiben.', style: TextStyle(color: AirmiusColors.muted, height: 1.38)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('No data loss', color: AirmiusColors.green),
                      StatusPill('Plan compare', color: AirmiusColors.blue),
                      StatusPill('Admin only', color: AirmiusColors.amber),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Plan vergleichen', icon: Icons.price_change_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API ENTITLEMENT PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'workspace_type', value: 'club'),
                  _PayloadLine(label: 'active_plan', value: _plan.toLowerCase().replaceAll(' ', '_')),
                  const _PayloadLine(label: 'feature_keys', value: 'teams, finance, analytics, sponsors'),
                  const _PayloadLine(label: 'gate_behavior', value: 'visible_locked, hidden, readonly, upgrade_cta'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LimitCard extends StatelessWidget {
  const _LimitCard({required this.title, required this.value, required this.body});

  final String title;
  final String value;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .38))),
            child: Center(child: Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900))),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GateToggle extends StatelessWidget {
  const _GateToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

  final IconData icon;
  final String title;
  final String body;
  final bool enabled;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: (enabled ? AirmiusColors.green : AirmiusColors.amber).withValues(alpha: .14),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: enabled ? AirmiusColors.green.withValues(alpha: .45) : AirmiusColors.amber.withValues(alpha: .45)),
            ),
            child: Icon(enabled ? icon : Icons.lock_outline, color: enabled ? AirmiusColors.green : AirmiusColors.amber),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          Switch(value: enabled, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _PayloadLine extends StatelessWidget {
  const _PayloadLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 120, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}
