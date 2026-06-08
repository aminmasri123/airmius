import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class NativeStoreReleaseAssetsSuiteScreen extends StatefulWidget {
  const NativeStoreReleaseAssetsSuiteScreen({super.key});

  @override
  State<NativeStoreReleaseAssetsSuiteScreen> createState() => _NativeStoreReleaseAssetsSuiteScreenState();
}

class _NativeStoreReleaseAssetsSuiteScreenState extends State<NativeStoreReleaseAssetsSuiteScreen> {
  String _platform = 'Android';
  bool _iconReady = true;
  bool _splashReady = true;
  bool _screenshotsReady = false;
  bool _privacyReady = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Store Release Assets', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Native Store Release Assets',
        subtitle: 'App-Icon, Splash, Screenshots, Store-Texte, Datenschutz und Release-Gates fuer Play Store und App Store.',
        trailing: const StatusPill('Store prep', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('NATIVE RELEASE'),
                  const SizedBox(height: 8),
                  const Text(
                    'Airmius soll wie eine echte App wirken, nicht wie eine verpackte Webseite.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Diese Ansicht sammelt alle sichtbaren Store-Bausteine: Branding, Screenshots, Beschreibungen, Datenschutzlabels, Berechtigungen und finale QA-Schritte.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Android', 'iOS', 'Tablet', 'Review'].map((item) {
                      return ChoiceChip(
                        selected: _platform == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _platform = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _platform == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _platform == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '2', label: 'Stores')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '7', label: 'Assets')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'QA', label: 'Gate')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('ASSET CHECKLIST')),
                      StatusPill(_platform, color: AirmiusColors.blue),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _AssetToggle(
                    icon: Icons.apps_outlined,
                    title: 'Airmius App-Icon',
                    body: 'Logo in Store-Groessen, Adaptive Icon, runde Vorschau und dunkler Hintergrund passend zur Web-App.',
                    value: _iconReady,
                    onChanged: (value) => setState(() => _iconReady = value),
                  ),
                  _AssetToggle(
                    icon: Icons.phone_android_outlined,
                    title: 'Splash Screen',
                    body: 'Kurzer nativer Start mit Airmius-Logo, dunklem Hintergrund und sauberem Uebergang zur App-Shell.',
                    value: _splashReady,
                    onChanged: (value) => setState(() => _splashReady = value),
                  ),
                  _AssetToggle(
                    icon: Icons.image_outlined,
                    title: 'Store Screenshots',
                    body: 'Login, Dashboard, Vereine, Mitgliedsantrag, Chat, Marketplace und Admin-Cockpit als mobile Screens.',
                    value: _screenshotsReady,
                    onChanged: (value) => setState(() => _screenshotsReady = value),
                  ),
                  _AssetToggle(
                    icon: Icons.privacy_tip_outlined,
                    title: 'Datenschutzangaben',
                    body: 'Datenkategorien, Zweckbindung, Konto-Loeschung, Minderjaehrige, Standort, Kamera, Dateien und Push.',
                    value: _privacyReady,
                    onChanged: (value) => setState(() => _privacyReady = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('STORE TEXTE'),
                  const SizedBox(height: 10),
                  const _StoreCopy(title: 'Kurzbeschreibung', body: 'Airmius verbindet Vereine, Mitglieder, Teams, Training, Dateien, Kommunikation und digitale Mitgliedschaft in einer App.'),
                  const _StoreCopy(title: 'Hauptnutzen', body: 'Verein finden, Mitgliedschaft beantragen, Teams organisieren, Nachrichten erhalten, Dokumente teilen und Zahlungen im Blick behalten.'),
                  const _StoreCopy(title: 'Review-Hinweis', body: 'Die App ist native Flutter-UI, keine reine WebView. Laravel wird spaeter per API angebunden.'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('DE'),
                      StatusPill('EN'),
                      StatusPill('FR'),
                      StatusPill('AR / RTL'),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('RELEASE GATES'),
                  const SizedBox(height: 12),
                  const _GateLine(icon: Icons.check_circle_outline, title: 'Mobile Web-App-Optik', body: 'Header, Panels, Buttons, Suche, Bottom-Navigation und Vereinsmodule bleiben im Airmius-Stil.'),
                  const _GateLine(icon: Icons.api_outlined, title: 'API-Vertrag vorbereitet', body: 'Alle Store-relevanten Flows bleiben UI-only und koennen spaeter sauber mit Laravel verbunden werden.'),
                  const _GateLine(icon: Icons.verified_outlined, title: 'Review-Readiness', body: 'Vor Einreichung fehlen noch echte Builds, Signierung, Datenschutzformular, Screenshots und Device-QA.'),
                  const SizedBox(height: 10),
                  AirmiusButton(label: 'Release spaeter mit Build-Daten pruefen', icon: Icons.fact_check_outlined, onPressed: () {}),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AssetToggle extends StatelessWidget {
  const _AssetToggle({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, this.last = false});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
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
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: (value ? AirmiusColors.green : AirmiusColors.amber).withValues(alpha: .14),
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: value ? AirmiusColors.green.withValues(alpha: .42) : AirmiusColors.amber.withValues(alpha: .42)),
            ),
            child: Icon(icon, color: value ? AirmiusColors.green : AirmiusColors.amber),
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
          Switch(value: value, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _StoreCopy extends StatelessWidget {
  const _StoreCopy({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Text(body, style: const TextStyle(color: AirmiusColors.text, height: 1.35)),
          ],
        ),
      ),
    );
  }
}

class _GateLine extends StatelessWidget {
  const _GateLine({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(width: 2),
          Icon(icon, color: AirmiusColors.green),
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
