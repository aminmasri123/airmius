import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SupportTicketServiceCenterSuiteScreen extends StatefulWidget {
  const SupportTicketServiceCenterSuiteScreen({super.key});

  @override
  State<SupportTicketServiceCenterSuiteScreen> createState() => _SupportTicketServiceCenterSuiteScreenState();
}

class _SupportTicketServiceCenterSuiteScreenState extends State<SupportTicketServiceCenterSuiteScreen> {
  String topic = 'Mitgliedschaft';
  bool attachDocuments = true;
  bool notifyClubAdmin = true;
  bool escalateToPlatform = false;
  bool includeTimeline = true;

  @override
  Widget build(BuildContext context) {
    final tickets = [
      const _TicketRow(
        title: 'Anfrage wurde nicht bearbeitet',
        status: 'Offen',
        body: 'User sieht Verlauf, Vereinshinweis, letzte Aktivitaet und kann freundlich nachfragen.',
        meta: 'Mitgliedschaft - ZBB',
        color: AirmiusColors.blue,
      ),
      const _TicketRow(
        title: 'Dokument fehlt im Antrag',
        status: 'Rückfrage',
        body: 'Support kann erklaeren, welches Dokument fehlt und ob Upload oder Consent benoetigt wird.',
        meta: 'Dokumente & Consent',
        color: AirmiusColors.amber,
      ),
      const _TicketRow(
        title: 'Beitrag falsch angezeigt',
        status: 'Prüfung',
        body: 'Zahlungsrhythmus, Beitragsgruppe, Rechnung und Vereinsregel werden im Ticket zusammengefuehrt.',
        meta: 'Beiträge & Zahlung',
        color: AirmiusColors.green,
      ),
    ];

    return PageFrame(
      title: 'Support Center',
      subtitle: 'Tickets, Verlauf und Eskalation',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('HILFE & SUPPORT'),
                const SizedBox(height: 8),
                const Text(
                  'User, Vereine und Admins brauchen eine mobile Supportstrecke: Ticket erstellen, Thema wählen, Dateien anhaengen, Verlauf sehen und bei Bedarf eskalieren.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Tickets'),
                    Metric(value: '4', label: 'Themen'),
                    Metric(value: 'Docs', label: 'Anhang'),
                    Metric(value: 'SLA', label: 'Status'),
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
                const SectionLabel('NEUES TICKET'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Mitgliedschaft', label: Text('Mitglied')),
                    ButtonSegment(value: 'Zahlung', label: Text('Zahlung')),
                    ButtonSegment(value: 'Dokumente', label: Text('Dokumente')),
                    ButtonSegment(value: 'Technik', label: Text('Technik')),
                  ],
                  selected: {topic},
                  onSelectionChanged: (value) => setState(() => topic = value.first),
                ),
                const SizedBox(height: 14),
                _SupportSwitch(title: 'Dokumente anhaengen', value: attachDocuments, color: AirmiusColors.blue, onChanged: (value) => setState(() => attachDocuments = value)),
                _SupportSwitch(title: 'Vereinsadmin informieren', value: notifyClubAdmin, color: AirmiusColors.green, onChanged: (value) => setState(() => notifyClubAdmin = value)),
                _SupportSwitch(title: 'Zur Plattform eskalieren', value: escalateToPlatform, color: AirmiusColors.amber, onChanged: (value) => setState(() => escalateToPlatform = value)),
                _SupportSwitch(title: 'Verlauf mitsenden', value: includeTimeline, color: AirmiusColors.pink, onChanged: (value) => setState(() => includeTimeline = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final ticket in tickets) ...[
            _TicketCard(ticket: ticket),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktuelles Thema: $topic. Die spätere API kann Ticketstatus, Bearbeiter, Verein, Plattformteam, Dateien, Nachrichten und SLA-Zeiten verbinden.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Ticket erstellen',
                  icon: Icons.support_agent_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Ticket erstellen',
                    body: 'Diese UI bereitet Supporttickets mit Thema, Anhang, Vereinsadmin, Plattformeskalation und Verlauf für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.support_agent_outlined,
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

class _TicketRow {
  const _TicketRow({
    required this.title,
    required this.status,
    required this.body,
    required this.meta,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final String meta;
  final Color color;
}

class _SupportSwitch extends StatelessWidget {
  const _SupportSwitch({
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
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket});

  final _TicketRow ticket;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: Icons.support_agent_outlined, color: ticket.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(ticket.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(ticket.status, color: ticket.color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(ticket.meta, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(ticket.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
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
              AirmiusButton(
                label: 'Antworten',
                icon: Icons.reply_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Antwort senden',
                  body: 'Ticketantworten können später Nachrichten, Dateien, interne Notizen und Statuswechsel enthalten.',
                  status: 'UI vorbereitet',
                  icon: Icons.reply_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Verlauf',
                icon: Icons.timeline_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Ticketverlauf',
                  body: 'Der Verlauf zeigt später Useraktionen, Vereinsantworten, Plattformentscheidungen, Dateien und Statuswechsel.',
                  status: 'UI vorbereitet',
                  icon: Icons.timeline_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Eskalieren',
                icon: Icons.priority_high_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Ticket eskalieren',
                  body: 'Eskalationen können später an Vereinsadmins oder Plattform-Support weitergeleitet werden.',
                  status: 'UI vorbereitet',
                  icon: Icons.priority_high_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
