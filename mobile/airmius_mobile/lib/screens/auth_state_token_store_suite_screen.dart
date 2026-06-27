import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AuthStateTokenStoreSuiteScreen extends StatelessWidget {
  const AuthStateTokenStoreSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final states = [
      _StateItem('Booting', 'App-Start', 'Session wird aus TokenStore wiederhergestellt.', AirmiusColors.blue, Icons.rocket_launch_outlined),
      _StateItem('Guest', 'Nicht eingeloggt', 'Login, Registrierung und Public-Bereiche bleiben erreichbar.', AirmiusColors.amber, Icons.person_outline),
      _StateItem('Authenticated', 'Token aktiv', 'RepositoryBundle kann echte Laravel-Daten mit Auth-Headern laden.', AirmiusColors.green, Icons.verified_user_outlined),
      _StateItem('Expired/Error', 'Schutz', 'Abgelaufene Tokens, Fehler und Logout werden zentral steuerbar.', AirmiusColors.red, Icons.warning_amber_outlined),
    ];

    final gates = [
      _GateItem('Secure Storage anschließen', 'Aktuell ist der Store abstrahiert; echte native Speicherung folgt mit Package und Build-Freigabe.'),
      _GateItem('Auth-Provider in App verdrahten', 'AirmiusAuthState muss in main.dart/App-Shell bereitgestellt werden.'),
      _GateItem('Guards auf Screens anwenden', 'Private Bereiche, Vereinsadmin, Trainer und Plattformadmin bekommen Rollen-/Status-Gates.'),
      _GateItem('Token Refresh definieren', 'Laravel-Strategie für Refresh, Logout, 401, Sessionablauf und Device-Sperre finalisieren.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Auth State', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Auth State',
        subtitle: 'Session, persistenter TokenStore, Restore, Login, Logout, User Refresh, Locale und Auth-Phasen für echte API-Daten.',
        trailing: const StatusPill('60% API Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('SESSION CORE'),
                  SizedBox(height: 10),
                  Text('Die App braucht ein Gedaechtnis für Login und User.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('AirmiusAuthState kapselt Token, User, Locale, Restore, Refresh und Logout. Damit werden API-Client und Repositories später kontrolliert in die App-Shell eingebunden.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '49%', label: 'Fertig')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '51%', label: 'Rest')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '40%', label: 'API')),
              ],
            ),
            const SizedBox(height: 14),
            for (final state in states) ...[
              AirmiusPanel(
                borderColor: state.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: state.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: state.color.withValues(alpha: .45)),
                      ),
                      child: Icon(state.icon, color: state.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(state.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(state.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(state.status, color: state.color), const StatusPill('Auth')]),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('OFFENE AUTH-GATES'),
                  const SizedBox(height: 12),
                  for (final gate in gates) ...[
                    _GateRow(item: gate),
                    if (gate != gates.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StateItem {
  const _StateItem(this.title, this.status, this.body, this.color, this.icon);

  final String title;
  final String status;
  final String body;
  final Color color;
  final IconData icon;
}

class _GateItem {
  const _GateItem(this.title, this.body);

  final String title;
  final String body;
}

class _GateRow extends StatelessWidget {
  const _GateRow({required this.item});

  final _GateItem item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.amber.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.amber.withValues(alpha: .42))),
            child: const Icon(Icons.security_outlined, color: AirmiusColors.amber, size: 19),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      );
}
