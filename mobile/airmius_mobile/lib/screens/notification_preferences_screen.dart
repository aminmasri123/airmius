import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_push_device_registry.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../widgets/airmius_widgets.dart';

class NotificationPreferencesScreen extends StatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  State<NotificationPreferencesScreen> createState() =>
      _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState
    extends State<NotificationPreferencesScreen> {
  static const _pushChannel = 'Push-Benachrichtigungen';

  final Map<String, bool> _channels = {
    _pushChannel: false,
    'E-Mail-Erinnerungen': true,
    'Chat-Erwähnungen': true,
    'Vereinsanfragen': true,
    'Zahlungen & Rechnungen': true,
    'Marketing & Sponsoren': false,
  };

  String _quietTime = '22:00 - 07:00';
  bool _savingPush = false;
  String? _pushStatus;
  AirmiusPushDeviceRegistration? _pushRegistration;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadPushState());
  }

  Future<void> _loadPushState() async {
    final registry = AirmiusServicesScope.of(context).pushDevices;
    final enabled = await registry.optInEnabled();
    final registration = await registry.lastRegistration();
    if (!mounted) return;
    setState(() {
      _channels[_pushChannel] = enabled;
      _pushRegistration = registration;
      _pushStatus = registration == null
          ? null
          : 'Registriert: ${registration.provider}/${registration.platform}';
    });
  }

  Future<void> _setPushEnabled(bool enabled) async {
    final services = AirmiusServicesScope.of(context);
    final session = services.authState.session;
    if (session == null || !session.isAuthenticated) {
      setState(() {
        _channels[_pushChannel] = false;
        _pushStatus = 'Bitte zuerst anmelden.';
      });
      return;
    }

    setState(() {
      _savingPush = true;
      _pushStatus = enabled
          ? 'Push wird registriert...'
          : 'Push wird deaktiviert...';
    });

    try {
      final result = await services.pushDevices.setOptIn(
        enabled: enabled,
        client: services.clientForSession(session),
      );
      final registration =
          result.registration ?? await services.pushDevices.lastRegistration();
      if (!mounted) return;
      setState(() {
        _channels[_pushChannel] = enabled;
        _pushRegistration = registration;
        _pushStatus = _pushStatusFor(result, registration);
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _channels[_pushChannel] = !enabled;
        _pushStatus = error.userMessage;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _channels[_pushChannel] = !enabled;
        _pushStatus = error.toString();
      });
    } finally {
      if (mounted) setState(() => _savingPush = false);
    }
  }

  String _pushStatusFor(
    AirmiusPushRegistrationResult result,
    AirmiusPushDeviceRegistration? registration,
  ) {
    if (result.status == 'missing_token') {
      return result.message ?? 'Push-Token ist noch nicht verfuegbar.';
    }
    if (result.status == 'disabled') return 'Deaktiviert';
    final device = registration;
    if (device == null) return 'Aktiv';
    return 'Registriert: ${device.provider}/${device.platform}';
  }

  @override
  Widget build(BuildContext context) {
    final accent = _notificationPreferenceAccent(context);
    final text = _notificationPreferenceText(context);
    final muted = _notificationPreferenceMuted(context);
    final surfaceSoft = _notificationPreferenceSurfaceSoft(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: _notificationPreferenceHeader(context),
        foregroundColor: text,
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Notification Settings',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Benachrichtigungen einstellen',
        subtitle:
            'Push, E-Mail, Chat, Zahlungen, Events, Ruhezeiten und Bulk-Aktionen',
        trailing: const StatusPill('Push'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Notification Center'),
                  const SizedBox(height: 8),
                  Text(
                    'Du entscheidest, welche Signale wichtig sind.',
                    style: TextStyle(
                      color: text,
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Diese UI bereitet Push-Preferences, E-Mail-Regeln, Ruhezeiten, Read-State und serverseitige Benachrichtigungsfilter vor.',
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '4', label: 'Ungelesen'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '6', label: 'Kanaele'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '2FA', label: 'Sicher'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Kanaele'),
                  const SizedBox(height: 8),
                  for (final entry in _channels.entries)
                    SwitchListTile(
                      value: entry.value,
                      onChanged: _savingPush && entry.key == _pushChannel
                          ? null
                          : (value) {
                              if (entry.key == _pushChannel) {
                                _setPushEnabled(value);
                                return;
                              }
                              setState(() => _channels[entry.key] = value);
                            },
                      activeThumbColor: accent,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        entry.key,
                        style: TextStyle(
                          color: text,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      subtitle: Text(
                        _channelSubtitle(entry),
                        style: TextStyle(color: muted),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Ruhezeit & Prioritaet'),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _quietTime,
                    dropdownColor: surfaceSoft,
                    decoration: const InputDecoration(labelText: 'Ruhezeit'),
                    items:
                        const [
                              'Keine',
                              '22:00 - 07:00',
                              '20:00 - 08:00',
                              'Nur Wochenende',
                            ]
                            .map(
                              (item) => DropdownMenuItem(
                                value: item,
                                child: Text(item),
                              ),
                            )
                            .toList(),
                    onChanged: (value) =>
                        setState(() => _quietTime = value ?? _quietTime),
                  ),
                  const SizedBox(height: 12),
                  const _PriorityLine(
                    icon: Icons.priority_high_outlined,
                    title: 'Hohe Prioritaet',
                    body:
                        'Sicherheitsmeldungen, Zahlungsprobleme und Guardian Consent trotzdem anzeigen.',
                    status: 'Immer',
                  ),
                  const _PriorityLine(
                    icon: Icons.done_all_outlined,
                    title: 'Bulk-Aktionen',
                    body:
                        'Alle als gelesen markieren, archivieren oder nach Typ filtern.',
                    status: 'Bereit',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: 'Einstellungen speichern',
              icon: Icons.save_outlined,
              onPressed: () => openUiAction(
                context,
                title: 'Einstellungen speichern',
                body:
                    'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.',
                status: 'UI bereit',
                icon: Icons.save_outlined,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _channelSubtitle(MapEntry<String, bool> entry) {
    if (entry.key != _pushChannel) {
      return entry.value ? 'Aktiv' : 'Ausgeschaltet';
    }
    if (_savingPush) return _pushStatus ?? 'Wird gespeichert...';
    if (_pushStatus != null) return _pushStatus!;
    if (_pushRegistration != null) return 'Registriert';
    return entry.value ? 'Aktiv' : 'Ausgeschaltet';
  }
}

class _PriorityLine extends StatelessWidget {
  const _PriorityLine({
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
  });

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    final accent = _notificationPreferenceAccent(context);
    final text = _notificationPreferenceText(context);
    final muted = _notificationPreferenceMuted(context);
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: accent),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(color: text, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(body, style: TextStyle(color: muted, height: 1.35)),
              ],
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}

AirmiusThemePalette _notificationPreferencePalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark
        ? AirmiusThemePalette.dark
        : AirmiusThemePalette.air;
  }
}

bool _notificationPreferenceDarkUi(BuildContext context) {
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

Color _notificationPreferenceAccent(BuildContext context) {
  return _notificationPreferencePalette(context).primary;
}

Color _notificationPreferenceHeader(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context)
      ? palette.darkHeader
      : palette.lightSurface;
}

Color _notificationPreferenceSurfaceSoft(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context)
      ? palette.darkSurfaceSoft
      : palette.lightSurfaceSoft;
}

Color _notificationPreferenceText(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context)
      ? AirmiusColors.text
      : palette.lightText;
}

Color _notificationPreferenceMuted(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context)
      ? AirmiusColors.muted
      : palette.lightMutedText;
}
