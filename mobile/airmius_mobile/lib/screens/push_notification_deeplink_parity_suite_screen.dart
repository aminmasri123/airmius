import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class PushNotificationDeeplinkParitySuiteScreen extends StatefulWidget {
  const PushNotificationDeeplinkParitySuiteScreen({super.key});

  @override
  State<PushNotificationDeeplinkParitySuiteScreen> createState() => _PushNotificationDeeplinkParitySuiteScreenState();
}

class _PushNotificationDeeplinkParitySuiteScreenState extends State<PushNotificationDeeplinkParitySuiteScreen> {
  String _channel = 'Push';
  String _route = 'Mitgliedsantrag';
  bool _permissionGranted = true;
  bool _quietHours = false;
  bool _badgeCount = true;

  static const _channels = ['Push', 'In-App', 'E-Mail', 'Chat'];
  static const _routes = ['Mitgliedsantrag', 'Chat', 'Event', 'Zahlung', 'Moderation', 'Guardian'];

  static const _notifications = <_NotificationFlow>[
    _NotificationFlow(
      route: 'Mitgliedsantrag',
      title: 'Neue Mitgliedschaftsanfrage',
      source: 'ClubRequestInbox',
      body: 'Verein wird informiert, wenn ein User eine Anfrage sendet, Dokumente hochlaedt oder die Anfrage zurueckzieht.',
      status: 'Club Admin',
      icon: Icons.assignment_ind_outlined,
      primary: 'Zur Inbox',
      secondary: 'Antrag',
      color: AirmiusColors.green,
    ),
    _NotificationFlow(
      route: 'Chat',
      title: 'Neue Chat-Nachricht',
      source: 'Chat/Index',
      body: 'Deep Link fuehrt direkt zur Konversation, zeigt Lesestatus, Mute-Regel, Teilnehmer und Attachment-Hinweis.',
      status: 'Message',
      icon: Icons.forum_outlined,
      primary: 'Chat oeffnen',
      secondary: 'Mute',
      color: AirmiusColors.blue,
    ),
    _NotificationFlow(
      route: 'Event',
      title: 'Training beginnt bald',
      source: 'Events/Show',
      body: 'Event-Erinnerung springt zu Teilnahme, Treffpunkt, Kalender, Guardian-Gate und Navigation.',
      status: 'Reminder',
      icon: Icons.event_available_outlined,
      primary: 'Event',
      secondary: 'Kalender',
      color: AirmiusColors.green,
    ),
    _NotificationFlow(
      route: 'Zahlung',
      title: 'Zahlung offen',
      source: 'Billing / Membership Payments',
      body: 'Beitrag, Rechnung, Banktransfer, Mahnung, Beleg und Zahlungsstatus werden als sichere Deep-Link-Karte gefuehrt.',
      status: 'Payment',
      icon: Icons.payments_outlined,
      primary: 'Zahlung',
      secondary: 'Beleg',
      color: AirmiusColors.amber,
    ),
    _NotificationFlow(
      route: 'Moderation',
      title: 'Moderationsfall eskaliert',
      source: 'Admin/Moderation',
      body: 'Admin-Push springt zu Report, Entscheidung, Audit, Sperre, Meldungsgrund und Rueckmeldung.',
      status: 'Admin',
      icon: Icons.flag_outlined,
      primary: 'Case',
      secondary: 'Audit',
      color: AirmiusColors.red,
    ),
    _NotificationFlow(
      route: 'Guardian',
      title: 'Elternfreigabe erforderlich',
      source: 'GuardianConsent/Pending',
      body: 'Guardian oder minderjaehriger User wird zu Freigabe, Ablehnung, Kinderdaten, Ablaufdatum und Kontakt gefuehrt.',
      status: 'Consent',
      icon: Icons.verified_user_outlined,
      primary: 'Freigabe',
      secondary: 'Status',
      color: AirmiusColors.blue,
    ),
  ];

  List<_NotificationFlow> get _visibleNotifications => _notifications.where((item) => item.route == _route).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Push Notification Deeplink Parity',
          subtitle: 'Native Benachrichtigungen, App-Badges und Sprungziele.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                channel: _channel,
                route: _route,
                permissionGranted: _permissionGranted,
                badgeCount: _badgeCount,
              ),
              const SizedBox(height: 16),
              _PermissionPanel(
                permissionGranted: _permissionGranted,
                quietHours: _quietHours,
                badgeCount: _badgeCount,
                onPermission: (value) => setState(() => _permissionGranted = value),
                onQuiet: (value) => setState(() => _quietHours = value),
                onBadge: (value) => setState(() => _badgeCount = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Kanal',
                items: _channels,
                active: _channel,
                color: AirmiusColors.blue,
                onChanged: (value) => setState(() => _channel = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Deep-Link-Ziel',
                items: _routes,
                active: _route,
                color: AirmiusColors.green,
                onChanged: (value) => setState(() => _route = value),
              ),
              const SizedBox(height: 16),
              _InboxPreview(
                channel: _channel,
                route: _route,
                quietHours: _quietHours,
                onOpen: () => openUiAction(
                  context,
                  title: 'Notification Routing',
                  body: 'Kanal $_channel, Ziel $_route, Permission $_permissionGranted, Quiet Hours $_quietHours und App Badge $_badgeCount als mobile Notification-UI pruefen.',
                  status: 'Routing',
                  icon: Icons.notifications_none_outlined,
                ),
              ),
              const SizedBox(height: 16),
              for (final notification in _visibleNotifications) ...[
                _NotificationCard(notification: notification, channel: _channel, quietHours: _quietHours),
                const SizedBox(height: 12),
              ],
              if (_visibleNotifications.isEmpty) const EmptyPanel('Keine Benachrichtigung fuer dieses Ziel sichtbar.'),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Push Deep-Link Parity',
                  body: 'Push Permission, In-App Inbox, E-Mail-Fallback, Chat, App Badges, Quiet Hours und Deep-Link Routing sind als mobile UI vorbereitet.',
                  status: 'Notifications',
                  icon: Icons.fact_check_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.channel,
    required this.route,
    required this.permissionGranted,
    required this.badgeCount,
  });

  final String channel;
  final String route;
  final bool permissionGranted;
  final bool badgeCount;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('PUSH & DEEP LINKS'),
          const SizedBox(height: 8),
          const Text(
            'Benachrichtigungen muessen direkt zur richtigen Aktion fuehren.',
            style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          const Text(
            'Flutter bereitet Push, In-App Inbox, E-Mail-Fallback, Chat, App-Badges, Ruhezeiten und Deep-Link-Ziele so vor, dass Laravel spaeter nur noch echte Events liefern muss.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: channel, label: 'Kanal'),
              _Metric(value: route, label: 'Ziel'),
              _Metric(value: permissionGranted ? 'Erlaubt' : 'Aus', label: 'Push'),
              _Metric(value: badgeCount ? 'Badge' : 'Still', label: 'App Icon'),
            ],
          ),
        ],
      ),
    );
  }
}

class _PermissionPanel extends StatelessWidget {
  const _PermissionPanel({
    required this.permissionGranted,
    required this.quietHours,
    required this.badgeCount,
    required this.onPermission,
    required this.onQuiet,
    required this.onBadge,
  });

  final bool permissionGranted;
  final bool quietHours;
  final bool badgeCount;
  final ValueChanged<bool> onPermission;
  final ValueChanged<bool> onQuiet;
  final ValueChanged<bool> onBadge;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Mobile Notification-Regeln',
      subtitle: 'Diese Flags werden spaeter durch Android/iOS Permission, User Settings und Laravel Events gesteuert.',
      children: [
        _SwitchLine(title: 'Push-Berechtigung aktiv', value: permissionGranted, onChanged: onPermission),
        _SwitchLine(title: 'Ruhezeiten beachten', value: quietHours, onChanged: onQuiet),
        _SwitchLine(title: 'App-Badge anzeigen', value: badgeCount, onChanged: onBadge),
      ],
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final List<String> items;
  final String active;
  final Color color;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => ChoiceChip(
                  selected: active == item,
                  label: Text(item),
                  onSelected: (_) => onChanged(item),
                  selectedColor: color.withValues(alpha: .24),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: active == item ? color : AirmiusColors.border),
                  labelStyle: TextStyle(color: active == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _InboxPreview extends StatelessWidget {
  const _InboxPreview({
    required this.channel,
    required this.route,
    required this.quietHours,
    required this.onOpen,
  });

  final String channel;
  final String route;
  final bool quietHours;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: quietHours ? AirmiusColors.amber : AirmiusColors.blue,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: AirmiusColors.blue.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AirmiusColors.blue.withValues(alpha: .55)),
            ),
            child: const Icon(Icons.notifications_none_outlined, color: AirmiusColors.blue),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$channel · $route', style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900)),
                const SizedBox(height: 5),
                Text(quietHours ? 'Wird gesammelt und nach Ruhezeit angezeigt.' : 'Wird sofort zugestellt und routed zum Zielscreen.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                AirmiusButton(label: 'Routing pruefen', icon: Icons.open_in_new, secondary: true, onPressed: onOpen),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.channel,
    required this.quietHours,
  });

  final _NotificationFlow notification;
  final String channel;
  final bool quietHours;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: notification.color.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: notification.color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: notification.color.withValues(alpha: .55)),
                ),
                child: Icon(notification.icon, color: notification.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(notification.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 5),
                    Text(notification.source, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              StatusPill(notification.status, color: notification.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(notification.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: notification.primary,
                icon: notification.icon,
                onPressed: () => openUiAction(
                  context,
                  title: notification.primary,
                  body: '${notification.title}: ${notification.body}\n\nKanal: $channel, Quiet Hours: $quietHours.',
                  status: notification.status,
                  icon: notification.icon,
                ),
              ),
              AirmiusButton(
                label: notification.secondary,
                icon: Icons.link_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: notification.secondary,
                  body: 'Deep Link, Permission, Badge, Mute, Audit und Fallback fuer ${notification.title}.',
                  status: 'Deep Link',
                  icon: Icons.link_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Notification-Paritaet',
      subtitle: 'Was aus Web-Notifications mobil erweitert wird.',
      children: [
        const _CheckLine('Push, In-App, E-Mail und Chat nutzen ein gemeinsames Routing-Muster.'),
        const _CheckLine('Jede Notification fuehrt direkt zum passenden Screen und zeigt Fallbacks.'),
        const _CheckLine('Ruhezeiten, Mute, App-Badges und Permission-Status bleiben sichtbar.'),
        const _CheckLine('Vereine werden ueber Antraege, Rueckzuege und Dokumente informiert.'),
        const SizedBox(height: 12),
        AirmiusButton(label: 'Notification-Paritaet markieren', icon: Icons.fact_check_outlined, onPressed: onOpen),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
          Switch(value: value, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.check_circle_outline, color: AirmiusColors.green, size: 19),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700, height: 1.35))),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.value,
    required this.label,
  });

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AirmiusColors.bg.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _NotificationFlow {
  const _NotificationFlow({
    required this.route,
    required this.title,
    required this.source,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String route;
  final String title;
  final String source;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
