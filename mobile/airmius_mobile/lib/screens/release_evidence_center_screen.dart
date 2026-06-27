import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ReleaseEvidenceCenterScreen extends StatelessWidget {
  const ReleaseEvidenceCenterScreen({super.key});

  static const _items = [
    _EvidenceItem('Flutter Analyze', 'flutter analyze', 'Terminal/CI-Log mit erfolgreichem Exit-Code.', 'Pending', Icons.manage_search_outlined, AirmiusColors.blue),
    _EvidenceItem('Android AAB', 'flutter build appbundle --release', 'Pfad zu app-release.aab plus Build-Log.', 'Pending', Icons.android_outlined, AirmiusColors.green),
    _EvidenceItem('Android APK', 'flutter build apk --release', 'Pfad zu app-release.apk für Device-Smoke.', 'Pending', Icons.phone_android_outlined, AirmiusColors.green),
    _EvidenceItem('iOS IPA', 'flutter build ipa --release', 'IPA/Archive und TestFlight-faehiger Upload-Nachweis.', 'Pending', Icons.phone_iphone_outlined, AirmiusColors.blue),
    _EvidenceItem('Domain Verification', 'curl /.well-known/...', 'HTTP-Header und gültige JSON-Dateien ohne Redirect.', 'Pending', Icons.domain_verification_outlined, AirmiusColors.amber),
    _EvidenceItem('Screenshots', 'Capture Plan', 'Finale Android/iOS Screenshot-Ordner aus release-equivalenter App.', 'Pending', Icons.photo_library_outlined, AirmiusColors.blue),
    _EvidenceItem('Real API QA', 'Smoke Routes', 'Login, Clubs, Antrag, Rückzug, Notifications, Events, Files, Finance.', 'Pending', Icons.api_outlined, AirmiusColors.green),
    _EvidenceItem('Legal & Localization', 'Owner Sign-off', 'Privacy-Freigabe und DE/EN/FR/AR QA inklusive Arabic RTL.', 'Pending', Icons.gavel_outlined, AirmiusColors.amber),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Release Evidence', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Release Evidence Center',
        subtitle: 'Beweise sammeln, bevor Airmius Mobile wirklich release-ready genannt wird.',
        trailing: const StatusPill('1% Evidence Rest', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('EVIDENCE PACKAGE'),
                  const SizedBox(height: 10),
                  const Text(
                    'Die App ist vorbereitet. Jetzt fehlen die Beweise.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Dieses Center entspricht dem Evidence-Template und zeigt, welche Logs, Artefakte, Screenshots und Freigaben für den finalen Go/No-Go gebraucht werden.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: const [
                      Expanded(child: MetricCard(value: '8', label: 'Evidence Gates')),
                      SizedBox(width: 10),
                      Expanded(child: MetricCard(value: '0', label: 'Erfasst')),
                      SizedBox(width: 10),
                      Expanded(child: MetricCard(value: '8', label: 'Offen')),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in _items) ...[
              _EvidenceCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _EvidenceCard extends StatelessWidget {
  const _EvidenceCard({required this.item});

  final _EvidenceItem item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: item.icon, color: item.color),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(item.status, color: item.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(item.command, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Text(item.evidence, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EvidenceItem {
  const _EvidenceItem(this.title, this.command, this.evidence, this.status, this.icon, this.color);

  final String title;
  final String command;
  final String evidence;
  final String status;
  final IconData icon;
  final Color color;
}
