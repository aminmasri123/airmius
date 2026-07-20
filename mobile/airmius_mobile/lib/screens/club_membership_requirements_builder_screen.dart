import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubMembershipRequirementsBuilderScreen extends StatefulWidget {
  const ClubMembershipRequirementsBuilderScreen({super.key});

  @override
  State<ClubMembershipRequirementsBuilderScreen> createState() => _ClubMembershipRequirementsBuilderScreenState();
}

class _ClubMembershipRequirementsBuilderScreenState extends State<ClubMembershipRequirementsBuilderScreen> {
  String paymentCycle = 'Jaehrlich';
  String paymentMethod = 'Überweisung';
  bool personalRequired = true;
  bool addressRequired = true;
  bool contactRequired = true;
  bool guardianRequired = false;
  bool emergencyRequired = true;
  bool sportDataRequired = false;
  bool paymentRequired = true;
  bool documentsRequired = true;

  @override
  Widget build(BuildContext context) {
    final groups = <_RequirementGroup>[
      _RequirementGroup(
        title: 'Personendaten',
        body: 'Vorname, Nachname, Geburtsdatum, Geschlecht, Nationalitaet und optionale Ausweis-/Lizenzdaten.',
        required: personalRequired,
        onChanged: (value) => setState(() => personalRequired = value),
        icon: Icons.badge_outlined,
        color: AirmiusColors.blue,
      ),
      _RequirementGroup(
        title: 'Wohndaten',
        body: 'Land, Straße, Hausnummer, PLZ, Stadt, Bundesland/Region und optionale abweichende Rechnungsadresse.',
        required: addressRequired,
        onChanged: (value) => setState(() => addressRequired = value),
        icon: Icons.home_outlined,
        color: AirmiusColors.green,
      ),
      _RequirementGroup(
        title: 'Kontaktdaten',
        body: 'E-Mail, Telefon, mobile Nummer, bevorzugter Kontaktkanal und Benachrichtigungszustimmung.',
        required: contactRequired,
        onChanged: (value) => setState(() => contactRequired = value),
        icon: Icons.contact_mail_outlined,
        color: AirmiusColors.amber,
      ),
      _RequirementGroup(
        title: 'Erziehungsberechtigte',
        body: 'Pflicht für Minderjaehrige: Name, E-Mail, Telefon, Zustimmung und Beziehung zum Mitglied.',
        required: guardianRequired,
        onChanged: (value) => setState(() => guardianRequired = value),
        icon: Icons.family_restroom_outlined,
        color: AirmiusColors.pink,
      ),
      _RequirementGroup(
        title: 'Notfallkontakt',
        body: 'Name, Telefonnummer, Beziehung, medizinische Hinweise und wichtige Kontaktanweisungen.',
        required: emergencyRequired,
        onChanged: (value) => setState(() => emergencyRequired = value),
        icon: Icons.health_and_safety_outlined,
        color: AirmiusColors.blue,
      ),
      _RequirementGroup(
        title: 'Sportdaten',
        body: 'Sportart, Teamwunsch, Lizenznummer, Leistungsklasse, Trainingsziel und gesundheitliche Hinweise.',
        required: sportDataRequired,
        onChanged: (value) => setState(() => sportDataRequired = value),
        icon: Icons.workspace_premium_outlined,
        color: AirmiusColors.green,
      ),
      _RequirementGroup(
        title: 'Zahlungsdaten',
        body: 'IBAN, BIC, SEPA-Mandat, Zahlungsart, Zahlungsrhythmus, Beitragsgruppe und Rechnungsadresse.',
        required: paymentRequired,
        onChanged: (value) => setState(() => paymentRequired = value),
        icon: Icons.account_balance_wallet_outlined,
        color: AirmiusColors.amber,
      ),
      _RequirementGroup(
        title: 'Dokumente',
        body: 'Datenschutz, Satzung, Beitrittsbedingungen, Nachweise, Gesundheitsfreigaben und Upload-Pflichten.',
        required: documentsRequired,
        onChanged: (value) => setState(() => documentsRequired = value),
        icon: Icons.description_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Anforderungsbuilder',
      subtitle: 'Mitgliedschaftsformular pro Verein',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('FORMULAR-LOGIK'),
                const SizedBox(height: 8),
                const Text(
                  'Jeder Verein kann mobil bestimmen, welche Daten im Mitgliedsantrag sichtbar, optional oder verpflichtend sind. Die UI ist vorbereitet für spätere API-Schemas pro Verein.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '8', label: 'Gruppen'),
                    Metric(value: '3', label: 'Modi'),
                    Metric(value: '4', label: 'Zyklen'),
                    Metric(value: 'Upload', label: 'Dokumente'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ZAHLUNGSREGEL'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Monatlich', label: Text('Monatlich')),
                    ButtonSegment(value: '4 Monate', label: Text('4 Mon.')),
                    ButtonSegment(value: '6 Monate', label: Text('6 Mon.')),
                    ButtonSegment(value: 'Jaehrlich', label: Text('Jahr')),
                  ],
                  selected: {paymentCycle},
                  onSelectionChanged: (value) => setState(() => paymentCycle = value.first),
                ),
                const SizedBox(height: 14),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Überweisung', label: Text('Überweisung')),
                    ButtonSegment(value: 'Bar', label: Text('Bar')),
                    ButtonSegment(value: 'SEPA', label: Text('SEPA')),
                  ],
                  selected: {paymentMethod},
                  onSelectionChanged: (value) => setState(() => paymentMethod = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final group in groups) ...[
            _RequirementCard(group: group),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktuell: Zahlung $paymentCycle per $paymentMethod. Pflichtbereiche werden im Antrag hervorgehoben, optionale Bereiche bleiben sichtbar, aber ohne Stern.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Formularvorschau öffnen',
                  icon: Icons.preview_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Formularvorschau',
                    body: 'Die mobile Vorschau zeigt später exakt, was der User beim Mitgliedschaftsantrag sieht, bevor der Verein die Regeln aktiviert.',
                    status: 'UI vorbereitet',
                    icon: Icons.preview_outlined,
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

class _RequirementGroup {
  const _RequirementGroup({
    required this.title,
    required this.body,
    required this.required,
    required this.onChanged,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final bool required;
  final ValueChanged<bool> onChanged;
  final IconData icon;
  final Color color;
}

class _RequirementCard extends StatelessWidget {
  const _RequirementCard({required this.group});

  final _RequirementGroup group;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: group.icon, color: group.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(group.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900))),
                    StatusPill(group.required ? 'Pflicht' : 'Optional', color: group.required ? group.color : AirmiusColors.muted),
                  ],
                ),
                const SizedBox(height: 8),
                Text(group.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.4, fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Als Pflichtfeld aktivieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)),
                  value: group.required,
                  activeThumbColor: group.color,
                  onChanged: group.onChanged,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

