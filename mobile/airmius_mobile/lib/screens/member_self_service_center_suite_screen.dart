import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MemberSelfServiceCenterSuiteScreen extends StatefulWidget {
  const MemberSelfServiceCenterSuiteScreen({super.key});

  @override
  State<MemberSelfServiceCenterSuiteScreen> createState() => _MemberSelfServiceCenterSuiteScreenState();
}

class _MemberSelfServiceCenterSuiteScreenState extends State<MemberSelfServiceCenterSuiteScreen> {
  bool showDigitalCard = true;
  bool showPaymentStatus = true;
  bool showDocumentTasks = true;
  bool showSupportAccess = true;

  @override
  Widget build(BuildContext context) {
    final memberships = [
      const _MembershipRow(
        club: 'ZBB',
        status: 'Aktiv',
        body: 'Mitglied seit 01.07.2026 mit digitaler Karte, Beitragsstatus und Vereinsdokumenten.',
        meta: 'Allgemeine Mitgliedschaft',
        color: AirmiusColors.green,
      ),
      const _MembershipRow(
        club: 'Airmius Running Club',
        status: 'Anfrage offen',
        body: 'Antrag wurde gesendet. User kann Status sehen, Dokumente ergaenzen oder Anfrage zurueckziehen.',
        meta: 'Laufgruppe',
        color: AirmiusColors.blue,
      ),
      const _MembershipRow(
        club: 'Tennis Zentrum West',
        status: 'Rueckfrage',
        body: 'Verein benoetigt eine Dokumentfreigabe. Aufgabe wird in der mobilen Mitgliedszentrale angezeigt.',
        meta: 'Sportdaten pruefen',
        color: AirmiusColors.amber,
      ),
    ];

    return PageFrame(
      title: 'Meine Mitgliedschaften',
      subtitle: 'Karte, Beitraege, Dokumente und Aufgaben',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('SELF SERVICE'),
                const SizedBox(height: 8),
                const Text(
                  'Mitglieder brauchen eine eigene mobile Zentrale: aktive Vereine, offene Anfragen, digitale Karte, Beitragsstatus, Dokumentpflichten, Aufgaben und Support.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Vereine'),
                    Metric(value: 'Card', label: 'Karte'),
                    Metric(value: 'Pay', label: 'Beitrag'),
                    Metric(value: 'Docs', label: 'Aufgaben'),
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
                const SectionLabel('SICHTBARE BEREICHE'),
                const SizedBox(height: 8),
                _SelfServiceSwitch(title: 'Digitale Mitgliedskarte', value: showDigitalCard, color: AirmiusColors.green, onChanged: (value) => setState(() => showDigitalCard = value)),
                _SelfServiceSwitch(title: 'Beitragsstatus', value: showPaymentStatus, color: AirmiusColors.blue, onChanged: (value) => setState(() => showPaymentStatus = value)),
                _SelfServiceSwitch(title: 'Dokumentaufgaben', value: showDocumentTasks, color: AirmiusColors.amber, onChanged: (value) => setState(() => showDocumentTasks = value)),
                _SelfServiceSwitch(title: 'Supportzugang', value: showSupportAccess, color: AirmiusColors.pink, onChanged: (value) => setState(() => showSupportAccess = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final membership in memberships) ...[
            _MembershipCard(membership: membership),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('NAECHSTE AUFGABEN'),
                const SizedBox(height: 8),
                const Text(
                  'Die spaetere API kann hier offene Dokumente, Rueckfragen, Zahlungsinformationen, Vereinsnachrichten, Event-Einladungen und Support-Tickets pro Mitgliedschaft anzeigen.',
                  style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill('Dokument fehlt', color: AirmiusColors.amber),
                    StatusPill('Beitrag offen', color: AirmiusColors.blue),
                    StatusPill('Event Einladung', color: AirmiusColors.green),
                    StatusPill('Rueckfrage', color: AirmiusColors.pink),
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

class _MembershipRow {
  const _MembershipRow({
    required this.club,
    required this.status,
    required this.body,
    required this.meta,
    required this.color,
  });

  final String club;
  final String status;
  final String body;
  final String meta;
  final Color color;
}

class _SelfServiceSwitch extends StatelessWidget {
  const _SelfServiceSwitch({
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

class _MembershipCard extends StatelessWidget {
  const _MembershipCard({required this.membership});

  final _MembershipRow membership;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: Icons.badge_outlined, color: membership.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(membership.club, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(membership.status, color: membership.color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(membership.meta, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(membership.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
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
                label: 'Karte',
                icon: Icons.qr_code_2_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Digitale Mitgliedskarte',
                  body: 'Diese UI bereitet Mitgliedskarte, QR-Code, Status, Rolle und Sichtbarkeit fuer die spaetere API vor.',
                  status: 'UI vorbereitet',
                  icon: Icons.qr_code_2_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Beitraege',
                icon: Icons.receipt_long_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Beitraege anzeigen',
                  body: 'Mitglieder sehen spaeter Beitrag, Zahlungsrhythmus, offene Zahlungen, SEPA-Status und Rechnungen.',
                  status: 'UI vorbereitet',
                  icon: Icons.receipt_long_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Dokumente',
                icon: Icons.folder_copy_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Dokumente anzeigen',
                  body: 'Dokumentpflichten, Consent, Nachweise und Rueckfragen werden spaeter pro Mitgliedschaft sichtbar.',
                  status: 'UI vorbereitet',
                  icon: Icons.folder_copy_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
