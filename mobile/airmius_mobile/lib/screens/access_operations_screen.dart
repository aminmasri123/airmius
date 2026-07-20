import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AccessOperationsScreen extends StatefulWidget {
  const AccessOperationsScreen({super.key});

  @override
  State<AccessOperationsScreen> createState() => _AccessOperationsScreenState();
}

class _AccessOperationsScreenState extends State<AccessOperationsScreen> {
  String _tab = 'Rollen';
  bool _auditRequired = true;
  bool _notifyUser = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Access Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Access Operations',
        subtitle: 'Mitglieder, Rollen, Permissions, Inaktivitaet, Einladungen und Audit',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Rechteverwaltung'),
                  const SizedBox(height: 8),
                  const Text('Mobile Umsetzung der Web-App-Admin-Routen für Mitglieder, Rollen, Permissions und Inaktivitaetsnotizen. So bleibt Rechteverwaltung auch in der App nachvollziehbar.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Rollen', 'Permissions', 'Mitglieder', 'Einladungen', 'Alle'])
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
            Row(children: const [Expanded(child: MetricCard(value: '6', label: 'Rollen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '42', label: 'Rechte')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Audits'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Sicherheitsregeln'),
                  SwitchListTile(value: _auditRequired, onChanged: (value) => setState(() => _auditRequired = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Audit verpflichtend', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Rollen-, Rechte- und Mitglieder-Änderungen werden später protokolliert.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _notifyUser, onChanged: (value) => setState(() => _notifyUser = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Betroffene informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('User oder Admins erhalten eine Nachricht nach Rollen-/Statuswechsel.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _AccessOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _AccessOperationCard extends StatelessWidget {
  const _AccessOperationCard({required this.item});

  final _AccessOperation item;

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
              AirmiusButton(label: 'Audit', icon: Icons.history_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Access Audit', body: 'Audit, Bearbeiter, vorheriger Wert, neuer Wert und Rechtegrund für ${item.title} anzeigen.', status: 'Audit', icon: Icons.history_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _AccessOperation item) {
    void action() => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Aktion beeinflusst Zugriff, Rollen oder Nutzerstatus und wird später mit Audit gespeichert.', item.action, action);
      return;
    }
    action();
  }
}

class _AccessOperation {
  const _AccessOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _AccessOperation(tab: 'Rollen', title: 'Rolle erstellen', body: 'Neue Rolle mit Name, Scope, Beschreibung und Basisrechten anlegen.', status: 'Create', icon: Icons.add_moderator_outlined, action: 'Rolle speichern'),
  _AccessOperation(tab: 'Rollen', title: 'Rolle bearbeiten', body: 'Rollenname, Rechte, Sicherheitsgates und Sichtbarkeit aktualisieren.', status: 'Update', icon: Icons.admin_panel_settings_outlined, action: 'Rolle aktualisieren'),
  _AccessOperation(tab: 'Rollen', title: 'Rolle löschen', body: 'Rolle entfernen oder Mitglieder vorher auf Ersatzrolle verschieben.', status: 'Delete', icon: Icons.delete_outline, action: 'Rolle löschen', danger: true),
  _AccessOperation(tab: 'Permissions', title: 'Permission anlegen', body: 'Neues Recht mit Key, Modul, Beschreibung und Sicherheitsklasse erstellen.', status: 'Permission', icon: Icons.key_outlined, action: 'Permission speichern'),
  _AccessOperation(tab: 'Permissions', title: 'Permission-Matrix prüfen', body: 'Lesen, erstellen, bearbeiten, freigeben, exportieren und moderieren pro Rolle vergleichen.', status: 'Matrix', icon: Icons.grid_on_outlined, action: 'Matrix prüfen'),
  _AccessOperation(tab: 'Mitglieder', title: 'Mitglied erstellen', body: 'Admin legt Nutzer/Mitglied mit Profil, Rolle, Verein und Status an.', status: 'Create', icon: Icons.person_add_alt_1_outlined, action: 'Mitglied speichern'),
  _AccessOperation(tab: 'Mitglieder', title: 'Mitglied bearbeiten', body: 'Profil, Rolle, Status, Verifizierung und Verbindungskontext aktualisieren.', status: 'Update', icon: Icons.manage_accounts_outlined, action: 'Mitglied aktualisieren'),
  _AccessOperation(tab: 'Mitglieder', title: 'Inaktivitaetsnotiz senden', body: 'Hinweis an inaktiven Nutzer mit Frist, Kontext und Reaktivierungslink senden.', status: 'Notice', icon: Icons.mark_email_read_outlined, action: 'Notiz senden'),
  _AccessOperation(tab: 'Mitglieder', title: 'Mitglied löschen', body: 'Nutzer entfernen, Datenschutzstatus prüfen und Auditgrund speichern.', status: 'Delete', icon: Icons.delete_forever_outlined, action: 'Mitglied löschen', danger: true),
  _AccessOperation(tab: 'Einladungen', title: 'Workspace-Einladung erstellen', body: 'Einladung mit Rolle, Verein, Team, Ablaufdatum und Token vorbereiten.', status: 'Invite', icon: Icons.send_outlined, action: 'Einladung senden'),
  _AccessOperation(tab: 'Einladungen', title: 'Einladung annehmen', body: 'Token prüfen, Rolle aktivieren und Workspace sichtbar machen.', status: 'Accept', icon: Icons.check_circle_outline, action: 'Einladung annehmen'),
  _AccessOperation(tab: 'Einladungen', title: 'Einladung ablehnen', body: 'Token ablehnen, Absender informieren und Invite archivieren.', status: 'Decline', icon: Icons.cancel_outlined, action: 'Einladung ablehnen', danger: true),
];
