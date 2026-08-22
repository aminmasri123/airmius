import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_operations_screen.dart';
import 'membership_operations_screen.dart';
import 'notification_chat_operations_screen.dart';

String _inboxTabKey(String value) {
  return switch (value.trim().toLowerCase()) {
    'alle' || 'all' => 'all',
    'prüfung' || 'review' || 'in review' => 'review',
    'rückzug' || 'withdrawn' || 'withdrawal' => 'withdrawn',
    'angenommen' || 'approved' || 'approve' => 'approved',
    'abgelehnt' || 'declined' || 'decline' => 'declined',
    _ => 'pending',
  };
}

class ClubRequestInboxScreen extends StatefulWidget {
  const ClubRequestInboxScreen({
    super.key,
    this.initialTab = 'Neu',
    this.initialClubId,
  });

  final String initialTab;
  final int? initialClubId;

  @override
  State<ClubRequestInboxScreen> createState() => _ClubRequestInboxScreenState();
}

class _ClubRequestInboxScreenState extends State<ClubRequestInboxScreen> {
  late String _tab = _inboxTabKey(widget.initialTab);
  bool _notifyAdmins = true;
  bool _notifyApplicant = true;
  bool _autoTask = true;
  bool _showWithdrawn = true;
  bool _showSettings = false;
  bool _loading = true;
  String? _loadError;
  int? _clubId;
  List<_MembershipRequest> _requests = const [];
  final Set<int> _decidingRequestIds = <int>{};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loading && _loadError == null && _clubId == null) {
      _loadRequests();
    }
  }

  Future<void> _loadRequests() async {
    try {
      final services = AirmiusServicesScope.of(context);
      final userId = services.authState.user?.id;
      final clubs = await services.repositories.clubs.searchClubs(mine: true);
      final managed = clubs.items
          .where((club) => club.canManage || club.ownerId == userId)
          .toList();
      final scopedManaged = widget.initialClubId == null
          ? managed
          : managed.where((club) => club.id == widget.initialClubId).toList();
      if (scopedManaged.isEmpty) {
        if (!mounted) return;
        setState(() {
          _loading = false;
          _requests = const [];
        });
        return;
      }
      _clubId = scopedManaged.first.id;
      final pages = await Future.wait(
        scopedManaged.map(
          (club) => services.repositories.memberships.clubRequests(club.id),
        ),
      );
      final allRequests = pages.expand((page) => page.items);
      if (!mounted) return;
      setState(() {
        _requests = allRequests.map(_mapRequest).toList();
        _loading = false;
        _loadError = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _loadError = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('membership.inboxLoadFailed');
      });
    }
  }

  _MembershipRequest _mapRequest(AirmiusClubMembershipRequest request) {
    final t = AirmiusScope.of(context).t;
    final data = request.applicationData;
    final address = [
      data['street'],
      data['house_number'],
      data['postal_code'],
      data['city'],
    ].whereType<String>().where((value) => value.trim().isNotEmpty).join(' ');
    return _MembershipRequest(
      id: request.id,
      clubId: request.clubId,
      apiStatus: request.status,
      status: _statusLabel(request.status, t),
      name: request.applicantName ?? t('membership.inbox.applicantFallback'),
      email: request.applicantEmail ?? data['email']?.toString() ?? '',
      address: address.isEmpty ? t('membership.inbox.addressMissing') : address,
      type: request.membershipTypeName ?? request.type,
      received: request.createdAt.toLocal().toString().substring(0, 16),
      body: request.message ?? t('membership.inbox.messageFallback'),
      payment:
          request.preferredPaymentMethod ?? t('membership.inbox.notProvided'),
      data: data,
      documentTitles: request.acceptedDocuments,
      hasDocuments: request.acceptedDocuments.isNotEmpty,
      documents: request.acceptedDocuments.isEmpty
          ? t('membership.inbox.documentsNone')
          : request.acceptedDocuments.length == 1
          ? t('membership.inbox.documentOne')
          : t(
              'membership.inbox.documentMany',
            ).replaceFirst('{count}', '${request.acceptedDocuments.length}'),
      color: _statusColor(request.status),
    );
  }

  String _statusLabel(String status, String Function(String) t) =>
      switch (status) {
        'approved' => t('membership.inbox.status.approved'),
        'declined' => t('membership.inbox.status.declined'),
        'withdrawn' => t('membership.inbox.status.withdrawn'),
        'review' => t('membership.inbox.status.review'),
        _ => t('membership.inbox.status.pending'),
      };

  String _tabLabel(String tab, String Function(String) t) =>
      t('membership.inbox.tab.$tab');

  Color _statusColor(String status) => switch (status) {
    'approved' => AirmiusColors.green,
    'declined' || 'withdrawn' => AirmiusColors.red,
    'review' => airmiusAccentColor(context),
    _ => AirmiusColors.amber,
  };

  Future<void> _decide(_MembershipRequest request, bool approve) async {
    final clubId = request.clubId;
    final t = AirmiusScope.of(context).t;
    final action = approve
        ? t('membership.inbox.accept')
        : t('membership.inbox.decline');
    final repository = AirmiusServicesScope.of(
      context,
    ).repositories.memberships;
    final note = await showDialog<String?>(
      context: context,
      builder: (_) => _DecisionNoteDialog(
        title: action,
        noteLabel: t('membership.inbox.noteOptional'),
        cancelLabel: t('membership.inbox.cancel'),
        confirmLabel: approve
            ? t('membership.inbox.accept')
            : t('membership.inbox.decline'),
      ),
    );
    if (note == null) return;
    if (_decidingRequestIds.contains(request.id)) return;
    setState(() => _decidingRequestIds.add(request.id));
    try {
      late final AirmiusClubMembershipRequest updated;
      if (approve) {
        updated = await repository.approveClubRequest(
          clubId,
          request.id,
          reviewNote: note,
        );
      } else {
        updated = await repository.declineClubRequest(
          clubId,
          request.id,
          reviewNote: note,
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve
                ? t('membership.inbox.actionApproved')
                : t('membership.inbox.actionDeclined'),
          ),
        ),
      );
      setState(() {
        final index = _requests.indexWhere((item) => item.id == request.id);
        if (index >= 0) {
          _requests = [..._requests]..[index] = _mapRequest(updated);
        }
        _decidingRequestIds.remove(request.id);
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _decidingRequestIds.remove(request.id));
      final message = error is AirmiusApiException
          ? error.userMessage
          : AirmiusScope.of(context).t('membership.inboxActionFailed');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final requests = _requests
        .where((request) => _tab == 'all' || request.apiStatus == _tab)
        .toList();
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('membership.inbox.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('membership.inbox.title'),
        subtitle: t('membership.inbox.subtitle'),
        trailing: StatusPill(t('membership.inbox.badge')),
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
                    t('membership.inbox.heroTitle'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    t('membership.inbox.heroBody'),
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
                          value:
                              '${_requests.where((request) => request.apiStatus == 'pending').length}',
                          label: t('membership.inbox.tab.pending'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(
                          value:
                              '${_requests.where((request) => request.apiStatus == 'withdrawn').length}',
                          label: t('membership.inbox.tab.withdrawn'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(
                          value:
                              '${_requests.where((request) => request.hasDocuments).length}',
                          label: t('membership.document'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in _tabs)
                        ChoiceChip(
                          label: Text(_tabLabel(tab, t)),
                          selected: _tab == tab,
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.green.withValues(
                            alpha: .22,
                          ),
                          backgroundColor: airmiusSurfaceSoftColor(context),
                          side: BorderSide(
                            color: _tab == tab
                                ? AirmiusColors.green
                                : airmiusBorderColor(context),
                          ),
                          labelStyle: TextStyle(
                            color: _tab == tab
                                ? airmiusTextColor(context)
                                : airmiusMutedColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      t('membership.inbox.settingsTitle'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: t('membership.inbox.settingsTitle'),
                    icon: const Icon(Icons.settings_outlined),
                    onPressed: () =>
                        setState(() => _showSettings = !_showSettings),
                  ),
                ],
              ),
            ),
            if (_showSettings) ...[
              const SizedBox(height: 10),
              _InboxSettingsPanel(
                notifyAdmins: _notifyAdmins,
                notifyApplicant: _notifyApplicant,
                autoTask: _autoTask,
                showWithdrawn: _showWithdrawn,
                onAdmins: (value) => setState(() => _notifyAdmins = value),
                onApplicant: (value) =>
                    setState(() => _notifyApplicant = value),
                onTask: (value) => setState(() => _autoTask = value),
                onWithdrawn: (value) => setState(() => _showWithdrawn = value),
              ),
            ],
            const SizedBox(height: 16),
            if (_loading)
              const AirmiusPanel(
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_loadError != null)
              AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: .45),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(_loadError!),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: AirmiusScope.of(context).t('common.retry'),
                      icon: Icons.refresh_outlined,
                      secondary: true,
                      onPressed: () {
                        setState(() {
                          _loading = true;
                          _loadError = null;
                        });
                        _loadRequests();
                      },
                    ),
                  ],
                ),
              )
            else if (requests.isEmpty)
              EmptyPanel(t('membership.inbox.empty'))
            else
              for (final request in requests) ...[
                if (_showWithdrawn || request.apiStatus != 'withdrawn')
                  _RequestCard(
                    request: request,
                    busy: _decidingRequestIds.contains(request.id),
                    onApprove: () => _decide(request, true),
                    onDecline: () => _decide(request, false),
                  ),
                if (_showWithdrawn || request.apiStatus != 'withdrawn')
                  const SizedBox(height: 12),
              ],
            _InboxWorkflowPanel(tab: _tab, onReload: _loadRequests),
          ],
        ),
      ),
    );
  }
}

class _DecisionNoteDialog extends StatefulWidget {
  const _DecisionNoteDialog({
    required this.title,
    required this.noteLabel,
    required this.cancelLabel,
    required this.confirmLabel,
  });

  final String title;
  final String noteLabel;
  final String cancelLabel;
  final String confirmLabel;

  @override
  State<_DecisionNoteDialog> createState() => _DecisionNoteDialogState();
}

class _DecisionNoteDialogState extends State<_DecisionNoteDialog> {
  final TextEditingController _noteController = TextEditingController();

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: TextField(
        controller: _noteController,
        maxLines: 3,
        decoration: InputDecoration(labelText: widget.noteLabel),
      ),
      actions: [
        SizedBox(
          width: double.infinity,
          child: Wrap(
            alignment: WrapAlignment.end,
            spacing: 8,
            runSpacing: 8,
            children: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(widget.cancelLabel),
              ),
              FilledButton(
                onPressed: () =>
                    Navigator.pop(context, _noteController.text.trim()),
                child: Text(widget.confirmLabel),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InboxSettingsPanel extends StatelessWidget {
  const _InboxSettingsPanel({
    required this.notifyAdmins,
    required this.notifyApplicant,
    required this.autoTask,
    required this.showWithdrawn,
    required this.onAdmins,
    required this.onApplicant,
    required this.onTask,
    required this.onWithdrawn,
  });

  final bool notifyAdmins;
  final bool notifyApplicant;
  final bool autoTask;
  final bool showWithdrawn;
  final ValueChanged<bool> onAdmins;
  final ValueChanged<bool> onApplicant;
  final ValueChanged<bool> onTask;
  final ValueChanged<bool> onWithdrawn;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context).withValues(alpha: .44),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('membership.inbox.settingsTitle')),
          const SizedBox(height: 8),
          Text(
            t('membership.inbox.settingsBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 10),
          _InboxSwitch(
            icon: Icons.notifications_active_outlined,
            title: t('membership.inbox.notifyAdminsTitle'),
            body: t('membership.inbox.notifyAdminsBody'),
            value: notifyAdmins,
            onChanged: onAdmins,
            color: AirmiusColors.green,
          ),
          _InboxSwitch(
            icon: Icons.person_outline,
            title: t('membership.inbox.notifyApplicantTitle'),
            body: t('membership.inbox.notifyApplicantBody'),
            value: notifyApplicant,
            onChanged: onApplicant,
            color: airmiusAccentColor(context),
          ),
          _InboxSwitch(
            icon: Icons.task_alt_outlined,
            title: t('membership.inbox.autoTaskTitle'),
            body: t('membership.inbox.autoTaskBody'),
            value: autoTask,
            onChanged: onTask,
            color: AirmiusColors.amber,
          ),
          _InboxSwitch(
            icon: Icons.undo_outlined,
            title: t('membership.inbox.showWithdrawnTitle'),
            body: t('membership.inbox.showWithdrawnBody'),
            value: showWithdrawn,
            onChanged: onWithdrawn,
            color: AirmiusColors.red,
          ),
        ],
      ),
    );
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({
    required this.request,
    required this.busy,
    required this.onApprove,
    required this.onDecline,
  });

  final _MembershipRequest request;
  final bool busy;
  final VoidCallback onApprove;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: request.color.withValues(alpha: .44),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AirmiusAvatar(request.name),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      request.name,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      request.body,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        StatusPill(request.status, color: request.color),
                        StatusPill(request.payment),
                        StatusPill(request.documents, color: request.color),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _RequestDataGrid(request: request),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: t('membership.inbox.review'),
                icon: Icons.fact_check_outlined,
                onPressed: () => showDialog<void>(
                  context: context,
                  builder: (_) => AlertDialog(
                    title: Text(
                      t(
                        'membership.inbox.reviewTitle',
                      ).replaceFirst('{name}', request.name),
                    ),
                    content: _RequestReviewContent(request: request),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: Text(t('membership.inbox.close')),
                      ),
                    ],
                  ),
                ),
              ),
              AirmiusButton(
                label: t('membership.inbox.accept'),
                icon: Icons.check_circle_outline,
                secondary: true,
                onPressed: busy || request.apiStatus != 'pending'
                    ? null
                    : onApprove,
              ),
              AirmiusButton(
                label: t('membership.inbox.decline'),
                icon: Icons.cancel_outlined,
                danger: true,
                onPressed: busy || request.apiStatus != 'pending'
                    ? null
                    : onDecline,
              ),
              AirmiusButton(
                label: t('membership.inbox.message'),
                icon: Icons.chat_bubble_outline,
                secondary: true,
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        NotificationChatOperationsScreen(initialTab: 'Chat'),
                  ),
                ),
              ),
              AirmiusButton(
                label: t('membership.inbox.files'),
                icon: Icons.folder_outlined,
                secondary: true,
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => FileOperationsScreen()),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RequestDataGrid extends StatelessWidget {
  const _RequestDataGrid({required this.request});

  final _MembershipRequest request;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _DataLine(label: t('membership.inbox.email'), value: request.email),
          _DataLine(
            label: t('membership.inbox.address'),
            value: request.address,
          ),
          _DataLine(label: t('membership.inbox.type'), value: request.type),
          _DataLine(
            label: t('membership.inbox.received'),
            value: request.received,
          ),
        ],
      ),
    );
  }
}

class _RequestReviewContent extends StatelessWidget {
  const _RequestReviewContent({required this.request});

  final _MembershipRequest request;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final dataRows = request.data.entries
        .where(
          (entry) => entry.value != null && entry.value.toString().isNotEmpty,
        )
        .map(
          (entry) => _DataLine(
            label: _membershipRequestFieldLabel(entry.key, t),
            value: _membershipRequestFieldValue(entry.value, t),
          ),
        )
        .toList();
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(request.body),
          const SizedBox(height: 12),
          _DataLine(label: t('membership.inbox.email'), value: request.email),
          _DataLine(
            label: t('membership.inbox.address'),
            value: request.address,
          ),
          _DataLine(label: t('membership.inbox.type'), value: request.type),
          _DataLine(
            label: t('application.paymentMethod'),
            value: request.payment,
          ),
          if (dataRows.isNotEmpty) ...[
            const Divider(height: 22),
            for (final row in dataRows) row,
          ],
          const Divider(height: 22),
          Text(
            t('membership.document'),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            request.documentTitles.isEmpty
                ? t('membership.inbox.documentsNone')
                : request.documentTitles.join('\n'),
          ),
        ],
      ),
    );
  }
}

const Map<String, String> _membershipRequestFieldLabelKeys = {
  'first_name': 'membership.field.firstName',
  'last_name': 'membership.field.lastName',
  'birth_date': 'membership.field.birthDate',
  'gender': 'membership.field.gender',
  'email': 'membership.field.email',
  'phone': 'membership.field.phone',
  'country': 'clubs.wizard.country',
  'city': 'membership.field.city',
  'street': 'membership.field.street',
  'house_number': 'membership.field.houseNumber',
  'postal_code': 'membership.field.postalCode',
  'state': 'clubs.wizard.region',
  'guardian_name': 'application.guardianName',
  'guardian_email': 'membership.field.guardianEmail',
  'guardian_phone': 'application.guardianPhone',
  'emergency_contact_name': 'application.emergencyName',
  'emergency_contact_phone': 'membership.field.emergencyPhone',
  'athlete_license_number': 'membership.field.licenseNumber',
  'sepa_iban': 'membership.field.iban',
  'sepa_bic': 'membership.field.bic',
};

String _membershipRequestFieldLabel(String key, String Function(String) t) {
  final translationKey = _membershipRequestFieldLabelKeys[key];
  if (translationKey != null) {
    return t(translationKey);
  }

  final translatedKey = t(key);
  if (translatedKey != key) {
    return translatedKey;
  }

  return _humanizeMembershipRequestFieldKey(key);
}

String _membershipRequestFieldValue(Object? value, String Function(String) t) {
  if (value is bool) {
    return value ? 'Ja' : 'Nein';
  }

  if (value is List) {
    return value.whereType<String>().join(', ');
  }

  if (value is String) {
    final normalized = value.trim().toLowerCase();
    if (normalized.isEmpty) return value;
    return switch (normalized) {
      'male' => t('application.gender.male'),
      'female' => t('application.gender.female'),
      'diverse' => t('application.gender.diverse'),
      'not_specified' => t('application.gender.unspecified'),
      _ => value,
    };
  }

  return '$value';
}

String _humanizeMembershipRequestFieldKey(String key) {
  if (key.trim().isEmpty) return '';
  return key
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

class _DataLine extends StatelessWidget {
  const _DataLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 86,
          child: Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    ),
  );
}

class _InboxWorkflowPanel extends StatelessWidget {
  const _InboxWorkflowPanel({required this.tab, required this.onReload});

  final String tab;
  final VoidCallback onReload;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final tabLabel = t('membership.inbox.tab.$tab');
    return AirmiusPanel(
      borderColor: AirmiusColors.green.withValues(alpha: .44),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('membership.inbox.workflowTitle')),
          const SizedBox(height: 8),
          Text(
            t('membership.inbox.workflowBody').replaceFirst('{tab}', tabLabel),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: t('membership.inbox.membershipOps'),
                icon: Icons.assignment_ind_outlined,
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => MembershipOperationsScreen(),
                  ),
                ),
              ),
              AirmiusButton(
                label: t('membership.inbox.refresh'),
                icon: Icons.refresh_outlined,
                secondary: true,
                onPressed: onReload,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InboxSwitch extends StatelessWidget {
  const _InboxSwitch({
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
  Widget build(BuildContext context) => Material(
    type: MaterialType.transparency,
    child: SwitchListTile(
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
    ),
  );
}

class _MembershipRequest {
  const _MembershipRequest({
    required this.id,
    required this.clubId,
    required this.apiStatus,
    required this.status,
    required this.name,
    required this.email,
    required this.address,
    required this.type,
    required this.received,
    required this.body,
    required this.payment,
    required this.hasDocuments,
    required this.documents,
    required this.data,
    required this.documentTitles,
    required this.color,
  });

  final int id;
  final int clubId;
  final String apiStatus;
  final String status;
  final String name;
  final String email;
  final String address;
  final String type;
  final String received;
  final String body;
  final String payment;
  final bool hasDocuments;
  final String documents;
  final JsonMap data;
  final List<String> documentTitles;
  final Color color;
}

const _tabs = ['all', 'pending', 'review', 'withdrawn', 'approved', 'declined'];
