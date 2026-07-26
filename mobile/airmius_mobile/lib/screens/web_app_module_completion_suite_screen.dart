import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class WebAppModuleCompletionSuiteScreen extends StatelessWidget {
  const WebAppModuleCompletionSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final modules = <_ModuleCompletion>[
      const _ModuleCompletion(
        title: 'Gastseite & Wachstum',
        progress: .86,
        status: 'UI abgedeckt',
        body:
            'Startseite, Blog, Jobs, Preise, Ads, Sponsoren, Marketplace, Lernen, öffentliche Clubseiten und Systemseiten.',
        actions: [
          'Registrieren',
          'Interesse senden',
          'Preisplan ansehen',
          'Content lesen',
        ],
        icon: Icons.travel_explore_outlined,
        color: AirmiusColors.blue,
      ),
      const _ModuleCompletion(
        title: 'Login, Account & Sicherheit',
        progress: .84,
        status: 'UI abgedeckt',
        body:
            'Login, Registrierung, Passwort vergessen, 2FA, Recovery Codes, Geräte, Sessions, API Tokens und Profilschutz.',
        actions: [
          'Einloggen',
          'Token verwalten',
          '2FA prüfen',
          'Session beenden',
        ],
        icon: Icons.lock_person_outlined,
        color: AirmiusColors.amber,
      ),
      const _ModuleCompletion(
        title: 'Vereine, Teams & Mitgliedschaft',
        progress: .9,
        status: 'UI stark',
        body:
            'Vereinssuche, Clubprofil, Beitrittsformular, Status, Rückzug, Rollen, Teams, Regeln, Dokumente und Admin-Kommunikation.',
        actions: [
          'Verein suchen',
          'Anfrage senden',
          'Anfrage zurückziehen',
          'Dateien verknuepfen',
        ],
        icon: Icons.groups_3_outlined,
        color: AirmiusColors.green,
      ),
      const _ModuleCompletion(
        title: 'Feed, Freunde & Kommunikation',
        progress: .82,
        status: 'UI abgedeckt',
        body:
            'Feed, Kommentare, Freunde, Gruppen, Nachrichten, Benachrichtigungen, Dateien, Support und Moderation.',
        actions: [
          'Post erstellen',
          'Nachricht senden',
          'Datei teilen',
          'Meldung prüfen',
        ],
        icon: Icons.dynamic_feed_outlined,
        color: AirmiusColors.pink,
      ),
      const _ModuleCompletion(
        title: 'Sport, Events & Training',
        progress: .8,
        status: 'UI abgedeckt',
        body:
            'Trainingsplaene, Events, Anwesenheit, Fahrgemeinschaften, Wohlbefinden, Kurse, Zertifikate und Badges.',
        actions: [
          'Training planen',
          'Event buchen',
          'Anwesenheit pflegen',
          'Badge anzeigen',
        ],
        icon: Icons.sports_soccer_outlined,
        color: AirmiusColors.blue,
      ),
      const _ModuleCompletion(
        title: 'Admin, Finanzen & Betrieb',
        progress: .78,
        status: 'UI vorbereitet',
        body:
            'Userverwaltung, Verifizierung, Abos, Rechnungen, Zahlungen, Provider, Mails, Plattformsettings und Release-Gates.',
        actions: [
          'User verwalten',
          'Verein prüfen',
          'Rechnung sehen',
          'Release prüfen',
        ],
        icon: Icons.manage_accounts_outlined,
        color: AirmiusColors.amber,
      ),
      const _ModuleCompletion(
        title: 'Mobile Qualitaet & Store',
        progress: .74,
        status: 'UI vorbereitet',
        body:
            'Responsive Shell, Bottom Navigation, Modals, Uploads, Offline-Zustaende, Push, Deep Links, RTL und Store-Readiness.',
        actions: [
          'Offline sehen',
          'Push testen',
          'Modal öffnen',
          'Store Check',
        ],
        icon: Icons.mobile_friendly_outlined,
        color: AirmiusColors.green,
      ),
    ];

    return PageFrame(
      title: 'Module Completion',
      subtitle: 'Komplette Webfunktionen als mobile UI',
      actions: [const AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('WEBVERSION ZU FLUTTER'),
                const SizedBox(height: 8),
                const Text(
                  'Diese Ansicht sammelt die Web-App-Funktionen in mobile Module. Ziel: kein Webbereich soll später fehlen, wenn die Laravel-API angebunden wird.',
                  style: TextStyle(
                    color: AirmiusColors.text,
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: [
                    const Metric(value: '7', label: 'Module'),
                    const Metric(value: '28+', label: 'Flows'),
                    const Metric(value: 'UI', label: 'Phase'),
                    const Metric(value: 'API', label: 'danach'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final module in modules) ...[
            _ModuleCard(module: module),
            const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }
}

class _ModuleCompletion {
  const _ModuleCompletion({
    required this.title,
    required this.progress,
    required this.status,
    required this.body,
    required this.actions,
    required this.icon,
    required this.color,
  });

  final String title;
  final double progress;
  final String status;
  final String body;
  final List<String> actions;
  final IconData icon;
  final Color color;
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.module});

  final _ModuleCompletion module;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: module.icon, color: module.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            module.title,
                            style: const TextStyle(
                              color: AirmiusColors.text,
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        StatusPill(module.status, color: module.color),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      module.body,
                      style: const TextStyle(
                        color: AirmiusColors.muted,
                        height: 1.42,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: module.progress,
              minHeight: 8,
              color: module.color,
              backgroundColor: AirmiusColors.surface2,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final action in module.actions)
                ActionChip(
                  label: Text(action),
                  avatar: Icon(
                    Icons.check_circle_outline,
                    size: 16,
                    color: module.color,
                  ),
                  onPressed: () => openUiAction(
                    context,
                    title: action,
                    body:
                        '$action ist als nativer Flutter-UI-Flow vorbereitet und wartet auf die spätere Laravel-API-Anbindung.',
                    status: 'UI bereit',
                    icon: Icons.check_circle_outline,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
