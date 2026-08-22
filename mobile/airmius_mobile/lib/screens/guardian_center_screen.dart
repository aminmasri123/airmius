import 'dart:async';

import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'guardian_child_overview_screen.dart';

class GuardianCenterScreen extends StatefulWidget {
  const GuardianCenterScreen({super.key});

  @override
  State<GuardianCenterScreen> createState() => _GuardianCenterScreenState();
}

class _GuardianCenterScreenState extends State<GuardianCenterScreen> {
  AirmiusApiClient? _client;
  Future<AirmiusGuardianWorkspace>? _future;
  final Set<int> _busyChildren = {};
  String _filter = 'all';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_client != null) return;
    final services = AirmiusServicesScope.of(context);
    _client = services.clientForSession(services.authState.session);
    _reload();
  }

  void _reload() {
    setState(() {
      _future = _client!.guardianChildren().then(
        AirmiusGuardianWorkspace.fromJson,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          scope.t('guardian.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: scope.t('guardian.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: FutureBuilder<AirmiusGuardianWorkspace>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const _GuardianLoading();
          }
          if (snapshot.hasError) {
            return _GuardianError(error: snapshot.error, onRetry: _reload);
          }
          final workspace = snapshot.data;
          if (workspace == null) {
            return _GuardianError(onRetry: _reload);
          }
          return _GuardianContent(
            workspace: workspace,
            filter: _filter,
            busyChildren: _busyChildren,
            onRefresh: () async {
              _reload();
              await _future;
            },
            onFilterChanged: (value) => setState(() => _filter = value),
            onApprove: _approve,
            onRevoke: _revoke,
            onResend: _resend,
            onOpenChild: (child) => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => GuardianChildOverviewScreen(childId: child.id),
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _approve(AirmiusGuardianChild child) async {
    final scope = AirmiusScope.of(context);
    final confirmed = await _confirm(
      title: scope.t('guardian.approveTitle'),
      message: scope
          .t('guardian.approveQuestion')
          .replaceFirst('{name}', child.name),
      confirmLabel: scope.t('guardian.approve'),
      icon: Icons.verified_user_outlined,
    );
    if (!confirmed) return;
    await _runChildAction(
      child,
      () => _client!.approveGuardianChild(child.id),
      scope.t('guardian.approvedSuccess'),
    );
  }

  Future<void> _revoke(AirmiusGuardianChild child) async {
    final scope = AirmiusScope.of(context);
    final confirmed = await _confirm(
      title: scope.t('guardian.revokeTitle'),
      message: scope
          .t('guardian.revokeQuestion')
          .replaceFirst('{name}', child.name),
      confirmLabel: scope.t('guardian.revoke'),
      icon: Icons.gpp_bad_outlined,
      destructive: true,
    );
    if (!confirmed) return;
    await _runChildAction(
      child,
      () => _client!.revokeGuardianChild(child.id),
      scope.t('guardian.revokedSuccess'),
    );
  }

  Future<void> _resend(AirmiusGuardianChild child) async {
    await _runChildAction(
      child,
      () => _client!.resendGuardianChildConsent(child.id),
      AirmiusScope.of(context).t('guardian.resentSuccess'),
    );
  }

  Future<void> _runChildAction(
    AirmiusGuardianChild child,
    Future<AirmiusJson> Function() action,
    String successMessage,
  ) async {
    if (_busyChildren.contains(child.id)) return;
    setState(() => _busyChildren.add(child.id));
    try {
      final response = await action();
      final data = response['data'];
      if (data is JsonMap) {
        final updatedChild = AirmiusGuardianChild.fromJson(data);
        final workspace = await _future;
        if (workspace != null) {
          final children = workspace.children
              .map(
                (current) =>
                    current.id == updatedChild.id ? updatedChild : current,
              )
              .toList();
          if (!children.any((current) => current.id == updatedChild.id)) {
            children.add(updatedChild);
          }
          _future = Future.value(workspace.copyWith(children: children));
        }
      }
      if (!mounted) return;
      setState(() {});
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException
          ? error.userMessage
          : AirmiusScope.of(context).t('common.errorDetails');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${AirmiusScope.of(context).t('guardian.actionError')} $message',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busyChildren.remove(child.id));
    }
  }

  Future<bool> _confirm({
    required String title,
    required String message,
    required String confirmLabel,
    required IconData icon,
    bool destructive = false,
  }) async {
    final scope = AirmiusScope.of(context);
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: Icon(
              icon,
              color: destructive
                  ? Theme.of(context).colorScheme.error
                  : Theme.of(context).colorScheme.primary,
              size: 34,
            ),
            title: Text(title),
            content: Text(message),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(scope.t('guardian.cancel')),
              ),
              FilledButton(
                style: destructive
                    ? FilledButton.styleFrom(
                        backgroundColor: Theme.of(context).colorScheme.error,
                      )
                    : null,
                onPressed: () => Navigator.pop(context, true),
                child: Text(confirmLabel),
              ),
            ],
          ),
        ) ??
        false;
  }
}

class _GuardianContent extends StatelessWidget {
  const _GuardianContent({
    required this.workspace,
    required this.filter,
    required this.busyChildren,
    required this.onRefresh,
    required this.onFilterChanged,
    required this.onApprove,
    required this.onRevoke,
    required this.onResend,
    required this.onOpenChild,
  });

  final AirmiusGuardianWorkspace workspace;
  final String filter;
  final Set<int> busyChildren;
  final Future<void> Function() onRefresh;
  final ValueChanged<String> onFilterChanged;
  final ValueChanged<AirmiusGuardianChild> onApprove;
  final ValueChanged<AirmiusGuardianChild> onRevoke;
  final ValueChanged<AirmiusGuardianChild> onResend;
  final ValueChanged<AirmiusGuardianChild> onOpenChild;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final visibleChildren = workspace.children.where((child) {
      if (filter == 'approved') return child.isApproved;
      if (filter == 'open') return !child.isApproved;
      return true;
    }).toList();

    return PageFrame(
      title: scope.t('guardian.title'),
      subtitle: scope.t('guardian.subtitle'),
      onRefresh: onRefresh,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _GuardianHero(workspace: workspace),
          const SizedBox(height: 14),
          _GuardianMetrics(workspace: workspace),
          const SizedBox(height: 16),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  scope.t('guardian.childrenTitle'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  scope.t('guardian.childrenHint'),
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 12),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: SegmentedButton<String>(
                    segments: [
                      ButtonSegment(
                        value: 'all',
                        label: Text(scope.t('guardian.filterAll')),
                        icon: const Icon(Icons.family_restroom_outlined),
                      ),
                      ButtonSegment(
                        value: 'open',
                        label: Text(scope.t('guardian.filterOpen')),
                        icon: const Icon(Icons.pending_actions_outlined),
                      ),
                      ButtonSegment(
                        value: 'approved',
                        label: Text(scope.t('guardian.filterApproved')),
                        icon: const Icon(Icons.verified_outlined),
                      ),
                    ],
                    selected: {filter},
                    showSelectedIcon: false,
                    onSelectionChanged: (selection) =>
                        onFilterChanged(selection.first),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          if (visibleChildren.isEmpty)
            _GuardianEmpty(hasChildren: workspace.children.isNotEmpty)
          else
            for (final child in visibleChildren) ...[
              _GuardianChildCard(
                child: child,
                canManage: workspace.canManage,
                busy: busyChildren.contains(child.id),
                onApprove: () => onApprove(child),
                onRevoke: () => onRevoke(child),
                onResend: () => onResend(child),
                onOpen: () => onOpenChild(child),
              ),
              const SizedBox(height: 12),
            ],
          const SizedBox(height: 2),
          const _GuardianSafetyPanel(),
        ],
      ),
    );
  }
}

class _GuardianHero extends StatelessWidget {
  const _GuardianHero({required this.workspace});

  final AirmiusGuardianWorkspace workspace;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final color = Theme.of(context).colorScheme.primary;
    return AirmiusPanel(
      gradient: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Icon(Icons.family_restroom_rounded, color: color, size: 32),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  scope.t('guardian.protectionEyebrow').toUpperCase(),
                  style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.7,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  workspace.guardianName.isEmpty
                      ? scope.t('guardian.heroTitle')
                      : scope
                            .t('guardian.heroGreeting')
                            .replaceFirst('{name}', workspace.guardianName),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  scope.t('guardian.heroBody'),
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GuardianMetrics extends StatelessWidget {
  const _GuardianMetrics({required this.workspace});

  final AirmiusGuardianWorkspace workspace;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth >= 620
            ? (constraints.maxWidth - 20) / 3
            : constraints.maxWidth;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            SizedBox(
              width: width,
              child: MetricCard(
                value: '${workspace.children.length}',
                label: scope.t('guardian.metricChildren'),
              ),
            ),
            SizedBox(
              width: width,
              child: MetricCard(
                value: '${workspace.approvedCount}',
                label: scope.t('guardian.metricApproved'),
              ),
            ),
            SizedBox(
              width: width,
              child: MetricCard(
                value: '${workspace.openCount}',
                label: scope.t('guardian.metricOpen'),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _GuardianChildCard extends StatelessWidget {
  const _GuardianChildCard({
    required this.child,
    required this.canManage,
    required this.busy,
    required this.onApprove,
    required this.onRevoke,
    required this.onResend,
    required this.onOpen,
  });

  final AirmiusGuardianChild child;
  final bool canManage;
  final bool busy;
  final VoidCallback onApprove;
  final VoidCallback onRevoke;
  final VoidCallback onResend;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final status = _guardianStatus(context, child.status);
    return AirmiusPanel(
      borderColor: status.color.withValues(alpha: 0.45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 25,
                backgroundColor: status.color.withValues(alpha: 0.14),
                child: Icon(status.icon, color: status.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      child.name,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${scope.t('guardian.age').replaceFirst('{age}', '${child.age}')} · ${child.email}',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusPill(status.label, color: status.color),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _SafetyChip(
                icon: Icons.visibility_off_outlined,
                label: scope.t('guardian.privateProfile'),
              ),
              _SafetyChip(
                icon: Icons.forum_outlined,
                label: child.directMessagesEnabled
                    ? scope.t('guardian.friendMessages')
                    : scope.t('guardian.messagesLocked'),
              ),
              _SafetyChip(
                icon: Icons.person_add_disabled_outlined,
                label: scope.t('guardian.friendRequestsProtected'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _GuardianStatusDetails(child: child),
          const SizedBox(height: 10),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              onPressed: onOpen,
              icon: const Icon(Icons.insights_outlined),
              label: Text(scope.t('guardian.openChildOverview')),
            ),
          ),
          if (canManage) ...[
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (!child.isApproved)
                  AirmiusButton(
                    label: scope.t('guardian.approve'),
                    icon: Icons.verified_user_outlined,
                    onPressed: busy ? null : onApprove,
                  ),
                if (!child.isApproved)
                  _GuardianResendButton(
                    initialSeconds: child.resendAvailableIn,
                    busy: busy,
                    onPressed: onResend,
                  ),
                if (child.isApproved)
                  AirmiusButton(
                    label: scope.t('guardian.revoke'),
                    icon: Icons.gpp_bad_outlined,
                    danger: true,
                    onPressed: busy ? null : onRevoke,
                  ),
              ],
            ),
          ],
          if (busy) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(minHeight: 3),
          ],
        ],
      ),
    );
  }
}

class _GuardianStatusDetails extends StatelessWidget {
  const _GuardianStatusDetails({required this.child});

  final AirmiusGuardianChild child;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final date = child.approvedAt ?? child.revokedAt ?? child.rejectedAt;
    final version = child.consentVersion;
    if (date == null && child.requestedAt == null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            scope.t('guardian.noDecisionYet'),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          if (version != null && version.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              scope
                  .t('guardian.consentVersion')
                  .replaceFirst('{version}', version),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      );
    }
    final label = child.isApproved
        ? scope.t('guardian.approvedAt')
        : child.status == 'revoked'
        ? scope.t('guardian.revokedAt')
        : child.status == 'rejected'
        ? scope.t('guardian.rejectedAt')
        : scope.t('guardian.requestedAt');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              Icons.schedule_outlined,
              size: 18,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            const SizedBox(width: 7),
            Expanded(
              child: Text(
                '$label ${_guardianDate(date ?? child.requestedAt!, scope.language)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          ],
        ),
        if (version != null && version.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            scope
                .t('guardian.consentVersion')
                .replaceFirst('{version}', version),
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ],
    );
  }
}

class _GuardianResendButton extends StatefulWidget {
  const _GuardianResendButton({
    required this.initialSeconds,
    required this.busy,
    required this.onPressed,
  });

  final int initialSeconds;
  final bool busy;
  final VoidCallback onPressed;

  @override
  State<_GuardianResendButton> createState() => _GuardianResendButtonState();
}

class _GuardianResendButtonState extends State<_GuardianResendButton> {
  Timer? _timer;
  late int _seconds;

  @override
  void initState() {
    super.initState();
    _seconds = widget.initialSeconds;
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant _GuardianResendButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialSeconds == widget.initialSeconds) return;
    _seconds = widget.initialSeconds;
    _startTimer();
  }

  void _startTimer() {
    _timer?.cancel();
    if (_seconds <= 0) return;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      if (_seconds <= 1) {
        timer.cancel();
        setState(() => _seconds = 0);
      } else {
        setState(() => _seconds--);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusButton(
      label: _seconds > 0
          ? scope.t('guardian.resendIn').replaceFirst('{seconds}', '$_seconds')
          : scope.t('guardian.resend'),
      icon: Icons.mark_email_unread_outlined,
      secondary: true,
      onPressed: widget.busy || _seconds > 0 ? null : widget.onPressed,
    );
  }
}

class _SafetyChip extends StatelessWidget {
  const _SafetyChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.primary;
    return Container(
      constraints: BoxConstraints(
        maxWidth: MediaQuery.sizeOf(context).width - 56,
      ),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.24)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 17, color: color),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              softWrap: true,
              style: Theme.of(
                context,
              ).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}

class _GuardianSafetyPanel extends StatelessWidget {
  const _GuardianSafetyPanel();

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.shield_outlined,
            size: 28,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  scope.t('guardian.safetyTitle'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  scope.t('guardian.safetyBody'),
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GuardianEmpty extends StatelessWidget {
  const _GuardianEmpty({required this.hasChildren});

  final bool hasChildren;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Column(
          children: [
            Icon(
              hasChildren
                  ? Icons.filter_alt_off_outlined
                  : Icons.family_restroom_outlined,
              size: 42,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 10),
            Text(
              scope.t(
                hasChildren ? 'guardian.emptyFilter' : 'guardian.emptyChildren',
              ),
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ),
    );
  }
}

class _GuardianLoading extends StatelessWidget {
  const _GuardianLoading();

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: 14),
            Text(scope.t('guardian.loading')),
          ],
        ),
      ),
    );
  }
}

class _GuardianError extends StatelessWidget {
  const _GuardianError({this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final forbidden =
        error is AirmiusApiException &&
        (error as AirmiusApiException).statusCode == 403;
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: AirmiusPanel(
            child: Column(
              children: [
                Icon(
                  forbidden
                      ? Icons.lock_person_outlined
                      : Icons.cloud_off_outlined,
                  size: 48,
                  color: Theme.of(context).colorScheme.error,
                ),
                const SizedBox(height: 12),
                Text(
                  scope.t(
                    forbidden
                        ? 'guardian.forbiddenTitle'
                        : 'guardian.loadError',
                  ),
                  textAlign: TextAlign.center,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 8),
                Text(
                  scope.t(
                    forbidden
                        ? 'guardian.forbiddenBody'
                        : 'guardian.loadErrorBody',
                  ),
                  textAlign: TextAlign.center,
                ),
                if (!forbidden) ...[
                  const SizedBox(height: 16),
                  AirmiusButton(
                    label: scope.t('guardian.retry'),
                    icon: Icons.refresh_outlined,
                    onPressed: onRetry,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

({String label, Color color, IconData icon}) _guardianStatus(
  BuildContext context,
  String status,
) {
  final scope = AirmiusScope.of(context);
  final scheme = Theme.of(context).colorScheme;
  return switch (status) {
    'approved' => (
      label: scope.t('guardian.statusApproved'),
      color: scheme.secondary,
      icon: Icons.verified_user_outlined,
    ),
    'revoked' => (
      label: scope.t('guardian.statusRevoked'),
      color: scheme.error,
      icon: Icons.gpp_bad_outlined,
    ),
    'rejected' => (
      label: scope.t('guardian.statusRejected'),
      color: scheme.error,
      icon: Icons.block_outlined,
    ),
    _ => (
      label: scope.t('guardian.statusPending'),
      color: scheme.tertiary,
      icon: Icons.pending_actions_outlined,
    ),
  };
}

String _guardianDate(DateTime date, AirmiusLanguage language) {
  final local = date.toLocal();
  final day = local.day.toString().padLeft(2, '0');
  final month = local.month.toString().padLeft(2, '0');
  if (language == AirmiusLanguage.en) {
    return '${local.year}-$month-$day';
  }
  return '$day.$month.${local.year}';
}
