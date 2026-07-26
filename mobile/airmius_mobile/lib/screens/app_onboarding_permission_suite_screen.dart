import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AppOnboardingPermissionSuiteScreen extends StatefulWidget {
  const AppOnboardingPermissionSuiteScreen({super.key});

  @override
  State<AppOnboardingPermissionSuiteScreen> createState() =>
      _AppOnboardingPermissionSuiteScreenState();
}

class _AppOnboardingPermissionSuiteScreenState
    extends State<AppOnboardingPermissionSuiteScreen> {
  String startRole = 'Mitglied';
  bool notificationPermission = true;
  bool locationPermission = false;
  bool filePermission = true;
  bool cameraPermission = false;
  bool privacyAccepted = true;

  @override
  Widget build(BuildContext context) {
    final steps = [
      const _OnboardingStep(
        title: 'Sprache wählen',
        status: 'DE',
        body:
            'Erststart mit Deutsch, Englisch, Franzoesisch und Arabisch inklusive späterer RTL-Unterstuetzung.',
        icon: Icons.language_outlined,
        color: AirmiusColors.blue,
      ),
      const _OnboardingStep(
        title: 'Rolle bestimmen',
        status: 'Workspace',
        body:
            'Mitglied, Vereinsadmin, Trainer, Guardian oder Plattformadmin bekommen passende Startbereiche.',
        icon: Icons.switch_account_outlined,
        color: AirmiusColors.green,
      ),
      const _OnboardingStep(
        title: 'Berechtigungen prüfen',
        status: 'Native',
        body:
            'Push, Standort, Dateien und Kamera werden mit erklaerendem Kontext abgefragt.',
        icon: Icons.security_outlined,
        color: AirmiusColors.amber,
      ),
      const _OnboardingStep(
        title: 'Datenschutz bestätigen',
        status: 'Consent',
        body:
            'Privacy, Nutzungsregeln, Datenrechte und Profil-Sichtbarkeit werden vor Nutzung erklaert.',
        icon: Icons.privacy_tip_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'App Onboarding',
      subtitle: 'Erststart, Sprache und Berechtigungen',
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
                const SectionLabel('NATIVE START'),
                const SizedBox(height: 8),
                Text(
                  'Eine echte Flutter-App braucht einen sauberen Erststart: Sprache, Rolle, Workspace, Datenschutz und native Berechtigungen werden freundlich erklaert.',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Schritte'),
                    Metric(value: 'Role', label: 'Start'),
                    Metric(value: 'Perm', label: 'Rechte'),
                    Metric(value: 'Store', label: 'Ready'),
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
                const SectionLabel('STARTROLLE'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Mitglied', label: Text('Member')),
                    ButtonSegment(value: 'Verein', label: Text('Club')),
                    ButtonSegment(value: 'Trainer', label: Text('Coach')),
                    ButtonSegment(value: 'Guardian', label: Text('Guardian')),
                  ],
                  selected: {startRole},
                  onSelectionChanged: (value) =>
                      setState(() => startRole = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('BERECHTIGUNGEN'),
                const SizedBox(height: 8),
                _PermissionSwitch(
                  title: 'Push-Benachrichtigungen',
                  body: 'Für Anfragen, Events, Zahlungen, Chat und Support.',
                  value: notificationPermission,
                  color: airmiusAccentColor(context),
                  onChanged: (value) =>
                      setState(() => notificationPermission = value),
                ),
                _PermissionSwitch(
                  title: 'Standort',
                  body: 'Für Orte, Routen, Fahrgemeinschaften und Abholung.',
                  value: locationPermission,
                  color: Theme.of(context).colorScheme.secondary,
                  onChanged: (value) =>
                      setState(() => locationPermission = value),
                ),
                _PermissionSwitch(
                  title: 'Dateien',
                  body: 'Für Dokumente, Nachweise, Uploads und Chat-Anhaenge.',
                  value: filePermission,
                  color: Theme.of(context).colorScheme.tertiary,
                  onChanged: (value) => setState(() => filePermission = value),
                ),
                _PermissionSwitch(
                  title: 'Kamera',
                  body: 'Für Profilbilder, Dokument-Scan, QR-Code und Medien.',
                  value: cameraPermission,
                  color: airmiusAccentColor(context),
                  onChanged: (value) =>
                      setState(() => cameraPermission = value),
                ),
                _PermissionSwitch(
                  title: 'Datenschutz akzeptiert',
                  body:
                      'Consent, Datenrechte und Sichtbarkeitsregeln sind erklaert.',
                  value: privacyAccepted,
                  color: Theme.of(context).colorScheme.secondary,
                  onChanged: (value) => setState(() => privacyAccepted = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final step in steps) ...[
            _StepCard(step: step),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Startrolle: $startRole. Später verbindet die API Profil, Sprache, Workspace, Consent, Device Token und native Berechtigungszustaende.',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Onboarding starten',
                  icon: Icons.phone_iphone_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Onboarding starten',
                    body:
                        'Diese UI bereitet Erststart, Sprache, Rollenwahl, Workspace, Datenschutz und native Berechtigungen für die spätere App/API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.phone_iphone_outlined,
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

class _OnboardingStep {
  const _OnboardingStep({
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

class _PermissionSwitch extends StatelessWidget {
  const _PermissionSwitch({
    required this.title,
    required this.body,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final String body;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final permissionColor = airmiusSemanticColor(context, color);
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(
        title,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w900,
        ),
      ),
      subtitle: Text(
        body,
        style: TextStyle(
          color: airmiusMutedColor(context),
          height: 1.35,
          fontWeight: FontWeight.w700,
        ),
      ),
      value: value,
      activeThumbColor: permissionColor,
      onChanged: onChanged,
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.step});

  final _OnboardingStep step;

  @override
  Widget build(BuildContext context) {
    final stepColor = airmiusSemanticColor(context, step.color);
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: step.icon, color: stepColor),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        step.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(step.status, color: stepColor),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  step.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.42,
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
