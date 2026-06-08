import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class MembershipOperationsScreen extends StatefulWidget {
  const MembershipOperationsScreen({super.key});

  @override
  State<MembershipOperationsScreen> createState() => _MembershipOperationsScreenState();
}

class _MembershipOperationsScreenState extends State<MembershipOperationsScreen> {
  String _tab = 'Anfragen';
  bool _requireDocuments = true;
  bool _autoMemberNumber = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Membership Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Membership Operations',
        subtitle: 'Anfragen, Rueckzug, Feldschema, Dokumentpflicht, Mitgliedsnummer, Status und Rollen',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Vereinsmitgliedschaft'),
                  const SizedBox(height: 8),
                  const Text('Diese Ansicht buendelt die mobilen Admin-Flows rund um Vereinsbeitritt: Antrag pruefen, Datenfelder steuern, Dokumente verlangen, Zahlung vorbereiten und Mitgliedschaft aktivieren.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Anfragen', 'Schema', 'Dokumente', 'Status', 'Zahlung', 'Alle'])
                        ChoiceChip(
                          selected: _tab == tab,
                          label: Text(tab),
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '1', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Docs')), SizedBox(width: 10), Expanded(child: MetricCard(value: '12 EUR', label: 'Beitrag'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Vereinsregeln'),
                  SwitchListTile(value: _requireDocuments, onChanged: (value) => setState(() => _requireDocuments = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Dokumentbestaetigung erforderlich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Datenschutz, Beitragsordnung und Vereinsregeln muessen vor Antrag bestaetigt werden.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _autoMemberNumber, onChanged: (value) => setState(() => _autoMemberNumber = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Mitgliedsnummer automatisch', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Nach Annahme wird eine Vereinsnummer vorbereitet.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _MembershipOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _MembershipOperationCard extends StatelessWidget {
  const _MembershipOperationCard({required this.item});

  final _MembershipOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.tab), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Antragsdaten', icon: Icons.assignment_ind_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Daten', body: 'Personendaten, Wohndaten, Kontaktdaten, Zahlmethode, Dokumente und Audit fuer ${item.title} anzeigen.', status: 'Application', icon: Icons.assignment_ind_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _MembershipOperation item) {
    final action = () => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Aktion veraendert Antrag, Mitgliedschaft oder Zahlstatus und wird spaeter auditiert.', item.action, action);
      return;
    }
    action();
  }
}

class _MembershipOperation {
  const _MembershipOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _MembershipOperation(tab: 'Anfragen', title: 'Antrag pruefen', body: 'Antragsdaten, Formularfelder, Dokumente, Zahlung und Guardian-Regeln anzeigen.', status: 'Review', icon: Icons.assignment_ind_outlined, action: 'Antrag pruefen'),
  _MembershipOperation(tab: 'Anfragen', title: 'Antrag annehmen', body: 'User als Mitglied aktivieren, Nummer vergeben, Rolle setzen und Verein informieren.', status: 'Accept', icon: Icons.check_circle_outline, action: 'Annehmen'),
  _MembershipOperation(tab: 'Anfragen', title: 'Antrag ablehnen', body: 'Antrag ablehnen, Grund speichern und User benachrichtigen.', status: 'Reject', icon: Icons.cancel_outlined, action: 'Ablehnen', danger: true),
  _MembershipOperation(tab: 'Anfragen', title: 'Antrag zurueckziehen', body: 'User-seitigen Rueckzug verarbeiten und offene Nachfrage entfernen.', status: 'Withdraw', icon: Icons.undo_outlined, action: 'Zurueckziehen', danger: true),
  _MembershipOperation(tab: 'Schema', title: 'Formularschema speichern', body: 'Pflicht/optional/ausgeblendet fuer Personendaten, Wohnort, Kontakt, Zahlung, Notfall und Sportdaten setzen.', status: 'Schema', icon: Icons.format_list_bulleted_outlined, action: 'Schema speichern'),
  _MembershipOperation(tab: 'Schema', title: 'Mitgliedschaftstyp konfigurieren', body: 'Allgemein, Jugend, Familie, Passiv, Extern oder Teammitgliedschaft mit eigenen Feldern definieren.', status: 'Type', icon: Icons.category_outlined, action: 'Typ speichern'),
  _MembershipOperation(tab: 'Dokumente', title: 'Dokument verknuepfen', body: 'Datenschutz, Beitragsordnung, SEPA, Vereinsregeln oder Uploadpflicht mit Antrag verbinden.', status: 'Docs', icon: Icons.description_outlined, action: 'Dokument verknuepfen'),
  _MembershipOperation(tab: 'Dokumente', title: 'Uploadpflicht setzen', body: 'Ausweis, Lizenz, SEPA-Mandat, Nachweis oder eigenes Vereinsdokument verlangen.', status: 'Upload', icon: Icons.upload_file_outlined, action: 'Uploadpflicht speichern'),
  _MembershipOperation(tab: 'Status', title: 'Mitgliedsnummer vergeben', body: 'Automatische oder manuelle Vereinsnummer mit Prefix und Audit erzeugen.', status: 'Number', icon: Icons.confirmation_number_outlined, action: 'Nummer vergeben'),
  _MembershipOperation(tab: 'Status', title: 'Mitgliedsstatus wechseln', body: 'Aktiv, pausiert, ausgetreten, extern, Jugend oder gesperrt setzen.', status: 'Status', icon: Icons.manage_accounts_outlined, action: 'Status setzen'),
  _MembershipOperation(tab: 'Status', title: 'Rolle nach Annahme setzen', body: 'Mitglied, Spieler, Trainer, Captain oder Vereinsadmin nach Beitritt zuweisen.', status: 'Role', icon: Icons.admin_panel_settings_outlined, action: 'Rolle setzen'),
  _MembershipOperation(tab: 'Zahlung', title: 'Beitragsregel anwenden', body: 'Monatlich, alle 4 Monate, halbjaehrlich oder jaehrlich mit Methode Ueberweisung/Bar/SEPA setzen.', status: 'Fee', icon: Icons.payments_outlined, action: 'Beitrag setzen'),
  _MembershipOperation(tab: 'Zahlung', title: 'Erstrechnung vorbereiten', body: 'Ersten Beitrag, Zeitraum, Faelligkeit und Zahlungsziel nach Annahme erzeugen.', status: 'Invoice', icon: Icons.receipt_long_outlined, action: 'Rechnung vorbereiten'),
];
