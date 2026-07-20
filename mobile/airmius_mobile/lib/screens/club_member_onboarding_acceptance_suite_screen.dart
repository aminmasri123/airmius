import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubMemberOnboardingAcceptanceSuiteScreen extends StatefulWidget {
  const ClubMemberOnboardingAcceptanceSuiteScreen({super.key});

  @override
  State<ClubMemberOnboardingAcceptanceSuiteScreen> createState() => _ClubMemberOnboardingAcceptanceSuiteScreenState();
}

class _ClubMemberOnboardingAcceptanceSuiteScreenState extends State<ClubMemberOnboardingAcceptanceSuiteScreen> {
  bool welcomeMessage = true;
  bool assignTeam = true;
  bool activatePayment = true;
  bool createMemberCard = true;

  @override
  Widget build(BuildContext context) {
    final steps = [
      _OnboardingStep(
        title: 'Mitgliedschaft bestätigen',
        body: 'Admin akzeptiert die Anfrage und erzeugt den Mitgliedsstatus mit Startdatum, Beitragsgruppe und Rolle.',
        done: true,
        icon: Icons.check_circle_outline,
        color: AirmiusColors.green,
      ),
      _OnboardingStep(
        title: 'Willkommensnachricht senden',
        body: 'User erhaelt eine In-App-Nachricht mit naechsten Schritten, Ansprechpartnern und Vereinsregeln.',
        done: welcomeMessage,
        icon: Icons.mail_outline,
        color: AirmiusColors.blue,
      ),
      _OnboardingStep(
        title: 'Team oder Gruppe zuweisen',
        body: 'Neue Mitglieder können direkt einem Team, Trainingsbereich oder einer Warteliste zugeordnet werden.',
        done: assignTeam,
        icon: Icons.people_outline,
        color: AirmiusColors.amber,
      ),
      _OnboardingStep(
        title: 'Zahlungsstart vorbereiten',
        body: 'Beitragsrhythmus, Zahlungsart, SEPA-Status und erste Rechnung werden für die API-Phase vorbereitet.',
        done: activatePayment,
        icon: Icons.payments_outlined,
        color: AirmiusColors.pink,
      ),
      _OnboardingStep(
        title: 'Mitgliedskarte aktivieren',
        body: 'Digitale Karte, QR-Code, Rollen, Badges und Sichtbarkeit im Verein werden mobil vorbereitet.',
        done: createMemberCard,
        icon: Icons.badge_outlined,
        color: AirmiusColors.green,
      ),
    ];

    return PageFrame(
      title: 'Member Onboarding',
      subtitle: 'Nach Annahme der Anfrage',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ANNAHME-FLOW'),
                const SizedBox(height: 8),
                const Text(
                  'Nach der Annahme darf der Prozess nicht abbrechen: Mitgliedsstatus, Willkommenskommunikation, Teamzuweisung, Zahlung und Mitgliedskarte werden als mobile Schritte vorbereitet.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '5', label: 'Schritte'),
                    Metric(value: 'Team', label: 'Zuweisung'),
                    Metric(value: 'Pay', label: 'Start'),
                    Metric(value: 'Card', label: 'Mitglied'),
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
                const SectionLabel('AUTOMATIONEN'),
                const SizedBox(height: 8),
                _ToggleRow(
                  title: 'Willkommensnachricht',
                  value: welcomeMessage,
                  color: AirmiusColors.blue,
                  onChanged: (value) => setState(() => welcomeMessage = value),
                ),
                _ToggleRow(
                  title: 'Teamzuweisung',
                  value: assignTeam,
                  color: AirmiusColors.amber,
                  onChanged: (value) => setState(() => assignTeam = value),
                ),
                _ToggleRow(
                  title: 'Zahlungsstart',
                  value: activatePayment,
                  color: AirmiusColors.pink,
                  onChanged: (value) => setState(() => activatePayment = value),
                ),
                _ToggleRow(
                  title: 'Mitgliedskarte',
                  value: createMemberCard,
                  color: AirmiusColors.green,
                  onChanged: (value) => setState(() => createMemberCard = value),
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
                const SectionLabel('USER-SICHT'),
                const SizedBox(height: 8),
                const Text(
                  'Der User sieht später nicht nur "angenommen", sondern konkrete naechste Schritte: Willkommen, Dokumentstatus, Zahlungsinfo, Team, Ansprechpartner und digitale Mitgliedskarte.',
                  style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'User-Onboarding anzeigen',
                  icon: Icons.person_outline,
                  onPressed: () => openUiAction(
                    context,
                    title: 'User-Onboarding',
                    body: 'Diese UI bereitet die mobile Ansicht für angenommene Mitglieder vor: Status, Aufgaben, Zahlungsinfo, Team und Willkommenskommunikation.',
                    status: 'UI vorbereitet',
                    icon: Icons.person_outline,
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
    required this.body,
    required this.done,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final bool done;
  final IconData icon;
  final Color color;
}

class _ToggleRow extends StatelessWidget {
  const _ToggleRow({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeThumbColor: color,
      onChanged: onChanged,
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.step});

  final _OnboardingStep step;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: step.icon, color: step.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(step.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900))),
                    StatusPill(step.done ? 'Aktiv' : 'Aus', color: step.done ? step.color : AirmiusColors.muted),
                  ],
                ),
                const SizedBox(height: 8),
                Text(step.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.4, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
