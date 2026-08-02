import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_contribution_rules_screen.dart';
import 'club_document_upload_manager_screen.dart';
import 'club_profile_editor_screen.dart';
import 'club_role_permissions_screen.dart';
import 'club_visibility_settings_screen.dart';

class ClubSetupOnboardingScreen extends StatefulWidget {
  const ClubSetupOnboardingScreen({super.key});

  @override
  State<ClubSetupOnboardingScreen> createState() =>
      _ClubSetupOnboardingScreenState();
}

class _ClubSetupOnboardingScreenState extends State<ClubSetupOnboardingScreen> {
  bool _profileDone = true;
  bool _visibilityDone = true;
  bool _rulesDone = false;
  bool _rolesDone = false;
  bool _documentsDone = false;

  final List<_SetupStep> _steps = const [
    _SetupStep(
      title: 'Vereinsprofil',
      body: 'Name, Logo, Beschreibung, Sportarten, Standort und Kontakt.',
      status: 'Bereit',
      icon: Icons.apartment_outlined,
      color: AirmiusColors.blue,
    ),
    _SetupStep(
      title: 'Sichtbarkeit',
      body:
          'Welche Felder, Beiträge, Kontakte und Teams öffentlich sichtbar sind.',
      status: 'Prüfen',
      icon: Icons.visibility_outlined,
      color: AirmiusColors.green,
    ),
    _SetupStep(
      title: 'Beitragsregeln',
      body:
          'Intervall, Zahlmethode, Preis, Dokumente und Mitgliedschaftstypen.',
      status: 'Offen',
      icon: Icons.receipt_long_outlined,
      color: AirmiusColors.amber,
    ),
    _SetupStep(
      title: 'Rollen & Rechte',
      body: 'Inhaber, Admins, Trainer, Finanzen und sensible Freigaben.',
      status: 'Offen',
      icon: Icons.admin_panel_settings_outlined,
      color: AirmiusColors.blueDeep,
    ),
    _SetupStep(
      title: 'Dokumente',
      body:
          'Datenschutz, Regeln, SEPA, Formulare und Dateimanager-Verknüpfung.',
      status: 'Offen',
      icon: Icons.folder_copy_outlined,
      color: AirmiusColors.red,
    ),
  ];

  int get _doneCount => [
    _profileDone,
    _visibilityDone,
    _rulesDone,
    _rolesDone,
    _documentsDone,
  ].where((value) => value).length;

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
                        const PageTitle(
                          title: 'Verein einrichten',
                          subtitle:
                              'Setup-Checkliste für Profil, Sichtbarkeit, Beiträge, Rollen, Dokumente und Startfreigabe.',
                        ),
                        const SizedBox(height: 16),
                        _SetupHero(
                          doneCount: _doneCount,
                          onContinue: () =>
                              _toast('Setup fortsetzen vorbereitet'),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Setup-Fortschritt',
                          child: Column(
                            children: [
                              _CheckRow(
                                title: 'Profil vervollständigt',
                                subtitle:
                                    'Vereinsname, Ort, Kontakt und Beschreibung.',
                                value: _profileDone,
                                onChanged: (value) =>
                                    setState(() => _profileDone = value),
                              ),
                              _CheckRow(
                                title: 'Sichtbarkeit entschieden',
                                subtitle:
                                    'Verein bestimmt, was öffentlich angezeigt wird.',
                                value: _visibilityDone,
                                onChanged: (value) =>
                                    setState(() => _visibilityDone = value),
                              ),
                              _CheckRow(
                                title: 'Beiträge konfiguriert',
                                subtitle:
                                    'Monatlich, quartalsweise, halbjährlich, jährlich, bar oder Überweisung.',
                                value: _rulesDone,
                                onChanged: (value) =>
                                    setState(() => _rulesDone = value),
                              ),
                              _CheckRow(
                                title: 'Rollen vergeben',
                                subtitle:
                                    'Admins, Trainer und Finanzen haben passende Rechte.',
                                value: _rolesDone,
                                onChanged: (value) =>
                                    setState(() => _rolesDone = value),
                              ),
                              _CheckRow(
                                title: 'Dokumente verknüpft',
                                subtitle:
                                    'Datenschutz, Regeln und Formulare liegen im Dateimanager.',
                                value: _documentsDone,
                                onChanged: (value) =>
                                    setState(() => _documentsDone = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final step in _steps) ...[
                          _SetupStepCard(
                            step: step,
                            onOpen: () => _openStep(step.title),
                          ),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Schnellaktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Profil',
                                icon: Icons.apartment_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ClubProfileEditorScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Sichtbarkeit',
                                icon: Icons.visibility_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        ClubVisibilitySettingsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Beiträge',
                                icon: Icons.receipt_long_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        ClubContributionRulesScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Rollen',
                                icon: Icons.admin_panel_settings_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ClubRolePermissionsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Dokumente',
                                icon: Icons.folder_copy_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        ClubDocumentUploadManagerScreen(),
                                  ),
                                ),
                              ),
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

  void _openStep(String title) {
    if (title == 'Vereinsprofil') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ClubProfileEditorScreen()),
      );
      return;
    }
    if (title == 'Sichtbarkeit') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ClubVisibilitySettingsScreen()),
      );
      return;
    }
    if (title == 'Beitragsregeln') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()),
      );
      return;
    }
    if (title == 'Rollen & Rechte') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ClubRolePermissionsScreen()),
      );
      return;
    }
    if (title == 'Dokumente') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ClubDocumentUploadManagerScreen()),
      );
      return;
    }
    _toast('$title vorbereitet');
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SetupHero extends StatelessWidget {
  const _SetupHero({required this.doneCount, required this.onContinue});

  final int doneCount;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    final progress = doneCount / 5;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF10243B), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
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
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('CLUB START'),
                    SizedBox(height: 4),
                    Text(
                      'Verein startklar machen',
                      style: TextStyle(
                        color: AirmiusColors.text,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: 'Weiter',
                icon: Icons.arrow_forward_outlined,
                onPressed: onContinue,
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Der Verein bekommt eine klare Setup-Strecke, damit Anträge, Dateien, Rechte, Beiträge und Sichtbarkeit vor dem Start sauber eingerichtet sind.',
            style: TextStyle(
              color: AirmiusColors.muted,
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 10,
              backgroundColor: AirmiusColors.input,
              color: AirmiusColors.blue,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            '$doneCount von 5 Schritten erledigt',
            style: const TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: value ? AirmiusColors.blue : AirmiusColors.border,
        ),
      ),
      child: Row(
        children: [
          Checkbox(
            value: value,
            onChanged: (next) => onChanged(next ?? false),
            activeColor: AirmiusColors.blue,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SetupStepCard extends StatelessWidget {
  const _SetupStepCard({required this.step, required this.onOpen});

  final _SetupStep step;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: step.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: step.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: step.color.withValues(alpha: .5)),
            ),
            child: Icon(step.icon, color: step.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(step.status, color: step.color),
                const SizedBox(height: 8),
                Text(
                  step.body,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ),
        ],
      ),
    );
  }
}

class _SetupStep {
  const _SetupStep({
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
