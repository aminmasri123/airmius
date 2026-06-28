import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../navigation/airmius_deep_link_navigator.dart';
import '../widgets/airmius_widgets.dart';
import 'notification_preferences_screen.dart';
import 'team_invitation_response_screen.dart';

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
  State<NotificationDetailScreen> createState() => _NotificationDetailScreenState();
}

class _NotificationDetailScreenState extends State<NotificationDetailScreen> {
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.notification.unread) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _markRead(silent: true);
      });
    }
  }

  Future<void> _markRead({bool silent = false}) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(context).repositories.notifications.markAsRead(widget.notification.id);
      widget.onChanged();
      if (!mounted) return;
      setState(() => _busy = false);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      if (silent) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('notifications.error'))));
    }
  }

  Future<void> _delete() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(context).repositories.notifications.delete(widget.notification.id);
      widget.onChanged();
      if (!mounted) return;
      Navigator.of(context).pop();
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('notifications.error'))));
    }
  }

  void _openContext() {
    final invitationId = _teamInvitationId(widget.notification);
    if (invitationId != null) {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => TeamInvitationResponseScreen(invitationId: invitationId, notification: widget.notification)),
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
        title: Text(scope.t('notifications.title'), style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.notification.title,
        subtitle: widget.notification.body,
        trailing: StatusPill(widget.typeLabel),
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
                      Expanded(child: Text(widget.notification.body, style: TextStyle(color: muted, height: 1.35))),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(widget.typeLabel),
                      StatusPill(widget.notification.unread ? scope.t('messages.unread') : scope.t('status.ready'), color: widget.notification.unread ? AirmiusColors.green : accent),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (widget.notification.actionUrl != null)
                  AirmiusButton(
                    label: scope.t('notifications.openContext'),
                    icon: Icons.open_in_new_outlined,
                    onPressed: _openContext,
                  ),
                AirmiusButton(
                  label: _busy ? scope.t('status.loading') : scope.t('notifications.markRead'),
                  icon: Icons.mark_email_read_outlined,
                  secondary: true,
                  onPressed: _busy || !widget.notification.unread ? null : _markRead,
                ),
                AirmiusButton(
                  label: 'Push',
                  icon: Icons.tune_outlined,
                  secondary: true,
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationPreferencesScreen())),
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
    return Theme.of(context).brightness == Brightness.dark ? AirmiusThemePalette.dark : AirmiusThemePalette.air;
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
  return _notificationDetailDarkUi(context) ? palette.darkHeader : palette.lightSurface;
}

Color _notificationDetailText(BuildContext context) {
  final palette = _notificationDetailPalette(context);
  return _notificationDetailDarkUi(context) ? AirmiusColors.text : palette.lightText;
}

Color _notificationDetailMuted(BuildContext context) {
  final palette = _notificationDetailPalette(context);
  return _notificationDetailDarkUi(context) ? AirmiusColors.muted : palette.lightMutedText;
}

int? _teamInvitationId(AirmiusNotification notification) {
  final type = notification.type.toLowerCase();
  final explicitId = _intFromDynamic(notification.data['invitation_id'] ?? notification.data['team_invitation_id']);
  if (explicitId != null && (type.contains('team.invite') || type.contains('team.invitation') || type.contains('trainer') || type.contains('invite') || type.contains('invitation'))) {
    return explicitId;
  }

  final actionUrl = notification.actionUrl;
  if (actionUrl == null || actionUrl.isEmpty) return null;

  final match = RegExp(r'(?:team_invitation|invitation_id)=([0-9]+)').firstMatch(actionUrl);
  return match == null ? null : int.tryParse(match.group(1) ?? '');
}

int? _intFromDynamic(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value');
}
