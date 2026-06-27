import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MapLocationRouteParitySuiteScreen extends StatefulWidget {
  const MapLocationRouteParitySuiteScreen({super.key});

  @override
  State<MapLocationRouteParitySuiteScreen> createState() => _MapLocationRouteParitySuiteScreenState();
}

class _MapLocationRouteParitySuiteScreenState extends State<MapLocationRouteParitySuiteScreen> {
  String _area = 'Sportkarte';
  String _permission = 'Beim Nutzen';
  bool _showPrivacyHint = true;
  bool _offlineMap = false;
  bool _liveTracking = false;

  static const _areas = ['Sportkarte', 'Events', 'Rides', 'Vereine', 'Public Orte'];
  static const _permissions = ['Aus', 'Beim Nutzen', 'Einmalig', 'Immer'];

  static const _flows = <_MapFlow>[
    _MapFlow(
      area: 'Sportkarte',
      title: 'Sportkarte entdecken',
      route: 'Auth/Dashboard/SportMap/Index',
      body: 'Orte, Routen, Trainingsspots, Filter, Distanz, Kategorie, Datenschutz und Melden als mobile Kartenansicht.',
      status: 'Map',
      icon: Icons.map_outlined,
      primary: 'Karte öffnen',
      secondary: 'Filter',
      color: AirmiusColors.blue,
    ),
    _MapFlow(
      area: 'Sportkarte',
      title: 'Route starten',
      route: 'RouteDetail',
      body: 'Route, Distanz, Dauer, Schwierigkeit, Offline-Hinweis, Start-CTA und Live-Track-Zustand.',
      status: 'Route',
      icon: Icons.alt_route_outlined,
      primary: 'Route starten',
      secondary: 'Offline speichern',
      color: AirmiusColors.green,
    ),
    _MapFlow(
      area: 'Events',
      title: 'Event-Ort und Treffpunkt',
      route: 'Events/Show',
      body: 'Trainingsevent mit Ort, Treffpunkt, Karte, Kalender, Teilnahme, Guardian-Gate und Navigation.',
      status: 'Event',
      icon: Icons.event_outlined,
      primary: 'Navigation',
      secondary: 'Teilnahme',
      color: AirmiusColors.green,
    ),
    _MapFlow(
      area: 'Rides',
      title: 'Fahrgemeinschaft Treffpunkt',
      route: 'Rides/Index',
      body: 'Mitfahrt, Treffpunkt, Fahrer, freie Plaetze, Kontaktfreigabe, Guardian-Schutz und sichere Standortanzeige.',
      status: 'Ride',
      icon: Icons.directions_car_outlined,
      primary: 'Mitfahrt',
      secondary: 'Treffpunkt',
      color: AirmiusColors.blue,
    ),
    _MapFlow(
      area: 'Vereine',
      title: 'Vereinsadresse sichtbar machen',
      route: 'Clubs/Profile + ClubVisibilitySettings',
      body: 'Club-Adresse, Public-Sichtbarkeit, Kontakt, Anfahrt, Standortgenauigkeit und Datenschutzoptionen.',
      status: 'Club',
      icon: Icons.groups_outlined,
      primary: 'Adresse zeigen',
      secondary: 'Sichtbarkeit',
      color: AirmiusColors.amber,
    ),
    _MapFlow(
      area: 'Public Orte',
      title: 'Standort einreichen',
      route: 'PublicLocationSubmission',
      body: 'Gast-Standortvorschlag mit Kategorie, Kontakt, Karte, Moderationsstatus, Datenschutz und Korrekturmeldung.',
      status: 'Submission',
      icon: Icons.add_location_alt_outlined,
      primary: 'Einreichen',
      secondary: 'Korrektur',
      color: AirmiusColors.green,
    ),
  ];

  List<_MapFlow> get _visibleFlows => _flows.where((flow) => flow.area == _area).toList();

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
          title: 'Map Location Route Parity',
          subtitle: 'Karten, Orte, Routen und Standortfreigaben als mobile UI.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(area: _area, permission: _permission, offlineMap: _offlineMap, liveTracking: _liveTracking),
              const SizedBox(height: 16),
              _MapPreview(
                area: _area,
                permission: _permission,
                offlineMap: _offlineMap,
                liveTracking: _liveTracking,
                onOpen: () => openUiAction(
                  context,
                  title: 'Kartenansicht',
                  body: 'Mobile Kartenansicht für $_area mit Standortfreigabe $_permission, Offline-Karte $_offlineMap und Live-Tracking $_liveTracking.',
                  status: 'Map',
                  icon: Icons.map_outlined,
                ),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Kartenbereich',
                items: _areas,
                active: _area,
                onChanged: (value) => setState(() => _area = value),
                color: AirmiusColors.blue,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Standortfreigabe',
                items: _permissions,
                active: _permission,
                onChanged: (value) => setState(() => _permission = value),
                color: AirmiusColors.green,
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showPrivacyHint: _showPrivacyHint,
                offlineMap: _offlineMap,
                liveTracking: _liveTracking,
                onPrivacy: (value) => setState(() => _showPrivacyHint = value),
                onOffline: (value) => setState(() => _offlineMap = value),
                onLive: (value) => setState(() => _liveTracking = value),
              ),
              const SizedBox(height: 16),
              if (_showPrivacyHint)
                _PrivacyPanel(
                  permission: _permission,
                  onOpen: () => openUiAction(
                    context,
                    title: 'Standort Datenschutz',
                    body: 'Standortfreigabe, Zweckbindung, Genauigkeit, Guardian-Regel, Sichtbarkeit und Widerruf als mobile Datenschutzkarte.',
                    status: 'Privacy',
                    icon: Icons.privacy_tip_outlined,
                  ),
                ),
              if (_showPrivacyHint) const SizedBox(height: 16),
              for (final flow in _visibleFlows) ...[
                _MapFlowCard(flow: flow),
                const SizedBox(height: 12),
              ],
              if (_visibleFlows.isEmpty) const EmptyPanel('Keine Kartenflows für diesen Bereich sichtbar.'),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Map Location Parity',
                  body: 'Sportkarte, Events, Fahrgemeinschaften, Vereinsadresse, Public-Orte, Routen, Permissions, Datenschutz und Offline-Karten sind als mobile UI vorbereitet.',
                  status: 'Location',
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
    required this.area,
    required this.permission,
    required this.offlineMap,
    required this.liveTracking,
  });

  final String area;
  final String permission;
  final bool offlineMap;
  final bool liveTracking;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('MAPS & LOCATION'),
          const SizedBox(height: 8),
          const Text(
            'Standort muss nuetzlich sein, ohne sich unsicher anzufuehlen.',
            style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          const Text(
            'Flutter bereitet Sportkarte, Routen, Treffpunkte, Vereinsadressen, Standortvorschläge, Berechtigungen, Datenschutz und Offline-Zustaende als native mobile UI vor.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: area, label: 'Bereich'),
              _Metric(value: permission, label: 'Permission'),
              _Metric(value: offlineMap ? 'An' : 'Aus', label: 'Offline'),
              _Metric(value: liveTracking ? 'Live' : 'Still', label: 'Tracking'),
            ],
          ),
        ],
      ),
    );
  }
}

class _MapPreview extends StatelessWidget {
  const _MapPreview({
    required this.area,
    required this.permission,
    required this.offlineMap,
    required this.liveTracking,
    required this.onOpen,
  });

  final String area;
  final String permission;
  final bool offlineMap;
  final bool liveTracking;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.blue,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 180,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: AirmiusColors.borderStrong),
              gradient: const LinearGradient(
                colors: [Color(0xFF0F2233), Color(0xFF123F5C), Color(0xFF0D1A28)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Stack(
              children: [
                Positioned(left: 22, top: 22, child: _MapDot(color: AirmiusColors.blue, label: 'A')),
                Positioned(right: 34, top: 42, child: _MapDot(color: AirmiusColors.green, label: 'B')),
                Positioned(left: 88, bottom: 34, child: _MapDot(color: AirmiusColors.amber, label: 'C')),
                Positioned(
                  left: 18,
                  right: 18,
                  bottom: 18,
                  child: Row(
                    children: [
                      StatusPill(area, color: AirmiusColors.blue),
                      const SizedBox(width: 8),
                      StatusPill(permission, color: AirmiusColors.green),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Text(area, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text(
            'Offline-Karte: ${offlineMap ? 'aktiv' : 'aus'} · Live Tracking: ${liveTracking ? 'aktiv' : 'aus'}',
            style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Karte simulieren', icon: Icons.map_outlined, onPressed: onOpen),
        ],
      ),
    );
  }
}

class _MapDot extends StatelessWidget {
  const _MapDot({
    required this.color,
    required this.label,
  });

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 38,
      height: 38,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .22),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color, width: 2),
      ),
      child: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w900)),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.onChanged,
    required this.color,
  });

  final String title;
  final List<String> items;
  final String active;
  final ValueChanged<String> onChanged;
  final Color color;

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

class _RulesPanel extends StatelessWidget {
  const _RulesPanel({
    required this.showPrivacyHint,
    required this.offlineMap,
    required this.liveTracking,
    required this.onPrivacy,
    required this.onOffline,
    required this.onLive,
  });

  final bool showPrivacyHint;
  final bool offlineMap;
  final bool liveTracking;
  final ValueChanged<bool> onPrivacy;
  final ValueChanged<bool> onOffline;
  final ValueChanged<bool> onLive;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Location-Regeln',
      subtitle: 'Diese Optionen werden später von Android/iOS Permissions und Laravel-API-Zwecken gesteuert.',
      children: [
        _SwitchLine(title: 'Datenschutzhinweis anzeigen', value: showPrivacyHint, onChanged: onPrivacy),
        _SwitchLine(title: 'Offline-Kartenmodus', value: offlineMap, onChanged: onOffline),
        _SwitchLine(title: 'Live-Tracking simulieren', value: liveTracking, onChanged: onLive),
      ],
    );
  }
}

class _PrivacyPanel extends StatelessWidget {
  const _PrivacyPanel({
    required this.permission,
    required this.onOpen,
  });

  final String permission;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.green,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.privacy_tip_outlined, color: AirmiusColors.green, size: 30),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Standortfreigabe: $permission', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 5),
                const Text('User sehen Zweck, Genauigkeit, Sichtbarkeit, Guardian-Hinweis und Widerruf direkt in der App.', style: TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                AirmiusButton(label: 'Datenschutz anzeigen', icon: Icons.privacy_tip_outlined, secondary: true, onPressed: onOpen),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MapFlowCard extends StatelessWidget {
  const _MapFlowCard({required this.flow});

  final _MapFlow flow;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: flow.color.withValues(alpha: .55),
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
                  color: flow.color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: flow.color.withValues(alpha: .55)),
                ),
                child: Icon(flow.icon, color: flow.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(flow.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 5),
                    Text(flow.route, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              StatusPill(flow.status, color: flow.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(flow.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: flow.primary,
                icon: flow.icon,
                onPressed: () => openUiAction(
                  context,
                  title: flow.primary,
                  body: '${flow.title}: ${flow.body}\n\nRoute: ${flow.route}',
                  status: flow.status,
                  icon: flow.icon,
                ),
              ),
              AirmiusButton(
                label: flow.secondary,
                icon: Icons.tune_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: flow.secondary,
                  body: 'Filter, Datenschutz, Berechtigung, Offline, Route und API-Zustand für ${flow.title}.',
                  status: 'Location Detail',
                  icon: Icons.tune_outlined,
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
      title: 'Karten-/Location-Paritaet',
      subtitle: 'Was aus Web-Orten mobil übernommen wird.',
      children: [
        const _CheckLine('Sportkarte, Routen, Events, Fahrgemeinschaften und Public-Orte haben eigene mobile Kartenzustaende.'),
        const _CheckLine('Standortfreigabe zeigt Zweck, Genauigkeit, Datenschutz, Guardian-Regeln und Widerruf.'),
        const _CheckLine('Treffpunkte, Navigation, Offline-Karten und Live-Tracking werden als UI-Zustaende vorbereitet.'),
        const _CheckLine('Vereinsadresse und Standortvorschläge bleiben mit Sichtbarkeit und Moderation verbunden.'),
        const SizedBox(height: 12),
        AirmiusButton(label: 'Location-Paritaet markieren', icon: Icons.fact_check_outlined, onPressed: onOpen),
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

class _MapFlow {
  const _MapFlow({
    required this.area,
    required this.title,
    required this.route,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String area;
  final String title;
  final String route;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
