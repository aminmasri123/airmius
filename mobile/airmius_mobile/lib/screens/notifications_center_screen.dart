import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../navigation/airmius_deep_link_navigator.dart';
import '../widgets/airmius_widgets.dart';
import 'file_manager_screen.dart';
import 'notification_detail_screen.dart';
import 'notification_preferences_screen.dart';
import 'friends_social_graph_screen.dart';
import 'team_invitation_response_screen.dart';

class NotificationsCenterScreen extends StatefulWidget {
  const NotificationsCenterScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<NotificationsCenterScreen> createState() =>
      _NotificationsCenterScreenState();
}

class _NotificationsCenterScreenState extends State<NotificationsCenterScreen> {
  bool _notificationsLoaded = false;
  AirmiusPage<AirmiusNotification>? _lastNotificationsPage;
  final Set<int> _locallyRead = <int>{};
  final Set<int> _locallyUnread = <int>{};
  bool _markingAllRead = false;
  late Future<AirmiusPage<AirmiusNotification>> _notificationsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_notificationsLoaded) return;
    _notificationsLoaded = true;
    _notificationsFuture = _loadNotifications();
  }

  Future<AirmiusPage<AirmiusNotification>> _loadNotifications() async {
    final page = await AirmiusServicesScope.of(
      context,
    ).repositories.notifications.notifications();
    final adjustedPage = _applyLocalReadState(page);
    _lastNotificationsPage = adjustedPage;
    return adjustedPage;
  }

  AirmiusPage<AirmiusNotification> _applyLocalReadState(
    AirmiusPage<AirmiusNotification> page,
  ) {
    final items = page.items.map((item) {
      if (_locallyUnread.contains(item.id)) return item.copyWith(unread: true);
      if (_locallyRead.contains(item.id)) return item.copyWith(unread: false);
      return item;
    }).toList();

    return AirmiusPage<AirmiusNotification>(
      items: items,
      currentPage: page.currentPage,
      lastPage: page.lastPage,
      unreadCount: items.where((item) => item.unread).length,
    );
  }

  void _reload() {
    setState(() {
      _notificationsFuture = _loadNotifications();
    });
  }

  Future<void> _refreshNotifications() async {
    _reload();
    await _notificationsFuture;
  }

  Future<void> _toggleNotificationReadState(
    AirmiusNotification notification,
  ) async {
    final markUnread = !notification.unread;

    setState(() {
      if (markUnread) {
        _locallyUnread.add(notification.id);
        _locallyRead.remove(notification.id);
      } else {
        _locallyRead.add(notification.id);
        _locallyUnread.remove(notification.id);
      }

      final currentPage = _lastNotificationsPage;
      if (currentPage != null) {
        final adjustedPage = _applyLocalReadState(currentPage);
        _lastNotificationsPage = adjustedPage;
      }
    });

    try {
      final repository = AirmiusServicesScope.of(
        context,
      ).repositories.notifications;
      markUnread
          ? await repository.markAsUnread(notification.id)
          : await repository.markAsRead(notification.id);
    } catch (_) {
      // Keep the local state. A stale backend route cache or temporary transport
      // issue must not turn a successful UI action into a list loading error.
    }
  }

  Future<void> _markAllNotificationsRead() async {
    if (_markingAllRead) return;
    final page = _lastNotificationsPage;
    if (page == null || page.items.every((item) => !item.unread)) return;

    setState(() {
      _markingAllRead = true;
      _locallyRead.addAll(page.items.map((item) => item.id));
      _locallyUnread.clear();
      _lastNotificationsPage = _applyLocalReadState(page);
    });

    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.notifications.markAllAsRead();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('notifications.error')),
        ),
      );
    } finally {
      if (mounted) setState(() => _markingAllRead = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final body = _buildNotifications(context);
    if (widget.embedded) return body;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: _notificationHeader(context),
        foregroundColor: _notificationText(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('notifications.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('notifications.title'),
        subtitle: scope.t('notifications.subtitle'),
        showHeader: false,
        onRefresh: _refreshNotifications,
        child: body,
      ),
    );
  }

  Widget _buildNotifications(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<AirmiusPage<AirmiusNotification>>(
      future: _notificationsFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const _LoadingNotifications();
        }
        if (snapshot.hasError) {
          return _ErrorNotifications(onRetry: _reload);
        }

        final page = _lastNotificationsPage ?? snapshot.data;
        final allItems = page?.items ?? const <AirmiusNotification>[];
        final items = allItems;
        final unread = allItems.where((item) => item.unread).length;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (unread > 0) ...[
              AirmiusButton(
                label: scope.t('notifications.markAllRead'),
                icon: Icons.done_all_outlined,
                secondary: true,
                onPressed: _markingAllRead ? null : _markAllNotificationsRead,
              ),
              const SizedBox(height: 14),
            ],
            if (items.isEmpty)
              EmptyPanel(scope.t('notifications.empty'))
            else
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final item in items) ...[
                      _NotificationLine(
                        item: item,
                        onChanged: _reload,
                        onToggleReadState: _toggleNotificationReadState,
                      ),
                      const SizedBox(height: 10),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: scope.t('notificationSettings.channel.push'),
              icon: Icons.tune_outlined,
              secondary: true,
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const NotificationPreferencesScreen(),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

AirmiusThemePalette _notificationPalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark
        ? AirmiusThemePalette.dark
        : AirmiusThemePalette.air;
  }
}

bool _notificationDarkUi(BuildContext context) {
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

Color _notificationAccent(BuildContext context) {
  return _notificationPalette(context).primary;
}

Color _notificationHeader(BuildContext context) {
  final palette = _notificationPalette(context);
  return _notificationDarkUi(context)
      ? palette.darkHeader
      : palette.lightSurface;
}

Color _notificationSurface(BuildContext context) {
  final palette = _notificationPalette(context);
  return _notificationDarkUi(context)
      ? palette.darkSurface
      : palette.lightSurface;
}

Color _notificationText(BuildContext context) {
  final palette = _notificationPalette(context);
  return _notificationDarkUi(context) ? AirmiusColors.text : palette.lightText;
}

Color _notificationMuted(BuildContext context) {
  final palette = _notificationPalette(context);
  return _notificationDarkUi(context)
      ? AirmiusColors.muted
      : palette.lightMutedText;
}

Color _notificationMutedSoft(BuildContext context) {
  return Color.lerp(
        _notificationMuted(context),
        _notificationSurface(context),
        _notificationDarkUi(context) ? 0.22 : 0.28,
      ) ??
      _notificationMuted(context);
}

Color _notificationBorder(BuildContext context) {
  final palette = _notificationPalette(context);
  return _notificationDarkUi(context)
      ? AirmiusColors.border
      : palette.lightBorder;
}

class _NotificationLine extends StatefulWidget {
  const _NotificationLine({
    required this.item,
    required this.onChanged,
    required this.onToggleReadState,
  });

  final AirmiusNotification item;
  final VoidCallback onChanged;
  final Future<void> Function(AirmiusNotification notification)
  onToggleReadState;

  @override
  State<_NotificationLine> createState() => _NotificationLineState();
}

class _NotificationLineState extends State<_NotificationLine> {
  bool _opening = false;
  bool _busy = false;

  Future<void> _toggleReadState() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await widget.onToggleReadState(widget.item);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('notifications.error')),
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openNotification() async {
    if (_opening) return;
    setState(() => _opening = true);
    final notification = widget.item;
    try {
      if (!mounted) return;
      if (_isFileShareNotification(notification)) {
        await _openFileShareResponse(notification);
        return;
      }

      final teamInvitationId = _teamInvitationId(notification);
      if (teamInvitationId != null) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => TeamInvitationResponseScreen(
              invitationId: teamInvitationId,
              notification: notification,
            ),
          ),
        );
        widget.onChanged();
        return;
      }

      if (_isFriendInvitationNotification(notification)) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) =>
                const FriendsSocialGraphScreen(initialSection: 'received'),
          ),
        );
        widget.onChanged();
        return;
      }

      if (_isSportMatchingDecisionNotification(notification) &&
          notification.actionUrl != null &&
          notification.actionUrl!.isNotEmpty) {
        AirmiusDeepLinkNavigator.open(context, notification.actionUrl!);
        widget.onChanged();
        return;
      }

      if (_isMembershipRequestNotification(notification) &&
          notification.actionUrl != null &&
          notification.actionUrl!.isNotEmpty) {
        AirmiusDeepLinkNavigator.open(context, notification.actionUrl!);
        return;
      }

      if (notification.actionUrl != null &&
          notification.actionUrl!.isNotEmpty) {
        AirmiusDeepLinkNavigator.open(context, notification.actionUrl!);
        widget.onChanged();
        return;
      }

      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => NotificationDetailScreen(
            notification: notification,
            typeLabel: _labelForType(
              AirmiusScope.of(context),
              notification.type,
            ),
            icon: _iconForType(notification.type),
            onChanged: widget.onChanged,
          ),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('notifications.error')),
        ),
      );
    } finally {
      if (widget.item.unread && mounted) {
        try {
          await AirmiusServicesScope.of(
            context,
          ).repositories.notifications.markAsRead(widget.item.id);
          widget.onChanged();
        } catch (_) {
          // Opening the notification must not depend on the read-state request.
        }
      }
      if (mounted) setState(() => _opening = false);
    }
  }

  Future<void> _openFileShareResponse(AirmiusNotification notification) async {
    final scope = AirmiusScope.of(context);
    final fileId = _fileShareTargetFileId(notification);
    final accepted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      backgroundColor: airmiusAccentColor(
                        sheetContext,
                      ).withValues(alpha: 0.14),
                      child: Icon(
                        Icons.attach_file_outlined,
                        color: airmiusAccentColor(sheetContext),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            notification.title,
                            style: TextStyle(
                              color: airmiusTextColor(sheetContext),
                              fontWeight: FontWeight.w900,
                              fontSize: 18,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            notification.body,
                            style: TextStyle(
                              color: airmiusMutedColor(sheetContext),
                              height: 1.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                AirmiusButton(
                  label: scope.t('notifications.fileShareAccept'),
                  icon: Icons.check_circle_outline,
                  onPressed: () => Navigator.pop(sheetContext, true),
                ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: scope.t('notifications.fileShareDecline'),
                  icon: Icons.close_outlined,
                  secondary: true,
                  onPressed: fileId == null
                      ? null
                      : () => Navigator.pop(sheetContext, false),
                ),
              ],
            ),
          ),
        );
      },
    );

    if (accepted == null || !mounted) return;

    if (accepted) {
      await AirmiusServicesScope.of(
        context,
      ).repositories.notifications.markAsRead(notification.id);
      if (!mounted) return;
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => FileManagerScreen(
            initialScope: 'mine',
            initialSearch: _fileShareName(notification),
          ),
        ),
      );
      widget.onChanged();
      return;
    }

    if (fileId == null) return;
    try {
      final repositories = AirmiusServicesScope.of(context).repositories;
      await repositories.files.deleteFile(fileId);
      await repositories.notifications.delete(notification.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(scope.t('notifications.fileShareDeclined'))),
      );
      widget.onChanged();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(scope.t('notifications.error'))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final item = widget.item;
    final accent = _notificationAccent(context);
    final surface = _notificationSurface(context);
    final text = _notificationText(context);
    final muted = _notificationMuted(context);
    final mutedSoft = _notificationMutedSoft(context);
    final border = _notificationBorder(context);
    final unreadBackground =
        Color.lerp(
          surface,
          accent,
          _notificationDarkUi(context) ? 0.12 : 0.09,
        ) ??
        surface;
    return InkWell(
      onTap: _openNotification,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: item.unread ? unreadBackground : surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: item.unread ? accent.withValues(alpha: 0.45) : border,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(_iconForType(item.type), color: item.unread ? accent : muted),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.title,
                          style: TextStyle(
                            color: text,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      Text(
                        _shortTime(item.timeLabel),
                        style: TextStyle(color: mutedSoft, fontSize: 12),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(item.body, style: TextStyle(color: muted, height: 1.3)),
                  const SizedBox(height: 8),
                  StatusPill(
                    _labelForType(scope, item.type),
                    color: item.unread ? accent : mutedSoft,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              tooltip: item.unread
                  ? scope.t('notifications.markRead')
                  : scope.t('notifications.markUnread'),
              onPressed: _busy ? null : _toggleReadState,
              icon: Icon(
                item.unread
                    ? Icons.mark_email_read_outlined
                    : Icons.mark_email_unread_outlined,
                color: item.unread ? accent : muted,
              ),
            ),
            Icon(Icons.chevron_right, color: muted, size: 20),
          ],
        ),
      ),
    );
  }
}

class _LoadingNotifications extends StatelessWidget {
  const _LoadingNotifications();

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final accent = _notificationAccent(context);
    final muted = _notificationMuted(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: accent),
            ),
            const SizedBox(width: 12),
            Text(
              scope.t('status.loading'),
              style: TextStyle(color: muted, fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorNotifications extends StatelessWidget {
  const _ErrorNotifications({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final text = _notificationText(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
          const SizedBox(height: 10),
          Text(
            scope.t('notifications.error'),
            textAlign: TextAlign.center,
            style: TextStyle(color: text, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: scope.t('notifications.retry'),
            icon: Icons.refresh_outlined,
            onPressed: onRetry,
            secondary: true,
          ),
        ],
      ),
    );
  }
}

Map<String, String> _filters(AirmiusScope scope) => {
  'all': scope.t('notifications.all'),
  'club': scope.t('messages.club'),
  'chat': scope.t('messages.chat'),
  'payment': scope.t('notifications.payment'),
  'system': scope.t('notifications.system'),
};

String _typeKey(String rawType) {
  final type = rawType.toLowerCase();
  if (type.contains('club') ||
      type.contains('verein') ||
      type.contains('membership') ||
      type.contains('request')) {
    return 'club';
  }
  if (type.contains('chat') || type.contains('message')) return 'chat';
  if (type.contains('payment') ||
      type.contains('billing') ||
      type.contains('zahlung') ||
      type.contains('invoice') ||
      type.contains('commerce') ||
      type.contains('marketplace')) {
    return 'payment';
  }
  return 'system';
}

String _labelForType(AirmiusScope scope, String rawType) {
  return _filters(scope)[_typeKey(rawType)] ?? scope.t('notifications.system');
}

bool _isMembershipRequestNotification(AirmiusNotification notification) {
  return notification.type == 'club.membership_request_created' ||
      notification.type == 'club.membership_request_withdrawn' ||
      notification.type == 'club.membership_request_approved' ||
      notification.type == 'club.membership_request_declined';
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

bool _isSportMatchingDecisionNotification(AirmiusNotification notification) {
  return notification.type == 'sport_matching.decision' &&
      _intFromDynamic(notification.data['conversation_id']) != null;
}

bool _isFileShareNotification(AirmiusNotification notification) {
  return notification.type.toLowerCase() == 'file.shared' &&
      _fileShareTargetFileId(notification) != null;
}

int? _fileShareTargetFileId(AirmiusNotification notification) {
  return _intFromDynamic(
    notification.data['target_file_id'] ?? notification.data['file_id'],
  );
}

String _fileShareName(AirmiusNotification notification) {
  final raw = notification.data['file_name'] ?? notification.data['display_name'];
  if (raw is String && raw.trim().isNotEmpty) return raw.trim();
  final match = RegExp(r'„([^“]+)”').firstMatch(notification.body);
  return match?.group(1)?.trim() ?? '';
}

int? _intFromDynamic(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value');
}

IconData _iconForType(String rawType) {
  final type = _typeKey(rawType);
  if (type == 'club') return Icons.assignment_ind_outlined;
  if (type == 'chat') return Icons.chat_bubble_outline;
  if (type == 'payment') return Icons.payments_outlined;
  return Icons.notifications_outlined;
}

String _shortTime(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}
