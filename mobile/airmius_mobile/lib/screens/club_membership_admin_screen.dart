import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_operations_screen.dart';
import 'club_membership_management_screen.dart';
import 'club_membership_prospects_screen.dart';
import 'membership_operations_screen.dart';

class ClubMembershipAdminScreen extends StatefulWidget {
  const ClubMembershipAdminScreen({super.key});

  @override
  State<ClubMembershipAdminScreen> createState() =>
      _ClubMembershipAdminScreenState();
}

class _ClubMembershipAdminScreenState extends State<ClubMembershipAdminScreen> {
  static const _fieldDefinitions =
      <({String key, String labelKey, String sectionKey})>[
        (
          key: 'first_name',
          labelKey: 'membership.field.firstName',
          sectionKey: 'membership.fieldSection.personal',
        ),
        (
          key: 'last_name',
          labelKey: 'membership.field.lastName',
          sectionKey: 'membership.fieldSection.personal',
        ),
        (
          key: 'birth_date',
          labelKey: 'membership.field.birthDate',
          sectionKey: 'membership.fieldSection.personal',
        ),
        (
          key: 'gender',
          labelKey: 'membership.field.gender',
          sectionKey: 'membership.fieldSection.personal',
        ),
        (
          key: 'email',
          labelKey: 'membership.field.email',
          sectionKey: 'membership.fieldSection.contact',
        ),
        (
          key: 'phone',
          labelKey: 'membership.field.phone',
          sectionKey: 'membership.fieldSection.contact',
        ),
        (
          key: 'street',
          labelKey: 'membership.field.street',
          sectionKey: 'membership.fieldSection.address',
        ),
        (
          key: 'house_number',
          labelKey: 'membership.field.houseNumber',
          sectionKey: 'membership.fieldSection.address',
        ),
        (
          key: 'postal_code',
          labelKey: 'membership.field.postalCode',
          sectionKey: 'membership.fieldSection.address',
        ),
        (
          key: 'city',
          labelKey: 'membership.field.city',
          sectionKey: 'membership.fieldSection.address',
        ),
        (
          key: 'guardian_email',
          labelKey: 'membership.field.guardianEmail',
          sectionKey: 'membership.fieldSection.guardian',
        ),
        (
          key: 'emergency_contact_phone',
          labelKey: 'membership.field.emergencyPhone',
          sectionKey: 'membership.fieldSection.emergency',
        ),
        (
          key: 'athlete_license_number',
          labelKey: 'membership.field.licenseNumber',
          sectionKey: 'membership.fieldSection.sport',
        ),
        (
          key: 'sepa_iban',
          labelKey: 'membership.field.iban',
          sectionKey: 'membership.fieldSection.payment',
        ),
        (
          key: 'sepa_bic',
          labelKey: 'membership.field.bic',
          sectionKey: 'membership.fieldSection.payment',
        ),
      ];

  final Map<String, String> _fieldModes = {
    for (final field in _fieldDefinitions) field.key: 'off',
  };
  final List<JsonMap> _documents = <JsonMap>[];
  Set<String> _paymentMethods = {'bank_transfer'};
  bool _requestsEnabled = true;
  int _setupStep = 0;

  Future<AirmiusClub>? _clubFuture;
  Future<AirmiusPage<AirmiusClubMembershipRequest>>? _requestsFuture;
  bool _reviewing = false;
  bool _saving = false;
  int? _hydratedClubId;
  String _clubName = 'Verein';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubFuture ??= _loadClub();
    _requestsFuture ??= _loadRequests();
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  Future<AirmiusClub> _loadClub() async {
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    final clubs = await repository.searchClubs(mine: true);
    final manageable = clubs.items.where((club) => club.canManage).toList();
    if (manageable.isEmpty) {
      throw StateError(t('membership.noManagedClub'));
    }
    final club = await repository.club(manageable.first.id);
    _clubName = club.name;
    _hydrate(club);
    return club;
  }

  Future<AirmiusPage<AirmiusClubMembershipRequest>> _loadRequests() async {
    final repository = AirmiusServicesScope.of(
      context,
    ).repositories.memberships;
    final club = await _clubFuture!;
    return repository.clubRequests(club.id);
  }

  void _reloadRequests() {
    setState(() {
      _requestsFuture = _loadRequests();
    });
  }

  void _hydrate(AirmiusClub club) {
    if (_hydratedClubId == club.id) return;
    _hydratedClubId = club.id;
    final settings = club.management?.settings ?? const <String, dynamic>{};
    final fields = settings['membership_application_fields'];
    if (fields is List) {
      for (final item in fields.whereType<JsonMap>()) {
        final key = item['key']?.toString();
        final mode = item['mode']?.toString();
        if (key != null &&
            mode != null &&
            {'off', 'optional', 'required'}.contains(mode)) {
          _fieldModes[key] = mode;
        }
      }
    } else if (fields is JsonMap) {
      for (final entry in fields.entries) {
        final mode = entry.value?.toString();
        if ({'off', 'optional', 'required'}.contains(mode)) {
          _fieldModes[entry.key] = mode!;
        }
      }
    }
    _requestsEnabled = settings['membership_requests_enabled'] != false;
    final methods = settings['membership_payment_methods'];
    if (methods is List && methods.isNotEmpty) {
      _paymentMethods = methods.map((value) => value.toString()).toSet();
    }
    final documents = settings['membership_application_documents'];
    if (documents is List) {
      _documents
        ..clear()
        ..addAll(documents.whereType<JsonMap>());
    }
  }

  String _paymentLabel(String value) => switch (value) {
    'cash' => t('membership.payment.cash'),
    'sepa_debit' => t('membership.payment.sepaDebit'),
    _ => t('membership.payment.bankTransfer'),
  };

  List<String> _setupStepLabels() => [
    t('membership.allowRequests'),
    t('membership.applicationFields'),
    t('membership.payment'),
    t('membership.linkedDocuments'),
  ];

  void _nextSetupStep() {
    if (_setupStep < _setupStepLabels().length - 1) {
      setState(() => _setupStep += 1);
    } else {
      _saveSettings();
    }
  }

  void _previousSetupStep() {
    if (_setupStep > 0) setState(() => _setupStep -= 1);
  }

  Future<void> _saveSettings() async {
    if (_saving) return;
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    setState(() => _saving = true);
    try {
      final club = await _clubFuture!;
      await repository.updateMembershipSettings(club.id, {
        'membership_requests_enabled': _requestsEnabled,
        'membership_application_fields': _fieldModes,
        'membership_payment_methods': _paymentMethods.toList(),
        'membership_application_documents': _documents,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('membership.settingsSavedAfter'))),
      );
      setState(() => _saving = false);
      _reloadRequests();
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('membership.saveFailed')}: ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final requestsFuture = _requestsFuture;
    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Theme.of(context).colorScheme.onPrimary,
        icon: Icon(Icons.folder_shared_outlined),
        label: Text(
          t('membership.documentOps'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        onPressed: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => const FileOperationsScreen(initialTab: 'Antrag'),
          ),
        ),
      ),
      appBar: AppBar(
        backgroundColor:
            (Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context)),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('membership.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('membership.title'),
        subtitle: t('membership.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('membership.admin')),
                  SizedBox(height: 8),
                  Text(
                    _clubName,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  SizedBox(height: 6),
                  Text(
                    t('membership.adminHint'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            FutureBuilder<AirmiusPage<AirmiusClubMembershipRequest>>(
              future: requestsFuture,
              builder: (context, snapshot) {
                final requests =
                    snapshot.data?.items ??
                    const <AirmiusClubMembershipRequest>[];
                final pending = requests
                    .where((request) => request.status == 'pending')
                    .length;
                final approved = requests
                    .where((request) => request.status == 'approved')
                    .length;
                final cards = [
                  MetricCard(
                    value: '${snapshot.hasData ? pending : '…'}',
                    label: t('membership.status.pending'),
                  ),
                  MetricCard(
                    value: '$approved',
                    label: t('membership.status.active'),
                  ),
                  MetricCard(
                    value: '${_documents.length}',
                    label: t('membership.documentsAfter'),
                  ),
                ];
                return LayoutBuilder(
                  builder: (context, constraints) {
                    final columns = constraints.maxWidth < 520 ? 2 : 3;
                    final gap = 10.0;
                    final width = columns == 2
                        ? (constraints.maxWidth - gap) / 2
                        : (constraints.maxWidth - gap * 2) / 3;
                    return Wrap(
                      spacing: gap,
                      runSpacing: gap,
                      children: [
                        for (final card in cards)
                          SizedBox(width: width, child: card),
                      ],
                    );
                  },
                );
              },
            ),
            const SizedBox(height: 14),
            FutureBuilder<AirmiusClub>(
              future: _clubFuture,
              builder: (context, snapshot) => AirmiusPanel(
                onTap: snapshot.hasData
                    ? () {
                        final club = snapshot.data!;
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => ClubMembershipProspectsScreen(
                              clubId: club.id,
                              clubName: club.name,
                              teams: club.management?.teams ?? club.teams,
                              membershipTypes:
                                  club.management?.membershipTypes ?? const [],
                            ),
                          ),
                        );
                      }
                    : null,
                borderColor: AirmiusColors.green.withValues(alpha: 0.55),
                child: Row(
                  children: [
                    const Icon(
                      Icons.person_search_outlined,
                      color: AirmiusColors.green,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Eyebrow(t('membership.prospect.title')),
                          const SizedBox(height: 5),
                          Text(
                            t('membership.prospect.adminHint'),
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              height: 1.35,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Icon(
                      Icons.chevron_right,
                      color: airmiusMutedColor(context),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ClubMembershipManagementScreen(),
                ),
              ),
              borderColor: airmiusAccentColor(context).withValues(alpha: 0.55),
              child: Row(
                children: [
                  Icon(
                    Icons.groups_3_outlined,
                    color: airmiusAccentColor(context),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow(t('membership.completeManagement')),
                        const SizedBox(height: 5),
                        Text(
                          t('membership.completeManagementHint'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: 0.55),
              child: FutureBuilder<AirmiusPage<AirmiusClubMembershipRequest>>(
                future: requestsFuture,
                builder: (context, snapshot) {
                  final requests =
                      snapshot.data?.items
                          .where((request) => request.status == 'pending')
                          .toList() ??
                      const <AirmiusClubMembershipRequest>[];
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Eyebrow(t('membership.newRequest'))),
                          if (snapshot.connectionState ==
                              ConnectionState.waiting)
                            SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: airmiusAccentColor(context),
                              ),
                            )
                          else
                            IconButton(
                              onPressed: _reloadRequests,
                              icon: Icon(
                                Icons.refresh_outlined,
                                color: airmiusMutedColor(context),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      if (snapshot.hasError)
                        _RequestFallback(onRetry: _reloadRequests)
                      else if (snapshot.connectionState ==
                          ConnectionState.waiting)
                        Center(
                          child: Padding(
                            padding: EdgeInsets.all(12),
                            child: CircularProgressIndicator(
                              color: airmiusAccentColor(context),
                            ),
                          ),
                        )
                      else if (requests.isEmpty && snapshot.hasData)
                        Text(
                          t('membership.noOpenRequests'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w800,
                          ),
                        )
                      else
                        for (final request in requests) ...[
                          _RequestCard(request: request),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: _reviewing
                                    ? t('membership.saving')
                                    : t('membership.approve'),
                                icon: Icons.check_circle_outline,
                                onPressed: _reviewing
                                    ? null
                                    : () => _reviewRequest(
                                        request,
                                        approve: true,
                                      ),
                              ),
                              AirmiusButton(
                                label: t('membership.decline'),
                                icon: Icons.cancel_outlined,
                                danger: true,
                                onPressed: _reviewing
                                    ? null
                                    : () => _reviewRequest(
                                        request,
                                        approve: false,
                                      ),
                              ),
                              AirmiusButton(
                                label: t('membership.operations'),
                                icon: Icons.tune_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        const MembershipOperationsScreen(),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 14),
            _MembershipSetupWizard(
              title: t('membership.setupTitle'),
              hint: t('membership.setupHint'),
              labels: _setupStepLabels(),
              currentStep: _setupStep,
              onStepSelected: (step) => setState(() => _setupStep = step),
            ),
            const SizedBox(height: 14),
            if (_setupStep <= 1)
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(
                      t(
                        _setupStep == 0
                            ? 'membership.allowRequests'
                            : 'membership.applicationFields',
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t('membership.applicationFieldsBody'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (_setupStep == 0)
                      Material(
                        type: MaterialType.transparency,
                        child: SwitchListTile(
                          value: _requestsEnabled,
                          onChanged: (value) =>
                              setState(() => _requestsEnabled = value),
                          title: Text(
                            t('membership.allowRequests'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          subtitle: Text(
                            t(
                              _requestsEnabled
                                  ? 'membership.requestsEnabled'
                                  : 'membership.requestsDisabled',
                            ),
                            style: TextStyle(color: airmiusMutedColor(context)),
                          ),
                          activeThumbColor: airmiusAccentColor(context),
                          contentPadding: EdgeInsets.zero,
                        ),
                      )
                    else ...[
                      const Divider(height: 12),
                      for (final field in _fieldDefinitions)
                        Material(
                          type: MaterialType.transparency,
                          child: SwitchListTile(
                            value: _fieldModes[field.key] != 'off',
                            onChanged: (value) => setState(
                              () => _fieldModes[field.key] = value
                                  ? (_fieldModes[field.key] == 'required'
                                        ? 'required'
                                        : 'optional')
                                  : 'off',
                            ),
                            title: Text(
                              t(field.labelKey),
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            subtitle: Text(
                              '${t(field.sectionKey)} · ${_fieldModes[field.key] == 'required'
                                  ? t('membership.field.required')
                                  : _fieldModes[field.key] == 'off'
                                  ? t('membership.field.hidden')
                                  : t('membership.field.optional')}',
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                              ),
                            ),
                            activeThumbColor: airmiusAccentColor(context),
                            contentPadding: EdgeInsets.zero,
                          ),
                        ),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 14),
            if (_setupStep == 2)
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('membership.payment')),
                    const SizedBox(height: 12),
                    Text(
                      t('membership.paymentMethods'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final method in const [
                          'bank_transfer',
                          'cash',
                          'sepa_debit',
                        ])
                          FilterChip(
                            selected: _paymentMethods.contains(method),
                            label: Text(_paymentLabel(method)),
                            onSelected: (selected) => setState(() {
                              if (selected) {
                                _paymentMethods = {..._paymentMethods, method};
                              } else if (_paymentMethods.length > 1) {
                                _paymentMethods = {..._paymentMethods}
                                  ..remove(method);
                              }
                            }),
                          ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      t('membership.contributionRulesHint'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 14),
            if (_setupStep == 3)
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('membership.linkedDocuments')),
                    const SizedBox(height: 10),
                    if (_documents.isEmpty)
                      Text(
                        t('membership.noDocuments'),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      )
                    else
                      for (final document in _documents)
                        _DocumentLinkLine(
                          title:
                              document['title']?.toString() ??
                              t('membership.document'),
                          status: document['is_required'] == true
                              ? t('membership.field.required')
                              : t('membership.field.optional'),
                        ),
                  ],
                ),
              ),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 12,
                runSpacing: 12,
                children: [
                  if (_setupStep > 0)
                    AirmiusButton(
                      label: t('common.back'),
                      icon: Icons.arrow_back_outlined,
                      secondary: true,
                      onPressed: _previousSetupStep,
                    ),
                  Text(
                    '${_setupStep + 1} / ${_setupStepLabels().length} · ${_setupStepLabels()[_setupStep]}',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  AirmiusButton(
                    label: _setupStep == _setupStepLabels().length - 1
                        ? (_saving
                              ? t('membership.saving')
                              : t('membership.save'))
                        : t('membership.next'),
                    icon: _setupStep == _setupStepLabels().length - 1
                        ? Icons.save_outlined
                        : Icons.arrow_forward_outlined,
                    onPressed: _saving ? null : _nextSetupStep,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            AirmiusButton(
              label: t('membership.operations'),
              icon: Icons.tune_outlined,
              secondary: true,
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const MembershipOperationsScreen(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _reviewRequest(
    AirmiusClubMembershipRequest request, {
    required bool approve,
  }) async {
    if (_reviewing) return;
    final applicant = request.applicantName ?? t('membership.member');
    final title = approve
        ? t('membership.reviewApproveTitle')
        : t('membership.reviewDeclineTitle');
    final message = approve
        ? t('membership.reviewApproveMessage').replaceFirst('{name}', applicant)
        : t(
            'membership.reviewDeclineMessage',
          ).replaceFirst('{name}', applicant);
    final ok = approve ? true : await confirmDanger(context, title, message);
    if (!ok || !mounted) return;

    setState(() => _reviewing = true);
    try {
      final repository = AirmiusServicesScope.of(
        context,
      ).repositories.memberships;
      if (approve) {
        await repository.approveClubRequest(request.clubId, request.id);
      } else {
        await repository.declineClubRequest(request.clubId, request.id);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve
                ? t('membership.reviewApproved')
                : t('membership.reviewDeclined'),
          ),
        ),
      );
      setState(() {
        _reviewing = false;
        _requestsFuture = _loadRequests();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _reviewing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('membership.reviewActionFailed')}: ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
          ),
        ),
      );
    }
  }
}

class _MembershipSetupWizard extends StatelessWidget {
  const _MembershipSetupWizard({
    required this.title,
    required this.hint,
    required this.labels,
    required this.currentStep,
    required this.onStepSelected,
  });

  final String title;
  final String hint;
  final List<String> labels;
  final int currentStep;
  final ValueChanged<int> onStepSelected;

  @override
  Widget build(BuildContext context) {
    final accent = airmiusAccentColor(context);
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(title),
          const SizedBox(height: 6),
          Text(
            hint,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 14),
          LinearProgressIndicator(
            value: labels.isEmpty ? 0 : (currentStep + 1) / labels.length,
            minHeight: 6,
            color: accent,
            backgroundColor: airmiusSurfaceColor(context),
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (var index = 0; index < labels.length; index++) ...[
                  if (index > 0) const SizedBox(width: 8),
                  ChoiceChip(
                    label: Text('${index + 1}. ${labels[index]}'),
                    selected: currentStep == index,
                    onSelected: (_) => onStepSelected(index),
                    selectedColor: accent.withValues(alpha: 0.24),
                    backgroundColor: airmiusSurfaceColor(context),
                    labelStyle: TextStyle(
                      color: currentStep == index
                          ? airmiusTextColor(context)
                          : airmiusMutedColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.request});

  final AirmiusClubMembershipRequest request;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final name = request.applicantName ?? t('membership.member');
    final club = request.clubName ?? t('membership.club');
    final status = _statusLabel(request.status, t);
    final meta = request.message == null || request.message!.isEmpty
        ? t('membership.requestSummary').replaceFirst('{club}', club)
        : request.message!;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          AirmiusAvatar(name),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(meta, style: TextStyle(color: airmiusMutedColor(context))),
              ],
            ),
          ),
          StatusPill(status, color: AirmiusColors.green),
        ],
      ),
    );
  }

  String _statusLabel(String status, String Function(String) t) {
    return switch (status) {
      'approved' => t('membership.inbox.status.approved'),
      'declined' => t('membership.inbox.status.declined'),
      'withdrawn' => t('membership.inbox.status.withdrawn'),
      'review' => t('membership.inbox.status.review'),
      _ => t('membership.inbox.status.pending'),
    };
  }
}

class _RequestFallback extends StatelessWidget {
  const _RequestFallback({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          t('membership.requestLoadFailed'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 10),
        AirmiusButton(
          label: t('common.retry'),
          icon: Icons.refresh_outlined,
          secondary: true,
          onPressed: onRetry,
        ),
      ],
    );
  }
}

class _DocumentLinkLine extends StatelessWidget {
  const _DocumentLinkLine({required this.title, required this.status});

  final String title;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        children: [
          Icon(Icons.description_outlined, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}
