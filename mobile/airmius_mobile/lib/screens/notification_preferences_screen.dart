import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class NotificationPreferencesScreen extends StatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  State<NotificationPreferencesScreen> createState() => _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState extends State<NotificationPreferencesScreen> {
  final Map<String, bool> _channels = {
    'Push-Benachrichtigungen': true,
    'E-Mail-Erinnerungen': true,
    'Chat-Erwähnungen': true,
    'Vereinsanfragen': true,
    'Zahlungen & Rechnungen': true,
    'Marketing & Sponsoren': false,
  };

  String _quietTime = '22:00 - 07:00';

  @override
  Widget build(BuildContext context) {
    final accent = _notificationPreferenceAccent(context);
    final text = _notificationPreferenceText(context);
    final muted = _notificationPreferenceMuted(context);
    final surfaceSoft = _notificationPreferenceSurfaceSoft(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(backgroundColor: _notificationPreferenceHeader(context), foregroundColor: text, surfaceTintColor: Colors.transparent, title: const Text('Notification Settings', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Benachrichtigungen einstellen',
        subtitle: 'Push, E-Mail, Chat, Zahlungen, Events, Ruhezeiten und Bulk-Aktionen',
        trailing: const StatusPill('Push'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Notification Center'),
            const SizedBox(height: 8),
            Text('Du entscheidest, welche Signale wichtig sind.', style: TextStyle(color: text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text('Diese UI bereitet Push-Preferences, E-Mail-Regeln, Ruhezeiten, Read-State und serverseitige Benachrichtigungsfilter vor.', style: TextStyle(color: muted, height: 1.4)),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '4', label: 'Ungelesen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '6', label: 'Kanaele')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2FA', label: 'Sicher'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Kanaele'),
            const SizedBox(height: 8),
            for (final entry in _channels.entries)
              SwitchListTile(
                value: entry.value,
                onChanged: (value) => setState(() => _channels[entry.key] = value),
                activeColor: accent,
                contentPadding: EdgeInsets.zero,
                title: Text(entry.key, style: TextStyle(color: text, fontWeight: FontWeight.w900)),
                subtitle: Text(entry.value ? 'Aktiv' : 'Ausgeschaltet', style: TextStyle(color: muted)),
              ),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Ruhezeit & Prioritaet'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _quietTime,
              dropdownColor: surfaceSoft,
              decoration: const InputDecoration(labelText: 'Ruhezeit'),
              items: const ['Keine', '22:00 - 07:00', '20:00 - 08:00', 'Nur Wochenende'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _quietTime = value ?? _quietTime),
            ),
            const SizedBox(height: 12),
            const _PriorityLine(icon: Icons.priority_high_outlined, title: 'Hohe Prioritaet', body: 'Sicherheitsmeldungen, Zahlungsprobleme und Guardian Consent trotzdem anzeigen.', status: 'Immer'),
            const _PriorityLine(icon: Icons.done_all_outlined, title: 'Bulk-Aktionen', body: 'Alle als gelesen markieren, archivieren oder nach Typ filtern.', status: 'Bereit'),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Einstellungen speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Einstellungen speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
        ]),
      ),
    );
  }
}

class _PriorityLine extends StatelessWidget {
  const _PriorityLine({required this.icon, required this.title, required this.body, required this.status});

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
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: accent),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: TextStyle(color: text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: TextStyle(color: muted, height: 1.35))])),
        StatusPill(status),
      ]),
    );
  }
}

AirmiusThemePalette _notificationPreferencePalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark ? AirmiusThemePalette.dark : AirmiusThemePalette.air;
  }
}

bool _notificationPreferenceDarkUi(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  if (palette == AirmiusThemePalette.dark) return true;
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
  return _notificationPreferenceDarkUi(context) ? palette.darkHeader : palette.lightSurface;
}

Color _notificationPreferenceSurfaceSoft(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context) ? palette.darkSurfaceSoft : palette.lightSurfaceSoft;
}

Color _notificationPreferenceText(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context) ? AirmiusColors.text : palette.lightText;
}

Color _notificationPreferenceMuted(BuildContext context) {
  final palette = _notificationPreferencePalette(context);
  return _notificationPreferenceDarkUi(context) ? AirmiusColors.muted : palette.lightMutedText;
}
