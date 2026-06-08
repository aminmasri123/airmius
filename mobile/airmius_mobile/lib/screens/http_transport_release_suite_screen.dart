import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class HttpTransportReleaseSuiteScreen extends StatelessWidget {
  const HttpTransportReleaseSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final transports = [
      _TransportItem('Web Transport', 'Browser', 'Chrome/Web nutzt Browser-HTTP mit Headers, JSON-Body und Response-Header-Mapping.', AirmiusColors.blue, Icons.public_outlined),
      _TransportItem('IO Transport', 'Mobile/Desktop', 'Android, iOS, Windows, macOS und Linux nutzen Dart HttpClient ohne neue Packages.', AirmiusColors.green, Icons.phone_iphone_outlined),
      _TransportItem('Stub Transport', 'Fallback', 'Nicht unterstuetzte Plattformen bekommen klare 501-Response statt stiller Fehler.', AirmiusColors.amber, Icons.warning_amber_outlined),
      _TransportItem('Static Transport', 'Demo/Test', 'Lokale Demo-Flows bleiben ohne Backend nutzbar und koennen spaeter Contract-Tests stuetzen.', AirmiusColors.pink, Icons.science_outlined),
    ];

    final gates = [
      _GateItem('Laravel Base URL setzen', 'AirmiusApp kann jetzt per AIRMIUS_USE_HTTP und AIRMIUS_API_BASE_URL auf echten HTTP-Transport wechseln.'),
      _GateItem('CORS/Auth finalisieren', 'Laravel muss Mobile/Web-Origin, Bearer Tokens, JSON Errors und Sanctum/API-Strategie erlauben.'),
      _GateItem('Screen-Migration fortsetzen', 'Notifications, Events, Billing, Profil und Dateien Schritt fuer Schritt auf HTTP-Repositories umstellen.'),
      _GateItem('Build pruefen', 'Conditional Imports muessen mit Flutter Web und Android/iOS Build analysiert werden, sobald du Build freigibst.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('HTTP Transport', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'HTTP Transport',
        subtitle: 'Conditional HTTP transport for Web, Mobile/Desktop, fallback and demo/static flows.',
        trailing: const StatusPill('44% Rest', color: AirmiusColors.amber),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('NETWORK CORE'),
            SizedBox(height: 10),
            Text('Die App kann jetzt echte Requests bekommen, ohne neue Pakete zu brauchen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
            SizedBox(height: 8),
            Text('AirmiusHttpTransport nutzt Conditional Imports: Web bekommt Browser-HTTP, Mobile/Desktop bekommt Dart IO, StaticTransport bleibt fuer Demo und Tests erhalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '57%', label: 'Fertig')), SizedBox(width: 10), Expanded(child: MetricCard(value: '43%', label: 'Rest')), SizedBox(width: 10), Expanded(child: MetricCard(value: '56%', label: 'API'))]),
          const SizedBox(height: 14),
          for (final item in transports) ...[
            AirmiusPanel(borderColor: item.color.withValues(alpha: .44), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(width: 50, height: 50, decoration: BoxDecoration(color: item.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                const SizedBox(height: 5),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 10),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.status, color: item.color), const StatusPill('Transport')]),
              ])),
            ])),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('NAECHSTE RELEASE-GATES'),
            const SizedBox(height: 12),
            for (final gate in gates) ...[
              _GateRow(item: gate),
              if (gate != gates.last) const SizedBox(height: 10),
            ],
          ])),
        ]),
      ),
    );
  }
}

class _TransportItem {
  const _TransportItem(this.title, this.status, this.body, this.color, this.icon);

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
  Widget build(BuildContext context) => Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(width: 34, height: 34, alignment: Alignment.center, decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .42))), child: const Icon(Icons.cloud_sync_outlined, color: AirmiusColors.blue, size: 19)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        ])),
      ]);
}
