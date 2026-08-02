import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubSurveyPollVotingSuiteScreen extends StatelessWidget {
  const ClubSurveyPollVotingSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final pollTypes = [
      _PollType(
        'Mitgliederfeedback',
        'Anonym möglich',
        'Zufriedenheit, Trainingszeiten, Vereinsleben',
        AirmiusColors.blue,
        Icons.rate_review_outlined,
      ),
      _PollType(
        'Event-Abstimmung',
        'Schnell',
        'Terminfindung, Helferbedarf, Essensauswahl',
        AirmiusColors.green,
        Icons.event_available_outlined,
      ),
      _PollType(
        'Vereinsentscheidung',
        'Verbindlich',
        'Quorum, Stimmberechtigung, Ergebnisprotokoll',
        AirmiusColors.amber,
        Icons.how_to_vote_outlined,
      ),
      _PollType(
        'Team-Check',
        'Trainer',
        'Belastung, Verfügbarkeit, Stimmung, Rückmeldung',
        AirmiusColors.pink,
        Icons.groups_2_outlined,
      ),
    ];

    final workflow = [
      _WorkflowStep(
        '1',
        'Zielgruppe wählen',
        'Verein, Team, Rolle, Mitgliederstatus oder eingeladene Kontakte.',
      ),
      _WorkflowStep(
        '2',
        'Fragen konfigurieren',
        'Single Choice, Multiple Choice, Skala, Freitext, Datei und Pflichtfeld.',
      ),
      _WorkflowStep(
        '3',
        'Regeln setzen',
        'Anonymität, Laufzeit, Quorum, Mehrfachantworten, Guardian-Freigabe und Sichtbarkeit.',
      ),
      _WorkflowStep(
        '4',
        'Auswerten',
        'Ergebnis, Export, Kommentar, Entscheidung, Aufgabe und Benachrichtigung.',
      ),
    ];

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Umfragen & Abstimmungen',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Umfragen & Abstimmungen',
        subtitle:
            'Feedback, Abstimmungen, Quorum, Auswertung und Vereinsentscheidungen im mobilen Web-App-Stil.',
        trailing: StatusPill('Verein', color: airmiusAccentColor(context)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('CLUB SURVEY SUITE'),
                  const SizedBox(height: 10),
                  Text(
                    'Vereine können Mitglieder wirklich einbeziehen.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Die mobile App bildet Umfragen, Abstimmungen und Feedback so ab, dass Vereinsadmins später mit Rollenrechten, Benachrichtigungen, Export und Audit arbeiten können.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.42,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '4', label: 'Formate'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'API', label: 'Ready'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'Audit', label: 'Log'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            for (final type in pollTypes) ...[
              AirmiusPanel(
                borderColor: airmiusSemanticColor(
                  context,
                  type.color,
                ).withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: airmiusSemanticColor(
                          context,
                          type.color,
                        ).withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(15),
                        border: Border.all(
                          color: airmiusSemanticColor(
                            context,
                            type.color,
                          ).withValues(alpha: .45),
                        ),
                      ),
                      child: Icon(
                        type.icon,
                        color: airmiusSemanticColor(context, type.color),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            type.title,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            type.body,
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              height: 1.35,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              StatusPill(
                                type.status,
                                color: airmiusSemanticColor(
                                  context,
                                  type.color,
                                ),
                              ),
                              const StatusPill('Mobile UI'),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ABLAUF'),
                  const SizedBox(height: 12),
                  for (final step in workflow) ...[
                    _StepRow(step: step),
                    if (step != workflow.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('API & DATENMODELL'),
                  SizedBox(height: 10),
                  _ApiLine(
                    label: 'survey_schema',
                    value:
                        'Titel, Beschreibung, Fragen, Optionen, Pflichtfelder, Version',
                  ),
                  _ApiLine(
                    label: 'targeting',
                    value:
                        'Verein, Team, Rolle, Mitgliedschaft, Guardian, externe Einladung',
                  ),
                  _ApiLine(
                    label: 'response_rules',
                    value:
                        'Anonym, sichtbar, einmalig, editierbar, Frist, Quorum',
                  ),
                  _ApiLine(
                    label: 'result_actions',
                    value:
                        'Export, Entscheidung, Aufgabe, Benachrichtigung, Audit Timeline',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PollType {
  const _PollType(this.title, this.status, this.body, this.color, this.icon);

  final String title;
  final String status;
  final String body;
  final Color color;
  final IconData icon;
}

class _WorkflowStep {
  const _WorkflowStep(this.number, this.title, this.body);

  final String number;
  final String title;
  final String body;
}

class _StepRow extends StatelessWidget {
  const _StepRow({required this.step});

  final _WorkflowStep step;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 34,
        height: 34,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: airmiusAccentColor(context).withValues(alpha: .18),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: airmiusAccentColor(context).withValues(alpha: .42),
          ),
        ),
        child: Text(
          step.number,
          style: TextStyle(
            color: airmiusAccentColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              step.title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              step.body,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
        ),
      ),
    ],
  );
}

class _ApiLine extends StatelessWidget {
  const _ApiLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 9),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 128,
          child: Text(
            label,
            style: TextStyle(
              color: airmiusAccentColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
        ),
      ],
    ),
  );
}
