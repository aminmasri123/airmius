import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class WebAppFullConversionControlScreen extends StatelessWidget {
  const WebAppFullConversionControlScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final sections = <_ConversionSection>[
      const _ConversionSection(
        title: 'Gastbereich',
        status: 'UI bereit',
        body: 'Landing, Preise, Jobs, Ads, Blog, Sponsoren, Marketplace, Lernen und oeffentliche Systemseiten.',
        icon: Icons.public_outlined,
        color: AirmiusColors.blue,
      ),
      const _ConversionSection(
        title: 'Auth & Konto',
        status: 'UI bereit',
        body: 'Login, Registrierung, Recovery, 2FA, Sessions, API Tokens, Profil, Sicherheit und Kontoeinstellungen.',
        icon: Icons.verified_user_outlined,
        color: AirmiusColors.amber,
      ),
      const _ConversionSection(
        title: 'Vereine & Mitgliedschaft',
        status: 'UI bereit',
        body: 'Vereinsprofil, Beitrittsformular, Status, Rueckzug, Rollen, Teams, Dateien, Regeln und Admin-Freigaben.',
        icon: Icons.groups_2_outlined,
        color: AirmiusColors.green,
      ),
      const _ConversionSection(
        title: 'Community & Kommunikation',
        status: 'UI bereit',
        body: 'Feed, Freunde, Gruppen, Nachrichten, Benachrichtigungen, Dateiablage, Support und Moderation.',
        icon: Icons.forum_outlined,
        color: AirmiusColors.pink,
      ),
      const _ConversionSection(
        title: 'Sport, Training & Lernen',
        status: 'UI bereit',
        body: 'Trainingsplaene, Events, Anwesenheit, Wohlbefinden, Kurse, Zertifikate, Badges und Gamification.',
        icon: Icons.fitness_center_outlined,
        color: AirmiusColors.blue,
      ),
      const _ConversionSection(
        title: 'Admin & Betrieb',
        status: 'UI bereit',
        body: 'Userverwaltung, Verifizierung, Finanzen, Abos, Provider, Mail, Plattformsettings und Release Readiness.',
        icon: Icons.admin_panel_settings_outlined,
        color: AirmiusColors.amber,
      ),
      const _ConversionSection(
        title: 'Mobile Qualitaet',
        status: 'Vorbereitet',
        body: 'Navigation, Tabellenaktionen, Modals, Uploads, Offline Cache, Push, Maps, RTL, Accessibility und Store-QA.',
        icon: Icons.phone_iphone_outlined,
        color: AirmiusColors.green,
      ),
    ];

    return PageFrame(
      title: 'Web-App Conversion',
      subtitle: 'Komplette mobile Struktur der Webversion',
      actions: [const AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ZIELBILD'),
                SizedBox(height: 8),
                Text(
                  'Die Flutter-App soll sich wie die mobile Web-App anfuehlen: gleiche Bereiche, gleiche Sprache, gleiche dunkle Airmius-Optik, aber mit nativen mobilen Interaktionen.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          GridWrap(
            children: [
              const Metric(value: '7', label: 'Bereiche'),
              const Metric(value: '123', label: 'Ops'),
              const Metric(value: '4', label: 'Sprachen'),
              const Metric(value: 'API', label: 'naechster Schritt'),
            ],
          ),
          const SizedBox(height: 14),
          for (final section in sections) ...[
            _SectionCard(section: section),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('NAECHSTER TECHNISCHER SCHRITT'),
                const SizedBox(height: 8),
                const Text(
                  'Nach der UI-Paritaet wird die App an Laravel angebunden: Auth, User, Clubs, Mitgliedsantraege, Dateien, Zahlungen und Benachrichtigungen laufen dann ueber echte API-Endpunkte.',
                  style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'API-Anbindung vorbereiten',
                  icon: Icons.api_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'API-Anbindung',
                    body: 'Als naechstes werden Laravel-Endpunkte, Auth-Tokens, DTOs, Fehlerzustaende und Ladezustaende systematisch mit der Flutter-App verbunden.',
                    status: 'Plan bereit',
                    icon: Icons.api_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ConversionSection {
  const _ConversionSection({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.section});

  final _ConversionSection section;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: section.icon, color: section.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(section.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(section.status, color: section.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(section.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
