import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class OutfitOperationsScreen extends StatefulWidget {
  const OutfitOperationsScreen({super.key});

  @override
  State<OutfitOperationsScreen> createState() => _OutfitOperationsScreenState();
}

class _OutfitOperationsScreenState extends State<OutfitOperationsScreen> {
  String _tab = 'Abos';
  bool _notify = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Outfit Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Outfit Operations',
        subtitle: 'Admin-Flows für Outfit-Abos, Plaene, Zahlstatus, Lieferungen, Issues und Visuals',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Outfit Admin'),
                  const SizedBox(height: 8),
                  const Text('Mobile Umsetzung der Web-App-Admin-Routen für Outfit-Abos: bezahlt/unbezahlt, Payment Reminder, Lieferadresse, Lieferung versendet/zugestellt, Issues, Plaene und Visuals.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Abos', 'Lieferungen', 'Plaene', 'Visuals', 'Alle'])
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
            Row(children: const [Expanded(child: MetricCard(value: '1', label: 'Aktiv')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Lieferungen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Issue'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: SwitchListTile(
                value: _notify,
                onChanged: (value) => setState(() => _notify = value),
                activeColor: AirmiusColors.blue,
                contentPadding: EdgeInsets.zero,
                title: const Text('User informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                subtitle: const Text('Zahlstatus, Lieferung, Problemfall und Planwechsel erzeugen später eine Benachrichtigung.', style: TextStyle(color: AirmiusColors.muted)),
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _OutfitOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _OutfitOperationCard extends StatelessWidget {
  const _OutfitOperationCard({required this.item});

  final _OutfitOperation item;

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
              AirmiusButton(label: 'Audit', icon: Icons.history_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Outfit Audit', body: 'Audit, User, Lieferung, Zahlung und Bearbeiter für ${item.title} anzeigen.', status: 'Audit', icon: Icons.history_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _OutfitOperation item) {
    final action = () => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Outfit-Admin-Aktion verändert Abo, Lieferung oder Plan. Später wird sie auditiert.', item.action, action);
      return;
    }
    action();
  }
}

class _OutfitOperation {
  const _OutfitOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _OutfitOperation(tab: 'Abos', title: 'Abo als bezahlt markieren', body: 'Banktransfer oder Providerzahlung für Outfit-Abo bestätigen.', status: 'Paid', icon: Icons.payments_outlined, action: 'Bezahlt markieren'),
  _OutfitOperation(tab: 'Abos', title: 'Abo als unbezahlt markieren', body: 'Zahlstatus zurücksetzen und Zahlungsaufforderung vorbereiten.', status: 'Unpaid', icon: Icons.money_off_outlined, action: 'Unbezahlt setzen', danger: true),
  _OutfitOperation(tab: 'Abos', title: 'Payment Reminder senden', body: 'Zahlungserinnerung mit Betrag, Frist und Zahlungslink vorbereiten.', status: 'Reminder', icon: Icons.notification_important_outlined, action: 'Reminder senden'),
  _OutfitOperation(tab: 'Abos', title: 'Lieferadresse aktualisieren', body: 'Adresse, Kontakt, Land und Lieferhinweis für naechste Box speichern.', status: 'Address', icon: Icons.edit_location_alt_outlined, action: 'Adresse speichern'),
  _OutfitOperation(tab: 'Abos', title: 'Outfit-Abo kündigen', body: 'Kündigung, Frist, letzte Lieferung und Benachrichtigung vorbereiten.', status: 'Cancel', icon: Icons.cancel_outlined, action: 'Abo kündigen', danger: true),
  _OutfitOperation(tab: 'Abos', title: 'Outfit-Abo löschen', body: 'Abo entfernen oder archivieren, wenn keine aktiven Lieferungen offen sind.', status: 'Delete', icon: Icons.delete_forever_outlined, action: 'Abo löschen', danger: true),
  _OutfitOperation(tab: 'Lieferungen', title: 'Lieferung aktualisieren', body: 'Paketstatus, Tracking, Inhalt, Groesse und Versandhinweis speichern.', status: 'Delivery', icon: Icons.local_shipping_outlined, action: 'Lieferung speichern'),
  _OutfitOperation(tab: 'Lieferungen', title: 'Lieferung als versendet markieren', body: 'Tracking aktivieren und User über Versand informieren.', status: 'Shipped', icon: Icons.outbox_outlined, action: 'Versendet markieren'),
  _OutfitOperation(tab: 'Lieferungen', title: 'Lieferung als zugestellt markieren', body: 'Lieferung abschließen und naechsten Zyklus vorbereiten.', status: 'Delivered', icon: Icons.task_alt_outlined, action: 'Zugestellt markieren'),
  _OutfitOperation(tab: 'Lieferungen', title: 'Lieferproblem aktualisieren', body: 'Issue, Rückgabe, Ersatzlieferung oder Supportantwort speichern.', status: 'Issue', icon: Icons.report_problem_outlined, action: 'Issue speichern'),
  _OutfitOperation(tab: 'Lieferungen', title: 'Lieferung löschen', body: 'Fehlerhafte Lieferung entfernen und Auditgrund speichern.', status: 'Delete', icon: Icons.delete_outline, action: 'Lieferung löschen', danger: true),
  _OutfitOperation(tab: 'Plaene', title: 'Outfit-Plan erstellen', body: 'Planname, Preis, Rhythmus, Lieferumfang und Sichtbarkeit anlegen.', status: 'Plan', icon: Icons.add_box_outlined, action: 'Plan erstellen'),
  _OutfitOperation(tab: 'Plaene', title: 'Outfit-Plan bearbeiten', body: 'Preis, Leistungen, Bild, Laufzeit und Verfuegbarkeit aktualisieren.', status: 'Plan', icon: Icons.edit_note_outlined, action: 'Plan speichern'),
  _OutfitOperation(tab: 'Plaene', title: 'Outfit-Plan löschen', body: 'Plan entfernen oder archivieren, wenn keine aktiven Abos betroffen sind.', status: 'Delete', icon: Icons.delete_forever_outlined, action: 'Plan löschen', danger: true),
  _OutfitOperation(tab: 'Visuals', title: 'Outfit Visuals aktualisieren', body: 'Hero-Bilder, Style-Kacheln, Public-Texte und App-Vorschau speichern.', status: 'Visuals', icon: Icons.image_outlined, action: 'Visuals speichern'),
];
