import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_operations_screen.dart';
import 'membership_operations_screen.dart';
import 'notification_chat_operations_screen.dart';

class ClubRequestInboxScreen extends StatefulWidget {
  const ClubRequestInboxScreen({super.key, this.initialTab = 'Neu'});

  final String initialTab;

  @override
  State<ClubRequestInboxScreen> createState() => _ClubRequestInboxScreenState();
}

class _ClubRequestInboxScreenState extends State<ClubRequestInboxScreen> {
  late String _tab = widget.initialTab;
  bool _notifyAdmins = true;
  bool _notifyApplicant = true;
  bool _autoTask = true;
  bool _showWithdrawn = true;

  @override
  Widget build(BuildContext context) {
    final requests = _requests.where((request) => _tab == 'Alle' || request.status == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Anfrage-Eingang', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Anfrage-Eingang',
        subtitle: 'Mitgliedschaftsanfragen, Rueckzuege, Dokumente, Adminentscheidungen und Benachrichtigungen',
        trailing: const StatusPill('Club Inbox'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text('Vereine sehen sofort, wer beitreten moechte.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Diese Inbox sammelt Anfragen, Rueckzuege, Dokumente, Zahlungswunsch und Adminaktionen. Spaeter kommen Push, E-Mail, Chat und Laravel-Statusupdates dazu.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: const [Expanded(child: MetricCard(value: '3', label: 'Neu')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Rueckzug')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Dokumente'))]),
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final tab in _tabs)
                      ChoiceChip(
                        label: Text(tab),
                        selected: _tab == tab,
                        onSelected: (_) => setState(() => _tab = tab),
                        selectedColor: AirmiusColors.green.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _tab == tab ? AirmiusColors.green : AirmiusColors.border),
                        labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _InboxSettingsPanel(
              notifyAdmins: _notifyAdmins,
              notifyApplicant: _notifyApplicant,
              autoTask: _autoTask,
              showWithdrawn: _showWithdrawn,
              onAdmins: (value) => setState(() => _notifyAdmins = value),
              onApplicant: (value) => setState(() => _notifyApplicant = value),
              onTask: (value) => setState(() => _autoTask = value),
              onWithdrawn: (value) => setState(() => _showWithdrawn = value),
            ),
            const SizedBox(height: 16),
            for (final request in requests) ...[
              if (_showWithdrawn || request.status != 'Rueckzug') _RequestCard(request: request),
              if (_showWithdrawn || request.status != 'Rueckzug') const SizedBox(height: 12),
            ],
            _InboxWorkflowPanel(tab: _tab),
          ],
        ),
      ),
    );
  }
}

class _InboxSettingsPanel extends StatelessWidget {
  const _InboxSettingsPanel({required this.notifyAdmins, required this.notifyApplicant, required this.autoTask, required this.showWithdrawn, required this.onAdmins, required this.onApplicant, required this.onTask, required this.onWithdrawn});

  final bool notifyAdmins;
  final bool notifyApplicant;
  final bool autoTask;
  final bool showWithdrawn;
  final ValueChanged<bool> onAdmins;
  final ValueChanged<bool> onApplicant;
  final ValueChanged<bool> onTask;
  final ValueChanged<bool> onWithdrawn;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.blue.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Benachrichtigung & Workflow'),
          const SizedBox(height: 8),
          const Text('Hier entscheidet der Verein, wie Admins und Antragsteller informiert werden. Rueckzuege bleiben sichtbar, damit keine versehentliche Anfrage weiterbearbeitet wird.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 10),
          _InboxSwitch(icon: Icons.notifications_active_outlined, title: 'Admins benachrichtigen', body: 'Push, E-Mail oder Inbox-Eintrag fuer neue Mitgliedschaftsanfragen.', value: notifyAdmins, onChanged: onAdmins, color: AirmiusColors.green),
          _InboxSwitch(icon: Icons.person_outline, title: 'Antragsteller informieren', body: 'Statusupdates fuer gesendet, in Pruefung, angenommen, abgelehnt oder zurueckgezogen.', value: notifyApplicant, onChanged: onApplicant, color: AirmiusColors.blue),
          _InboxSwitch(icon: Icons.task_alt_outlined, title: 'Admin-Aufgabe erzeugen', body: 'Neue Anfrage landet als Aufgabe im Vereinscockpit oder Adminbereich.', value: autoTask, onChanged: onTask, color: AirmiusColors.amber),
          _InboxSwitch(icon: Icons.undo_outlined, title: 'Rueckzuege anzeigen', body: 'Zurueckgezogene Anfragen bleiben mit Zeitstempel und Grund sichtbar.', value: showWithdrawn, onChanged: onWithdrawn, color: AirmiusColors.red),
        ]),
      );
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.request});

  final _MembershipRequest request;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: request.color.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            AirmiusAvatar(request.name),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(request.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
              const SizedBox(height: 5),
              Text(request.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(request.status, color: request.color), StatusPill(request.payment), StatusPill(request.documents, color: request.color)]),
            ])),
          ]),
          const SizedBox(height: 12),
          _RequestDataGrid(request: request),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Pruefen', icon: Icons.fact_check_outlined, onPressed: () => openUiAction(context, title: '${request.name} pruefen', body: 'Antragstellerdaten, Dokumente, Zahlungswunsch und Consent pruefen. Danach annehmen, ablehnen oder Rueckfrage senden.', status: request.status, icon: Icons.fact_check_outlined)),
            AirmiusButton(label: 'Annehmen', icon: Icons.check_circle_outline, secondary: true, onPressed: request.status == 'Rueckzug' ? null : () => openUiAction(context, title: 'Anfrage annehmen', body: '${request.name} als Mitglied aufnehmen, Rolle zuweisen, erste Rechnung/Zahlungsaufgabe erzeugen und Nutzer benachrichtigen.', status: 'Annehmen', icon: Icons.check_circle_outline)),
            AirmiusButton(label: 'Ablehnen', icon: Icons.cancel_outlined, danger: true, onPressed: request.status == 'Rueckzug' ? null : () => openUiAction(context, title: 'Anfrage ablehnen', body: 'Ablehnungsgrund erfassen, Antragsteller informieren und Audit-Eintrag speichern.', status: 'Ablehnen', icon: Icons.cancel_outlined)),
            AirmiusButton(label: 'Nachricht', icon: Icons.chat_bubble_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
            AirmiusButton(label: 'Dateien', icon: Icons.folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileOperationsScreen()))),
          ]),
        ]),
      );
}

class _RequestDataGrid extends StatelessWidget {
  const _RequestDataGrid({required this.request});

  final _MembershipRequest request;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          _DataLine(label: 'E-Mail', value: request.email),
          _DataLine(label: 'Adresse', value: request.address),
          _DataLine(label: 'Typ', value: request.type),
          _DataLine(label: 'Eingang', value: request.received),
        ]),
      );
}

class _DataLine extends StatelessWidget {
  const _DataLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 86, child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))),
          Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
        ]),
      );
}

class _InboxWorkflowPanel extends StatelessWidget {
  const _InboxWorkflowPanel({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Anfrage-Workflow'),
          const SizedBox(height: 8),
          Text('Aktueller Filter: $tab. Spaeter synchronisiert Laravel Anfrage-Status, Rueckzug, Adminentscheidung, Benachrichtigung, Mitgliedsnummer, Rolle und erste Zahlung.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Membership Ops', icon: Icons.assignment_ind_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
            AirmiusButton(label: 'Alle als gelesen', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Anfragen gelesen', body: 'Alle sichtbaren Mitgliedschaftsanfragen als gelesen markieren und Admin-Badge aktualisieren.', status: 'Inbox', icon: Icons.mark_email_read_outlined)),
          ]),
        ]),
      );
}

class _InboxSwitch extends StatelessWidget {
  const _InboxSwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(
        value: value,
        onChanged: onChanged,
        activeColor: color,
        contentPadding: EdgeInsets.zero,
        secondary: Icon(icon, color: color),
        title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
      );
}

class _MembershipRequest {
  const _MembershipRequest({required this.status, required this.name, required this.email, required this.address, required this.type, required this.received, required this.body, required this.payment, required this.documents, required this.color});

  final String status;
  final String name;
  final String email;
  final String address;
  final String type;
  final String received;
  final String body;
  final String payment;
  final String documents;
  final Color color;
}

const _tabs = ['Alle', 'Neu', 'Pruefung', 'Rueckzug', 'Angenommen', 'Abgelehnt'];

const _requests = <_MembershipRequest>[
  _MembershipRequest(status: 'Neu', name: 'ZBB Konto', email: 'zbb.bop.it@gmail.com', address: 'Saargemuender Str. 110, 66271 Kleinblittersdorf', type: 'Allgemeine Anfrage', received: 'Heute 10:24', body: 'Moechte dem Verein ZBB beitreten. Personendaten, Wohndaten und Kontaktdaten sind ausgefuellt.', payment: 'Ueberweisung', documents: '1 offen', color: AirmiusColors.green),
  _MembershipRequest(status: 'Pruefung', name: 'Mina Becker', email: 'mina@example.com', address: 'Trier, Rheinland-Pfalz', type: 'Jugendmitglied', received: 'Gestern 18:12', body: 'Guardian Consent erforderlich. SEPA-Mandat und Medienfreigabe liegen als Upload vor.', payment: 'SEPA', documents: '3/3', color: AirmiusColors.blue),
  _MembershipRequest(status: 'Rueckzug', name: 'Ali Hassan', email: 'ali@example.com', address: 'Saarbruecken', type: 'Probemonat', received: 'Vor 2 Tagen', body: 'Anfrage wurde vom Nutzer zurueckgezogen. Adminentscheidung ist gesperrt, Historie bleibt sichtbar.', payment: 'Bar', documents: 'Rueckzug', color: AirmiusColors.red),
  _MembershipRequest(status: 'Angenommen', name: 'Jonas Weber', email: 'jonas@example.com', address: 'Koblenz', type: 'Standard', received: '03.06.2026', body: 'Als Mitglied aufgenommen, Mitgliedsnummer vorbereitet und erste Zahlungsaufgabe erzeugt.', payment: 'Jaehrlich', documents: 'OK', color: AirmiusColors.green),
  _MembershipRequest(status: 'Abgelehnt', name: 'Test Account', email: 'test@example.com', address: 'Unvollstaendig', type: 'Unklar', received: '01.06.2026', body: 'Ablehnung wegen fehlender Pflichtdaten und nicht akzeptierter Vereinsregeln.', payment: 'Offen', documents: 'Fehlt', color: AirmiusColors.amber),
];
