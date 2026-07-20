import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'api_token_manager_screen.dart';
import 'legal_status_center_screen.dart';
import 'localization_center_screen.dart';
import 'system_admin_operations_screen.dart';

class AdminPlatformSettingsScreen extends StatefulWidget {
  const AdminPlatformSettingsScreen({super.key});

  @override
  State<AdminPlatformSettingsScreen> createState() => _AdminPlatformSettingsScreenState();
}

class _AdminPlatformSettingsScreenState extends State<AdminPlatformSettingsScreen> {
  bool _registrationOpen = true;
  bool _clubApplicationsOpen = true;
  bool _maintenanceMode = false;
  bool _apiEnabled = true;
  bool _publicPagesEnabled = true;
  bool _strictModeration = true;

  final List<_SettingItem> _items = const [
    _SettingItem(title: 'Registrierung', body: 'Neue User, Vereine, Guardian-Konten und Profilabschluss steuern.', status: 'Offen', icon: Icons.person_add_outlined, color: AirmiusColors.blue),
    _SettingItem(title: 'Vereinsbeitritt', body: 'Mitgliedsanfragen, Pflichtfelder, Dokumente und Rückzugsmöglichkeit global erlauben.', status: 'Aktiv', icon: Icons.card_membership_outlined, color: AirmiusColors.green),
    _SettingItem(title: 'Wartungsmodus', body: 'Maintenance-Seite, Statushinweis, Support und technische Sperrung vorbereiten.', status: 'Aus', icon: Icons.construction_outlined, color: AirmiusColors.amber),
    _SettingItem(title: 'API & Tokens', body: 'Laravel API, Scopes, Tokenrotation, Webhooks und Feature-Flags verwalten.', status: 'Aktiv', icon: Icons.api_outlined, color: AirmiusColors.blueDeep),
    _SettingItem(title: 'Moderation', body: 'Melden, Sperren, Trust & Safety, Adminfreigaben und Risiko-Regeln steuern.', status: 'Streng', icon: Icons.shield_outlined, color: AirmiusColors.red),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Admin Plattformeinstellungen', subtitle: 'Registrierung, Vereine, Wartung, API, Public Pages, Moderation, Sprache und Systemflags.'),
                        const SizedBox(height: 16),
                        _SettingsHero(onSave: () => _toast('Plattformeinstellungen speichern vorbereitet')),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Globale Flags',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Registrierung offen', subtitle: 'Login/Register, Profilabschluss und E-Mail-Verifizierung erlauben.', value: _registrationOpen, onChanged: (value) => setState(() => _registrationOpen = value)),
                              _SwitchRow(title: 'Vereinsanfragen offen', subtitle: 'User dürfen Vereinen beitreten und Mitgliedsanträge senden.', value: _clubApplicationsOpen, onChanged: (value) => setState(() => _clubApplicationsOpen = value)),
                              _SwitchRow(title: 'Wartungsmodus', subtitle: 'Maintenance-Seite aktivieren und App-Zugriff begrenzen.', value: _maintenanceMode, onChanged: (value) => setState(() => _maintenanceMode = value)),
                              _SwitchRow(title: 'API aktiv', subtitle: 'Laravel API, Tokens, Webhooks und mobile Syncs erlauben.', value: _apiEnabled, onChanged: (value) => setState(() => _apiEnabled = value)),
                              _SwitchRow(title: 'Public Pages aktiv', subtitle: 'Guest-Seiten, Pricing, Marketplace, Blog und Landingpages anzeigen.', value: _publicPagesEnabled, onChanged: (value) => setState(() => _publicPagesEnabled = value)),
                              _SwitchRow(title: 'Strenge Moderation', subtitle: 'Meldungen, Sperren, Content-Freigaben und Risiko-Regeln verschaerfen.', value: _strictModeration, onChanged: (value) => setState(() => _strictModeration = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _SettingCard(item: item, onOpen: () => _toast('${item.title}: Setting-Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: () => _toast('Einstellungen speichern vorbereitet')),
                              AirmiusButton(label: 'API Tokens', icon: Icons.key_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiTokenManagerScreen()))),
                              AirmiusButton(label: 'Legal/Status', icon: Icons.gavel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalStatusCenterScreen()))),
                              AirmiusButton(label: 'Sprachen', icon: Icons.translate_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationCenterScreen()))),
                              AirmiusButton(label: 'System Admin', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SettingsHero extends StatelessWidget {
  const _SettingsHero({required this.onSave});

  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('ADMIN SETTINGS'), SizedBox(height: 4), Text('Plattformregeln zentral steuern', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: onSave),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Admin/Settings wird als mobile UI abgebildet: Registrierung, Vereinsbeitritt, Wartung, API, Public Pages, Moderation und Sprachen.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '6', label: 'Flags')), SizedBox(width: 10), Expanded(child: MetricCard(value: '5', label: 'Bereiche')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'API'))]),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _SettingCard extends StatelessWidget {
  const _SettingCard({required this.item, required this.onOpen});

  final _SettingItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _SettingItem {
  const _SettingItem({required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
