import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';
import 'membership_application_form_screen.dart';
import 'notification_chat_operations_screen.dart';

class MembershipRequestStatusScreen extends StatefulWidget {
  const MembershipRequestStatusScreen({
    super.key,
    this.clubId,
    this.applicationId,
  });

  final int? clubId;
  final int? applicationId;

  @override
  State<MembershipRequestStatusScreen> createState() =>
      _MembershipRequestStatusScreenState();
}

class _MembershipRequestStatusScreenState
    extends State<MembershipRequestStatusScreen> {
  bool _withdrawing = false;
  bool _withdrawn = false;
  bool _requestLoadStarted = false;
  AirmiusClubMembershipRequest? _request;
  String? _requestLoadError;

  void _finishToHome() {
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  List<_TimelineItem> _timeline(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final club = _request?.clubName?.trim().isNotEmpty == true
        ? _request!.clubName!.trim()
        : t('membership.club');
    return [
      _TimelineItem(
        title: t('membership.status.sent'),
        body: t('membership.status.sentBody').replaceFirst('{club}', club),
        status: t('membership.status.completed'),
        icon: Icons.send_outlined,
        color: airmiusSemanticColor(context, AirmiusColors.green),
      ),
      _TimelineItem(
        title: t('membership.status.review'),
        body: t('membership.status.reviewBody'),
        status: t('membership.status.inProgress'),
        icon: Icons.manage_search_outlined,
        color: airmiusAccentColor(context),
      ),
      _TimelineItem(
        title: t('membership.status.question'),
        body: t('membership.status.questionBody'),
        status: t('membership.status.ready'),
        icon: Icons.forum_outlined,
        color: airmiusSemanticColor(context, AirmiusColors.amber),
      ),
      _TimelineItem(
        title: t('membership.status.decision'),
        body: t('membership.status.decisionBody'),
        status: t('membership.status.pending'),
        icon: Icons.verified_outlined,
        color: airmiusMutedColor(context),
      ),
    ];
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_requestLoadStarted ||
        (widget.clubId == null && widget.applicationId == null)) {
      return;
    }
    _requestLoadStarted = true;
    _loadRequest();
  }

  Future<void> _loadRequest() async {
    try {
      final memberships = AirmiusServicesScope.of(
        context,
      ).repositories.memberships;
      final loadFailedMessage = AirmiusScope.of(
        context,
      ).t('membership.statusLoadFailed');
      var clubId = widget.clubId;
      var applicationId = widget.applicationId;
      if (applicationId != null) {
        final application = await memberships.application(applicationId);
        clubId = application.clubId;
      }
      if (clubId == null || clubId <= 0) {
        throw const FormatException('Missing membership club');
      }
      final requests = await memberships.clubRequests(clubId);
      final matching = applicationId == null
          ? null
          : requests.items.where((item) => item.id == applicationId);
      if (!mounted) return;
      setState(() {
        _request = matching == null
            ? (requests.items.isEmpty ? null : requests.items.first)
            : (matching.isEmpty ? null : matching.first);
        if (applicationId != null && _request == null) {
          _requestLoadError = loadFailedMessage;
        }
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _requestLoadError = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('membership.statusLoadFailed');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return PopScope<void>(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _finishToHome();
      },
      child: Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
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
                          PageTitle(
                            title: AirmiusScope.of(
                              context,
                            ).t('membership.statusTitle'),
                            subtitle: AirmiusScope.of(
                              context,
                            ).t('membership.statusSubtitle'),
                          ),
                          const SizedBox(height: 16),
                          if (_requestLoadError != null)
                            AirmiusPanel(
                              borderColor: airmiusSemanticColor(
                                context,
                                AirmiusColors.red,
                              ).withValues(alpha: .45),
                              child: Text(_requestLoadError!),
                            ),
                          if (_requestLoadError != null)
                            const SizedBox(height: 12),
                          _StatusHero(
                            onWithdraw: _confirmWithdraw,
                            disabled: _withdrawing || _withdrawn,
                            clubName: _request?.clubName,
                            requestStatus: _request?.status,
                          ),
                          const SizedBox(height: 16),
                          _DataPanel(
                            title: t('membership.status.personalData'),
                            rows: _personalRows(context),
                          ),
                          _DataPanel(
                            title: t('membership.status.paymentData'),
                            rows: _paymentRows(context),
                          ),
                          _DataPanel(
                            title: t('membership.status.documents'),
                            rows: _documentRows(context),
                          ),
                          if (_request?.consent != null)
                            _DataPanel(
                              title: t('membership.status.consent'),
                              rows: _consentRows(context),
                            ),
                          const SizedBox(height: 16),
                          AirmiusPanel(
                            title: t('membership.status.timeline'),
                            child: Column(
                              children: [
                                for (final item in _timeline(context))
                                  _TimelineRow(item: item),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),
                          AirmiusPanel(
                            title: t('membership.status.actions'),
                            child: Wrap(
                              spacing: 10,
                              runSpacing: 10,
                              children: [
                                AirmiusButton(
                                  label: t('membership.status.finish'),
                                  icon: Icons.home_outlined,
                                  onPressed: _finishToHome,
                                ),
                                AirmiusButton(
                                  label: t('membership.status.sendQuestion'),
                                  icon: Icons.forum_outlined,
                                  secondary: true,
                                  onPressed: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          NotificationChatOperationsScreen(
                                            initialTab: 'Chat',
                                          ),
                                    ),
                                  ),
                                ),
                                AirmiusButton(
                                  label: t('membership.status.addData'),
                                  icon: Icons.edit_document,
                                  secondary: true,
                                  onPressed: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          MembershipApplicationFormScreen(
                                            clubId: widget.clubId,
                                          ),
                                    ),
                                  ),
                                ),
                                AirmiusButton(
                                  label: t('membership.status.otherClubs'),
                                  icon: Icons.groups_2_outlined,
                                  secondary: true,
                                  onPressed: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => ClubsScreen(
                                        requestedClubIds: const {},
                                        onRequestClub: (_) {},
                                        onWithdrawClub: (_) {},
                                      ),
                                    ),
                                  ),
                                ),
                                AirmiusButton(
                                  label: _withdrawn
                                      ? t('membership.status.withdrawn')
                                      : (_withdrawing
                                            ? t('membership.status.withdrawing')
                                            : t('membership.status.withdraw')),
                                  icon: Icons.undo_outlined,
                                  secondary: true,
                                  onPressed: _withdrawing || _withdrawn
                                      ? null
                                      : _confirmWithdraw,
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
      ),
    );
  }

  List<String> _personalRows(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final data = _request?.applicationData ?? const <String, dynamic>{};
    final rows = <String>[];
    final name = [
      data['first_name'],
      data['last_name'],
    ].whereType<String>().where((value) => value.trim().isNotEmpty).join(' ');
    if (name.isNotEmpty) rows.add('${t('membership.status.name')}: $name');
    if ((data['birth_date'] ?? '').toString().isNotEmpty) {
      rows.add('${t('membership.status.birthDate')}: ${data['birth_date']}');
    }
    if ((data['email'] ?? '').toString().isNotEmpty) {
      rows.add('${t('membership.status.email')}: ${data['email']}');
    }
    final address = [
      data['street'],
      data['house_number'],
      data['postal_code'],
      data['city'],
    ].whereType<String>().where((value) => value.trim().isNotEmpty).join(' ');
    if (address.isNotEmpty) {
      rows.add('${t('membership.status.address')}: $address');
    }
    return rows.isEmpty ? [t('membership.status.noPersonalData')] : rows;
  }

  List<String> _paymentRows(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final request = _request;
    if (request == null) {
      return [t('membership.status.noPayment')];
    }
    return [
      if (request.preferredPaymentMethod?.isNotEmpty == true)
        '${t('membership.status.paymentMethod')}: ${request.preferredPaymentMethod}',
      if (request.requestedBillingInterval?.isNotEmpty == true)
        '${t('membership.status.interval')}: ${request.requestedBillingInterval}',
      if (request.previewAmount?.isNotEmpty == true)
        '${t('membership.status.preview')}: ${request.previewAmount}',
      if (request.previewBaseAmount?.isNotEmpty == true &&
          request.previewBaseAmount != request.previewAmount)
        '${t('membership.status.baseAmount')}: ${request.previewBaseAmount}',
      if (request.previewDiscountAmount?.isNotEmpty == true &&
          request.previewDiscountAmount != '0.00')
        '${t('membership.status.discountAmount')}: -${request.previewDiscountAmount}',
      if (request.preferredPaymentMethod?.isEmpty != false &&
          request.requestedBillingInterval?.isEmpty != false)
        t('membership.status.noPayment'),
    ];
  }

  List<String> _documentRows(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final documents = _request?.acceptedDocuments ?? const <String>[];
    if (documents.isEmpty) return [t('membership.status.noDocuments')];
    return documents
        .map(
          (document) => t(
            'membership.status.acceptedDocument',
          ).replaceFirst('{name}', document),
        )
        .toList();
  }

  List<String> _consentRows(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final consent = _request?.consent;
    if (consent == null) return [t('membership.status.noConsent')];
    final method = consent.method == 'typed_signature'
        ? t('membership.status.typedSignature')
        : t('membership.status.checkboxConfirmation');
    return [
      '${t('membership.status.consentVersion')}: ${consent.version}',
      '${t('membership.status.consentMethod')}: $method',
      if (consent.signature?.trim().isNotEmpty == true)
        '${t('membership.status.signature')}: ${consent.signature}',
      if (consent.signedAt != null)
        '${t('membership.status.signedAt')}: ${consent.signedAt!.toLocal()}',
    ];
  }

  Future<void> _confirmWithdraw() async {
    final t = AirmiusScope.of(context).t;
    final services = AirmiusServicesScope.of(context);
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(22),
          side: BorderSide(color: airmiusBorderColor(context)),
        ),
        title: Text(
          t('membership.status.withdrawTitle'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        content: Text(
          t('membership.status.withdrawConfirm'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w700,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('membership.status.cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: airmiusSemanticColor(context, AirmiusColors.red),
              foregroundColor: airmiusOnColor(
                airmiusSemanticColor(context, AirmiusColors.red),
              ),
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('membership.status.withdraw')),
          ),
        ],
      ),
    );
    if (confirm != true || widget.clubId == null) {
      if (confirm == true) {
        _toast(t('membership.withdrawUnavailable'));
      }
      return;
    }
    setState(() => _withdrawing = true);
    try {
      await services.repositories.memberships.withdrawClubRequest(
        widget.clubId!,
      );
      if (!mounted) return;
      setState(() {
        _withdrawing = false;
        _withdrawn = true;
      });
      _toast(t('membership.withdrawn'));
    } catch (error) {
      if (!mounted) return;
      setState(() => _withdrawing = false);
      final message = error is AirmiusApiException
          ? error.userMessage
          : t('membership.withdrawFailed');
      _toast(message);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _StatusHero extends StatelessWidget {
  const _StatusHero({
    required this.onWithdraw,
    this.disabled = false,
    this.clubName,
    this.requestStatus,
  });

  final VoidCallback onWithdraw;
  final bool disabled;
  final String? clubName;
  final String? requestStatus;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final resolvedClub = clubName?.trim().isNotEmpty == true
        ? clubName!.trim()
        : t('membership.club');
    final resolvedStatus = requestStatus?.trim().isNotEmpty == true
        ? requestStatus!.trim()
        : t('membership.status.sent');
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            airmiusSurfaceSoftColor(context),
            airmiusSurfaceColor(context),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Theme.of(context).colorScheme.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final identity = Row(
                children: [
                  AirmiusAvatar((clubName ?? 'Verein').trim(), large: true),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow(t('membership.status.statusEyebrow')),
                        const SizedBox(height: 4),
                        Text(
                          resolvedClub,
                          softWrap: true,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 24,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          requestStatus == null
                              ? t('membership.status.review')
                              : '${t('membership.status')}: $resolvedStatus',
                          softWrap: true,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              );
              final status = StatusPill(
                resolvedStatus,
                color: airmiusSemanticColor(context, AirmiusColors.green),
              );
              if (constraints.maxWidth < 560) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    identity,
                    const SizedBox(height: 10),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: status,
                    ),
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: identity),
                  const SizedBox(width: 12),
                  status,
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          Text(
            t('membership.status.heroBody'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: disabled
                    ? t('membership.status.withdrawn')
                    : t('membership.status.withdraw'),
                icon: Icons.undo_outlined,
                secondary: true,
                onPressed: disabled ? null : onWithdraw,
              ),
              AirmiusButton(
                label: t('membership.status.view'),
                icon: Icons.timeline_outlined,
                secondary: true,
                onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      AirmiusScope.of(context).t('membership.statusLoaded'),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DataPanel extends StatelessWidget {
  const _DataPanel({required this.title, required this.rows});

  final String title;
  final List<String> rows;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        title: title,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (final row in rows)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  children: [
                    Icon(
                      Icons.check_circle_outline,
                      size: 17,
                      color: airmiusSemanticColor(context, AirmiusColors.green),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        row,
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
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

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.item});

  final _TimelineItem item;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final title = Text(
            item.title,
            softWrap: true,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          );
          final body = Text(
            item.body,
            softWrap: true,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.35,
              fontWeight: FontWeight.w700,
            ),
          );
          final copy = Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [title, const SizedBox(height: 4), body],
          );
          final status = StatusPill(item.status, color: item.color);
          if (constraints.maxWidth < 560) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(item.icon, color: item.color),
                    const SizedBox(width: 12),
                    Expanded(child: title),
                  ],
                ),
                const SizedBox(height: 6),
                body,
                const SizedBox(height: 8),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: status,
                ),
              ],
            );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.color),
              const SizedBox(width: 12),
              Expanded(child: copy),
              const SizedBox(width: 10),
              status,
            ],
          );
        },
      ),
    );
  }
}

class _TimelineItem {
  const _TimelineItem({
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
