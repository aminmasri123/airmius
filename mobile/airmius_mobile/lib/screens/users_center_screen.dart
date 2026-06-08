import 'package:flutter/material.dart';
import 'trust_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'user_admin_detail_screen.dart';
import 'trust_operations_screen.dart';

class UsersCenterScreen extends StatefulWidget {
  const UsersCenterScreen({super.key});

  @override
  State<UsersCenterScreen> createState() => _UsersCenterScreenState();
}

class _UsersCenterScreenState extends State<UsersCenterScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.verified_user_outlined), label: const Text('Trust Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TrustOperationsScreen(initialTab: 'Inaktivitaet')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Nutzer', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Nutzer',
        subtitle: 'Personen, Profile, Status, Rollen, Verbindungen und Moderation',
        trailing: const StatusPill('People'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Nutzerverwaltung'),
            const SizedBox(height: 8),
            const Text('Profile und Personen mobil verwalten.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Suche, Rollen, Status, Sperren, Verifizieren, Profilvollstaendigkeit und Datenschutzstatus werden als native UI vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Alle', 'Aktiv', 'Admin', 'Gesperrt', 'Pruefen'].map((item) => ChoiceChip(
              selected: _filter == item,
              label: Text(item),
              onSelected: (_) => setState(() => _filter = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _filter == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _filter == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '42', label: 'Nutzer')), SizedBox(width: 10), Expanded(child: MetricCard(value: '7', label: 'Online')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Pruefen'))]),
          const SizedBox(height: 14),
          _UserLine(
            name: 'ZBB Konto',
            body: 'Player - Kleinblittersdorf - Profil 82%',
            status: 'Aktiv',
            color: AirmiusColors.green,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserAdminDetailScreen(name: 'ZBB Konto', status: 'Aktiv'))),
          ),
          const SizedBox(height: 12),
          _UserLine(
            name: 'verein airmius',
            body: 'Admin - Vereinsbereich und Rollen aktiv.',
            status: 'Admin',
            color: AirmiusColors.blue,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserAdminDetailScreen(name: 'verein airmius', status: 'Admin'))),
          ),
          const SizedBox(height: 12),
          _UserLine(
            name: 'Junior Mitglied',
            body: 'Guardian Consent und Altersfreigaben aktiv.',
            status: 'Jugend',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserAdminDetailScreen(name: 'Junior Mitglied', status: 'Jugend'))),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Nutzer-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Nutzer suchen', icon: Icons.search_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserAdminDetailScreen(name: 'Nutzersuche', status: 'Suche')))),
              AirmiusButton(label: 'Nutzer anlegen', icon: Icons.person_add_alt_1_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Nutzer anlegen', body: 'Admin-Nutzeranlage, Rolle, Einladung, Verifizierung und Audit vorbereiten.', status: 'Create', icon: Icons.person_add_alt_1_outlined)),
              AirmiusButton(label: 'Status pruefen', icon: Icons.verified_user_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserAdminDetailScreen(name: 'Status pruefen', status: 'Pruefen')))),
              AirmiusButton(label: 'Inaktivitaetsnotiz', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Inaktivitaetsnotiz senden', body: 'Inaktive Nutzer filtern, E-Mail vorbereiten und Versandstatus auditieren.', status: 'Notice', icon: Icons.mark_email_read_outlined)),
              AirmiusButton(label: 'Moderieren', icon: Icons.gpp_maybe_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserAdminDetailScreen(name: 'Moderation', status: 'Audit')))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _UserLine extends StatelessWidget {
  const _UserLine({required this.name, required this.body, required this.status, required this.color, required this.onTap});

  final String name;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      AirmiusAvatar(name),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      const Icon(Icons.chevron_right, color: AirmiusColors.muted),
    ]));
  }
}

