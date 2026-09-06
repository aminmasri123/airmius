import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/admin_access_denied_screen.dart';
import 'admin_backoffice_screen.dart';
import 'admin_mail_center_screen.dart';
import 'admin_platform_settings_screen.dart';
import 'outfit_operations_screen.dart';

typedef JsonMap = AirmiusJson;

class PlatformAdminScreen extends StatefulWidget {
  const PlatformAdminScreen({super.key, this.initialSection = 'users'});

  final String initialSection;

  @override
  State<PlatformAdminScreen> createState() => _PlatformAdminScreenState();
}

class _PlatformAdminScreenState extends State<PlatformAdminScreen> {
  Future<JsonMap>? _future;
  String _section = 'users';
  bool _busy = false;
  bool _didRevealInitialSection = false;
  final GlobalKey _sectionAnchorKey = GlobalKey();

  static const _validSections = {
    'users',
    'clubs',
    'sports',
    'badges',
    'roles',
    'moderation',
    'gamification',
  };

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!AirmiusModuleAccess.canOpenPlatformAdmin(
      AirmiusServicesScope.of(context).authState.user,
    )) {
      return;
    }
    _future ??= _load();
  }

  @override
  void initState() {
    super.initState();
    _section = _validSections.contains(widget.initialSection)
        ? widget.initialSection
        : 'users';
  }

  Future<JsonMap> _load() async {
    final response = await _client.adminPlatformDashboard();
    return _platformMap(response['data']);
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  void _revealInitialSection() {
    if (_didRevealInitialSection || widget.initialSection == 'users') return;
    _didRevealInitialSection = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final anchorContext = _sectionAnchorKey.currentContext;
      if (!mounted || anchorContext == null) return;
      Scrollable.ensureVisible(anchorContext, alignment: 0.05);
    });
  }

  Future<void> _run(
    Future<dynamic> Function() action, {
    required String success,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!AirmiusModuleAccess.canOpenPlatformAdmin(
      AirmiusServicesScope.of(context).authState.user,
    )) {
      return const AdminAccessDeniedScreen();
    }

    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('platformAdmin.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return _PlatformFailure(
              message: error is AirmiusApiException
                  ? error.userMessage
                  : t('platformAdmin.loadFailed'),
              onRetry: _reload,
            );
          }
          final data = snapshot.data ?? const {};
          _revealInitialSection();
          return PageFrame(
            title: t('platformAdmin.title'),
            subtitle: t('platformAdmin.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _hero(data),
                const SizedBox(height: 14),
                KeyedSubtree(key: _sectionAnchorKey, child: _tabs()),
                if (_busy) ...[
                  const SizedBox(height: 10),
                  const LinearProgressIndicator(minHeight: 3),
                ],
                const SizedBox(height: 14),
                _content(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final summary = _platformMap(data['summary']);
    final user = AirmiusServicesScope.of(context).authState.user;
    final canOpenBackoffice =
        user?.can('subscriptions.manage') == true ||
        user?.can('billing.manage') == true ||
        user?.can('finance.view') == true ||
        user?.can('finance.edit') == true ||
        user?.can('system.manage') == true;
    final canOpenMail = user?.can('system.manage') == true;
    final canOpenOutfits = user?.can('outfit-subscriptions.manage') == true;
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('platformAdmin.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('platformAdmin.headline'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 7),
          Text(t('platformAdmin.body')),
          if (canOpenBackoffice || canOpenMail || canOpenOutfits) ...[
            const SizedBox(height: 14),
            Wrap(
              spacing: 9,
              runSpacing: 9,
              children: [
                if (canOpenBackoffice)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AdminBackofficeScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.account_balance_wallet_outlined),
                    label: Text(t('backoffice.title')),
                  ),
                if (canOpenOutfits)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const OutfitOperationsScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.checkroom_outlined),
                    label: Text(t('outfitAdmin.title')),
                  ),
                if (canOpenMail)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AdminMailCenterScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.mark_email_read_outlined),
                    label: Text(t('mailAdmin.title')),
                  ),
                if (canOpenMail)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AdminPlatformSettingsScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.settings_suggest_outlined),
                    label: Text(t('systemAdmin.title')),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _PlatformMetric(
                value: '${_platformInt(summary['users'])}',
                label: t('platformAdmin.users'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['users_suspended'])}',
                label: t('platformAdmin.suspended'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['clubs_pending'])}',
                label: t('platformAdmin.pendingClubs'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['sports_active'])}',
                label: t('platformAdmin.activeSports'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['badges'])}',
                label: t('platformAdmin.badges'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['roles'])}',
                label: t('platformAdmin.roles'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['moderation_open'])}',
                label: t('platformAdmin.moderationOpen'),
              ),
              _PlatformMetric(
                value: '${_platformInt(summary['gamification_active'])}',
                label: t('platformAdmin.activeRules'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tabs() {
    final t = AirmiusScope.of(context).t;
    final sections = {
      'users': t('platformAdmin.users'),
      'clubs': t('platformAdmin.clubReview'),
      'sports': t('platformAdmin.sports'),
      'badges': t('platformAdmin.badges'),
      'roles': t('platformAdmin.roles'),
      'moderation': t('platformAdmin.moderation'),
      'gamification': t('platformAdmin.gamification'),
    };
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: sections.entries
            .map(
              (entry) => Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  selected: _section == entry.key,
                  label: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Text(entry.value),
                  ),
                  onSelected: (_) => setState(() => _section = entry.key),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _content(JsonMap data) => switch (_section) {
    'clubs' => _clubs(data),
    'sports' => _sports(data),
    'badges' => _badges(data),
    'roles' => _roles(data),
    'moderation' => _moderation(data),
    'gamification' => _gamification(data),
    _ => _users(data),
  };

  Widget _users(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final abilities = _platformMap(data['abilities']);
    final canEdit = abilities['users_edit'] == true;
    final users = _platformMaps(data['users']);
    if (users.isEmpty) {
      return _PlatformEmpty(
        icon: Icons.people_outline,
        text: t('platformAdmin.noUsers'),
      );
    }
    return Column(
      children: users
          .map(
            (user) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.person_outline,
                title: _platformText(
                  user['name'],
                  fallback: t('platformAdmin.user'),
                ),
                subtitle: _platformText(user['email']),
                status: _platformText(
                  user['account_status'],
                  fallback: 'active',
                ),
                details: [
                  if (_platformStrings(user['roles']).isNotEmpty)
                    _platformStrings(user['roles']).join(', '),
                  user['email_verified'] == true
                      ? t('platformAdmin.emailVerified')
                      : t('platformAdmin.emailUnverified'),
                  user['two_factor_enabled'] == true
                      ? t('platformAdmin.twoFactorOn')
                      : t('platformAdmin.twoFactorOff'),
                ],
                actions: canEdit && user['can_change_status'] == true
                    ? [
                        if (_platformText(user['account_status']) ==
                            'suspended')
                          OutlinedButton.icon(
                            onPressed: _busy ? null : () => _liftUser(user),
                            icon: const Icon(Icons.lock_open_outlined),
                            label: Text(t('platformAdmin.lift')),
                          )
                        else
                          OutlinedButton.icon(
                            onPressed: _busy ? null : () => _suspendUser(user),
                            icon: const Icon(Icons.block_outlined),
                            label: Text(t('platformAdmin.suspend')),
                          ),
                      ]
                    : const [],
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _clubs(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final clubs = _platformMaps(data['clubs']);
    if (clubs.isEmpty) {
      return _PlatformEmpty(
        icon: Icons.approval_outlined,
        text: t('platformAdmin.noClubs'),
      );
    }
    return Column(
      children: clubs
          .map(
            (club) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.approval_outlined,
                title: _platformText(
                  club['name'],
                  fallback: t('platformAdmin.club'),
                ),
                subtitle: [
                  _platformText(club['city']),
                  _platformText(club['country']),
                ].where((value) => value.isNotEmpty).join(' · '),
                status: _platformText(
                  club['verification_status'],
                  fallback: 'pending',
                ),
                details: [
                  if (_platformText(
                    club['requested_official_club_number'],
                  ).isNotEmpty)
                    '${t('platformAdmin.requestedNumber')}: ${_platformText(club['requested_official_club_number'])}',
                  if (_platformMap(club['owner']).isNotEmpty)
                    '${t('platformAdmin.owner')}: ${_platformText(_platformMap(club['owner'])['name'])}',
                ],
                actions: _platformText(club['verification_status']) == 'pending'
                    ? [
                        FilledButton.icon(
                          onPressed: _busy ? null : () => _approveClub(club),
                          icon: const Icon(Icons.check_outlined),
                          label: Text(t('platformAdmin.approve')),
                        ),
                        OutlinedButton.icon(
                          onPressed: _busy ? null : () => _rejectClub(club),
                          icon: const Icon(Icons.close_outlined),
                          label: Text(t('platformAdmin.reject')),
                        ),
                      ]
                    : const [],
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _sports(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final sports = _platformMaps(data['sports']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: FilledButton.icon(
            onPressed: _busy ? null : () => _editSport(),
            icon: const Icon(Icons.add_outlined),
            label: Text(t('platformAdmin.addSport')),
          ),
        ),
        const SizedBox(height: 12),
        if (sports.isEmpty)
          _PlatformEmpty(
            icon: Icons.sports_outlined,
            text: t('platformAdmin.noSports'),
          )
        else
          ...sports.map(
            (sport) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.sports_outlined,
                title: _platformText(sport['name']),
                subtitle: _platformText(
                  sport['category'],
                  fallback: t('platformAdmin.noCategory'),
                ),
                status: sport['is_active'] == true
                    ? t('platformAdmin.active')
                    : t('platformAdmin.inactive'),
                details: [
                  '${t('platformAdmin.slug')}: ${_platformText(sport['slug'])}',
                  '${t('platformAdmin.usage')}: ${_platformInt(sport['usage_count'])}',
                  '${t('platformAdmin.skills')}: ${_platformInt(sport['skills_count'])}',
                ],
                actions: [
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _editSport(sport),
                    icon: const Icon(Icons.edit_outlined),
                    label: Text(t('common.edit')),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy || _platformInt(sport['usage_count']) > 0
                        ? null
                        : () => _deleteSport(sport),
                    icon: const Icon(Icons.delete_outline),
                    label: Text(t('common.delete')),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _badges(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final badges = _platformMaps(data['badges']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: FilledButton.icon(
            onPressed: _busy ? null : () => _editBadge(),
            icon: const Icon(Icons.add_outlined),
            label: Text(t('platformAdmin.addBadge')),
          ),
        ),
        const SizedBox(height: 12),
        if (badges.isEmpty)
          _PlatformEmpty(
            icon: Icons.workspace_premium_outlined,
            text: t('platformAdmin.noBadges'),
          )
        else
          ...badges.map(
            (badge) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.workspace_premium_outlined,
                title: _platformText(badge['name']),
                subtitle: _platformText(badge['description']),
                status: _platformText(badge['actor_type']),
                details: [
                  '${t('platformAdmin.key')}: ${_platformText(badge['key'])}',
                  '${t('platformAdmin.trigger')}: ${_platformText(badge['trigger'])}',
                  '${t('platformAdmin.threshold')}: ${_platformInt(badge['threshold'])}',
                  '${t('platformAdmin.awarded')}: ${_platformInt(badge['users_count'])}',
                ],
                actions: [
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _editBadge(badge),
                    icon: const Icon(Icons.edit_outlined),
                    label: Text(t('common.edit')),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy || _platformInt(badge['users_count']) > 0
                        ? null
                        : () => _deleteBadge(badge),
                    icon: const Icon(Icons.delete_outline),
                    label: Text(t('common.delete')),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _roles(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final abilities = _platformMap(data['abilities']);
    final canManage = abilities['roles_assign'] == true;
    final canCreatePermission = abilities['permissions_create'] == true;
    final roles = _platformMaps(data['roles']);
    final permissionGroups = _platformMaps(data['permission_groups']);
    if (!canManage) {
      return _PlatformEmpty(
        icon: Icons.admin_panel_settings_outlined,
        text: t('platformAdmin.rolesForbidden'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 9,
          runSpacing: 9,
          children: [
            FilledButton.icon(
              onPressed: _busy
                  ? null
                  : () => _editRole(permissionGroups: permissionGroups),
              icon: const Icon(Icons.add_moderator_outlined),
              label: Text(t('platformAdmin.addRole')),
            ),
            if (canCreatePermission)
              OutlinedButton.icon(
                onPressed: _busy ? null : _createPermission,
                icon: const Icon(Icons.key_outlined),
                label: Text(t('platformAdmin.addPermission')),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (roles.isEmpty)
          _PlatformEmpty(
            icon: Icons.admin_panel_settings_outlined,
            text: t('platformAdmin.noRoles'),
          )
        else
          ...roles.map((role) {
            final permissions = _platformMaps(role['permissions']);
            return Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.admin_panel_settings_outlined,
                title: _platformText(role['name']),
                subtitle: _platformText(
                  role['description'],
                  fallback: t('platformAdmin.noDescription'),
                ),
                status: role['is_system'] == true
                    ? t('platformAdmin.systemRole')
                    : t('platformAdmin.customRole'),
                details: [
                  '${t('platformAdmin.assignedUsers')}: ${_platformInt(role['users_count'])}',
                  '${t('platformAdmin.permissions')}: ${permissions.length}',
                  if (permissions.isNotEmpty)
                    permissions
                        .take(5)
                        .map((permission) => _platformText(permission['name']))
                        .join(' · '),
                ],
                actions: [
                  OutlinedButton.icon(
                    onPressed: _busy || role['can_edit'] != true
                        ? null
                        : () => _editRole(
                            role: role,
                            permissionGroups: permissionGroups,
                          ),
                    icon: const Icon(Icons.edit_outlined),
                    label: Text(t('common.edit')),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy || role['can_delete'] != true
                        ? null
                        : () => _deleteRole(role),
                    icon: const Icon(Icons.delete_outline),
                    label: Text(t('common.delete')),
                  ),
                ],
              ),
            );
          }),
      ],
    );
  }

  Widget _moderation(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final moderation = _platformMap(data['moderation']);
    final flags = _platformMaps(moderation['flags']);
    final reports = _platformMaps(moderation['reports']);
    final warnings = _platformMaps(moderation['warnings']);
    if (flags.isEmpty && reports.isEmpty && warnings.isEmpty) {
      return _PlatformEmpty(
        icon: Icons.shield_outlined,
        text: t('platformAdmin.noModerationCases'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (flags.isNotEmpty) ...[
          _PlatformSectionTitle(
            icon: Icons.smart_toy_outlined,
            title: t('platformAdmin.automaticFlags'),
            count: flags.length,
          ),
          const SizedBox(height: 9),
          ...flags.map(
            (flag) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.flag_outlined,
                title: _platformText(
                  _platformMap(flag['content'])['text'],
                  fallback: t('platformAdmin.moderationCase'),
                ),
                subtitle: _platformText(
                  _platformMap(flag['content'])['author'],
                  fallback: _platformText(_platformMap(flag['user'])['name']),
                ),
                status: _platformText(flag['status'], fallback: 'open'),
                details: [
                  '${t('platformAdmin.severity')}: ${_platformText(flag['severity'])}',
                  '${t('platformAdmin.source')}: ${_platformText(flag['source'])}',
                  if (_platformStrings(flag['categories']).isNotEmpty)
                    _platformStrings(flag['categories']).join(', '),
                ],
                actions: [
                  FilledButton.tonalIcon(
                    onPressed: _busy
                        ? null
                        : () => _decideModeration(item: flag, kind: 'flag'),
                    icon: const Icon(Icons.fact_check_outlined),
                    label: Text(t('platformAdmin.review')),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 5),
        ],
        if (reports.isNotEmpty) ...[
          _PlatformSectionTitle(
            icon: Icons.report_outlined,
            title: t('platformAdmin.userReports'),
            count: reports.length,
          ),
          const SizedBox(height: 9),
          ...reports.map(
            (report) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.report_outlined,
                title: _platformText(
                  _platformMap(report['content'])['text'],
                  fallback: _platformText(report['reason']),
                ),
                subtitle: _platformText(report['details']),
                status: _platformText(report['status'], fallback: 'open'),
                details: [
                  '${t('platformAdmin.reason')}: ${_platformText(report['reason'])}',
                  if (_platformMap(report['reporter']).isNotEmpty)
                    '${t('platformAdmin.reporter')}: ${_platformText(_platformMap(report['reporter'])['name'])}',
                  if (_platformText(report['appeal_status']).isNotEmpty)
                    '${t('platformAdmin.appeal')}: ${_platformText(report['appeal_status'])}',
                ],
                actions: [
                  FilledButton.tonalIcon(
                    onPressed: _busy
                        ? null
                        : () => _decideModeration(item: report, kind: 'report'),
                    icon: const Icon(Icons.fact_check_outlined),
                    label: Text(t('platformAdmin.review')),
                  ),
                  if (_platformText(report['appeal_status']) == 'pending')
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _decideAppeal(report),
                      icon: const Icon(Icons.balance_outlined),
                      label: Text(t('platformAdmin.decideAppeal')),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 5),
        ],
        if (warnings.isNotEmpty) ...[
          _PlatformSectionTitle(
            icon: Icons.warning_amber_outlined,
            title: t('platformAdmin.accountWarnings'),
            count: warnings.length,
          ),
          const SizedBox(height: 9),
          ...warnings.map(
            (warning) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: Icons.warning_amber_outlined,
                title: _platformText(
                  _platformMap(warning['user'])['name'],
                  fallback: t('platformAdmin.user'),
                ),
                subtitle: _platformText(warning['reason']),
                status: _platformText(warning['severity']),
                details: [
                  '${t('platformAdmin.points')}: ${_platformInt(warning['points'])}',
                  _platformText(_platformMap(warning['user'])['email']),
                ].where((value) => value.isNotEmpty).toList(),
                actions: const [],
              ),
            ),
          ),
        ],
      ],
    );
  }

  Widget _gamification(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final rules = _platformMaps(data['gamification_rules']);
    if (rules.isEmpty) {
      return _PlatformEmpty(
        icon: Icons.auto_awesome_outlined,
        text: t('platformAdmin.noGamificationRules'),
      );
    }
    return Column(
      children: rules
          .map(
            (rule) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _PlatformCard(
                icon: rule['is_penalty'] == true
                    ? Icons.remove_circle_outline
                    : Icons.add_circle_outline,
                title: _platformText(rule['label']),
                subtitle: _platformText(rule['description']),
                status: rule['is_active'] == true
                    ? t('platformAdmin.active')
                    : t('platformAdmin.inactive'),
                details: [
                  '${t('platformAdmin.actor')}: ${_platformText(rule['actor_type'])}',
                  '${t('platformAdmin.category')}: ${_platformText(rule['category'])}',
                  '${t('platformAdmin.xp')}: ${_platformInt(rule['xp_amount'])}',
                  '${t('platformAdmin.trust')}: ${_platformInt(rule['trust_delta'])}',
                  if (rule['daily_limit'] != null)
                    '${t('platformAdmin.dailyLimit')}: ${_platformInt(rule['daily_limit'])}',
                ],
                actions: [
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _editRule(rule),
                    icon: const Icon(Icons.tune_outlined),
                    label: Text(t('common.edit')),
                  ),
                ],
              ),
            ),
          )
          .toList(),
    );
  }

  Future<void> _suspendUser(JsonMap user) async {
    final t = AirmiusScope.of(context).t;
    final days = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [1, 3, 7, 14, 30, 90]
              .map(
                (value) => ListTile(
                  title: Text('$value ${t('platformAdmin.days')}'),
                  onTap: () => Navigator.pop(sheetContext, value),
                ),
              )
              .toList(),
        ),
      ),
    );
    if (days == null || !mounted) return;
    final reason = await _askText(
      title: t('platformAdmin.suspendUser'),
      label: t('platformAdmin.reason'),
    );
    if (reason == null) return;
    await _run(
      () => _client.adminUpdateUserStatus(_platformInt(user['id']), {
        'action': 'suspend',
        'days': days,
        'reason': reason,
      }),
      success: t('platformAdmin.userUpdated'),
    );
  }

  Future<void> _liftUser(JsonMap user) async {
    final t = AirmiusScope.of(context).t;
    await _run(
      () => _client.adminUpdateUserStatus(_platformInt(user['id']), {
        'action': 'lift',
      }),
      success: t('platformAdmin.userUpdated'),
    );
  }

  Future<void> _approveClub(JsonMap club) async {
    final t = AirmiusScope.of(context).t;
    final result = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _ClubApprovalDialog(club: club),
    );
    if (result == null) return;
    await _run(
      () => _client.adminApproveClub(_platformInt(club['id']), result),
      success: t('platformAdmin.clubApproved'),
    );
  }

  Future<void> _rejectClub(JsonMap club) async {
    final t = AirmiusScope.of(context).t;
    final note = await _askText(
      title: t('platformAdmin.rejectClub'),
      label: t('platformAdmin.reason'),
    );
    if (note == null) return;
    await _run(
      () => _client.adminRejectClub(_platformInt(club['id']), {
        'verification_notes': note,
      }),
      success: t('platformAdmin.clubRejected'),
    );
  }

  Future<void> _editSport([JsonMap? sport]) async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _SportAdminDialog(sport: sport),
    );
    if (payload == null) return;
    await _run(
      () => sport == null
          ? _client.adminCreateSport(payload)
          : _client.adminUpdateSport(_platformInt(sport['id']), payload),
      success: t('platformAdmin.sportSaved'),
    );
  }

  Future<void> _deleteSport(JsonMap sport) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await confirmDanger(
      context,
      t('platformAdmin.deleteSport'),
      t('platformAdmin.deleteSportBody'),
    );
    if (!confirmed) return;
    await _run(
      () => _client.adminDeleteSport(_platformInt(sport['id'])),
      success: t('platformAdmin.sportDeleted'),
    );
  }

  Future<void> _editBadge([JsonMap? badge]) async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _BadgeAdminDialog(badge: badge),
    );
    if (payload == null) return;
    await _run(
      () => badge == null
          ? _client.adminCreateBadge(payload)
          : _client.adminUpdateBadge(_platformInt(badge['id']), payload),
      success: t('platformAdmin.badgeSaved'),
    );
  }

  Future<void> _deleteBadge(JsonMap badge) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await confirmDanger(
      context,
      t('platformAdmin.deleteBadge'),
      t('platformAdmin.deleteBadgeBody'),
    );
    if (!confirmed) return;
    await _run(
      () => _client.adminDeleteBadge(_platformInt(badge['id'])),
      success: t('platformAdmin.badgeDeleted'),
    );
  }

  Future<void> _editRole({
    JsonMap? role,
    required List<JsonMap> permissionGroups,
  }) async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) =>
          _RoleAdminDialog(role: role, permissionGroups: permissionGroups),
    );
    if (payload == null) return;
    await _run(
      () => role == null
          ? _client.adminCreateRole(payload)
          : _client.adminUpdateRole(_platformInt(role['id']), payload),
      success: t('platformAdmin.roleSaved'),
    );
  }

  Future<void> _deleteRole(JsonMap role) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await confirmDanger(
      context,
      t('platformAdmin.deleteRole'),
      t('platformAdmin.deleteRoleBody'),
    );
    if (!confirmed) return;
    await _run(
      () => _client.adminDeleteRole(_platformInt(role['id'])),
      success: t('platformAdmin.roleDeleted'),
    );
  }

  Future<void> _createPermission() async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => const _PermissionAdminDialog(),
    );
    if (payload == null) return;
    await _run(
      () => _client.adminCreatePermission(payload),
      success: t('platformAdmin.permissionCreated'),
    );
  }

  Future<void> _decideModeration({
    required JsonMap item,
    required String kind,
  }) async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _ModerationDecisionDialog(item: item),
    );
    if (payload == null) return;
    await _run(
      () => kind == 'flag'
          ? _client.adminUpdateModerationFlag(_platformInt(item['id']), payload)
          : _client.adminUpdateModerationReport(
              _platformInt(item['id']),
              payload,
            ),
      success: t('platformAdmin.moderationSaved'),
    );
  }

  Future<void> _decideAppeal(JsonMap report) async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => const _AppealDecisionDialog(),
    );
    if (payload == null) return;
    await _run(
      () => _client.adminDecideModerationAppeal(
        _platformInt(report['id']),
        payload,
      ),
      success: t('platformAdmin.appealSaved'),
    );
  }

  Future<void> _editRule(JsonMap rule) async {
    final t = AirmiusScope.of(context).t;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _GamificationRuleDialog(rule: rule),
    );
    if (payload == null) return;
    await _run(
      () => _client.adminUpdateGamificationRule(
        _platformInt(rule['id']),
        payload,
      ),
      success: t('platformAdmin.ruleSaved'),
    );
  }

  Future<String?> _askText({
    required String title,
    required String label,
  }) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          minLines: 3,
          maxLines: 6,
          decoration: InputDecoration(labelText: label),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(AirmiusScope.of(context).t('common.cancel')),
          ),
          FilledButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.isNotEmpty) Navigator.pop(dialogContext, value);
            },
            child: Text(AirmiusScope.of(context).t('common.save')),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
  }
}

class _RoleAdminDialog extends StatefulWidget {
  const _RoleAdminDialog({this.role, required this.permissionGroups});

  final JsonMap? role;
  final List<JsonMap> permissionGroups;

  @override
  State<_RoleAdminDialog> createState() => _RoleAdminDialogState();
}

class _RoleAdminDialogState extends State<_RoleAdminDialog> {
  late final _name = TextEditingController(
    text: _platformText(widget.role?['name']),
  );
  late final _description = TextEditingController(
    text: _platformText(widget.role?['description']),
  );
  late final Set<String> _selected = _platformMaps(widget.role?['permissions'])
      .map((permission) => _platformText(permission['name']))
      .where((name) => name.isNotEmpty)
      .toSet();

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        t(
          widget.role == null
              ? 'platformAdmin.addRole'
              : 'platformAdmin.editRole',
        ),
      ),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextField(
                controller: _name,
                enabled: widget.role == null,
                autocorrect: false,
                decoration: InputDecoration(
                  labelText: t('platformAdmin.roleName'),
                  helperText: t('platformAdmin.roleNameHint'),
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _description,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: t('platformAdmin.description'),
                ),
              ),
              const SizedBox(height: 14),
              Text(
                t('platformAdmin.permissions'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 6),
              for (final group in widget.permissionGroups)
                ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  title: Text(
                    _platformText(
                      group['group'],
                      fallback: t('platformAdmin.permissions'),
                    ),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  children: _platformMaps(group['items']).map((permission) {
                    final name = _platformText(permission['name']);
                    return CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _selected.contains(name),
                      title: Text(name),
                      subtitle: _platformText(permission['description']).isEmpty
                          ? null
                          : Text(_platformText(permission['description'])),
                      onChanged: (selected) => setState(() {
                        if (selected == true) {
                          _selected.add(name);
                        } else {
                          _selected.remove(name);
                        }
                      }),
                    );
                  }).toList(),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (widget.role == null && _name.text.trim().isEmpty) return;
            Navigator.pop(context, {
              if (widget.role == null) 'name': _name.text.trim(),
              'description': _description.text.trim(),
              'permissions': _selected.toList()..sort(),
            });
          },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _PermissionAdminDialog extends StatefulWidget {
  const _PermissionAdminDialog();

  @override
  State<_PermissionAdminDialog> createState() => _PermissionAdminDialogState();
}

class _PermissionAdminDialogState extends State<_PermissionAdminDialog> {
  final _name = TextEditingController();
  final _description = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('platformAdmin.addPermission')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _name,
              autocorrect: false,
              decoration: InputDecoration(
                labelText: t('platformAdmin.permissionName'),
                helperText: t('platformAdmin.permissionNameHint'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _description,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: t('platformAdmin.description'),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_name.text.trim().isEmpty) return;
            Navigator.pop(context, {
              'name': _name.text.trim(),
              'description': _description.text.trim(),
            });
          },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _ModerationDecisionDialog extends StatefulWidget {
  const _ModerationDecisionDialog({required this.item});

  final JsonMap item;

  @override
  State<_ModerationDecisionDialog> createState() =>
      _ModerationDecisionDialogState();
}

class _ModerationDecisionDialogState extends State<_ModerationDecisionDialog> {
  late String _status = _platformText(widget.item['status'], fallback: 'open');
  late final _reason = TextEditingController(
    text: _platformText(widget.item['decision_reason']),
  );
  bool _removeContent = false;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('platformAdmin.reviewCase')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _status,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: t('platformAdmin.decision'),
              ),
              items: const ['open', 'dismissed', 'actioned']
                  .map(
                    (status) => DropdownMenuItem(
                      value: status,
                      child: Text(t('platformAdmin.status.$status')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _status = value ?? _status),
            ),
            const SizedBox(height: 10),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _removeContent,
              onChanged: (value) => setState(() => _removeContent = value),
              title: Text(t('platformAdmin.removeContent')),
              subtitle: Text(t('platformAdmin.removeContentBody')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _reason,
              minLines: 3,
              maxLines: 6,
              decoration: InputDecoration(
                labelText: t('platformAdmin.decisionReason'),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'status': _status,
            'remove_content': _removeContent,
            'decision_reason': _reason.text.trim(),
          }),
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _AppealDecisionDialog extends StatefulWidget {
  const _AppealDecisionDialog();

  @override
  State<_AppealDecisionDialog> createState() => _AppealDecisionDialogState();
}

class _AppealDecisionDialogState extends State<_AppealDecisionDialog> {
  String _status = 'accepted';
  final _decision = TextEditingController();

  @override
  void dispose() {
    _decision.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('platformAdmin.decideAppeal')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _status,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: t('platformAdmin.decision'),
              ),
              items: const ['accepted', 'rejected']
                  .map(
                    (status) => DropdownMenuItem(
                      value: status,
                      child: Text(t('platformAdmin.appeal.$status')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _status = value ?? _status),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _decision,
              minLines: 3,
              maxLines: 6,
              decoration: InputDecoration(
                labelText: t('platformAdmin.decisionReason'),
                helperText: t('platformAdmin.appealReasonHint'),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_decision.text.trim().length < 10) return;
            Navigator.pop(context, {
              'appeal_status': _status,
              'appeal_decision': _decision.text.trim(),
            });
          },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _GamificationRuleDialog extends StatefulWidget {
  const _GamificationRuleDialog({required this.rule});

  final JsonMap rule;

  @override
  State<_GamificationRuleDialog> createState() =>
      _GamificationRuleDialogState();
}

class _GamificationRuleDialogState extends State<_GamificationRuleDialog> {
  late final _label = TextEditingController(
    text: _platformText(widget.rule['label']),
  );
  late final _description = TextEditingController(
    text: _platformText(widget.rule['description']),
  );
  late final _xp = TextEditingController(
    text: '${_platformInt(widget.rule['xp_amount'])}',
  );
  late final _dailyLimit = TextEditingController(
    text: widget.rule['daily_limit'] == null
        ? ''
        : '${_platformInt(widget.rule['daily_limit'])}',
  );
  late final _trust = TextEditingController(
    text: '${_platformInt(widget.rule['trust_delta'])}',
  );
  late bool _active = widget.rule['is_active'] == true;

  @override
  void dispose() {
    _label.dispose();
    _description.dispose();
    _xp.dispose();
    _dailyLimit.dispose();
    _trust.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('platformAdmin.editRule')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _label,
              decoration: InputDecoration(labelText: t('platformAdmin.name')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _description,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: t('platformAdmin.description'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _xp,
              keyboardType: const TextInputType.numberWithOptions(signed: true),
              decoration: InputDecoration(labelText: t('platformAdmin.xp')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _dailyLimit,
              enabled: widget.rule['is_penalty'] != true,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: t('platformAdmin.dailyLimit'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _trust,
              keyboardType: const TextInputType.numberWithOptions(signed: true),
              decoration: InputDecoration(labelText: t('platformAdmin.trust')),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: (value) => setState(() => _active = value),
              title: Text(t('platformAdmin.active')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_label.text.trim().isEmpty) return;
            Navigator.pop(context, {
              'label': _label.text.trim(),
              'description': _description.text.trim(),
              'xp_amount': int.tryParse(_xp.text) ?? 0,
              'daily_limit': _dailyLimit.text.trim().isEmpty
                  ? null
                  : int.tryParse(_dailyLimit.text),
              'trust_delta': int.tryParse(_trust.text) ?? 0,
              'is_active': _active,
              'actor_type': _platformText(widget.rule['actor_type']),
            });
          },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _ClubApprovalDialog extends StatefulWidget {
  const _ClubApprovalDialog({required this.club});

  final JsonMap club;

  @override
  State<_ClubApprovalDialog> createState() => _ClubApprovalDialogState();
}

class _ClubApprovalDialogState extends State<_ClubApprovalDialog> {
  late final TextEditingController _number = TextEditingController(
    text: _platformText(widget.club['requested_official_club_number']),
  );
  final _note = TextEditingController();
  bool _official = true;

  @override
  void dispose() {
    _number.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('platformAdmin.approveClub')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _official,
              onChanged: (value) => setState(() => _official = value),
              title: Text(t('platformAdmin.markOfficial')),
            ),
            TextField(
              controller: _number,
              enabled: _official,
              decoration: InputDecoration(
                labelText: t('platformAdmin.officialNumber'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _note,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: t('platformAdmin.noteOptional'),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'mark_official': _official,
            if (_official) 'official_club_number': _number.text.trim(),
            'verification_notes': _note.text.trim(),
          }),
          child: Text(t('platformAdmin.approve')),
        ),
      ],
    );
  }
}

class _SportAdminDialog extends StatefulWidget {
  const _SportAdminDialog({this.sport});

  final JsonMap? sport;

  @override
  State<_SportAdminDialog> createState() => _SportAdminDialogState();
}

class _SportAdminDialogState extends State<_SportAdminDialog> {
  late final _name = TextEditingController(
    text: _platformText(widget.sport?['name']),
  );
  late final _slug = TextEditingController(
    text: _platformText(widget.sport?['slug']),
  );
  late final _category = TextEditingController(
    text: _platformText(widget.sport?['category']),
  );
  late final _order = TextEditingController(
    text: '${_platformInt(widget.sport?['sort_order'])}',
  );
  late bool _active = widget.sport?['is_active'] != false;

  @override
  void dispose() {
    _name.dispose();
    _slug.dispose();
    _category.dispose();
    _order.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        t(
          widget.sport == null
              ? 'platformAdmin.addSport'
              : 'platformAdmin.editSport',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _name,
              decoration: InputDecoration(labelText: t('platformAdmin.name')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _slug,
              decoration: InputDecoration(labelText: t('platformAdmin.slug')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _category,
              decoration: InputDecoration(
                labelText: t('platformAdmin.category'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _order,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: t('platformAdmin.sortOrder'),
              ),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: (value) => setState(() => _active = value),
              title: Text(t('platformAdmin.active')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_name.text.trim().isEmpty) return;
            Navigator.pop(context, {
              'name': _name.text.trim(),
              'slug': _slug.text.trim(),
              'category': _category.text.trim(),
              'sort_order': int.tryParse(_order.text) ?? 0,
              'is_active': _active,
            });
          },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _BadgeAdminDialog extends StatefulWidget {
  const _BadgeAdminDialog({this.badge});

  final JsonMap? badge;

  @override
  State<_BadgeAdminDialog> createState() => _BadgeAdminDialogState();
}

class _BadgeAdminDialogState extends State<_BadgeAdminDialog> {
  late final _key = TextEditingController(
    text: _platformText(widget.badge?['key']),
  );
  late final _name = TextEditingController(
    text: _platformText(widget.badge?['name']),
  );
  late final _description = TextEditingController(
    text: _platformText(widget.badge?['description']),
  );
  late final _threshold = TextEditingController(
    text: '${_platformInt(widget.badge?['threshold'])}',
  );
  late final _reason = TextEditingController(
    text: _platformText(_platformMap(widget.badge?['meta'])['reason']),
  );
  late String _actor = _platformText(
    widget.badge?['actor_type'],
    fallback: 'sportler',
  );
  late String _trigger = _platformText(
    widget.badge?['trigger'],
    fallback: 'xp',
  );

  @override
  void dispose() {
    _key.dispose();
    _name.dispose();
    _description.dispose();
    _threshold.dispose();
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        t(
          widget.badge == null
              ? 'platformAdmin.addBadge'
              : 'platformAdmin.editBadge',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _key,
              decoration: InputDecoration(labelText: t('platformAdmin.key')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _name,
              decoration: InputDecoration(labelText: t('platformAdmin.name')),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _description,
              decoration: InputDecoration(
                labelText: t('platformAdmin.description'),
              ),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _actor,
              isExpanded: true,
              decoration: InputDecoration(labelText: t('platformAdmin.actor')),
              items: const ['sportler', 'trainer', 'verein', 'team']
                  .map(
                    (value) =>
                        DropdownMenuItem(value: value, child: Text(value)),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _actor = value ?? _actor),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _trigger,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: t('platformAdmin.trigger'),
              ),
              items: const ['xp', 'level', 'streak', 'reason']
                  .map(
                    (value) =>
                        DropdownMenuItem(value: value, child: Text(value)),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _trigger = value ?? _trigger),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _threshold,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: t('platformAdmin.threshold'),
              ),
            ),
            if (_trigger == 'reason') ...[
              const SizedBox(height: 10),
              TextField(
                controller: _reason,
                decoration: InputDecoration(
                  labelText: t('platformAdmin.reasonKey'),
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_key.text.trim().isEmpty || _name.text.trim().isEmpty) return;
            Navigator.pop(context, {
              'key': _key.text.trim(),
              'name': _name.text.trim(),
              'description': _description.text.trim(),
              'actor_type': _actor,
              'trigger': _trigger,
              'threshold': int.tryParse(_threshold.text) ?? 0,
              if (_trigger == 'reason') 'meta': {'reason': _reason.text.trim()},
            });
          },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _PlatformSectionTitle extends StatelessWidget {
  const _PlatformSectionTitle({
    required this.icon,
    required this.title,
    required this.count,
  });

  final IconData icon;
  final String title;
  final int count;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, color: Theme.of(context).colorScheme.primary),
      const SizedBox(width: 9),
      Expanded(
        child: Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
      Semantics(
        label: '$title: $count',
        child: Badge(label: Text('$count')),
      ),
    ],
  );
}

class _PlatformCard extends StatelessWidget {
  const _PlatformCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.status,
    required this.details,
    required this.actions,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String status;
  final List<String> details;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.primary;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: icon, color: accent),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(subtitle),
                    ],
                    const SizedBox(height: 7),
                    StatusPill(status, color: accent),
                  ],
                ),
              ),
            ],
          ),
          if (details.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: details
                  .where((value) => value.isNotEmpty)
                  .map((value) => Chip(label: Text(value)))
                  .toList(),
            ),
          ],
          if (actions.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: actions),
          ],
        ],
      ),
    );
  }
}

class _PlatformMetric extends StatelessWidget {
  const _PlatformMetric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 125),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(
        context,
      ).colorScheme.surfaceContainerHighest.withValues(alpha: 0.7),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        Text(label),
      ],
    ),
  );
}

class _PlatformEmpty extends StatelessWidget {
  const _PlatformEmpty({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 18),
      child: Column(
        children: [
          Icon(icon, size: 42, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 9),
          Text(text, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _PlatformFailure extends StatelessWidget {
  const _PlatformFailure({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.lock_outline, size: 44),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_outlined),
            label: Text(AirmiusScope.of(context).t('common.retry')),
          ),
        ],
      ),
    ),
  );
}

JsonMap _platformMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<JsonMap> _platformMaps(dynamic value) => value is List
    ? value.whereType<Map>().map((item) => _platformMap(item)).toList()
    : const [];

List<String> _platformStrings(dynamic value) =>
    value is List ? value.map((item) => '$item').toList() : const [];

String _platformText(dynamic value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _platformInt(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
