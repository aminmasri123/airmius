import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubVisibilityRulesSuiteScreen extends StatefulWidget {
  const ClubVisibilityRulesSuiteScreen({super.key});

  @override
  State<ClubVisibilityRulesSuiteScreen> createState() =>
      _ClubVisibilityRulesSuiteScreenState();
}

class _ClubVisibilityRulesSuiteScreenState
    extends State<ClubVisibilityRulesSuiteScreen> {
  bool publicProfile = true;
  bool showMembers = false;
  bool showTeams = true;
  bool showFees = true;
  bool requirePrivacyConsent = true;
  bool requireClubRules = true;
  bool allowDocumentUpload = true;
  bool notifyAdmins = true;

  @override
  Widget build(BuildContext context) {
    final rows = [
      _RuleRow(
        title: 'Öffentliches Vereinsprofil',
        body:
            'Name, Ort, Logo, Beschreibung, Kontakt und Mitgliedschaftsstatus für Besucher sichtbar machen.',
        value: publicProfile,
        onChanged: (value) => setState(() => publicProfile = value),
        icon: Icons.public_outlined,
        color: AirmiusColors.blue,
      ),
      _RuleRow(
        title: 'Mitgliederliste anzeigen',
        body:
            'Verein entscheidet, ob Mitglieder im Profil sichtbar sind oder nur intern angezeigt werden.',
        value: showMembers,
        onChanged: (value) => setState(() => showMembers = value),
        icon: Icons.people_alt_outlined,
        color: AirmiusColors.green,
      ),
      _RuleRow(
        title: 'Teams anzeigen',
        body:
            'Teams, Trainingsgruppen und Rollen können auf der Clubseite sichtbar oder verborgen werden.',
        value: showTeams,
        onChanged: (value) => setState(() => showTeams = value),
        icon: Icons.groups_2_outlined,
        color: AirmiusColors.amber,
      ),
      _RuleRow(
        title: 'Beitragsregeln anzeigen',
        body:
            'Monatlich, vierteljährlich, halbjährlich, jährlich, bar oder Überweisung als sichtbare Optionen.',
        value: showFees,
        onChanged: (value) => setState(() => showFees = value),
        icon: Icons.payments_outlined,
        color: AirmiusColors.pink,
      ),
      _RuleRow(
        title: 'Datenschutz bestätigen',
        body:
            'Mitgliedsanträge müssen Datenschutzdokumente lesen und aktiv bestätigen.',
        value: requirePrivacyConsent,
        onChanged: (value) => setState(() => requirePrivacyConsent = value),
        icon: Icons.privacy_tip_outlined,
        color: AirmiusColors.blue,
      ),
      _RuleRow(
        title: 'Vereinsregeln verknüpfen',
        body:
            'Satzung, Hausordnung, Trainingsregeln oder Teilnahmebedingungen als Link oder Upload verbinden.',
        value: requireClubRules,
        onChanged: (value) => setState(() => requireClubRules = value),
        icon: Icons.rule_folder_outlined,
        color: AirmiusColors.green,
      ),
      _RuleRow(
        title: 'Dokument-Upload erlauben',
        body:
            'PDF, Bild oder Nachweis wird mobil hochgeladen und später automatisch dem Vereins-Dateimanager zugeordnet.',
        value: allowDocumentUpload,
        onChanged: (value) => setState(() => allowDocumentUpload = value),
        icon: Icons.upload_file_outlined,
        color: AirmiusColors.amber,
      ),
      _RuleRow(
        title: 'Admins informieren',
        body:
            'Neue Anfragen, Rückzüge, Dokumente und Formularänderungen erzeugen sichtbare Vereinsbenachrichtigungen.',
        value: notifyAdmins,
        onChanged: (value) => setState(() => notifyAdmins = value),
        icon: Icons.notifications_active_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Vereinsregeln',
      subtitle: 'Sichtbarkeit, Formulare und Dokumente',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('CLUB CONTROL'),
                const SizedBox(height: 8),
                Text(
                  'Vereine bekommen eine mobile Steuerzentrale: Was ist öffentlich, welche Daten sind Pflicht, welche Regeln müssen bestätigt werden und welche Dokumente dürfen hochgeladen werden?',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '8', label: 'Regeln'),
                    Metric(value: 'Upload', label: 'Dokumente'),
                    Metric(value: 'Form', label: 'Felder'),
                    Metric(value: 'Admin', label: 'Info'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final row in rows) ...[
            _RuleCard(row: row),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('DATEIMANAGER-LOGIK'),
                const SizedBox(height: 8),
                Text(
                  'Hochgeladene Vereinsdokumente sollen später automatisch im Dateimanager des Vereins landen, mit Kategorie, Sichtbarkeit, Gültigkeit, Version und Zustimmungspflicht.',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(
                      'Datenschutz',
                      color: airmiusSemanticColor(context, AirmiusColors.blue),
                    ),
                    StatusPill(
                      'Satzung',
                      color: airmiusSemanticColor(context, AirmiusColors.green),
                    ),
                    StatusPill(
                      'Beiträge',
                      color: airmiusSemanticColor(context, AirmiusColors.amber),
                    ),
                    StatusPill(
                      'Nachweise',
                      color: airmiusSemanticColor(context, AirmiusColors.pink),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RuleRow {
  const _RuleRow({
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final IconData icon;
  final Color color;
}

class _RuleCard extends StatelessWidget {
  const _RuleCard({required this.row});

  final _RuleRow row;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(
            icon: row.icon,
            color: airmiusSemanticColor(context, row.color),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        row.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    Switch.adaptive(
                      value: row.value,
                      activeThumbColor: airmiusSemanticColor(
                        context,
                        row.color,
                      ),
                      onChanged: row.onChanged,
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  row.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.4,
                    fontWeight: FontWeight.w700,
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
