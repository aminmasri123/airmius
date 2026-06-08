import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LocationMapFacilitySuiteScreen extends StatefulWidget {
  const LocationMapFacilitySuiteScreen({super.key});

  @override
  State<LocationMapFacilitySuiteScreen> createState() => _LocationMapFacilitySuiteScreenState();
}

class _LocationMapFacilitySuiteScreenState extends State<LocationMapFacilitySuiteScreen> {
  String contextType = 'Training';
  bool showPublicAddress = true;
  bool enableRoutePlanning = true;
  bool pickupPoints = true;
  bool privacySafeLocation = true;

  @override
  Widget build(BuildContext context) {
    final places = [
      const _PlaceRow(
        title: 'Sporthalle Kleinblittersdorf',
        status: 'Training',
        body: 'Trainingsort mit Adresse, Hallenhinweis, Check-in, Treffpunkt und Routenlink.',
        icon: Icons.sports_handball_outlined,
        color: AirmiusColors.blue,
      ),
      const _PlaceRow(
        title: 'ZBB Clubhaus',
        status: 'Verein',
        body: 'Vereinsadresse, Kontakt, Abholung fuer Clubshop und Treffpunkt fuer Veranstaltungen.',
        icon: Icons.home_work_outlined,
        color: AirmiusColors.green,
      ),
      const _PlaceRow(
        title: 'Auswaertsspiel Saarbruecken',
        status: 'Route',
        body: 'Zielort mit Fahrgemeinschaft, freien Plaetzen, Treffpunkt und Abfahrtszeit.',
        icon: Icons.route_outlined,
        color: AirmiusColors.amber,
      ),
      const _PlaceRow(
        title: 'Abholung Trainingsshirt',
        status: 'Pickup',
        body: 'Marketplace-Abholung mit Zeitfenster, Ansprechpartner und Benachrichtigung.',
        icon: Icons.storefront_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Orte & Karten',
      subtitle: 'Vereinsorte, Routen und Treffpunkte',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('LOCATION CENTER'),
                const SizedBox(height: 8),
                const Text(
                  'Die mobile App braucht Orte fuer Vereinsprofile, Training, Events, Fahrgemeinschaften, Abholung und sichere Standortfreigabe.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Orte'),
                    Metric(value: 'Route', label: 'Planung'),
                    Metric(value: 'Pickup', label: 'Abholung'),
                    Metric(value: 'Privacy', label: 'Schutz'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('KONTEXT'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Training', label: Text('Training')),
                    ButtonSegment(value: 'Event', label: Text('Event')),
                    ButtonSegment(value: 'Team', label: Text('Team')),
                    ButtonSegment(value: 'Shop', label: Text('Shop')),
                  ],
                  selected: {contextType},
                  onSelectionChanged: (value) => setState(() => contextType = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REGELN'),
                const SizedBox(height: 8),
                _LocationSwitch(title: 'Adresse oeffentlich anzeigen', value: showPublicAddress, color: AirmiusColors.blue, onChanged: (value) => setState(() => showPublicAddress = value)),
                _LocationSwitch(title: 'Routenplanung aktivieren', value: enableRoutePlanning, color: AirmiusColors.green, onChanged: (value) => setState(() => enableRoutePlanning = value)),
                _LocationSwitch(title: 'Abholpunkte anzeigen', value: pickupPoints, color: AirmiusColors.amber, onChanged: (value) => setState(() => pickupPoints = value)),
                _LocationSwitch(title: 'Standort datenschutzsicher', value: privacySafeLocation, color: AirmiusColors.pink, onChanged: (value) => setState(() => privacySafeLocation = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final place in places) ...[
            _PlaceCard(place: place),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Kontext: $contextType. Spaeter verbindet die API Ort, Verein, Team, Event, Abholung, Fahrgemeinschaft, Sichtbarkeit und Benachrichtigung.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Route vorbereiten',
                  icon: Icons.map_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Route vorbereiten',
                    body: 'Diese UI bereitet Karten, Routen, Treffpunkte, Abholung, Fahrgemeinschaften und Standortfreigaben fuer die spaetere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.map_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PlaceRow {
  const _PlaceRow({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _LocationSwitch extends StatelessWidget {
  const _LocationSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _PlaceCard extends StatelessWidget {
  const _PlaceCard({required this.place});

  final _PlaceRow place;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: place.icon, color: place.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(place.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(place.status, color: place.color),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(place.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Karte',
                icon: Icons.map_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Karte oeffnen',
                  body: 'Kartenansicht, Adresse, Treffpunkt und externe Navigation werden fuer die spaetere API vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.map_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Route',
                icon: Icons.route_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Route planen',
                  body: 'Route, Fahrgemeinschaft, Abfahrtszeit und Treffpunkt werden als mobiler Standortfluss vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.route_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Teilen',
                icon: Icons.share_location_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Ort teilen',
                  body: 'Standorte koennen spaeter rollen- und datenschutzsicher mit Teams, Events oder Mitgliedern geteilt werden.',
                  status: 'UI vorbereitet',
                  icon: Icons.share_location_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
