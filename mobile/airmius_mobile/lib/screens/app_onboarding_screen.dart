import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'auth_flows_screen.dart';
import 'file_operations_screen.dart';
import 'legal_support_operations_screen.dart';
import 'localization_center_screen.dart';
import 'notification_chat_operations_screen.dart';
import 'operations_hub_screen.dart';
import 'safety_community_operations_screen.dart';
import 'search_operations_screen.dart';
import 'wellbeing_operations_screen.dart';

class AppOnboardingScreen extends StatefulWidget {
  const AppOnboardingScreen({super.key});

  @override
  State<AppOnboardingScreen> createState() => _AppOnboardingScreenState();
}

class _AppOnboardingScreenState extends State<AppOnboardingScreen> {
  String _role = 'Sportler';
  String _workspace = 'Privat';
  bool _privacy = true;
  bool _push = true;
  bool _location = false;
  bool _camera = false;
  bool _files = true;
  bool _guardian = false;

  int get _done => [_privacy, _push, _location, _camera, _files].where((item) => item).length;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(scope.t('onboarding.title'), style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: scope.t('onboarding.title'),
        subtitle: scope.t('onboarding.subtitle'),
        trailing: StatusPill('$_done/5', color: _done >= 4 ? AirmiusColors.green : AirmiusColors.amber),
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
                  Text(scope.t('onboarding.heroTitle'), style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  Text(scope.t('onboarding.heroBody'), style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: [
                    Expanded(child: MetricCard(value: '$_done/5', label: 'Setup')),
                    const SizedBox(width: 10),
                    const Expanded(child: MetricCard(value: '4', label: 'Sprachen')),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: _role, label: 'Rolle')),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _LanguageStartCard(scope: scope),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Profilstart'),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: _role,
                    dropdownColor: AirmiusColors.cardSoft,
                    decoration: const InputDecoration(labelText: 'Rolle'),
                    items: const ['Sportler', 'Vereinsadmin', 'Trainer', 'Guardian', 'Gast', 'Seller'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                    onChanged: (value) => setState(() => _role = value ?? _role),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: _workspace,
                    dropdownColor: AirmiusColors.cardSoft,
                    decoration: const InputDecoration(labelText: 'Startbereich'),
                    items: const ['Privat', 'Verein', 'Team', 'Trainer', 'Admin', 'Public'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                    onChanged: (value) => setState(() => _workspace = value ?? _workspace),
                  ),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    AirmiusButton(label: 'Konto-Flows', icon: Icons.manage_accounts_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuthFlowsScreen()))),
                    AirmiusButton(label: 'Verein suchen', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SearchOperationsScreen()))),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _StepCard(
              icon: Icons.groups_2_outlined,
              color: AirmiusColors.green,
              title: 'Verein oder Team verbinden',
              body: 'Nutzer können direkt nach Vereinen, Teams oder Personen suchen. Für Vereinsbeitritt fuehrt der Flow später in Antrag, Dokumente und Zahlungsdaten.',
              status: 'Club-Kontext',
              actions: [
                AirmiusButton(label: 'Suche öffnen', icon: Icons.search, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SearchOperationsScreen()))),
              ],
            ),
            const SizedBox(height: 12),
            _StepCard(
              icon: Icons.privacy_tip_outlined,
              color: AirmiusColors.amber,
              title: 'Datenschutz und Regeln zuerst',
              body: 'AGB, Datenschutz, Jugendschutz, Widerruf, Vereinsregeln und verknuepfte Dokumente werden vor kritischen Aktionen sichtbar gemacht.',
              status: 'Legal',
              actions: [
                AirmiusButton(label: 'Legal Center', icon: Icons.gavel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalSupportOperationsScreen()))),
              ],
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('onboarding.permissions')),
                  const SizedBox(height: 8),
                  const Text('Diese Berechtigungen werden später nativ auf Android/iOS abgefragt. Jetzt ist die UI vorbereitet und erklaert jeden Grund im Airmius-Stil.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 10),
                  _PermissionSwitch(icon: Icons.privacy_tip_outlined, title: 'Datenschutz & AGB akzeptiert', body: 'Erforderlich für Konto, Mitgliedsantrag, Kontakt und API-Verarbeitung.', value: _privacy, onChanged: (value) => setState(() => _privacy = value), color: AirmiusColors.green),
                  _PermissionSwitch(icon: Icons.notifications_active_outlined, title: 'Push erlauben', body: 'Mitgliedschaftsanfragen, Chat, Zahlungen, Guardian und Safety-Hinweise.', value: _push, onChanged: (value) => setState(() => _push = value), color: AirmiusColors.blue),
                  _PermissionSwitch(icon: Icons.location_on_outlined, title: 'Standort erlauben', body: 'Sportkarte, Live-Track, Fahrgemeinschaft, Treffpunkt und Orte.', value: _location, onChanged: (value) => setState(() => _location = value), color: AirmiusColors.amber),
                  _PermissionSwitch(icon: Icons.camera_alt_outlined, title: 'Kamera/Fotos erlauben', body: 'Profilbild, Vereinsdokumente, Medien, Nutrition-Fotoanalyse und Retouren.', value: _camera, onChanged: (value) => setState(() => _camera = value), color: AirmiusColors.blue),
                  _PermissionSwitch(icon: Icons.folder_outlined, title: 'Dateien erlauben', body: 'Mitgliedsantrag, Datenschutzdokumente, Chat-Anhaenge und Dateimanager.', value: _files, onChanged: (value) => setState(() => _files = value), color: AirmiusColors.green),
                  _PermissionSwitch(icon: Icons.family_restroom_outlined, title: 'Guardian Flow aktivieren', body: 'Für Minderjaehrige, Elternzugang, Consent und Maturity-Gates.', value: _guardian, onChanged: (value) => setState(() => _guardian = value), color: AirmiusColors.amber),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    AirmiusButton(label: 'Push Center', icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Push')))),
                    AirmiusButton(label: 'Dateien', icon: Icons.folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileOperationsScreen()))),
                    AirmiusButton(label: 'Safety', icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen(initialTab: 2)))),
                    AirmiusButton(label: 'Wellbeing', icon: Icons.favorite_border_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WellbeingOperationsScreen()))),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Naechster Schritt'),
                  const SizedBox(height: 8),
                  Text('Startbereich: $_workspace. Rolle: $_role. Setup: $_done von 5 Kernberechtigungen vorbereitet.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    AirmiusButton(label: 'Onboarding abschließen', icon: Icons.task_alt_outlined, onPressed: () => openUiAction(context, title: 'Onboarding abschließen', body: 'Rolle $_role, Startbereich $_workspace, Datenschutz, Berechtigungen und Sprache für spätere Laravel-API speichern.', status: 'Onboarding', icon: Icons.task_alt_outlined)),
                    AirmiusButton(label: 'Operations Hub', icon: Icons.hub_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OperationsHubScreen()))),
                  ]),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LanguageStartCard extends StatelessWidget {
  const _LanguageStartCard({required this.scope});

  final AirmiusScope scope;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.language_outlined, color: AirmiusColors.blue),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(scope.t('onboarding.languages'), style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                      const SizedBox(height: 4),
                      const Text('Die App startet mehrsprachig und respektiert bei Arabisch direkt RTL-Ausrichtung.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final language in AirmiusLanguage.values)
                ChoiceChip(
                  selected: scope.language == language,
                  label: Text('${language.code} ${language.label}'),
                  onSelected: (_) => scope.setLanguage(language),
                  selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: scope.language == language ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: scope.language == language ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
            const SizedBox(height: 12),
            AirmiusButton(label: 'Sprachzentrale', icon: Icons.translate_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationCenterScreen()))),
          ],
        ),
      );
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.icon, required this.color, required this.title, required this.body, required this.status, this.actions = const []});

  final IconData icon;
  final Color color;
  final String title;
  final String body;
  final String status;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: color.withValues(alpha: .44),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: color.withValues(alpha: .45))),
                  child: Icon(icon, color: color),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                      const SizedBox(height: 5),
                      Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                      const SizedBox(height: 10),
                      StatusPill(status, color: color),
                    ],
                  ),
                ),
              ],
            ),
            if (actions.isNotEmpty) ...[
              const SizedBox(height: 12),
              Wrap(spacing: 8, runSpacing: 8, children: actions),
            ],
          ],
        ),
      );
}

class _PermissionSwitch extends StatelessWidget {
  const _PermissionSwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

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
