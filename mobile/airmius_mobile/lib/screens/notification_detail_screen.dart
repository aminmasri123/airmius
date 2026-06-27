import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'notification_preferences_screen.dart';

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

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
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
                      Icon(widget.icon, color: AirmiusColors.blue, size: 34),
                      const SizedBox(width: 12),
                      Expanded(child: Text(widget.notification.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(widget.typeLabel),
                      StatusPill(widget.notification.unread ? scope.t('messages.unread') : scope.t('status.ready'), color: widget.notification.unread ? AirmiusColors.green : AirmiusColors.blue),
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
                    onPressed: null,
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
