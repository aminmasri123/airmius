import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ServiceContainerTransportSuiteScreen extends StatelessWidget {
  const ServiceContainerTransportSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final layers = [
      _LayerItem('Environment', 'Base URL', 'API-Base-URL, Locale, Offline Queue und Timeout werden zentral definiert.', AirmiusColors.blue, Icons.settings_outlined),
      _LayerItem('Service Container', 'App Core', 'AuthState, TokenStore, ClientFactory und RepositoryBundle werden zusammengefuehrt.', AirmiusColors.green, Icons.hub_outlined),
      _LayerItem('Queued Transport', 'Retry/Offline', 'Requests können bei Offline-Zustand gesammelt und später geflusht werden.', AirmiusColors.amber, Icons.sync_outlined),
      _LayerItem('Static Transport', 'Dev/Test', 'Mockbare Responses für lokale Screens, Demos und spätere Contract-Tests.', AirmiusColors.pink, Icons.bug_report_outlined),
    ];

    final gates = [
      _GateItem('Echten HTTP Transport bauen', 'Package/http oder Dio anschließen, ohne die Repository-Schicht neu zu schreiben.'),
      _GateItem('Secure Storage haerten', 'Persistenten TokenStore später durch Flutter Secure Storage, Keychain oder Android Keystore absichern.'),
      _GateItem('App-Shell verdrahten', 'ServiceContainer in main.dart bereitstellen und AuthState in Navigation/Guards nutzen.'),
      _GateItem('Screens umstellen', 'Clubsuche, Clubdetail, Mitgliedsantrag, Dateien und Billing auf Repositories migrieren.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Service Container', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Service Container',
        subtitle: 'Environment, API-Client, Auth-State, TokenStore, Repositories, Offline Queue und Transport-Pipeline.',
        trailing: const StatusPill('58% API Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('APP SERVICES'),
                  SizedBox(height: 10),
                  Text('Jetzt bekommt die App eine zentrale technische Schaltstelle.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Der Service Container verbindet Umgebung, Auth, API-Client, Repositories und Transport. Das ist die Stelle, an der später echter HTTP-Transport und Secure Storage eingesteckt werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '50%', label: 'Fertig')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '50%', label: 'Rest')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '42%', label: 'API')),
              ],
            ),
            const SizedBox(height: 14),
            for (final layer in layers) ...[
              AirmiusPanel(
                borderColor: layer.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: layer.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: layer.color.withValues(alpha: .45)),
                      ),
                      child: Icon(layer.icon, color: layer.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(layer.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(layer.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(layer.status, color: layer.color), const StatusPill('Core')]),
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
                  const Eyebrow('NAECHSTE VERDRAHTUNG'),
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

class _LayerItem {
  const _LayerItem(this.title, this.status, this.body, this.color, this.icon);

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
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .42))),
            child: const Icon(Icons.build_outlined, color: AirmiusColors.blue, size: 19),
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
