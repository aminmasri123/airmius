import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class EventAdminDetailScreen extends StatefulWidget {
  const EventAdminDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<EventAdminDetailScreen> createState() => _EventAdminDetailScreenState();
}

class _EventAdminDetailScreenState extends State<EventAdminDetailScreen> {
  String _visibility = 'Verein';
  bool _waitlist = true;
  bool _eventChat = true;
  bool _reminder = true;
  bool _cancellationAllowed = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Eventdetail', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Teilnahme, Warteliste, Chat, Absagen und Erinnerungen',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Event'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Titel', hint: 'Training, Treffen oder Wettkampf', icon: Icons.event_available_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Ort & Zeit', hint: 'Sportplatz, Samstag 15:00', icon: Icons.location_on_outlined),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '18', label: 'Zusagen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Warteliste')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Offen'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit'),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final item in const ['Privat', 'Team', 'Verein', 'Öffentlich'])
                ChoiceChip(
                  selected: _visibility == item,
                  label: Text(item),
                  onSelected: (_) => setState(() => _visibility = item),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _visibility == item ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _visibility == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
            const SizedBox(height: 8),
            SwitchListTile(value: _waitlist, onChanged: (value) => setState(() => _waitlist = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Warteliste aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Teilnehmer können nachrücken.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _eventChat, onChanged: (value) => setState(() => _eventChat = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Eventchat aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Chat bleibt mit Termin und Teilnehmern verknuepft.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _reminder, onChanged: (value) => setState(() => _reminder = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Erinnerungen senden', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Push vor Eventbeginn vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _cancellationAllowed, onChanged: (value) => setState(() => _cancellationAllowed = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Absage erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Teilnehmer dürfen Status ändern.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: 0.45), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Teilnehmer & Warteliste'),
            SizedBox(height: 8),
            Text('18 Zusagen, 3 offen, 2 Warteliste. Massenaktionen und Reminder werden später über die Laravel-API ausgefuehrt.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Teilnehmer'), StatusPill('Warteliste'), StatusPill('Reminder')]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Event speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Event speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Reminder senden', icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Reminder senden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.notifications_active_outlined)),
          ]),
        ]),
      ),
    );
  }
}
