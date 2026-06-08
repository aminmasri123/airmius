import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubApplicationInboxSuiteScreen extends StatefulWidget {
  const ClubApplicationInboxSuiteScreen({super.key});

  @override
  State<ClubApplicationInboxSuiteScreen> createState() => _ClubApplicationInboxSuiteScreenState();
}

class _ClubApplicationInboxSuiteScreenState extends State<ClubApplicationInboxSuiteScreen> {
  String filter = 'Offen';

  @override
  Widget build(BuildContext context) {
    final requests = [
      _ApplicationRequest(
        name: 'ZBB Konto',
        status: 'Offen',
        type: 'Allgemeine Anfrage',
        body: 'Personendaten, Wohndaten, Kontaktdaten und Zahlungswunsch wurden eingereicht.',
        documents: '2 Dokumente',
        color: AirmiusColors.blue,
      ),
      _ApplicationRequest(
        name: 'Amina Becker',
        status: 'Rueckfrage',
        type: 'Jugendmitgliedschaft',
        body: 'Erziehungsberechtigte fehlen noch. Verein kann eine Rueckfrage senden.',
        documents: '1 Dokument',
        color: AirmiusColors.amber,
      ),
      _ApplicationRequest(
        name: 'Noah Wagner',
        status: 'Bereit',
        type: 'Teambeitritt',
        body: 'Alle Pflichtfelder, Datenschutz und Vereinsregeln sind bestaetigt.',
        documents: '3 Dokumente',
        color: AirmiusColors.green,
      ),
    ];

    final filtered = requests.where((request) => filter == 'Alle' || request.status == filter).toList();

    return PageFrame(
      title: 'Anfrage-Inbox',
      subtitle: 'Vereinsbenachrichtigungen und Entscheidungen',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VEREINS-INBOX'),
                const SizedBox(height: 8),
                const Text(
                  'Wenn User eine Mitgliedschaftsanfrage senden, landet sie hier: mit Formularstatus, Dokumenten, Rueckfragen, Rueckzug-Historie und Adminentscheidung.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Anfragen'),
                    Metric(value: '6', label: 'Dokumente'),
                    Metric(value: '2', label: 'Admins'),
                    Metric(value: 'Push', label: 'Info'),
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
                const SectionLabel('FILTER'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Alle', label: Text('Alle')),
                    ButtonSegment(value: 'Offen', label: Text('Offen')),
                    ButtonSegment(value: 'Rueckfrage', label: Text('Rueckfrage')),
                    ButtonSegment(value: 'Bereit', label: Text('Bereit')),
                  ],
                  selected: {filter},
                  onSelectionChanged: (value) => setState(() => filter = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final request in filtered) ...[
            _RequestCard(request: request),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('BENACHRICHTIGUNGEN'),
                const SizedBox(height: 8),
                const Text(
                  'Vereinsadmins sollen spaeter Push, In-App-Badge und E-Mail-Hinweis erhalten. Rueckzug, neue Datei, Rueckfrage und Entscheidung bleiben im Verlauf sichtbar.',
                  style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill('Push', color: AirmiusColors.blue),
                    StatusPill('E-Mail', color: AirmiusColors.green),
                    StatusPill('Badge', color: AirmiusColors.amber),
                    StatusPill('Audit', color: AirmiusColors.pink),
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

class _ApplicationRequest {
  const _ApplicationRequest({
    required this.name,
    required this.status,
    required this.type,
    required this.body,
    required this.documents,
    required this.color,
  });

  final String name;
  final String status;
  final String type;
  final String body;
  final String documents;
  final Color color;
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.request});

  final _ApplicationRequest request;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: Icons.inbox_outlined, color: request.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(request.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(request.status, color: request.color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(request.type, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(request.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(request.documents, color: AirmiusColors.blue),
              StatusPill('Formular pruefen', color: AirmiusColors.green),
              StatusPill('Verlauf', color: AirmiusColors.amber),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Annehmen',
                icon: Icons.check_circle_outline,
                onPressed: () => openUiAction(
                  context,
                  title: 'Anfrage annehmen',
                  body: 'Der Verein kann spaeter nach Pruefung der Daten die Mitgliedschaft bestaetigen und den User informieren.',
                  status: 'UI vorbereitet',
                  icon: Icons.check_circle_outline,
                ),
              ),
              AirmiusButton(
                label: 'Rueckfrage',
                icon: Icons.mark_email_read_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Rueckfrage senden',
                  body: 'Admins koennen fehlende Angaben oder Dokumente anfordern, ohne die Anfrage abzulehnen.',
                  status: 'UI vorbereitet',
                  icon: Icons.mark_email_read_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Ablehnen',
                icon: Icons.cancel_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Anfrage ablehnen',
                  body: 'Eine Ablehnung soll spaeter mit Grund, interner Notiz und User-Benachrichtigung gespeichert werden.',
                  status: 'UI vorbereitet',
                  icon: Icons.cancel_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
