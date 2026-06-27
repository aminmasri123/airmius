class AirmiusStoreReleaseConfig {
  const AirmiusStoreReleaseConfig({
    required this.appName,
    required this.bundleId,
    required this.shortDescription,
    required this.longDescription,
    required this.supportEmail,
    required this.privacyUrl,
    required this.permissionJustifications,
    required this.releaseGates,
  });

  final String appName;
  final String bundleId;
  final String shortDescription;
  final String longDescription;
  final String supportEmail;
  final String privacyUrl;
  final List<AirmiusPermissionJustification> permissionJustifications;
  final List<AirmiusReleaseGate> releaseGates;
}

class AirmiusPermissionJustification {
  const AirmiusPermissionJustification({required this.permission, required this.reason, required this.storeText});

  final String permission;
  final String reason;
  final String storeText;
}

class AirmiusReleaseGate {
  const AirmiusReleaseGate({required this.title, required this.owner, required this.status, required this.remainingWork});

  final String title;
  final String owner;
  final String status;
  final String remainingWork;
}

const airmiusStoreReleaseConfig = AirmiusStoreReleaseConfig(
  appName: 'Airmius',
  bundleId: 'com.airmius.app',
  shortDescription: 'Vereine, Teams, Mitglieder und Sport in einer mobilen App.',
  longDescription: 'Airmius verbindet Vereinsverwaltung, Mitgliedschaftsantraege, Teams, Events, Dateien, Kommunikation, Zahlungen, Sport, Lernen, Sponsoren und Community in einer mobilen App im Stil der Web-App.',
  supportEmail: 'support@airmius.de',
  privacyUrl: 'https://airmius.de/datenschutz',
  permissionJustifications: [
    AirmiusPermissionJustification(permission: 'Push Notifications', reason: 'Mitglieder erhalten Updates zu Anfragen, Events, Zahlungen, Chat und Vereinsentscheidungen.', storeText: 'Benachrichtigungen fuer Vereinsupdates, Nachrichten und Termine.'),
    AirmiusPermissionJustification(permission: 'Camera', reason: 'Dateiuploads, QR-Check-in, Mitgliedskarte, Dokumente und Nachweise koennen fotografiert oder gescannt werden.', storeText: 'Kamera fuer QR-Codes, Uploads und Nachweise.'),
    AirmiusPermissionJustification(permission: 'Photos / Files', reason: 'Vereinsdokumente, Antragsunterlagen, Chat-Anhaenge und Medien koennen hochgeladen werden.', storeText: 'Dateien und Bilder fuer Vereinsdokumente und Anhaenge.'),
    AirmiusPermissionJustification(permission: 'Location', reason: 'Sportkarte, Trainingsorte, Event-Anfahrt und Fahrgemeinschaften koennen Standorte nutzen.', storeText: 'Standort fuer Sportkarte, Events und Anfahrt.'),
  ],
  releaseGates: [
    AirmiusReleaseGate(title: 'Local Release Prerequisites', owner: 'Release', status: 'Skript vorbereitet', remainingWork: 'Flutter, Android SDK, adb, cmdline-tools, Android-Lizenzen, Java und Pflichtdateien als Evidence-Log beweisen.'),
    AirmiusReleaseGate(title: 'Branding & Theme', owner: 'Design', status: 'Persistenz vorbereitet', remainingWork: 'Finale visuelle Prüfung auf Testgeraeten und spaetere Storage-Haertung nachziehen.'),
    AirmiusReleaseGate(title: 'Logo & Theme Parity', owner: 'Design', status: 'QA-Gate vorbereitet', remainingWork: 'Normal mit dunklem Logo, Dunkel mit weissem Logo und System mit Geraete-Helligkeit per Screenshot nach Neustart beweisen.'),
    AirmiusReleaseGate(title: 'Logo Theme Asset Mapping', owner: 'Design', status: 'Skript vorbereitet', remainingWork: 'Statisches Logo-Asset- und Mapping-Log aus lokalem RC-Lauf oder CI als Evidence sichern.'),
    AirmiusReleaseGate(title: 'Auth Token Store', owner: 'Mobile', status: 'Persistenz vorbereitet', remainingWork: 'Flutter Secure Storage oder Keychain/Keystore-Provider fuer Release-Sicherheit final einsetzen.'),
    AirmiusReleaseGate(title: 'Lokalisierung', owner: 'Product', status: 'Release-Matrix vorbereitet', remainingWork: 'Public/member-facing Screens final uebersetzen, Arabisch RTL pruefen und Screenshot-Sprache bestaetigen.'),
    AirmiusReleaseGate(title: 'Flutter Analyze', owner: 'Mobile', status: 'Runbook vorbereitet', remainingWork: 'Analyse lokal oder in GitHub Actions ausfuehren und Compilerfehler beheben.'),
    AirmiusReleaseGate(title: 'Android Build', owner: 'Store', status: 'Build-Runbook vorbereitet', remainingWork: 'Android SDK/Lizenzen, echten Keystore hinterlegen und Release-Build ausfuehren.'),
    AirmiusReleaseGate(title: 'iOS Build', owner: 'Store', status: 'macOS Evidence-CI vorbereitet', remainingWork: 'Apple Developer Account, echtes Signing, Capabilities und TestFlight-Build ausfuehren.'),
    AirmiusReleaseGate(title: 'Datenschutzlabels', owner: 'Legal', status: 'Store-Drafts vorbereitet', remainingWork: 'Datenarten final anhand echter API, Tracking-Funktionen und Rechtsfreigabe bestaetigen.'),
    AirmiusReleaseGate(title: 'CI Pipeline', owner: 'DevOps', status: 'Vorbereitet', remainingWork: 'GitHub Actions mit echten Secrets, Signing und Store-Builds ausfuehren.'),
    AirmiusReleaseGate(title: 'Store Listing', owner: 'Product', status: 'Review-Runbook vorbereitet', remainingWork: 'Finale URLs, Review-Account und Freigabe vor Submission bestaetigen.'),
    AirmiusReleaseGate(title: 'Deep Links', owner: 'Mobile', status: 'Domain-Runbook vorbereitet', remainingWork: 'Release-Fingerprint, Apple Team ID, Domain-Dateien deployen und Navigation final pruefen.'),
    AirmiusReleaseGate(title: 'Screenshots', owner: 'Design', status: 'Capture-Plan vorbereitet', remainingWork: 'Mobile Web-App-nahe Screens fuer Android und iOS mit finalem Build erzeugen.'),
    AirmiusReleaseGate(title: 'Manual Evidence Pack', owner: 'Release', status: 'Final-Gate vorbereitet', remainingWork: 'Manual Evidence Pack erzeugen, Screenshots/Logs/Sign-offs einlegen und im finalen Go/No-Go als Log/Manifest-Gate beweisen.'),
    AirmiusReleaseGate(title: 'Store Submission Readiness', owner: 'Release', status: 'Runbook vorbereitet', remainingWork: 'Play Console, App Store Connect, TestFlight, Review Account, Privacy Labels und Release Notes nach finalem Go/No-Go abgabebereit signieren.'),
    AirmiusReleaseGate(title: 'Release Candidate Audit', owner: 'Product', status: 'Evidence-Paket vorbereitet', remainingWork: 'Alle harten Gates mit Build-, QA-, Signing-, Domain- und Store-Evidence ausfuehren und abschliessen.'),
  ],
);
