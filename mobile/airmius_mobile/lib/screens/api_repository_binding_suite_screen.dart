import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ApiRepositoryBindingSuiteScreen extends StatelessWidget {
  const ApiRepositoryBindingSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final repos = [
      _RepoBinding('Auth Repository', 'User', 'Login und aktueller Nutzer laufen ueber typed AirmiusUser.', AirmiusColors.blue, Icons.lock_outline),
      _RepoBinding('Club Repository', 'Club', 'Vereinssuche und Clubdetail werden auf AirmiusClub gemappt.', AirmiusColors.green, Icons.apartment_outlined),
      _RepoBinding('Membership Repository', 'Application', 'Mitgliedsantrag senden liefert ClubMembershipRequest, Zurueckziehen nutzt die club-scoped Web-Route.', AirmiusColors.amber, Icons.assignment_ind_outlined),
      _RepoBinding('Files, Events, Billing', 'Page<T>', 'Upload-Intent, Events und Rechnungen haben klare Repository-Vertraege.', AirmiusColors.pink, Icons.hub_outlined),
    ];

    final next = [
      _NextGate('HTTP Transport', 'Echten Transport fuer mobile/web faehige Requests, Timeouts, Retry und Fehler-Mapping anschliessen.'),
      _NextGate('Provider/State Layer', 'RepositoryBundle in App-State, Auth-State, Cache und Screens einspeisen.'),
      _NextGate('Screen Migration', 'Clubs, Suche, Mitgliedsantrag, Notifications, Events und Billing von Mock auf Repository umstellen.'),
      _NextGate('Contract Tests', 'Laravel Response-Formate mit typed Models und Error-States pruefen.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('API Repositories', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'API Repositories',
        subtitle: 'Repository-Implementierungen verbinden API-Client, typed Models, Pagination und spaetere Screens.',
        trailing: const StatusPill('62% API Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('REPOSITORY LAYER'),
                  SizedBox(height: 10),
                  Text('Die App bekommt eine echte Daten-Schicht.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Repositories kapseln Laravel-Requests und liefern typisierte Objekte. Damit koennen UI-Screens spaeter sauber von Mock-Daten auf echte API-Daten wechseln.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '48%', label: 'Fertig')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '52%', label: 'Rest')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '38%', label: 'API')),
              ],
            ),
            const SizedBox(height: 14),
            for (final repo in repos) ...[
              AirmiusPanel(
                borderColor: repo.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: repo.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: repo.color.withValues(alpha: .45)),
                      ),
                      child: Icon(repo.icon, color: repo.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(repo.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(repo.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(repo.model, color: repo.color), const StatusPill('Repository')]),
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
                  const Eyebrow('NAECHSTE API-SCHRITTE'),
                  const SizedBox(height: 12),
                  for (final gate in next) ...[
                    _NextGateRow(item: gate),
                    if (gate != next.last) const SizedBox(height: 10),
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

class _RepoBinding {
  const _RepoBinding(this.title, this.model, this.body, this.color, this.icon);

  final String title;
  final String model;
  final String body;
  final Color color;
  final IconData icon;
}

class _NextGate {
  const _NextGate(this.title, this.body);

  final String title;
  final String body;
}

class _NextGateRow extends StatelessWidget {
  const _NextGateRow({required this.item});

  final _NextGate item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .42))),
            child: const Icon(Icons.alt_route_outlined, color: AirmiusColors.blue, size: 19),
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
