import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

String _inboxTabKey(String value) {
  return switch (value.trim().toLowerCase()) {
    'alle' || 'all' => 'all',
    'prüfung' || 'review' || 'in review' => 'review',
    'rückzug' || 'withdrawn' || 'withdrawal' => 'withdrawn',
    'angenommen' || 'approved' || 'approve' => 'approved',
    'abgelehnt' || 'declined' || 'decline' => 'declined',
    'warteliste' || 'waitlisted' || 'waitlist' => 'waitlisted',
    'rückfrage' || 'information_requested' => 'information_requested',
    _ => 'pending',
  };
}

String _requestTypeLabel(String value, String Function(String) t) =>
    value.trim().toLowerCase() == 'membership' ? t('clubs.membership') : value;

String _requestPaymentLabel(String value, String Function(String) t) =>
    switch (value.trim().toLowerCase()) {
      'cash' => t('membership.payment.cash'),
      'bank_transfer' ||
      'bank transfer' => t('membership.payment.bankTransfer'),
      'sepa_debit' || 'sepa' => t('membership.payment.sepaDebit'),
      'manual' => t('membership.payment.manual'),
      _ => value,
    };

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
      received: request.createdAt.toLocal(),
      body: request.message ?? t('membership.inbox.messageFallback'),
      informationRequest: request.informationRequestMessage,
      applicantResponse: request.applicantResponseMessage,
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
        'waitlisted' => t('membership.inbox.status.waitlisted'),
        'information_requested' => t(
          'membership.inbox.status.informationRequested',
        ),
        _ => t('membership.inbox.status.pending'),
      };

  String _tabLabel(String tab, String Function(String) t) =>
      t('membership.inbox.tab.$tab');

  Color _statusColor(String status) => switch (status) {
    'approved' => AirmiusColors.green,
    'declined' || 'withdrawn' => AirmiusColors.red,
    'review' => airmiusAccentColor(context),
    'waitlisted' => airmiusAccentColor(context),
    'information_requested' => AirmiusColors.amber,
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

  Future<void> _requestInformation(_MembershipRequest request) async {
    final t = AirmiusScope.of(context).t;
    final message = await showDialog<String?>(
      context: context,
      builder: (_) => _DecisionNoteDialog(
        title: t('membership.inbox.requestInformation'),
        noteLabel: t('membership.inbox.informationPrompt'),
        cancelLabel: t('membership.inbox.cancel'),
        confirmLabel: t('membership.inbox.send'),
      ),
    );
    if (message == null || message.trim().isEmpty) return;
    await _followUp(
      request,
      () => AirmiusServicesScope.of(context).repositories.memberships
          .requestClubRequestInformation(
            request.clubId,
            request.id,
            message: message,
          ),
    );
  }

  Future<void> _waitlist(_MembershipRequest request) async {
    final t = AirmiusScope.of(context).t;
    final note = await showDialog<String?>(
      context: context,
      builder: (_) => _DecisionNoteDialog(
        title: t('membership.inbox.waitlist'),
        noteLabel: t('membership.inbox.noteOptional'),
        cancelLabel: t('membership.inbox.cancel'),
        confirmLabel: t('membership.inbox.waitlist'),
      ),
    );
    if (note == null) return;
    await _followUp(
      request,
      () => AirmiusServicesScope.of(context).repositories.memberships
          .waitlistClubRequest(request.clubId, request.id, reviewNote: note),
    );
  }

  Future<void> _followUp(
    _MembershipRequest request,
    Future<AirmiusClubMembershipRequest> Function() action,
  ) async {
    if (_decidingRequestIds.contains(request.id)) return;
    setState(() => _decidingRequestIds.add(request.id));
    try {
      final updated = await action();
      if (!mounted) return;
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
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error is AirmiusApiException
                ? error.userMessage
                : AirmiusScope.of(context).t('membership.inboxActionFailed'),
          ),
        ),
      );
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
        actions: [
          IconButton(
            tooltip: t('membership.inbox.refresh'),
            icon: const Icon(Icons.refresh_outlined),
            onPressed: _loadRequests,
          ),
        ],
      ),
      body: PageFrame(
        title: t('membership.inbox.title'),
        subtitle: t('membership.inbox.subtitle'),
        showHeader: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              child: DropdownButtonFormField<String>(
                key: ValueKey(_tab),
                initialValue: _tab,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: t('membership.inbox.filter'),
                ),
                items: [
                  for (final tab in _tabs)
                    DropdownMenuItem(
                      value: tab,
                      child: Text(_tabLabel(tab, t)),
                    ),
                ],
                onChanged: (value) {
                  if (value != null) setState(() => _tab = value);
                },
              ),
            ),
            const SizedBox(height: 12),
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
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(t('membership.inbox.empty')),
                    if (_tab != 'all') ...[
                      const SizedBox(height: 10),
                      TextButton.icon(
                        onPressed: () => setState(() => _tab = 'all'),
                        icon: const Icon(Icons.list_alt_outlined),
                        label: Text(t('membership.inbox.showAll')),
                      ),
                    ],
                  ],
                ),
              )
            else
              for (final request in requests) ...[
                _RequestCard(
                  request: request,
                  busy: _decidingRequestIds.contains(request.id),
                  onApprove: () => _decide(request, true),
                  onDecline: () => _decide(request, false),
                  onRequestInformation: () => _requestInformation(request),
                  onWaitlist: () => _waitlist(request),
                ),
                const SizedBox(height: 12),
              ],
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

class _RequestCard extends StatelessWidget {
  const _RequestCard({
    required this.request,
    required this.busy,
    required this.onApprove,
    required this.onDecline,
    required this.onRequestInformation,
    required this.onWaitlist,
  });

  final _MembershipRequest request;
  final bool busy;
  final VoidCallback onApprove;
  final VoidCallback onDecline;
  final VoidCallback onRequestInformation;
  final VoidCallback onWaitlist;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final material = MaterialLocalizations.of(context);
    final received =
        '${material.formatMediumDate(request.received)} · ${material.formatTimeOfDay(TimeOfDay.fromDateTime(request.received))}';
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
                      received,
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        StatusPill(request.status, color: request.color),
                        StatusPill(_requestPaymentLabel(request.payment, t)),
                        if (request.hasDocuments)
                          StatusPill(request.documents, color: request.color),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (request.apiStatus == 'pending') const SizedBox(height: 12),
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
              if (request.apiStatus == 'information_requested')
                AirmiusButton(
                  label: t('membership.inbox.decline'),
                  icon: Icons.cancel_outlined,
                  danger: true,
                  onPressed: busy ? null : onDecline,
                ),
              if (request.apiStatus == 'pending' ||
                  request.apiStatus == 'waitlisted') ...[
                AirmiusButton(
                  label: t('membership.inbox.accept'),
                  icon: Icons.check_circle_outline,
                  secondary: true,
                  onPressed: busy ? null : onApprove,
                ),
                AirmiusButton(
                  label: t('membership.inbox.decline'),
                  icon: Icons.cancel_outlined,
                  danger: true,
                  onPressed: busy ? null : onDecline,
                ),
                AirmiusButton(
                  label: t('membership.inbox.requestInformation'),
                  icon: Icons.mark_email_unread_outlined,
                  secondary: true,
                  onPressed: busy ? null : onRequestInformation,
                ),
                if (request.apiStatus == 'pending')
                  AirmiusButton(
                    label: t('membership.inbox.waitlist'),
                    icon: Icons.hourglass_top_outlined,
                    secondary: true,
                    onPressed: busy ? null : onWaitlist,
                  ),
              ],
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
          _DataLine(
            label: t('membership.inbox.type'),
            value: _requestTypeLabel(request.type, t),
          ),
          _DataLine(
            label: t('membership.inbox.received'),
            value:
                '${MaterialLocalizations.of(context).formatMediumDate(request.received)} · ${MaterialLocalizations.of(context).formatTimeOfDay(TimeOfDay.fromDateTime(request.received))}',
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
          if (request.informationRequest?.isNotEmpty == true) ...[
            const SizedBox(height: 8),
            Text(
              '${t('membership.inbox.requestInformation')}: ${request.informationRequest}',
            ),
          ],
          if (request.applicantResponse?.isNotEmpty == true) ...[
            const SizedBox(height: 8),
            Text(
              '${t('membership.inbox.applicantResponse')}: ${request.applicantResponse}',
            ),
          ],
          const SizedBox(height: 12),
          _RequestDataGrid(request: request),
          _DataLine(
            label: t('application.paymentMethod'),
            value: _requestPaymentLabel(request.payment, t),
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
    this.informationRequest,
    this.applicantResponse,
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
  final DateTime received;
  final String body;
  final String? informationRequest;
  final String? applicantResponse;
  final String payment;
  final bool hasDocuments;
  final String documents;
  final JsonMap data;
  final List<String> documentTitles;
  final Color color;
}

const _tabs = [
  'all',
  'pending',
  'information_requested',
  'waitlisted',
  'review',
  'withdrawn',
  'approved',
  'declined',
];
