import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class WorkspaceDetailScreen extends StatefulWidget {
  const WorkspaceDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<WorkspaceDetailScreen> createState() => _WorkspaceDetailScreenState();
}

class _WorkspaceDetailScreenState extends State<WorkspaceDetailScreen> {
  String _context = 'Vereinsbereich';
  bool _pinToHome = true;
  bool _pushEnabled = true;
  bool _inheritRoles = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Arbeitsbereich', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Kontext, Rollen, Einladungen und mobile Sichtbarkeit',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Aktiver Kontext'),
            const SizedBox(height: 8),
            const Text('Mobile Navigation bleibt gleich, der Arbeitskontext wechselt.', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('So kann ein User zwischen Gastseite, Verein, Team, Trainerbereich und Admin-Aufgaben wechseln, ohne die App zu verlassen.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _context,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Arbeitsbereich'),
              items: const ['Gastseite', 'Dashboard', 'Vereinsbereich', 'Trainerbereich', 'Admin'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _context = value ?? _context),
            ),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '6', label: 'Rollen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Teams')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Invite'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Workspace-Regeln'),
            const SizedBox(height: 8),
            SwitchListTile(value: _pinToHome, onChanged: (value) => setState(() => _pinToHome = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Im Home sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Arbeitsbereich erscheint im Dashboard-Schnellzugriff.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _pushEnabled, onChanged: (value) => setState(() => _pushEnabled = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Push in diesem Kontext', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Benachrichtigungen respektieren Rollen und Ruhezeiten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _inheritRoles, onChanged: (value) => setState(() => _inheritRoles = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Vereinsrollen übernehmen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Team- und Trainerrechte aus Vereinsrollen ableiten.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Rollen & Rechte'),
            SizedBox(height: 12),
            _WorkspaceRole(role: 'Vereinsadmin', rights: 'Mitglieder, Dokumente, Beiträge, Teams, Zahlungen'),
            SizedBox(height: 10),
            _WorkspaceRole(role: 'Trainer', rights: 'Athleten, Plaene, Feedback, Teamkalender'),
            SizedBox(height: 10),
            _WorkspaceRole(role: 'Mitglied', rights: 'Profil, Feed, Chat, Events, Dateien ansehen'),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Einladung'),
            SizedBox(height: 8),
            Text('Offene Workspace-Einladung: ZBB Verein moechte dich als Trainer hinzufuegen. Token, Ablaufdatum und Rollenprüfung werden später serverseitig geladen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Token offen'), StatusPill('Coach'), StatusPill('ZBB')]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Kontext aktivieren', icon: Icons.swap_horiz_outlined, onPressed: () => openUiAction(context, title: 'Kontext aktivieren', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.swap_horiz_outlined)),
            AirmiusButton(label: 'Einladung annehmen', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Einladung annehmen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.mark_email_read_outlined)),
          ]),
        ]),
      ),
    );
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}

class _WorkspaceRole extends StatelessWidget {
  const _WorkspaceRole({required this.role, required this.rights});

  final String role;
  final String rights;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(role, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        Text(rights, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
      ]),
    );
  }
}
