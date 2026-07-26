import 'package:flutter/material.dart';
import 'dart:convert';

import '../core/airmius_api_client.dart';
import '../core/airmius_push_device_registry.dart';
import '../core/airmius_l10n.dart';
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
  static const _pushChannel = 'push';
  static const _channelsStorageKey = 'airmius.notifications.channels.v1';
  static const _quietTimeStorageKey = 'airmius.notifications.quiet_time.v1';

  final Map<String, bool> _channels = {
    _pushChannel: false,
    'email': true,
    'chat': true,
    'club': true,
    'billing': true,
    'marketing': false,
  };

  String _quietTime = 'late';
  bool _savingPush = false;
  bool _savingPreferences = false;
  bool _preferencesLoaded = false;
  String? _pushStatus;
  AirmiusPushDeviceRegistration? _pushRegistration;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadPreferences());
  }

  Future<void> _loadPreferences() async {
    if (_preferencesLoaded) return;
    _preferencesLoaded = true;
    final services = AirmiusServicesScope.of(context);
    final store = services.pushDevices.store;
    final rawChannels = await store.readString(_channelsStorageKey);
    final rawQuietTime = await store.readString(_quietTimeStorageKey);
    if (rawChannels != null && rawChannels.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(rawChannels);
        if (decoded is Map) {
          for (final channel in _channels.keys) {
            if (decoded[channel] is bool) {
              _channels[channel] = decoded[channel] as bool;
            }
          }
        }
      } catch (_) {
        // Keep safe defaults when a previous preference payload is malformed.
      }
    }
    if (rawQuietTime != null &&
        const {'none', 'late', 'early', 'weekend'}.contains(rawQuietTime)) {
      _quietTime = rawQuietTime;
    }
    try {
      final payload = await _client.settings();
      final data = payload['data'];
      final preferences = data is Map ? data['notification_preferences'] : null;
      if (preferences is Map) {
        final channels = preferences['channels'];
        if (channels is Map) {
          for (final channel in _channels.keys) {
            if (channels[channel] is bool) {
              _channels[channel] = channels[channel] as bool;
            }
          }
        }
        final quietTime = preferences['quiet_time']?.toString();
        if (quietTime != null &&
            const {'none', 'late', 'early', 'weekend'}.contains(quietTime)) {
          _quietTime = quietTime;
        }
      }
    } catch (_) {
      // Keep the local preference cache available when the account is offline
      // or this screen is opened before authentication has finished.
    }
    final registry = services.pushDevices;
    final enabled = await registry.optInEnabled();
    final registration = await registry.lastRegistration();
    if (!mounted) return;
    setState(() {
      _channels[_pushChannel] = enabled;
      _pushRegistration = registration;
      _pushStatus = registration == null
          ? null
          : '${_t('notificationSettings.registered')}: ${registration.provider}/${registration.platform}';
    });
  }

  Future<void> _setPushEnabled(bool enabled) async {
    final services = AirmiusServicesScope.of(context);
    final session = services.authState.session;
    if (session == null || !session.isAuthenticated) {
      setState(() {
        _channels[_pushChannel] = false;
        _pushStatus = _t('notificationSettings.loginRequired');
      });
      return;
    }

    setState(() {
      _savingPush = true;
      _pushStatus = enabled
          ? _t('notificationSettings.pushRegistering')
          : _t('notificationSettings.pushDisabling');
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
        _pushStatus = _t('common.errorDetails');
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
      return result.message ?? _t('notificationSettings.tokenMissing');
    }
    if (result.status == 'disabled') return _t('notificationSettings.disabled');
    final device = registration;
    if (device == null) return _t('notificationSettings.active');
    return '${_t('notificationSettings.registered')}: ${device.provider}/${device.platform}';
  }

  String _t(String key) => AirmiusScope.of(context).t(key);

  String _channelLabel(String key) => _t('notificationSettings.channel.$key');

  String _quietTimeLabel(String key) => _t('notificationSettings.quiet.$key');

  Future<void> _savePreferences() async {
    if (_savingPreferences) return;
    setState(() => _savingPreferences = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final store = services.pushDevices.store;
      await store.writeString(_channelsStorageKey, jsonEncode(_channels));
      await store.writeString(_quietTimeStorageKey, _quietTime);
      final session = services.authState.session;
      if (session?.isAuthenticated == true) {
        try {
          await _client.updateSettings({
            'notification_channels': Map<String, bool>.from(_channels),
            'notification_quiet_time': _quietTime,
          });
        } on AirmiusApiException catch (error) {
          if (!mounted) return;
          ScaffoldMessenger.of(context)
            ..hideCurrentSnackBar()
            ..showSnackBar(
              SnackBar(
                content: Text(
                  '${_t('notificationSettings.serverSaveFailed')}: ${error.userMessage}',
                ),
              ),
            );
          return;
        }
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text(_t('notificationSettings.saved'))),
        );
    } finally {
      if (mounted) setState(() => _savingPreferences = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
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
        title: Text(
          t('notificationSettings.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('notificationSettings.save'),
            onPressed: _savingPreferences ? null : _savePreferences,
            icon: const Icon(Icons.save_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('notificationSettings.title'),
        subtitle: t('notificationSettings.subtitle'),
        trailing: StatusPill(t('notificationSettings.local')),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('notificationSettings.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('notificationSettings.headline'),
                    style: TextStyle(
                      color: text,
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    t('notificationSettings.body'),
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: '${_channels.length}',
                    label: t('notificationSettings.channels'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '${_channels.values.where((value) => value).length}',
                    label: t('notificationSettings.active'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: _quietTime == 'none'
                        ? t('notificationSettings.off')
                        : t('notificationSettings.on'),
                    label: t('notificationSettings.quietTime'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('notificationSettings.channels')),
                  const SizedBox(height: 8),
                  for (final entry in _channels.entries)
                    Material(
                      color: Colors.transparent,
                      child: SwitchListTile(
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
                          _channelLabel(entry.key),
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
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('notificationSettings.priorityTitle')),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _quietTime,
                    dropdownColor: surfaceSoft,
                    decoration: InputDecoration(
                      labelText: t('notificationSettings.quietTime'),
                    ),
                    items: const ['none', 'late', 'early', 'weekend']
                        .map(
                          (item) => DropdownMenuItem(
                            value: item,
                            child: Text(_quietTimeLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _quietTime = value ?? _quietTime),
                  ),
                  const SizedBox(height: 12),
                  _PriorityLine(
                    icon: Icons.priority_high_outlined,
                    title: t('notificationSettings.highPriority'),
                    body: t('notificationSettings.highPriorityBody'),
                    status: t('notificationSettings.always'),
                  ),
                  _PriorityLine(
                    icon: Icons.done_all_outlined,
                    title: t('notificationSettings.bulkActions'),
                    body: t('notificationSettings.bulkActionsBody'),
                    status: t('notificationSettings.ready'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: _savingPreferences
                  ? t('notificationSettings.saving')
                  : t('notificationSettings.save'),
              icon: Icons.save_outlined,
              onPressed: _savingPreferences ? null : _savePreferences,
            ),
          ],
        ),
      ),
    );
  }

  String _channelSubtitle(MapEntry<String, bool> entry) {
    if (entry.key != _pushChannel) {
      return entry.value
          ? _t('notificationSettings.active')
          : _t('notificationSettings.disabled');
    }
    if (_savingPush) {
      return _pushStatus ?? _t('notificationSettings.saving');
    }
    if (_pushStatus != null) return _pushStatus!;
    if (_pushRegistration != null) return _t('notificationSettings.registered');
    return entry.value
        ? _t('notificationSettings.active')
        : _t('notificationSettings.disabled');
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
