import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LaravelApiBindingProgressSuiteScreen extends StatelessWidget {
  const LaravelApiBindingProgressSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final bindings = [
      _BindingItem('Auth & Session', '45%', 'Login, Me, Token, Locale und Guard-Status sind als Client-Kontrakt vorbereitet.', AirmiusColors.blue, Icons.lock_outline),
      _BindingItem('Vereine & Mitgliedschaft', '40%', 'Club-Liste, Clubdetail, Antrag senden und Antrag zurueckziehen sind als Endpunkte modelliert.', AirmiusColors.green, Icons.apartment_outlined),
      _BindingItem('Dateien & Uploads', '28%', 'Upload-Intent ist vorbereitet; echter Multipart/Storage-Flow bleibt offen.', AirmiusColors.amber, Icons.cloud_upload_outlined),
      _BindingItem('Billing, Chat, Events', '24%', 'Invoices, Conversations, Notifications und Events sind als erste Lesepfade abgebildet.', AirmiusColors.pink, Icons.hub_outlined),
    ];

    final nextSteps = [
      _NextStep('Transport implementieren', 'HTTP-Transport mit Auth-Headern, Timeout, Retry, Offline-Queue und Fehlervertrag anschliessen.'),
      _NextStep('Laravel Routen absichern', 'API-v1-Routen, Sanctum/Token-Strategie, Policies, Pagination und Response-Formate finalisieren.'),
      _NextStep('Screens mit Daten verbinden', 'Clubseiten, Suche, Mitgliedsantrag, Dateien, Notifications und Rechnungen von Mock auf API umstellen.'),
      _NextStep('Tests & Monitoring', 'Contract-Tests, Smoke-Flows, Error-States, Logging und Rollback-Strategie aufbauen.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('API Bindung', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'API Bindung',
        subtitle: 'Laravel-v1-Client, Auth, Vereine, Mitgliedsantraege, Uploads, Billing, Chat, Events und offene Rest-Prozente.',
        trailing: const StatusPill('66% Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('API FOUNDATION'),
                  SizedBox(height: 10),
                  Text('Jetzt beginnt der Weg von Mock-UI zu echter App.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Der Client-Kontrakt definiert die wichtigsten Laravel-v1-Endpunkte. Danach koennen Screens Schritt fuer Schritt echte Daten statt vorbereiteter UI-Zustaende verwenden.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '46%', label: 'Fertig')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '54%', label: 'Rest')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '34%', label: 'API')),
              ],
            ),
            const SizedBox(height: 14),
            for (final binding in bindings) ...[
              AirmiusPanel(
                borderColor: binding.color.withValues(alpha: .44),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 50,
                          height: 50,
                          decoration: BoxDecoration(
                            color: binding.color.withValues(alpha: .14),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: binding.color.withValues(alpha: .45)),
                          ),
                          child: Icon(binding.icon, color: binding.color),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(binding.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                              const SizedBox(height: 5),
                              Text(binding.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                              const SizedBox(height: 10),
                              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(binding.done, color: binding.color), const StatusPill('Client-Kontrakt')]),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(999),
                      child: LinearProgressIndicator(
                        value: _percent(binding.done),
                        minHeight: 8,
                        backgroundColor: AirmiusColors.panelSoft,
                        valueColor: AlwaysStoppedAnimation<Color>(binding.color),
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
                  const Eyebrow('NAECHSTE API-GATES'),
                  const SizedBox(height: 12),
                  for (final step in nextSteps) ...[
                    _NextStepRow(item: step),
                    if (step != nextSteps.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static double _percent(String value) {
    final number = double.tryParse(value.replaceAll('%', '')) ?? 0;
    return number.clamp(0, 100) / 100;
  }
}

class _BindingItem {
  const _BindingItem(this.title, this.done, this.body, this.color, this.icon);

  final String title;
  final String done;
  final String body;
  final Color color;
  final IconData icon;
}

class _NextStep {
  const _NextStep(this.title, this.body);

  final String title;
  final String body;
}

class _NextStepRow extends StatelessWidget {
  const _NextStepRow({required this.item});

  final _NextStep item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .42))),
            child: const Icon(Icons.api_outlined, color: AirmiusColors.blue, size: 19),
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
