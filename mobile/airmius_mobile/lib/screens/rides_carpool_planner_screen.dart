import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_event_attendance_screen.dart';
import 'support_helpdesk_screen.dart';

class RidesCarpoolPlannerScreen extends StatefulWidget {
  const RidesCarpoolPlannerScreen({super.key});

  @override
  State<RidesCarpoolPlannerScreen> createState() =>
      _RidesCarpoolPlannerScreenState();
}

class _RidesCarpoolPlannerScreenState extends State<RidesCarpoolPlannerScreen> {
  String _tab = 'Alle';
  bool _showDriver = true;
  bool _showPassengers = true;
  bool _showCosts = true;
  bool _showSafety = true;

  final List<_RideItem> _items = const [
    _RideItem(
      title: 'Zum Freitagstraining',
      area: 'Offen',
      body:
          'Fahrt zum Lauftraining mit Treffpunkt, Uhrzeit und zwei freien Plaetzen.',
      status: '2 Plaetze',
      meta: 'Kleinblittersdorf - 18:00',
      icon: Icons.directions_car_outlined,
      color: AirmiusColors.blue,
    ),
    _RideItem(
      title: 'Event Saisonauftakt',
      area: 'Gebucht',
      body:
          'Mitfahrt für Vereins-Event, Fahrer bestätigt und Chat vorbereitet.',
      status: 'Gebucht',
      meta: 'Sa 15.06 - 15:15',
      icon: Icons.event_available_outlined,
      color: AirmiusColors.green,
    ),
    _RideItem(
      title: 'Rückfahrt Halle West',
      area: 'Suche',
      body: 'User sucht Mitfahrgelegenheit nach Training oder Event.',
      status: 'Suche',
      meta: 'Halle West - 21:00',
      icon: Icons.transfer_within_a_station_outlined,
      color: AirmiusColors.amber,
    ),
    _RideItem(
      title: 'Fahrt storniert',
      area: 'Storniert',
      body: 'Stornierung mit Hinweis, Ersatzsuche und Supportoption.',
      status: 'Storniert',
      meta: 'Support',
      icon: Icons.cancel_outlined,
      color: AirmiusColors.red,
    ),
  ];

  List<_RideItem> get _visibleItems =>
      _items.where((item) => _tab == 'Alle' || item.area == _tab).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(
                          title: 'Fahrgemeinschaften',
                          subtitle:
                              'Rides, Fahrer, Mitfahrer, Plaetze, Treffpunkt, Kosten, Sicherheit und Stornierung.',
                        ),
                        const SizedBox(height: 16),
                        _RidesHero(
                          onCreate: () => _toast('Fahrt erstellen vorbereitet'),
                        ),
                        const SizedBox(height: 16),
                        _ChoicePanel(
                          title: 'Status',
                          value: _tab,
                          values: const [
                            'Alle',
                            'Offen',
                            'Gebucht',
                            'Suche',
                            'Storniert',
                          ],
                          onChanged: (value) => setState(() => _tab = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Ride-Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Fahrer anzeigen',
                                subtitle:
                                    'Fahrerprofil, Kontaktfreigabe und Fahrzeughinweis sichtbar machen.',
                                value: _showDriver,
                                onChanged: (value) =>
                                    setState(() => _showDriver = value),
                              ),
                              _SwitchRow(
                                title: 'Mitfahrer anzeigen',
                                subtitle:
                                    'Freie Plaetze, Anfragen und bestätigte Mitfahrer anzeigen.',
                                value: _showPassengers,
                                onChanged: (value) =>
                                    setState(() => _showPassengers = value),
                              ),
                              _SwitchRow(
                                title: 'Kostenhinweis anzeigen',
                                subtitle:
                                    'Kostenbeteiligung, Vereinshinweis und Fairnessregel vorbereiten.',
                                value: _showCosts,
                                onChanged: (value) =>
                                    setState(() => _showCosts = value),
                              ),
                              _SwitchRow(
                                title: 'Sicherheit anzeigen',
                                subtitle:
                                    'Notfallkontakt, Melden, Stornieren und Chatkontext sichtbar machen.',
                                value: _showSafety,
                                onChanged: (value) =>
                                    setState(() => _showSafety = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _RideCard(
                            item: item,
                            onOpen: () => _toast(
                              '${item.title}: Fahrt-Detail vorbereitet',
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty)
                          const EmptyPanel(
                            'Keine Fahrten für diesen Status gefunden.',
                          ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Fahrt erstellen',
                                icon: Icons.add_circle_outline,
                                onPressed: () =>
                                    _toast('Fahrt erstellen vorbereitet'),
                              ),
                              AirmiusButton(
                                label: 'Events',
                                icon: Icons.event_available_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ClubEventAttendanceScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Support',
                                icon: Icons.support_agent_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => SupportHelpdeskScreen(),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _RidesHero extends StatelessWidget {
  const _RidesHero({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF10243B), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('RIDES'),
                    SizedBox(height: 4),
                    Text(
                      'Gemeinsam zum Training',
                      style: TextStyle(
                        color: AirmiusColors.text,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: 'Neu',
                icon: Icons.add_circle_outline,
                onPressed: onCreate,
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Das Rides-Webmodul wird als mobile UI abgebildet: Fahrten, Fahrer, Mitfahrer, Plaetze, Treffpunkte, Kostenhinweise und Sicherheit.',
            style: TextStyle(
              color: AirmiusColors.muted,
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(
                child: MetricCard(value: '4', label: 'Fahrten'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '2', label: 'Plaetze'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '1', label: 'Suche'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(
                color: value == item ? AirmiusColors.text : AirmiusColors.muted,
                fontWeight: FontWeight.w900,
              ),
              side: BorderSide(
                color: value == item
                    ? AirmiusColors.blue
                    : AirmiusColors.border,
              ),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeThumbColor: AirmiusColors.blue,
          ),
        ],
      ),
    );
  }
}

class _RideCard extends StatelessWidget {
  const _RideCard({required this.item, required this.onOpen});

  final _RideItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: item.color.withValues(alpha: .5)),
            ),
            child: Icon(item.icon, color: item.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(item.status, color: item.color),
                const SizedBox(height: 8),
                Text(
                  item.meta,
                  style: const TextStyle(
                    color: AirmiusColors.blue,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  item.body,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ),
        ],
      ),
    );
  }
}

class _RideItem {
  const _RideItem({
    required this.title,
    required this.area,
    required this.body,
    required this.status,
    required this.meta,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
