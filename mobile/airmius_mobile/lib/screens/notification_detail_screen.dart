import 'carpool_ride_screen.dart';
import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../navigation/airmius_deep_link_navigator.dart';
import '../widgets/airmius_widgets.dart';
import 'friends_social_graph_screen.dart';
import 'team_invitation_response_screen.dart';
import 'member_removal_screen.dart';

bool notificationHasContextTarget(String? url) {
  if (url == null || url.trim().isEmpty) return false;
  final uri = Uri.tryParse(url);
  if (uri == null) return false;
  final path = uri.scheme == 'airmius' ? '/${uri.host}${uri.path}' : uri.path;
  return path.replaceAll(RegExp(r'/+$'), '') != '/notifications';
}

bool isRemovalObjectionNotification(String type) =>
    type == 'club.member_removal_objected' ||
    type == 'club.member_removal_objection';

class NotificationDetailScreen extends StatefulWidget {
  const NotificationDetailScreen({
    super.key,
    required this.notification,
    required this.typeLabel,
    required this.icon,
    required this.onChanged,
  });

  final AirmiusNotification notification;
  final String typeLabel;
  final IconData icon;
  final VoidCallback onChanged;

  @override
  State<NotificationDetailScreen> createState() =>
      _NotificationDetailScreenState();
}

class _NotificationDetailScreenState extends State<NotificationDetailScreen> {
  bool _busy = false;
  late String _serviceHourResponseStatus;

  @override
  void initState() {
    super.initState();
    _serviceHourResponseStatus =
        '${widget.notification.data['member_response_status'] ?? 'pending'}';
    if (widget.notification.unread) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _markRead(silent: true);
      });
    }
  }

  bool get _isServiceHourResponse =>
      widget.notification.type == 'club.service_hour.recorded' &&
      _intFromDynamic(widget.notification.data['club_id']) != null &&
      _intFromDynamic(widget.notification.data['service_hour_record_id']) !=
          null;

  Future<void> _respondToServiceHour(String decision) async {
    if (_busy || !_isServiceHourResponse) return;
    String? reason;
    if (decision == 'accepted') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Dienststunde bestätigen?'),
          content: const Text(
            'Bitte bestätige nur, wenn Tätigkeit, Datum und Dauer stimmen.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Abbrechen'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Bestätigen'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    } else {
      final controller = TextEditingController();
      reason = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Dienststunde beanstanden'),
          content: TextField(
            controller: controller,
            autofocus: true,
            maxLength: 1000,
            maxLines: 4,
            decoration: const InputDecoration(
              labelText: 'Begründung',
              hintText: 'Was stimmt nicht?',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Abbrechen'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text.trim()),
              child: const Text('Beanstandung senden'),
            ),
          ],
        ),
      );
      controller.dispose();
      if (!mounted || reason == null) return;
      if (reason.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Bitte gib eine Begründung ein.')),
        );
        return;
      }
    }

    setState(() => _busy = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services.clientForSession(services.authState.session)
          .respondToClubServiceHourRecord(
            _intFromDynamic(widget.notification.data['club_id'])!,
            _intFromDynamic(
              widget.notification.data['service_hour_record_id'],
            )!,
            decision,
            reason: reason,
          );
      widget.onChanged();
      if (!mounted) return;
      setState(() {
        _busy = false;
        _serviceHourResponseStatus = decision;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            decision == 'accepted'
                ? 'Dienststunde bestätigt.'
                : 'Beanstandung wurde an die Vereinsverwaltung gesendet.',
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error is AirmiusApiException
                ? error.userMessage
                : 'Rückmeldung konnte nicht gespeichert werden.',
          ),
        ),
      );
    }
  }

  Future<void> _markRead({bool silent = false}) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.notifications.markAsRead(widget.notification.id);
      widget.onChanged();
      if (!mounted) return;
      setState(() => _busy = false);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      if (silent) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('notifications.error')),
        ),
      );
    }
  }

  Future<void> _delete() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.notifications.delete(widget.notification.id);
      widget.onChanged();
      if (!mounted) return;
      Navigator.of(context).pop();
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('notifications.error')),
        ),
      );
    }
  }

  void _openContext() {
    final invitationId = _teamInvitationId(widget.notification);
    if (invitationId != null) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => TeamInvitationResponseScreen(
            invitationId: invitationId,
            notification: widget.notification,
          ),
        ),
      );
      return;
    }

    if (_isFriendInvitationNotification(widget.notification)) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) =>
              const FriendsSocialGraphScreen(initialSection: 'received'),
        ),
      );
      return;
    }

    final actionUrl = widget.notification.actionUrl;
    if (actionUrl != null) {
      AirmiusDeepLinkNavigator.open(context, actionUrl);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.notification.type == 'ride.deleted') {
      return const CarpoolCancelledScreen();
    }
    final rideId = int.tryParse('${widget.notification.data['ride_id'] ?? ''}');
    if (widget.notification.type.startsWith('ride.') &&
        rideId != null &&
        rideId > 0) {
      return CarpoolRideScreen(rideId: rideId);
    }
    final removalId = int.tryParse(
      '${widget.notification.data['removal_id'] ?? ''}',
    );
    if (removalId != null ||
        widget.notification.type == 'club.member_removed') {
      return MemberRemovalScreen(
        removalId: removalId,
        notificationId: removalId == null ? widget.notification.id : null,
      );
    }
    final scope = AirmiusScope.of(context);
    final accent = _notificationDetailAccent(context);
    final text = _notificationDetailText(context);
    final muted = _notificationDetailMuted(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: _notificationDetailHeader(context),
        foregroundColor: text,
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('notifications.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.notification.title,
        subtitle: '',
        showHeader: true,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(widget.icon, color: accent, size: 34),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          widget.notification.body,
                          style: TextStyle(color: muted, height: 1.35),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(widget.typeLabel),
                      StatusPill(
                        widget.notification.unread
                            ? scope.t('messages.unread')
                            : scope.t('status.ready'),
                        color: widget.notification.unread
                            ? Theme.of(context).colorScheme.secondary
                            : accent,
                      ),
                      if (_isServiceHourResponse &&
                          _serviceHourResponseStatus != 'pending')
                        StatusPill(
                          _serviceHourResponseStatus == 'accepted'
                              ? 'Bestätigt'
                              : 'Beanstandet',
                          color: _serviceHourResponseStatus == 'accepted'
                              ? accent
                              : Theme.of(context).colorScheme.error,
                        ),
                    ],
                  ),
                  if (_isServiceHourResponse &&
                      _serviceHourResponseStatus == 'pending') ...[
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        AirmiusButton(
                          label: 'Bestätigen',
                          icon: Icons.check,
                          onPressed: _busy
                              ? null
                              : () => _respondToServiceHour('accepted'),
                        ),
                        AirmiusButton(
                          label: 'Beanstanden',
                          icon: Icons.flag_outlined,
                          secondary: true,
                          onPressed: _busy
                              ? null
                              : () => _respondToServiceHour('disputed'),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (!isRemovalObjectionNotification(widget.notification.type) &&
                    notificationHasContextTarget(widget.notification.actionUrl))
                  AirmiusButton(
                    label:
                        widget.notification.type ==
                                'club.service_hour.member_accepted' ||
                            widget.notification.type ==
                                'club.service_hour.member_disputed'
                        ? 'Dienststunde öffnen'
                        : scope.t('notifications.openContext'),
                    icon: Icons.open_in_new_outlined,
                    onPressed: _openContext,
                  ),
                AirmiusButton(
                  label: _busy
                      ? scope.t('status.loading')
                      : scope.t('notifications.markRead'),
                  icon: Icons.mark_email_read_outlined,
                  secondary: true,
                  onPressed: _busy || !widget.notification.unread
                      ? null
                      : _markRead,
                ),
                AirmiusButton(
                  label: scope.t('notifications.delete'),
                  icon: Icons.delete_outline,
                  danger: true,
                  onPressed: _busy ? null : _delete,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

AirmiusThemePalette _notificationDetailPalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark
        ? AirmiusThemePalette.dark
        : AirmiusThemePalette.air;
  }
}

bool _notificationDetailDarkUi(BuildContext context) {
  try {
    final mode = AirmiusThemeModeScope.of(context).mode;
    return switch (mode) {
      ThemeMode.dark => true,
      ThemeMode.light => false,
      ThemeMode.system => Theme.of(context).brightness == Brightness.dark,
    };
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark;
  }
}

Color _notificationDetailAccent(BuildContext context) {
  return _notificationDetailPalette(context).primary;
}

Color _notificationDetailHeader(BuildContext context) {
  final palette = _notificationDetailPalette(context);
  return _notificationDetailDarkUi(context)
      ? palette.darkHeader
      : palette.lightSurface;
}

Color _notificationDetailText(BuildContext context) {
  final palette = _notificationDetailPalette(context);
  return _notificationDetailDarkUi(context)
      ? airmiusTextColor(context)
      : palette.lightText;
}

Color _notificationDetailMuted(BuildContext context) {
  final palette = _notificationDetailPalette(context);
  return _notificationDetailDarkUi(context)
      ? airmiusMutedColor(context)
      : palette.lightMutedText;
}

int? _teamInvitationId(AirmiusNotification notification) {
  final type = notification.type.toLowerCase();
  final explicitId = _intFromDynamic(
    notification.data['invitation_id'] ??
        notification.data['team_invitation_id'],
  );
  final isTeamInvitation =
      type.startsWith('team.') &&
      (type.contains('invite') ||
          type.contains('invitation') ||
          type.contains('trainer'));
  if (explicitId != null && isTeamInvitation) {
    return explicitId;
  }

  final actionUrl = notification.actionUrl;
  if (actionUrl == null || actionUrl.isEmpty) return null;

  final match = RegExp(
    r'(?:team_invitation(?:_id)?|team-invitations)[=/]([0-9]+)',
  ).firstMatch(actionUrl);
  return match == null ? null : int.tryParse(match.group(1) ?? '');
}

bool _isFriendInvitationNotification(AirmiusNotification notification) {
  final type = notification.type.toLowerCase();
  return (type == 'friend.invite' ||
          type == 'friend.request' ||
          type == 'friend.requested') &&
      _intFromDynamic(notification.data['invitation_id']) != null;
}

int? _intFromDynamic(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value');
}
