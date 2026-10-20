import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/country_field.dart';
import 'commerce_center_screen.dart';
import 'sponsor_application_screen.dart';
import 'sponsors_center_screen.dart';

class SponsorCockpitScreen extends StatefulWidget {
  const SponsorCockpitScreen({super.key, this.initialSponsorId, this.initialSection = 0});

  final int? initialSponsorId;
  final int initialSection;

  @override
  State<SponsorCockpitScreen> createState() => _SponsorCockpitScreenState();
}

class _SponsorCockpitScreenState extends State<SponsorCockpitScreen> {
  Future<Map<String, dynamic>>? _future;
  int? _selectedSponsorId;
  int _selectedSection = 0;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    _selectedSponsorId = widget.initialSponsorId;
    _selectedSection = widget.initialSection;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await _client.sponsorWorkspace(
      sponsorId: _selectedSponsorId,
    );
    return _map(response['data']);
  }

  void _reload() {
    final future = _load();
    setState(() {
      _future = future;
    });
  }

  void _open(Widget screen) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => screen),
    ).then((_) => mounted ? _reload() : null);
  }

  Future<void> _editProfile(Map<String, dynamic>? profile) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SponsorProfileEditor(
        client: _client,
        profile: profile,
        sponsorId: _selectedSponsorId,
        accountEmail:
            AirmiusServicesScope.of(context).authState.user?.email ?? '',
      ),
    );
    if (saved == true && mounted) {
      _toast(t('sponsorCockpit.savedReview'));
      _reload();
    }
  }

  void _switchOrganization(int sponsorId) {
    if (_selectedSponsorId == sponsorId) return;
    _selectedSponsorId = sponsorId;
    _selectedSection = 0;
    _reload();
  }

  Future<void> _answerInvitation(int membershipId, bool accept) async {
    try {
      await _client.answerSponsorTeamInvitation(membershipId, accept: accept);
      if (!mounted) return;
      await AirmiusServicesScope.of(context).authState.refreshUser();
      if (!mounted) return;
      _toast(
        t(
          accept
              ? 'sponsorCockpit.invitationAccepted'
              : 'sponsorCockpit.invitationDeclined',
        ),
      );
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    }
  }

  Future<void> _inviteTeamMember(int sponsorId) async {
    final email = TextEditingController();
    var role = 'viewer';
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('sponsorCockpit.inviteMember')),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: email,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.email'),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: role,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.role'),
                ),
                items:
                    const [
                          'administrator',
                          'campaign_manager',
                          'finance',
                          'viewer',
                        ]
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('sponsorCockpit.role.$value')),
                          ),
                        )
                        .toList(),
                onChanged: (value) =>
                    setDialogState(() => role = value ?? role),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(t('sponsorCockpit.invite')),
            ),
          ],
        ),
      ),
    );
    final address = email.text.trim();
    email.dispose();
    if (submitted != true || address.isEmpty || !mounted) return;
    try {
      await _client.inviteSponsorTeamMember({
        'sponsor_id': sponsorId,
        'email': address,
        'role': role,
      });
      if (!mounted) return;
      _toast(t('sponsorCockpit.invited'));
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    }
  }

  Future<void> _changeMemberRole(
    Map<String, dynamic> member,
    String role,
  ) async {
    final confirmed = await _confirm(
      t('sponsorCockpit.changeRoleTitle'),
      t('sponsorCockpit.changeRoleBody'),
    );
    if (!confirmed || !mounted) return;
    try {
      await _client.updateSponsorTeamMember(_int(member['id']), role);
      if (!mounted) return;
      _toast(t('sponsorCockpit.roleChanged'));
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    }
  }

  Future<void> _removeMember(Map<String, dynamic> member) async {
    final confirmed = await _confirm(
      t('sponsorCockpit.removeMemberTitle'),
      t('sponsorCockpit.removeMemberBody'),
    );
    if (!confirmed || !mounted) return;
    try {
      await _client.removeSponsorTeamMember(_int(member['id']));
      if (!mounted) return;
      _toast(t('sponsorCockpit.memberRemoved'));
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    }
  }

  Future<void> _transferOwnership(Map<String, dynamic> member) async {
    final confirmed = await _confirm(
      t('sponsorCockpit.transferOwnerTitle'),
      t('sponsorCockpit.transferOwnerBody'),
    );
    if (!confirmed || !mounted) return;
    try {
      await _client.transferSponsorOwnership(_int(member['id']));
      if (!mounted) return;
      await AirmiusServicesScope.of(context).authState.refreshUser();
      if (!mounted) return;
      _toast(t('sponsorCockpit.ownerTransferred'));
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    }
  }

  Future<bool> _confirm(String title, String body) async =>
      await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(title),
          content: Text(body),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(t('common.confirm')),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _editDeliverable(
    Map<String, dynamic> partner, [
    Map<String, dynamic>? deliverable,
  ]) async {
    final sponsorId = _int(_map(partner['sponsor'])['id']);
    if (sponsorId <= 0) return;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SponsorDeliverableEditor(
        client: _client,
        sponsorId: sponsorId,
        clubId: _int(_map(partner['club'])['id']),
        deliverable: deliverable,
      ),
    );
    if (saved == true && mounted) {
      _toast(t('sponsorCockpit.deliverableSaved'));
      _reload();
    }
  }

  Future<void> _deleteDeliverable(
    Map<String, dynamic> partner,
    Map<String, dynamic> deliverable,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('sponsorCockpit.deleteDeliverableTitle')),
        content: Text(t('sponsorCockpit.deleteDeliverableBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await _client.deleteSponsorDeliverable(
        _int(_map(partner['sponsor'])['id']),
        _int(deliverable['id']),
      );
      if (!mounted) return;
      _toast(t('sponsorCockpit.deliverableDeleted'));
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    } catch (_) {
      if (mounted) _toast(t('sponsorCockpit.deliverableDeleteFailed'));
    }
  }

  Future<void> _createPartnership(
    int sponsorId,
    List<Map<String, dynamic>> clubs,
  ) async {
    if (sponsorId <= 0 || clubs.isEmpty) {
      _toast(t('sponsorCockpit.partnershipNoClubs'));
      return;
    }
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SponsorPartnershipEditor(
        client: _client,
        sponsorId: sponsorId,
        clubs: clubs,
      ),
    );
    if (saved == true && mounted) {
      _toast(t('sponsorCockpit.partnershipRequested'));
      _reload();
    }
  }

  Future<void> _withdrawPartnership(Map<String, dynamic> partner) async {
    final confirmed = await _confirm(
      t('sponsorCockpit.withdrawPartnershipTitle'),
      t('sponsorCockpit.withdrawPartnershipBody'),
    );
    if (!confirmed || !mounted) return;
    try {
      await _client.withdrawSponsorPartnership(_int(partner['id']));
      if (!mounted) return;
      _toast(t('sponsorCockpit.partnershipWithdrawn'));
      _reload();
    } on AirmiusApiException catch (exception) {
      if (mounted) _toast(exception.userMessage);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('sponsorCockpit.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return _SponsorCockpitError(
              message: error is AirmiusApiException
                  ? error.userMessage
                  : t('sponsorCockpit.loadFailed'),
              onRetry: _reload,
            );
          }

          final data = snapshot.data ?? const <String, dynamic>{};
          final organizations = _list(data['organizations']);
          final currentOrganization = _map(data['current_organization']);
          final invitations = _list(data['pending_invitations']);
          final summary = _map(data['summary']);
          final capabilities = _map(data['capabilities']);
          final profile = data['own_profile'] is Map
              ? _map(data['own_profile'])
              : null;
          final campaigns = _list(data['campaigns']);
          final partners = _list(data['partners']);
          final incomingInquiries = _list(data['incoming_inquiries']);
          final partnershipClubs = _list(data['partnership_clubs']);
          final assets = _list(data['assets']);
          final agencyBriefs = _list(data['agency_briefs']);
          final team = _list(data['team']);
          final activities = _list(data['activities']);
          final currentId = _int(currentOrganization['id']);

          return PageFrame(
            title: t('sponsorCockpit.title'),
            subtitle: t('sponsorCockpit.subtitle'),
            onRefresh: () async => _reload(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (organizations.isNotEmpty)
                  _organizationSwitcher(organizations, currentOrganization),
                if (invitations.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _pendingInvitations(invitations),
                ],
                if (organizations.isNotEmpty) const SizedBox(height: 14),
                _hero(profile, capabilities, currentId),
                if (profile != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: AirmiusPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _text(profile['name']),
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          Text(_profileCopy(context, 'verification_title')),
                          Text(
                            _profileCopy(
                              context,
                              'verification_${_text(profile['verification_status'], fallback: 'pending_review')}',
                            ),
                          ),
                          if (_text(profile['verification_note']).isNotEmpty)
                            Text(_text(profile['verification_note'])),
                        ],
                      ),
                    ),
                  ),
                if (profile == null && capabilities['edit_own_profile'] == true)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: _profilePrompt(profile),
                  ),
                const SizedBox(height: 14),
                _workspaceTabs(),
                const SizedBox(height: 16),
                if (_selectedSection == 0) ...[
                  _metrics(summary),
                  const SizedBox(height: 16),
                  _nextStep(summary, profile, capabilities),
                ] else if (_selectedSection == 1)
                  _campaigns(campaigns, currentId)
                else if (_selectedSection == 2) ...[
                  if (incomingInquiries.isNotEmpty) _incomingInquiries(
                    incomingInquiries, capabilities['manage_partnerships'] == true,
                  ),
                  _partners(
                    partners,
                    partnershipClubs,
                    currentId,
                    capabilities['manage_partnerships'] == true,
                  ),
                ]
                else if (_selectedSection == 3)
                  _campaignOperations(assets, agencyBriefs)
                else ...[
                  if (currentId > 0) ...[
                    _teamAndAccess(
                      currentId,
                      team,
                      capabilities['manage_team'] == true,
                      _text(currentOrganization['role']),
                    ),
                    const SizedBox(height: 16),
                    _activities(activities),
                  ] else
                    _teamSetupRequired(profile),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _workspaceTabs() {
    final tabs = [
      (Icons.dashboard_outlined, t('sponsorCockpit.tabOverview')),
      (Icons.campaign_outlined, t('sponsorCockpit.tabCampaigns')),
      (Icons.handshake_outlined, t('sponsorCockpit.tabPartnerships')),
      (Icons.perm_media_outlined, t('sponsorCockpit.tabMaterials')),
      (Icons.group_outlined, t('sponsorCockpit.tabTeam')),
    ];

    return Semantics(
      label: t('sponsorCockpit.workspaceNavigation'),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (var index = 0; index < tabs.length; index++) ...[
              if (index > 0) const SizedBox(width: 8),
              ChoiceChip(
                selected: _selectedSection == index,
                showCheckmark: false,
                avatar: Icon(tabs[index].$1, size: 18),
                label: Text(tabs[index].$2),
                onSelected: (_) => setState(() => _selectedSection = index),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _organizationSwitcher(
    List<Map<String, dynamic>> organizations,
    Map<String, dynamic> current,
  ) {
    final currentId = _int(current['id']);
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(Icons.business_outlined, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Eyebrow(t('sponsorCockpit.organization')),
                const SizedBox(height: 3),
                if (organizations.length == 1)
                  Text(
                    _text(current['name']),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  )
                else
                  DropdownButtonHideUnderline(
                    child: DropdownButton<int>(
                      value: currentId > 0 ? currentId : null,
                      isExpanded: true,
                      items: organizations
                          .map(
                            (organization) => DropdownMenuItem<int>(
                              value: _int(organization['id']),
                              child: Text(
                                _text(organization['name']),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (value) {
                        if (value != null) _switchOrganization(value);
                      },
                    ),
                  ),
                Text(
                  t(
                    'sponsorCockpit.role.${_text(current['role'], fallback: 'viewer')}',
                  ),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pendingInvitations(List<Map<String, dynamic>> invitations) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('sponsorCockpit.pendingInvitations'),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          for (final invitation in invitations)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    _text(_map(invitation['sponsor'])['name']),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  Text(
                    t(
                      'sponsorCockpit.role.${_text(invitation['role'], fallback: 'viewer')}',
                    ),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () =>
                              _answerInvitation(_int(invitation['id']), false),
                          child: Text(t('sponsorCockpit.decline')),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: FilledButton(
                          onPressed: () =>
                              _answerInvitation(_int(invitation['id']), true),
                          child: Text(t('sponsorCockpit.accept')),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _teamAndAccess(
    int sponsorId,
    List<Map<String, dynamic>> team,
    bool canManage,
    String currentRole,
  ) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _sectionHeader(
            t('sponsorCockpit.teamAccess'),
            canManage ? t('sponsorCockpit.inviteMember') : null,
            canManage ? () => _inviteTeamMember(sponsorId) : null,
          ),
          const SizedBox(height: 6),
          Text(
            t('sponsorCockpit.teamAccessBody'),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          const SizedBox(height: 12),
          for (final member in team)
            Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: airmiusSurfaceSoftColor(context),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: airmiusBorderColor(context)),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    child: Text(
                      _text(
                        member['name'],
                        fallback: _text(member['email']),
                      ).characters.take(2).toString().toUpperCase(),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _text(
                            member['name'],
                            fallback: _text(member['email']),
                          ),
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        Text(
                          '${_text(member['email'])} · ${t('sponsorCockpit.memberStatus.${_text(member['status'], fallback: 'pending')}')}',
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_text(member['role']) == 'owner' || !canManage)
                    StatusPill(
                      t('sponsorCockpit.role.${_text(member['role'])}'),
                    )
                  else
                    PopupMenuButton<String>(
                      tooltip: t('sponsorCockpit.manageAccess'),
                      onSelected: (value) {
                        if (value == 'remove') {
                          _removeMember(member);
                        } else if (value == 'transfer_owner') {
                          _transferOwnership(member);
                        } else {
                          _changeMemberRole(member, value);
                        }
                      },
                      itemBuilder: (_) => [
                        for (final role in const [
                          'administrator',
                          'campaign_manager',
                          'finance',
                          'viewer',
                        ])
                          PopupMenuItem(
                            value: role,
                            child: Text(t('sponsorCockpit.role.$role')),
                          ),
                        if (currentRole == 'owner' &&
                            _text(member['status']) == 'active' &&
                            member['is_current_user'] != true) ...[
                          const PopupMenuDivider(),
                          PopupMenuItem(
                            value: 'transfer_owner',
                            child: Text(t('sponsorCockpit.transferOwner')),
                          ),
                        ],
                        const PopupMenuDivider(),
                        PopupMenuItem(
                          value: 'remove',
                          child: Text(t('sponsorCockpit.removeMember')),
                        ),
                      ],
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _teamSetupRequired(Map<String, dynamic>? profile) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.group_outlined, color: airmiusAccentColor(context)),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  t('sponsorCockpit.teamAccess'),
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            t('sponsorCockpit.teamSetupRequired'),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => _editProfile(profile),
            icon: const Icon(Icons.group_add_outlined),
            label: Text(t('sponsorCockpit.setupCompanyProfile')),
          ),
        ],
      ),
    );
  }

  Widget _activities(List<Map<String, dynamic>> activities) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('sponsorCockpit.recentActivity'),
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          if (activities.isEmpty)
            Text(
              t('sponsorCockpit.noActivity'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          else
            for (final activity in activities.take(6))
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.history_outlined),
                title: Text(
                  t('sponsorCockpit.activity.${_text(activity['action'])}'),
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                subtitle: Text(
                  _text(
                    activity['actor_name'],
                    fallback: t('sponsorCockpit.system'),
                  ),
                ),
              ),
        ],
      ),
    );
  }

  Widget _hero(
    Map<String, dynamic>? profile,
    Map<String, dynamic> capabilities,
    int sponsorId,
  ) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('sponsorCockpit.eyebrow')),
          const SizedBox(height: 8),
          Text(
            t('sponsorCockpit.heroTitle'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            t('sponsorCockpit.heroBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.45),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (capabilities['manage_campaigns'] == true)
                FilledButton.icon(
                  onPressed: () => _open(
                    CommerceCenterScreen(
                      initialSection: 'ads',
                      openCampaignComposer: true,
                      sponsorId: sponsorId > 0 ? sponsorId : null,
                    ),
                  ),
                  icon: const Icon(Icons.add),
                  label: Text(t('sponsorCockpit.createCampaign')),
                ),
              if (capabilities['edit_own_profile'] == true)
                OutlinedButton.icon(
                  onPressed: () => _editProfile(profile),
                  icon: const Icon(Icons.business_outlined),
                  label: Text(
                    profile == null
                        ? t('sponsorCockpit.createProfile')
                        : t('sponsorCockpit.editProfile'),
                  ),
                ),
              OutlinedButton.icon(
                onPressed: () => _open(const SponsorApplicationScreen()),
                icon: const Icon(Icons.fact_check_outlined),
                label: Text(t('sponsorCockpit.applicationHistory')),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _profilePrompt(Map<String, dynamic>? profile) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  t('sponsorCockpit.profileMissing'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  t('sponsorCockpit.profileMissingBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                TextButton(
                  onPressed: () => _editProfile(profile),
                  child: Text(t('sponsorCockpit.setupNow')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _metrics(Map<String, dynamic> summary) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    final number = NumberFormat.decimalPattern(locale);
    final currency = NumberFormat.currency(locale: locale, symbol: '€');
    final values = [
      (
        number.format(_int(summary['active_partners'])),
        t('sponsorCockpit.partners'),
      ),
      (
        number.format(_int(summary['active_campaigns'])),
        t('sponsorCockpit.activeCampaigns'),
      ),
      (
        number.format(_int(summary['impressions'])),
        t('sponsorCockpit.impressions'),
      ),
      (
        '${_double(summary['ctr']).toStringAsFixed(1)} %',
        t('sponsorCockpit.ctr'),
      ),
      (
        currency.format(_int(summary['budget_cents']) / 100),
        t('sponsorCockpit.budget'),
      ),
      (
        currency.format(_int(summary['open_budget_cents']) / 100),
        t('sponsorCockpit.openBudget'),
      ),
      (
        number.format(_int(summary['pending_approvals'])),
        t('sponsorCockpit.pendingApprovals'),
      ),
      (
        number.format(_int(summary['new_club_requests'])),
        t('sponsorCockpit.clubRequests'),
      ),
      (number.format(_int(summary['clicks'])), t('sponsorCockpit.clicks')),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        mainAxisExtent: 60 + MediaQuery.textScalerOf(context).scale(100),
      ),
      itemCount: values.length,
      itemBuilder: (_, index) =>
          MetricCard(value: values[index].$1, label: values[index].$2),
    );
  }

  Widget _campaigns(List<Map<String, dynamic>> campaigns, int sponsorId) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    final number = NumberFormat.decimalPattern(locale);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _sectionHeader(
            t('sponsorCockpit.campaigns'),
            t('sponsorCockpit.openCampaigns'),
            () => _open(
              CommerceCenterScreen(
                initialSection: 'ads',
                sponsorId: sponsorId > 0 ? sponsorId : null,
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (campaigns.isEmpty)
            _empty(
              Icons.campaign_outlined,
              t('sponsorCockpit.noCampaigns'),
              t('sponsorCockpit.noCampaignsBody'),
            )
          else
            ...campaigns
                .take(5)
                .map(
                  (campaign) => Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: airmiusSurfaceSoftColor(context),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: airmiusBorderColor(context)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                _text(
                                  campaign['headline'],
                                  fallback: _text(campaign['name']),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: airmiusTextColor(context),
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            _status(_text(campaign['status'])),
                          ],
                        ),
                        const SizedBox(height: 9),
                        Text(
                          '${number.format(_int(campaign['impressions']))} ${t('sponsorCockpit.impressions')}  ·  '
                          '${number.format(_int(campaign['clicks']))} ${t('sponsorCockpit.clicks')}  ·  '
                          '${_double(campaign['ctr']).toStringAsFixed(1)} % CTR',
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
        ],
      ),
    );
  }

  Future<void> _respondInquiry(Map<String, dynamic> inquiry, String decision) async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(context: context, builder: (dialogContext) => AlertDialog(
      title: Text(decision == 'interested' ? 'Interesse bestätigen' : 'Anfrage absagen'),
      content: TextField(controller: note, maxLines: 4, maxLength: 2000,
        decoration: const InputDecoration(labelText: 'Antwort an den Verein')),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Abbrechen')),
        FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Senden')),
      ],
    ));
    final reply = note.text.trim();
    note.dispose();
    if (confirmed != true || !mounted) return;
    try {
      await _client.respondSponsorInquiry(_int(inquiry['id']), decision, reply);
      if (mounted) _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    }
  }

  Widget _incomingInquiries(List<Map<String, dynamic>> inquiries, bool canManage) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text('Anfragen von Vereinen', style: Theme.of(context).textTheme.titleMedium),
      ...inquiries.map((inquiry) => Padding(padding: const EdgeInsets.only(top: 12), child: Column(
        crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${_text(_map(inquiry['club'])['name'])} · ${_text(inquiry['subject'])}',
            style: const TextStyle(fontWeight: FontWeight.bold)),
          Text(_text(inquiry['message'])),
          Text(_text(inquiry['status']), style: TextStyle(color: airmiusMutedColor(context))),
          if (_text(inquiry['response_note']).isNotEmpty) Text(_text(inquiry['response_note'])),
          if (canManage && inquiry['status'] == 'pending') Wrap(spacing: 8, children: [
            TextButton(onPressed: () => _respondInquiry(inquiry, 'interested'), child: const Text('Interesse bestätigen')),
            TextButton(onPressed: () => _respondInquiry(inquiry, 'declined'), child: const Text('Absagen')),
          ]),
          const Divider(),
        ],
      ))),
    ]));
  }

  Widget _partners(
    List<Map<String, dynamic>> partners,
    List<Map<String, dynamic>> clubs,
    int sponsorId,
    bool canManage,
  ) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _sectionHeader(
            t('sponsorCockpit.partnerships'),
            t('sponsorCockpit.publicView'),
            () => _open(const SponsorsCenterScreen()),
          ),
          if (canManage) ...[
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () => _createPartnership(sponsorId, clubs),
              icon: const Icon(Icons.add),
              label: Text(t('sponsorCockpit.requestPartnership')),
            ),
          ],
          const SizedBox(height: 12),
          if (partners.isEmpty)
            _empty(
              Icons.handshake_outlined,
              t('sponsorCockpit.noPartners'),
              t('sponsorCockpit.noPartnersBody'),
            )
          else
            ...partners.take(5).map(_partnerCard),
        ],
      ),
    );
  }

  Widget _partnerCard(Map<String, dynamic> partner) {
    final deliverables = _list(partner['deliverables']);
    final canEdit = partner['can_edit_deliverables'] == true;
    final canDelete = partner['can_delete_deliverables'] == true;
    final rights = _displayList(partner['rights_package']);
    final period = _dateRange(partner['starts_at'], partner['ends_at']);
    final terms = _text(partner['individual_offer_terms']);
    final package = _text(partner['package_code']);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: ExpansionTile(
        shape: const Border(),
        collapsedShape: const Border(),
        leading: const Icon(Icons.handshake_outlined),
        title: Row(
          children: [
            Expanded(
              child: Text(
                _text(partner['name']),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            _status(_text(partner['status'])),
          ],
        ),
        subtitle: Text(
          [
            t('sponsorCockpit.openDetails'),
            if (period.isNotEmpty) period,
          ].join(' · '),
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          if (package.isNotEmpty)
            _partnerLine(
              Icons.inventory_2_outlined,
              _sponsorTerm(context, 'package'),
              package,
            ),
          if (rights.isNotEmpty)
            _partnerLine(
              Icons.verified_outlined,
              _sponsorTerm(context, 'rights'),
              rights,
            ),
          if (terms.isNotEmpty)
            _partnerLine(
              Icons.description_outlined,
              _sponsorTerm(context, 'agreement'),
              terms,
            ),
          if (_text(partner['renewal_deadline']).isNotEmpty)
            _partnerLine(
              Icons.event_repeat_outlined,
              _sponsorTerm(context, 'renewal'),
              _dateLabel(partner['renewal_deadline']),
            ),
          if (_text(partner['response_note']).isNotEmpty)
            _partnerLine(
              Icons.chat_bubble_outline,
              t('sponsorCockpit.clubResponse'),
              _text(partner['response_note']),
            ),
          const Divider(height: 24),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${_sponsorTerm(context, 'deliverables')} (${deliverables.length})',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (canEdit)
                IconButton(
                  tooltip: t('sponsorCockpit.addDeliverable'),
                  onPressed: () => _editDeliverable(partner),
                  icon: const Icon(Icons.add_circle_outline),
                ),
            ],
          ),
          const SizedBox(height: 8),
          if (deliverables.isEmpty)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                _sponsorTerm(context, 'noDeliverables'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
            )
          else
            ...deliverables.map(
              (deliverable) => _deliverableRow(
                partner,
                deliverable,
                canEdit: canEdit,
                canDelete: canDelete,
              ),
            ),
          if (partner['can_withdraw'] == true) ...[
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () => _withdrawPartnership(partner),
              icon: const Icon(Icons.undo),
              label: Text(t('sponsorCockpit.withdrawRequest')),
            ),
          ],
        ],
      ),
    );
  }

  Widget _partnerLine(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: airmiusMutedColor(context)),
          const SizedBox(width: 8),
          Expanded(
            child: Text.rich(
              TextSpan(
                style: TextStyle(color: airmiusMutedColor(context)),
                children: [
                  TextSpan(
                    text: '$label: ',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  TextSpan(text: value),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _deliverableRow(
    Map<String, dynamic> partner,
    Map<String, dynamic> deliverable, {
    required bool canEdit,
    required bool canDelete,
  }) {
    final responsible = _text(_map(deliverable['responsible'])['name']);
    final due = _dateLabel(deliverable['due_at']);
    final evidence = _text(deliverable['fulfillment_evidence']);
    final status = _text(deliverable['status'], fallback: 'open');
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _text(deliverable['title']),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              StatusPill(t('sponsorCockpit.status.$status')),
              if (canEdit || canDelete)
                PopupMenuButton<String>(
                  tooltip: t('common.edit'),
                  onSelected: (action) {
                    if (action == 'edit') {
                      _editDeliverable(partner, deliverable);
                    } else if (action == 'delete') {
                      _deleteDeliverable(partner, deliverable);
                    }
                  },
                  itemBuilder: (_) => [
                    if (canEdit)
                      PopupMenuItem(
                        value: 'edit',
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.edit_outlined),
                          title: Text(t('common.edit')),
                        ),
                      ),
                    if (canDelete)
                      PopupMenuItem(
                        value: 'delete',
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.delete_outline),
                          title: Text(t('common.delete')),
                        ),
                      ),
                  ],
                ),
            ],
          ),
          if (responsible.isNotEmpty || due.isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(
              [
                if (responsible.isNotEmpty) responsible,
                if (due.isNotEmpty) '${_sponsorTerm(context, 'due')} $due',
              ].join(' · '),
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
            ),
          ],
          if (evidence.isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(
              '${_sponsorTerm(context, 'evidence')}: $evidence',
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }

  Widget _nextStep(
    Map<String, dynamic> summary,
    Map<String, dynamic>? profile,
    Map<String, dynamic> capabilities,
  ) {
    final hasActiveCampaign = _int(summary['active_campaigns']) > 0;
    final needsProfile =
        profile == null && capabilities['edit_own_profile'] == true;
    final title = needsProfile
        ? t('sponsorCockpit.nextProfile')
        : hasActiveCampaign
        ? t('sponsorCockpit.nextMeasure')
        : t('sponsorCockpit.nextCampaign');
    final body = needsProfile
        ? t('sponsorCockpit.nextProfileBody')
        : hasActiveCampaign
        ? t('sponsorCockpit.nextMeasureBody')
        : t('sponsorCockpit.nextCampaignBody');
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(t('sponsorCockpit.nextStep')),
          const SizedBox(height: 8),
          Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _campaignOperations(
    List<Map<String, dynamic>> assets,
    List<Map<String, dynamic>> briefs,
  ) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            _sponsorTerm(context, 'campaignMaterial'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          _operationHeader(
            Icons.photo_library_outlined,
            _sponsorTerm(context, 'assets'),
            assets.length,
            () => _open(const CommerceCenterScreen(initialSection: 'ads')),
          ),
          if (assets.isEmpty)
            _operationEmpty(_sponsorTerm(context, 'noAssets'))
          else
            ...assets.take(4).map(_assetRow),
          const Divider(height: 28),
          _operationHeader(
            Icons.assignment_outlined,
            _sponsorTerm(context, 'briefs'),
            briefs.length,
            () => _open(const CommerceCenterScreen(initialSection: 'websites')),
          ),
          if (briefs.isEmpty)
            _operationEmpty(_sponsorTerm(context, 'noBriefs'))
          else
            ...briefs.take(4).map(_briefRow),
        ],
      ),
    );
  }

  Widget _operationHeader(
    IconData icon,
    String title,
    int count,
    VoidCallback onOpen,
  ) {
    return Row(
      children: [
        Icon(icon, color: airmiusAccentColor(context)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            '$title ($count)',
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ),
        IconButton(
          tooltip: _sponsorTerm(context, 'open'),
          onPressed: onOpen,
          icon: const Icon(Icons.arrow_forward_outlined),
        ),
      ],
    );
  }

  Widget _operationEmpty(String text) => Padding(
    padding: const EdgeInsetsDirectional.only(start: 32, top: 4, bottom: 4),
    child: Text(text, style: TextStyle(color: airmiusMutedColor(context))),
  );

  Widget _assetRow(Map<String, dynamic> asset) {
    return ListTile(
      contentPadding: const EdgeInsetsDirectional.only(start: 32),
      dense: true,
      title: Text(
        _text(asset['name']),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      subtitle: Text(
        [
          _text(asset['campaign_name']),
          _text(asset['format']),
          '${_int(asset['impressions'])} ${t('sponsorCockpit.impressions')}',
        ].where((value) => value.isNotEmpty).join(' · '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Icon(
        asset['is_active'] == true
            ? Icons.check_circle_outline
            : Icons.pause_circle_outline,
        color: asset['is_active'] == true
            ? Colors.greenAccent
            : airmiusMutedColor(context),
      ),
    );
  }

  Widget _briefRow(Map<String, dynamic> brief) {
    final club = _text(_map(brief['club'])['name']);
    final package = _text(brief['package']);
    return ListTile(
      contentPadding: const EdgeInsetsDirectional.only(start: 32),
      dense: true,
      title: Text(
        club.isNotEmpty ? club : package,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      subtitle: Text(
        [
          if (club.isNotEmpty && package.isNotEmpty) package,
          _dateLabel(brief['submitted_at']),
        ].where((value) => value.isNotEmpty).join(' · '),
      ),
      trailing: StatusPill(_statusLabel(_text(brief['status']))),
    );
  }

  Widget _sectionHeader(String title, String? action, VoidCallback? onTap) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        if (action != null && onTap != null)
          Flexible(
            child: TextButton(onPressed: onTap, child: Text(action)),
          ),
      ],
    );
  }

  Widget _empty(IconData icon, String title, String body) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Column(
        children: [
          Icon(icon, size: 38, color: airmiusMutedColor(context)),
          const SizedBox(height: 10),
          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            body,
            textAlign: TextAlign.center,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _status(String value) {
    final active = value == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: active
            ? Colors.green.withValues(alpha: 0.12)
            : airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(
          color: active
              ? Colors.green.withValues(alpha: 0.5)
              : airmiusBorderColor(context),
        ),
      ),
      child: Text(
        t('sponsorCockpit.status.$value'),
        style: TextStyle(
          color: active ? Colors.greenAccent : airmiusMutedColor(context),
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SponsorPartnershipEditor extends StatefulWidget {
  const _SponsorPartnershipEditor({
    required this.client,
    required this.sponsorId,
    required this.clubs,
  });

  final AirmiusApiClient client;
  final int sponsorId;
  final List<Map<String, dynamic>> clubs;

  @override
  State<_SponsorPartnershipEditor> createState() =>
      _SponsorPartnershipEditorState();
}

class _SponsorPartnershipEditorState extends State<_SponsorPartnershipEditor> {
  final _formKey = GlobalKey<FormState>();
  final _start = TextEditingController();
  final _end = TextEditingController();
  final _package = TextEditingController();
  final _rights = TextEditingController();
  final _terms = TextEditingController();
  final _renewal = TextEditingController();
  int? _clubId;
  bool _saving = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  Future<void> _showHelp() => showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(t('sponsorCockpit.partnershipHelpTitle')),
      content: Text(t('sponsorCockpit.partnershipHelpBody')),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(dialogContext),
          child: Text(t('common.close')),
        ),
      ],
    ),
  );

  @override
  void dispose() {
    _start.dispose();
    _end.dispose();
    _package.dispose();
    _rights.dispose();
    _terms.dispose();
    _renewal.dispose();
    super.dispose();
  }

  Future<void> _pickDate(TextEditingController controller) async {
    final initial = DateTime.tryParse(controller.text) ?? DateTime.now();
    final value = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (value != null) {
      controller.text = DateFormat('yyyy-MM-dd').format(value);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    try {
      await widget.client.createSponsorPartnership({
        'sponsor_id': widget.sponsorId,
        'club_id': _clubId,
        'starts_at': _start.text.trim().isEmpty ? null : _start.text.trim(),
        'ends_at': _end.text.trim().isEmpty ? null : _end.text.trim(),
        'package_code': _package.text.trim().isEmpty
            ? null
            : _package.text.trim(),
        'rights_package': _rights.text
            .split(',')
            .map((value) => value.trim())
            .where((value) => value.isNotEmpty)
            .toList(),
        'individual_offer_terms': _terms.text.trim().isEmpty
            ? null
            : _terms.text.trim(),
        'renewal_deadline': _renewal.text.trim().isEmpty
            ? null
            : _renewal.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (exception) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.userMessage)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        20,
        20,
        MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t('sponsorCockpit.requestPartnership'),
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: _showHelp,
                    tooltip: t('sponsorCockpit.partnershipHelpTitle'),
                    icon: const Icon(Icons.help_outline),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                t('sponsorCockpit.partnershipReviewHint'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
              const SizedBox(height: 18),
              Autocomplete<Map<String, dynamic>>(
                displayStringForOption: (club) => _text(club['name']),
                optionsBuilder: (text) {
                  final query = text.text.trim().toLowerCase();
                  return widget.clubs.where(
                    (club) => query.isEmpty ||
                        _text(club['name']).toLowerCase().contains(query),
                  );
                },
                onSelected: (club) => setState(() => _clubId = _int(club['id'])),
                fieldViewBuilder: (context, controller, focusNode, onSubmitted) =>
                    TextFormField(
                      controller: controller,
                      focusNode: focusNode,
                      decoration: InputDecoration(
                        labelText: t('sponsorCockpit.club'),
                        hintText: t('sponsorCockpit.clubSearch'),
                        prefixIcon: const Icon(Icons.search),
                      ),
                      onChanged: (_) {
                        if (_clubId != null) setState(() => _clubId = null);
                      },
                      validator: (_) => _clubId == null
                          ? t('sponsorCockpit.clubRequired')
                          : null,
                    ),
              ),
              const SizedBox(height: 6),
              Text(
                t('sponsorCockpit.clubVisibilityHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(child: _dateField(_start, 'sponsorCockpit.start')),
                  const SizedBox(width: 10),
                  Expanded(child: _dateField(_end, 'sponsorCockpit.end')),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _package,
                maxLength: 120,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.packageName'),
                ),
              ),
              TextFormField(
                controller: _rights,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.rights'),
                  hintText: t('sponsorCockpit.rightsHint'),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _terms,
                maxLines: 4,
                maxLength: 5000,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.agreementMessage'),
                ),
              ),
              _dateField(_renewal, 'sponsorCockpit.renewalDeadline'),
              const SizedBox(height: 18),
              FilledButton.icon(
                onPressed: _saving ? null : _submit,
                icon: _saving
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_outlined),
                label: Text(t('sponsorCockpit.sendRequest')),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _dateField(TextEditingController controller, String key) {
    return TextFormField(
      controller: controller,
      readOnly: true,
      onTap: () => _pickDate(controller),
      decoration: InputDecoration(
        labelText: t(key),
        suffixIcon: const Icon(Icons.calendar_today_outlined),
      ),
    );
  }
}

class _SponsorDeliverableEditor extends StatefulWidget {
  const _SponsorDeliverableEditor({
    required this.client,
    required this.sponsorId,
    required this.clubId,
    required this.deliverable,
  });

  final AirmiusApiClient client;
  final int sponsorId;
  final int clubId;
  final Map<String, dynamic>? deliverable;

  @override
  State<_SponsorDeliverableEditor> createState() =>
      _SponsorDeliverableEditorState();
}

class _SponsorDeliverableEditorState extends State<_SponsorDeliverableEditor> {
  static const _statuses = [
    'planned',
    'in_progress',
    'fulfilled',
    'waived',
    'cancelled',
  ];

  final _form = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _location;
  late final TextEditingController _evidence;
  late String _status;
  DateTime? _dueAt;
  bool _busy = false;
  String? _error;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final deliverable = widget.deliverable ?? const <String, dynamic>{};
    _title = TextEditingController(text: _text(deliverable['title']));
    _location = TextEditingController(text: _text(deliverable['location']));
    _evidence = TextEditingController(
      text: _text(deliverable['fulfillment_evidence']),
    );
    final status = _text(deliverable['status'], fallback: 'planned');
    _status = _statuses.contains(status) ? status : 'planned';
    _dueAt = DateTime.tryParse(_text(deliverable['due_at']));
  }

  @override
  void dispose() {
    _title.dispose();
    _location.dispose();
    _evidence.dispose();
    super.dispose();
  }

  Future<void> _pickDueDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _dueAt ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (selected != null && mounted) setState(() => _dueAt = selected);
  }

  Future<void> _save() async {
    if (_busy || !_form.currentState!.validate()) return;
    if (_status == 'fulfilled' && _evidence.text.trim().isEmpty) {
      setState(() => _error = t('sponsorCockpit.deliverableEvidence'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final payload = <String, dynamic>{
      'club_id': widget.clubId,
      'title': _title.text.trim(),
      'location': _location.text.trim().isEmpty ? null : _location.text.trim(),
      'due_at': _dueAt == null
          ? null
          : DateFormat('yyyy-MM-dd').format(_dueAt!),
      'status': _status,
      'fulfillment_evidence': _evidence.text.trim().isEmpty
          ? null
          : _evidence.text.trim(),
    };
    try {
      final id = _int(widget.deliverable?['id']);
      if (id > 0) {
        await widget.client.updateSponsorDeliverable(
          widget.sponsorId,
          id,
          payload,
        );
      } else {
        await widget.client.createSponsorDeliverable(widget.sponsorId, payload);
      }
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (exception) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = exception.userMessage;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = t('sponsorCockpit.deliverableSaveFailed');
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.88,
      minChildSize: 0.62,
      maxChildSize: 0.96,
      builder: (context, controller) => Form(
        key: _form,
        child: SingleChildScrollView(
          controller: controller,
          padding: EdgeInsets.fromLTRB(
            20,
            12,
            20,
            24 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t(
                        widget.deliverable == null
                            ? 'sponsorCockpit.addDeliverable'
                            : 'sponsorCockpit.editDeliverable',
                      ),
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: t('common.cancel'),
                    onPressed: _busy ? null : () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _title,
                enabled: !_busy,
                maxLength: 255,
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: '${t('sponsorCockpit.deliverableTitle')} *',
                ),
                validator: (value) => value == null || value.trim().isEmpty
                    ? t('seller.required')
                    : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _location,
                enabled: !_busy,
                maxLength: 255,
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.deliverableLocation'),
                ),
              ),
              const SizedBox(height: 12),
              InkWell(
                onTap: _busy ? null : _pickDueDate,
                borderRadius: BorderRadius.circular(8),
                child: InputDecorator(
                  decoration: InputDecoration(
                    labelText: t('sponsorCockpit.deliverableDue'),
                    prefixIcon: const Icon(Icons.event_outlined),
                    suffixIcon: _dueAt == null
                        ? const Icon(Icons.chevron_right)
                        : IconButton(
                            tooltip: t('common.delete'),
                            onPressed: _busy
                                ? null
                                : () => setState(() => _dueAt = null),
                            icon: const Icon(Icons.close),
                          ),
                  ),
                  child: Text(
                    _dueAt == null
                        ? '—'
                        : DateFormat.yMd(
                            Localizations.localeOf(context).toLanguageTag(),
                          ).format(_dueAt!),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.deliverableStatus'),
                ),
                items: _statuses
                    .map(
                      (status) => DropdownMenuItem(
                        value: status,
                        child: Text(t('sponsorCockpit.status.$status')),
                      ),
                    )
                    .toList(),
                onChanged: _busy
                    ? null
                    : (value) => setState(() => _status = value ?? 'planned'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _evidence,
                enabled: !_busy,
                minLines: 3,
                maxLines: 6,
                maxLength: 5000,
                decoration: InputDecoration(
                  labelText: t('sponsorCockpit.deliverableEvidence'),
                  alignLabelWithHint: true,
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 10),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 18),
              FilledButton.icon(
                onPressed: _busy ? null : _save,
                icon: _busy
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(t('common.save')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SponsorProfileEditor extends StatefulWidget {
  const _SponsorProfileEditor({
    required this.client,
    required this.profile,
    required this.accountEmail,
    required this.sponsorId,
  });

  final AirmiusApiClient client;
  final Map<String, dynamic>? profile;
  final String accountEmail;
  final int? sponsorId;

  @override
  State<_SponsorProfileEditor> createState() => _SponsorProfileEditorState();
}

class _SponsorProfileEditorState extends State<_SponsorProfileEditor> {
  final _form = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late bool _legalAccuracy;
  late bool _dataPrivacy;
  bool _busy = false;
  String? _error;
  Map<String, String> _errors = {};
  final Map<String, PlatformFile> _logoFiles = {};

  @override
  void initState() {
    super.initState();
    _fields = {
      for (final key in [
        'name',
        'legal_name',
        'country_code',
        'region',
        'city',
        'registration_number',
        'vat_id',
        'contact_name',
        'email',
        'website',
        'logo',
        'logo_light',
        'logo_dark',
      ])
        key: TextEditingController(
          text: _text(
            widget.profile?[key],
            fallback: switch (key) {
              'country_code' => 'DE',
              'email' => widget.accountEmail,
              _ => '',
            },
          ),
        ),
    };
    _legalAccuracy = widget.profile?['legal_accuracy_accepted'] == true;
    _dataPrivacy = widget.profile?['data_privacy_accepted'] == true;
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_busy) return;
    final t = AirmiusScope.of(context).t;
    setState(() {
      _error = null;
      _errors = {};
    });
    final valid = _form.currentState!.validate();
    if (!valid || !_legalAccuracy || !_dataPrivacy) {
      setState(() => _error = t('seller.required'));
      return;
    }
    setState(() => _busy = true);
    try {
      // Only the self-service fields are writable; ownership and verification
      // remain server-controlled for both creation and editing.
      final response = await widget.client.updateSponsorWorkspaceProfile({
        for (final entry in _fields.entries)
          entry.key: entry.key == 'country_code'
              ? entry.value.text.trim().toUpperCase()
              : entry.value.text.trim(),
        'rule_legal_accuracy': _legalAccuracy,
        'rule_data_privacy': _dataPrivacy,
      }, sponsorId: widget.sponsorId);
      if (!mounted) return;
      if (response['queued'] == true) {
        setState(() {
          _busy = false;
          _error = _profileCopy(context, 'queued');
        });
        return;
      }
      for (final entry in _logoFiles.entries) {
        await _uploadLogo(entry.key, entry.value);
      }
      if (!mounted) return;
      // A dismissed sheet can stay mounted during its exit animation.
      if (ModalRoute.of(context)?.isCurrent == true) {
        Navigator.pop(context, true);
      }
    } on AirmiusApiException catch (exception) {
      if (!mounted) return;
      final errors = <String, String>{};
      try {
        final body = _map(jsonDecode(exception.body));
        for (final entry in _map(body['errors']).entries) {
          if (_fields.containsKey(entry.key) ||
              entry.key == 'rule_legal_accuracy' ||
              entry.key == 'rule_data_privacy') {
            // Retain the API client's diagnostic filtering for inline errors.
            errors[entry.key] = AirmiusApiException(
              statusCode: exception.statusCode,
              path: exception.path,
              body: jsonEncode({
                'errors': {entry.key: entry.value},
              }),
            ).userMessage;
          }
        }
      } catch (_) {
        // Non-JSON failures still use the sanitized general message.
      }
      setState(() {
        _busy = false;
        _error = exception.userMessage;
        _errors = errors;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = t('sponsorCockpit.saveFailed');
      });
    }
  }

  Future<void> _pickLogo(String variant) async {
    final selection = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp'],
      withData: true,
    );
    final file = selection?.files.single;
    if (file == null || !mounted) return;
    if (file.size > 5 * 1024 * 1024) {
      setState(
        () =>
            _error = AirmiusScope.of(context).t('sponsorCockpit.logoTooLarge'),
      );
      return;
    }
    setState(() {
      _logoFiles[variant] = file;
      _error = null;
    });
  }

  Future<void> _uploadLogo(String variant, PlatformFile file) async {
    final base = Uri.parse(widget.client.baseUrl);
    final rootPath = base.path.endsWith('/') ? base.path : '${base.path}/';
    final request =
        http.MultipartRequest(
            'POST',
            base.replace(
              path: '${rootPath}api/v1/sponsor-workspace/profile/logo',
              query: null,
              fragment: null,
            ),
          )
          ..headers['Accept'] = 'application/json'
          ..headers['Accept-Language'] = widget.client.locale
          ..fields['variant'] = variant;
    if (widget.client.token?.isNotEmpty == true) {
      request.headers['Authorization'] = 'Bearer ${widget.client.token}';
    }
    if (widget.sponsorId != null) {
      request.fields['sponsor_id'] = '${widget.sponsorId}';
    }
    if (file.bytes != null) {
      request.files.add(
        http.MultipartFile.fromBytes('logo', file.bytes!, filename: file.name),
      );
    } else if (file.path != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'logo',
          file.path!,
          filename: file.name,
        ),
      );
    } else {
      throw StateError(
        AirmiusScope.of(context).t('sponsorCockpit.logoReadFailed'),
      );
    }
    final response = await http.Response.fromStream(await request.send());
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: '/api/v1/sponsor-workspace/profile/logo',
      );
    }
  }

  Widget _logoPicker(String variant, String label, String currentUrl) {
    final selected = _logoFiles[variant];
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Container(
            width: 64,
            height: 64,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: airmiusSurfaceColor(context),
              borderRadius: BorderRadius.circular(8),
            ),
            child: selected?.bytes != null
                ? Image.memory(selected!.bytes!, fit: BoxFit.contain)
                : currentUrl.isNotEmpty
                ? Image.network(
                    currentUrl,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => const Icon(Icons.image_outlined),
                  )
                : const Icon(Icons.image_outlined),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                if (selected != null)
                  Text(
                    selected.name,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                    ),
                  ),
                TextButton.icon(
                  onPressed: _busy ? null : () => _pickLogo(variant),
                  icon: const Icon(Icons.upload_outlined),
                  label: Text(
                    AirmiusScope.of(context).t('sponsorCockpit.chooseImage'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _field(
    String key,
    String label, {
    bool required = false,
    int max = 255,
  }) {
    final isUrl = key == 'website' || key.startsWith('logo');
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        key: ValueKey('sponsor-profile-$key'),
        controller: _fields[key],
        enabled: !_busy,
        maxLength: max,
        textCapitalization: key == 'country_code'
            ? TextCapitalization.characters
            : TextCapitalization.none,
        keyboardType: isUrl
            ? TextInputType.url
            : key == 'email'
            ? TextInputType.emailAddress
            : TextInputType.text,
        textInputAction: TextInputAction.next,
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          hintText: isUrl ? 'https://' : null,
          errorText: _errors[key],
          errorMaxLines: 4,
          counterText: '',
        ),
        onChanged: (_) {
          if (_errors.containsKey(key)) setState(() => _errors.remove(key));
        },
        validator: (value) {
          final text = value?.trim() ?? '';
          if (required && text.isEmpty) return t('seller.required');
          if (key == 'country_code' && text.length != 2) {
            return t('seller.countryCodeHint');
          }
          if (isUrl && text.isNotEmpty) {
            final uri = Uri.tryParse(text);
            if (uri == null ||
                !uri.hasAuthority ||
                (uri.scheme != 'http' && uri.scheme != 'https')) {
              return t('sponsors.invalidWebsite');
            }
          }
          return null;
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          20,
          20,
          20,
          20 + MediaQuery.viewPaddingOf(context).bottom,
        ),
        child: Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t(
                        widget.profile == null
                            ? 'sponsorCockpit.createProfile'
                            : 'sponsorCockpit.editProfile',
                      ),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  IconButton(
                    tooltip: t('common.cancel'),
                    onPressed: _busy ? null : () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              Text(t('sponsorCockpit.profileBody')),
              const SizedBox(height: 8),
              Text(_profileCopy(context, 'review_after_save')),
              const SizedBox(height: 18),
              _field('name', t('sponsorCockpit.brandName'), required: true),
              _field(
                'legal_name',
                _profileCopy(context, 'legal_name'),
                required: true,
              ),
              CountryField(
                controller: _fields['country_code'],
                label: _profileCopy(context, 'country'),
                required: true,
              ),
              _field('region', _profileCopy(context, 'region'), max: 120),
              _field('city', _profileCopy(context, 'city'), max: 120),
              const SizedBox(height: 12),
              _field(
                'registration_number',
                _profileCopy(context, 'registration'),
                max: 120,
              ),
              _field('vat_id', _profileCopy(context, 'vat_id'), max: 80),
              _field('contact_name', t('sponsorCockpit.contact')),
              _field('email', t('sponsorCockpit.email')),
              _field('website', t('sponsorCockpit.website')),
              const SizedBox(height: 4),
              Text(
                t('sponsorCockpit.logoUploadTitle'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 4),
              Text(
                t('sponsorCockpit.logoUploadBody'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
              const SizedBox(height: 10),
              _logoPicker(
                'primary',
                t('sponsorCockpit.logoPrimary'),
                _fields['logo']!.text,
              ),
              _logoPicker(
                'light',
                t('sponsorCockpit.logoLight'),
                _fields['logo_light']!.text,
              ),
              _logoPicker(
                'dark',
                t('sponsorCockpit.logoDark'),
                _fields['logo_dark']!.text,
              ),
              const SizedBox(height: 4),
              Text(
                t('sponsorCockpit.externalLogoUrls'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              _field('logo', t('sponsorCockpit.logoUrl'), max: 2048),
              _field(
                'logo_light',
                _profileCopy(context, 'logo_light'),
                max: 2048,
              ),
              _field(
                'logo_dark',
                _profileCopy(context, 'logo_dark'),
                max: 2048,
              ),
              CheckboxListTile(
                key: const ValueKey('sponsor-profile-rule_legal_accuracy'),
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(_profileCopy(context, 'legal_accuracy')),
                subtitle: _errors['rule_legal_accuracy'] == null
                    ? null
                    : Text(
                        _errors['rule_legal_accuracy']!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                value: _legalAccuracy,
                onChanged: _busy
                    ? null
                    : (value) => setState(() {
                        _legalAccuracy = value == true;
                        _errors.remove('rule_legal_accuracy');
                      }),
              ),
              CheckboxListTile(
                key: const ValueKey('sponsor-profile-rule_data_privacy'),
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(_profileCopy(context, 'data_privacy')),
                subtitle: _errors['rule_data_privacy'] == null
                    ? null
                    : Text(
                        _errors['rule_data_privacy']!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                value: _dataPrivacy,
                onChanged: _busy
                    ? null
                    : (value) => setState(() {
                        _dataPrivacy = value == true;
                        _errors.remove('rule_data_privacy');
                      }),
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              const SizedBox(height: 18),
              FilledButton.icon(
                key: const ValueKey('sponsor-profile-save'),
                onPressed: _busy ? null : _save,
                icon: _busy
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(t('sponsorCockpit.saveProfile')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// Keep sponsor-only copy local while sharing the web workspace's legal wording.
String _profileCopy(BuildContext context, String key) {
  final language = AirmiusScope.of(context).language;
  return _profileMessages[language]?[key] ??
      _profileMessages[AirmiusLanguage.en]?[key] ??
      key;
}

const _profileMessages = <AirmiusLanguage, Map<String, String>>{
  AirmiusLanguage.de: {
    'legal_name': 'Rechtlicher Unternehmens-/Organisationsname',
    'country': 'Sitzland',
    'region': 'Region (optional)',
    'city': 'Ort (optional)',
    'registration': 'Register-/Vereinsnummer (optional)',
    'vat_id': 'USt-IdNr. (optional)',
    'legal_accuracy':
        'Ich bestätige, dass die rechtlichen Angaben korrekt sind und ich für die Organisation handeln darf.',
    'data_privacy':
        'Ich nutze Kontakt- und Kampagnendaten nur zweckgebunden und beachte die Datenschutzregeln.',
    'verification_title': 'Öffentliche Verifizierung',
    'verification_pending_review':
        'Deine Angaben werden geprüft und sind bis zur Freigabe nicht öffentlich sichtbar.',
    'verification_verified':
        'Dein geprüftes Sponsorprofil ist öffentlich sichtbar.',
    'verification_rejected':
        'Bitte korrigiere die beanstandeten Angaben und reiche das Profil erneut ein.',
    'logo_light': 'Logo für hellen Hintergrund',
    'logo_dark': 'Logo für dunklen Hintergrund',
    'review_after_save': 'Nach dem Speichern wird das Profil erneut geprüft.',
    'queued':
        'Das Profil wurde zur späteren Übertragung vorgemerkt. Die Speicherung auf dem Server ist noch nicht bestätigt.',
  },
  AirmiusLanguage.en: {
    'legal_name': 'Legal company/organisation name',
    'country': 'Country of establishment',
    'region': 'Region (optional)',
    'city': 'City (optional)',
    'registration': 'Registration number (optional)',
    'vat_id': 'VAT ID (optional)',
    'legal_accuracy':
        'I confirm the legal details are correct and I am authorised to act for the organisation.',
    'data_privacy':
        'I use contact and campaign data only for its intended purpose and follow privacy rules.',
    'verification_title': 'Public verification',
    'verification_pending_review':
        'Your details are under review and remain private until approved.',
    'verification_verified':
        'Your verified sponsor profile is publicly visible.',
    'verification_rejected':
        'Correct the flagged details and submit the profile again.',
    'logo_light': 'Logo for light backgrounds',
    'logo_dark': 'Logo for dark backgrounds',
    'review_after_save': 'Saving submits the profile for review again.',
    'queued':
        'The profile is queued for later delivery. Saving on the server is not yet confirmed.',
  },
  AirmiusLanguage.fr: {
    'legal_name': 'Raison sociale de l’entreprise/organisation',
    'country': 'Pays d’établissement',
    'registration': 'Numéro d’immatriculation (facultatif)',
    'vat_id': 'N° TVA (facultatif)',
    'legal_accuracy':
        'Je confirme que les informations légales sont exactes et que je peux agir pour l’organisation.',
    'data_privacy':
        'J’utilise les données de contact et de campagne uniquement aux fins prévues et respecte les règles de confidentialité.',
    'verification_title': 'Vérification publique',
    'verification_pending_review':
        'Vos informations sont en cours de vérification et restent privées jusqu’à leur approbation.',
    'verification_verified':
        'Votre profil sponsor vérifié est visible publiquement.',
    'verification_rejected':
        'Corrigez les informations signalées et renvoyez le profil.',
    'logo_light': 'Logo sur fond clair',
    'logo_dark': 'Logo sur fond sombre',
    'review_after_save':
        'Après enregistrement, le profil sera à nouveau examiné.',
    'queued':
        'Le profil est en attente de transmission. Son enregistrement sur le serveur n’est pas encore confirmé.',
  },
  AirmiusLanguage.ar: {
    'legal_name': 'الاسم القانوني للشركة أو المؤسسة',
    'country': 'بلد التأسيس',
    'registration': 'رقم التسجيل (اختياري)',
    'vat_id': 'الرقم الضريبي (اختياري)',
    'legal_accuracy':
        'أؤكد صحة البيانات القانونية وأنني مخوّل بالتصرف نيابةً عن المؤسسة.',
    'data_privacy':
        'أستخدم بيانات الاتصال والحملات للغرض المحدد فقط وألتزم بقواعد الخصوصية.',
    'verification_title': 'التحقق العلني',
    'verification_pending_review':
        'بياناتك قيد المراجعة وستبقى غير علنية حتى الموافقة.',
    'verification_verified': 'ملف الراعي الموثّق ظاهر للعامة.',
    'verification_rejected':
        'صحّح البيانات المشار إليها ثم أرسل الملف من جديد.',
    'logo_light': 'شعار للخلفيات الفاتحة',
    'logo_dark': 'شعار للخلفيات الداكنة',
    'review_after_save': 'يُرسل الملف للمراجعة مجدداً عند الحفظ.',
    'queued':
        'الملف في انتظار الإرسال لاحقاً. لم يتم تأكيد حفظه على الخادم بعد.',
  },
};

class _SponsorCockpitError extends StatelessWidget {
  const _SponsorCockpitError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: Text(t('common.retry')),
            ),
          ],
        ),
      ),
    );
  }
}

Map<String, dynamic> _map(Object? value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};

List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value.whereType<Map>().map(_map).toList(growable: false)
    : const [];

String _displayList(Object? value) {
  if (value is List) {
    return value
        .map((item) => _text(item))
        .where((item) => item.isNotEmpty)
        .join(', ');
  }
  if (value is Map) {
    return value.entries
        .where((entry) => entry.value == true || _text(entry.value).isNotEmpty)
        .map(
          (entry) => _text(entry.value) == 'true'
              ? '${entry.key}'
              : '${entry.key}: ${entry.value}',
        )
        .join(', ');
  }
  return _text(value);
}

String _dateLabel(Object? value) {
  final parsed = DateTime.tryParse(_text(value));
  return parsed == null
      ? _text(value)
      : DateFormat('dd.MM.yyyy').format(parsed);
}

String _dateRange(Object? start, Object? end) {
  final from = _dateLabel(start);
  final until = _dateLabel(end);
  if (from.isEmpty) return until;
  if (until.isEmpty) return from;
  return '$from – $until';
}

String _statusLabel(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');

String _sponsorTerm(BuildContext context, String key) {
  final language = Localizations.localeOf(context).languageCode;
  const values = <String, Map<String, String>>{
    'de': {
      'package': 'Paket',
      'rights': 'Rechte und Leistungen',
      'agreement': 'Vereinbarung',
      'renewal': 'Verlängerungsfrist',
      'deliverables': 'Vereinbarte Leistungen',
      'noDeliverables': 'Noch keine Leistung erfasst.',
      'due': 'Fällig',
      'evidence': 'Nachweis',
      'campaignMaterial': 'Kampagnenmaterial & Aufträge',
      'assets': 'Creative-Assets',
      'briefs': 'Agenturbriefings',
      'noAssets': 'Noch kein Kampagnenmaterial vorhanden.',
      'noBriefs': 'Noch kein Agenturbriefing vorhanden.',
      'open': 'Öffnen',
    },
    'en': {
      'package': 'Package',
      'rights': 'Rights and benefits',
      'agreement': 'Agreement',
      'renewal': 'Renewal deadline',
      'deliverables': 'Agreed deliverables',
      'noDeliverables': 'No deliverable recorded yet.',
      'due': 'Due',
      'evidence': 'Evidence',
      'campaignMaterial': 'Campaign assets & briefs',
      'assets': 'Creative assets',
      'briefs': 'Agency briefs',
      'noAssets': 'No campaign asset yet.',
      'noBriefs': 'No agency brief yet.',
      'open': 'Open',
    },
    'fr': {
      'package': 'Forfait',
      'rights': 'Droits et prestations',
      'agreement': 'Accord',
      'renewal': 'Date limite de renouvellement',
      'deliverables': 'Prestations convenues',
      'noDeliverables': 'Aucune prestation enregistrée.',
      'due': 'Échéance',
      'evidence': 'Preuve',
      'campaignMaterial': 'Ressources et briefs de campagne',
      'assets': 'Ressources créatives',
      'briefs': 'Briefs agence',
      'noAssets': 'Aucune ressource de campagne.',
      'noBriefs': 'Aucun brief agence.',
      'open': 'Ouvrir',
    },
    'ar': {
      'package': 'الباقة',
      'rights': 'الحقوق والمزايا',
      'agreement': 'الاتفاقية',
      'renewal': 'موعد التجديد',
      'deliverables': 'الخدمات المتفق عليها',
      'noDeliverables': 'لم تُسجل أي خدمة بعد.',
      'due': 'الاستحقاق',
      'evidence': 'الإثبات',
      'campaignMaterial': 'مواد الحملة والطلبات',
      'assets': 'مواد إبداعية',
      'briefs': 'موجزات الوكالة',
      'noAssets': 'لا توجد مواد حملة بعد.',
      'noBriefs': 'لا يوجد موجز وكالة بعد.',
      'open': 'فتح',
    },
  };
  return values[language]?[key] ?? values['de']![key] ?? key;
}

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

double _double(Object? value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString() ?? '') ?? 0;
