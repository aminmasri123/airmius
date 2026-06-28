import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'notification_detail_screen.dart';
import 'notification_preferences_screen.dart';

class NotificationsCenterScreen extends StatefulWidget {
  const NotificationsCenterScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<NotificationsCenterScreen> createState() => _NotificationsCenterScreenState();
}

class _NotificationsCenterScreenState extends State<NotificationsCenterScreen> {
  String _filter = 'all';
  bool _notificationsLoaded = false;
  AirmiusPage<AirmiusNotification>? _lastNotificationsPage;
  final Set<int> _locallyRead = <int>{};
  final Set<int> _locallyUnread = <int>{};
  late Future<AirmiusPage<AirmiusNotification>> _notificationsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_notificationsLoaded) return;
    _notificationsLoaded = true;
    _notificationsFuture = _loadNotifications();
  }

  Future<AirmiusPage<AirmiusNotification>> _loadNotifications() async {
    final page = await AirmiusServicesScope.of(context).repositories.notifications.notifications();
    final adjustedPage = _applyLocalReadState(page);
    _lastNotificationsPage = adjustedPage;
    return adjustedPage;
  }

  AirmiusPage<AirmiusNotification> _applyLocalReadState(AirmiusPage<AirmiusNotification> page) {
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
    setState(() => _notificationsFuture = _loadNotifications());
  }

  Future<void> _toggleNotificationReadState(AirmiusNotification notification) async {
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
      final repository = AirmiusServicesScope.of(context).repositories.notifications;
      markUnread ? await repository.markAsUnread(notification.id) : await repository.markAsRead(notification.id);
    } catch (_) {
      // Keep the local state. A stale backend route cache or temporary transport
      // issue must not turn a successful UI action into a list loading error.
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final body = _buildNotifications(context);
    if (widget.embedded) return body;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(scope.t('notifications.title'), style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: scope.t('notifications.title'),
        subtitle: scope.t('notifications.subtitle'),
        showHeader: true,
        child: body,
      ),
    );
  }

  Widget _buildNotifications(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return RefreshIndicator(
      color: AirmiusColors.blue,
      backgroundColor: AirmiusColors.card,
      onRefresh: () async {
        _reload();
        await _notificationsFuture;
      },
      child: FutureBuilder<AirmiusPage<AirmiusNotification>>(
        future: _notificationsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const _ScrollableNotifications(child: _LoadingNotifications());
          }
          if (snapshot.hasError) {
            return _ScrollableNotifications(child: _ErrorNotifications(onRetry: _reload));
          }

          final page = _lastNotificationsPage ?? snapshot.data;
          final allItems = page?.items ?? const <AirmiusNotification>[];
          final items = allItems.where((item) => _filter == 'all' || _typeKey(item.type) == _filter).toList();
          final unread = allItems.where((item) => item.unread).length;
          final requests = allItems.where((item) => _typeKey(item.type) == 'club').length;
          final system = allItems.where((item) => _typeKey(item.type) == 'system').length;

          return _ScrollableNotifications(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(child: MetricCard(value: '$unread', label: scope.t('messages.unread'))),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: '$requests', label: scope.t('notifications.requests'))),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: '$system', label: scope.t('notifications.system'))),
                  ],
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final entry in _filters(scope).entries)
                      ChoiceChip(
                        selected: _filter == entry.key,
                        label: Text(entry.value),
                        onSelected: (_) => setState(() => _filter = entry.key),
                        selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _filter == entry.key ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _filter == entry.key ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ],
                ),
                const SizedBox(height: 14),
                if (items.isEmpty)
                  EmptyPanel(scope.t('notifications.empty'))
                else
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        SectionLabel(scope.t('notifications.title')),
                        const SizedBox(height: 10),
                        for (final item in items) ...[
                          _NotificationLine(item: item, onChanged: _reload, onToggleReadState: _toggleNotificationReadState),
                          const SizedBox(height: 10),
                        ],
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Push',
                  icon: Icons.tune_outlined,
                  secondary: true,
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationPreferencesScreen())),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ScrollableNotifications extends StatelessWidget {
  const _ScrollableNotifications({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: child,
    );
  }
}

class _NotificationLine extends StatefulWidget {
  const _NotificationLine({required this.item, required this.onChanged, required this.onToggleReadState});

  final AirmiusNotification item;
  final VoidCallback onChanged;
  final Future<void> Function(AirmiusNotification notification) onToggleReadState;

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
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('notifications.error'))));
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
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => NotificationDetailScreen(
            notification: notification,
            typeLabel: _labelForType(AirmiusScope.of(context), notification.type),
            icon: _iconForType(notification.type),
            onChanged: widget.onChanged,
          ),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('notifications.error'))));
    } finally {
      if (widget.item.unread && mounted) {
        try {
          await AirmiusServicesScope.of(context).repositories.notifications.markAsRead(widget.item.id);
          widget.onChanged();
        } catch (_) {
          // Opening the notification must not depend on the read-state request.
        }
      }
      if (mounted) setState(() => _opening = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final item = widget.item;
    return InkWell(
      onTap: _openNotification,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: item.unread ? AirmiusColors.blue.withValues(alpha: 0.10) : AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: item.unread ? AirmiusColors.blue.withValues(alpha: 0.45) : AirmiusColors.border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(_iconForType(item.type), color: item.unread ? AirmiusColors.blue : AirmiusColors.muted),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                      Text(_shortTime(item.timeLabel), style: const TextStyle(color: AirmiusColors.mutedSoft, fontSize: 12)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                  const SizedBox(height: 8),
                  StatusPill(_labelForType(scope, item.type), color: item.unread ? AirmiusColors.blue : AirmiusColors.mutedSoft),
                ],
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              tooltip: item.unread ? scope.t('notifications.markRead') : scope.t('notifications.markUnread'),
              onPressed: _busy ? null : _toggleReadState,
              icon: Icon(item.unread ? Icons.mark_email_read_outlined : Icons.mark_email_unread_outlined, color: item.unread ? AirmiusColors.blue : AirmiusColors.muted),
            ),
            const Icon(Icons.chevron_right, color: AirmiusColors.muted, size: 20),
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
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.blue)),
            const SizedBox(width: 12),
            Text(scope.t('status.loading'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
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
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
          const SizedBox(height: 10),
          Text(scope.t('notifications.error'), textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          AirmiusButton(label: scope.t('notifications.retry'), icon: Icons.refresh_outlined, onPressed: onRetry, secondary: true),
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
  if (type.contains('club') || type.contains('verein') || type.contains('membership') || type.contains('request')) return 'club';
  if (type.contains('chat') || type.contains('message')) return 'chat';
  if (type.contains('payment') || type.contains('billing') || type.contains('zahlung') || type.contains('invoice')) return 'payment';
  return 'system';
}

String _labelForType(AirmiusScope scope, String rawType) {
  return _filters(scope)[_typeKey(rawType)] ?? scope.t('notifications.system');
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
