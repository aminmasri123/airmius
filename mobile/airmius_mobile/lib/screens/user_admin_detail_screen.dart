import 'package:flutter/material.dart';
import 'trust_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'trust_operations_screen.dart';

class UserAdminDetailScreen extends StatefulWidget {
  const UserAdminDetailScreen({super.key, required this.name, required this.status});

  final String name;
  final String status;

  @override
  State<UserAdminDetailScreen> createState() => _UserAdminDetailScreenState();
}

class _UserAdminDetailScreenState extends State<UserAdminDetailScreen> {
  bool _verified = true;
  bool _blocked = false;
  bool _twoFactor = true;
  bool _dataExport = false;
  String _role = 'Player';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.verified_user_outlined), label: const Text('User Trust', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TrustOperationsScreen(initialTab: 'Inaktivitaet')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Nutzerprofil', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.name,
        subtitle: 'Profil, Rollen, Verifizierung, Datenschutz und Moderation',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Row(children: [
              AirmiusAvatar(widget.name),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Eyebrow('Nutzerkonto'),
                const SizedBox(height: 4),
                Text(widget.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                const Text('zbb.bop.it@gmail.com - Kleinblittersdorf', style: TextStyle(color: AirmiusColors.muted)),
              ])),
            ]),
            const SizedBox(height: 14),
            const _ProfileProgress(title: 'Profilvollstaendigkeit', value: 0.82, label: '82%'),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '3', label: 'Rollen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Vereine')), SizedBox(width: 10), Expanded(child: MetricCard(value: '0', label: 'Reports'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Rolle & Status'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _role,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Primaere Rolle'),
              items: const ['Player', 'Coach', 'Vereinsadmin', 'Guardian', 'Gast'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _role = value ?? _role),
            ),
            const SizedBox(height: 8),
            SwitchListTile(value: _verified, onChanged: (value) => setState(() => _verified = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('E-Mail verifiziert', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kann Login, Anfragen und Benachrichtigungen nutzen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _twoFactor, onChanged: (value) => setState(() => _twoFactor = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('2FA aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Empfohlen für Admin- und Finanzrechte.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _blocked, onChanged: (value) => setState(() => _blocked = value), activeColor: AirmiusColors.red, contentPadding: EdgeInsets.zero, title: const Text('Konto sperren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Sperre verhindert Login und neue Aktionen.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Datenschutz & Sicherheit'),
            const SizedBox(height: 8),
            SwitchListTile(value: _dataExport, onChanged: (value) => setState(() => _dataExport = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Datenexport angefragt', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('DSGVO Export wird später über API bereitgestellt.', style: TextStyle(color: AirmiusColors.muted))),
            const Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Consent OK'), StatusPill('Push aktiv'), StatusPill('Keine Reports')]),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Moderationsnotiz'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Interne Notiz', hint: 'Nur für Admins sichtbar', icon: Icons.gpp_maybe_outlined, maxLines: 4),
            SizedBox(height: 10),
            _AuditLine(title: 'Profil geprüft', body: 'Heute 10:15 - verein airmius'),
            SizedBox(height: 8),
            _AuditLine(title: 'Rolle aktualisiert', body: 'Gestern 18:42 - Admin'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Nutzer speichern', body: '${widget.name} mit Rolle, Status, Datenschutz und Audit aktualisieren.', status: 'Speichern', icon: Icons.save_outlined)))),
            AirmiusButton(label: 'Supportfall', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Supportfall erstellen', body: 'Supportfall für ${widget.name} erstellen und Moderationsnotiz verknuepfen.', status: 'Support', icon: Icons.support_agent_outlined)))),
            AirmiusButton(label: 'Konto sperren', icon: Icons.block_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Konto sperren', body: '${widget.name} sperren, Login blockieren und Audit schreiben.', status: 'Sperre', icon: Icons.block_outlined)))),
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

class _ProfileProgress extends StatelessWidget {
  const _ProfileProgress({required this.title, required this.value, required this.label});

  final String title;
  final double value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))]),
      const SizedBox(height: 8),
      ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: value, minHeight: 9, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue))),
    ]);
  }
}

class _AuditLine extends StatelessWidget {
  const _AuditLine({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        const Icon(Icons.history_outlined, color: AirmiusColors.blue),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(body, style: const TextStyle(color: AirmiusColors.muted)),
        ])),
      ]),
    );
  }
}

