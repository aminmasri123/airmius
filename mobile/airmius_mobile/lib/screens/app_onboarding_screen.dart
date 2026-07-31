import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_preferences.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'account_management_screen.dart';
import 'file_operations_screen.dart';
import 'legal_support_operations_screen.dart';
import 'localization_center_screen.dart';
import 'maturity_center_screen.dart';
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
  bool _saving = false;
  bool _profileRestoreStarted = false;
  Future<JsonMap>? _serverOnboardingFuture;

  int get _done => [
    _privacy,
    _push,
    _location,
    _camera,
    _files,
  ].where((item) => item).length;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _serverOnboardingFuture ??= _loadServerOnboarding();
    if (!_profileRestoreStarted) {
      _profileRestoreStarted = true;
      _restoreOnboardingProfile();
    }
  }

  Future<void> _restoreOnboardingProfile() async {
    final profile = await AirmiusPreferences().readOnboardingProfile();
    if (!mounted || profile == null) return;
    final role = profile['role'];
    final workspace = profile['workspace'];
    final permissions = profile['permissions'];
    final savedPermissions = permissions is Map
        ? permissions.map((key, value) => MapEntry('$key', value == true))
        : <String, bool>{};
    setState(() {
      if (role is String && _onboardingRoles.contains(role)) _role = role;
      if (workspace is String && _onboardingWorkspaces.contains(workspace)) {
        _workspace = workspace;
      }
      _privacy = savedPermissions['privacy'] ?? _privacy;
      _push = savedPermissions['push'] ?? _push;
      _location = savedPermissions['location'] ?? _location;
      _camera = savedPermissions['camera'] ?? _camera;
      _files = savedPermissions['files'] ?? _files;
      _guardian = savedPermissions['guardian'] ?? _guardian;
    });
  }

  Future<JsonMap> _loadServerOnboarding() async {
    final services = AirmiusServicesScope.of(context);
    final response = await services
        .clientForSession(services.authState.session)
        .maturityOverview();
    final data = response['data'];
    return data is Map
        ? data.map((key, value) => MapEntry('$key', value))
        : <String, dynamic>{};
  }

  Future<void> _finishOnboarding(AirmiusScope scope) async {
    if (!_privacy || _saving) {
      if (!_privacy) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(scope.t('onboarding.privacyRequired'))),
        );
      }
      return;
    }
    setState(() => _saving = true);
    try {
      await AirmiusPreferences().writeOnboardingProfile(
        role: _role,
        workspace: _workspace,
        permissions: {
          'privacy': _privacy,
          'push': _push,
          'location': _location,
          'camera': _camera,
          'files': _files,
          'guardian': _guardian,
        },
      );
      await AirmiusPreferences().writePermissionOnboardingComplete(true);
      if (!mounted) return;
      setState(() {
        _saving = false;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(scope.t('onboarding.saved'))));
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(scope.t('onboarding.saveFailed'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final roleLabel = _onboardingRoleLabel(scope, _role);
    final workspaceLabel = _onboardingWorkspaceLabel(scope, _workspace);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('onboarding.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('onboarding.title'),
        subtitle: scope.t('onboarding.subtitle'),
        trailing: StatusPill(
          '$_done/5',
          color: _done >= 4
              ? Theme.of(context).colorScheme.secondary
              : Theme.of(context).colorScheme.tertiary,
        ),
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
                  Text(
                    scope.t('onboarding.heroTitle'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('onboarding.heroBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: MetricCard(
                          value: '$_done/5',
                          label: scope.t('onboarding.setup'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(
                          value: '4',
                          label: scope.t('onboarding.languages'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(
                          value: roleLabel,
                          label: scope.t('onboarding.role'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _ServerOnboardingPanel(future: _serverOnboardingFuture),
            const SizedBox(height: 16),
            _LanguageStartCard(scope: scope),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('onboarding.profileStart')),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: _role,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: scope.t('onboarding.role'),
                    ),
                    items: _onboardingRoles
                        .map(
                          (item) => DropdownMenuItem(
                            value: item,
                            child: Text(_onboardingRoleLabel(scope, item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _role = value ?? _role),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _workspace,
                    dropdownColor: airmiusSurfaceSoftColor(context),
                    decoration: InputDecoration(
                      labelText: scope.t('onboarding.startArea'),
                    ),
                    items: _onboardingWorkspaces
                        .map(
                          (item) => DropdownMenuItem(
                            value: item,
                            child: Text(_onboardingWorkspaceLabel(scope, item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _workspace = value ?? _workspace),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      AirmiusButton(
                        label: scope.t('onboarding.accountSecurity'),
                        icon: Icons.manage_accounts_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const AccountManagementScreen(),
                          ),
                        ),
                      ),
                      AirmiusButton(
                        label: scope.t('onboarding.findClub'),
                        icon: Icons.manage_search_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => SearchOperationsScreen(),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _StepCard(
              icon: Icons.groups_2_outlined,
              color: Theme.of(context).colorScheme.secondary,
              title: scope.t('onboarding.connectTitle'),
              body: scope.t('onboarding.connectBody'),
              status: scope.t('onboarding.clubContext'),
              actions: [
                AirmiusButton(
                  label: scope.t('onboarding.openSearch'),
                  icon: Icons.search,
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => SearchOperationsScreen()),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _StepCard(
              icon: Icons.privacy_tip_outlined,
              color: Theme.of(context).colorScheme.tertiary,
              title: scope.t('onboarding.privacyRulesTitle'),
              body: scope.t('onboarding.privacyRulesBody'),
              status: scope.t('onboarding.legal'),
              actions: [
                AirmiusButton(
                  label: scope.t('onboarding.legal'),
                  icon: Icons.gavel_outlined,
                  secondary: true,
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LegalSupportOperationsScreen(),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('onboarding.permissions')),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('onboarding.permissionExplanation'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 10),
                  _PermissionSwitch(
                    icon: Icons.privacy_tip_outlined,
                    title: scope.t('onboarding.privacyAccepted'),
                    body: scope.t('onboarding.privacyAcceptedBody'),
                    value: _privacy,
                    onChanged: (value) => setState(() => _privacy = value),
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  _PermissionSwitch(
                    icon: Icons.notifications_active_outlined,
                    title: scope.t('onboarding.push'),
                    body: scope.t('onboarding.pushBody'),
                    value: _push,
                    onChanged: (value) => setState(() => _push = value),
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  _PermissionSwitch(
                    icon: Icons.location_on_outlined,
                    title: scope.t('onboarding.location'),
                    body: scope.t('onboarding.locationBody'),
                    value: _location,
                    onChanged: (value) => setState(() => _location = value),
                    color: Theme.of(context).colorScheme.tertiary,
                  ),
                  _PermissionSwitch(
                    icon: Icons.camera_alt_outlined,
                    title: scope.t('onboarding.camera'),
                    body: scope.t('onboarding.cameraBody'),
                    value: _camera,
                    onChanged: (value) => setState(() => _camera = value),
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  _PermissionSwitch(
                    icon: Icons.folder_outlined,
                    title: scope.t('onboarding.files'),
                    body: scope.t('onboarding.filesBody'),
                    value: _files,
                    onChanged: (value) => setState(() => _files = value),
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  _PermissionSwitch(
                    icon: Icons.family_restroom_outlined,
                    title: scope.t('onboarding.guardian'),
                    body: scope.t('onboarding.guardianBody'),
                    value: _guardian,
                    onChanged: (value) => setState(() => _guardian = value),
                    color: Theme.of(context).colorScheme.tertiary,
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      AirmiusButton(
                        label: scope.t('onboarding.pushCenter'),
                        icon: Icons.notifications_active_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => NotificationChatOperationsScreen(
                              initialTab: 'Push',
                            ),
                          ),
                        ),
                      ),
                      AirmiusButton(
                        label: scope.t('onboarding.fileCenter'),
                        icon: Icons.folder_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => FileOperationsScreen(),
                          ),
                        ),
                      ),
                      AirmiusButton(
                        label: scope.t('onboarding.safety'),
                        icon: Icons.security_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                SafetyCommunityOperationsScreen(initialTab: 2),
                          ),
                        ),
                      ),
                      AirmiusButton(
                        label: scope.t('onboarding.wellbeing'),
                        icon: Icons.favorite_border_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => WellbeingOperationsScreen(),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              borderColor: Theme.of(
                context,
              ).colorScheme.secondary.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('onboarding.nextStep')),
                  const SizedBox(height: 8),
                  Text(
                    '${scope.t('onboarding.startArea')}: $workspaceLabel. '
                    '${scope.t('onboarding.role')}: $roleLabel. '
                    '${scope.t('onboarding.setup')}: $_done/5.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      AirmiusButton(
                        label: scope.t('onboarding.finish'),
                        icon: Icons.task_alt_outlined,
                        onPressed: _saving
                            ? null
                            : () => _finishOnboarding(scope),
                      ),
                      AirmiusButton(
                        label: scope.t('ops.hub'),
                        icon: Icons.hub_outlined,
                        secondary: true,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => OperationsHubScreen(),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

const _onboardingRoles = [
  'Sportler',
  'Vereinsadmin',
  'Trainer',
  'Guardian',
  'Gast',
  'Seller',
];

const _onboardingWorkspaces = [
  'Privat',
  'Verein',
  'Team',
  'Trainer',
  'Admin',
  'Public',
];

String _onboardingRoleLabel(AirmiusScope scope, String value) {
  final key = switch (value) {
    'Sportler' => 'onboarding.role.athlete',
    'Vereinsadmin' => 'onboarding.role.clubAdmin',
    'Trainer' => 'onboarding.role.trainer',
    'Guardian' => 'onboarding.role.guardian',
    'Gast' => 'onboarding.role.guest',
    'Seller' => 'onboarding.role.seller',
    _ => 'onboarding.role',
  };
  return scope.t(key);
}

String _onboardingWorkspaceLabel(AirmiusScope scope, String value) {
  final key = switch (value) {
    'Privat' => 'onboarding.workspace.private',
    'Verein' => 'onboarding.workspace.club',
    'Team' => 'onboarding.workspace.team',
    'Trainer' => 'onboarding.workspace.coach',
    'Admin' => 'onboarding.workspace.admin',
    'Public' => 'onboarding.workspace.public',
    _ => 'onboarding.startArea',
  };
  return scope.t(key);
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
            Icon(Icons.language_outlined, color: airmiusAccentColor(context)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    scope.t('onboarding.languages'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    scope.t('onboarding.languageBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final language in AirmiusLanguage.values)
              ChoiceChip(
                selected: scope.language == language,
                label: Text('${language.code} ${language.label}'),
                onSelected: (_) => scope.setLanguage(language),
                selectedColor: airmiusAccentColor(
                  context,
                ).withValues(alpha: .22),
                backgroundColor: airmiusSurfaceSoftColor(context),
                side: BorderSide(
                  color: scope.language == language
                      ? airmiusAccentColor(context)
                      : airmiusBorderColor(context),
                ),
                labelStyle: TextStyle(
                  color: scope.language == language
                      ? airmiusAccentColor(context)
                      : airmiusMutedColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: scope.t('onboarding.languageCenter'),
          icon: Icons.translate_outlined,
          secondary: true,
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => LocalizationCenterScreen()),
          ),
        ),
      ],
    ),
  );
}

class _ServerOnboardingPanel extends StatelessWidget {
  const _ServerOnboardingPanel({required this.future});

  final Future<JsonMap>? future;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      title: scope.t('onboarding.serverTitle'),
      child: FutureBuilder<JsonMap>(
        future: future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const LinearProgressIndicator(minHeight: 6);
          }
          if (snapshot.hasError) {
            final message = snapshot.error is AirmiusApiException
                ? (snapshot.error as AirmiusApiException).userMessage
                : scope.t('onboarding.serverUnavailable');
            return Text(
              message,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            );
          }

          final data = snapshot.data ?? const <String, dynamic>{};
          final actions = data['next_actions'] is List
              ? (data['next_actions'] as List).whereType<Map>().toList()
              : const <Map>[];
          final done = actions.where((item) => item['done'] == true).length;
          final completion = data['scores'] is Map
              ? (data['scores'] as Map)['onboarding']
              : null;
          final completionText = completion is num
              ? '${completion.round()}%'
              : '$done/${actions.length}';

          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(child: Text(scope.t('onboarding.serverBody'))),
                  StatusPill(
                    completionText,
                    color: done == actions.length && actions.isNotEmpty
                        ? Theme.of(context).colorScheme.secondary
                        : Theme.of(context).colorScheme.tertiary,
                  ),
                ],
              ),
              const SizedBox(height: 10),
              if (actions.isEmpty)
                Text(scope.t('onboarding.serverEmpty'))
              else
                for (final action in actions.take(6))
                  _ServerChecklistRow(
                    label: _serverOnboardingLabel(
                      scope,
                      '${action['key'] ?? ''}',
                      '${action['label'] ?? ''}',
                    ),
                    done: action['done'] == true,
                  ),
              const SizedBox(height: 10),
              AirmiusButton(
                label: scope.t('onboarding.openProgress'),
                icon: Icons.insights_outlined,
                secondary: true,
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => MaturityCenterScreen()),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ServerChecklistRow extends StatelessWidget {
  const _ServerChecklistRow({required this.label, required this.done});

  final String label;
  final bool done;

  @override
  Widget build(BuildContext context) {
    final color = done
        ? Theme.of(context).colorScheme.secondary
        : Theme.of(context).colorScheme.tertiary;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(
            done ? Icons.check_circle : Icons.radio_button_unchecked,
            size: 20,
            color: color,
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(label)),
          Text(
            AirmiusScope.of(
              context,
            ).t(done ? 'maturity.complete' : 'maturity.open'),
            style: TextStyle(color: color, fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

String _serverOnboardingLabel(AirmiusScope scope, String key, String fallback) {
  final translated = scope.t('onboarding.step.$key');
  return translated == 'onboarding.step.$key' && fallback.isNotEmpty
      ? fallback
      : translated;
}

class _StepCard extends StatelessWidget {
  const _StepCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.body,
    required this.status,
    this.actions = const [],
  });

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
              decoration: BoxDecoration(
                color: color.withValues(alpha: .13),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: color.withValues(alpha: .45)),
              ),
              child: Icon(icon, color: color),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    body,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
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
  const _PermissionSwitch({
    required this.icon,
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
    required this.color,
  });

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
    activeThumbColor: color,
    contentPadding: EdgeInsets.zero,
    secondary: Icon(icon, color: color),
    title: Text(
      title,
      style: TextStyle(
        color: airmiusTextColor(context),
        fontWeight: FontWeight.w900,
      ),
    ),
    subtitle: Text(
      body,
      style: TextStyle(color: airmiusMutedColor(context), height: 1.3),
    ),
  );
}
