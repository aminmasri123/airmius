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
  bool _busy = false;
  late Future<AirmiusPage<AirmiusNotification>> _notificationsFuture;

  @override
  void initState() {
    super.initState();
    _notificationsFuture = _loadNotifications();
  }

  Future<AirmiusPage<AirmiusNotification>> _loadNotifications() {
    return AirmiusServicesScope.of(context).repositories.notifications.notifications();
  }

  void _reload() {
    setState(() => _notificationsFuture = _loadNotifications());
  }

  Future<void> _markAllRead() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(context).repositories.notifications.markAllAsRead();
      if (!mounted) return;
      setState(() {
        _busy = false;
        _notificationsFuture = _loadNotifications();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('notifications.error'))));
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

          final allItems = snapshot.data?.items ?? const <AirmiusNotification>[];
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
                          _NotificationLine(item: item, onChanged: _reload),
                          const SizedBox(height: 10),
                        ],
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    AirmiusButton(
                      label: _busy ? scope.t('status.loading') : scope.t('notifications.markAllRead'),
                      icon: Icons.done_all_outlined,
                      secondary: true,
                      onPressed: _busy ? null : _markAllRead,
                    ),
                    AirmiusButton(
                      label: 'Push',
                      icon: Icons.tune_outlined,
                      secondary: true,
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationPreferencesScreen())),
                    ),
                  ],
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

class _NotificationLine extends StatelessWidget {
  const _NotificationLine({required this.item, required this.onChanged});

  final AirmiusNotification item;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return InkWell(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => NotificationDetailScreen(
            notification: item,
            typeLabel: _labelForType(scope, item.type),
            icon: _iconForType(item.type),
            onChanged: onChanged,
          ),
        ),
      ),
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
