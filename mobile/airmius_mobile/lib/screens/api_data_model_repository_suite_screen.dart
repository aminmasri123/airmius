import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ApiDataModelRepositorySuiteScreen extends StatelessWidget {
  const ApiDataModelRepositorySuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final models = [
      _ModelItem('User', 'Auth', 'id, name, email, role, avatarUrl und spätere Workspace-/Permission-Daten.', AirmiusColors.blue, Icons.person_outline),
      _ModelItem('Club', 'Verein', 'Name, Stadt, Mitgliederzahl, Logo, Banner und Antragsschalter.', AirmiusColors.green, Icons.apartment_outlined),
      _ModelItem('MembershipApplication', 'Forms', 'Status, Club, eingereicht, zurückgezogen und dynamische Payloads.', AirmiusColors.amber, Icons.assignment_ind_outlined),
      _ModelItem('Files, Events, Invoices', 'Core', 'Upload-Assets, Kalenderdaten und Rechnungen mit Pagination-Vertrag.', AirmiusColors.pink, Icons.hub_outlined),
    ];

    final repositories = [
      _RepoItem('AirmiusAuthRepository', 'Login und aktueller Nutzer als klares Interface.'),
      _RepoItem('AirmiusClubRepository', 'Vereinssuche, Clubdetail und Pagination für mobile Listen.'),
      _RepoItem('AirmiusMembershipRepository', 'Mitgliedsantrag senden und zurückziehen als typed Flow.'),
      _RepoItem('AirmiusFile/Event/Billing Repositories', 'Upload Intent, Events und Rechnungen später API-sicher anbinden.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('API Datenmodelle', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'API Datenmodelle',
        subtitle: 'Typed Models, Pagination, Repository-Verträge und Laravel Response-Mapping für echte Daten statt Mock-UI.',
        trailing: const StatusPill('64% API Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('DATA CONTRACTS'),
                  SizedBox(height: 10),
                  Text('Flutter braucht stabile Datenformen, bevor echte Screens sauber laufen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Die neuen Models und Repository-Verträge definieren, wie Laravel-Responses in der App ankommen: User, Vereine, Mitgliedsanträge, Dateien, Events, Rechnungen und Pagination.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '47%', label: 'Fertig')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '53%', label: 'Rest')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '36%', label: 'API')),
              ],
            ),
            const SizedBox(height: 14),
            for (final model in models) ...[
              AirmiusPanel(
                borderColor: model.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: model.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: model.color.withValues(alpha: .45)),
                      ),
                      child: Icon(model.icon, color: model.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(model.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(model.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(model.area, color: model.color), const StatusPill('Typed')]),
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
                  const Eyebrow('REPOSITORIES'),
                  const SizedBox(height: 12),
                  for (final repo in repositories) ...[
                    _RepoRow(item: repo),
                    if (repo != repositories.last) const SizedBox(height: 10),
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

class _ModelItem {
  const _ModelItem(this.title, this.area, this.body, this.color, this.icon);

  final String title;
  final String area;
  final String body;
  final Color color;
  final IconData icon;
}

class _RepoItem {
  const _RepoItem(this.title, this.body);

  final String title;
  final String body;
}

class _RepoRow extends StatelessWidget {
  const _RepoRow({required this.item});

  final _RepoItem item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .42))),
            child: const Icon(Icons.data_object_outlined, color: AirmiusColors.blue, size: 19),
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
