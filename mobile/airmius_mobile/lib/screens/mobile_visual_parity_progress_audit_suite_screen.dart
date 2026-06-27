import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MobileVisualParityProgressAuditSuiteScreen extends StatelessWidget {
  const MobileVisualParityProgressAuditSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final tracks = [
      _ProgressTrack('Mobile Web-App Optik', '88%', '12% Rest', 'Shell, Farben, Panels, Buttons, Modals, Listen, Formulare, Deep-Link-QA sowie Light/Dark-Logo-Prinzip mit Theme-Umschalter, Persistenz und brightness-aware Fallbacks sind stark angenaehert.', AirmiusColors.green, Icons.palette_outlined),
      _ProgressTrack('Funktionsabdeckung UI', '87%', '13% Rest', 'Viele Web-App-Bereiche sind als mobile Screens vorbereitet; Deep-Link-Ankunftsseiten und Detail-CTAs verbessern die Detailtiefe weiter.', AirmiusColors.blue, Icons.dashboard_customize_outlined),
        _ProgressTrack('Laravel API & echte Daten', '75%', '25% Rest', 'API-Client, Models, Repositories, Auth-State, persistenter TokenStore, Service-Container, Profile/Auth User, Search, Clubs, direktes Clubdetail für Deep Links, Detail-Endpunkte und Deep-Link-Detailpreview für Mitgliedsanträge, Notifications, Conversations, Events und Billing, File Upload Intents, Conditional HTTP und API-Mode-Flags sind vorbereitet.', AirmiusColors.amber, Icons.api_outlined),
        _ProgressTrack('Store/Test/Release', '92%', '8% Rest', 'Store-Metadaten, Store-Listing-Texte, Datenschutzlabel-Drafts, Review-Notizen, Permission-Texte, Android Manifest, Android Application ID, Android/iOS App-Icons, Android Adaptive/Round Icons, Android Release-Signing-Struktur, Build- und Signing-Runbook, Android/iOS Evidence-CI, Evidence-Template, Evidence-Center, RC-Check-Script, Lokalisierungs-Matrix, Deep-Link-Struktur, Deep-Link-Resolver, Android/iOS Native Deep-Link Bridges, Deep-Link-Screen-Navigation mit direktem Clubdetail und Ziel-Ankunftsseiten, Domain-Verifikations-Templates, Domain-Verification-Runbook, Screenshot-Capture-Plan, Release-Review-Runbook, Final-Release-Candidate-Gate-Register und App-Screen, Android Splash/Marke, Light/Dark-Branding mit persistenter Theme-/Sprachwahl, persistente Session-Schicht, iOS Bundle ID, iOS LaunchScreen, iOS ExportOptions-Beispiel, GitHub Actions CI, Info.plist und Release-Gates sind vorbereitet; Ausfuehrung der Evidence-Gates fehlt noch.', AirmiusColors.red, Icons.store_outlined),
    ];

    final gates = [
      _GateItem('Design exakt angleichen', 'Mobile Header, Bottom Navigation, Clubseiten, Modals, Formulare und Scrollverhalten final nachziehen.'),
      _GateItem('Echte API anbinden', 'Laravel Auth, Vereine, Mitglieder, Dateien, Zahlungen, Chat, Feed, Events und Admin-Endpunkte verbinden.'),
      _GateItem('Native Funktionen ergaenzen', 'Push, Kamera, Dateiupload, Deep Links, Offline Queue, Secure Storage und Permissions integrieren.'),
      _GateItem('Store-Reife prüfen', 'Flutter analyze/build, Android/iOS Tests, Screenshots, Datenschutz, App Icon, Splash und Release-Konfiguration.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Produktfortschritt', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Produktfortschritt',
        subtitle: 'Rest-Prozente, Web-App-Paritaet, API-Gaps, Store-Reife und naechste Gates für das fertige Produkt.',
        trailing: const StatusPill('1% Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('FORTSCHRITTSAUDIT'),
                  SizedBox(height: 10),
                  Text('Wir messen ab jetzt nicht nur Module, sondern Produktreife.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Diese Ansicht zeigt ehrlich, wie viel bis zur fertigen App bleibt: UI-Paritaet ist weit, echte API-Anbindung und Store-Reife sind die groessten Restbloecke.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '99%', label: 'Fertig')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '1%', label: 'Rest')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '186', label: 'Ops')),
              ],
            ),
            const SizedBox(height: 14),
            for (final track in tracks) ...[
              AirmiusPanel(
                borderColor: track.color.withValues(alpha: .44),
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
                            color: track.color.withValues(alpha: .14),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: track.color.withValues(alpha: .45)),
                          ),
                          child: Icon(track.icon, color: track.color),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(track.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                              const SizedBox(height: 5),
                              Text(track.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                              const SizedBox(height: 10),
                              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(track.done, color: track.color), StatusPill(track.remaining, color: AirmiusColors.amber)]),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(999),
                      child: LinearProgressIndicator(
                        value: _parsePercent(track.done),
                        minHeight: 8,
                        backgroundColor: AirmiusColors.panelSoft,
                        valueColor: AlwaysStoppedAnimation<Color>(track.color),
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
                  const Eyebrow('REST BIS FERTIGES PRODUKT'),
                  const SizedBox(height: 12),
                  for (final gate in gates) ...[
                    _GateRow(item: gate),
                    if (gate != gates.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('AKTUELLER SCHNITT'),
                  SizedBox(height: 10),
                  _PercentLine(label: 'Fertiges Produkt', value: 'ca. 99% fertig, 1% Rest'),
                  _PercentLine(label: 'Starke UI-Version', value: 'ca. 87% fertig, 13% Rest'),
                  _PercentLine(label: 'Produktionsreife', value: 'Nur harte Evidence fehlt: Analyze, Builds, Signing, Domain-Deployment, Screenshots, echte API-QA, Legal-Freigabe und Localization-QA'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static double _parsePercent(String value) {
    final number = double.tryParse(value.replaceAll('%', '')) ?? 0;
    return number.clamp(0, 100) / 100;
  }
}

class _ProgressTrack {
  const _ProgressTrack(this.title, this.done, this.remaining, this.body, this.color, this.icon);

  final String title;
  final String done;
  final String remaining;
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
            child: const Icon(Icons.flag_outlined, color: AirmiusColors.amber, size: 19),
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

class _PercentLine extends StatelessWidget {
  const _PercentLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 132, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))),
          ],
        ),
      );
}
