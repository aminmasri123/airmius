class AirmiusReleaseBlocker {
  const AirmiusReleaseBlocker({
    required this.title,
    required this.owner,
    required this.requiredEvidence,
    required this.status,
  });

  final String title;
  final String owner;
  final String requiredEvidence;
  final String status;
}

class AirmiusReleaseBlockers {
  const AirmiusReleaseBlockers._();

  static const items = [
    AirmiusReleaseBlocker(
      title: 'Local Release Prerequisites',
      owner: 'Release',
      requiredEvidence: 'Flutter, Android SDK, adb, cmdline-tools, Android-Lizenzen, Java und Pflichtdateien sind lokal verfügbar',
      status: 'Evidence-Log offen',
    ),
    AirmiusReleaseBlocker(
      title: 'Flutter Analyze',
      owner: 'Mobile',
      requiredEvidence: 'Grüner flutter analyze Lauf',
      status: 'Nicht ausgeführt',
    ),
    AirmiusReleaseBlocker(
      title: 'Flutter Dependency Lock',
      owner: 'Mobile',
      requiredEvidence: 'pubspec.lock enthält flutter_secure_storage nach flutter pub get',
      status: 'Skript vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Android Release Build',
      owner: 'Store',
      requiredEvidence: 'Signiertes oder release-equivalentes AAB',
      status: 'Runbook vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'iOS Release Build',
      owner: 'Store',
      requiredEvidence: 'IPA/TestFlight-fähiger Archive-Build',
      status: 'Runbook vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Domain Verification',
      owner: 'DevOps',
      requiredEvidence: 'Live assetlinks.json und apple-app-site-association ohne Redirect',
      status: 'Runbook vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Finale Screenshots',
      owner: 'Design',
      requiredEvidence: 'Android/iOS Screenshots aus release-equivalenter App',
      status: 'Capture-Plan vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Real API QA',
      owner: 'Backend',
      requiredEvidence: 'Staging/Production API Smoke Test für Kernflüsse',
      status: 'Flutter-Vertrag vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Legal Privacy Approval',
      owner: 'Legal',
      requiredEvidence: 'Freigabe der Store Privacy Labels anhand echter Datenverarbeitung',
      status: 'Drafte vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Localization Visual QA',
      owner: 'Product',
      requiredEvidence: 'DE/EN/FR/AR Kernflows visuell geprüft, inklusive AR RTL',
      status: 'Matrix vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Logo & Theme Parity QA',
      owner: 'Design',
      requiredEvidence: 'Normal nutzt dunkles Logo, Dunkel nutzt weisses Logo, System folgt der Geräte-Helligkeit nach Neustart',
      status: 'Screenshot-Evidence offen',
    ),
    AirmiusReleaseBlocker(
      title: 'Logo Theme Asset Mapping',
      owner: 'Design',
      requiredEvidence: 'Statischer Check bestätigt Logo-Dateien und Normal/Dunkel/System-Mapping',
      status: 'Evidence-Log offen',
    ),
    AirmiusReleaseBlocker(
      title: 'Secure Token Storage QA',
      owner: 'Mobile',
      requiredEvidence: 'Session Restore und Logout auf Android/iOS mit Secure Storage geprüft',
      status: 'Implementierung vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Release Evidence Bundle',
      owner: 'Release',
      requiredEvidence: 'Finales ZIP mit Runbooks, Manifest, Reports, Screenshots und Build-Artefakten',
      status: 'Packaging-Skript vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Manual Evidence Pack',
      owner: 'Release',
      requiredEvidence: 'Manuelle Screenshots, Sign-offs, API-QA-Notizen und Approval-Platzhalter liegen in der Evidence-Struktur',
      status: 'Finaler Go/No-Go-Check vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Store Review Account',
      owner: 'Product',
      requiredEvidence: 'Sicherer Review-Testzugang mit Demo-Daten und Store-Reviewer-Hinweisen',
      status: 'Runbook vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Store Release Notes',
      owner: 'Product',
      requiredEvidence: 'Play/App Store und TestFlight Release Notes ohne Secrets oder unbewiesene Claims',
      status: 'Runbook vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Store Submission Readiness',
      owner: 'Release',
      requiredEvidence: 'Play Console, App Store Connect, TestFlight, Review Account, Privacy Labels und Release Notes sind final abgabebereit',
      status: 'Store-Signoff offen',
    ),
    AirmiusReleaseBlocker(
      title: 'Release Configuration Check',
      owner: 'Mobile',
      requiredEvidence: 'HTTPS API, HTTP-Transport, kein Localhost und Secure-Storage-Voraussetzungen geprüft',
      status: 'Skript vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Release Secrets Hygiene',
      owner: 'Security',
      requiredEvidence: 'Keine Review-Passwörter, Tokens, API Keys oder Bearer-Secrets in Release-Dateien',
      status: 'Skript vorbereitet',
    ),
    AirmiusReleaseBlocker(
      title: 'Release Go/No-Go Decision',
      owner: 'Release',
      requiredEvidence: 'Manifestbasierter Go/No-Go Check zeigt keine pending, failed oder blocked Gates',
      status: 'Skript vorbereitet',
    ),
  ];

  static int get remainingHardGates => items.length;
}
