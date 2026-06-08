import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'guardian_consent_detail_screen.dart';
import 'safety_community_operations_screen.dart';

class GuardianCenterScreen extends StatefulWidget {
  const GuardianCenterScreen({super.key});

  @override
  State<GuardianCenterScreen> createState() => _GuardianCenterScreenState();
}

class _GuardianCenterScreenState extends State<GuardianCenterScreen> {
  bool _consentEnabled = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.security_outlined), label: const Text('Guardian Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen(initialTab: 2)))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Eltern & Jugendschutz', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Eltern & Jugendschutz',
        subtitle: 'Guardian Consent, Elternzugang, Kinderkonten und Widerruf',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(
            gradient: true,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Eyebrow('Schutz minderjaehriger Nutzer'),
              const SizedBox(height: 8),
              const Text('Eltern koennen Zustimmung geben, Kinder verwalten und Zugriffe widerrufen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 12),
              SwitchListTile(
                value: _consentEnabled,
                onChanged: (value) => setState(() => _consentEnabled = value),
                contentPadding: EdgeInsets.zero,
                activeColor: AirmiusColors.blue,
                title: const Text('Guardian Consent aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                subtitle: const Text('Minderjaehrige benoetigen Zustimmung.', style: TextStyle(color: AirmiusColors.muted)),
              ),
            ]),
          ),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '1', label: 'Ausstehend')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Kinder')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Widerruf'))]),
          const SizedBox(height: 14),
          const _GuardianCard(title: 'Zustimmung ausstehend', body: 'ZBB Junior wartet auf Freigabe per E-Mail oder Code.', status: 'Offen', icon: Icons.pending_actions_outlined),
          const SizedBox(height: 12),
          const _GuardianCard(title: 'Elternlogin', body: 'Code-Verifizierung und Zugriff auf Kinderkonten.', status: 'Login', icon: Icons.family_restroom_outlined),
          const SizedBox(height: 12),
          const _GuardianCard(title: 'Elterncode pruefen', body: 'Token oder Code aus E-Mail eingeben und Elternzugang freischalten.', status: 'Code', icon: Icons.password_outlined),
          const SizedBox(height: 12),
          const _GuardianCard(title: 'Kinder verwalten', body: 'Profile, Vereine, Zustimmung und Widerruf verwalten.', status: '2 Kinder', icon: Icons.child_care_outlined),
          const SizedBox(height: 12),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Zustimmung senden', icon: Icons.send_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Zustimmung senden', body: 'Guardian Consent, Eltern-E-Mail, Token und Freigabeumfang vorbereiten.', status: 'Consent', icon: Icons.send_outlined)))),
              AirmiusButton(label: 'Elternlogin starten', icon: Icons.family_restroom_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Elternlogin starten', body: 'Elternzugang, Code-Verifizierung, Kinderuebersicht und Session vorbereiten.', status: 'Elternlogin', icon: Icons.family_restroom_outlined)))),
              AirmiusButton(label: 'Kinderkonto erstellen', icon: Icons.child_care_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Kinderkonto erstellen', body: 'Elternkonto, Kindprofil, Altersfreigaben und Consent-Historie vorbereiten.', status: 'Kinderkonto', icon: Icons.child_care_outlined)))),
              AirmiusButton(label: 'Safety Ops', icon: Icons.health_and_safety_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen()))),
              AirmiusButton(label: 'Widerrufen', icon: Icons.block_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Zustimmung widerrufen', body: 'Widerruf, betroffene Rechte und Historie vorbereiten.', status: 'Widerruf', icon: Icons.block_outlined)))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _GuardianCard extends StatelessWidget {
  const _GuardianCard({required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuardianConsentDetailScreen(title: title, body: body, status: status, icon: icon))),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: AirmiusColors.blue),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)), const SizedBox(height: 8), StatusPill(status)])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}

